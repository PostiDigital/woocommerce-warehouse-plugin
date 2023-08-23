<?php

namespace Woo_Posti_Warehouse;

defined('ABSPATH') || exit;

class Core {

	private $settings = null;
	private $api = null;
	private $metabox = null;
	private $order = null;
	private $product = null;
	private $is_test = false;
	private $debug = false;
	private $add_tracking = false;
	private $cron_time = 600;
	private $logger;
	private $frontend = null;
	public $prefix = 'warehouse';
	public $version = '0.0.0';
	public $templates_dir;
	public $templates;

	public function __construct() {

		$this->templates_dir = plugin_dir_path(__POSTI_WH_FILE__) . 'templates/';
		$this->templates = array(
		  'checkout_pickup' => 'checkout-pickup.php',
		  'account_order' => 'myaccount-order.php',
		);
		
		$this->load_options();
		add_action('admin_enqueue_scripts', array($this, 'posti_wh_admin_styles'));
		$this->WC_hooks();

		register_activation_hook(__POSTI_WH_FILE__, array($this, 'install'));
		register_deactivation_hook(__POSTI_WH_FILE__, array($this, 'uninstall'));

		add_action('updated_option', array($this, 'after_settings_update'), 10, 3);
		add_action('admin_notices', array($this, 'render_messages'));

		add_filter('plugin_action_links', array($this, 'attach_plugin_links'), 10, 2);
	}
	
	public function getApi() {
		return $this->api;
	}

	public function install() {
		Settings::install();
		Api::install();
		Logger::install();
		delete_transient('posti_warehouse_shipping_methods');
	}

	public function uninstall() {
		Settings::uninstall();
		Api::uninstall();
		Logger::uninstall();
		delete_transient('posti_warehouse_shipping_methods');
	}

	public function load_textdomain() {
		load_plugin_textdomain(
				'posti-warehouse',
				false,
				dirname(__FILE__) . '/languages/'
		);
	}
	
	public function attach_plugin_links( $actions, $file) {
		if (strpos($file, 'woocommerce-warehouse-plugin') !== false) {
			$settings_link = sprintf('<a href="%s">%s</a>', admin_url('options-general.php?page=posti_wh'), 'Settings');
			array_unshift($actions, $settings_link);
		}

		return $actions;
	}

	public function after_settings_update( $option, $old_value, $value) {
		if ('posti_wh_options' == $option) {
			if (Settings::is_changed($old_value, $value, 'posti_wh_field_username')
				|| Settings::is_changed($old_value, $value, 'posti_wh_field_password')
				|| Settings::is_changed($old_value, $value, 'posti_wh_field_username_test')
				|| Settings::is_changed($old_value, $value, 'posti_wh_field_password_test')
				|| Settings::is_changed($old_value, $value, 'posti_wh_field_test_mode')) {
				//login info changed, try to get token
				delete_option('posti_wh_api_auth');
				delete_option('posti_wh_api_warehouses');
				if ('' === session_id() || !isset($_SESSION)) {
					session_start();
				}
				
				$_SESSION['posti_warehouse_check_token'] = true;
			}
		}
	}

	public function render_messages() {
		
		if (session_id() === '' || !isset($_SESSION)) {
			session_start();
		}
		
		if (isset($_SESSION['posti_warehouse_check_token'])) {
			// reload updated options
			$this->load_options();
			$token = $this->api->getToken();
			if ($token) {
				$this->token_success();
			} else {
				$this->token_error();
			}

			unset($_SESSION['posti_warehouse_check_token']);
		}
	}

	public function token_error() {
		?>
		<div class="error notice">
			<p><?php echo esc_html(Text::error_api_credentials_wrong()); ?></p>
		</div>
		<?php
	}

	public function token_success() {
		?>
		<div class="updated notice">
			<p><?php echo esc_html(Text::api_credentials_correct()); ?></p>
		</div>
		<?php
	}

