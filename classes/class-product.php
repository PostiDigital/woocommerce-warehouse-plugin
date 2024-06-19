<?php

namespace Posti_Warehouse;

defined('ABSPATH') || exit;

class Posti_Warehouse_Product {
	
	private $api;
	private $logger;
	private $assets_url;
	
	public function __construct(Posti_Warehouse_Api $api, Posti_Warehouse_Logger $logger) {
		
		$this->api = $api;
		$this->logger = $logger;
		$this->assets_url = plugins_url('assets', dirname(__FILE__));
		
		add_action('admin_notices', array($this, 'posti_notices'));
		
		add_action('wp_ajax_posti_warehouses', array($this, 'get_ajax_posti_warehouse'));
		
		add_filter('woocommerce_product_data_tabs', array($this, 'posti_wh_product_tab'), 99, 1);
		add_action('woocommerce_product_data_panels', array($this, 'posti_wh_product_tab_fields'));
		add_action('woocommerce_process_product_meta', array($this, 'posti_wh_product_tab_fields_save'));
		add_action('woocommerce_process_product_meta', array($this, 'after_product_save'), 99);
		
		add_action('woocommerce_product_options_inventory_product_data', array($this, 'woocom_simple_product_ean_field'), 10, 1);
		add_action('woocommerce_product_options_general_product_data', array($this, 'woocom_simple_product_wholesale_field'), 10, 1);
		
		add_action('woocommerce_product_after_variable_attributes', array($this, 'variation_settings_fields'), 10, 3);
		add_action('woocommerce_save_product_variation', array($this, 'variation_settings_fields_save'), 10, 2);
		
		add_filter('bulk_actions-edit-product', array($this, 'bulk_actions_warehouse_products'));
		add_filter('handle_bulk_actions-edit-product', array($this, 'handle_bulk_actions_warehouse_products'), 10, 3);
		
		add_filter('manage_edit-product_columns', array($this, 'custom_columns_register'), 11);
		add_action('manage_product_posts_custom_column', array($this, 'custom_columns_show'), 10, 2);
	}
	
	public function custom_columns_register( $columns) {
		$columns['warehouse'] = '<span class="parent-tips" data-tip="' . esc_html(Posti_Warehouse_Text::column_warehouse()) . '"><img class="posti_wh-icon" src="' . $this->assets_url . '/img/warehouse.svg" /></span>';
		return $columns;
	}
	
	public function custom_columns_show( $column, $product_id) {
		if ('warehouse' === $column) {
			$externalId = get_post_meta($product_id, '_posti_wh_warehouse', true);
			if (empty($externalId)) {
				echo '';
			}
			else {
				$warehouses = $this->api->getWarehouses();
				$warehouse = $this->get_warehouse_name($warehouses, $externalId);
				echo '<span class="tips dashicons dashicons-saved" data-tip="' . esc_html($warehouse) . "\"> </span>";
			}
		}
	}
	
	public function bulk_actions_warehouse_products( $bulk_actions) {
		$bulk_actions['_posti_wh_bulk_actions_publish_products'] = Posti_Warehouse_Text::action_publish_to_warehouse();
		$bulk_actions['_posti_wh_bulk_actions_remove_products'] = Posti_Warehouse_Text::action_remove_from_warehouse();
		
		return $bulk_actions;
	}
	
	public function handle_bulk_actions_warehouse_products( $redirect_to, $action, $post_ids) {
		if (count($post_ids) == 0) {
			return $redirect_to;
		}
		
		if ('_posti_wh_bulk_actions_publish_products' === $action
			|| '_posti_wh_bulk_actions_remove_products' === $action) {

			$cnt_fail = 0;
			if ('_posti_wh_bulk_actions_publish_products' === $action) {
				$warehouse = isset($_REQUEST['_posti_wh_warehouse_bulk_publish']) ? sanitize_text_field($_REQUEST['_posti_wh_warehouse_bulk_publish']) : null;
				if (!empty($warehouse)) {
					$cnt_fail = $this->handle_products($post_ids, $warehouse);
				}
				
			} elseif ('_posti_wh_bulk_actions_remove_products' === $action) {
				$cnt_fail = $this->handle_products($post_ids, '--delete');
				
			}
			
			$redirect_to = add_query_arg(array(
				'products_total' => count($post_ids),
				'products_fail' => $cnt_fail), $redirect_to);
		}
		
		return $redirect_to;
	}

