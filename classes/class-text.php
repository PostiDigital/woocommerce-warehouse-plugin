<?php

namespace Posti_Warehouse;

defined('ABSPATH') || exit;

class Posti_Warehouse_Text {

	public static $namespace = 'posti-warehouse';

	public static function company() {
		return __('Posti', 'posti-warehouse');
	}
	
	public static function pickup_point_title() {
		return __( 'Pickup point', 'posti-warehouse');
	}
	
	public static function pickup_points_title() {
		return __('Pickup points', 'posti-warehouse');
	}
	
	public static function pickup_points_instruction() {
		return __('Choose one of pickup points close to the address you entered:', 'posti-warehouse');
	}
	
	public static function pickup_address() {
		return __('Pickup address', 'posti-warehouse');
	}

	public static function pickup_address_custom() {
		return __('Custom pickup address', 'posti-warehouse');
	}
	
	public static function pickup_points_search_instruction1() {
		return __('Search pickup points near you by typing your address above.', 'posti-warehouse');
	}
	
	public static function pickup_points_search_instruction2() {
		return __('If none of your preferred pickup points are listed, fill in a custom address above and select another pickup point.', 'posti-warehouse');
	}
	
	public static function pickup_points_hide_outdoor() {
		return __('Hide outdoor pickup points', 'posti-warehouse');
	}
	
	public static function pickup_point_select() {
		return __('Select a pickup point', 'posti-warehouse');
	}
	
	public static function store_pickup_title() {
		return __('Store pickup', 'posti-warehouse');
	}
	
	public static function estimated_delivery($date) {
		return sprintf(__('Estimated delivery %1$s', 'posti-warehouse'), \esc_html($date));
	}
	
	public static function order_not_placed() {
		return __('Order not placed', 'posti-warehouse');
	}
	
	public static function order_place() {
		return __('Place Order', 'posti-warehouse');
	}
	
	public static function order_failed() {
		return __('Failed to order.', 'posti-warehouse');
	}

	public static function order_status() {
		return __('Order status', 'posti-warehouse');
	}
	
	public static function tracking_title() {
		return __('Posti API Tracking', 'posti-warehouse');
	}
	
	public static function tracking_number( $number) {
		/* translators: $number, not translatable */
		return sprintf(__('Tracking number: %1$s', 'posti-warehouse'), \esc_html($number));
	}
	
	public static function column_warehouse() {
		return __('Warehouse', 'posti-warehouse');
	}
	
	public static function action_publish_to_warehouse() {
		return __('Select Posti warehouse/supplier', 'posti-warehouse');
	}
	
	public static function action_remove_from_warehouse() {
		return __('Remove Posti warehouse/supplier', 'posti-warehouse');
	}
	
	public static function field_ean() {
		return __('EAN / ISBN / Barcode', 'posti-warehouse');
	}
	
	public static function field_ean_caption() {
		return __('Enter EAN / ISBN / Barcode', 'posti-warehouse');
	}
	
	public static function field_price() {
		return __('Wholesale price', 'posti-warehouse');
	}
	
	public static function field_price_caption() {
		return __('Enter wholesale price', 'posti-warehouse');
	}
	
	public static function field_stock_type() {
		return __('Stock type', 'posti-warehouse');
	}
	
	public static function field_warehouse() {
		return __('Warehouse', 'posti-warehouse');
	}
	
	public static function field_distributor() {
		return __('Distributor ID', 'posti-warehouse');
	}
	
	public static function confirm_selection() {
		return __('Confirm selection', 'posti-warehouse');
	}
	
	public static function error_product_update() {
		return __('Posti error: product sync not active. Please check product SKU, price or try resave.', 'posti-warehouse');
	}
	
	public static function error_order_not_placed() {
		return __('ERROR: Unable to place order.', 'posti-warehouse');
	}
	
	public static function error_order_failed_no_shipping() {
		return __('Failed to order: Shipping method not configured.', 'posti-warehouse');
	}
	
	public static function error_generic() {
		return __('An error occurred. Please try again later.', 'posti-warehouse');
	}
	
	public static function error_empty_postcode() {
		return __('Empty postcode. Please check your address information.', 'posti-warehouse');
	}
	
	public static function error_invalid_postcode( $shipping_postcode) {
		return sprintf(
			/* translators: $shipping_postcode, not translatable */
			esc_attr__('Invalid postcode "%1$s". Please check your address information.', 'posti-warehouse'),
			esc_attr($shipping_postcode));
	}
	
	public static function error_pickup_point_not_provided() {
		return __('Please choose a pickup point.', 'posti-warehouse');
	}
	
