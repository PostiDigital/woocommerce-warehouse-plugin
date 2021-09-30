<?php

namespace PostiWarehouse\Classes;

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
                //$options = get_option('posti_wh_options');
                $options = get_option('woocommerce_posti_shipping_method_settings');
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
            //var_dump($this->api->getProductsByWarehouse($product_warehouse));
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
        //update product information
        $type = get_post_meta($post_id, '_posti_wh_stock_type', true);
        $product_warehouse = get_post_meta($post_id, '_posti_wh_warehouse', true);
        $product_distributor = get_post_meta($post_id, '_posti_wh_distribution', true);
        //$posti_product = get_post_meta($post_id, '_posti_wh_product', true);
        $options = get_option('posti_wh_options');
        $business_id = false;

        if (isset($options['posti_wh_field_business_id'])) {
            $business_id = $options['posti_wh_field_business_id'];
        }

        if (!$business_id) {
            $this->logger->log("erorr", "Cannot add  product id " . $post_id . " no Business id set");
            return;
        }

        $_product = wc_get_product($post_id);
        if (!$_product->get_sku()) {
            $this->logger->log("erorr", "Cannot add product id " . $post_id . " no SKU set");
            return false;
        }

        if ($type == 'Catalog') {
            //if dropshipping, id without business_id
            $posti_product_id = $_product->get_sku();
            update_post_meta($post_id, '_posti_id', $posti_product_id);
            
            update_post_meta($post_id, '_posti_last_sync', 0);
            $this->syncProducts([$post_id]);
        }
        /*
          if ($posti_product){
          //if have posti product and not dropshipping
          if ($type != 'Catalog') {
          delete_post_meta($post_id, '_posti_wh_product');
          } else {
          update_post_meta($post_id, '_posti_id', $posti_product);
          update_post_meta($post_id, '_posti_last_sync', 0);
          $this->syncProducts([$post_id]);
          }
          } */

        if (($type == "Posti" || $type == "Store") && $product_warehouse) {
            //id with business_id and sku
            $posti_product_id = $business_id . '-' . $_product->get_sku();
            update_post_meta($post_id, '_posti_id', $posti_product_id);
            
            $products = array();
            $products_ids = array();

            $type = $_product->get_type();
            $ean = get_post_meta($post_id, '_ean', true);
            $wholesale_price = (float) str_ireplace(',', '.', get_post_meta($post_id, '_wholesale_price', true));
            if (!$wholesale_price) {
                $wholesale_price = (float) $_product->get_price();
            }
            /*
              $posti_product_id = get_post_meta($post_id, '_posti_id', true);
              $wh_product_id = get_post_meta($post_id, '_wh_id', true);
              if (!$posti_product_id || !$wh_product_id || $posti_product_id !== $wh_product_id) {
              if ($wh_product_id) {
              $posti_product_id = $wh_product_id;
              } else {
              $posti_product_id = bin2hex(random_bytes(16));
              }
              update_post_meta($post_id, '_posti_id', $posti_product_id);
              //save unique id to other field, to be able to restore
              update_post_meta($post_id, '_wh_id', $posti_product_id);
              $this->logger->log("info", "Product id " . $post_id . " set _posti_id " . $posti_product_id);
              }
             */
            if ($type == 'variable') {
                $_products = $_product->get_children();
            } else {
                $product = array(
                    'externalId' => $posti_product_id,
                    "supplierId" => $business_id,
                    'descriptions' => array(
                        'en' => array(
                            'name' => $_product->get_name(),
                            'description' => $_product->get_description()
                        )
                    ),
                    'eanCode' => $ean, //$_product->get_sku(),
                    "unitOfMeasure" => "KPL",
                    "status" => "ACTIVE",
                    "recommendedRetailPrice" => (float) $_product->get_price(),
                    "currency" => get_woocommerce_currency(),
                    "distributor" => $product_distributor,
                    "isFragile" => get_post_meta($post_id, '_posti_fragile', true) ? true : false,
                    "isDangerousGoods" => get_post_meta($post_id, '_posti_lq', true) ? true : false,
                    "isOversized" => get_post_meta($post_id, '_posti_large', true) ? true : false,
                );

                $weight = $_product->get_weight();
                $length = $_product->get_length();
                $width = $_product->get_width();
                $height = $_product->get_height();
                $product['measurements'] = array(
                    "weight" => round(wc_get_weight($weight, 'kg'), 3),
                    "length" => round(wc_get_dimension($length, 'm'), 3),
                    "width" => round(wc_get_dimension($width, 'm'), 3),
                    "height" => round(wc_get_dimension($height, 'm'), 3),
                );

                $balances = array(
                    array(
                        "retailerId" => $business_id,
                        "productExternalId" => $posti_product_id,
                        "catalogExternalId" => $product_warehouse,
                        //"quantity" => 0.0,
                        "wholesalePrice" => $wholesale_price,
                        "currency" => get_woocommerce_currency()
                    )
                );
                $products_ids[$business_id . '-' . $_product->get_sku()] = $_product->get_id();
                $products[] = array('product' => $product, 'balances' => $balances);
            }
            if (count($products)) {
                $this->logger->log("info", "Product id " . $post_id . " added to posti: \n" . json_encode($products));
                $this->api->addProduct($products, $business_id);
                //add 0 to force sync
                update_post_meta($_product->get_id(), '_posti_last_sync', 0);
                $this->syncProducts($products_ids);
            }
        }
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

    public function syncProducts($ids) {
        foreach ($ids as $id) {
            try {
                $_product = wc_get_product($id);
                $options = get_option('posti_wh_options');
                $business_id = false;
                if (isset($options['posti_wh_field_business_id'])) {
                    $business_id = $options['posti_wh_field_business_id'];
                }
                if (!$business_id) {
                    $this->logger->log("error", "Cannot sync  product id " . $id . " no Business id set");
                    return;
                }
                $posti_product_id = get_post_meta($id, '_posti_id', true);
                if (!$posti_product_id) {
                    $this->logger->log("error", "Cannot sync  product id " . $id . " no _posti_id set");
                    return;
                }
                $stock_type = get_post_meta($id, '_posti_wh_stock_type', true);
                if ($stock_type == "Not_in_stock") {
                    return;
                }
                $product_data = $this->api->getProduct($posti_product_id);
                if (is_array($product_data)) {
                    $this->logger->log("info", "Posti info for product id " . $id . ":\n" . json_encode($product_data));
                    if (isset($product_data['balances']) && is_array($product_data['balances'])) {
                        $stock = 0;
                        foreach ($product_data['balances'] as $balance) {
                            if (isset($balance['quantity'])) {
                                $stock += $balance['quantity'];
                            }
                        }
                        $_product->set_stock_quantity($stock);
                        $_product->save();
                        $this->logger->log("info", "Set product id " . $id . " stock: " . $stock);
                        update_post_meta($_product->get_id(), '_posti_last_sync', time());
                        /*
                          $stocks = $product_data['warehouseBalance'];
                          foreach ($stocks as $stock){
                          if ($stock['externalWarehouseId'] == $product_warehouse){
                          $_product = set_stock_quantity(0)
                          }
                          }
                         */
                    }
                } else {
                    $this->logger->log("error", "No data from glue for " . $posti_product_id . " product");
                }
            } catch (\Exception $e) {
                $this->logger->log("error", $e->getMessage());
            }
        }
    }

}
