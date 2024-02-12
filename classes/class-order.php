<?php

namespace Posti_Warehouse;

defined('ABSPATH') || exit;

class Posti_Warehouse_Order {
	
	private $orderStatus = false;
	private $addTracking = false;
	private $api;
	private $logger;
	private $product;
	private $status_mapping;
	
	public function __construct(Posti_Warehouse_Api $api, Posti_Warehouse_Logger $logger, Posti_Warehouse_Product $product, $addTracking = false) {
		$this->api = $api;
		$this->logger = $logger;
		$this->product = $product;
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
		add_filter('manage_edit-shop_order_columns', array($this, 'posti_tracking_column'));
		add_action('manage_posts_custom_column', array($this, 'posti_tracking_column_data'));
		
		add_filter( 'woocommerce_order_item_display_meta_key', array($this, 'change_metadata_title_for_order_shipping_method'), 20, 3 );
		
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
	
	public function getOrderStatus( $order_id) {
		$order_data = $this->getOrder($order_id);
		if (!$order_data) {
			return Posti_Warehouse_Text::order_not_placed();
		}
		$this->orderStatus = $order_data['status']['value'];
		return $order_data['status']['value'];
	}
	
	public function getOrderActionButton() {
		if (!$this->orderStatus) {
			?>
			<button type = "button" class="button button-posti" id = "posti-order-btn" name="posti_order_action"  onclick="posti_order_change(this);" value="place_order"><?php echo esc_html(Posti_Warehouse_Text::order_place()); ?></button>
			<?php
		}
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

	public function getOrder( $order_id) {
		$posti_order_id = get_post_meta($order_id, '_posti_id', true);
		if ($posti_order_id) {
			return $this->api->getOrder($posti_order_id);
		}
		return false;
	}

	public function addOrder( $order) {
		$options = Posti_Warehouse_Settings::get();
		return $this->addOrderWithOptions($order, $options);
	}

	public function addOrderWithOptions( $order, $options) {
		if (!is_object($order)) {
			$order = wc_get_order($order);
		}
		
		if (Posti_Warehouse_Settings::is_reject_partial_orders($options) && !$this->hasPostiProductsOnly($order)) {
			return [ 'error' => 'ERROR: Partial order not allowed.' ];
		}

		$order_services = $this->get_additional_services($order);
		if (!isset($order_services['service']) || empty($order_services['service'])) {
			$order->update_status('on-hold', Posti_Warehouse_Text::error_order_failed_no_shipping(), true);
			return [ 'error' => 'ERROR: Shipping method not configured.' ];
		}

		$data = null;
		$order_id = (string) $order->get_id();
		try {
			$data = $this->prepare_posti_order($order_id, $order, $order_services);
		} catch (\Exception $e) {
			$this->logger->log('error', $e->getMessage());
			return [ 'error' => $e->getMessage() ];
		}
		
		$result = $this->api->addOrder($data);
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
			update_post_meta($order_id, '_posti_id', (string) $order->get_id());
		} else {
			$order->update_status('failed', Posti_Warehouse_Text::order_failed(), true);
		}

		return false === $result ? [ 'error' => Posti_Warehouse_Text::error_order_not_placed() ] : [];
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
	
	private function sync_page( $page) {
		if (!isset($page) || false === $page) {
			return false;
		}

		$orders = $page['content'];
		if (!isset($orders) || !is_array($orders) || count($orders) == 0) {
			return false;
		}

		$order_ids = array();
		foreach ($orders as $order) {
			$order_id = $order['externalId'];
			if (isset($order_id) && strlen($order_id) > 0) {
				array_push($order_ids, (string) $order_id);
			}
		}
		
		$posts_query = array(
			'post_type' => 'shop_order',
			'post_status' => 'any',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => '_posti_id',
					'value' => $order_ids,
					'compare' => 'IN'
				)
			)
		);
		$posts = get_posts($posts_query);
		if (count($posts) == 0) {
			return true;
		}
		
		$post_by_order_id = array();
		foreach ($posts as $post) {
			$order_id = get_post_meta($post->ID, '_posti_id', true);
			if (isset($order_id) && strlen($order_id) > 0) {
				$post_by_order_id[$order_id] = $post->ID;
			}
		}
		
		$options = Posti_Warehouse_Settings::get();
		$autocomplete = Posti_Warehouse_Settings::get_value($options, 'posti_wh_field_autocomplete');
		foreach ($orders as $order) {
			$order_id = $order['externalId'];
			if (isset($post_by_order_id[$order_id]) && !empty($post_by_order_id[$order_id])) {
				$this->sync_order($post_by_order_id[$order_id], $order, $autocomplete);
			}
		}

		return true;
	}

