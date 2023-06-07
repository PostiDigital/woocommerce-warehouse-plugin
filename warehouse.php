<?php

/**
 * Plugin Name: Posti Warehouse
 * Version: 2.0.2
 * Description: Provides integration to Posti warehouse and dropshipping services.
 * Author: Posti
 * Author URI: https://www.posti.fi/
 * Text Domain: posti-warehouse
 * Domain Path: /languages/
 * License: GPL v3 or later
 *
 * WC requires at least: 3.4
 * WC tested up to: 4.1
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace PostiWarehouse;
// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit;
}

define( '__POSTI_WH_FILE__', __FILE__ );

require_once __DIR__ . '/Classes/Settings.php';
require_once __DIR__ . '/Classes/Order.php';
require_once __DIR__ . '/Classes/Metabox.php';
require_once __DIR__ . '/Classes/Api.php';
require_once __DIR__ . '/Classes/Core.php';
require_once __DIR__ . '/Classes/Logger.php';
require_once __DIR__ . '/Classes/Debug.php';
require_once __DIR__ . '/Classes/Product.php';
require_once __DIR__ . '/Classes/Dataset.php';
require_once __DIR__ . '/Classes/Shipping.php';
require_once __DIR__ . '/Classes/Frontend.php';

use PostiWarehouse\Classes\Core;

new Core();



