<?php
/**
 * Plugin Name:       KwaWingu Tours
 * Plugin URI:        https://github.com/KwaWingu/kw-wp-plugin
 * Description:       Build a tour-operator website fast on your KwaWingu Tours data — sync your catalog into WordPress, add blocks, and go live in minutes.
 * Version:           1.14.3
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            KwaWingu Tours
 * Author URI:        https://kwawingu.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kwawingu-tours
 * Domain Path:       /languages
 *
 * @package KwaWingu\Tours
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KWAWINGU_TOURS_VERSION', '1.14.3' );
define( 'KWAWINGU_TOURS_PLUGIN_FILE', __FILE__ );
define( 'KWAWINGU_TOURS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KWAWINGU_TOURS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
// Where KwaWingu is. Override both in wp-config.php to point a site at a staging or
// local KwaWingu (e.g. define( 'KWAWINGU_TOURS_SITE_BASE', 'http://host.docker.internal:8085' );).
// KWAWINGU_TOURS_SITE_BASE is the hosted booking pages and dashboard; KWAWINGU_TOURS_API_BASE is the
// Developer API root and defaults to KWAWINGU_TOURS_SITE_BASE . '/api/v1'.
if ( ! defined( 'KWAWINGU_TOURS_SITE_BASE' ) ) {
	define( 'KWAWINGU_TOURS_SITE_BASE', 'https://tours.kwawingu.com' );
}
if ( ! defined( 'KWAWINGU_TOURS_API_BASE' ) ) {
	define( 'KWAWINGU_TOURS_API_BASE', rtrim( KWAWINGU_TOURS_SITE_BASE, '/' ) . '/api/v1' );
}

// The plugin has no runtime dependencies: everything under includes/ is its own
// code, mapped PSR-4 from the KwaWingu\Tours namespace. The release zip therefore
// ships no vendor/ directory; a development checkout also has Composer's autoloader
// (for the test suite), which is loaded when present.
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'KwaWingu\\Tours\\';
		if ( 0 !== strncmp( $class_name, $prefix, strlen( $prefix ) ) ) {
			return;
		}
		$file = KWAWINGU_TOURS_PLUGIN_DIR . 'includes/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_file( $file ) ) {
			require $file;
		}
	}
);
$kwawingu_tours_autoload = KWAWINGU_TOURS_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $kwawingu_tours_autoload ) ) {
	require $kwawingu_tours_autoload;
}

add_action(
	'plugins_loaded',
	static function () {
		\KwaWingu\Tours\Plugin::instance()->boot();
	}
);

register_activation_hook(
	__FILE__,
	static function () {
		// CPTs must exist before flushing so their rewrite rules register.
		\KwaWingu\Tours\Plugin::instance()->boot();
		// The push endpoint is unusable — and returns 503 — while the secret is empty,
		// so it is generated here rather than left for the operator to remember.
		( new \KwaWingu\Tours\Settings() )->ensure_push_secret();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( 'kwawingu_tours_sync_cron' );
		wp_clear_scheduled_hook( 'kwawingu_tours_sync_push' );
		flush_rewrite_rules();
	}
);
