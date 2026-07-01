<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Settings;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        // WP sanitizers used by Settings::sanitize().
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'sanitize_key' )->alias( static function ( $k ) {
            return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $k ) );
        } );
        Functions\when( 'esc_url_raw' )->returnArg();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_sanitize_trims_and_defaults(): void {
        $settings = new Settings();
        $out = $settings->sanitize( array(
            'slug'          => '  Serengeti-Tours  ',
            'public_key'    => ' kw_live_abc ',
            'private_key'   => ' kw_live_secret ',
            'sync_interval' => 'weekly',        // invalid -> falls back
            'media_mode'    => 'bogus',         // invalid -> falls back
            'booking_mode'  => 'onsite',
        ) );

        $this->assertSame( 'serengeti-tours', $out['slug'] );      // slugified
        $this->assertSame( 'kw_live_abc', $out['public_key'] );    // trimmed
        $this->assertSame( 'kw_live_secret', $out['private_key'] );
        $this->assertSame( 'hourly', $out['sync_interval'] );      // invalid -> default
        $this->assertSame( 'sideload', $out['media_mode'] );       // invalid -> default
        $this->assertSame( 'onsite', $out['booking_mode'] );
    }

    public function test_getters_read_option_with_defaults(): void {
        Functions\when( 'get_option' )->justReturn( array( 'slug' => 'acme' ) );
        $settings = new Settings();
        $this->assertSame( 'acme', $settings->get_slug() );
        $this->assertSame( '', $settings->get_public_key() );      // missing -> ''
        $this->assertSame( 'redirect', $settings->get_booking_mode() ); // missing -> default
    }
}
