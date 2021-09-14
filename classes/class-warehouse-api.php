<?php

use Pakettikauppa\Client;

class PostiWarehouseApi {

    private $username = null;
    private $password = null;
    private $token = null;
    private $test = false;
    private $debug = false;
    private $business_id = false;

    public function __construct($business_id, $test = false, $debug = false) {
        $this->business_id = $business_id;
        if ($test) {
            $this->test = true;
        }
        
        $this->debug = $debug;
        $options = get_option('posti_wh_options');
        $this->username = $options['posti_wh_field_username'];
        $this->password = $options['posti_wh_field_password'];
    }

    private function getApiUrl() {
        if ($this->test) {
            return "https://argon.api.posti.fi/ecommerce/v3/";
        }
        return "https://api.posti.fi/ecommerce/v3/";
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

    public function getToken() {
        /*
          $curl = curl_init();
          $accesstoken = base64_encode($this->username . ":" . $this->password);
          $header = array();
          $header[] = 'Accept: application/json';
          $header[] = 'Authorization: Basic ' . $accesstoken;

          curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
          curl_setopt($curl, CURLOPT_POST, 1);
          //curl_setopt($curl, CURLOPT_POSTFIELDS, $auth_data);
          curl_setopt($curl, CURLOPT_URL, $this->getAuthUrl() . 'oauth/token?grant_type=client_credentials');
          curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
          $result = curl_exec($curl);
          if (!$result) {
          return false;
          }
          curl_close($curl);
          $data = json_decode($result);
          $token = $data->access_token;
          $expires = time() + $data->expires_in;
          update_option('posti_wh_api_auth', array($token, $expires));
          $this->token = $token;
          return $token;
         * 
         */
        $config = array('wh' => [
                'api_key' => $this->username,
                'secret' => $this->password,
                'use_posti_auth' => true,
                'posti_auth_url' => $this->getAuthUrl(),
                'base_uri' => $this->getApiUrl(),
            ]
        );

        $client = new \Pakettikauppa\Client($config, 'wh');

        $token_data = $client->getToken();
        if (isset($token_data->access_token)) {
            update_option('posti_wh_api_auth', array('token' => $token_data->access_token, 'expires' => time() + $token_data->expires_in - 100));
            $this->token = $token_data->access_token;
            $this->log("Refreshed access token");
            return $token_data->access_token;
        } else {
            $this->log("Failed to get token from api: ".json_encode($config).', reponse '.json_encode($token_data));
        }
        return false;
    }

    private function ApiCall($url, $data = '', $action = 'GET') {
        if (!$this->token) {
            $token_data = get_option('posti_wh_api_auth');
            if (!$token_data || isset($token_data['expires']) && $token_data['expires'] < time()) {
                $this->getToken();
            } elseif (isset($token_data['token'])) {
                $this->token = $token_data['token'];
            } else {
                $this->log("Failed to get token");
                return false;
            }
        }
        $curl = curl_init();
        $header = array();
//$header[] = 'Accept: application/json';
        $header[] = 'Authorization: Bearer ' . $this->token;

        $this->log("Request to: " . $url);
        if ($data) {
            $this->log($data);
        }

        if ($action == "POST" || $action == "PUT") {
            $payload = json_encode($data);
//var_dump($payload); exit;
            $header[] = 'Content-Type: application/json';
            $header[] = 'Content-Length: ' . strlen($payload);
            if ($action == "POST") {
                curl_setopt($curl, CURLOPT_POST, 1);
            } else {
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $action);
            }
            curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
//echo $this->getApiUrl() . $url;
        curl_setopt($curl, CURLOPT_URL, $this->getApiUrl() . $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
//curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $result = curl_exec($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
//var_dump($http_status);
//var_dump($result); exit;

        if (!$result) {
            $this->log($http_status . ' - response from ' . $url . ': ' . $result);
            return false;
        }


        if ($http_status != 200) {
            $this->log("Response code: " . $http_status);
            return false;
        }
        return json_decode($result, true);
    }

    public function getUrlData($url) {
        $curl = curl_init();
        $header = array();
//$header[] = 'Accept: application/json';

        $this->log("Request to: " . $url);

        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
//echo $this->getApiUrl() . $url;
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
//curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $result = curl_exec($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
//var_dump($http_status);
//var_dump($result); exit;

        if (!$result) {

            $this->log($curl);
            return false;
        }
        $this->log($result, 'Response from ' . $url . ': ');

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

    public function getProduct($id) {
        $product = $this->ApiCall('inventory/' . $id, '', 'GET');
//var_dump($product);exit;
        return $product;
    }

    public function getProductsByWarehouse($id) {
        $products = $this->ApiCall('products/all/' . $id, '', 'GET');

        return $products;
    }

    public function addProduct($product, $business_id = false) {
//var_dump($product); exit;
        $status = $this->ApiCall('inventory', $product, 'PUT');
        return $status;
    }

    public function addOrder($order, $business_id = false) {
        $status = $this->ApiCall('orders', $order, 'POST');
        return $status;
    }

    public function getOrder($order_id, $business_id = false) {
        $status = $this->ApiCall('orders/' . $order_id, '', 'GET');
        return $status;
    }

    private function log($msg, $extra = '') {
        if ($this->debug) {
            if (is_array($msg) || is_object($msg)) {
                $msg = $extra . print_r($msg, true);
            }
            $debug = get_option('posti_wh_logs', array());
            if (!is_array($debug)) {
                $debug = array();
            }
            $debug[] = date('Y-m-d H:i:s') . ': ' . $msg;
            while (is_array($debug) && count($debug) > 20) {
                $debug = array_shift($debug);
            }


            update_option('posti_wh_logs', $debug);
        }
    }

}
