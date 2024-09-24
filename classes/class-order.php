<?php

namespace Posti_Warehouse;

defined('ABSPATH') || exit;

class Posti_Warehouse_Order {
	
	private $orderStatus = false;
	private $addTracking = false;
	private $api;
	private $logger;
	private $product;
	private $service;
	private $status_mapping;
	
	public function __construct(Posti_Warehouse_Api $api, Posti_Warehouse_Logger $logger, Posti_Warehouse_Product $product, $addTracking = false) {
		$this->api = $api;
		$this->logger = $logger;
		$this->product = $product;
		$this->service = new Posti_Warehouse_Service($this->api, $this->logger);
		$this->addTracking = $addTracking;
		
		$statuses = array();
		$statuses['Delivered'] = 'completed';
		$statuses['Accepted'] = 'processing';
		$statuses['Submitted'] = 'processing';
		$statuses['Error'] = 'failed';
		$statuses['Cancelled'] = 'cancelled';
		$this->status_mapping = $statuses;
		
		//on order status change
		add_action('woocommerce_order_status_changed', array($this, 'posti_check_order'), 10, 3);
		//api tracking columns
		add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'posti_tracking_column'), 20);
		add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'posti_tracking_column_data'), 20, 2);
		add_action('woocommerce_order_note_added', array($this, 'posti_comment_add'), 10, 2);
		add_action('woocommerce_order_note_deleted', array($this, 'posti_comment_delete'), 10, 2);

		add_filter('woocommerce_order_item_display_meta_key', array($this, 'change_metadata_title_for_order_shipping_method'), 20, 3 );
		
		if ($this->addTracking) {
			add_action('woocommerce_email_order_meta', array($this, 'addTrackingToEmail'), 10, 4);
		}
	}

	public function change_metadata_title_for_order_shipping_method( $key, $meta, $item) {
		if ('warehouse_pickup_point' === $meta->key) {
			$key = Posti_Warehouse_Text::pickup_point_title();
		}
		
		return $key;
	}

	public function hasPostiProducts( $order) {
		if (!is_object($order)) {
			$order = wc_get_order($order);
		}
		
		if (!$order) {
			return false;
		}
		
		$items = $order->get_items();
		if (count($items) == 0) {
			return false;
		}
		
		foreach ($items as $item_id => $item) {
			if ($this->product->has_known_stock_type($item['product_id'])) {
				return true;
			}
		}
		
		return false;
	}
	
	public function hasPostiProductsOnly( $order) {
		if (!is_object($order)) {
			$order = wc_get_order($order);
		}
		
		if (!$order) {
			return false;
		}
		
		$items = $order->get_items();
		if (count($items) == 0) {
			return false;
		}
		
		foreach ($items as $item_id => $item) {
			if (!$this->product->has_known_stock_type($item['product_id'])) {
				return false;
			}
		}
		
		return true;
	}

	public function getOrder( $order) {
		$posti_order_id = $this->get_order_external_id_field($order);
		$this->logger->log('info', print_r($order, true));
		if ($posti_order_id) {
			return $this->api->getOrder($posti_order_id);
		}
		return false;
	}

	public function addOrder( $order) {
		$options = Posti_Warehouse_Settings::get();
		return $this->addOrderWithOptions($order, $options, null);
	}
	
	public function addOrderWithOptions( $order, $options, $order_status) {
		if (!is_object($order)) {
			$order = wc_get_order($order);
		}
		
		if (Posti_Warehouse_Settings::is_reject_partial_orders($options) && !$this->hasPostiProductsOnly($order)) {
			return [ 'error' => 'ERROR: Partial order not allowed.' ];
		}

		$order_services = $this->get_additional_services($order);
		if (!isset($order_services['service']) || empty($order_services['service'])) {
			$order->update_status('failed', Posti_Warehouse_Text::error_order_failed_no_shipping(), true);
			return [ 'error' => 'ERROR: Shipping method not configured.' ];
		}

		//$order_id = (string) $order->get_id();
		$order_number = (string) $order->get_order_number();
		$existing_order_id = $this->get_order_external_id_field($order);
		if (!empty($existing_order_id)) {
			$existing_order = $this->api->getOrder($existing_order_id);
			if ($existing_order) {
				$status = isset($existing_order['status']) && isset($existing_order['status']['value']) ? $existing_order['status']['value'] : '';
				if ('Cancelled' !== $status && 'Delivered' !== $status) {
					return [ 'error' => 'ERROR: Already ordered.' ];
				}
			}
		}

		$external_id = empty($existing_order_id) ? $order_number : $existing_order_id;
		$data = null;
		try {
			$preferences = ['autoSubmit' => ($order_status !== 'on-hold')];
			$data = $this->prepare_posti_order($external_id, $order, $order_services, $preferences);

		} catch (\Exception $e) {
			$this->logger->log('error', $e->getMessage());
			return [ 'error' => $e->getMessage() ];
		}

		if (empty($existing_order_id)) {
			$result = $this->api->addOrder($data);
		}
		else {
			$result = $this->api->reopenOrder($existing_order_id, $data);
		}

		$status = $this->api->getLastStatus();
		if (502 == $status || 503 == $status) {
			for ($i = 0; $i < 3; $i++) {
				sleep(1);
				$result = $this->api->addOrder($data);
				$status = $this->api->getLastStatus();
				if (200 == $status) {
					break;
				}
			}
		}

		if ($status >= 200 && $status < 300) {
		    $order->update_meta_data('_posti_id', $order_number);
		} else {
			$order->update_status('failed', Posti_Warehouse_Text::order_failed(), true);
		}
		$order->save();

		if (false === $result) {
			return [ 'error' => Posti_Warehouse_Text::error_order_not_placed() ];
		}

		$this->trigger_sync_order($order->get_id(), $existing_order_id);

		return [];
	}
	
	public function submitDelayedOrder( $order_external_id, $order, $options, $order_status) {
		$order = $this->api->getOrder($order_external_id);
		if ($order) {
			$autoSubmit = $this->get_order_autosubmit_preference($order);
			// check if order is actually delayed
			if ($autoSubmit === false) {
				$this->update_order_autosubmit_preference($order_external_id, true);
			}
		}
	}
	
	public function submitOrder( $order, $sync = false) {
		$order_external_id = $this->get_order_external_id_field($order);
		$result = $this->update_order_autosubmit_preference($order_external_id, true);
		if (!$result) {
			return [ 'error' => 'ERROR: Technical error.' ];
		}

		$this->trigger_sync_order($order->id, $order_external_id);

		return [];
	}

	public function cancelOrder( $order) {
		$order_external_id = $this->get_order_external_id_field($order);
		if (empty($order_external_id)) {
			return [];
		}

		$existing_order = $this->api->getOrder($order_external_id);
		if (!$existing_order) {
			return [];
		}

		$status = isset($existing_order['status']['value']) ? $existing_order['status']['value'] : null;
		if ('Cancelled' !== $status && 'Delivered' !== $status) {
			$warehouse_order = array();
			$warehouse_order['status'] = ['value' => 'Cancelled'];
			$result = $this->api->updateOrder($order_external_id, $warehouse_order);
			if (!$result) {
				return [ 'error' => 'ERROR: Technical error.' ];
			}
		}

		return [];
	}
	
	public function sync( $datetime) {
		$response = $this->api->getOrdersUpdatedSince($datetime, 30);
		if (!$this->sync_page($response)) {
			return false;
		}

		$pages = $response['page']['totalPages'];
		for ($page = 1; $page < $pages; $page++) {
			$page_response = $this->api->getOrdersUpdatedSince($datetime, 30, $page);
			if (!$this->sync_page($page_response)) {
				break;
			}
		}
		
		return true;
	}

	function posti_comment_add( $order_note_id, $order) {
		$comment = get_comment($order_note_id);
		$is_customer_note = get_comment_meta($order_note_id, 'is_customer_note', true);
		$posti_order_id = $this->get_order_external_id_field($order);
		if (!empty($posti_order_id)
			&& 'WooCommerce' !== $comment->comment_author) { // automatic internal comment

			$posti_comment = array(
				'externalId' => (string) $order_note_id,
				'author' => $comment->comment_author_email,
				'value' => (string) $comment->comment_content,
				'type' => ($is_customer_note == 1 ? 'pickingNote' : 'passThrough'),
				'createdDate' => date('c', strtotime($comment->comment_date_gmt)),
				'origin' => 'WOOCOMMERCE'
			);
			$this->api->addOrderComment($posti_order_id, $posti_comment);
		}
	}
	
	function posti_comment_delete( $order_note_id, $note) {
		$posti_order_id = $this->get_order_external_id($note->order_id);
		if (!empty($posti_order_id)) {
			$this->api->deleteOrderComment($posti_order_id, $order_note_id);
		}
	}

	private function sync_page( $page) {
		if (!isset($page) || false === $page) {
			return false;
		}

		$warehouse_orders = $page['content'];
		if (!isset($warehouse_orders) || !is_array($warehouse_orders) || count($warehouse_orders) == 0) {
			return false;
		}

		$order_ids = array();
		foreach ($warehouse_orders as $warehouse_order) {
			$order_id = $warehouse_order['externalId'];
			if (isset($order_id) && strlen($order_id) > 0) {
				array_push($order_ids, (string) $order_id);
			}
		}
		
		$options = Posti_Warehouse_Settings::get();
		$is_verbose = Posti_Warehouse_Settings::is_verbose_logging($options);
		if ($is_verbose) {
			$this->logger->log('info', "Got order statuses for: " . implode(', ', $order_ids));
		}
		
		$posts_query = array(
			'post_type' => 'shop_order',
			'post_status' => 'any',
			'numberposts' => -1,
			'meta_key' => '_posti_id',
			'meta_value' => $order_ids,
			'meta_compare' => 'IN'
		);
		$posts = wc_get_orders($posts_query);
		if (count($posts) == 0) {
			if ($is_verbose) {
				$this->logger->log('info', "No matched orders for status update");
			}

			return true;
		}
		
		if ($is_verbose) {
			$matched_post_ids = array();
			foreach ($posts as $post) {
				array_push($matched_post_ids, (string) $post->ID);
			}
			$this->logger->log('info', "Matched orders: " . implode(', ', $matched_post_ids));
		}
		
		$post_by_order_id = array();
		foreach ($posts as $post) {
			$order_id = $this->get_order_external_id_field($post);
			if (isset($order_id) && strlen($order_id) > 0) {
				$post_by_order_id[$order_id] = $post->ID;
			}
		}

		$autocomplete = Posti_Warehouse_Settings::get_value($options, 'posti_wh_field_autocomplete');
		foreach ($warehouse_orders as $warehouse_order) {
			$order_id = $warehouse_order['externalId'];
			if (isset($post_by_order_id[$order_id]) && !empty($post_by_order_id[$order_id])) {
				$this->sync_order($post_by_order_id[$order_id], $order_id, $warehouse_order, $autocomplete, $is_verbose);
			}
		}

		return true;
	}

	public function sync_order( $id, $order_external_id, $warehouse_order, $autocomplete, $is_verbose) {
		try {
			$status = isset($warehouse_order['status']) && isset($warehouse_order['status']['value']) ? $warehouse_order['status']['value'] : '';
			if (empty($status)) {
				return;
			}
	
			$status_new = isset($this->status_mapping[$status]) ? $this->status_mapping[$status] : '';
			if (empty($status_new)) {
				return;
			}
	
			$order = wc_get_order($id);
			if (false === $order) {
				return;
			}

			$order_updated = false;
			$tracking = isset($warehouse_order['trackingCodes']) ? $warehouse_order['trackingCodes'] : '';
			if (!empty($tracking)) {
				if (is_array($tracking)) {
					$tracking = implode(', ', $tracking);
				}
				$order->update_meta_data('_posti_api_tracking', sanitize_text_field($tracking));
				$order_updated = true;
			}

			$status_updated = false;
			$status_old = $order->get_status();
			if ($status_old !== $status_new) {
				if ('completed' === $status_new) {
					if (isset($autocomplete)) {
						$order->update_status($status_new, "Posti Warehouse: $status", true);
						$status_updated = true;
					}
					else {
						$this->logger->log('info', "Order $id autocomplete disabled for status $status_new");
					}

				} elseif ('cancelled' === $status_new || 'cancelled'  === $status_old) {
					$order->update_status($status_new, "Posti Warehouse: $status", true);
					$status_updated = true;

				} elseif ('on-hold' === $status_old) {
					$autoSubmit = $this->get_order_autosubmit_preference($warehouse_order);
					if ($autoSubmit === true) { // prevent updating status when order is registered (qty reserved) but is not yet submitted to warehouse
						$order->update_status($status_new, "Posti Warehouse: $status", true);
						$status_updated = true;
					}
				}

				if ($status_updated) {
					$order_updated = true;
					$this->logger->log('info', "Changed order $id status $status_old -> $status_new");
				}
			}
			else if ('completed' === $status_old) {
				$this->logger->log('info', "Order $id ($order_external_id) status is already completed");
			}
			else if ($is_verbose) {
				$this->logger->log('info', "Order $id ($order_external_id) status is already $status_new");
			}
			
			if ($order_updated || $status_updated) {
				$order->save();
			}
			
		} catch (\Exception $e) {
			$this->logger->log('error', $e->getMessage());
		}
	}

	private function trigger_sync_order( $order_id, $order_external_id) {
		if ($order_external_id) {
			$order = $this->api->getOrder($order_external_id);
			$this->sync_order( $order_id, $order_external_id, $order, false, false);
		}
	}

	private function update_order_autosubmit_preference( $order_external_id, $value) {
		$prefs = ['autoSubmit' => $value];
		return $this->api->updateOrderPreferences($order_external_id, $prefs);
	}
	
	private function get_additional_services( &$order) {
		$additional_services = array();
		$shipping_service = '';
		$settings = Posti_Warehouse_Settings::get_shipping_settings();
		$shipping_methods = $order->get_shipping_methods();
		$chosen_shipping_method = array_pop($shipping_methods);
		$add_cod_to_additional_services = 'cod' === $order->get_payment_method();

		if (!empty($chosen_shipping_method)) {
			$method_id = $chosen_shipping_method->get_method_id();

			if ('local_pickup' === $method_id) {
				return ['service' => $shipping_service, 'additional_services' => $additional_services];
			}

			$instance_id = $chosen_shipping_method->get_instance_id();
			$pickup_points = isset($settings['pickup_points']) ? json_decode($settings['pickup_points'], true) : array();
			if (isset($pickup_points[$instance_id])
				&& isset($pickup_points[$instance_id]['service'])
				&& !empty($pickup_points[$instance_id]['service'])
				&& '__NULL__' !== $pickup_points[$instance_id]['service']) {

				$service_id = $pickup_points[$instance_id]['service'];
				$shipping_service = $service_id;

				$hide_outdoors = isset($pickup_points[$instance_id][$service_id]['pickuppoints_hideoutdoors']) ? $pickup_points[$instance_id][$service_id]['pickuppoints_hideoutdoors'] : 'no';
				if ('yes' === $hide_outdoors) {
					$service = $this->service->get_service($service_id);
					if (isset($service) && 'Posti' === $service['provider']) {
						$additional_services['3376'] = array();
					}
				}

				if (isset($pickup_points[$instance_id][$service_id])
					&& !empty($pickup_points[$instance_id][$service_id])
					&& isset($pickup_points[$instance_id][$service_id]['additional_services'])
					&& !empty($pickup_points[$instance_id][$service_id]['additional_services'])) {

					$services = $pickup_points[$instance_id][$service_id]['additional_services'];
					foreach ($services as $service_code => $value) {
						if ('yes' === $value && '3101' === $service_code) {
							$add_cod_to_additional_services = true;
							break;
						}
					}
				}
			}
		}

		if ($add_cod_to_additional_services) {
			$additional_services['3101'] = array(
				'amount' => $order->get_total(),
				'reference' => $this->calculate_reference($order->get_id()),
			);
		}

		return ['service' => $shipping_service, 'additional_services' => $additional_services];
	}

	public static function calculate_reference( $id) {
		$weights = array(7, 3, 1);
		$sum = 0;

		$base = str_split(strval(( $id )));
		$reversed_base = array_reverse($base);
		$reversed_base_length = count($reversed_base);

		for ($i = 0; $i < $reversed_base_length; $i++) {
			$sum += $reversed_base[$i] * $weights[$i % 3];
		}

		$checksum = ( 10 - $sum % 10 ) % 10;

		$reference = implode('', $base) . $checksum;

		return $reference;
	}

	private function get_order_external_id($order_id) {
		$order = wc_get_order($order_id);
		return isset($order) ? $this->get_order_external_id_field($order) : null;
	}
	
	private function get_order_external_id_field($order) {
		return $order->get_meta('_posti_id', true);
	}

	private function get_order_autosubmit_preference(&$order) {
		return isset($order['preferences']['autoSubmit']) ? $order['preferences']['autoSubmit'] : true;
	}

	private function prepare_posti_order($posti_order_id, &$_order, &$order_services, $preferences) {
		$shipping_phone = $_order->get_shipping_phone();
		$shipping_email = $_order->get_meta('_shipping_email', true);
		$phone = !empty($shipping_phone) ? $shipping_phone : $_order->get_billing_phone();
		$email = !empty($shipping_email) ? $shipping_email : $_order->get_billing_email();
		if (empty($phone) && empty($email)) {
			throw new \Exception('ERROR: Email and phone are missing.');
		}
		
		$additional_services = [];
		foreach ($order_services['additional_services'] as $_service => $_service_data) {
			$additional_services[] = ['serviceCode' => (string) $_service];
		}

		$order_items = array();
		$total_price = 0;
		$total_tax = 0;
		$items = $_order->get_items();
		$item_counter = 1;
		$service_code = $order_services['service'];
		$pickup_point = $_order->get_meta('_warehouse_pickup_point_id', true); //_woo_posti_shipping_pickup_point_id

		foreach ($_order->get_items('shipping') as $item_id => $shipping_item_obj) {
			$item_service_code = $shipping_item_obj->get_meta('service_code');
			if ($item_service_code) {
				$service_code = $item_service_code;
			}
		}
		
		$warehouses = $this->api->getWarehouses();
		foreach ($items as $item_id => $item) {
			$product_warehouse = get_post_meta($item['product_id'], '_posti_wh_warehouse', true);
			$type = $this->product->get_stock_type($warehouses, $product_warehouse);
			if ('Posti' === $type || 'Store' === $type || 'Catalog' === $type) {
				$total_price += $item->get_total();
				$total_tax += $item->get_subtotal_tax();
				if (isset($item['variation_id']) && $item['variation_id']) {
					$_product = wc_get_product($item['variation_id']);
				} else {
					$_product = wc_get_product($item['product_id']);
				}
				
				$external_id = $_product->get_meta('_posti_id', true);
				$ean = $_product->get_meta('_ean', true);
				$order_items[] = [
					'externalId' => (string) $item_counter,
					'externalProductId' => $external_id,
					'productEANCode' => $ean,
					'productUnitOfMeasure' => 'KPL',
					'productDescription' => $item['name'],
					'externalWarehouseId' => $product_warehouse,
					'quantity' => $item['qty']
				];
				$item_counter++;
			}
		}
		
		$order = array(
			'externalId' => (string) $posti_order_id,
			'orderDate' => date('Y-m-d\TH:i:s.vP', strtotime($_order->get_date_created()->__toString())),
			'metadata' => [
				'documentType' => 'SalesOrder',
				'client' => $this->api->getUserAgent()
			],
			'vendor' => [
				'name' => get_option('blogname'),
				'streetAddress' => get_option('woocommerce_store_address'),
				'postalCode' => get_option('woocommerce_store_postcode'),
				'postOffice' => get_option('woocommerce_store_city'),
				'country' => get_option('woocommerce_default_country'),
				'email' => get_option('admin_email')
			],
			'sender' => [
				'name' => get_option('blogname'),
				'streetAddress' => get_option('woocommerce_store_address'),
				'postalCode' => get_option('woocommerce_store_postcode'),
				'postOffice' => get_option('woocommerce_store_city'),
				'country' => get_option('woocommerce_default_country'),
				'email' => get_option('admin_email')
			],
			'client' => [
				'name' => $_order->get_billing_first_name() . ' ' . $_order->get_billing_last_name(),
				'streetAddress' => $_order->get_billing_address_1(),
				'postalCode' => $_order->get_billing_postcode(),
				'postOffice' => $_order->get_billing_city(),
				'country' => $_order->get_billing_country(),
				'telephone' => $_order->get_billing_phone(),
				'email' => $_order->get_billing_email()
			],
			'recipient' => [
				'name' => $_order->get_billing_first_name() . ' ' . $_order->get_billing_last_name(),
				'streetAddress' => $_order->get_billing_address_1(),
				'postalCode' => $_order->get_billing_postcode(),
				'postOffice' => $_order->get_billing_city(),
				'country' => $_order->get_billing_country(),
				'telephone' => $_order->get_billing_phone(),
				'email' => $_order->get_billing_email()
			],
			'deliveryAddress' => [
				'name' => $_order->get_shipping_first_name() . ' ' . $_order->get_shipping_last_name(),
				'streetAddress' => $_order->get_shipping_address_1(),
				'postalCode' => $_order->get_shipping_postcode(),
				'postOffice' => $_order->get_shipping_city(),
				'country' => $_order->get_shipping_country(),
				'telephone' => $phone,
				'email' => $email
			],
			'pickupPointId' => $pickup_point,
			'currency' => $_order->get_currency(),
			'serviceCode' => (string) $service_code,
			'totalPrice' => $total_price,
			'totalTax' => $total_tax,
			'totalWholeSalePrice' => $total_price + $total_tax,
			'rows' => $order_items
		);

		$note = $_order->get_customer_note();
		if (!empty($note)) {
			$order['comments'] = array(array('type' => 'pickingNote', 'value' => $note));
		}

		if ($additional_services) {
			$order['additionalServices'] = $additional_services;
		}

		$order['preferences'] = $preferences;

		return $order;
	}

	public function posti_check_order( $order_id, $old_status, $new_status) {
		if ('processing' === $new_status || 'on-hold' === $new_status) {
			$order = wc_get_order($order_id);
			$is_posti_order = $this->hasPostiProducts($order);
			$posti_order_id = $this->get_order_external_id_field($order);

			$options = Posti_Warehouse_Settings::get();
			if ('processing' === $new_status && isset($options['posti_wh_field_autoorder'])) {
				if ('on-hold' === $old_status && !empty($posti_order_id)) {
					$this->submitDelayedOrder($posti_order_id, $order, $options, $new_status);
				}
				else if ($is_posti_order) {
					if (empty($posti_order_id)) {
						$this->addOrderWithOptions($order, $options, $new_status);
					}

				} else {
					$this->logger->log('info', 'Order  ' . $order_id . ' is not posti');
				}
			}
			elseif ('on-hold' === $new_status && isset($options['posti_wh_field_reserve_onhold'])) {
				if ($is_posti_order) {
					if (empty($posti_order_id)) {
						$this->addOrderWithOptions($order, $options, $new_status);
					}
					
				} else {
					$this->logger->log('info', 'Order  ' . $order_id . ' is not posti');
				}
			}
		}
		elseif ('cancelled' === $new_status) {
			$order = wc_get_order($order_id);
			$this->cancelOrder($order);
		}
	}

	public function posti_tracking_column( $columns) {
		$new_columns = array();
		foreach ($columns as $key => $name) {
			$new_columns[$key] = $name;
			if ('order_status' === $key) {
				$new_columns['posti_api_tracking'] = Posti_Warehouse_Text::tracking_title();
			}
		}
		return $new_columns;
	}

	public function posti_tracking_column_data( $column_name, $order_id) {
		if ('posti_api_tracking' == $column_name) {
			$order = wc_get_order($order_id);
			$tracking = $order ? $order->get_meta('_posti_api_tracking', true) : false;
			echo $tracking ? esc_html($tracking) : '–';
		}
	}

	public function addTrackingToEmail( $order, $sent_to_admin, $plain_text, $email) {
		$tracking = $order->get_meta('_posti_api_tracking', true);
		if ($tracking) {
			echo esc_html(Posti_Warehouse_Text::tracking_number($tracking));
		}
	}

}
