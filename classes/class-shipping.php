<?php

namespace Posti_Warehouse;

// Prevent direct access to the script
use WC_Countries;
use WC_Shipping_Method;

defined('ABSPATH') || exit;

function posti_warehouse_define_shipping_method() {
	if (!class_exists('Posti_Warehouse_Shipping')) {
		class Posti_Warehouse_Shipping extends \WC_Shipping_Method {
			
			public $is_loaded = false;
			private $is_test = false;
			private $debug = false;
			private $api;
			private $delivery_service = 'WAREHOUSE';
			private $logger;
			private $options;
			
			public function __construct() {
				$this->options = Posti_Warehouse_Settings::get();
				$this->is_test = Posti_Warehouse_Settings::is_test($this->options);
				$this->debug = Posti_Warehouse_Settings::is_debug($this->options);
				
				$this->delivery_service = Posti_Warehouse_Settings::get_value($this->options, 'posti_wh_field_service');
				$this->logger = new Posti_Warehouse_Logger();
				$this->logger->setDebug($this->debug);
				
				$this->api = new Posti_Warehouse_Api($this->logger, $this->options);
				
				$this->load();
			}
			
			public function load() {
				$this->id = 'posti_warehouse';
				$this->method_title = 'Posti warehouse';
				$this->method_description = 'Posti warehouse';
				$this->enabled = 'yes';
				$this->supports = array(
					'settings',
				);
				
				$this->init();
				
				// Save settings in admin if you have any defined
				add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
			}
			
			/**
			 * Initialize Pakettikauppa shipping
			 */
			public function init() {
				$this->form_fields = $this->get_global_form_fields();
				$this->title = 'Warehouse shipping';
				$this->init_settings();
			}
			
			public function process_admin_options() {
				parent::process_admin_options();
				
				$service_code = Posti_Warehouse_Settings::get_value($this->options, 'posti_wh_field_service');
				if (!empty($service_code) && $this->delivery_service != $service_code) {
					$this->delivery_service = $service_code;
					delete_transient('posti_warehouse_shipping_methods');
				}
			}
			
			public function validate_pickuppoints_field( $key, $value) {
				$values = wp_json_encode($value);
				return $values;
			}
			
			public function generate_pickuppoints_html( $key, $value) {
				$field_key = $this->get_field_key($key);
				if ($this->get_option($key) !== '') {
					$values = $this->get_option($key);
					
					if (is_string($values)) {
						$values = json_decode($this->get_option($key), true);
					}
				} else {
					$values = array();
				}
				
				$all_shipping_methods = $this->services();
				$user_lang = $this->get_user_language();
				
				if (empty($all_shipping_methods)) {
					$all_shipping_methods = array();
				}
				
				ob_start();
				?>
				<script>
					function posti_warehouse_shippingChangeOptionVisibility(elCheckbox) {
						var elSection = document.getElementById(elCheckbox.name + '[options]');
						if (elSection) {
							elSection.style.display = (elCheckbox.checked == true) ? "block" : "none";
						}
					}

					function posti_warehouse_shippingChangeOptions(elem, methodId) {

						var strUser = elem.options[elem.selectedIndex].value;
						var elements = document.getElementsByClassName('pk-services-' + methodId);

						var servicesElement = document.getElementById('services-' + methodId + '-' + strUser);
						var pickuppointsElement = document.getElementById('pickuppoints-' + methodId);
						var servicePickuppointsElement = document.getElementById('service-' + methodId + '-' + strUser + '-pickuppoints');
						var servicePickupstoresElement = document.getElementById('service-' + methodId + '-' + strUser + '-pickupstores');

						for (var i = 0; i < elements.length; ++i) {
							elements[i].style.display = "none";
						}

						if (strUser == '__PICKUPPOINTS__') {
							if (pickuppointsElement)
								pickuppointsElement.style.display = "block";
							if (servicesElement)
								servicesElement.style.display = "none";
						} else {
							if (pickuppointsElement)
								pickuppointsElement.style.display = "none";
							if (servicesElement)
								servicesElement.style.display = "block";
							if (elem.options[elem.selectedIndex].getAttribute('data-haspp') == 'true')
								servicePickuppointsElement.style.display = "block";
							if (elem.options[elem.selectedIndex].getAttribute('data-hassp') == 'true')
								servicePickupstoresElement.style.display = "block";
						}
						
						var ppOptions = document.getElementsByClassName('posti-warehouse-pickup-point-checkbox');
						for (var i = 0; i < ppOptions.length; ++i) {
							posti_warehouse_shippingChangeOptionVisibility(ppOptions[i]);
						}
					}
				</script>
				<tr>
					<td colspan="2" class="mode_react">
						<h1><?php echo esc_html($value['title']); ?></h1>
						<?php foreach (\WC_Shipping_Zones::get_zones('admin') as $zone_raw) : ?>
							<hr>
							<?php $zone = new \WC_Shipping_Zone($zone_raw['zone_id']); ?>
							<h3>
								<?php echo esc_html(Posti_Warehouse_Text::zone_name()); ?>: <?php echo esc_html($zone->get_zone_name()); ?>
							</h3>
							<p>
								<?php echo esc_html(Posti_Warehouse_Text::zone_regions()); ?>: <?php echo esc_html($zone->get_formatted_location()); ?>
							</p>
							<h4><?php echo esc_html(Posti_Warehouse_Text::zone_shipping()); ?></h4>
							<?php foreach ($zone->get_shipping_methods() as $method_id => $shipping_method) : ?>
								<?php if ('yes' === $shipping_method->enabled && 'posti_warehouse' !== $shipping_method->id && 'local_pickup' !== $shipping_method->id) : ?>
									<?php
									$selected_service = null;
									if (!empty($values[$method_id]['service'])) {
										$selected_service = $values[$method_id]['service'];
									}
									?>
									<table style="border-collapse: collapse;" border="0">
										<th><?php echo esc_html($shipping_method->title); ?></th>
										<td style="vertical-align: top;">
											<select id="<?php echo esc_attr($method_id); ?>-select" name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][service]'; ?>" onchange="posti_warehouse_shippingChangeOptions(this, '<?php echo esc_attr($method_id); ?>');">
												<option value="__NULL__"><?php echo 'No shipping'; ?></option>  <?php //Issue: #171, was no echo ?>
												<?php foreach ($all_shipping_methods as $service_id => $service_name) : ?>
													<?php $has_pp = ( $this->service_has_pickup_points($service_id) ) ? true : false; ?>
													<?php $has_sp = ( $this->service_has_store_pickup($service_id) ) ? true : false; ?>
													<option value="<?php echo esc_attr($service_id); ?>" <?php echo ( strval($selected_service) === strval($service_id) ? 'selected' : '' ); ?> data-haspp="<?php echo ( $has_pp ) ? 'true' : 'false'; ?>" data-hassp="<?php echo ( $has_sp ) ? 'true' : 'false'; ?>">
														<?php echo esc_attr($service_name); ?>
														<?php if ($has_pp) : ?>
															(Has pickup points)
														<?php endif; ?>
													</option>
												<?php endforeach; ?>
											</select>
										</td>
										<td style="vertical-align: top;">
											<?php
											$all_additional_services = $this->get_additional_services();
											if (empty($all_additional_services)) {
												$all_additional_services = array();
											}
											?>
											<?php foreach ($all_additional_services as $method_code => $additional_services) : ?>
												<div class="pk-services-<?php echo esc_attr($method_id); ?>" style='display: none;' id="services-<?php echo esc_attr($method_id); ?>-<?php echo esc_attr($method_code); ?>">
													<?php foreach ($additional_services as $additional_service) : ?>
														<?php if (empty($additional_service->specifiers) || in_array($additional_service->code, array('3102'), true)) : ?>
															<input type="hidden"
																	name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($method_code) . '][additional_services][' . esc_attr($additional_service->code) . ']'; ?>"
																	value="no">
															<p>
																<label>
																	<input type="checkbox"
																			name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($method_code) . '][additional_services][' . esc_attr($additional_service->code) . ']'; ?>"
																			value="yes" <?php echo ( !empty($values[$method_id][$method_code]['additional_services'][$additional_service->code]) && 'yes' === $values[$method_id][$method_code]['additional_services'][$additional_service->code] ) ? 'checked' : ''; ?>>
																			<?php echo esc_html(isset($additional_service->description[$user_lang]) ? $additional_service->description[$user_lang] : $additional_service->description['en']); ?>
																</label>
															</p>
														<?php endif; ?>
													<?php endforeach; ?>
												</div>
											<?php endforeach; ?>
											<?php foreach ($all_shipping_methods as $service_id => $service_name) : ?>
												<?php if ($this->service_has_pickup_points($service_id)) : ?>
													<div id="service-<?php echo esc_attr($method_id); ?>-<?php echo esc_attr($service_id); ?>-pickuppoints" class="pk-services-<?php echo esc_attr($method_id); ?>" style="display: none;">
														<input type="hidden"
																name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($service_id) . '][pickuppoints]'; ?>" value="no">
														<p>
															<label>
																<input class="posti-warehouse-pickup-point-checkbox"
																		type="checkbox"
																		name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($service_id) . '][pickuppoints]'; ?>"
																		value="yes" <?php echo ( ( !empty($values[$method_id][$service_id]['pickuppoints']) && 'yes' === $values[$method_id][$service_id]['pickuppoints'] ) || empty($values[$method_id][$service_id]['pickuppoints']) ) ? 'checked' : ''; ?>
																		onclick="posti_warehouse_shippingChangeOptionVisibility(this)">
																		<?php echo esc_html(Posti_Warehouse_Text::pickup_points_title()); ?>
															</label>
															<div id="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($service_id) . '][pickuppoints][options]'; ?>">
																<label> - 
																	<input type="checkbox"
																			name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($service_id) . '][pickuppoints_hideoutdoors]'; ?>"
																			value="yes" <?php echo ( ( !empty($values[$method_id][$service_id]['pickuppoints_hideoutdoors']) && 'yes' === $values[$method_id][$service_id]['pickuppoints_hideoutdoors'] )) ? 'checked' : ''; ?>>
																			<?php echo esc_html(Posti_Warehouse_Text::pickup_points_hide_outdoor()); ?>
																</label>
															</div>
														</p>
													</div>
												<?php endif; ?>
											<?php endforeach; ?>
											<?php foreach ($all_shipping_methods as $service_id => $service_name) : ?>
												<?php if ($this->service_has_store_pickup($service_id)) : ?>
													<div id="service-<?php echo esc_attr($method_id); ?>-<?php echo esc_attr($service_id); ?>-pickupstores" class="pk-services-<?php echo esc_attr($method_id); ?>" style="display: none;">
														<input type="hidden"
																name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($service_id) . '][storepoints]'; ?>" value="no">
														<p>
															<label>
																<input type="checkbox"
																		name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($service_id) . '][storepoints]'; ?>"
																		value="yes" <?php echo ( ( !empty($values[$method_id][$service_id]['storepoints']) && 'yes' === $values[$method_id][$service_id]['storepoints'] ) ) ? 'checked' : ''; ?>>
																		<?php echo esc_html(Posti_Warehouse_Text::store_pickup_title()); ?>
															</label>
														</p>
													</div>
												<?php endif; ?>
											<?php endforeach; ?>
										</td>
									</table>
									<script>posti_warehouse_shippingChangeOptions(document.getElementById("<?php echo esc_attr($method_id); ?>-select"), '<?php echo esc_attr($method_id); ?>');</script>
								<?php endif; ?>
							<?php endforeach; ?>
						<?php endforeach; ?>
						<hr>

					</td>
				</tr>

				<?php
				$html = ob_get_contents();
				ob_end_clean();
				return $html;
			}

			private function get_global_form_fields() {
				return array(
					'pickup_points' => array(
						'title' => 'Pickup',
						'type' => 'pickuppoints',
					),
						/*
						  array(
						  'title' => $this->get_core()->text->shipping_settings_title(),
						  'type' => 'title',
						  'description' => $this->get_core()->text->shipping_settings_desc(),
						  ),
						 */
				);
			}

			private function services() {
				$services = array();

				$user_lang = $this->get_user_language();
				$all_shipping_methods = $this->get_shipping_methods();

				// List all available methods as shipping options on checkout page
				if (null === $all_shipping_methods) {
					// returning null seems to invalidate services cache
					return null;
				}

				foreach ($all_shipping_methods as $shipping_method) {
					$value = isset($shipping_method->description[$user_lang]) ? $shipping_method->description[$user_lang] : $shipping_method->description['en'];
					$services[strval($shipping_method->id)] = sprintf('%1$s: %2$s', $shipping_method->deliveryOperator, $value);
				}

				ksort($services);

				return $services;
			}

			private function get_user_language( $user = 0 ) {
				$user_splited_locale = explode('_', get_user_locale($user));
				return isset($user_splited_locale[0]) ? $user_splited_locale[0] : 'en';
			}

			public function get_additional_services() {
				$all_shipping_methods = $this->get_shipping_methods();

				if (null === $all_shipping_methods) {
					return null;
				}

				$additional_services = array();
				foreach ($all_shipping_methods as $shipping_method) {
					if (!isset($shipping_method->additionalServices)) {
						continue;
					}
					foreach ($shipping_method->additionalServices as $key => $service) {
						$additional_services[strval($shipping_method->id)][$key] = (object) $service;
					}
				}

				return $additional_services;
			}

			private function get_shipping_methods() {
				$transient_name = 'posti_warehouse_shipping_methods';
				$transient_time = 86400; // 24 hours

				$all_shipping_methods = get_transient($transient_name);
				if (empty($all_shipping_methods)) {
					try {
						$all_shipping_methods = $this->api->getDeliveryServices($this->delivery_service);

						$log_msg = ( empty($all_shipping_methods) ) ? 'An empty list was received' : 'List received successfully';
						$this->logger->log('info', 'Trying to get list of shipping methods... ' . $log_msg);
					} catch (\Exception $ex) {
						$all_shipping_methods = null;
						$this->logger->log('error', 'Failed to get list of shipping methods: ' . $ex->getMessage());
					}

					if (!empty($all_shipping_methods)) {
						set_transient($transient_name, $all_shipping_methods, $transient_time);
					}
				}

				if (empty($all_shipping_methods)) {
					return null;
				}

				foreach ($all_shipping_methods as $key => $shipping_method) {
					$all_shipping_methods[$key] = (object) $shipping_method;
				}

				return $all_shipping_methods;
			}

			private function service_has_pickup_points($service_id) {
				return $this->has_service_feature($service_id, 'PICKUP_POINT');
			}
			
			private function service_has_store_pickup($service_id) {
				return $this->has_service_feature($service_id, 'STORE_PICKUP');
			}
			
			private function has_service_feature($service_id, $feature) {
				$all_shipping_methods = $this->get_shipping_methods();
				
				if (null === $all_shipping_methods) {
					return false;
				}
				
				foreach ($all_shipping_methods as $shipping_method) {
					if (strval($shipping_method->id) !== strval($service_id)) {
						continue;
					}
					if (!isset($shipping_method->tags)) {
						continue;
					}
					if (!in_array($feature, $shipping_method->tags)) {
						continue;
					}
					return true;
				}
				
				return false;
			}
		}
	}
}

add_action('woocommerce_shipping_init', '\Posti_Warehouse\posti_warehouse_define_shipping_method');

function posti_warehouse_add_shipping_method( $methods) {
	$methods[] = '\Posti_Warehouse\Posti_Warehouse_Shipping';
	return $methods;
}

add_filter('woocommerce_shipping_methods', '\Posti_Warehouse\posti_warehouse_add_shipping_method');
