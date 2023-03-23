<?php

namespace PostiWarehouse\Classes;

use \WP_Query;
use PostiWarehouse\Classes\Dataset;
use PostiWarehouse\Classes\Api;
use PostiWarehouse\Classes\Logger;

class Product {

    private $api;
    private $logger;

    public function __construct(Api $api, Logger $logger) {

        $this->api = $api;
        $this->logger = $logger;

        add_action('admin_notices', array($this, 'posti_notices'));
        //upate ajax warehouses
        add_action('wp_ajax_posti_warehouses', array($this, 'get_ajax_post_warehouse'));
        //upate ajax products
        add_action('wp_ajax_posti_products', array($this, 'get_ajax_posti_products'));

        add_filter('woocommerce_product_data_tabs', array($this, 'posti_wh_product_tab'), 99, 1);

        add_action('woocommerce_product_data_panels', array($this, 'posti_wh_product_tab_fields'));

        add_action('woocommerce_process_product_meta', array($this, 'posti_wh_product_tab_fields_save'));

        add_action('woocommerce_process_product_meta', array($this, 'after_product_save'), 99);
        //add_action('save_post_product', array($this, 'after_product_save'), 99, 3);
        //EAN field
        add_action('woocommerce_product_options_inventory_product_data', array($this, 'woocom_simple_product_ean_field'), 10, 1);
        //wholesale field
        add_action('woocommerce_product_options_general_product_data', array($this, 'woocom_simple_product_wholesale_field'), 10, 1);

        add_action('woocommerce_product_after_variable_attributes', array($this, 'variation_settings_fields'), 10, 3);
        add_action('woocommerce_save_product_variation', array($this, 'save_variation_settings_fields'), 10, 2);
    }

    public function woocom_simple_product_ean_field() {
        global $woocommerce, $post;
        $product = new \WC_Product(get_the_ID());
        echo '<div id="ean_attr" class="options_group">';
        woocommerce_wp_text_input(
                array(
                    'id' => '_ean',
                    'label' => __('EAN', 'posti-warehouse'),
                    'placeholder' => '01234567891231',
                    'desc_tip' => 'true',
                    'description' => __('Enter EAN number', 'posti-warehouse')
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
                    'label' => __('Wholesale price', 'posti-warehouse'),
                    'placeholder' => '',
                    'desc_tip' => 'true',
                    'type' => 'number',
                    'custom_attributes' => array(
                        'step' => '0.01',
                        'min' => '0'
                    ),
                    'description' => __('Enter wholesale price', 'posti-warehouse')
                )
        );
        echo '</div>';
    }

    public function variation_settings_fields($loop, $variation_data, $variation) {
        woocommerce_wp_text_input(
                array(
                    'id' => '_ean[' . $variation->ID . ']',
                    'label' => __('EAN', 'posti-warehouse'),
                    'placeholder' => '01234567891231',
                    'desc_tip' => 'true',
                    'description' => __('Enter EAN number', 'posti-warehouse'),
                    'value' => get_post_meta($variation->ID, '_ean', true)
                )
        );
    }

    public function save_variation_settings_fields($post_id) {

        $ean_post = $_POST['_ean'][$post_id];
        if (isset($ean_post)) {
            update_post_meta($post_id, '_ean', esc_attr($ean_post));
        }
        $ean_post = get_post_meta($post_id, '_ean', true);
        if (empty($ean_post)) {
            delete_post_meta($post_id, '_ean', '');
        }
    }

    public function posti_wh_product_tab($product_data_tabs) {
        $product_data_tabs['posti-tab'] = array(
            'label' => __('Posti', 'postic'),
            'target' => 'posti_wh_tab',
        );
        return $product_data_tabs;
    }

    public function get_ajax_posti_products() {

        if (!isset($_POST['warehouse_id'])) {
            wp_die('', '', 501);
        }
        $products = $this->parseApiProductsResponse($this->api->getProductsByWarehouse($_POST['warehouse_id']));

        foreach ($products as $id => $product) {
            $products_options[] = array('value' => $id, 'name' => $product);
        }

        echo json_encode($products_options);
        die();
    }

