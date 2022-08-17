<?php

namespace PostiWarehouse\Classes;

defined('ABSPATH') || exit;

use PostiWarehouse\Classes\Api;
use PostiWarehouse\Classes\Order;
use PostiWarehouse\Classes\Product;
use PostiWarehouse\Classes\Metabox;
use PostiWarehouse\Classes\Logger;
use PostiWarehouse\Classes\Dataset;
use PostiWarehouse\Classes\Frontend;

class Core {

    private $api = null;
    private $test_api = null;
    private $metabox = null;
    private $order = null;
    private $product = null;
    private $business_id = false;
    private $is_test = false;
    private $debug = false;
    private $add_tracking = false;
    private $cron_time = 7200;
    private $logger;
    private $options_checked = false;
    private $frontend = null;
    public $prefix = 'warehouse';
    public $templates_dir;
    public $templates;

    public function __construct() {
        
        $this->templates_dir = plugin_dir_path(__POSTI_WH_FILE__) . 'templates/';
        $this->templates = array(
          'checkout_pickup' => 'checkout-pickup.php',
          'account_order' => 'myaccount-order.php',
        );
      

        $this->load_options();

                
        //add_action('admin_init', array($this, 'posti_wh_settings_init'));

        //add_action('admin_menu', array($this, 'posti_wh_options_page'));

        add_action('admin_enqueue_scripts', array($this, 'posti_wh_admin_styles'));

        $this->WC_hooks();

        register_activation_hook(__POSTI_WH_FILE__, array($this, 'install'));
        register_deactivation_hook(__POSTI_WH_FILE__, array($this, 'uninstall'));

        //after update options check login info
        add_action('updated_option', array($this, 'after_settings_update'), 10, 3);
        add_action('admin_notices', array($this, 'render_messages'));
    }
    
    private function load_options(){
        $options = get_option('woocommerce_posti_warehouse_settings');
        
        if (isset($options['posti_wh_field_test_mode']) && $options['posti_wh_field_test_mode'] == "yes") {
            $this->is_test = true;
        }

        if (isset($options['posti_wh_field_debug']) && $options['posti_wh_field_debug'] == "yes") {
            $this->debug = true;
        }

        if (isset($options['posti_wh_field_addtracking']) && $options['posti_wh_field_addtracking'] == "yes") {
            $this->add_tracking = true;
        }

        if (isset($options['posti_wh_field_crontime']) && $options['posti_wh_field_crontime']) {
            $this->cron_time = (int) $options['posti_wh_field_crontime'];
        }

        if (isset($options['posti_wh_field_business_id'])) {
            $this->business_id = $options['posti_wh_field_business_id'];
        }
        
        $this->logger = new Logger();
        $this->logger->setDebug($this->debug);

        $this->api = new Api($this->logger, $this->business_id, false);
        $this->test_api = new Api($this->logger, $this->business_id, true);
        
        $this->order = new Order($this->is_test ? $this->test_api : $this->api, $this->logger, $this->add_tracking);
        $this->product = new Product($this->is_test ? $this->test_api : $this->api, $this->logger);
        $this->metabox = new Metabox($this->order);

        if ($this->debug) {
            $debug = new Debug();
            $debug->setTest($this->is_test);
        }
        
        $this->frontend = new Frontend($this);
        $this->frontend->load();
    }
    
    public function getApi() {
        return $this->api;
    }

    public function install() {
        Logger::install();
    }

    public function uninstall() {
        Logger::uninstall();
    }

    public function load_textdomain() {
        load_plugin_textdomain(
                'posti-warehouse',
                false,
                dirname(__FILE__) . '/languages/'
        );
    }

    public function after_settings_update($option, $old_value, $value) { 
        if ($option == 'woocommerce_posti_warehouse_settings') {
            if (
                    $old_value['posti_wh_field_username'] != $value['posti_wh_field_username'] || 
                    $old_value['posti_wh_field_password'] != $value['posti_wh_field_password']
            ) {
                //login info changed, try to get token
                delete_option('posti_wh_api_auth');
                if (session_id() === '' || !isset($_SESSION)) {
                    session_start();
                }
                $_SESSION['posti_warehouse_check_token'] = true;
            }
            if (
                    $old_value['posti_wh_field_username_test'] != $value['posti_wh_field_username_test'] || 
                    $old_value['posti_wh_field_password_test'] != $value['posti_wh_field_password_test']
            ) {
                //login info changed, try to get token
                delete_option('posti_wh_api_auth_test');
                if (session_id() === '' || !isset($_SESSION)) {
                    session_start();
                }
                $_SESSION['posti_warehouse_check_test_token'] = true;
            }
        }
    }

    public function render_messages() {
        
        if (session_id() === '' || !isset($_SESSION)) {
            session_start();
        }
        
        if (isset($_SESSION['posti_warehouse_check_token'])) {
            //reload options, because their are saved after load
            $this->load_options();
            $token = $this->api->getToken();
            if ($token) {
                $this->token_success();
            } else {
                $this->token_error();
            }
            unset($_SESSION['posti_warehouse_check_token']);
        }
        
        if (isset($_SESSION['posti_warehouse_check_test_token'])) {
            //reload options, because their are saved after load
            $this->load_options();
            $token = $this->test_api->getToken();
            if ($token) {
                $this->token_success(true);
            } else {
                $this->token_error(true);
            }
            unset($_SESSION['posti_warehouse_check_test_token']);
        }
    }

