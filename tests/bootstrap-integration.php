<?php
/**
 * PHPUnit bootstrap for WordPress integration tests (wp-env).
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

$root = dirname( __DIR__ );
require_once $root . '/vendor/autoload.php';

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! is_readable( $_tests_dir . '/includes/functions.php' ) ) {
	echo "WP test library not found at {$_tests_dir}. Run: npx @wordpress/env start\n";
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Load plugin before WP test bootstrap.
 */
function _simplevpbot_tests_load_plugin() {
	require dirname( __DIR__ ) . '/simplevpbot.php';
}

tests_add_filter( 'muplugins_loaded', '_simplevpbot_tests_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