	public function has_known_stock_type($product_id) {
		$product_warehouse = get_post_meta($product_id, '_posti_wh_warehouse', true);
		$type = $this->get_stock_type_by_warehouse($product_warehouse);
		return 'Posti' === $type || 'Store' === $type || 'Catalog' === $type;
	}

	public function woocom_simple_product_ean_field() {
		global $woocommerce, $post;
		$product = new \WC_Product(get_the_ID());
		echo '<div id="ean_attr" class="options_group">';
		woocommerce_wp_text_input(
			array(
				'id' => '_ean',
				'label' => Posti_Warehouse_Text::field_ean(),
				'placeholder' => '',
				'desc_tip' => 'true',
				'description' => Posti_Warehouse_Text::field_ean_caption()
			)
			);
		echo '</div>';
	}
	
	public function woocom_simple_product_wholesale_field() {
		global $woocommerce, $post;
		$product = new \WC_Product(get_the_ID());
		echo '<div id="wholesale_attr" class="options_group">';
		woocommerce_wp_text_input(
			array(
				'id' => '_wholesale_price',
				'label' => Posti_Warehouse_Text::field_price(),
				'placeholder' => '',
				'desc_tip' => 'true',
				'type' => 'number',
				'custom_attributes' => array(
					'step' => '0.01',
					'min' => '0'
				),
				'description' => Posti_Warehouse_Text::field_price_caption()
			)
			);
		echo '</div>';
	}
	
	public function variation_settings_fields( $loop, $variation_data, $variation) {
		woocommerce_wp_text_input(
			array(
				'id' => '_ean[' . $variation->ID . ']',
				'label' => Posti_Warehouse_Text::field_ean(),
				'placeholder' => '',
				'desc_tip' => 'true',
				'description' => Posti_Warehouse_Text::field_ean_caption(),
				'value' => get_post_meta($variation->ID, '_ean', true)
			)
			);
		wp_nonce_field('posti_wh_nonce_var', 'posti_wh_nonce_var_' . $variation->ID);
	}
	
	public function variation_settings_fields_save( $post_id) {
		if (!check_admin_referer('posti_wh_nonce_var', 'posti_wh_nonce_var_' . $post_id)) {
			throw new \Exception('Nonce check failed for save_variation_settings_fields');
		}
		
		$ean_post = isset($_POST['_ean']) && isset($_POST['_ean'][$post_id]) ? sanitize_text_field($_POST['_ean'][$post_id]) : null;
		if (isset($ean_post)) {
			update_post_meta($post_id, '_ean', $ean_post);
		}
		$ean_post = get_post_meta($post_id, '_ean', true);
		if (empty($ean_post)) {
			delete_post_meta($post_id, '_ean', '');
		}
	}
	
	public function posti_wh_product_tab( $product_data_tabs) {
		$product_data_tabs['posti-tab'] = array(
			'label' => Posti_Warehouse_Text::company(),
			'target' => 'posti_wh_tab',
		);
		return $product_data_tabs;
	}
	
	public function get_ajax_posti_warehouse() {
		if (!isset($_REQUEST['security']) || !wp_verify_nonce(sanitize_key($_REQUEST['security']), 'posti_wh_nonce')) {
			throw new \Exception('Nonce check failed for get_ajax_posti_warehouse');
		}
		
		$warehouses = $this->api->getWarehouses();
		$warehouses_options = array();
		
		$catalogType = isset($_POST['catalog_type']) ? sanitize_text_field($_POST['catalog_type']) : null;
		foreach ($warehouses as $warehouse) {
			if (empty($catalogType) || $warehouse['catalogType'] === $catalogType) {
				array_push($warehouses_options, array(
					'value' => $warehouse['externalId'],
					'name' => $warehouse['catalogName'] . ' (' . $warehouse['externalId'] . ')',
					'type' => $warehouse['catalogType']
				));
			}
		}
		echo wp_json_encode($warehouses_options);
		die();
	}
	
