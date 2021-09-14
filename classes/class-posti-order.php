<?php
defined('ABSPATH') || exit;


class PostiOrder {

    private $orderStatus = false;

    public function __construct(PostiWarehouseApi $api) {
        $this->api = $api;
    }

    public function getOrderStatus($order_id) {
        $order_data = $this->getOrder($order_id);
        if (!$order_data) {
            return "Order not placed";
        }
        $this->orderStatus = $order_data['status']['value'];
        return $order_data['status']['value'];
    }

    public function getOrderActionButton() {
        if (!$this->orderStatus) {
            ?>
            <button type = "button" class="button button-posti" id = "posti-order-btn" name="posti_order_action"  onclick="posti_order_change(this);" value="place_order"><?php _e('Place Order', 'posti-warehouse'); ?></button>
            <?php
        } elseif ($this->orderStatus != "Delivered") {
            /*
              ?>
              <button type = "button" class="button button-posti" id = "posti-order-btn" name="posti_order_action"  onclick="posti_order_change(this);" value="complete"><?php _e('Complete Order', 'posti-warehouse');?></button>
              <?php
             */
        }
    }

    public function hasPostiProducts($order) {
        if (!is_object($order)) {
            $order = wc_get_order($order);
        }
        if (!$order) {
            return false;
        }
        $items = $order->get_items();
        foreach ($items as $item_id => $item) {
            $type = get_post_meta($item['product_id'], '_posti_wh_stock_type', true);
            $product_warehouse = get_post_meta($item['product_id'], '_posti_wh_warehouse', true);
            if (($type == "Posti" || $type == "Store") && $product_warehouse) {
                return true;
            }
        }

        return false;
    }

    public function getOrder($order_id) {
        return $this->api->getOrder($this->api->getBusinessId() . '-' . $order_id);
    }

    public function addOrder($order) {
        if (!is_object($order)) {
            $order = wc_get_order($order);
        }
        return $this->api->addOrder($this->prepare_posti_order($order));
    }

    public function updatePostiOrders() {
        //$options = get_option('posti_wh_options');
        $options = get_option('woocommerce_posti_shipping_method_settings');
        $args = array(
            'post_type' => 'shop_order',
            'post_status' => 'wc-processing',
            'meta_query' => array(
                array(
                    'key' => '_posti_wh_order',
                    'value' => '1',
                ),
            ),
        );
        $orders = get_posts($args);
        if (is_array($orders)) {
            foreach ($orders as $order) {
                $order_data = $this->getOrder($order->ID);
                if (!$order_data) {
                    continue;
                }
                if (!isset($options['posti_wh_field_autocomplete'])) {
                    continue;
                }
                $tracking = $order_data['trackingCodes'];
                if ($tracking) {
                    if (is_array($tracking)) {
                        $tracking = implode(', ', $tracking);
                    }
                    update_post_meta($order->ID, '_posti_api_tracking', $tracking);
                }

                $status = $order_data['status']['value'];
                if ($status == 'Delivered') {
                    $_order = wc_get_order($order->ID);
                    if ($_order) {
                        $_order->update_status('completed', '', true);
                    }
                }
            }
        }
    }

    private function get_additional_services($order) {
        $additional_services = array();

        $settings = get_option('woocommerce_posti_shipping_method_settings');

        $shipping_methods = $order->get_shipping_methods();

        $chosen_shipping_method = array_pop($shipping_methods);

        $add_cod_to_additional_services = 'cod' === $order->get_payment_method();
        
        if (!empty($chosen_shipping_method)) {
            $method_id = $chosen_shipping_method->get_method_id();

            if ($method_id === 'local_pickup') {
                return $additional_services;
            }

            $instance_id = $chosen_shipping_method->get_instance_id();
            
            $pickup_points = json_decode($settings['pickup_points'], true);
            //var_dump($pickup_points);
            if (!empty($pickup_points[$instance_id]['service'])) {
                $service_id = $pickup_points[$instance_id]['service'];

                $services = array();

                if (!empty($pickup_points[$instance_id][$service_id]) && isset($pickup_points[$instance_id][$service_id]['additional_services'])) {
                    $services = $pickup_points[$instance_id][$service_id]['additional_services'];
                }

                if (!empty($services)) {
                    foreach ($services as $service_code => $service) {
                        if ($service === 'yes' && $service_code !== '3101') {
                            $additional_services[] = array($service_code => null);
                        } elseif ($service === 'yes' && $service_code === '3101') {
                            $add_cod_to_additional_services = true;
                        }
                    }
                }
            }
        }

        if ($add_cod_to_additional_services) {
            $additional_services[] = array(
                '3101' => array(
                    'amount' => $order->get_total(),
                    'account' => $settings['cod_iban'],
                    'codbic' => $settings['cod_bic'],
                    'reference' => $this->calculate_reference($order->get_id()),
                ),
            );
        }

        return $additional_services;
    }
    
