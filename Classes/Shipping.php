<?php

namespace PostiWarehouse\Classes;

// Prevent direct access to the script
use WC_Countries;
use PostiWarehouse\Classes\Api;
use PostiWarehouse\Classes\Logger;
use PostiWarehouse\Classes\Dataset;

if (!defined('ABSPATH')) {
    exit;
}

function warehouse_shipping_method() {
    if (!class_exists('WarehouseShipping')) {

        class WarehouseShipping extends \WC_Shipping_Method {

            /**
             * Required to access Pakettikauppa client
             * @var Shipment $shipment
             */
            private $shipment = null;
            public $is_loaded = false;
            private $is_test = false;
            private $debug = false;
            private $api;
            private $client;
            private $business_id = false;
            private $logger;

            /**
             * Constructor for Pakettikauppa shipping class
             *
             * @access public
             * @return void
             */
            public function __construct() {
                $options = get_option('woocommerce_posti_warehouse_settings');
                if (isset($options['posti_wh_field_test_mode']) && $options['posti_wh_field_test_mode'] == "yes") {
                    $this->is_test = true;
                }

                if (isset($options['posti_wh_field_debug']) && $options['posti_wh_field_debug'] == "yes") {
                    $this->debug = true;
                }

                if (isset($options['posti_wh_field_business_id'])) {
                    $this->business_id = $options['posti_wh_field_business_id'];
                }
                $this->logger = new Logger();
                $this->logger->setDebug($this->debug);

                $this->api = new Api($this->logger, $this->business_id, $this->is_test);
                $this->client = $this->api->getClient();

                $this->load();
            }

            public function load() {

                $this->id = 'posti_warehouse'; // ID for your shipping method. Should be unique.
                $this->method_title = 'Posti warehouse';
                $this->method_description = 'Posti warehouse'; // Description shown in admin
                $this->enabled = "yes";
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
                $this->copy_old_settings();
                $this->form_fields = $this->my_global_form_fields();
                $this->title = "Warehouse shipping";
                $this->init_settings();
            }
            
            /**
             * Copy setting from old settings page
             */
            private function copy_old_settings(){
                $bool_fields = array_flip([
                    'posti_wh_field_autoorder', 
                    'posti_wh_field_autocomplete', 
                    'posti_wh_field_addtracking', 
                    'posti_wh_field_test_mode', 
                    'posti_wh_field_debug'
                    ]);
                $old_options = get_option('posti_wh_options');
                if (!empty($old_options)){
                    $new_options = get_option('woocommerce_posti_warehouse_settings');
                    foreach ($old_options as $key=>$value){
                        $new_options[$key] = (isset($bool_fields[$key])?($value=='1'?'yes':'no'):$value); 
                    }
                    update_option('woocommerce_posti_warehouse_settings', $new_options);
                    delete_option('posti_wh_options');
                }
            }

            public function validate_pickuppoints_field($key, $value) {
                $values = wp_json_encode($value);
                return $values;
            }

            public function generate_pickuppoints_html($key, $value) {
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

                if (empty($all_shipping_methods)) {
                    $all_shipping_methods = array();
                }

                $methods = $this->get_pickup_point_methods();

                ob_start();
                ?>
                <script>
                    function pkChangeOptions(elem, methodId) {

                        var strUser = elem.options[elem.selectedIndex].value;
                        var elements = document.getElementsByClassName('pk-services-' + methodId);

                        var servicesElement = document.getElementById('services-' + methodId + '-' + strUser);
                        var pickuppointsElement = document.getElementById('pickuppoints-' + methodId);
                        var servicePickuppointsElement = document.getElementById('service-' + methodId + '-' + strUser + '-pickuppoints');

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
                        }
                    }
                </script>
                <tr>
                    <th colspan="2" class="titledesc mode_react" scope="row"><?php echo esc_html($value['title']); ?></th>
                </tr>
                <tr>
                    <td colspan="2" class="mode_react">
                        <?php foreach (\WC_Shipping_Zones::get_zones('admin') as $zone_raw) : ?>
                            <hr>
                            <?php $zone = new \WC_Shipping_Zone($zone_raw['zone_id']); ?>
                            <h3>
                                <?php esc_html_e('Zone name', 'woocommerce'); ?>: <?php echo $zone->get_zone_name(); ?>
                            </h3>
                            <p>
                                <?php esc_html_e('Zone regions', 'woocommerce'); ?>: <?php echo $zone->get_formatted_location(); ?>
                            </p>
                            <h4><?php esc_html_e('Shipping method(s)', 'woocommerce'); ?></h4>
                            <?php foreach ($zone->get_shipping_methods() as $method_id => $shipping_method) : ?>
                                <?php if ($shipping_method->enabled === 'yes' && $shipping_method->id !== "posti_warehouse" && $shipping_method->id !== 'local_pickup') : ?>
                                    <?php
                                    $selected_service = null;
                                    if (!empty($values[$method_id]['service'])) {
                                        $selected_service = $values[$method_id]['service'];
                                    }
                                    if (empty($selected_service) && !empty($methods)) {
                                        $selected_service = '__PICKUPPOINTS__';
                                    }
                                    ?>
                                    <table style="border-collapse: collapse;" border="0">
                                        <th><?php echo $shipping_method->title; ?></th>
                                        <td style="vertical-align: top;">
                                            <select id="<?php echo $method_id; ?>-select" name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][service]'; ?>" onchange="pkChangeOptions(this, '<?php echo $method_id; ?>');">
                                                <option value="__NULL__"><?php echo "No shipping"; ?></option>  //Issue: #171, was no echo
                                                <?php if (!empty($methods)) : ?>
                                                    <option value="__PICKUPPOINTS__" <?php echo ($selected_service === '__PICKUPPOINTS__' ? 'selected' : ''); ?>>Noutopisteet</option>
                                                <?php endif; ?>
                                                <?php foreach ($all_shipping_methods as $service_id => $service_name) : ?>
                                                    <?php $has_pp = ($this->service_has_pickup_points($service_id)) ? true : false; ?>
                                                    <option value="<?php echo $service_id; ?>" <?php echo (strval($selected_service) === strval($service_id) ? 'selected' : ''); ?> data-haspp="<?php echo ($has_pp) ? 'true' : 'false'; ?>">
                                                        <?php echo $service_name; ?>
                                                        <?php if ($has_pp) : ?>
                                                            (Has pickup points)
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td style="vertical-align: top;">
                                            <div style='display: none;' id="pickuppoints-<?php echo $method_id; ?>">
                                                <?php foreach ($methods as $method_code => $method_name) : ?>
                                                    <input type="hidden"
                                                           name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . $method_code . '][active]'; ?>"
                                                           value="no">
                                                    <p>
                                                        <label>
                                                            <input type="checkbox"
                                                                   name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . $method_code . '][active]'; ?>"
                                                                   value="yes" <?php echo (!empty($values[$method_id][$method_code]['active']) && $values[$method_id][$method_code]['active'] === 'yes') ? 'checked' : ''; ?>>
                                                                   <?php echo $method_name; ?>
                                                        </label>
                                                    </p>
                                                <?php endforeach; ?>
                                            </div>

                                            <?php
                                            $all_additional_services = $this->get_additional_services();
                                            if (empty($all_additional_services)) {
                                                $all_additional_services = array();
                                            }
                                            ?>
                                            <?php foreach ($all_additional_services as $method_code => $additional_services) : ?>
                                                <div class="pk-services-<?php echo $method_id; ?>" style='display: none;' id="services-<?php echo $method_id; ?>-<?php echo $method_code; ?>">
                                                    <?php foreach ($additional_services as $additional_service) : ?>
                                                        <?php if (empty($additional_service->specifiers) || in_array($additional_service->service_code, array('3102'), true)) : ?>
                                                            <input type="hidden"
                                                                   name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($method_code) . '][additional_services][' . $additional_service->service_code . ']'; ?>"
                                                                   value="no">
                                                            <p>
                                                                <label>
                                                                    <input type="checkbox"
                                                                           name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($method_code) . '][additional_services][' . $additional_service->service_code . ']'; ?>"
                                                                           value="yes" <?php echo (!empty($values[$method_id][$method_code]['additional_services'][$additional_service->service_code]) && $values[$method_id][$method_code]['additional_services'][$additional_service->service_code] === 'yes') ? 'checked' : ''; ?>>
                                                                           <?php echo $additional_service->name; ?>
                                                                </label>
                                                            </p>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php foreach ($all_shipping_methods as $service_id => $service_name) : ?>
                                                <?php if ($this->service_has_pickup_points($service_id)) : ?>
                                                    <div id="service-<?php echo $method_id; ?>-<?php echo $service_id; ?>-pickuppoints" class="pk-services-<?php echo $method_id; ?>" style="display: none;">
                                                        <input type="hidden"
                                                               name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($service_id) . '][pickuppoints]'; ?>" value="no">
                                                        <p>
                                                            <label>
                                                                <input type="checkbox"
                                                                       name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($service_id) . '][pickuppoints]'; ?>"
                                                                       value="yes" <?php echo ((!empty($values[$method_id][$service_id]['pickuppoints']) && $values[$method_id][$service_id]['pickuppoints'] === 'yes') || empty($values[$method_id][$service_id]['pickuppoints'])) ? 'checked' : ''; ?>>
                                                                       <?php echo __('Pickup points', 'woo-pakettikauppa'); ?>
                                                            </label>
                                                        </p>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </td>
                                    </table>
                                    <script>pkChangeOptions(document.getElementById("<?php echo $method_id; ?>-select"), '<?php echo $method_id; ?>');</script>
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

            private function my_global_form_fields() {

                return array(
                    'account_number' => array(
                        'title' => "Username",
                        'description' => "API username for shipping methods",
                        'type' => 'text',
                        'default' => '',
                        'desc_tip' => true,
                    ),
                    'secret_key' => array(
                        'title' => "Password",
                        'description' => "API password for shipping methods",
                        'type' => 'text',
                        'default' => '',
                        'desc_tip' => true,
                    ),
                    'posti_wh_field_username' => array(
                        'title' => "Username for glue",
                        'description' => "API username for glue",
                        'type' => 'text',
                        'default' => '',
                        'desc_tip' => true,
                    ),
                    'posti_wh_field_password' => array(
                        'title' => "Password for glue",
                        'description' => "API password for glue",
                        'type' => 'text',
                        'default' => '',
                        'desc_tip' => true,
                    ),
                    'posti_wh_field_business_id' => array(
                        'title' => __('Business ID', 'posti-warehouse'),
                        'desc' => "",
                        'type' => 'text',
                        'default' => '',
                        'desc_tip' => false,
                    ),
                    'posti_wh_field_contract' => array(
                        'title' => __('Contract number', 'posti-warehouse'),
                        'desc' => "",
                        'type' => 'text',
                        'default' => '',
                        'desc_tip' => false,
                    ),
                    'posti_wh_field_type' => array(
                        'title' => __('Default stock type', 'posti-warehouse'),
                        'desc' => "",
                        'type' => 'select',
                        'default' => '',
                        'desc_tip' => false,
                        'options' => Dataset::getSToreTypes()
                    ),
                    'posti_wh_field_autoorder' => array(
                        'title' => __('Auto ordering', 'posti-warehouse'),
                        'desc' => "",
                        'type' => 'checkbox',
                        'default' => '',
                        'desc_tip' => false,
                    ),
                    'posti_wh_field_autocomplete' => array(
                        'title' => __('Auto mark orders as "Completed"', 'posti-warehouse'),
                        'desc' => "",
                        'type' => 'checkbox',
                        'default' => '',
                        'desc_tip' => false,
                    ),
                    'posti_wh_field_addtracking' => array(
                        'title' => __('Add tracking to email', 'posti-warehouse'),
                        'desc' => "",
                        'type' => 'checkbox',
                        'default' => '',
                        'desc_tip' => false,
                    ),
                    'posti_wh_field_crontime' => array(
                        'title' => __('Delay between stock and order checks in seconds', 'posti-warehouse'),
                        'desc' => "",
                        'type' => 'number',
                        'default' => '7200',
                        'desc_tip' => false,
                    ),
                    'posti_wh_field_test_mode' => array(
                        'title' => __('Test mode', 'posti-warehouse'),
                        'desc' => "",
                        'type' => 'checkbox',
                        'default' => '',
                        'desc_tip' => false,
                    ),
                    'posti_wh_field_debug' => array(
                        'title' => __('Debug', 'posti-warehouse'),
                        'desc' => "",
                        'type' => 'checkbox',
                        'default' => '',
                        'desc_tip' => false,
                    ),
                    
                    'pickup_points' => array(
                        'title' => "Pickup",
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

                $all_shipping_methods = $this->get_shipping_methods();

                // List all available methods as shipping options on checkout page
                if ($all_shipping_methods === null) {
                    // returning null seems to invalidate services cache
                    return null;
                }

                foreach ($all_shipping_methods as $shipping_method) {
                    $services[strval($shipping_method->shipping_method_code)] = sprintf('%1$s: %2$s', $shipping_method->service_provider, $shipping_method->name);
                }

                ksort($services);

                return $services;
            }

            private function get_pickup_point_methods() {
                $methods = array(
                    '2103' => 'Posti',
                    '90080' => 'Matkahuolto',
                    '80010' => 'DB Schenker',
                    '2711' => 'Posti International',
                );

                return $methods;
            }

            public function get_additional_services() {
                $all_shipping_methods = $this->get_shipping_methods();

                if ($all_shipping_methods === null) {
                    return null;
                }

                $additional_services = array();
                foreach ($all_shipping_methods as $shipping_method) {
                    $additional_services[strval($shipping_method->shipping_method_code)] = $shipping_method->additional_services;
                }

                return $additional_services;
            }

            private function get_shipping_methods() {
                $transient_name = 'posti_warehouse_shipping_methods';
                $transient_time = 86400; // 24 hours

                $all_shipping_methods = get_transient($transient_name);

                if (empty($all_shipping_methods)) {
                    try {
                        $all_shipping_methods = $this->client->listShippingMethods();
                    } catch (\Exception $ex) {
                        $all_shipping_methods = null;
                    }

                    if (!empty($all_shipping_methods)) {
                        set_transient($transient_name, $all_shipping_methods, $transient_time);
                    }
                }

                if (empty($all_shipping_methods)) {
                    return null;
                }

                return $all_shipping_methods;
            }

            private function service_has_pickup_points($service_id) {
                $all_shipping_methods = $this->get_shipping_methods();

                if ($all_shipping_methods === null) {
                    return false;
                }

                foreach ($all_shipping_methods as $shipping_method) {
                    if (strval($shipping_method->shipping_method_code) === strval($service_id)) {
                        return $shipping_method->has_pickup_points;
                    }
                }

                return false;
            }

        }

    }
}

add_action('woocommerce_shipping_init', '\PostiWarehouse\Classes\warehouse_shipping_method');

function add_cloudways_shipping_method($methods) {
    $methods[] = '\PostiWarehouse\Classes\WarehouseShipping';
    return $methods;
}

add_filter('woocommerce_shipping_methods', '\PostiWarehouse\Classes\add_cloudways_shipping_method');
