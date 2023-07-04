<?php

namespace Woo_Posti_Warehouse;

defined('ABSPATH') || exit;

class Api {

    private $username = null;
    private $password = null;
    private $token = null;
    private $test = false;
    private $logger;
    private $last_status = false;
    private $token_option = 'posti_wh_api_auth';
    private $user_agent = 'woo-wh-client/2.1.0';

    public function __construct(Logger $logger, array &$options) {
        $this->logger = $logger;
        $this->test = Settings::is_test($options);

        if($this->test) {
            $this->username = Settings::get_value($options, 'posti_wh_field_username_test');
            $this->password = Settings::get_value($options, 'posti_wh_field_password_test');
        } else {
            $this->username = Settings::get_value($options, 'posti_wh_field_username');
            $this->password = Settings::get_value($options, 'posti_wh_field_password');
        }
    }
    
    public static function install() {
        delete_option('posti_wh_api_auth');
    }
    
    public static function uninstall() {
        delete_option('posti_wh_api_auth');
    }
    
    public function getUserAgent() {
        return $this->user_agent;
    }
    
    public function getLastStatus() {
        return $this->last_status;
    }

    public function getToken() {
        $token_data = $this->createToken($this->getBaseUrl() . '/auth/token', $this->username, $this->password);
        if (isset($token_data->access_token)) {
            update_option($this->token_option, array('token' => $token_data->access_token, 'expires' => time() + $token_data->expires_in - 100));
            $this->token = $token_data->access_token;
            $this->logger->log('info', "Refreshed access token");
            return $token_data->access_token;
        } else {
            $this->logger->log('error', "Failed to get token for " . $this->username . ', repsonse ' . json_encode($token_data));
        }
        return false;
    }
    