	public function posti_wh_product_tab_fields() {
		global $woocommerce, $post;
		?>
		<!-- id below must match target registered in posti_wh_product_tab function -->
		<div id="posti_wh_tab" class="panel woocommerce_options_panel">
			<?php
			$warehouses = $this->api->getWarehouses();
			$product_warehouse = get_post_meta($post->ID, '_posti_wh_warehouse', true);
			$type = $this->get_stock_type($warehouses, $product_warehouse);
			if (!$type) {
				$options = Posti_Warehouse_Settings::get();
				$type = Posti_Warehouse_Settings::get_value($options, 'posti_wh_field_type');
			}

			$warehouses_options = array('' => 'Select warehouse');
			foreach ($warehouses as $warehouse) {
				if (!$type || $type !== $warehouse['catalogType']) {
					continue;
				}
				$warehouses_options[$warehouse['externalId']] = $warehouse['catalogName'] . ' (' . $warehouse['externalId'] . ')';
			}

			woocommerce_wp_select(
					array(
						'id' => '_posti_wh_stock_type',
						'class' => 'select short posti-wh-select2',
						'label' => Posti_Warehouse_Text::field_stock_type(),
						'options' => Posti_Warehouse_Dataset::getSToreTypes(),
						'value' => $type
					)
			);

			woocommerce_wp_select(
					array(
						'id' => '_posti_wh_warehouse',
						'class' => 'select short posti-wh-select2',
						'label' => Posti_Warehouse_Text::field_warehouse(),
						'options' => $warehouses_options,
						'value' => $product_warehouse
					)
			);

			woocommerce_wp_text_input(
					array(
						'id' => '_posti_wh_distribution',
						'label' => Posti_Warehouse_Text::field_distributor(),
						'placeholder' => '',
						'type' => 'text',
					)
			);

			foreach (Posti_Warehouse_Dataset::getServicesTypes() as $id => $name) {
				woocommerce_wp_checkbox(
						array(
							'id' => $id,
							'label' => $name,
						)
				);
			}
			
			wp_nonce_field('posti_wh_nonce_prod', 'posti_wh_nonce_prod');
			?>
		</div>
		<?php
	}

	public function posti_wh_product_tab_fields_save( $post_id) {
		if (!check_admin_referer('posti_wh_nonce_prod', 'posti_wh_nonce_prod')) {
			throw new \Exception('Nonce check failed for save_variation_settings_fields');
		}
		
		$this->save_form_field('_posti_wh_product', $post_id);
		$this->save_form_field('_posti_wh_distribution', $post_id);
		$this->save_form_field('_ean', $post_id);
		$this->save_form_field('_wholesale_price', $post_id);

		foreach (Posti_Warehouse_Dataset::getServicesTypes() as $id => $name) {
			$this->save_form_field($id, $post_id);
		}
		
		$warehouse = isset($_POST['_posti_wh_warehouse']) ? sanitize_text_field($_POST['_posti_wh_warehouse']) : null;
		update_post_meta($post_id, '_posti_wh_warehouse_single', ( empty($warehouse) ? '--delete' : $warehouse ));
	}
	
	public function after_product_save( $post_id) {
		$warehouse = get_post_meta($post_id, '_posti_wh_warehouse_single', true);
		$cnt_fail = $this->handle_products([$post_id], $warehouse);
		if (isset($cnt_fail) && $cnt_fail > 0) {
			update_post_meta($post_id, '_posti_last_sync', 0);
		}
	}
	
