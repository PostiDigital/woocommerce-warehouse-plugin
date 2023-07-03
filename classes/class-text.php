<?php

namespace Woo_Posti_Warehouse;

defined('ABSPATH') || exit;

class Text {

    public static $namespace = 'posti-warehouse';

    public static function pickup_point_title() {
        return __( 'Pickup point', self::$namespace);
    }
    
    public static function pickup_point_select() {
        return __('Select a pickup point', self::$namespace);
    }
    
    public static function pickup_point_other() {
        return __('Other', self::$namespace);
    }
    
    public static function order_not_placed() {
        return __('Order not placed', self::$namespace);
    }
    
    public static function order_failed() {
        return __('Failed to order.', self::$namespace);
    }
    
    public static function tracking_title() {
        return __('Posti API Tracking', self::$namespace);
    }
    
    public static function tracking_number($number) {
        return sprintf(__('Tracking number: %1$s', self::$namespace), \esc_html($number));
    }
    
    public static function column_warehouse() {
        return __('Warehouse', self::$namespace);
    }
    
    public static function action_publish_to_warehouse() {
        return __( 'Publish to warehouse (Posti)', self::$namespace);
    }
    
    public static function action_remove_from_warehouse() {
        return __( 'Remove from warehouse (Posti)', self::$namespace);
    }
    
    public static function field_ean() {
        return __('EAN / ISBN / Barcode', self::$namespace);
    }
    
    public static function field_ean_caption() {
        return __('Enter EAN / ISBN / Barcode', self::$namespace);
    }
    
    public static function field_price() {
        return __('Wholesale price', self::$namespace);
    }
    
    public static function field_price_caption() {
        return __('Enter wholesale price', self::$namespace);
    }
    
    public static function field_stock_type() {
        return __('Stock type', self::$namespace);
    }
    
    public static function field_warehouse() {
        return __('Warehouse', self::$namespace);
    }
    
    public static function field_distributor() {
        return __('Distributor ID', self::$namespace);
    }
    
    public static function confirm_selection() {
        return __('Confirm selection', self::$namespace);
    }
    
    public static function error_product_update() {
        return __('Posti error: product sync not active. Please check product SKU, price or try resave.', self::$namespace);
    }
    
    public static function error_order_not_placed() {
        return __('ERROR: Unable to place order.', self::$namespace);
    }
    
    public static function error_order_failed_no_shipping() {
        return __('Failed to order: Shipping method not configured.', self::$namespace);
    }
    
    public static function error_generic() {
        return __('An error occurred. Please try again later.', self::$namespace);
    }
    
    public static function error_empty_postcode() {
        return __('Empty postcode. Please check your address information.', self::$namespace);
    }
    
    public static function error_invalid_postcode($shipping_postcode) {
        return sprintf(
            esc_attr__('Invalid postcode "%1$s". Please check your address information.', self::$namespace),
            esc_attr($shipping_postcode));
    }
    
    public static function error_pickup_point_not_provided() {
        return __('Please choose a pickup point.', self::$namespace);
    }
    
    public static function error_pickup_point_not_found() {
        return __('No pickup points found', self::$namespace);
    }
    
    public static function error_pickup_point_generic() {
        return __('Error while searching pickup points', self::$namespace);
    }
}
