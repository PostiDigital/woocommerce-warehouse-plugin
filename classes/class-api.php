<?php

namespace Posti_Warehouse;

defined('ABSPATH') || exit;

class Posti_Warehouse_Api {

	private $username = null;
	private $password = null;
	private $token = null;
	private $test = false;
	private $logger;
	private $last_status = false;
	private $token_option = 'posti_wh_api_auth';
	private $user_agent = 'woo-wh-client/2.4.2';

	public function __construct(Posti_Warehouse_Logger $logger, array &$options) {
		$this->logger = $logger;
		$this->test = Posti_Warehouse_Settings::is_test($options);

		if ($this->test) {
			$this->username = Posti_Warehouse_Settings::get_value($options, 'posti_wh_field_username_test');
			$this->password = Posti_Warehouse_Settings::get_value($options, 'posti_wh_field_password_test');
		} else {
			$this->username = Posti_Warehouse_Settings::get_value($options, 'posti_wh_field_username');
			$this->password = Posti_Warehouse_Settings::get_value($options, 'posti_wh_field_password');
		}
	}
	
	public static function install() {
		delete_option('posti_wh_api_auth');
	}
	
	public static function uninstall() {
		delete_option('posti_wh_api_auth');
		delete_option('posti_wh_api_warehouses');
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
			$this->logger->log('info', 'Refreshed access token');
			return $token_data->access_token;
		} else {
			$this->logger->log('error', 'Failed to get token for ' . $this->username . ', repsonse ' . wp_json_encode($token_data));
		}
		return false;
	}

	private function ApiCall($url, $data = '', $method = 'GET') {
		if (!$this->token) {
			$token_data = get_option($this->token_option);			
			if (!$token_data || isset($token_data['expires']) && $token_data['expires'] < time()) {
				$this->getToken();
			} elseif (isset($token_data['token'])) {
				$this->token = $token_data['token'];
			} else {
				$this->logger->log('error', 'Failed to get token');
				return false;
			}
		}

		$request_args = array(
			'method' => $method,
			'user-agent' => $this->user_agent,
			'timeout' => 30
		);

		$headers = array(
			'Authorization' => 'Bearer ' . $this->token
		);

		$request_body = null;
		if ('POST' == $method || 'PUT' == $method || 'DELETE' == $method) {
			$request_body = wp_json_encode($data);
			$request_args['body'] = $request_body;
			$headers['Content-Type'] = 'application/json';
			$headers['Content-Length'] = strlen($request_body);

		} elseif ('GET' == $method && is_array($data)) {
			$url = $url . '?' . http_build_query($data);
		}
		$request_args['headers'] = $headers;

		$response = wp_remote_request($this->getBaseUrl() . $url, $request_args);
		$response_body = wp_remote_retrieve_body($response);
		$http_status = wp_remote_retrieve_response_code($response);
		$this->last_status = $http_status;

		$env = $this->test ? 'TEST ': '';
		if ($http_status < 200 || $http_status >= 300) {
			$this->logger->log('error', $env . "HTTP $http_status : $method request to $url" . ( isset($request_body) ? " with payload:\r\n $request_body" : '' ) . "\r\n\r\nand result:\r\n $response_body");
			return false;
		}

		$this->logger->log('info', $env . "HTTP $http_status : $method request to $url" . ( isset($request_body) ? " with payload\r\n $request_body" : '' ));
		return json_decode($response_body, true);
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

	public function getDeliveryServices( $workflow) {
		$services = $this->ApiCall('/ecommerce/v3/services', array('workflow' => urlencode($workflow)) , 'GET');
		return $services;
	}

	public function getProduct( $id) {
		return $this->ApiCall('/ecommerce/v3/inventory/' . urlencode($id), '', 'GET');
	}
	
	public function getProducts( &$ids) {
		$ids_encoded = array();
		foreach ($ids as $id) {
			array_push($ids_encoded, urlencode($id));
		}
		
		return $this->ApiCall('/ecommerce/v3/inventory?productExternalId=' . implode(',', $ids_encoded), '', 'GET');
	}
	
	public function getBalancesUpdatedSince( $dttm_since, $size, $page = 0) {
		if (!isset($dttm_since)) {
			return [];
		}
		
		return $this->ApiCall('/ecommerce/v3/catalogs/balances?modifiedFromDate=' . urlencode($dttm_since) . '&size=' . $size . '&page=' . $page, '', 'GET');
	}
	
	public function getBalances( &$ids) {
		$ids_encoded = array();
		foreach ($ids as $id) {
			array_push($ids_encoded, urlencode($id));
		}
		
		return $this->ApiCall('/ecommerce/v3/catalogs/balances?productExternalId=' . implode(',', $ids_encoded), '', 'GET');
	}
	
	public function putInventory( &$products) {
		$status = $this->ApiCall('/ecommerce/v3/inventory', $products, 'PUT');
		return $status;
	}
	
	public function deleteInventory( &$products) {
		$status = $this->ApiCall('/ecommerce/v3/inventory', $products, 'DELETE');
		return $status;
	}
	
	public function deleteInventoryBalances( &$balances) {
		$status = $this->ApiCall('/ecommerce/v3/inventory/balances', $balances, 'DELETE');
		return $status;
	}
	
	public function addOrder( &$order) {
		$status = $this->ApiCall('/ecommerce/v3/orders', $order, 'POST');
		return $status;
	}

	public function getOrder( $order_id) {
		$status = $this->ApiCall('/ecommerce/v3/orders/' . urlencode($order_id), '', 'GET');
		return $status;
	}
	
	public function getOrdersUpdatedSince( $dttm_since, $size, $page = 0) {
		if (!isset($dttm_since)) {
			return [];
		}
		
		$result = $this->ApiCall('/ecommerce/v3/orders'
				. '?modifiedFromDate=' . urlencode($dttm_since)
				. '&size=' . $size
				. '&page=' . $page, '', 'GET');
		return $result;
	}
	
	public function getPickupPoints($postcode = null, $street_address = null, $country = null, $city = null, $service_code = null, $type = null, $capability = null) {
		if ((null == $postcode && null == $street_address)
			|| ('' == trim($postcode) && '' == trim($street_address))) {
			return array();
		}

		return $this->ApiCall('/ecommerce/v3/pickup-points'
				. '?serviceCode=' . urlencode($service_code)
				. '&postalCode=' . urlencode($postcode)
				. '&postOffice=' . urlencode($city)
				. '&streetAddress=' . urlencode($street_address)
				. '&country=' . urlencode($country)
				. '&type=' . urlencode($type)
				. '&capability=' . urlencode($capability), '', 'GET');
	}

	public function getPickupPointsByText($query_text, $country, $service_code, $type = null, $capability = null) {
		if (null == $query_text || '' == trim($query_text)) {
			return array();
		}

		return $this->ApiCall('/ecommerce/v3/pickup-points'
				. '?serviceCode=' . urlencode($service_code)
				. '&search=' . urlencode($query_text)
				. '&country=' . urlencode($country)
				. '&type=' . urlencode($type)
				. '&capability=' . urlencode($capability), '', 'GET');
	}
	
	public function migrate() {
		$status = $this->ApiCall('/ecommerce/v3/inventory/migrate', '', 'POST');
		return $status;
	}

	private function getBaseUrl() {
		if ($this->test) {
			return 'https://argon.ecom-api.posti.com';
		}
		return 'https://ecom-api.posti.com';
	}

	private function createToken($url, $user, $secret) {
		$headers = array(
			'Accept' => 'application/json',
			'Authorization' => 'Basic ' . base64_encode("$user:$secret")
		);

		$response = wp_remote_request(
			$url,
			array(
				'method' => 'GET',
				'user-agent' => $this->user_agent,
				'headers' => $headers,
				'timeout' => 30
			)
		);

		return json_decode(wp_remote_retrieve_body($response));
	}
}
