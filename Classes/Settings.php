<?php

namespace PostiWarehouse\Classes;

use PostiWarehouse\Classes\Dataset;

defined('ABSPATH') || exit;

class Settings {
    
    private $api;
    private $logger;
    
    public function __construct(Api $api, Logger $logger) {
        $this->api = $api;
        $this->logger = $logger;
        register_setting('posti_wh', 'posti_wh_options');
        add_action('admin_init', array($this, 'posti_wh_settings_init'));
        add_action('admin_menu', array($this, 'posti_wh_options_page'));
        add_action('wp_ajax_warehouse_products_migrate', array($this, 'warehouse_products_migrate'));
    }

    public static function get() {
        $options = get_option('posti_wh_options');
        return $options ? $options : array();
    }
    
    public static function get_shipping_settings() {
        $options = get_option('woocommerce_posti_warehouse_settings');
        return $options ? $options : array();
    }
    
    public static function get_value(&$options, $key) {
        if (!isset($options) || !isset($options[$key])) {
            return null;
        }
        
        return $options[$key];
    }
    
    
    public static function update(&$options) {
        update_option('posti_wh_options', $options);
    }
    
    public static function install() {
        $old_options = get_option('woocommerce_posti_warehouse_settings');
        if (empty($old_options)) {
            return false;
        }
        
        $new_options = get_option('posti_wh_options');
        $fields = [
            'posti_wh_field_username',
            'posti_wh_field_password',
            'posti_wh_field_username_test',
            'posti_wh_field_password_test',
            'posti_wh_field_service',
            'posti_wh_field_business_id',
            'posti_wh_field_contract',
            'posti_wh_field_type',
            'posti_wh_field_autoorder',
            'posti_wh_field_autocomplete',
            'posti_wh_field_addtracking',
            'posti_wh_field_crontime',
            'posti_wh_field_test_mode',
            'posti_wh_field_debug',
            'posti_wh_field_stock_sync_dttm',
            'posti_wh_field_order_sync_dttm'
        ];

        foreach ($fields as $field) {
            if (isset($old_options[$field]) && !empty($old_options[$field])) {
                if (!isset($new_options[$field]) && isset($old_options[$field])) {
                    $new_options[$field] = $old_options[$field];
                }
            }
        }
        update_option('posti_wh_options', $new_options);

        return true;
    }
    
    public static function uninstall() {
    }
    
    public static function is_debug($options) {
        return Settings::is_option_true($options, 'posti_wh_field_debug');
    }
    
    public static function is_test($options) {
        return Settings::is_option_true($options, 'posti_wh_field_test_mode');
    }
    
    public static function is_test_mode() {
        return Settings::is_option_true(Settings::get(), 'posti_wh_field_test_mode');
    }
    
    public static function is_add_tracking($options) {
        return Settings::is_option_true($options, 'posti_wh_field_addtracking');
    }
    
    public static function is_changed(&$old_options, &$new_options, $option) {
        return Settings::get_value($old_options, $option) != Settings::get_value($new_options, $option);
    }
    
    public static function is_developer() {
        return (isset($_GET) && isset($_GET['developer']))
            || (isset($_POST) && isset($_POST['developer']));
    }
    