	public function handle_products( $post_ids, $product_warehouse_override) {
		$products = array();
		$product_id_diffs = array();
		$product_whs_diffs = array();
		$product_ids_map = array();
		$warehouses = $this->api->getWarehouses();
		$can_add_balances = $this->is_warehouse_supports_add_remove($warehouses, $product_warehouse_override);
		$cnt_fail = 0;
		foreach ($post_ids as $post_id) {
			$product_warehouse = $this->get_update_warehouse_id($post_id, $product_warehouse_override, $product_whs_diffs);
			$_product = wc_get_product($post_id);
			if (!$this->can_publish_product($_product)) {
				if (!empty($product_warehouse)) { // dont count: removing product from warehouse that is not there
					$cnt_fail++;
				}
				
				continue;
			}
			
			$retailerId = !empty($product_warehouse) ? $this->get_retailer_id($warehouses, $product_warehouse) : null;
			$product_distributor = get_post_meta($post_id, '_posti_wh_distribution', true);
			$wholesale_price = (float) str_ireplace(',', '.', get_post_meta($post_id, '_wholesale_price', true));
			
			$product_type = $_product->get_type();
			if ('variable' == $product_type) {
				$this->collect_products_variations($post_id, $retailerId,
					$_product, $product_distributor, $product_warehouse, $wholesale_price, $products, $product_id_diffs, $product_ids_map, $can_add_balances);
			} else {
				$this->collect_products_simple($post_id, $retailerId,
					$_product, $product_distributor, $product_warehouse, $wholesale_price, $products, $product_id_diffs, $product_ids_map, $can_add_balances);
			}
		}
		
		if (count($product_whs_diffs) > 0 || count($product_id_diffs) > 0) {
			$balances_obsolete = $this->get_balances_for_removal($product_whs_diffs, $product_ids_map, $warehouses);
			if (count($balances_obsolete) > 0) {
				$errors = $this->api->deleteInventoryBalances($balances_obsolete);
				if (false !== $errors) {
					$cnt = count($balances_obsolete);
					for ($i = 0; $i < $cnt; $i++) {
						if (!$this->contains_error($errors, $i)) {
							$balance_obsolete = $balances_obsolete[$i];
							$product_id_obsolete = $balance_obsolete['productExternalId'];
							$post_id_obsolete = $product_ids_map[$product_id_obsolete];
							
							$this->unlink_balance_from_post($post_id_obsolete);
						}
					}
				}
			}
/*
			// EOS status is used instead of delete
			$products_obsolete = $this->get_products_for_removal($product_id_diffs, $products, $product_ids_map, $warehouses);
			if (count($products_obsolete) > 0) {
				$errors = $this->api->deleteInventory($products_obsolete);
				if (false !== $errors) {
					$cnt = count($products_obsolete);
					for ($i = 0; $i < $cnt; $i++) {
						if (!$this->contains_error($errors, $i)) {
							$product_obsolete = $products_obsolete[$i];
							$product_id_obsolete = $product_obsolete['product']['externalId'];
							$post_id_obsolete = $product_ids_map[$product_id_obsolete];
							
							$this->unlink_product_from_post($post_id_obsolete);
						}
					}
				}
			} else {
				// products never published to warehouse
				foreach ($product_whs_diffs as $diff) {
					$this->unlink_product_from_post($diff['id']);
				}
			}
*/
		}

		if (count($products) > 0) {
			$errors = $this->api->putInventory($products);
			if (false !== $errors) {
				$cnt = count($products);
				for ($i = 0; $i < $cnt; $i++) {
					if (!$this->contains_error($errors, $i)) {
						$product = $products[$i];
						$product_id = $product['product']['externalId'];
						$post_id = $product_ids_map[$product_id];

						$var_key = 'VAR-' . $product_id;
						$variation_post_id = isset($product_ids_map[$var_key]) ? $product_ids_map[$var_key] : null;

						if ('EOS' === $product['product']['status']) {
							$this->unlink_product_from_post($post_id, $variation_post_id);
						}
						else {
							$this->link_product_to_post($post_id, $variation_post_id, $product_id, $product_warehouse_override);
						}
					}
				}
			}
			
			$product_ids = array();
			foreach ($products as $product) {
				$product_id = $product['product']['externalId'];
				array_push($product_ids, $product_id);
			}
			$this->sync_by_ids($product_ids);
			
			if (false === $errors) {
				$cnt_fail = count($post_ids);
			} elseif (is_array($errors)) {
				$cnt_fail += count($errors);
			}
		}
		
		return $cnt_fail;
	}

	private function link_product_to_post( $post_id, $variation_post_id, $product_id, $product_warehouse_override) {
		update_post_meta($post_id, '_posti_wh_warehouse', sanitize_text_field($product_warehouse_override));
		
		$_post_id = !empty($variation_post_id) ? $variation_post_id : $post_id;
		update_post_meta($_post_id, '_posti_id', sanitize_text_field($product_id));
	}
	
	private function unlink_product_from_post( $post_id, $variation_post_id) {
		delete_post_meta($post_id, '_posti_id', '');
		delete_post_meta($post_id, '_posti_wh_warehouse', '');
		
		if (!empty($variation_post_id)) {
			delete_post_meta($variation_post_id, '_posti_id', '');
		}
	}
/*
	private function unlink_product_from_post( $post_id) {
		delete_post_meta($post_id, '_posti_id', '');
		delete_post_meta($post_id, '_posti_wh_warehouse', '');
		
		$_product = wc_get_product($post_id);
		if (false !== $_product && 'variable' === $_product->get_type()) {
			$variations = $_product->get_available_variations();
			foreach ($variations as $variation) {
				delete_post_meta($variation['variation_id'], '_posti_id', '');
			}
		}
	}
*/
	private function unlink_balance_from_post( $post_id) {
		delete_post_meta($post_id, '_posti_wh_warehouse', '');
	}
	
	private function can_publish_product( $_product) {
		$product_type = $_product->get_type();
		if ('variable' == $product_type) {
			$variations = $this->get_available_variations($_product);
			foreach ($variations as $variation) {
				if (!isset($variation['sku']) || empty($variation['sku'])) {
					return false;
				}
			}
		} else {
			if (empty($_product->get_sku())) {
				return false;
			}
		}
		
		return true;
	}
	
