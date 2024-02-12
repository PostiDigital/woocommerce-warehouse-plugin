<?php

namespace Posti_Warehouse;

defined('ABSPATH') || exit;

class Posti_Warehouse_Settings {
	
	private $api;
	private $logger;
	
	public function __construct(Posti_Warehouse_Api $api, Posti_Warehouse_Logger $logger) {
		$this->api = $api;
		$this->logger = $logger;
		register_setting('posti_wh', 'posti_wh_options');
		add_action('admin_init', array($this, 'posti_wh_settings_init'));
		add_action('admin_menu', array($this, 'posti_wh_options_page'));
		add_action('wp_ajax_posti_warehouse_products_migrate', array($this, 'posti_warehouse_products_migrate'));
	}

	public static function get() {
		$options = get_option('posti_wh_options');
		return $options ? $options : array();
	}
	
	public static function get_shipping_settings() {
		$options = get_option('woocommerce_posti_warehouse_settings');
		return $options ? $options : array();
	}
	
	public static function get_value( &$options, $key) {
		if (!isset($options) || !isset($options[$key])) {
			return null;
		}
		
		return $options[$key];
	}
	
	
	public static function update( &$options) {
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
	
	public static function is_debug( $options) {
		return self::is_option_true($options, 'posti_wh_field_debug');
	}
	
	public static function is_test( $options) {
		return self::is_option_true($options, 'posti_wh_field_test_mode');
	}
	
	public static function is_test_mode() {
		return self::is_option_true(self::get(), 'posti_wh_field_test_mode');
	}
	
	public static function is_add_tracking( $options) {
		return self::is_option_true($options, 'posti_wh_field_addtracking');
	}
	
	public static function is_changed( &$old_options, &$new_options, $option) {
		return self::get_value($old_options, $option) != self::get_value($new_options, $option);
	}
	
	public static function is_developer() {
		return ( isset($_GET) && isset($_GET['developer']) )
			|| ( isset($_POST) && isset($_POST['developer']) );
	}
	
	public function posti_wh_settings_init() {
		$is_developer = self::is_developer();
		$developer_fields_class = $is_developer ? 'posti_wh_row' : 'hidden';
		add_settings_section(
			'posti_wh_options',
			'<span class="dashicons dashicons-admin-generic" style="padding-right: 2pt"></span>' . Posti_Warehouse_Text::field_warehouse_settings(),
				array($this, 'posti_wh_section_developers_cb'),
				'posti_wh'
		);

		add_settings_field(
				'posti_wh_field_username',
				Posti_Warehouse_Text::field_username(),
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
				Posti_Warehouse_Text::field_password(),
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
				Posti_Warehouse_Text::field_username_test(),
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
				Posti_Warehouse_Text::field_password_test(),
				array($this, 'posti_wh_field_string_cb'),
				'posti_wh',
				'posti_wh_options',
				[
					'label_for' => 'posti_wh_field_password_test',
					'class' => 'posti_wh_row',
					'posti_wh_custom_data' => 'custom',
				]
		);

		add_settings_field(
			'posti_wh_field_business_id',
			Posti_Warehouse_Text::field_business_id(),
			array($this, 'posti_wh_field_string_cb'),
			'posti_wh',
			'posti_wh_options',
			[
				'label_for' => 'posti_wh_field_business_id',
				'class' => $developer_fields_class,
				'posti_wh_custom_data' => 'custom',
			]
		);

		add_settings_field(
				'posti_wh_field_service',
				Posti_Warehouse_Text::field_service(),
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
				'posti_wh_field_type',
				Posti_Warehouse_Text::field_type(),
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
				Posti_Warehouse_Text::field_autoorder(),
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
				Posti_Warehouse_Text::field_autocomplete(),
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
				Posti_Warehouse_Text::field_addtracking(),
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
				Posti_Warehouse_Text::field_crontime(),
				array($this, 'posti_wh_field_string_cb'),
				'posti_wh',
				'posti_wh_options',
				[
					'label_for' => 'posti_wh_field_crontime',
					'class' => $developer_fields_class,
					'posti_wh_custom_data' => 'custom',
					'input_type' => 'number',
					'default' => '600'
				]
		);

		add_settings_field(
				'posti_wh_field_test_mode',
				Posti_Warehouse_Text::field_test_mode(),
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
				Posti_Warehouse_Text::field_field_debug(),
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
				Posti_Warehouse_Text::field_stock_sync_dttm(),
				array($this, 'posti_wh_field_string_cb'),
				'posti_wh',
				'posti_wh_options',
				[
					'label_for' => 'posti_wh_field_stock_sync_dttm',
					'class' => $developer_fields_class,
					'posti_wh_custom_data' => 'custom',
				]
		);
		
		add_settings_field(
				'posti_wh_field_order_sync_dttm',
				Posti_Warehouse_Text::field_order_sync_dttm(),
				array($this, 'posti_wh_field_string_cb'),
				'posti_wh',
				'posti_wh_options',
				[
					'label_for' => 'posti_wh_field_order_sync_dttm',
					'class' => $developer_fields_class,
					'posti_wh_custom_data' => 'custom',
				]
		);
	}

	public function posti_wh_section_developers_cb( $args) {
		
	}

	public function posti_wh_field_checkbox_cb( $args) {
		$options = self::get();
		$checked = '';
		if (self::is_option_true($options, $args['label_for'])) {
			$checked = ' checked="checked" ';
		}
		?>
		<input <?php echo esc_html($checked); ?> id = "<?php echo esc_attr($args['label_for']); ?>" name='posti_wh_options[<?php echo esc_attr($args['label_for']); ?>]' type='checkbox' value = "1"/>
		<?php
	}
	
	public function posti_wh_field_string_cb( $args) {
		$options = self::get();
		$value = self::get_value($options, $args['label_for']);
		$type = 'text';
		if (isset($args['input_type'])) {
			$type = $args['input_type'];
		}
		if (!$value && isset($args['default'])) {
			$value = $args['default'];
		}
		?>
		<input id="<?php echo esc_attr($args['label_for']); ?>" name="posti_wh_options[<?php echo esc_attr($args['label_for']); ?>]" size='20' type='<?php echo esc_attr($type); ?>' value="<?php echo esc_attr($value); ?>" />
		<?php
	}

	public function posti_wh_field_type_cb( $args) {

		$options = self::get();
		?>
		<select id="<?php echo esc_attr($args['label_for']); ?>"
				data-custom="<?php echo esc_attr($args['posti_wh_custom_data']); ?>"
				name="posti_wh_options[<?php echo esc_attr($args['label_for']); ?>]"
				>
		<?php foreach (Posti_Warehouse_Dataset::getSToreTypes() as $val => $type) : ?>
				<option value="<?php echo esc_attr($val); ?>" <?php echo isset($options[$args['label_for']]) ? ( selected($options[$args['label_for']], $val, false) ) : ( '' ); ?>>
						<?php
						echo esc_html($type);
						?>
				</option>
				<?php endforeach; ?>
		</select>
			<?php
	}

	public function posti_wh_field_service_cb( $args) {

		$options = self::get();
		?>
		<select id="<?php echo esc_attr($args['label_for']); ?>"
				data-custom="<?php echo esc_attr($args['posti_wh_custom_data']); ?>"
				name="posti_wh_options[<?php echo esc_attr($args['label_for']); ?>]"
				>
		<?php foreach (Posti_Warehouse_Dataset::getDeliveryTypes() as $val => $type) : ?>
				<option value="<?php echo esc_attr($val); ?>" <?php echo isset($options[$args['label_for']]) ? ( selected($options[$args['label_for']], $val, false) ) : ( '' ); ?>>
						<?php
						echo esc_html($type);
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
		
		$business_id = self::get_business_id();
		if (isset($business_id) && !empty($business_id)) {
			$token = $this->api->getToken();
			if (!empty($token)) {
				?>
		<input id="posti_migration_metabox_nonce" name="posti_migration_metabox_nonce"
			value="<?php echo esc_attr(wp_create_nonce('posti-migration')); ?>"
			type="hidden" />
		<input id="posti_migration_url" name="posti_migration_url"
			value="<?php echo esc_attr(admin_url('admin-ajax.php')); ?>"
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
	
	public function posti_warehouse_products_migrate() {
		if (!isset($_POST['security'])
			|| !wp_verify_nonce(sanitize_key($_POST['security']), 'posti-migration')) {
			$this->logger->log('error', 'Unable to migrate products: nonce check failed');
			throw new \Exception('Unable to migrate products');
		}

		if (self::is_test_mode() && !self::is_developer()) {
			echo wp_json_encode(array('testMode' => true));
			exit();
		}
		
		if ($this->api->migrate() === false) {
			$this->logger->log('error', 'Unable to migrate products');
			throw new \Exception('Unable to migrate products');
		}
		
		$business_id = self::get_business_id();
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
						update_post_meta($post->ID, '_posti_id', sanitize_text_field(substr($product_id, strlen($business_id) + 1)));
					}
				}
			}
		}
		
		$options = self::get();
		if (isset($options['posti_wh_field_business_id'])) {
			unset($options['posti_wh_field_business_id']);
			self::update($options);
		}
		
		$this->logger->log('info', 'Products migrated');
		echo wp_json_encode(array('result' => true));
		exit();
	}
	
	private static function is_option_true( &$options, $key) {
		return isset($options[$key]) && self::is_true($options[$key]);
	}
	
	private static function is_true( $value) {
		if (!isset($value)) {
			return false;
		}
		
		return 1 === $value
			|| '1' === $value
			|| 'yes' === $value
			|| 'true' === $value;
	}
	
	private static function get_business_id() {
		$options = self::get();
		return isset($options['posti_wh_field_business_id']) ? $options['posti_wh_field_business_id'] : null;
	}
}
