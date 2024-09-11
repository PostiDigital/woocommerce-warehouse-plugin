<?php

/**
 * Plugin Name: Posti Warehouse
 * Version: 3.0.1
 * Description: Provides integration to Posti warehouse and dropshipping services.
 * Author: Posti
 * Author URI: https://www.posti.fi/
 * Text Domain: posti-warehouse
 * Domain Path: /languages/
 * License: GPL v3 or later
 *
 * WC requires at least: 5.0
 * WC tested up to: 6.4.2
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Posti_Warehouse;

// Prevent direct access to this script
defined('ABSPATH') || exit;

define( 'POSTI_WH_FILE__', __FILE__ );

require_once __DIR__ . '/classes/class-text.php';
require_once __DIR__ . '/classes/class-settings.php';
require_once __DIR__ . '/classes/class-order.php';
require_once __DIR__ . '/classes/class-metabox.php';
require_once __DIR__ . '/classes/class-api.php';
require_once __DIR__ . '/classes/class-service.php';
require_once __DIR__ . '/classes/class-core.php';
require_once __DIR__ . '/classes/class-logger.php';
require_once __DIR__ . '/classes/class-debug.php';
require_once __DIR__ . '/classes/class-product.php';
require_once __DIR__ . '/classes/class-dataset.php';
require_once __DIR__ . '/classes/class-shipping.php';
require_once __DIR__ . '/classes/class-frontend.php';

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
});

use Posti_Warehouse\Posti_Warehouse_Core;

$core = new Posti_Warehouse_Core();

function posti_wh_get_products_manager() {
	global $core;
	return $core->get_product_manager();
}

function posti_wh_sync_products($product_ids) {
	$pm = posti_wh_get_products_manager();
	return $pm->sync_products($product_ids);
}