	private function collect_products_variations($post_id, $retailerId,
		$_product, $product_distributor, $product_warehouse, $wholesale_price, &$products, &$product_id_diffs, &$product_ids_map, $can_add_balances) {

		$variations = $this->get_available_variations($_product);
		foreach ($variations as $variation) {
			$variation_post_id = $variation['variation_id'];
			$variation_product_id = $this->get_update_product_id($variation_post_id, $variation['sku'], $product_id_diffs);
			$variable_name = $_product->get_name();
			$ean = get_post_meta($variation_post_id, '_ean', true);
			$specifications = [];
			$options = [
				'type' => 'Options',
				'properties' => [
				]
			];
			
			foreach ($variation['attributes'] as $attr_id => $attr) {
				$options['properties'][] = [
					'name' => (string) str_ireplace('attribute_', '', $attr_id),
					'value' => (string) self::strip_html($attr),
					'specifier' => '',
					'description' => ''
				];
				$variable_name .= ' ' . (string) $attr;
			}
			$specifications[] = $options;

			$product = array(
				'externalId' => $variation_product_id,
				'descriptions' => array(
					'en' => array(
						'name' => self::strip_html($variable_name),
						'description' => self::strip_html($_product->get_description()),
						'specifications' => $specifications,
					)
				),
				'eanCode' => $ean,
				'unitOfMeasure' => 'KPL',
				'status' => 'ACTIVE',
				'recommendedRetailPrice' => (float) $variation['display_regular_price'],
				'currency' => get_woocommerce_currency(),
				'distributor' => $product_distributor,
				'isFragile' => get_post_meta($post_id, '_posti_fragile', true) ? true : false,
				'isDangerousGoods' => get_post_meta($post_id, '_posti_lq', true) ? true : false,
				'isOversized' => get_post_meta($post_id, '_posti_large', true) ? true : false,
			);

			$weight = $variation['weight'] ? $variation['weight'] : 0;
			$length = $variation['dimensions']['length'] ? $variation['dimensions']['length'] : 0;
			$width = $variation['dimensions']['width'] ? $variation['dimensions']['width'] : 0;
			$height = $variation['dimensions']['height'] ? $variation['dimensions']['height'] : 0;
			$product['measurements'] = array(
				'weight' => round(wc_get_weight($weight, 'kg'), 3),
				'length' => round(wc_get_dimension($length, 'm'), 3),
				'width' => round(wc_get_dimension($width, 'm'), 3),
				'height' => round(wc_get_dimension($height, 'm'), 3),
			);
			
			$image_id = isset($variation['image_id']) ? $variation['image_id'] : null;
			$image_url = !empty($image_id) ? wp_get_attachment_image_url($image_id, 'full') : null;
			if (!empty($image_url)) {
				$product['images'] = [ array('url' => $image_url) ];
			}

			$product_ids_map[$variation_product_id] = $post_id;
			$product_ids_map['VAR-' . $variation_product_id] = $variation_post_id;
			if (!empty($product_warehouse)) {
				if ($can_add_balances) {
					$balances = array(
						array(
							'retailerId' => $retailerId,
							'catalogExternalId' => $product_warehouse,
							'wholesalePrice' => $wholesale_price ? $wholesale_price : (float) $variation['display_regular_price'],
							'currency' => get_woocommerce_currency()
						)
					);
				}
			}
			else {
				$product['status'] = 'EOS';
			}

			if (!empty($balances)) {
				array_push($products, array('product' => $product, 'balances' => $balances));
			} else {
				array_push($products, array('product' => $product));
			}
		}
		
		return true;
	}
	
