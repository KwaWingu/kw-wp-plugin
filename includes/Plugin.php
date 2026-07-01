<?php
namespace KwaWingu\Tours;

/**
 * Root container. Instantiates and boots subsystems.
 */
final class Plugin {

    const VERSION = '0.3.0';

    /** @var Plugin|null */
    private static $instance = null;

    /** @var bool */
    private $booted = false;

    public static function instance(): Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Wire up subsystems. Safe to call more than once. */
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

        $api      = new Api_Client( $settings );
        $media    = new Media( $settings );
        $sync     = new Sync( $api, $media );

        $branding = new Branding( $api );
        $branding->register();

        ( new Seo() )->register();

        ( new Rest_Proxy( $api ) )->register();

        $importer = new Importer();
        ( new Setup_Wizard( $settings, $branding, $importer, $sync ) )->register();

        $controller = new Sync_Controller( $sync, $settings );
        $controller->register();

        ( new Admin_Page( $settings, $controller ) )->register();
    }
}