    private function parseApiProductsResponse($products) {
        $products_options = array();
        if (isset($products['content']) && is_array($products['content'])) {
            foreach ($products['content'] as $productData) {
                $product = $productData['product'];
                $products_options[$product['externalId']] = $product['descriptions']['en']['name'] . ' (' . $product['externalId'] . ')';
            }
        }
        return $products_options;
    }

    public function get_ajax_post_warehouse() {

        if (!isset($_POST['catalog_type'])) {
            wp_die('', '', 501);
        }
        $warehouses = $this->api->getWarehouses();
        $warehouses_options = array();
        foreach ($warehouses as $warehouse) {
            if ($warehouse['catalogType'] !== $_POST['catalog_type']) {
                continue;
            }
            $warehouses_options[] = array('value' => $warehouse['externalId'], 'name' => $warehouse['catalogName'] . ' (' . $warehouse['externalId'] . ')');
        }
        echo json_encode($warehouses_options);
        die();
    }

    public function posti_wh_product_tab_fields() {
        global $woocommerce, $post;
        ?>
        <!-- id below must match target registered in above add_my_custom_product_data_tab function -->
        <div id="posti_wh_tab" class="panel woocommerce_options_panel">
            <?php
            $type = get_post_meta($post->ID, '_posti_wh_stock_type', true);
            $product_warehouse = get_post_meta($post->ID, '_posti_wh_warehouse', true);
            if (!$type) {
                $options = get_option('woocommerce_posti_warehouse_settings');
                //$options = get_option('woocommerce_posti_shipping_method_settings');
                if (isset($options['posti_wh_field_type'])) {
                    $type = $options['posti_wh_field_type'];
                }
            }

            $warehouses = $this->api->getWarehouses();
            $warehouses_options = array('' => 'Select warehouse');
            foreach ($warehouses as $warehouse) {
                if (!$type || $type !== $warehouse['catalogType']) {
                    continue;
                }
                $warehouses_options[$warehouse['externalId']] = $warehouse['catalogName'] . ' (' . $warehouse['externalId'] . ')';
            }
            //can be used for product mapping
            /*
              $products_options = array('' => 'Select product');
              $product = get_post_meta($post->ID, '_posti_wh_product', true);
              if ($type == "Catalog" && $product_warehouse) {
              $products_options = $this->parseApiProductsResponse($this->api->getProductsByWarehouse($product_warehouse));
              }
             */
            woocommerce_wp_select(
                    array(
                        'id' => '_posti_wh_stock_type',
                        'class' => 'select short posti-wh-select2',
                        'label' => __('Stock type', 'posti-warehouse'),
                        'options' => Dataset::getSToreTypes(),
                        'value' => $type
                    )
            );

            woocommerce_wp_select(
                    array(
                        'id' => '_posti_wh_warehouse',
                        'class' => 'select short posti-wh-select2',
                        'label' => __('Warehouse', 'posti-warehouse'),
                        'options' => $warehouses_options,
                        'value' => $product_warehouse
                    )
            );
            /*
              woocommerce_wp_select(
              array(
              'id' => '_posti_wh_product',
              'class' => 'select short posti-wh-select2',
              'label' => __('Catalog product', 'posti-warehouse'),
              'options' => $products_options,
              'value' => $product
              )
              );
             */
            woocommerce_wp_text_input(
                    array(
                        'id' => '_posti_wh_distribution',
                        'label' => __('Distributor ID', 'posti-warehouse'),
                        'placeholder' => '',
                        'type' => 'text',
                    )
            );

            foreach (Dataset::getServicesTypes() as $id => $name) {
                woocommerce_wp_checkbox(
                        array(
                            'id' => $id,
                            'label' => $name,
                        )
                );
            }
            ?>
        </div>
        <?php
    }

    public function posti_wh_product_tab_fields_save($post_id) {

        $this->saveWCField('_posti_wh_stock_type', $post_id);
        $this->saveWCField('_posti_wh_warehouse', $post_id);
        $this->saveWCField('_posti_wh_product', $post_id);
        $this->saveWCField('_posti_wh_distribution', $post_id);
        $this->saveWCField('_ean', $post_id);
        $this->saveWCField('_wholesale_price', $post_id);

        foreach (Dataset::getServicesTypes() as $id => $name) {
            $this->saveWCField($id, $post_id);
        }
    }
    