	private function collect_products_simple($post_id, $retailerId,
		$_product, $product_distributor, $product_warehouse, $wholesale_price, &$products, &$product_id_diffs, &$product_ids_map, $can_add_balances) {

		$ean = get_post_meta($post_id, '_ean', true);
		if (!$wholesale_price) {
			$wholesale_price = (float) $_product->get_price();
		}

		$product_id = $this->get_update_product_id($post_id, $_product->get_sku(), $product_id_diffs);
		$product = array(
			'externalId' => $product_id,
			'descriptions' => array(
				'en' => array(
					'name' => self::strip_html($_product->get_name()),
					'description' => self::strip_html($_product->get_description())
				)
			),
			'eanCode' => $ean,
			'unitOfMeasure' => 'KPL',
			'status' => 'ACTIVE',
			'recommendedRetailPrice' => (float) $_product->get_price(),
			'currency' => get_woocommerce_currency(),
			'distributor' => $product_distributor,
			'isFragile' => get_post_meta($post_id, '_posti_fragile', true) ? true : false,
			'isDangerousGoods' => get_post_meta($post_id, '_posti_lq', true) ? true : false,
			'isOversized' => get_post_meta($post_id, '_posti_large', true) ? true : false,
		);

		$weight = $_product->get_weight();
		$length = $_product->get_length();
		$width = $_product->get_width();
		$height = $_product->get_height();
		$product['measurements'] = array(
			'weight' => !empty($weight) ? round(wc_get_weight($weight, 'kg'), 3) : null,
			'length' => !empty($length) ? round(wc_get_dimension($length, 'm'), 3) : null,
			'width' => !empty($width) ? round(wc_get_dimension($width, 'm'), 3) : null,
			'height' => !empty($height) ? round(wc_get_dimension($height, 'm'), 3) : null
		);

		$image_id = $_product->get_image_id();
		$image_url = !empty($image_id) ? wp_get_attachment_image_url($image_id, 'full') : null;
		if (!empty($image_url)) {
			$product['images'] = [ array('url' => $image_url) ];
		}

		$product_ids_map[$product_id] = $post_id;
		if (!empty($product_warehouse)) {
			if ($can_add_balances) {
				$balances = array(
					array(
						'retailerId' => $retailerId,
						'catalogExternalId' => $product_warehouse,
						'wholesalePrice' => $wholesale_price,
						'currency' => get_woocommerce_currency()
					)
				);
			}
		}
		else {
			$product['status'] = 'EOS';
		}

		if (!empty($balances)) {
			array_push($products, array('product' => $product, 'balances' => $balances));
		} else {
			array_push($products, array('product' => $product));
		}
	}
/*
	private function get_products_for_removal( &$product_id_diffs, &$products, &$product_ids_map, &$warehouses) {
		$products_obsolete = array();
		foreach ($product_id_diffs as $diff) {
			$product_id = $diff['from'];
			if (!empty($product_id) && !$this->contains_active_product($products, $product_id)) {
				$product_ids_map[$product_id] = $diff['id'];

				$product_warehouse = get_post_meta($diff['id'], '_posti_wh_warehouse', true);
				if ($this->is_warehouse_supports_add_remove($warehouses, $product_warehouse)) {
					$product = array('externalId' => $product_id, 'status' => 'EOS');
					array_push($products_obsolete, array('product' => $product));
				}
			}
		}
		
		return $products_obsolete;
	}
*/
	private function get_balances_for_removal( &$product_whs_diffs, &$product_ids_map, &$warehouses) {
		$balances_obsolete = array();
		foreach ($product_whs_diffs as $diff) {
			$warehouse_from = $diff['from'];
			if ($this->is_warehouse_supports_add_remove($warehouses, $warehouse_from)) {
				$product_id = get_post_meta($diff['id'], '_posti_id', true);
				if (!empty($product_id)) {
					$product_ids_map[$product_id] = $diff['id'];
					
					array_push($balances_obsolete, array(
						'productExternalId' => $product_id,
						'catalogExternalId' => $warehouse_from,
						'retailerId' => $this->get_retailer_id($warehouses, $warehouse_from)));
				} else {
					$_product = wc_get_product($diff['id']);
					if (false !== $_product && 'variable' === $_product->get_type()) {
						$retailer_id = $this->get_retailer_id($warehouses, $warehouse_from);
						$variations = $this->get_available_variations($_product);
						foreach ($variations as $variation) {
							$variation_product_id = get_post_meta($variation['variation_id'], '_posti_id', true);
							$product_ids_map[$variation_product_id] = $diff['id'];
							
							array_push($balances_obsolete, array(
								'productExternalId' => $variation_product_id,
								'catalogExternalId' => $warehouse_from,
								'retailerId' => $retailer_id));
						}
					}
				}
			}
		}
		
		return $balances_obsolete;
	}

