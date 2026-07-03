<?php
/**
 * Plugin Name:       KwaWingu Tours
 * Plugin URI:        https://tours.kwawingu.com
 * Description:        Build a tour-operator website fast on your KwaWingu Tours data — sync your catalog into WordPress, add blocks, and go live in minutes.
 * Version:           1.11.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            KwaWingu Tours
 * Author URI:        https://tours.kwawingu.com
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

define( 'KWT_VERSION', '1.11.0' );
define( 'KWT_PLUGIN_FILE', __FILE__ );
define( 'KWT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KWT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
if ( ! defined( 'KWT_API_BASE' ) ) {
	define( 'KWT_API_BASE', 'https://tours.kwawingu.com/api/v1' );
}

$kwt_autoload = KWT_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $kwt_autoload ) ) {
	require $kwt_autoload;
}

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'kwawingu-tours', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		\KwaWingu\Tours\Plugin::instance()->boot();
	}
);

register_activation_hook(
	__FILE__,
	static function () {
		// CPTs must exist before flushing so their rewrite rules register.
		\KwaWingu\Tours\Plugin::instance()->boot();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( 'kwt_sync_cron' );
		flush_rewrite_rules();
	}
);
