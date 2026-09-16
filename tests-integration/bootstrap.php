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

$kwawingu_tours_wp_phpunit = getenv( 'WP_PHPUNIT__DIR' );
if ( ! $kwawingu_tours_wp_phpunit ) {
	$kwawingu_tours_wp_phpunit = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';
}

require_once dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

// Point wp-phpunit at the config wp-env generates. Its Composer copy otherwise looks for
// wp-tests-config.php beside itself (vendor/wp-phpunit/) and finds nothing, and the
// install.php child process it spawns needs the same file (DB credentials, ABSPATH,
// WP_TESTS_DOMAIN/EMAIL/TITLE, WP_PHP_BINARY — wp-env writes all of them there).
if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
	$kwawingu_tours_tests_dir = getenv( 'WP_TESTS_DIR' );
	$kwawingu_tours_candidates = array(
		getenv( 'WP_PHPUNIT__TESTS_CONFIG' ),
		$kwawingu_tours_tests_dir ? $kwawingu_tours_tests_dir . '/wp-tests-config.php' : null,
		'/wordpress-phpunit/wp-tests-config.php',
		dirname( __DIR__ ) . '/wp-tests-config.php',
	);
	foreach ( $kwawingu_tours_candidates as $kwawingu_tours_candidate ) {
		if ( $kwawingu_tours_candidate && is_readable( $kwawingu_tours_candidate ) ) {
			define( 'WP_TESTS_CONFIG_FILE_PATH', $kwawingu_tours_candidate );
			break;
		}
	}
	unset( $kwawingu_tours_tests_dir, $kwawingu_tours_candidates, $kwawingu_tours_candidate );
}

require_once $kwawingu_tours_wp_phpunit . '/includes/functions.php';

// Load the plugin into the test WordPress before it boots.
tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/kwawingu-tours.php';
	}
);

require $kwawingu_tours_wp_phpunit . '/includes/bootstrap.php';
