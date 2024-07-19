<?php

namespace Posti_Warehouse;

defined('ABSPATH') || exit;

class Posti_Warehouse_Service {

	private $api;
	private $logger;

	public function __construct(Posti_Warehouse_Api $api, Posti_Warehouse_Logger $logger) {
		$this->api = $api;
		$this->logger = $logger;
	}
	
	public function get_services() {
		$transient_name = 'posti_warehouse_shipping_methods';
		$transient_time = 86400; // 24 hours
		
		$all_shipping_methods = get_transient($transient_name);
		if (empty($all_shipping_methods)) {
			try {
				$all_shipping_methods = $this->api->getDeliveryServices($this->delivery_service);
				
				$log_msg = ( empty($all_shipping_methods) ) ? 'An empty list was received' : 'List received successfully';
				$this->logger->log('info', 'Trying to get list of shipping methods... ' . $log_msg);
			} catch (\Exception $ex) {
				$all_shipping_methods = null;
				$this->logger->log('error', 'Failed to get list of shipping methods: ' . $ex->getMessage());
			}
			
			if (!empty($all_shipping_methods)) {
				set_transient($transient_name, $all_shipping_methods, $transient_time);
			}
		}
		
		return $all_shipping_methods;
	}
	
	public function get_service($id) {
		foreach ($this->get_services() as $service) {
			if ($service['id'] === $id) {
				return $service;
			}
		}

		return null;
	}
}
