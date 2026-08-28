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

// Point wp-phpunit at the config wp-env generates. Its Composer copy otherwise looks for
// wp-tests-config.php beside itself (vendor/wp-phpunit/) and finds nothing, and the
// install.php child process it spawns needs the same file (DB credentials, ABSPATH,
// WP_TESTS_DOMAIN/EMAIL/TITLE, WP_PHP_BINARY — wp-env writes all of them there).
if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
	$kwt_tests_dir = getenv( 'WP_TESTS_DIR' );
	$kwt_candidates = array(
		getenv( 'WP_PHPUNIT__TESTS_CONFIG' ),
		$kwt_tests_dir ? $kwt_tests_dir . '/wp-tests-config.php' : null,
		'/wordpress-phpunit/wp-tests-config.php',
		dirname( __DIR__ ) . '/wp-tests-config.php',
	);
	foreach ( $kwt_candidates as $kwt_candidate ) {
		if ( $kwt_candidate && is_readable( $kwt_candidate ) ) {
			define( 'WP_TESTS_CONFIG_FILE_PATH', $kwt_candidate );
			break;
		}
	}
	unset( $kwt_tests_dir, $kwt_candidates, $kwt_candidate );
}

require_once $kwt_wp_phpunit . '/includes/functions.php';

// Load the plugin into the test WordPress before it boots.
tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/kwawingu-tours.php';
	}
);

require $kwt_wp_phpunit . '/includes/bootstrap.php';
