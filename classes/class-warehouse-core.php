<?php
defined('ABSPATH') || exit;

class PostiWarehouse {

    private $store_types = array();
    private $api = null;
    private $metabox = null;
    private $order = null;
    private $business_id = false;

    public function __construct() {

        $options = get_option('posti_wh_options');
        $is_test = false;
        $debug = false;
        
        if (isset($options['posti_wh_field_test_mode']) && $options['posti_wh_field_test_mode'] == "1") {
            $is_test = true;
        }
         
        if (isset($options['posti_wh_field_debug']) && $options['posti_wh_field_debug'] == "1") {
            $debug = true;
        }
        if (isset($options['posti_wh_field_business_id'])) {
            $this->business_id = $options['posti_wh_field_business_id'];
        }

        $this->store_types = array(
            'Store' => __('Store', 'posti-warehouse'),
            'Posti' => __('Posti Warehouse', 'posti-warehouse'),
            'Not_in_stock' => __('Not in stock', 'posti-warehouse'),
            'Catalog' => __('Dropshipping', 'posti-warehouse'),
        );

        $this->api = new PostiWarehouseApi($this->business_id, $is_test, $debug);



        $this->order = new PostiOrder($this->api);

        $this->metabox = new PostiWarehouseMetabox($this->order);

        add_action('admin_init', array($this, 'posti_wh_settings_init'));




        add_action('admin_menu', array($this, 'posti_wh_options_page'));

        if ($debug) {
            add_action('admin_menu', array($this, 'posti_wh_debug_page'));
        }

        add_action('admin_enqueue_scripts', array($this, 'posti_wh_admin_styles'));



        $this->WC_hooks();
    }
    
    public function load_textdomain() {

        load_plugin_textdomain(
                'posti-warehouse',
                false,
                dirname(__FILE__) . '/languages/'
        );
    }

    public function posti_wh_admin_styles($hook) {

        wp_enqueue_style('posti_wh_admin_style', plugins_url('assets/css/admin-warehouse-settings.css', dirname(__FILE__)));
        wp_enqueue_script('posti_wh_admin_script', plugins_url('assets/js/admin-warehouse.js', dirname(__FILE__)));
    }

    public function posti_wh_debug_page() {
        add_submenu_page(
                'options-general.php',
                __('Posti Warehouse Debug', 'posti-warehouse'),
                __('Posti Warehouse Debug', 'posti-warehouse'),
                'manage_options',
                'posti_wh_debug',
                array($this, 'posti_wh_debug_page_html')
        );
    }