    public function after_product_save($post_id) {
        $options = get_option('woocommerce_posti_warehouse_settings');
        $business_id = $options['posti_wh_field_business_id'];
        if (!isset($business_id) || strlen($business_id) == 0) {
            $this->logger->log("error", "Cannot add  product id " . $post_id . " no Business id set");
            return;
        }

        $_product = wc_get_product($post_id);
        $product_id_diffs = array();
        $type = get_post_meta($post_id, '_posti_wh_stock_type', true);
        if ($type == 'Catalog') {
            update_post_meta($post_id, '_posti_last_sync', 0);
            
            $product_id = $this->get_update_product_id($post_id, $business_id, $_product->get_sku(), $product_id_diffs);
            if (isset($product_id) && strlen($product_id) > 0) {
                $this->sync_products($business_id, [$product_id]);
            }
        }

        $products = null;
        $product_warehouse = get_post_meta($post_id, '_posti_wh_warehouse', true);
        if ($product_warehouse && ($type == "Posti" || $type == "Store")) {
            update_post_meta($post_id, '_posti_last_sync', 0);

            $product_distributor = get_post_meta($post_id, '_posti_wh_distribution', true);
            $wholesale_price = (float) str_ireplace(',', '.', get_post_meta($post_id, '_wholesale_price', true));

            $product_type = $_product->get_type();
            if ($product_type == 'variable') {
                $products = $this->create_products_variations($post_id, $business_id,
                        $_product, $product_distributor, $product_warehouse, $wholesale_price, $product_id_diffs);
            }
            else {

                $products = $this->create_products_simple($post_id, $business_id,
                        $_product, $product_distributor, $product_warehouse, $wholesale_price, $product_id_diffs);

            }
        }

        if (isset($products) && count($products) > 0) {
            $product_ids = array();
            foreach ($products as $product) {
                $product_id = $product['product']['externalId'];
                array_push($product_ids, $product_id);
            }
            $this->logger->log("info", "Products " . implode(', ', $product_ids) . " sent to Posti: \n" . json_encode($products));
            $this->api->putProducts($products);

            $product_ids_obsolete = array();
            foreach ($product_id_diffs as $diff) {
                update_post_meta($diff['id'], '_posti_id', $diff['to']);
                if (isset($diff['from']) && !$this->contains_product($products, $diff['from'])) {
                    array_push($product_ids_obsolete, $diff['from']);
                }
            }

            if (count($product_ids_obsolete) > 0) {
                $products_obsolete = array();
                foreach ($product_ids_obsolete as $product_id_obsolete) {
                    $product = array(
                        'externalId' => $product_id_obsolete,
                        'status' => 'EOS'
                    );
                    array_push($products_obsolete, array('product' => $product));
                }
                $this->api->putProducts($products_obsolete);
            }

            $this->sync_products($business_id, $product_ids);
        }
    }
    
