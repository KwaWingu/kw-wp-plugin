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

// wp-env's generated test config carries the DB constants but not these four, and
// wp-phpunit's bootstrap refuses to start without them ("The following required
// constants are not defined: WP_TESTS_DOMAIN, WP_TESTS_EMAIL, WP_TESTS_TITLE,
// WP_PHP_BINARY"). Defaults only — a config that does define them wins.
$kwt_test_defaults = array(
	'WP_TESTS_DOMAIN' => 'localhost',
	'WP_TESTS_EMAIL'  => 'admin@example.org',
	'WP_TESTS_TITLE'  => 'KwaWingu Tours Test Site',
	'WP_PHP_BINARY'   => 'php',
);
foreach ( $kwt_test_defaults as $kwt_const => $kwt_value ) {
	if ( ! defined( $kwt_const ) ) {
		define( $kwt_const, $kwt_value );
	}
}
unset( $kwt_test_defaults, $kwt_const, $kwt_value );

require_once $kwt_wp_phpunit . '/includes/functions.php';

// Load the plugin into the test WordPress before it boots.
tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/kwawingu-tours.php';
	}
);

require $kwt_wp_phpunit . '/includes/bootstrap.php';
