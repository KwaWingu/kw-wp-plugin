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
        Functions\when( 'sanitize_email' )->returnArg();
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

    public function test_notification_and_lead_getters(): void {
        Functions\when( 'get_option' )->justReturn( array(
            'notify_enabled' => '1',
            'notify_email'   => 'ops@example.com',
            'capture_leads'  => '1',
        ) );
        $s = new Settings();
        $this->assertTrue( $s->notifications_enabled() );
        $this->assertSame( 'ops@example.com', $s->notification_recipient() );
        $this->assertTrue( $s->lead_capture_enabled() );

        Functions\when( 'get_option' )->justReturn( array() );
        $s2 = new Settings();
        $this->assertFalse( $s2->notifications_enabled() );
        $this->assertSame( '', $s2->notification_recipient() );
        $this->assertFalse( $s2->lead_capture_enabled() );
    }

    public function test_sanitize_notification_fields(): void {
        $out = ( new Settings() )->sanitize( array(
            'notify_enabled' => 'on',
            'notify_email'   => ' ops@example.com ',
            'capture_leads'  => '',
        ) );
        $this->assertSame( '1', $out['notify_enabled'] );
        $this->assertSame( 'ops@example.com', $out['notify_email'] );
        $this->assertSame( '', $out['capture_leads'] );
    }
}
