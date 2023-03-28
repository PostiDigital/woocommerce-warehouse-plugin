<?php

namespace PostiWarehouse\Classes;

use Pakettikauppa\Client;
use PostiWarehouse\Classes\Logger;

class Api {

    private $username = null;
    private $password = null;
    private $token = null;
    private $test = false;
    private $business_id = false;
    private $logger;
    private $last_status = false;
    private $token_option = 'posti_wh_api_auth';

    public function __construct(Logger $logger, $business_id, $test = false) {
        $this->business_id = $business_id;
        if ($test) {
            $this->test = true;
            $this->token_option = 'posti_wh_api_auth_test';
        }

        $options = get_option('woocommerce_posti_warehouse_settings');
        if($this->test){
            $this->username = $options['posti_wh_field_username_test'];
            $this->password = $options['posti_wh_field_password_test'];
        } else {
            $this->username = $options['posti_wh_field_username'];
            $this->password = $options['posti_wh_field_password'];
        }
        $this->logger = $logger;
    }
    
    public function getLastStatus() {
        return $this->last_status;
    }

    private function getApiUrl() {
        if ($this->test) {
            return "https://argon.ecom-api.posti.com/ecommerce/v3/";
        }
        return "https://ecom-api.posti.com/ecommerce/v3/";
    }

    public function getBusinessId() {
        return $this->business_id;
    }

    private function getAuthUrl() {
        if ($this->test) {
            return "https://oauth2.barium.posti.com";
        }
        return "https://oauth2.posti.com";
    }

    public function getToken($client = null) {
   
        if ($client == null){
            $config = array('wh' => [
                    'api_key' => $this->username,
                    'secret' => $this->password,
                    'use_posti_auth' => true,
                    'posti_auth_url' => $this->getAuthUrl(),
                    'base_uri' => $this->getApiUrl(),
                ]
            );

            $client = new Client($config, 'wh');
        }

        $token_data = $client->getToken();
        if (isset($token_data->access_token)) {
            update_option($this->token_option, array('token' => $token_data->access_token, 'expires' => time() + $token_data->expires_in - 100));
            $this->token = $token_data->access_token;
            $this->logger->log('info', "Refreshed access token");
            return $token_data->access_token;
        } else {
            $config_data = (!empty($config)) ? json_encode($config) : '-';
            $this->logger->log('error', "Failed to get token from api: " . $config_data . ', reponse ' . json_encode($token_data));
        }
        return false;
    }
    
    public function getClient(){
        $options = get_option('woocommerce_posti_warehouse_settings');
        $config = array('wh' => [
                'api_key' => $this->username,
                'secret' => $this->password,
                'use_posti_auth' => true,
                //'posti_auth_url' => $this->getAuthUrl(),
                //'base_uri' => $this->getApiUrl(),
                'posti_auth_url' => 'https://oauth2.posti.com',
                'base_uri' => 'https://nextshipping.posti.fi',
            ]
        );
        $client = new Client($config, 'wh');
        if (!$this->token) {
            $token_data = get_option($this->token_option);            
            if (!$token_data || isset($token_data['expires']) && $token_data['expires'] < time()) {
                $this->getToken($client);
            } elseif (isset($token_data['token'])) {
                $this->token = $token_data['token'];
            } else {
                $this->logger->log('error', "Failed to get token");
                return false;
            }
        }
        $client->setAccessToken($this->token);
        return $client;
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
        
        $env = $this->test ? "TEST ": "PROD ";
        $curl = curl_init();
        $header = array();

        $header[] = 'Authorization: Bearer ' . $this->token;

        
        if ($data) {
            $this->logger->log("info", json_encode($data));
        }

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
        $this->logger->log("info", $env . "Request to: " . $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

        curl_setopt($curl, CURLOPT_URL, $this->getApiUrl() . $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $this->last_status = $http_status;

        if (!$result) {
            $this->logger->log("error", $http_status . ' - response from ' . $url . ': ' . $result);
            return false;
        }


        if ($http_status != 200) {
            $this->logger->log("error", $env . "Request to: " . $url . "\nResponse code: " . $http_status . "\nResult: " . $result);
            return false;
        }
        return json_decode($result, true);
    }

    public function getUrlData($url) {
        $curl = curl_init();
        $header = array();
        $env = $this->test ? "TEST ": "PROD ";
        $this->logger->log("info", $env . "Request to: " . $url);

        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if (!$result) {

            $this->logger->log("error", $http_status . ' - response from ' . $url . ': ' . $result);
            return false;
        }
        $this->logger->log("info", $env . 'Response from ' . $url . ': ' . json_encode($result));

        return $result;
    }

    public function getWarehouses() {
        $warehouses_data = get_option('posti_wh_api_warehouses');
        if (!$warehouses_data || $warehouses_data['last_sync'] < time() - 1800) {
            $warehouses = $this->ApiCall('catalogs?role=RETAILER', '', 'GET');
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
        $services = $this->ApiCall('services', array('workflow' => urlencode($workflow)) , 'GET');
        return $services;
    }

    public function getProduct($id) {
        $product = $this->ApiCall('inventory/' . urlencode($id), '', 'GET');
        return $product;
    }
    
    public function getProducts($ids) {
        $ids_encoded = array();
        foreach ($ids as $id) {
            array_push($ids_encoded, urlencode($id));
        }
        
        $products = $this->ApiCall('inventory?productExternalId=' . implode(',', $ids_encoded), '', 'GET');
        return $products;
    }
    
    public function getBalancesUpdatedSince($dttm_since, $size, $page = 0) {
        if (!isset($dttm_since)) {
            return [];
        }
        
        $products = $this->ApiCall('inventory/balances?modifiedFromDate=' . urlencode($dttm_since) . '&size=' . $size . '&page=' . $page, '', 'GET');
        return $products;
    }

    public function putInventory($products) {
        $status = $this->ApiCall('inventory', $products, 'PUT');
        return $status;
    }
    
    public function deleteInventory($products) {
        $status = $this->ApiCall('inventory', $products, 'DELETE');
        return $status;
    }

    public function addOrder($order) {
        $status = $this->ApiCall('orders', $order, 'POST');
        return $status;
    }

    public function getOrder($order_id) {
        $status = $this->ApiCall('orders/' . urlencode($order_id), '', 'GET');
        return $status;
    }
    
    public function getOrdersUpdatedSince($dttm_since, $size, $page = 0) {
        if (!isset($dttm_since)) {
            return [];
        }
        
        $products = $this->ApiCall('orders?modifiedFromDate=' . urlencode($dttm_since) . '&size=' . $size . '&page=' . $page, '', 'GET');
        return $products;
    }
}