    private function create_products_variations($post_id, $business_id,
            $_product, $product_distributor, $product_warehouse, $wholesale_price, &$product_id_diff) {

        $result = array();
        $variations = $_product->get_available_variations();
        foreach ($variations as $variation) {
            $variation_post_id = $variation['variation_id'];
            $variation_product_id = $this->get_update_product_id($variation_post_id, $business_id, $variation['sku'], $product_id_diff);
            if (!isset($variation_product_id) || strlen($variation_product_id) == 0) {
                $this->logger->log("error", "Cannot add product id " . $post_id . " variation ". $variation['variation_id'] ." no SKU set");
                continue;
            }
            
            $variable_name = $_product->get_name();
            update_post_meta($variation_post_id, '_posti_id', $variation_product_id);
            update_post_meta($variation_post_id, '_posti_wh_stock_type', $type);
            $ean = get_post_meta($variation_post_id, '_ean', true);
            $specifications = [];
            $options = [
                'type' => 'Options',
                'properties' => [
                ]
            ];
            
            foreach ($variation['attributes'] as $attr_id => $attr) {
                $options["properties"][] = [
                    'name' => (string) str_ireplace('attribute_', '', $attr_id),
                    'value' => (string) $attr,
                    'specifier' => '',
                    'description' => ''
                ];
                $variable_name .= ' ' . (string) $attr;
            }
            $specifications[] = $options;

            $product = array(
                'externalId' => $variation_product_id,
                "supplierId" => $business_id,
                'descriptions' => array(
                    'en' => array(
                        'name' => $variable_name,
                        'description' => $_product->get_description(),
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

            $balances = array(
                array(
                    'retailerId' => $business_id,
                    'catalogExternalId' => $product_warehouse,
                    'wholesalePrice' => $wholesale_price ? $wholesale_price : (float) $variation['display_regular_price'],
                    'currency' => get_woocommerce_currency()
                )
            );

            array_push($result, array('product' => $product, 'balances' => $balances));
        }
        
        return $result;
    }
    
    private function create_products_simple($post_id, $business_id,
            $_product, $product_distributor, $product_warehouse, $wholesale_price, &$product_id_diffs) {

        $result = array();
        $ean = get_post_meta($post_id, '_ean', true);
        if (!$wholesale_price) {
            $wholesale_price = (float) $_product->get_price();
        }

        $product_id = $this->get_update_product_id($post_id, $business_id, $_product->get_sku(), $product_id_diffs);
        if (!isset($product_id) || strlen($product_id) == 0) {
            $this->logger->log("error", "Cannot add product id " . $post_id . " no SKU set");
            return $result;
        }

        $product = array(
            'externalId' => $product_id,
            "supplierId" => $business_id,
            'descriptions' => array(
                'en' => array(
                    'name' => $_product->get_name(),
                    'description' => $_product->get_description()
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
            'weight' => round(wc_get_weight($weight, 'kg'), 3),
            'length' => round(wc_get_dimension($length, 'm'), 3),
            'width' => round(wc_get_dimension($width, 'm'), 3),
            'height' => round(wc_get_dimension($height, 'm'), 3),
        );

        $balances = array(
            array(
                'retailerId' => $business_id,
                'catalogExternalId' => $product_warehouse,
                'wholesalePrice' => $wholesale_price,
                'currency' => get_woocommerce_currency()
            )
        );
        array_push($result, array('product' => $product, 'balances' => $balances));

        return $result;
    }

    private function saveWCField($name, $post_id) {
        $value = isset($_POST[$name]) ? $_POST[$name] : '';
        update_post_meta($post_id, $name, $value);
    }

    public function posti_notices() {
        $screen = get_current_screen();
        if (( $screen->id == 'product' ) && ($screen->parent_base == 'edit')) {
            global $post;
            $id = $post->ID;
            $type = get_post_meta($post->ID, '_posti_wh_stock_type', true);
            $product_warehouse = get_post_meta($post->ID, '_posti_wh_warehouse', true);
            if ($type == "Not_in_stock") {
                return;
            }
            $last_sync = get_post_meta($post->ID, '_posti_last_sync', true);
            if ($type && !$product_warehouse) {
                $class = 'notice notice-error';
                $message = __('Posti error: Please select Posti warehouse.', 'posti-warehouse');
                printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
            } elseif ($type && (!$last_sync || $last_sync < (time() - 3600))) {
                $class = 'notice notice-error';
                $message = __('Posti error: product sync not active. Please check product SKU, price or try resave.', 'posti-warehouse');
                printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
            }
        }
    }

    public function sync($datetime) {        
        $options = get_option('woocommerce_posti_warehouse_settings');
        $business_id = $options['posti_wh_field_business_id'];
        if (!isset($business_id) || strlen($business_id) <= 0) {
            $this->logger->log("error", "Cannot sync products: no Business id set");
            return false;
        }

        $response = $this->api->getBalancesUpdatedSince($datetime, 50);
        if (!$this->sync_page($business_id, $response)) {
            return false;
        }

        $pages = $response['page']['totalPages'];
        for ($page = 1; $page < $pages; $page++) {
            $page_response = $this->api->getBalancesUpdatedSince($datetime, 50, $page);
            if (!$this->sync_page($business_id, $page_response)) {
                break;
            }
        }
        
        return true;
    }
    
    private function sync_page($business_id, $page) {
        if (!isset($page)) {
            return false;
        }

        $balances = $page['content'];
        if (!isset($balances) || !is_array($balances) || count($balances) == 0) {
            return false;
        }

        $product_ids_tmp = array();
        foreach ($balances as $balance) {
            $product_id = $balance['productExternalId'];
            if (isset($product_id) && strlen($product_id) > 0) {
                array_push($product_ids_tmp, $product_id);
            }
        }
        $product_ids = array_unique($product_ids_tmp);
        $this->sync_products($business_id, $product_ids);

        return true;
    }
    
    private function sync_products($business_id, $product_ids) {
        if (count($product_ids) == 0) {
            return;
        }

        $posts_query = array(
            'post_type' => ['product', 'product_variation'],
            'meta_query' => array(
                array(
                    'key' => '_posti_id',
                    'value' => $product_ids,
                    'compare' => 'IN'
                )
            )
        );
        $posts = get_posts($posts_query);
        if (count($posts) == 0) {
            return;
        }

        $post_by_product_id = array();
        foreach ($posts as $post) {
            $product_id = get_post_meta($post->ID, '_posti_id', true);
            if (isset($product_id) && strlen($product_id) > 0) {
                $post_by_product_id[$product_id] = $post->ID;
            }
        }

        $product_ids_chunks = array_chunk($product_ids, 30);
        foreach ($product_ids_chunks as $product_ids_chunk) {
            try {
                $response = $this->api->getProducts($product_ids_chunk);
                if (isset($response)) {
                    $products_with_balances = $response['content'];
                    if (isset($products_with_balances) && is_array($products_with_balances)) {
                        foreach ($products_with_balances as $product_with_balances) {
                            $product = $product_with_balances['product'];
                            $product_id = $product['externalId'];
                            $id = $post_by_product_id[$product_id];
                            if (isset($id) && strlen($id) > 0) {
                                $this->sync_product($id, $product_id, $product_with_balances['balances']);
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->logger->log("error", $e->getMessage());
            }
        }
    }
    
    private function sync_product($id, $product_id, $balances) {
        $_product = wc_get_product($id);
        if (!isset($_product)) {
            return;
        }

        $totalStock = 0;
        if (isset($balances) && is_array($balances)) {
            $stock = 0;
            $sharedStock = 0;
            foreach ($balances as $balance) {
                if (isset($balance['quantity'])) {
                    if (isset($balance['sharedStock']) && $balance['sharedStock']) {
                        $sharedStock = $balance['quantity'];
                    }
                    else {
                        $stock += $balance['quantity'];
                    }
                }
            }

            $totalStock = $stock + $sharedStock;
        }

        $total_stock_old = $_product->get_stock_quantity();
        if (!isset($total_stock_old) || $total_stock_old != $totalStock) {
            $_product->set_stock_quantity($totalStock);
            $_product->save();
            $this->logger->log("info", "Set product $id ($product_id) stock: $total_stock_old -> $totalStock");
        }

        //if variation, update main product sync time
        $post_id = $id;
        if ($_product->get_type() == 'variation') {
            $post_id = $_product->get_parent_id();
        }

        if (isset($post_id)) {
            update_post_meta($post_id, '_posti_last_sync', time());
        }
    }
    
    private function contains_product($products, $product_id) {
        foreach ($products as $product) {
            if ($product['externalId'] === $product_id) {
                return true;
            }
        }
        
        return false;
    }
    
    private function get_update_product_id($post_id, $business_id, $product_id_latest, &$product_id_diffs) {
        if (!isset($product_id_latest) || strlen($product_id_latest) == 0) {
            return null;
        }

        $product_id = get_post_meta($post_id, '_posti_id', true);
        if (!isset($product_id) || strlen($product_id) == 0) {
            $product_id = $product_id_latest;
            array_push($product_id_diffs, array('id' => post_id, 'to' => $product_id_latest));
        }
        elseif ($product_id !== $product_id_latest) {
            $product_id_deprecated = $business_id . '-' . $product_id_latest;
            if ($product_id !== $product_id_deprecated) {
                array_push($product_id_diffs, array('id' => $post_id, 'from' => $product_id, 'to' => $product_id_latest));
                $product_id = $product_id_latest; // SKU changed since last update
            }
        }

        return $product_id;
    }
}