	public function posti_notices() {
		$screen = get_current_screen();
		if (( 'product' == $screen->id ) && ( 'edit' == $screen->parent_base )) {
			global $post;
			$last_sync = get_post_meta($post->ID, '_posti_last_sync', true);
			if (isset($last_sync) && 0 == $last_sync) {
				$class = 'notice notice-error';
				$message = Posti_Warehouse_Text::error_product_update();
				printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));

				delete_post_meta($post->ID, '_posti_last_sync', '');
			}
		}
		
		if (isset($_REQUEST['products_fail'])) {
			$cnt_fail = sanitize_text_field($_REQUEST['products_fail']);
			if ($cnt_fail > 0) {
				$class = 'notice notice-error';
				$message = "Action failed for $cnt_fail product(s)";
				printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
			}
		}
		
		wp_nonce_field('posti_wh_nonce', 'posti_wh_nonce');
	}

	public function sync( $datetime) {
		$response = $this->api->getBalancesUpdatedSince($datetime, 100);
		if (!$this->sync_page($response)) {
			return false;
		}

		$pages = $response['page']['totalPages'];
		for ($page = 1; $page < $pages; $page++) {
			$page_response = $this->api->getBalancesUpdatedSince($datetime, 100, $page);
			if (!$this->sync_page($page_response)) {
				break;
			}
		}
		
		return true;
	}
	
	private function sync_by_ids( &$product_ids) {
		$product_ids_chunks = array_chunk($product_ids, 30);
		foreach ($product_ids_chunks as $product_ids_chunk) {
			$response = $this->api->getBalances($product_ids_chunk);
			$balances = isset($response['content']) ? $response['content'] : null;
			if (isset($balances) && is_array($balances) && count($balances) > 0) {
				$this->sync_products($balances);
			}
		}
	}
	
	private function sync_page( &$page) {
		if (!isset($page) || false === $page) {
			return false;
		}

		$balances = isset($page['content']) ? $page['content'] : null;
		if (!isset($balances) || !is_array($balances) || 0 == count($balances)) {
			return false;
		}

		$this->sync_products($balances);

		return true;
	}
	
	private function sync_products( &$balances) {
		if (0 == count($balances)) {
			return;
		}
		
		$product_ids_tmp = array();
		foreach ($balances as $balance) {
			$product_id = $balance['productExternalId'];
			if (isset($product_id) && !empty($product_id)) {
				array_push($product_ids_tmp, $product_id);
			}
		}
		$product_ids = array_unique($product_ids_tmp);

		$options = Posti_Warehouse_Settings::get();
		$is_verbose = Posti_Warehouse_Settings::is_verbose_logging($options);
		if ($is_verbose) {
			$this->logger->log('info', "Got inventory updates for: " . implode(', ', $product_ids_tmp));
		}
		
		$posts_query = array(
			'post_type' => ['product', 'product_variation'],
			'numberposts' => -1,
			'meta_query' => array(
				array(
					'key' => '_posti_id',
					'value' => $product_ids,
					'compare' => 'IN'
				)
			)
		);
		$posts = get_posts($posts_query);
		if (0 == count($posts)) {
			if ($is_verbose) {
				$this->logger->log('info', "No matched products for inventory update");
			}
			
			return;
		}

		if ($is_verbose) {
			$matched_post_ids = array();
			foreach ($posts as $post) {
				array_push($matched_post_ids, (string) $post->ID);
			}
			$this->logger->log('info', "Matched products: " . implode(', ', $matched_post_ids));
		}

		$post_by_product_id = array();
		foreach ($posts as $post) {
			$product_id = get_post_meta($post->ID, '_posti_id', true);
			if (isset($product_id) && !empty($product_id)) {
				if (isset($post_by_product_id[$product_id])) {
					$post_ids = $post_by_product_id[$product_id];
					array_push($post_ids, $post->ID);
					$post_by_product_id[$product_id] = $post_ids;
				}
				else {
					$post_by_product_id[$product_id] = array($post->ID);
				}
			}
		}

		foreach ($balances as $balance) {
			$product_id = $balance['productExternalId'];
			if (isset($post_by_product_id[$product_id]) && !empty($post_by_product_id[$product_id])) {
				$post_ids = $post_by_product_id[$product_id];
				foreach ($post_ids as $post_id) {
					$this->sync_product($post_id, $product_id, $balance);
				}
			}
		}
	}
	
	private function sync_product( $id, $product_id, &$balance) {
		$_product = wc_get_product($id);
		if (!isset($_product)) {
			return;
		}

		$main_id = 'variation' == $_product->get_type() ? $_product->get_parent_id() : $id;
		$product_warehouse = get_post_meta($main_id, '_posti_wh_warehouse', true);
		if (!empty($product_warehouse)) {
			if (isset($balance['quantity']) && $product_warehouse === $balance['catalogExternalId']) {
				$totalStock = $balance['quantity'];
				$total_stock_old = $_product->get_stock_quantity();
				if (!isset($total_stock_old) || $total_stock_old != $totalStock) {
					$_product->set_stock_quantity($totalStock);
					$_product->save();
					$this->logger->log('info', "Set product $id ($product_id) stock: $total_stock_old -> $totalStock");
				}
			}
		}
	}

	private function save_form_field( $name, $post_id) {
		$value = isset($_POST[$name]) ? sanitize_text_field($_POST[$name]) : '';
		update_post_meta($post_id, $name, $value);
		
		return $value;
	}