    public function token_error($test = false) {
        ?>
        <div class="error notice">
            <p><?php echo $test?'TEST ':'';?><?php _e('Wrong credentials - access token not received!', 'posti-warehouse'); ?></p>
        </div>
        <?php
    }

    public function token_success($test = false) {
        ?>
        <div class="updated notice">
            <p><?php echo $test?'TEST ':'';?><?php _e('Credentials matched - access token received!', 'posti-warehouse'); ?></p>
        </div>
        <?php
    }

    public function posti_wh_admin_styles($hook) {
        
        wp_enqueue_style( 'select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0');
        wp_enqueue_script( 'select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', 'jquery', '4.1.0-rc.0');
    
        wp_enqueue_style('posti_wh_admin_style', plugins_url('assets/css/admin-warehouse-settings.css', dirname(__FILE__)), [], '1.0');
        wp_enqueue_script('posti_wh_admin_script', plugins_url('assets/js/admin-warehouse.js', dirname(__FILE__)), 'jquery', '1.2');
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
                'posti_wh_field_addtracking',
                __('Add tracking to email', 'posti-warehouse'),
                array($this, 'posti_wh_field_checkbox_cb'),
                'posti_wh',
                'posti_wh_settings_section',
                [
                    'label_for' => 'posti_wh_field_addtracking',
                    'class' => 'posti_wh_row',
                    'posti_wh_custom_data' => 'custom',
                ]
        );

        add_settings_field(
                'posti_wh_field_crontime',
                __('Delay between stock and order checks in seconds', 'posti-warehouse'),
                array($this, 'posti_wh_field_string_cb'),
                'posti_wh',
                'posti_wh_settings_section',
                [
                    'label_for' => 'posti_wh_field_crontime',
                    'class' => 'posti_wh_row',
                    'posti_wh_custom_data' => 'custom',
                    'input_type' => 'number',
                    'default' => '7200'
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
        $options = get_option('woocommerce_posti_warehouse_settings');
        $checked = "";
        if ($options[$args['label_for']]) {
            $checked = ' checked="checked" ';
        }
        ?>
        <input <?php echo $checked; ?> id = "<?php echo esc_attr($args['label_for']); ?>" name='posti_wh_options[<?php echo esc_attr($args['label_for']); ?>]' type='checkbox' value = "1"/>
        <?php
    }

    public function posti_wh_field_string_cb($args) {
        $options = get_option('woocommerce_posti_warehouse_settings');
        $value = $options[$args['label_for']];
        $type = 'text';
        if (isset($args['input_type'])) {
            $type = $args['input_type'];
        }
        if (!$value && isset($args['default'])) {
            $value = $args['default'];
        }
        ?>
        <input id="<?php echo esc_attr($args['label_for']); ?>" name="posti_wh_options[<?php echo esc_attr($args['label_for']); ?>]" size='20' type='<?= $type; ?>' value="<?php echo $value; ?>" />
        <?php
    }

    public function posti_wh_field_type_cb($args) {

        $options = get_option('woocommerce_posti_warehouse_settings');
        ?>
        <select id="<?php echo esc_attr($args['label_for']); ?>"
                data-custom="<?php echo esc_attr($args['posti_wh_custom_data']); ?>"
                name="posti_wh_options[<?php echo esc_attr($args['label_for']); ?>]"
                >
        <?php foreach (Dataset::getSToreTypes() as $val => $type): ?>
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

        //create cronjob to sync products and get order status
        add_filter('cron_schedules', array($this, 'posti_interval'));

        add_action('posti_cronjob', array($this, 'posti_cronjob_callback'));
        if (!wp_next_scheduled('posti_cronjob')) {
            wp_schedule_event(time(), 'posti_wh_time', 'posti_cronjob');
        }

        //filter shipping methods, if product is in Posti store, allow only posti shipping methods
        add_filter('woocommerce_package_rates', array($this, 'hide_other_shipping_if_posti_products'), 100, 1);
    }

    public function posti_interval($schedules) {
        $schedules['posti_wh_time'] = array(
            'interval' => $this->cron_time,
            'display' => esc_html__('Every ' . $this->cron_time . ' seconds'));
        return $schedules;
    }

    /*
     * Cronjob to sync products and orders
     */

    public function posti_cronjob_callback() {
        $args = array(
            'post_type' => ['product', 'product_variation'],
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_posti_wh_stock_type',
                    'value' => array('Store', 'Posti', 'Catalog'),
                    'compare' => 'IN'
                ),
                array(
                    'key' => '_posti_last_sync',
                    'value' => (time() - 60),
                    'compare' => '<'
                ),
            ),
        );
        $products = get_posts($args);
        $this->logger->log("info", "Found  " . count($products) . " products to sync");
        if (is_array($products)) {
            $product_ids = [];
            foreach ($products as $product) {
                $product_ids[] = $product->ID;
            }
            if (count($product_ids)) {
                $this->product->syncProducts($product_ids);
            }
        }

        $this->order->updatePostiOrders();
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
            //to do: how to check posti methods
            //return $posti_rates;
        }
        return $rates;
    }

}
