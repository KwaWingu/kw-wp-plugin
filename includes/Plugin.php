<?php
/**
 * Plugin bootstrap: root container that instantiates and boots all subsystems.
 *
 * @package KwaWingu\Tours
 */

namespace KwaWingu\Tours;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Root container. Instantiates and boots subsystems.
 */
final class Plugin {

	const VERSION = '1.9.0';

	/**
	 * Singleton instance of this class.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether the plugin has already been booted.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Returns (and lazily creates) the singleton Plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wires up all subsystems. Safe to call more than once.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$settings = new Settings();
		$settings->register();

		( new Cpt() )->register();

		( new Blocks() )->register();

		( new Shortcodes() )->register();

		( new Patterns() )->register();

		$api   = new Api_Client( $settings );
		$media = new Media( $settings );
		$sync  = new Sync( $api, $media );

		$branding = new Branding( $api );
		$branding->register();

		( new Seo() )->register();

		( new Rest_Proxy( $api, new Notifications( $settings ) ) )->register();

		( new Assets() )->register();

		$importer = new Importer();
		( new Setup_Wizard( $settings, $branding, $importer, $sync ) )->register();

		$controller = new Sync_Controller( $sync, $settings );
		$controller->register();

		( new Admin_Page( $settings, $controller ) )->register();
	}
}
