<?php
/*
 * Plugin Name: Disable WooCommerce Emails Per Product
 * Description: Disable WooCommerce emails per product or Order.
 * Version: 1.0.0
 * Author: Nagdy
 * Author URI: https://nagdy.me
 * Text Domain: dwepp
 * Domain Path: /languages
 * License: GPL3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DWEPP_VERSION', '1.0.0' );
define( 'DWEPP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DWEPP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DWEPP_PREFIX', 'dwepp' );

require_once 'vendor/autoload.php';

new \DisableWoocommerceEmailsPerProduct\Admin();
new \DisableWoocommerceEmailsPerProduct\Core();

/**
 * Declare compatibility with WooCommerce Custom Order Tables.
 */
add_action(
    'before_woocommerce_init',
    function () {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    }
);
