<?php

/**
 * Test bootstrap for the Disable Emails Per Product regression suite.
 *
 * Loads the Composer autoloader, provisions the WordPress test bed,
 * activates WooCommerce, and activates this plugin.
 */

// Step 1: Load Composer autoloader so the Tests\ PSR-4 mapping is active.
$composer_autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($composer_autoload)) {
	echo "FATAL: Composer autoloader not found at {$composer_autoload}\n";
	exit(1);
}
require_once $composer_autoload;

// Step 2: Resolve WordPress test library path.
$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

$_tests_dir = rtrim($_tests_dir, '/\\');

// Step 3: Load WordPress test scaffolding functions.
$_tests_functions = $_tests_dir . '/includes/functions.php';
if (!file_exists($_tests_functions)) {
	echo "FATAL: WordPress test functions not found at {$_tests_functions}\n";
	echo "Run: bash tests/bin/install-wp-tests.sh wordpress_test root '' 127.0.0.1 latest\n";
	exit(1);
}
require_once $_tests_functions;

// Step 4: Register plugin activation via muplugins_loaded.
tests_add_filter(
	'muplugins_loaded',
	function () {
		// Activate WooCommerce.
		$wc_develop_dir = getenv('WC_DEVELOP_DIR');
		if ($wc_develop_dir) {
			$wc_bootstrap = rtrim($wc_develop_dir, '/\\') . '/woocommerce.php';
		} else {
			$wc_bootstrap = '/tmp/wordpress/wp-content/plugins/woocommerce/woocommerce.php';
		}

		if (file_exists($wc_bootstrap)) {
			require_once $wc_bootstrap;
		} else {
			echo "FATAL: WooCommerce bootstrap not found at {$wc_bootstrap}\n";
			exit(1);
		}

		// Activate this plugin.
		$plugin_bootstrap = dirname(__DIR__) . '/disable-emails-per-product-for-woocommerce.php';
		if (file_exists($plugin_bootstrap)) {
			require_once $plugin_bootstrap;
		} else {
			echo "FATAL: Plugin bootstrap not found at {$plugin_bootstrap}\n";
			exit(1);
		}
	}
);

// Step 5: Bootstrap the WordPress test runner.
$_bootstrap = $_tests_dir . '/includes/bootstrap.php';
if (!file_exists($_bootstrap)) {
	echo "FATAL: WordPress test bootstrap not found at {$_bootstrap}\n";
	exit(1);
}
require $_bootstrap;