    public function posti_wh_settings_init() {

        add_settings_section(
                'posti_wh_options',
                '<span class="dashicons dashicons-admin-generic" style="padding-right: 2pt"></span>' . __('Posti Warehouse settings', 'posti-warehouse'),
                array($this, 'posti_wh_section_developers_cb'),
                'posti_wh'
        );

        add_settings_field(
                'posti_wh_field_username',
                __('Username', 'posti-warehouse'),
                array($this, 'posti_wh_field_string_cb'),
                'posti_wh',
                'posti_wh_options',
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
                'posti_wh_options',
                [
                    'label_for' => 'posti_wh_field_password',
                    'class' => 'posti_wh_row',
                    'posti_wh_custom_data' => 'custom',
                ]
        );

        add_settings_field(
                'posti_wh_field_username_test',
                __('TEST Username', 'posti-warehouse'),
                array($this, 'posti_wh_field_string_cb'),
                'posti_wh',
                'posti_wh_options',
                [
                    'label_for' => 'posti_wh_field_username_test',
                    'class' => 'posti_wh_row',
                    'posti_wh_custom_data' => 'custom',
                ]
        );

        add_settings_field(
                'posti_wh_field_password_test',
                __('TEST Password', 'posti-warehouse'),
                array($this, 'posti_wh_field_string_cb'),
                'posti_wh',
                'posti_wh_options',
                [
                    'label_for' => 'posti_wh_field_password_test',
                    'class' => 'posti_wh_row',
                    'posti_wh_custom_data' => 'custom',
                ]
        );
        
        if (Settings::is_developer()) {
            add_settings_field(
                'posti_wh_field_business_id',
                __('Business ID', 'posti-warehouse'),
                array($this, 'posti_wh_field_string_cb'),
                'posti_wh',
                'posti_wh_options',
                [
                    'label_for' => 'posti_wh_field_business_id',
                    'class' => 'posti_wh_row',
                    'posti_wh_custom_data' => 'custom',
                ]
            );
        }

        add_settings_field(
                'posti_wh_field_service',
                __('Delivery service', 'posti-warehouse'),
                array($this, 'posti_wh_field_service_cb'),
                'posti_wh',
                'posti_wh_options',
                [
                    'label_for' => 'posti_wh_field_service',
                    'class' => 'posti_wh_row',
                    'posti_wh_custom_data' => 'custom',
                ]
        );

        add_settings_field(
                'posti_wh_field_contract',
                __('Contract number', 'posti-warehouse'),
                array($this, 'posti_wh_field_string_cb'),
                'posti_wh',
                'posti_wh_options',
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
                'posti_wh_options',
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
                'posti_wh_options',
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
                'posti_wh_options',
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
                'posti_wh_options',
                [
                    'label_for' => 'posti_wh_field_addtracking',
                    'class' => 'posti_wh_row',
                    'posti_wh_custom_data' => 'custom',
                ]
        );

        add_settings_field(
                'posti_wh_field_crontime',
                __('Stock and order update interval (in seconds)', 'posti-warehouse'),
                array($this, 'posti_wh_field_string_cb'),
                'posti_wh',
                'posti_wh_options',
                [
                    'label_for' => 'posti_wh_field_crontime',
                    'class' => 'posti_wh_row',
                    'posti_wh_custom_data' => 'custom',
                    'input_type' => 'number',
                    'default' => '600'
                ]
        );

        add_settings_field(
                'posti_wh_field_test_mode',
                __('Test mode', 'posti-warehouse'),
                array($this, 'posti_wh_field_checkbox_cb'),
                'posti_wh',
                'posti_wh_options',
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
                'posti_wh_options',
                [
                    'label_for' => 'posti_wh_field_debug',
                    'class' => 'posti_wh_row',
                    'posti_wh_custom_data' => 'custom',
                ]
        );

        add_settings_field(
                'posti_wh_field_stock_sync_dttm',
                __('Datetime of last stock update', 'posti-warehouse'),
                array($this, 'posti_wh_field_string_cb'),
                'posti_wh',
                'posti_wh_options',
                [
                    'label_for' => 'posti_wh_field_stock_sync_dttm',
                    'class' => 'posti_wh_row',
                    'posti_wh_custom_data' => 'custom',
                ]
        );
        
        add_settings_field(
                'posti_wh_field_order_sync_dttm',
                __('Datetime of last order update', 'posti-warehouse'),
                array($this, 'posti_wh_field_string_cb'),
                'posti_wh',
                'posti_wh_options',
                [
                    'label_for' => 'posti_wh_field_order_sync_dttm',
                    'class' => 'posti_wh_row',
                    'posti_wh_custom_data' => 'custom',
                ]
        );
    }

    public function posti_wh_section_developers_cb($args) {
        
    }

    public function posti_wh_field_checkbox_cb($args) {
        $options = Settings::get();
        $checked = "";
        if (Settings::is_option_true($options, $args['label_for'])) {
            $checked = ' checked="checked" ';
        }
        ?>
        <input <?php echo $checked; ?> id = "<?php echo esc_attr($args['label_for']); ?>" name='posti_wh_options[<?php echo esc_attr($args['label_for']); ?>]' type='checkbox' value = "1"/>
        <?php
    }
    