/*
	private function contains_product( $products, $product_id) {
		foreach ($products as $product) {
			if ($product['product']['externalId'] === $product_id) {
				return true;
			}
		}
		
		return false;
	}
*/
	private function contains_error( $errors, $idx) {
		foreach ($errors as $error) {
			if ($error['index'] === $idx) {
				return true;
			}
		}
		
		return false;
	}

	private function get_update_product_id( $post_id, $product_id_latest, &$product_id_diffs) {
		if (!isset($product_id_latest) || empty($product_id_latest)) {
			return null;
		}

		$product_id = get_post_meta($post_id, '_posti_id', true);
		if (empty($product_id)) {
			$product_id = $product_id_latest;
			array_push($product_id_diffs, array('id' => $post_id, 'to' => $product_id_latest));
		} elseif ($product_id !== $product_id_latest) {
			array_push($product_id_diffs, array('id' => $post_id, 'from' => $product_id, 'to' => $product_id_latest));
			$product_id = $product_id_latest; // SKU changed since last update
		}

		return $product_id;
	}
	
	private function get_update_warehouse_id( $post_id, $product_warehouse_override, &$product_whs_diffs) {
		$product_warehouse = get_post_meta($post_id, '_posti_wh_warehouse', true);
		if ('--delete' === $product_warehouse_override) {
			if (!empty($product_warehouse)) {
				array_push($product_whs_diffs, array('id' => $post_id, 'from' => $product_warehouse, 'to' => ''));
				$product_warehouse = '';
			}
		} elseif (!empty($product_warehouse_override) && $product_warehouse_override !== $product_warehouse) {
			array_push($product_whs_diffs, array('id' => $post_id, 'from' => $product_warehouse, 'to' => $product_warehouse_override));
			$product_warehouse = $product_warehouse_override;
		}

		return $product_warehouse;
	}
	
	public function get_stock_type_by_warehouse( $product_warehouse) {
		$warehouses = $this->api->getWarehouses();
		return $this->get_stock_type($warehouses, $product_warehouse);
	}
	
	public function get_stock_type( $warehouses, $product_warehouse) {
		return $this->get_warehouse_property($warehouses, $product_warehouse, 'catalogType', 'Not_in_stock');
	}
	
	public function get_warehouse_name( $warehouses, $product_warehouse) {
		return $this->get_warehouse_property($warehouses, $product_warehouse, 'catalogName', '');
	}
	
	private function get_warehouse_property( $warehouses, $product_warehouse, $property, $defaultValue) {
		$result = $defaultValue;
		if (!empty($warehouses) && !empty($product_warehouse)) {
			foreach ($warehouses as $warehouse) {
				if ($warehouse['externalId'] === $product_warehouse) {
					$result = $warehouse[$property];
					break;
				}
			}
		}
		
		return $result;
	}
	
	private function get_retailer_id( $warehouses, $product_warehouse) {
		$result = null;
		if (!empty($product_warehouse)) {
			foreach ($warehouses as $warehouse) {
				if ($warehouse['externalId'] === $product_warehouse) {
					$result = $warehouse['retailerId'];
					break;
				}
			}
		}

		return $result;
	}
	
	private function is_warehouse_supports_add_remove( $warehouses, $warehouse) {
		return !empty($warehouse) && 'Catalog' !== $this->get_stock_type($warehouses, $warehouse);
	}

	private function get_available_variations($product) {
		$variation_ids = $product->get_children();

		if (empty($variation_ids)) {
			return [];
		}

		$available_variations = array();

		foreach ($variation_ids as $variation_id) {
			$variation = wc_get_product($variation_id);

			if ($variation && $variation->exists()) {
				$available_variations[] = $product->get_available_variation($variation);
			}
		}

		return array_values(array_filter($available_variations));
	}

	private static function strip_html($text) {
		return strip_tags(str_replace('<br>', "\n", $text));
	}
}