	public function posti_wh_admin_styles( $hook) {
		
		wp_enqueue_style( 'select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0');
		wp_enqueue_script( 'select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', 'jquery', '4.1.0-rc.0');
	
		wp_enqueue_style('posti_wh_admin_style', plugins_url('assets/css/admin-warehouse-settings.css', dirname(__FILE__)), [], '1.0');
		wp_enqueue_script('posti_wh_admin_script', plugins_url('assets/js/admin-warehouse.js', dirname(__FILE__)), 'jquery', '1.2');
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

	public function posti_interval( $schedules) {
		$schedules['posti_wh_time'] = array(
			'interval' => $this->cron_time,
			'display' => Text::interval_every($this->cron_time));
		return $schedules;
	}

	/*
	 * Cronjob to sync products and orders
	 */

	public function posti_cronjob_callback() {
		$options = Settings::get();
		$nextStockSyncDttm = $this->posti_cronjob_sync_stock($options);
		$nextOrderSyncDttm = $this->posti_cronjob_sync_orders($options);

		if (false !== $nextStockSyncDttm || false !== $nextOrderSyncDttm) {
			$new_options = Settings::get();
			if (false !== $nextStockSyncDttm) {
				$new_options['posti_wh_field_stock_sync_dttm'] = $nextStockSyncDttm;
			}
			
			if (false !== $nextOrderSyncDttm) {
				$new_options['posti_wh_field_order_sync_dttm'] = $nextOrderSyncDttm;
			}

			Settings::update($new_options);
		}
	}

	public function posti_cronjob_sync_stock( $options) {
		try {
			$sync_dttm = $this->get_option_datetime_sync($options, 'posti_wh_field_stock_sync_dttm');
			$next_sync_dttm = ( new \DateTime() )->format(\DateTimeInterface::RFC3339_EXTENDED);
			$synced = $this->product->sync($sync_dttm);

			return $synced ? $next_sync_dttm : false;

		} catch (\Exception $e) {
			$this->logger->log('error', $e->getMessage());
		}
		
		return false;
	}
	
	public function posti_cronjob_sync_orders( $options) {
		try {
			$sync_dttm = $this->get_option_datetime_sync($options, 'posti_wh_field_order_sync_dttm');
			$next_sync_dttm = ( new \DateTime() )->format(\DateTimeInterface::RFC3339_EXTENDED);
			$synced = $this->order->sync($sync_dttm);

			return $synced ? $next_sync_dttm : false;

		} catch (\Exception $e) {
			$this->logger->log('error', $e->getMessage());
		}
		
		return false;
	}
	
	public function hide_other_shipping_if_posti_products( $rates) {
		global $woocommerce;
		$hide_other = false;
		$items = $woocommerce->cart->get_cart();

		foreach ($items as $item => $values) {
			$product_warehouse = get_post_meta($values['data']->get_id(), '_posti_wh_warehouse', true);
			$type = $this->product->get_stock_type_by_warehouse($product_warehouse);
			if (( 'Posti' == $type ) && $product_warehouse) {
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

	private function get_option_datetime_sync( $options, $option) {
		$value = Settings::get_value($options, $option);
		if (!isset($value) || empty($value)) {
			$now = new \DateTime('now -7 day');
			$value = $now->format(\DateTimeInterface::RFC3339_EXTENDED);
		}
		
		return $value;
	}
	
	private function load_options() {
		$options = Settings::get();
		$this->is_test = Settings::is_test($options);
		$this->debug = Settings::is_debug($options);
		$this->add_tracking = Settings::is_add_tracking($options);

		if (isset($options['posti_wh_field_crontime']) && $options['posti_wh_field_crontime']) {
			$this->cron_time = (int) $options['posti_wh_field_crontime'];
		}

		$this->logger = new Logger();
		$this->logger->setDebug($this->debug);
		
		$this->api = new Api($this->logger, $options, $this->is_test);
		$this->product = new Product($this->api, $this->logger);
		$this->order = new Order($this->api, $this->logger, $this->product, $this->add_tracking);
		$this->metabox = new Metabox($this->order);
		$this->debug = new Debug($options);
		$this->frontend = new Frontend($this);
		$this->settings = new Settings($this->api, $this->logger);
		$this->frontend->load();
	}
}