    public function posti_wh_field_string_cb($args) {
        $options = Settings::get();
        $value = Settings::get_value($options, $args['label_for']);
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

        $options = Settings::get();
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

    public function posti_wh_field_service_cb($args) {

        $options = Settings::get();
        ?>
        <select id="<?php echo esc_attr($args['label_for']); ?>"
                data-custom="<?php echo esc_attr($args['posti_wh_custom_data']); ?>"
                name="posti_wh_options[<?php echo esc_attr($args['label_for']); ?>]"
                >
        <?php foreach (Dataset::getDeliveryTypes() as $val => $type): ?>
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
            <form action="options.php" method="post">
        <?php
        settings_fields('posti_wh');
        do_settings_sections('posti_wh');
        submit_button('Save');
        
        $business_id = Settings::get_business_id();
        if (isset($business_id) && !empty($business_id)) {
            $token = $this->api->getToken();
            if (!empty($token)) {
        ?>
        <input id="posti_migration_metabox_nonce" name="posti_migration_metabox_nonce"
            value="<?php echo wp_create_nonce(str_replace('wc_', '', 'posti-migration') . '-meta-box'); ?>"
            type="hidden" />
        <input id="posti_migration_url" name="posti_migration_url"
            value="<?php echo admin_url('admin-ajax.php'); ?>"
            type="hidden" />
        <div class="wrap">
        	<hr/>
        	<table>
        		<tr>
        			<td><span class="dashicons dashicons-info-outline" style="padding-right: 2pt"></span></td>
        			<td>
        				<div id="posti_wh_migration_required">
        					<b>Product data update is required!</b><br/>
        					Click Update button to sync product identifiers between Woocommerce and Posti.
        				</div>
        				<div id="posti_wh_migration_test_mode_notice" style="display: none">
        					<b style="color: red">Test mode must be disabled!</b>
        				</div>
        				<div id="posti_wh_migration_completed" style="display: none">
        					<b>Product data update is complete!</b>
        				</div>
        			</td>
        		</tr>
        	</table>
        	<hr/>
        	<div style="float: right; margin-top: 4pt">
        		<input id="posti_wh_migration_submit" name="posti_wh_migration_submit" class="button button-primary" type="button" value="Update"/>
        	</div>
    		<div style="clear: both"></div>
        </div>
        
            </form>
        </div>
        <?php
        }}
    }
    
    public function warehouse_products_migrate() {
        if (Settings::is_test_mode() && !Settings::is_developer()) {
            echo json_encode(array('testMode' => true));
            exit();
        }
        
        if ($this->api->migrate() === false) {
            $this->logger->log("error", 'Unable to migrate products');
            throw new \Exception('Unable to migrate products');
        }
        
        $business_id = Settings::get_business_id();
        $posts_query = array(
            'post_type' => ['product', 'product_variation'],
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_posti_id',
                    'compare' => 'EXISTS'
                )
            )
        );
        
        $posts = get_posts($posts_query);
        if (count($posts) > 0) {
            foreach ($posts as $post) {
                $product_id = get_post_meta($post->ID, '_posti_id', true);
                if (isset($product_id) && !empty($product_id)) {
                    if (substr_compare($product_id, $business_id, 0, strlen($business_id)) === 0) {
                        update_post_meta($post->ID, '_posti_id', substr($product_id, strlen($business_id) + 1));
                    }
                }
            }
        }
        
        $options = Settings::get();
        if (isset($options['posti_wh_field_business_id'])) {
            unset($options['posti_wh_field_business_id']);
            Settings::update($options);
        }
        
        $this->logger->log("info", "Products migrated");
        echo json_encode(array('result' => true));
        exit();
    }
    
    private static function is_option_true(&$options, $key) {
        return isset($options[$key]) && Settings::is_true($options[$key]);
    }
    
    private static function is_true($value) {
        if (!isset($value)) {
            return false;
        }
        
        return $value === 1
            || $value === '1'
            || $value === 'yes'
            || $value === 'true';
    }
    
    private static function get_business_id() {
        $options = Settings::get();
        return isset($options['posti_wh_field_business_id']) ? $options['posti_wh_field_business_id'] : null;
    }
}
