<?php
/**
 * Bootstrap for the wp-env / WordPress integration test suite.
 *
 * Runs against a REAL WordPress (via the WP PHPUnit test library). Requires
 * Docker (wp-env) — this suite is CI-only; it does not run in the constrained
 * dev sandbox. See docs/testing.md.
 *
 * @package KwaWingu\Tours
 */

$kwt_wp_phpunit = getenv( 'WP_PHPUNIT__DIR' );
if ( ! $kwt_wp_phpunit ) {
	$kwt_wp_phpunit = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';
}

require_once dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
require_once $kwt_wp_phpunit . '/includes/functions.php';

// Load the plugin into the test WordPress before it boots.
tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/kwawingu-tours.php';
	}
);

require $kwt_wp_phpunit . '/includes/bootstrap.php';