    public function posti_wh_debug_page_html() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <ul>
                <?php
                $debug = get_option('posti_wh_logs', array());
                if (!is_array($debug)) {
                    $debug = array($debug);
                }
                $debug = array_reverse($debug);
                foreach ($debug as $info) {
                    echo '<li>' . $info . '</li>';
                }
                ?>
            </ul>
        </div>
        <?php
    }

    public function posti_wh_settings_init() {

        register_setting('posti_wh', 'posti_wh_options');

        add_settings_section(
                'posti_wh_settings_section',
                __('Posti Warehouse settings', 'posti-warehouse'),
                array($this, 'posti_wh_section_developers_cb'),
                'posti_wh'
        );

        add_settings_field(
                'posti_wh_field_username',
                __('Username', 'posti-warehouse'),
                array($this, 'posti_wh_field_string_cb'),
                'posti_wh',
                'posti_wh_settings_section',
                [
                    'label_for' => 'posti_wh_field_username',
                    'class' => 'posti_wh_row',
                    'posti_wh_custom_data' => 'custom',
                ]
        );

        add_settings_field(
                'posti_wh_field_password',
                __('Password', 'posti-warehouse'),
                array($this, 'posti_wh_field_string_cb'),
                'posti_wh',
                'posti_wh_settings_section',
                [
                    'label_for' => 'posti_wh_field_password',
                    'class' => 'posti_wh_row',
                    'posti_wh_custom_data' => 'custom',
                ]
        );

        add_settings_field(
                'posti_wh_field_business_id',
                __('Business ID', 'posti-warehouse'),
                array($this, 'posti_wh_field_string_cb'),
                'posti_wh',
                'posti_wh_settings_section',
                [
                    'label_for' => 'posti_wh_field_business_id',
                    //'default' => 'A',
                    'class' => 'posti_wh_row',
                    'posti_wh_custom_data' => 'custom',
                ]
        );

        add_settings_field(
                'posti_wh_field_contract',
                __('Contract number', 'posti-warehouse'),
                array($this, 'posti_wh_field_string_cb'),
                'posti_wh',
                'posti_wh_settings_section',
                [
                    'label_for' => 'posti_wh_field_contract',
                    'class' => 'posti_wh_row',
                    'posti_wh_custom_data' => 'custom',
                ]
        );



        add_settings_field(
                'posti_wh_field_type',
                __('Default stock type', 'posti-warehouse'),
                array($this, 'posti_wh_field_type_cb'),
                'posti_wh',
                'posti_wh_settings_section',
                [
                    'label_for' => 'posti_wh_field_type',
                    'class' => 'posti_wh_row',
                    'posti_wh_custom_data' => 'custom',
                ]
        );

        add_settings_field(
                'posti_wh_field_autoorder',
                __('Auto ordering', 'posti-warehouse'),
                array($this, 'posti_wh_field_checkbox_cb'),
                'posti_wh',
                'posti_wh_settings_section',
                [
                    'label_for' => 'posti_wh_field_autoorder',
                    'class' => 'posti_wh_row',
                    'posti_wh_custom_data' => 'custom',
                ]
        );

        add_settings_field(
                'posti_wh_field_autocomplete',
                __('Auto mark orders as "Completed"', 'posti-warehouse'),
                array($this, 'posti_wh_field_checkbox_cb'),
                'posti_wh',
                'posti_wh_settings_section',
                [
                    'label_for' => 'posti_wh_field_autocomplete',
                    'class' => 'posti_wh_row',
                    'posti_wh_custom_data' => 'custom',
                ]
        );

        add_settings_field(
                'posti_wh_field_test_mode',
                __('Test mode', 'posti-warehouse'),
                array($this, 'posti_wh_field_checkbox_cb'),
                'posti_wh',
                'posti_wh_settings_section',
                [
                    'label_for' => 'posti_wh_field_test_mode',
                    'class' => 'posti_wh_row',
                    'posti_wh_custom_data' => 'custom',
                ]
        );

        add_settings_field(
                'posti_wh_field_debug',
                __('Debug', 'posti-warehouse'),
                array($this, 'posti_wh_field_checkbox_cb'),
                'posti_wh',
                'posti_wh_settings_section',
                [
                    'label_for' => 'posti_wh_field_debug',
                    'class' => 'posti_wh_row',
                    'posti_wh_custom_data' => 'custom',
                ]
        );
    }

    public function posti_wh_section_developers_cb($args) {
        
    }

    public function posti_wh_field_checkbox_cb($args) {
        $options = get_option('posti_wh_options');
        $checked = "";
        if ($options[$args['label_for']]) {
            $checked = ' checked="checked" ';
        }
        ?>
        <input <?php echo $checked; ?> id = "<?php echo esc_attr($args['label_for']); ?>" name='posti_wh_options[<?php echo esc_attr($args['label_for']); ?>]' type='checkbox' value = "1"/>
        <?php
    }

    public function posti_wh_field_string_cb($args) {
        $options = get_option('posti_wh_options');
        $value = $options[$args['label_for']];
        if (!$value && isset($args['default'])) {
            $value = $args['default'];
        }
        ?>
        <input id="<?php echo esc_attr($args['label_for']); ?>" name="posti_wh_options[<?php echo esc_attr($args['label_for']); ?>]" size='20' type='text' value="<?php echo $value; ?>" />
        <?php
    }

    public function posti_wh_field_type_cb($args) {

        $options = get_option('posti_wh_options');
        ?>
        <select id="<?php echo esc_attr($args['label_for']); ?>"
                data-custom="<?php echo esc_attr($args['posti_wh_custom_data']); ?>"
                name="posti_wh_options[<?php echo esc_attr($args['label_for']); ?>]"
                >
                    <?php foreach ($this->store_types as $val => $type): ?>
                <option value="<?php echo $val; ?>" <?php echo isset($options[$args['label_for']]) ? ( selected($options[$args['label_for']], $val, false) ) : ( '' ); ?>>
                    <?php
                    echo $type;
                    ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function posti_wh_options_page() {
        add_submenu_page(
                'options-general.php',
                'Posti Warehouse Settings',
                'Posti Warehouse Settings',
                'manage_options',
                'posti_wh',
                array($this, 'posti_wh_options_page_html')
        );
    }

    public function posti_wh_options_page_html() {
        if (!current_user_can('manage_options')) {
            return;
        }

        settings_errors('posti_wh_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('posti_wh');
                do_settings_sections('posti_wh');
                submit_button('Save');
                ?>
            </form>
        </div>
        <?php
    }

    public function WC_hooks() {
        add_filter('woocommerce_product_data_tabs', array($this, 'posti_wh_product_tab'), 99, 1);

        add_action('woocommerce_product_data_panels', array($this, 'posti_wh_product_tab_fields'));

        add_action('woocommerce_process_product_meta', array($this, 'posti_wh_product_tab_fields_save'));

        add_action('admin_notices', array($this, 'posti_notices'));

        add_action('save_post_product', array($this, 'after_product_save'));

//create cronjob to sync products and get order status
        add_filter('cron_schedules', array($this, 'posti_interval'));

        add_action('posti_cronjob', array($this, 'posti_cronjob_callback'));
        if (!wp_next_scheduled('posti_cronjob')) {
            wp_schedule_event(time(), '30_minutes', 'posti_cronjob');
        }

//on order sttaus chnage
        add_action('woocommerce_order_status_changed', array($this, 'posti_check_order'), 10, 3);

//upate ajax warehouses
        add_action('wp_ajax_posti_warehouses', array($this, 'get_ajax_post_warehouse'));

//filter shipping methods, if product is in Posti store, allow only posti shipping methods
        add_filter('woocommerce_package_rates', array($this, 'hide_other_shipping_if_posti_products'), 100, 1);


//api tracking columns
        add_filter('manage_edit-shop_order_columns', array($this, 'posti_tracking_column'));
        add_action('manage_posts_custom_column', array($this, 'posti_tracking_column_data'));

//EAN field
        add_action('woocommerce_product_options_inventory_product_data', array($this, 'woocom_simple_product_ean_field'), 10, 1);

        add_action('woocommerce_product_options_general_product_data', array($this, 'woocom_simple_product_wholesale_field'), 10, 1);

        add_action('woocommerce_product_after_variable_attributes', array($this, 'variation_settings_fields'), 10, 3);
        add_action('woocommerce_save_product_variation', array($this, 'save_variation_settings_fields'), 10, 2);
    }

    public function woocom_simple_product_ean_field() {
        global $woocommerce, $post;
        $product = new WC_Product(get_the_ID());
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
        $product = new WC_Product(get_the_ID());
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

    public function posti_interval($schedules) {
        $schedules['30_minutes'] = array(
            'interval' => 300, //1800,
            'display' => esc_html__('Every 30 Minutes'),);
        return $schedules;
    }

    public function posti_wh_product_tab($product_data_tabs) {
        $product_data_tabs['posti-tab'] = array(
            'label' => __('Posti', 'postic'),
            'target' => 'posti_wh_tab',
        );
        return $product_data_tabs;
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
            $warehouses_options[] = array('value' => $warehouse['externalId'], 'name' => $warehouse['externalId'] . ' - ' . $warehouse['catalogName']);
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
                $warehouses_options[$warehouse['externalId']] = $warehouse['externalId'] . ' - ' . $warehouse['catalogName'];
            }
            //var_dump($this->api->getProductsByWarehouse($product_warehouse));
            woocommerce_wp_select(
                    array(
                        'id' => '_posti_wh_stock_type',
                        'label' => __('Stock type', 'posti-warehouse'),
                        'options' => $this->store_types,
                        'value' => $type
                    )
            );

            woocommerce_wp_select(
                    array(
                        'id' => '_posti_wh_warehouse',
                        'label' => __('Warehouse', 'posti-warehouse'),
                        'options' => $warehouses_options,
                        'value' => $product_warehouse
                    )
            );

            woocommerce_wp_text_input(
                    array(
                        'id' => '_posti_wh_distribution',
                        'label' => __('Distributor ID', 'posti-warehouse'),
                        'placeholder' => '',
                        'type' => 'text',
                    )
            );
            ?>
        </div>
        <?php
    }

    public function posti_wh_product_tab_fields_save($post_id) {

        $this->saveWCField('_posti_wh_stock_type', $post_id);
        $this->saveWCField('_posti_wh_warehouse', $post_id);
        $this->saveWCField('_posti_wh_distribution', $post_id);
        $this->saveWCField('_ean', $post_id);
        $this->saveWCField('_wholesale_price', $post_id);
    }

    public function after_product_save($post_id) {
//update product information
        $type = get_post_meta($post_id, '_posti_wh_stock_type', true);
        $product_warehouse = get_post_meta($post_id, '_posti_wh_warehouse', true);
        $product_distributor = get_post_meta($post_id, '_posti_wh_distribution', true);
        
        if (($type == "Posti" || $type == "Store" || $type == "Catalog") && $product_warehouse) {
            //$options = get_option('posti_wh_options');
            $options = get_option('woocommerce_posti_shipping_method_settings');
            $business_id = false;
            if (isset($options['posti_wh_field_business_id'])) {
                $business_id = $options['posti_wh_field_business_id'];
            }
            if (!$business_id) {
                return;
            }
            $products = array();
            $products_ids = array();
            $_product = wc_get_product($post_id);
            if (!$_product->get_sku()) {
                return false;
            }
            $type = $_product->get_type();
            $ean = get_post_meta($post_id, '_ean', true);
            $wholesale_price = (float) str_ireplace(',', '.', get_post_meta($post_id, '_wholesale_price', true));
            if (!$wholesale_price) {
                $wholesale_price = (float) $_product->get_price();
            }
            if ($type == 'variable') {
                $_products = $_product->get_children();
            } else {
                $product = array(
                    'externalId' => $business_id . '-' . $_product->get_sku(),
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
                        "productExternalId" => $business_id . '-' . $_product->get_sku(),
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
            if ($type == "Not_in_stock"){
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

    private function syncProducts($ids) {
        foreach ($ids as $id) {
            $_product = wc_get_product($id);
            //$options = get_option('posti_wh_options');
            $options = get_option('woocommerce_posti_shipping_method_settings');
            $business_id = false;
            if (isset($options['posti_wh_field_business_id'])) {
                $business_id = $options['posti_wh_field_business_id'];
            }
            if (!$business_id) {
                return;
            }
            $product_reference = $business_id . '-' . $_product->get_sku();
            $stock_type = get_post_meta($id, '_posti_wh_stock_type', true);
            if ($stock_type == "Not_in_stock"){
                return;
            }
            $product_data = $this->api->getProduct($product_reference);
            if (is_array($product_data)) {
                if (isset($product_data['balances']) && is_array($product_data['balances'])) {
                    $stock = 0;
                    foreach ($product_data['balances'] as $balance) {
                        if (isset($balance['quantity'])) {
                            $stock += $balance['quantity'];
                        }
                    }
                    $_product->set_stock_quantity($stock);
                    $_product->save();
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
            }
        }
    }

    /*
     * Cronjob to sync products and orders
     */

    public function posti_cronjob_callback() {
        $args = array(
            'post_type' => 'product',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_posti_wh_stock_type',
                    'value' => array('Store', 'Posti'),
                    'compare' => 'IN'
                ),
                array(
                    'key' => '_posti_last_sync',
                    'value' => (time() - 3600),
                    'compare' => '<'
                ),
            ),
        );
        $products = get_posts($args);
        if (is_array($products)) {
            $product_ids = [];
            foreach ($products as $product) {
                $product_ids[] = $product->ID;
            }
            if (count($product_ids)) {
                $this->syncProducts($product_ids);
            }
        }

        $this->order->updatePostiOrders();
    }

    /*
     * Check all orders that gets status processing, if order has 
     * posti product, add order to API
     */

    public function posti_check_order($order_id, $old_status, $new_status) {
        $posti_order = false;
        if ($new_status == "processing") {
            //$options = get_option('posti_wh_options');
            $options = get_option('woocommerce_posti_shipping_method_settings');
            if (isset($options['posti_wh_field_autoorder'])) {
//if autoorder on, check if order has posti products
                $order = wc_get_order($order_id);
                if ($this->order->hasPostiProducts($order)) {
                    update_post_meta($order_id, '_posti_wh_order', '1');
                    $this->order->addOrder($order);
                }
            }
        }
    }

    public function hide_other_shipping_if_posti_products($rates) {
        global $woocommerce;
        $hide_other = false;
        $items = $woocommerce->cart->get_cart();

        foreach ($items as $item => $values) {
            $type = get_post_meta($values['data']->get_id(), '_posti_wh_stock_type', true);
            $product_warehouse = get_post_meta($values['data']->get_id(), '_posti_wh_warehouse', true);
            if (($type == "Posti" ) && $product_warehouse) { //|| $type == "Store"
                $hide_other = true;
                break;
            }
        }

        $posti_rates = array();
        if ($hide_other) {
            foreach ($rates as $rate_id => $rate) {
                if (stripos($rate_id, 'posti_shipping_method') !== false) {
                    $posti_rates[$rate_id] = $rate;
                }
            }
            return $posti_rates;
        }
        return $rates;
    }

    public function posti_tracking_column($columns) {
        $new_columns = array();
        foreach ($columns as $key => $name) {
            $new_columns[$key] = $name;
            if ('order_status' === $key) {
                $new_columns['posti_api_tracking'] = __('Posti API Tracking', 'posti-warehouse');
            }
        }
        return $new_columns;
    }

    public function posti_tracking_column_data($column_name) {
        if ($column_name == 'posti_api_tracking') {
            $tracking = get_post_meta(get_the_ID(), '_posti_api_tracking', true);
            echo $tracking ? $tracking : '–';
        }
    }

}