	public function sync_order( $id, $order, $autocomplete) {
		$tracking = isset($order['trackingCodes']) ? $order['trackingCodes'] : '';
		if (!empty($tracking)) {
			if (is_array($tracking)) {
				$tracking = implode(', ', $tracking);
			}
			update_post_meta($id, '_posti_api_tracking', sanitize_text_field($tracking));
		}

		$status = $order['status']['value'];
		if (!isset($this->status_mapping[$status])) {
			return;
		}
		$status_new = $this->status_mapping[$status];

		$_order = wc_get_order($id);
		if (false === $_order) {
			return;
		}

		$data = $_order->get_data();
		$status_old = false !== $data ? $data['status'] : '';
		if ($status_old !== $status_new) {
			if ('completed' == $status_new) {
				if (isset($autocomplete)) {
					$_order->update_status($status_new, "Posti Glue: $status", true);
					$this->logger->log('info', "Changed order $id status $status_old -> $status_new");
				}
			} else {
				$_order->update_status($status_new, "Posti Glue: $status", true);
				$this->logger->log('info', "Changed order $id status $status_old -> $status_new");
			}
		}
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
				$services = array();

				if (isset($pickup_points[$instance_id][$service_id])
					&& !empty($pickup_points[$instance_id][$service_id])
					&& isset($pickup_points[$instance_id][$service_id]['additional_services'])
					&& !empty($pickup_points[$instance_id][$service_id]['additional_services'])) {

					$services = $pickup_points[$instance_id][$service_id]['additional_services'];
					foreach ($services as $service_code => $service) {
						if ('yes' === $service && '3101' !== $service_code) {
							$additional_services[$service_code] = null;
						} elseif ('yes' === $service && '3101' === $service_code) {
							$add_cod_to_additional_services = true;
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

	private function prepare_posti_order($posti_order_id, &$_order, &$order_services) {
		$shipping_phone = $_order->get_shipping_phone();
		$shipping_email = get_post_meta($_order->get_id(), '_shipping_email', true);
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
		$pickup_point = get_post_meta($_order->get_id(), '_warehouse_pickup_point_id', true); //_woo_posti_shipping_pickup_point_id

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
				
				$external_id = get_post_meta($_product->get_id(), '_posti_id', true);
				$ean = get_post_meta($_product->get_id(), '_ean', true);
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

		return $order;
	}

	public function posti_check_order( $order_id, $old_status, $new_status) {
		if ('processing' === $new_status) {
			$options = Posti_Warehouse_Settings::get();
			if (isset($options['posti_wh_field_autoorder'])) {
				$order = wc_get_order($order_id);
				$is_posti_order = $this->hasPostiProducts($order);
				$posti_order_id = get_post_meta($order_id, '_posti_id', true);
				
				if ($is_posti_order && empty($posti_order_id)) {
					$this->addOrderWithOptions($order, $options);

				} else {
					$this->logger->log('info', 'Order  ' . $order_id . ' is not posti');
				}
			}
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

	public function posti_tracking_column_data( $column_name) {
		if ('posti_api_tracking' == $column_name) {
			$tracking = get_post_meta(get_the_ID(), '_posti_api_tracking', true);
			echo $tracking ? esc_html($tracking) : '–';
		}
	}

	public function addTrackingToEmail( $order, $sent_to_admin, $plain_text, $email) {
		$tracking = get_post_meta($order->get_id(), '_posti_api_tracking', true);
		if ($tracking) {
			echo esc_html(Posti_Warehouse_Text::tracking_number($tracking));
		}
	}

}
