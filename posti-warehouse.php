<?php

/**
 * Plugin Name: Posti Warehouse
 * Version: 2.1.2
 * Description: Provides integration to Posti warehouse and dropshipping services.
 * Author: Posti
 * Author URI: https://www.posti.fi/
 * Text Domain: posti-warehouse
 * Domain Path: /languages/
 * License: GPL v3 or later
 *
 * WC requires at least: 3.4
 * WC tested up to: 6.2.2
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Woo_Posti_Warehouse;

// Prevent direct access to this script
defined('ABSPATH') || exit;

define( '__POSTI_WH_FILE__', __FILE__ );

require_once __DIR__ . '/classes/class-text.php';
require_once __DIR__ . '/classes/class-settings.php';
require_once __DIR__ . '/classes/class-order.php';
require_once __DIR__ . '/classes/class-metabox.php';
require_once __DIR__ . '/classes/class-api.php';
require_once __DIR__ . '/classes/class-core.php';
require_once __DIR__ . '/classes/class-logger.php';
require_once __DIR__ . '/classes/class-debug.php';
require_once __DIR__ . '/classes/class-product.php';
require_once __DIR__ . '/classes/class-dataset.php';
require_once __DIR__ . '/classes/class-shipping.php';
require_once __DIR__ . '/classes/class-frontend.php';

use Woo_Posti_Warehouse\Core;

new Core();