    public static function calculate_reference( $id ) {
      $weights = array( 7, 3, 1 );
      $sum     = 0;

      $base                 = str_split(strval(($id)));
      $reversed_base        = array_reverse($base);
      $reversed_base_length = count($reversed_base);

      for ( $i = 0; $i < $reversed_base_length; $i ++ ) {
        $sum += $reversed_base[ $i ] * $weights[ $i % 3 ];
      }

      $checksum = (10 - $sum % 10) % 10;

      $reference = implode('', $base) . $checksum;

      return $reference;
    }

    private function prepare_posti_order($_order) {
        
        $additional_services = $this->get_additional_services($_order);
        //var_dump($additional_services); exit;
        $additional_services = [
            [
                "serviceCode" => "3174"
            ]
        ];

        $business_id = $this->api->getBusinessId();
        $order_items = array();
        $total_price = 0;
        $total_tax = 0;
        $items = $_order->get_items();
        $item_counter = 1;
        $service_code = "2103";
        $routing_service_code = "";
        $pickup_point = get_post_meta($_order->get_id(), '_woo_posti_shipping_pickup_point_id', true);
        if ($pickup_point) {
            $routing_service_code = "3201";
        }
        //shipping service code 
        foreach ($_order->get_items('shipping') as $item_id => $shipping_item_obj) {
            $item_service_code = $shipping_item_obj->get_meta('service_code');
            if ($item_service_code) {
                $service_code = $item_service_code;
            }
        }
        foreach ($items as $item_id => $item) {
            $type = get_post_meta($item['product_id'], '_posti_wh_stock_type', true);
            $product_warehouse = get_post_meta($item['product_id'], '_posti_wh_warehouse', true);
            if (($type == "Posti" || $type == "Store") && $product_warehouse) {


                $total_price += $item->get_total();
                $total_tax += $item->get_subtotal_tax();
                $_product = wc_get_product($item['product_id']);
                $ean = get_post_meta($item['product_id'], '_ean', true);
                $order_items[] = [
                    "externalId" => (string) $item_counter,
                    "externalProductId" => $business_id . '-' . $_product->get_sku(),
                    "productEANCode" => $ean, //$_product->get_sku(),
                    "productUnitOfMeasure" => "KPL",
                    "productDescription" => $item['name'],
                    "externalWarehouseId" => $product_warehouse,
                    //"weight" => 0,
                    //"volume" => 0,
                    "quantity" => $item['qty'],
                        //"deliveredQuantity" => 0,
                        /*
                          "comments" => [
                          [
                          "name" => "string",
                          "value" => "string",
                          "type" => "string"
                          ]
                          ]
                         */
                ];
                $item_counter++;
            }
        }

        $order = array(
            "externalId" => $business_id . "-" . $_order->get_id(),
            "clientId" => (string) $business_id,
            "orderDate" => date('Y-m-d\TH:i:s.vP', strtotime($_order->get_date_created()->__toString())),
            "metadata" => [
                "documentType" => "SalesOrder"
            ],
            "vendor" => [
                //"externalId" => "string",
                "name" => get_option("blogname"),
                "streetAddress" => get_option('woocommerce_store_address'),
                "postalCode" => get_option('woocommerce_store_postcode'),
                "postOffice" => get_option('woocommerce_store_city'),
                "country" => get_option('woocommerce_default_country'),
                //"telephone" => "string",
                "email" => get_option("admin_email")
            ],
            "sender" => [
                //"externalId" => "string",
                "name" => get_option("blogname"),
                "streetAddress" => get_option('woocommerce_store_address'),
                "postalCode" => get_option('woocommerce_store_postcode'),
                "postOffice" => get_option('woocommerce_store_city'),
                "country" => get_option('woocommerce_default_country'),
                //"telephone" => "string",
                "email" => get_option("admin_email")
            ],
            "client" => [
                "externalId" => $business_id . "-" . $_order->get_customer_id(),
                "name" => $_order->get_billing_first_name() . ' ' . $_order->get_billing_last_name(),
                "streetAddress" => $_order->get_billing_address_1(),
                "postalCode" => $_order->get_billing_postcode(),
                "postOffice" => $_order->get_billing_city(),
                "country" => $_order->get_billing_country(),
                "telephone" => $_order->get_billing_phone(),
                "email" => $_order->get_billing_email()
            ],
            "recipient" => [
                "externalId" => $business_id . "-" . $_order->get_customer_id(),
                "name" => $_order->get_billing_first_name() . ' ' . $_order->get_billing_last_name(),
                "streetAddress" => $_order->get_billing_address_1(),
                "postalCode" => $_order->get_billing_postcode(),
                "postOffice" => $_order->get_billing_city(),
                "country" => $_order->get_billing_country(),
                "telephone" => $_order->get_billing_phone(),
                "email" => $_order->get_billing_email()
            ],
            "deliveryAddress" => [
                "externalId" => $business_id . "-" . $_order->get_customer_id(),
                "name" => $_order->get_shipping_first_name() . ' ' . $_order->get_shipping_last_name(),
                "streetAddress" => $_order->get_shipping_address_1(),
                "postalCode" => $_order->get_shipping_postcode(),
                "postOffice" => $_order->get_shipping_city(),
                "country" => $_order->get_shipping_country(),
                "telephone" => $_order->get_billing_phone(),
                "email" => $_order->get_billing_email()
            ],
            "currency" => $_order->get_currency(),
            /*
              "additionalServices" => [
              [
              "serviceCode" => "string",
              "telephone" => "string",
              "email" => "string",
              "attributes" => [
              [
              "name" => "string",
              "value" => "string"
              ]
              ]
              ]
              ], */
            "serviceCode" => $service_code,
            "routingServiceCode" => $routing_service_code,
            "totalPrice" => $total_price,
            "totalTax" => $total_tax,
            //"totalWeight" => 0,
            "totalWholeSalePrice" => $total_price + $total_tax,
            "deliveryOperator" => "Posti",
            /*
              "trackingCodes" => [
              "string"
              ],
             */
            /*
              "comments" => [
              [
              "name" => "string",
              "value" => "string",
              "type" => "string"
              ]
              ],
             * */
            /*
              "status" => [
              "value" => "string",
              "timestamp" => "string"
              ],
             */
            "rows" => $order_items
        );

        if ($pickup_point) {
            $address = $this->pickupPointData($pickup_point, $_order, $business_id);
            if ($address) {
                $order['deliveryAddress'] = $address;
            }
        }
        if ($additional_services){
            $order['additionalServices'] = $additional_services;
        }
        
        return $order;
    }

    public function pickupPointData($id, $_order, $business_id) {
        $data = $this->api->getUrlData('https://locationservice.posti.com/api/2/location');
        $points = json_decode($data, true);
        if (is_array($points) && isset($points['locations'])) {
            foreach ($points['locations'] as $point) {
                if ($point['pupCode'] === $id) {
                    return array(
                        "externalId" => $business_id . "-" . $_order->get_customer_id(),
                        "name" => $_order->get_shipping_first_name() . ' ' . $_order->get_shipping_last_name() . ' c/o ' . $point['publicName']['en'],
                        "streetAddress" => $point['address']['en']['address'],
                        "postalCode" => $point['postalCode'],
                        "postOffice" => $point['address']['en']['postalCodeName'],
                        "country" => $point['countryCode'],
                        "telephone" => $_order->get_billing_phone(),
                        "email" => $_order->get_billing_email()
                    );
                }
            }
        }
        return false;
    }

}