	public static function error_pickup_point_not_found() {
		return __('No pickup points found', 'posti-warehouse');
	}
	
	public static function error_pickup_point_generic() {
		return __('Error while searching pickup points', 'posti-warehouse');
	}
	
	public static function error_api_credentials_wrong() {
		return __('Wrong credentials - access token not received!', 'posti-warehouse');
	}
	
	public static function api_credentials_correct() {
		return __('Credentials matched - access token received!', 'posti-warehouse');
	}
	
	public static function field_username() {
		return __('Username', 'posti-warehouse');
	}
	
	public static function field_password() {
		return __('Password', 'posti-warehouse');
	}
	
	public static function field_username_test() {
		return __('TEST Username', 'posti-warehouse');
	}
	
	public static function field_password_test() {
		return __('TEST Password', 'posti-warehouse');
	}
	
	public static function field_business_id() {
		return __('Business ID', 'posti-warehouse');
	}
	
	public static function field_service() {
		return __('Delivery service', 'posti-warehouse');
	}
	
	public static function field_reject_partial_orders() {
		return __('Reject partial orders', 'posti-warehouse');
	}

	public static function field_type() {
		return __('Default stock type', 'posti-warehouse');
	}
	
	public static function field_autoorder() {
		return __('Auto ordering', 'posti-warehouse');
	}
	
	public static function field_autocomplete() {
		return __('Auto mark orders as "Completed"', 'posti-warehouse');
	}
	
	public static function field_reserve_onhold() {
		return __('Reserve quantity for "On-hold" orders', 'posti-warehouse');
	}
	
	public static function field_addtracking() {
		return __('Add tracking to email', 'posti-warehouse');
	}
	
	public static function field_crontime() {
		return __('Stock and order update interval (in seconds)', 'posti-warehouse');
	}
	
	public static function field_test_mode() {
		return __('Test mode', 'posti-warehouse');
	}
	
	public static function field_field_debug() {
		return __('Debug', 'posti-warehouse');
	}
	
	public static function field_field_verbose_logging() {
		return __('Verbose logging', 'posti-warehouse');
	}
	
	public static function field_stock_sync_dttm() {
		return __('Datetime of last stock update', 'posti-warehouse');
	}
	
	public static function field_order_sync_dttm() {
		return __('Datetime of last order update', 'posti-warehouse');
	}
	
	public static function field_warehouse_settings() {
		return __('Posti Warehouse settings', 'posti-warehouse');
	}
	
	public static function field_warehouse_debug() {
		return __('Posti Warehouse Debug', 'posti-warehouse');
	}
	
	public static function type_store() {
		return __('Store', 'posti-warehouse');
	}
	
	public static function type_warehouse() {
		return __('Posti Warehouse', 'posti-warehouse');
	}
	
	public static function type_none() {
		return __('Not in stock', 'posti-warehouse');
	}
	
	public static function type_dropshipping() {
		return __('Dropshipping', 'posti-warehouse');
	}
	
	public static function feature_lq() {
		return __('LQ Process permission', 'posti-warehouse');
	}
	
	public static function feature_large() {
		return __('Large', 'posti-warehouse');
	}
	
	public static function feature_fragile() {
		return __('Fragile', 'posti-warehouse');
	}
	
	public static function logs_empty() {
		return __('No logs found', 'posti-warehouse');
	}
	
	public static function logs_title() {
		return __('Logs', 'posti-warehouse');
	}
	
	public static function logs_token_data() {
		return __('Current token:', 'posti-warehouse');
	}
	
	public static function logs_token_expiration() {
		return __('Token expiration:', 'posti-warehouse');
	}
	
	public static function column_created_date() {
		return __('Created', 'posti-warehouse');
	}
	
	public static function column_type() {
		return __('Type', 'posti-warehouse');
	}
	
	public static function column_message() {
		return __('Message', 'posti-warehouse');
	}
	
	public static function interval_every( $secs) {
		/* translators: $secs, not translatable */
		return sprintf(__('Every %1$s seconds', 'posti-warehouse'), \esc_html($secs));
	}
	
	public static function field_phone() {
		return __('Phone', 'woocommerce');
	}
	
	public static function field_email() {
		return __('Email', 'woocommerce');
	}
	
	public static function zone_name() {
		return __('Zone name', 'woocommerce');
	}
	
	public static function zone_regions() {
		return __('Zone regions', 'woocommerce');
	}
	
	public static function zone_shipping() {
		return __('Shipping method(s)', 'woocommerce');
	}
}
