<?php

namespace Posti_Warehouse;

// Prevent direct access to this script
defined('ABSPATH') || exit;

if (!class_exists(__NAMESPACE__ . '\Posti_Warehouse_Frontend')) {

	class Posti_Warehouse_Frontend {
		public $core = null;
		
		public $api = null;
		
		private $errors = array();

		public function __construct(Posti_Warehouse_Core $plugin) {
			$this->core = $plugin;
			$this->api = $this->core->getApi();
		}

		public function load() {
			add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
			add_action('woocommerce_review_order_after_shipping', array($this, 'pickup_point_field_html'));
			add_action('woocommerce_order_details_after_order_table', array($this, 'display_order_data'));
			add_action('woocommerce_checkout_update_order_meta', array($this, 'update_order_meta_pickup_point_field'));
			add_action('woocommerce_checkout_process', array($this, 'validate_checkout'));
			add_action('woocommerce_checkout_create_order_shipping_item', array($this, 'add_metadata_to_order_shipping_method'), 10, 4);

			add_action('wp_ajax_posti_warehouse_save_pickup_point_info_to_session', array($this, 'save_pickup_point_info_to_session'), 10);
			add_action('wp_ajax_nopriv_posti_warehouse_save_pickup_point_info_to_session', array($this, 'save_pickup_point_info_to_session'), 10);

			add_action('wp_ajax_posti_warehouse_use_custom_address_for_pickup_point', array($this, 'use_custom_address_for_pickup_point'), 10);
			add_action('wp_ajax_nopriv_posti_warehouse_use_custom_address_for_pickup_point', array($this, 'use_custom_address_for_pickup_point'), 10);

			add_filter('woocommerce_checkout_fields', array($this, 'add_checkout_fields'));
			add_filter('woocommerce_admin_order_data_after_shipping_address', array($this, 'render_checkout_fields'));
		}

		public function render_checkout_fields( $order) {
			?>
			<div style="clear: both;">
				<p>
			<?php echo esc_html(Posti_Warehouse_Text::field_phone()); ?>
					: <?php echo esc_html(get_post_meta($order->get_id(), '_shipping_phone', true)); ?>
					<br>
			<?php echo esc_html(Posti_Warehouse_Text::field_email()); ?>
					: <?php echo esc_html(get_post_meta($order->get_id(), '_shipping_email', true)); ?>
			</div>
			<?php
		}

		public function add_checkout_fields( $fields) {
			// Add shipping phone is billing phone exists
			if (isset($fields['billing']['billing_phone'])) {
				$fields['shipping']['shipping_phone'] = $fields['billing']['billing_phone'];
				$fields['shipping']['shipping_phone']['required'] = 0;
			}

			// Add shipping email if billing email exists
			if (isset($fields['billing']['billing_email'])) {
				$fields['shipping']['shipping_email'] = $fields['billing']['billing_email'];
				$fields['shipping']['shipping_email']['required'] = 0;
			}

			return $fields;
		}

		public function save_pickup_point_info_to_session() {
			if (!check_ajax_referer($this->add_prefix('-pickup_point_update'), 'security')) {
				return;
			}

			$pickup_point_id = isset($_POST['pickup_point_id']) ? sanitize_text_field($_POST['pickup_point_id']) : null;
			$this->set_pickup_point_session_data(
					array_replace(
							$this->get_pickup_point_session_data(),
							array(
								'pickup_point' => $pickup_point_id,
							)
					)
			);
		}

		public function reset_pickup_point_session_data() {
			WC()->session->set(str_replace('wc_', 'woo_', $this->core->prefix) . '_pickup_point', null);
		}

		public function set_pickup_point_session_data( $data) {
			WC()->session->set(str_replace('wc_', 'woo_', $this->core->prefix) . '_pickup_point', $data);
		}

		public function get_pickup_point_session_data() {
			return WC()->session->get(
							str_replace('wc_', 'woo_', $this->core->prefix) . '_pickup_point',
							array(
								'address' => WC()->customer->get_shipping_address(),
								'postcode' => WC()->customer->get_shipping_postcode(),
								'country' => WC()->customer->get_shipping_country(),
								'custom_address' => null,
								'pickup_point' => null,
							)
			);
		}

		public function use_custom_address_for_pickup_point() {
			if (!check_ajax_referer($this->add_prefix('-pickup_point_update'), 'security')) {
				return;
			}

			$address = !empty($_POST['address']) ? sanitize_text_field($_POST['address']) : null;
			$this->set_pickup_point_session_data(
					array_replace(
							$this->get_pickup_point_session_data(),
							array(
								'custom_address' => $address,
								'pickup_point' => null,
							)
					)
			);

			// Rest is handled in Posti_Warehouse_Frontend\fetch_pickup_point_options
		}

		/**
		 * Add an error with a specified error message.
		 *
		 * @param string $message A message containing details about the error.
		 */
		public function add_error( $message) {
			if (!empty($message)) {
				array_push($this->errors, $message);
			}
		}

		/**
		 * Display error in woocommerce
		 */
		public function display_error( $error = null) {
			if (!$error) {
				$error = Posti_Warehouse_Text::error_generic();
			}

			wc_add_notice($error, 'error');
		}

		/**
		 * Enqueue frontend-specific styles and scripts.
		 */
		public function enqueue_scripts() {

			if (!is_checkout()) {
				return;
			}

			wp_enqueue_style($this->core->prefix . '_css', plugins_url('assets/css/frontend.css', dirname(__FILE__)), array(), $this->core->version);
			wp_enqueue_script($this->core->prefix . '_js', plugins_url('assets/js/frontend.js', dirname(__FILE__)), array('jquery'), $this->core->version, true);
			wp_localize_script(
					$this->core->prefix . '_js',
					'posti_warehouseData',
					array(
						'privatePickupPointConfirm' => Posti_Warehouse_Text::confirm_selection(),
					)
			);
		}

		/**
		 * Update the order meta with posti_warehouse_pickup_point field value
		 * Example value from checkout page: "DB Schenker: R-KIOSKI TRE AMURI (#6681)"
		 *
		 * @param int $order_id The id of the order to update
		 */
		public function update_order_meta_pickup_point_field( $order_id) {
			// NOTE: nonce verification is not needed here and will fail when guest submits order with "Create an account?"
			// https://github.com/woocommerce/woocommerce/issues/44779

			$key = $this->add_prefix('_pickup_point');
			$pickup_point = isset($_POST[$key]) ? sanitize_text_field($_POST[$key]) : array();

			$key_id = $this->add_prefix('_pickup_point_id');
			
			if (empty($pickup_point)) {
				$pickup_point = WC()->session->get($key_id);
				WC()->session->set($key_id, null);
			}

			if (!empty($pickup_point)) {
				update_post_meta($order_id, '_' . $key, sanitize_text_field($pickup_point));
				// Find string like '(#6681)'
				preg_match('/\(#[A-Za-z0-9\-]+\)/', $pickup_point, $matches);
				// Cut the number out from a string of the form '(#6681)'
				$pickup_point_id = ( !empty($matches) ) ? substr($matches[0], 2, -1) : '';
				update_post_meta($order_id, '_' . $key_id, sanitize_text_field($pickup_point_id));
			}
		}

		private function shipping_needs_pickup_points() {
			$chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
			if (empty($chosen_shipping_methods)) {
				return false;
			}

			$packages = WC()->shipping()->get_packages();
			$shipping_rate = null;

			// Find first chosen shipping method that has shipping_rates
			foreach ($chosen_shipping_methods as $chosen_shipping_id) {
				foreach ($packages as $package) {
					if (isset($package['rates'][$chosen_shipping_id])) {
						$shipping_rate = $package['rates'][$chosen_shipping_id];
					}
				}

				if (null !== $shipping_rate) {
					break;
				}
			}

			if (null === $shipping_rate) {
				return false;
			}

			$shipping_method_providers = array();
			$point_types = array();
			$shipment_meta_data = $shipping_rate->get_meta_data();

			$settings = Posti_Warehouse_Settings::get_shipping_settings();
			$pickup_points = isset($settings['pickup_points']) ? json_decode($settings['pickup_points'], true) : array();
			if (isset($shipment_meta_data['service_code'])) {
				$shipping_method_id = $shipment_meta_data['service_code'];

				if (!empty($pickup_points[$shipping_method_id]['service'])) {
					$shipping_method_providers[] = $shipping_method_id;
				}

			} else {

				$temp_array = explode(':', $chosen_shipping_id); // for php 5.6 compatibility

				if (count($temp_array) < 2) {
					// no instance_id available -> return
					return false;
				}

				$instance_id = $temp_array[1];
				if (!empty($pickup_points[$instance_id]) && !empty($pickup_points[$instance_id]['service'])) {
					$service = $pickup_points[$instance_id]['service'];
					if ('__PICKUPPOINTS__' === $service) {
						foreach ($pickup_points[$instance_id] as $shipping_method => $shipping_method_data) {
							if (isset($shipping_method_data['active']) && 'yes' === $shipping_method_data['active']) {
								$shipping_method_providers[] = $shipping_method;
							}
						}
					} else {
						if (isset($pickup_points[$instance_id][$service]['pickuppoints']) && 'yes' === $pickup_points[$instance_id][$service]['pickuppoints']) {
							$shipping_method_providers[] = $service;
							$point_types['pickup_points'] = true;
						}
						
						if (isset($pickup_points[$instance_id][$service]['storepoints']) && 'yes' === $pickup_points[$instance_id][$service]['storepoints']) {
							$shipping_method_providers[] = $service;
							$point_types['store_points'] = true;
						}
						
						if (isset($pickup_points[$instance_id][$service]['pickuppoints_hideoutdoors']) && 'yes' === $pickup_points[$instance_id][$service]['pickuppoints_hideoutdoors']) {
							$point_types['hideoutdoors'] = true;
						}
					}
				}
			}

			// Bail out if the shipping method is not one of the pickup point services
			if (empty($shipping_method_providers)) {
				return false;
			}

			return array('services' => $shipping_method_providers, 'types' => $point_types);
		}

		/*
		 * Customize the layout of the checkout screen so that there is a section
		 * where the pickup point can be defined. Don't use the woocommerce_checkout_fields
		 * filter, it only lists fields without values, and we need to know the postcode.
		 * Also the WooCommerce_checkout_fields has separate billing and shipping address
		 * listings, when we want to have only one single pickup point per order.
		 */

		public function pickup_point_field_html() {
			if (!wp_doing_ajax()) {
				return;
			}

			$error_msg = '';
			$select_field = null;
			$custom_field = null;
			$custom_field_title = '';
			$custom_field_desc = '';

			$shipping_method_providers = $this->shipping_needs_pickup_points();

			echo '<input type="hidden" name="' . esc_attr($this->core->prefix) . '_validate_pickup_points" value="' . ( false === $shipping_method_providers ? 'false' : 'true' ) . '" />';

			if (false === $shipping_method_providers) {
				return;
			}

			$selected_payment_method = WC()->session->get('chosen_payment_method');
			$is_klarna = 'kco' === $selected_payment_method;

			$shipping_postcode = WC()->customer->get_shipping_postcode();
			$shipping_address = WC()->customer->get_shipping_address();
			$shipping_country = WC()->customer->get_shipping_country();
			$shipping_city = WC()->customer->get_shipping_city();
			$store_country_code = get_option('woocommerce_default_country');
			$store_postal_code = get_option('woocommerce_store_postcode');

			$session = $this->get_pickup_point_session_data();
			$stale_items = array_filter(
					$session,
				function ( $v, $k) use ( $shipping_postcode, $shipping_address, $shipping_country, $shipping_city) {
						if ('postcode' === $k && $v !== $shipping_postcode) {
							return true;
						} else if ('address' === $k && $v !== $shipping_address) {
							return true;
						} else if ('country' === $k && $v !== $shipping_country) {
							return true;
						} else if ('city' === $k && $v !== $shipping_city) {
							return true;
						}

						return false;
					},
					\ARRAY_FILTER_USE_BOTH
			);

			if (!empty($stale_items)) {
				$this->reset_pickup_point_session_data();
				$session = $this->get_pickup_point_session_data();
			}

			if (empty($shipping_country)) {
				$shipping_country = 'FI';
			}

			// Return if the customer has not yet chosen a postcode
			if (empty($shipping_postcode)) {
				$error_msg = Posti_Warehouse_Text::error_empty_postcode();
			} else if (!is_numeric($shipping_postcode)) {
				$error_msg = Posti_Warehouse_Text::error_invalid_postcode($shipping_postcode);
			} else {
				try {
					$options_array = $this->fetch_pickup_point_options(
						$shipping_postcode, $shipping_address,
						$shipping_country, $shipping_city, $shipping_method_providers,
						$store_country_code, $store_postal_code);
				} catch (\Exception $e) {
					$options_array = false;

					// The error prints twice if the page is refreshed and there's an invalid address.
					// Which doesn't make any sense as this method should only be called *once*.
					// The error is displayed differently because of that.
					// $this->display_error($e->getMessage());
					// Adding the error to $this->errors doesn't work either, as the errors are only displayed in the
					// woocommerce_checkout_process hook which is triggered when the user submits the order.
					// $this->add_error($e->getMessage());
					// This works though. It prevents the pickup point input from rendering,
					// and if there's no pickup point selected, the order will error in woocommerce_checkout_process.
					$error_msg = $e->getMessage();
				}

				$selected_point = false;
				if (!$error_msg) {
					$list_type = 'select';

					if (isset($settings['pickup_point_list_type']) && 'list' === $settings['pickup_point_list_type']) {
						$list_type = 'radio';

						array_splice($options_array, 0, 1);
					}

					$flatten = function ( $point) {
						return $point['text'];
					};

					$private_points = \array_map(
							$flatten,
							\array_filter(
									$options_array,
									function ( $point) {
										return isset($point['is_private']) ? true === $point['is_private'] : false;
									}
							)
					);

					$all_points = \array_map($flatten, $options_array);

					$selected_point = $session['pickup_point'];
					$selected_point_empty = empty($selected_point);

					if ($is_klarna && $selected_point_empty) {
						// Select the first point as the default when using Klarna Checkout, which does not validate the selection
						$selected_point = array_keys($all_points)[1];
					} else if ($selected_point_empty) {
						$selected_point = array_keys($all_points)[0];
					}

					$select_field = array(
						'name' => $this->add_prefix('_pickup_point'),
						'data' => array(
							'clear' => true,
							'type' => $list_type,
							'custom_attributes' => array(
								'style' => 'word-wrap: normal;',
								'onchange' => 'warehouse_pickup_point_change(this)',
								'data-private-points' => join(';', array_keys($private_points)),
							),
							'options' => $all_points,
							'required' => true,
							'default' => $selected_point,
						),
						'value' => null,
					);
				}

				if ('other' === $selected_point || $is_klarna || !$options_array) {
					$custom_field_title = $is_klarna ? Posti_Warehouse_Text::pickup_address() : Posti_Warehouse_Text::pickup_address_custom();
					$custom_field = array(
						'name' => 'posti_warehousecustom_pickup_point',
						'data' => array(
							'type' => 'textarea',
							'custom_attributes' => array(
							'onchange' => 'warehouse_custom_pickup_point_change(this)',
							),
						),
						'value' => $session['custom_address'],
					);

					$custom_field_desc = $is_klarna ? Posti_Warehouse_Text::pickup_points_search_instruction1() : Posti_Warehouse_Text::pickup_points_search_instruction2();
				}
				
			}
			
			wc_get_template(
					$this->core->templates['checkout_pickup'],
					array(
						'nonce' => wp_create_nonce($this->add_prefix('-pickup_point_update')),
						'error' => array(
							'msg' => $error_msg,
							'name' => esc_attr($this->add_prefix('_pickup_point')),
						),
						'pickup' => array(
							'show' => ( $select_field ) ? true : false,
							'field' => $select_field,
							'title' => Posti_Warehouse_Text::pickup_point_title(),
							'desc' => Posti_Warehouse_Text::pickup_points_instruction()
						),
						'custom' => array(
							'show' => ( $custom_field ) ? true : false,
							'title' => $custom_field_title,
							'field' => $custom_field,
							'desc' => $custom_field_desc,
						),
					),
					'',
					$this->core->templates_dir
			);
		}

		private function fetch_pickup_point_options( $shipping_postcode, $shipping_address, $shipping_country, $shipping_city, $shipping_method_providers, $from_country_code, $from_postal_code) {
			$shipping_method_provider = implode(',', $shipping_method_providers['services']);
			$types = $shipping_method_providers['types'];
			$type = '';
			if (!isset($types['pickup_points']) && isset($types['store_points'])) {
				$type = 'STORE';
			}
			else if (isset($types['pickup_points']) && !isset($types['store_points'])) {
				$type = '!STORE';
			}
			
			$pickup_point = WC()->session->get(str_replace('wc_', 'woo_', $this->core->prefix) . '_pickup_point');
			$custom_address = isset($pickup_point) && isset($pickup_point['custom_address']) ? $pickup_point['custom_address'] : false;
			$capability = isset($types['hideoutdoors']) ? '!outdoors' : '';

			$pickup_point_data = $this->fetch_pickup_point_option_array(
				$shipping_postcode, $shipping_address,
				$shipping_country, $shipping_city,
				$shipping_method_provider, $type,
				$capability, $custom_address,
				$from_country_code, $from_postal_code);
			if (empty($pickup_point_data) && !empty($shipping_city) && 'STORE' === $type) {
				$pickup_point_data = $this->fetch_pickup_point_option_array(
					$shipping_postcode, $shipping_address,
					$shipping_country, null,
					$shipping_method_provider, $type,
					$capability, $custom_address,
					$from_country_code, $from_postal_code);
			}

			if (false === $pickup_point_data) {
				throw new \Exception(Posti_Warehouse_Text::error_pickup_point_generic());
			}
			
			if (empty($pickup_point_data)) {
				throw new \Exception(Posti_Warehouse_Text::error_pickup_point_not_found());
			}

			return $this->process_pickup_points_to_option_array($pickup_point_data);
		}
		
		private function fetch_pickup_point_option_array(
			$shipping_postcode, $shipping_address,
			$shipping_country, $shipping_city,
			$shipping_method_provider, $type,
			$capability, $custom_address,
			$from_country_code, $from_postal_code) {

			if ($custom_address) {
				return $this->get_pickup_points_by_free_input(
					$custom_address, $shipping_country, $shipping_method_provider, $type, $capability);
			} else {
				return $this->get_pickup_points(
					$shipping_postcode, $shipping_address,
					$shipping_country, $shipping_city,
					$shipping_method_provider, $type, $capability,
					$from_country_code, $from_postal_code);
			}
		}

		private function process_pickup_points_to_option_array( $pickup_points) {
			$options_array = array('' => array('text' => '- ' . Posti_Warehouse_Text::pickup_point_select() . ' -'));
			if (!empty($pickup_points)) {
				foreach ($pickup_points as $pickup_point) {
					$serviceProvider = isset($pickup_point['serviceProvider']) ? $pickup_point['serviceProvider'] : null;
					$type = isset($pickup_point['type']) ? $pickup_point['type'] : null;
					if (empty($serviceProvider) && 'STORE' !== $type) {
						continue;
					}

					$key_part = empty($serviceProvider) ? $type : $serviceProvider;
					$pickup_point_key = $key_part
							. ': ' . $pickup_point['name']
							. ' (#' . $pickup_point['externalId'] . ')';
					$pickup_point_value = $pickup_point['name']
							. ' (' . $pickup_point['streetAddress'] . ')';

					if (isset($pickup_point['estimation']) && !empty($pickup_point['estimation'])) {
						$fmtDate = date("d.m", strtotime($pickup_point['estimation']));
						$pickup_point_value .= ' - ' . Posti_Warehouse_Text::estimated_delivery($fmtDate);
					}

					if (!empty($serviceProvider)) {
						$pickup_point_value = $serviceProvider . ': ' . $pickup_point_value;
					}

					$options_array[$pickup_point_key] = array(
						'text' => $pickup_point_value,
						'is_private' => 'PRIVATE_LOCKER' === $type,
					);
				}
			}

			return $options_array;
		}

		/**
		 * Display pickup point to customer after order.
		 *
		 * @param WC_Order $order the order that was placed
		 */
		public function display_order_data( $order) {
			$pickup_point = $order->get_meta('_' . $this->add_prefix('_pickup_point'));

			if (!empty($pickup_point)) {
				wc_get_template(
					$this->core->templates['account_order'],
					array(
						'pickup_point' => esc_attr($pickup_point),
						'texts' => array(
							'title' => Posti_Warehouse_Text::pickup_point_title()
						)
					),
					'',
					$this->core->templates_dir);
			}
		}

		public function validate_checkout() {
			$key = $this->add_prefix('_pickup_point');
			$pickup_data = isset($_POST[$key]) ? sanitize_text_field($_POST[$key]) : '__NULL__';
			$pickup_data = '__null__' === $pickup_data ? strtoupper($pickup_data) : $pickup_data;

			// if there is no pickup point data, let's see do we need it
			if ('__NULL__' === $pickup_data || '' === $pickup_data || 'other' === $pickup_data) {
				$key = $this->core->prefix . '_validate_pickup_points';
				// if the value does not exists, then we expect to have pickup point data
				$shipping_needs_pickup_points = isset($_POST[$key]) ? 'true' === $_POST[$key] : false;

				if ($shipping_needs_pickup_points) {
					$this->add_error(Posti_Warehouse_Text::error_pickup_point_not_provided());
				}

				foreach ($this->errors as $error) {
					$this->display_error($error);
				}
			}
		}

		public function add_metadata_to_order_shipping_method( $item, $package_key, $package, $order) {
			if (isset($_POST['warehouse_pickup_point'])) {
				$item->update_meta_data($this->add_prefix('_pickup_point'), sanitize_text_field($_POST['warehouse_pickup_point']));
			}
		}

		public function get_pickup_points(
			$postcode, $street_address = null, $country = null, $city = null,
			$service_provider = null, $type = null, $capability = null,
		    $from_country_code = null, $from_postal_code = null) {
			return $this->api->getPickupPoints(
				trim($postcode), trim($street_address), trim($country), trim($city),
				$service_provider, $type, $capability, $from_country_code, $from_postal_code);
		}

		public function get_pickup_points_by_free_input( $input, $shipping_country, $service_provider = null, $type = null, $capability = null) {
			return $this->api->getPickupPointsByText(trim($input), trim($shipping_country), $service_provider, $type, $capability);
		}

		private function add_prefix( $name) {
			return str_replace('wc_', '', $this->core->prefix) . $name;
		}
	}
}