    private function ApiCall($url, $data = '', $action = 'GET') {
        if (!$this->token) {
            $token_data = get_option($this->token_option);            
            if (!$token_data || isset($token_data['expires']) && $token_data['expires'] < time()) {
                $this->getToken();
            } elseif (isset($token_data['token'])) {
                $this->token = $token_data['token'];
            } else {
                $this->logger->log('error', "Failed to get token");
                return false;
            }
        }
        
        $env = $this->test ? "TEST ": "";
        $curl = curl_init();
        $header = array();

        $header[] = 'Authorization: Bearer ' . $this->token;
        $payload = null;
        if ($action == "POST" || $action == "PUT" || $action == "DELETE") {
            $payload = json_encode($data);

            $header[] = 'Content-Type: application/json';
            $header[] = 'Content-Length: ' . strlen($payload);
            if ($action == "POST") {
                curl_setopt($curl, CURLOPT_POST, 1);
            } else {
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $action);
            }
            curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        }
        elseif ($action == "GET" && is_array($data)){
            $url .= '?' . http_build_query($data);
        }
        
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_URL, $this->getBaseUrl() . $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_USERAGENT, $this->user_agent);

        $result = curl_exec($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $this->last_status = $http_status;

        if ($http_status < 200 || $http_status >= 300) {
            $this->logger->log("error", $env . "HTTP $http_status : $action request to $url" . (isset($payload) ? " with payload:\r\n $payload" : '') . "\r\n\r\nand result:\r\n $result");
            return false;
        }
        
        $this->logger->log("info", $env . "HTTP $http_status : $action request to $url" . (isset($payload) ? " with payload\r\n $payload" : ''));
        return json_decode($result, true);
    }

    public function getWarehouses() {
        $warehouses_data = get_option('posti_wh_api_warehouses');
        if (!$warehouses_data || $warehouses_data['last_sync'] < time() - 1800) {
            $warehouses = $this->ApiCall('/ecommerce/v3/catalogs?role=RETAILER', '', 'GET');
            if (is_array($warehouses) && isset($warehouses['content'])) {
                update_option('posti_wh_api_warehouses', array(
                    'warehouses' => $warehouses['content'],
                    'last_sync' => time(),
                ));
                $warehouses = $warehouses['content'];
            } else {
                $warehouses = array();
            }
        } else {
            $warehouses = $warehouses_data['warehouses'];
        }
        return $warehouses;
    }

    public function getDeliveryServices($workflow) {
        $services = $this->ApiCall('/ecommerce/v3/services', array('workflow' => urlencode($workflow)) , 'GET');
        return $services;
    }

    public function getProduct($id) {
        return $this->ApiCall('/ecommerce/v3/inventory/' . urlencode($id), '', 'GET');
    }
    
    public function getProducts(&$ids) {
        $ids_encoded = array();
        foreach ($ids as $id) {
            array_push($ids_encoded, urlencode($id));
        }
        
        return $this->ApiCall('/ecommerce/v3/inventory?productExternalId=' . implode(',', $ids_encoded), '', 'GET');
    }
    
    public function getBalancesUpdatedSince($dttm_since, $size, $page = 0) {
        if (!isset($dttm_since)) {
            return [];
        }
        
        return $this->ApiCall('/ecommerce/v3/catalogs/balances?modifiedFromDate=' . urlencode($dttm_since) . '&size=' . $size . '&page=' . $page, '', 'GET');
    }
    
    public function getBalances(&$ids) {
        $ids_encoded = array();
        foreach ($ids as $id) {
            array_push($ids_encoded, urlencode($id));
        }
        
        return $this->ApiCall('/ecommerce/v3/catalogs/balances?productExternalId=' . implode(',', $ids_encoded), '', 'GET');
    }
    
    public function patchBalances($catalogId, &$balances) {
        $status = $this->ApiCall('/ecommerce/v3/catalogs/' . urlencode($catalogId) . '/balances', $balances, 'PATCH');
        return $status;
    }

    public function putInventory(&$products) {
        $status = $this->ApiCall('/ecommerce/v3/inventory', $products, 'PUT');
        return $status;
    }
    
    public function deleteInventory(&$products) {
        $status = $this->ApiCall('/ecommerce/v3/inventory', $products, 'DELETE');
        return $status;
    }
    
    public function addOrder(&$order) {
        $status = $this->ApiCall('/ecommerce/v3/orders', $order, 'POST');
        return $status;
    }

    public function getOrder($order_id) {
        $status = $this->ApiCall('/ecommerce/v3/orders/' . urlencode($order_id), '', 'GET');
        return $status;
    }
    
    public function getOrdersUpdatedSince($dttm_since, $size, $page = 0) {
        if (!isset($dttm_since)) {
            return [];
        }
        
        $products = $this->ApiCall('/ecommerce/v3/orders'
                . '?modifiedFromDate=' . urlencode($dttm_since)
                . '&size=' . $size
                . '&page=' . $page, '', 'GET');
        return $products;
    }
    
    public function getPickupPoints($postcode = null, $street_address = null, $country = null, $service_code = null) {
        if (($postcode == null && $street_address == null) || (trim($postcode) == '' && trim($street_address) == '')) {
            return array();
        }

        return $this->ApiCall('/ecommerce/v3/pickup-points'
                . '?serviceCode=' . urlencode($service_code)
                . '&postalCode=' . urlencode($postcode)
                . '&streetAddress=' . urlencode($street_address)
                . '&country=' . urlencode($country), '', 'GET');
    }

    public function getPickupPointsByText($query_text, $service_code) {
        if ($query_text == null || trim($query_text) == '') {
            return array();
        }

        return $this->ApiCall('/ecommerce/v3/pickup-points'
                . '?serviceCode=' . urlencode($service_code)
                . '&search=' . urlencode($query_text), '', 'GET');
    }
    
    public function migrate() {
        $status = $this->ApiCall('/ecommerce/v3/inventory/migrate', '', 'POST');
        return $status;
    }

    private function getBaseUrl() {
        if ($this->test) {
            return "https://argon.ecom-api.posti.com";
        }
        return "https://ecom-api.posti.com";
    }

    private function createToken($url, $user, $secret) {
        $headers = array();
        $headers[] = 'Accept: application/json';
        $headers[] = 'Authorization: Basic ' .base64_encode("$user:$secret");

        $options = array(
            CURLOPT_POST            => 0,
            CURLOPT_HEADER          => 0,
            CURLOPT_URL             => $url,
            CURLOPT_FRESH_CONNECT   => 1,
            CURLOPT_RETURNTRANSFER  => 1,
            CURLOPT_FORBID_REUSE    => 1,
            CURLOPT_USERAGENT       => $this->user_agent,
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_HTTPHEADER      => $headers,

        );

        $ch = curl_init();
        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);

        curl_close($ch);

        return json_decode($response);
    }
}
