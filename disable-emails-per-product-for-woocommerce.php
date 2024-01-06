<?php
/**
 * Plugin Name: Disable Emails Per Product for WooCommerce
 * Description: Disable emails per product or Order.
 * Version: 1.0.0
 * Author: Nagdy
 * Author URI: https://nagdy.me
 * Contributors: Nagdy
 * Text Domain: disable-emails-per-product-for-woocommerce
 * Domain Path: /languages
 * License: GPL3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 */

use DisableEmailsPerProductForWooCommerce\Admin;
use DisableEmailsPerProductForWooCommerce\Core;
use DisableEmailsPerProductForWooCommerce\GlobalView;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
const DEPPWC_PREFIX = 'deppwc';

require_once 'vendor/autoload.php';

new Admin();
new Core();
add_action( 'after_setup_theme', function () {
	if ( ! apply_filters( 'dwepp_disable_global_view', false ) ) {
		new GlobalView();
	}
} );

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
