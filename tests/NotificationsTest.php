<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Notifications;
use KwaWingu\Tours\Settings;
use PHPUnit\Framework\TestCase;

class NotificationsTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'wp_strip_all_tags' )->returnArg();
		Functions\when( '__' )->returnArg();
	}
	protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

	private function payload(): array {
		return array( 'guestFirstName' => 'Jane', 'guestLastName' => 'Doe', 'guestEmail' => 'j@x.com', 'guestPhone' => '255700', 'tourSlug' => 'safari' );
	}

	public function test_captures_lead_and_emails_when_enabled(): void {
		Functions\when( 'get_option' )->justReturn( array( 'notify_enabled' => '1', 'notify_email' => 'ops@x.com', 'capture_leads' => '1' ) );
		$inserted = array();
		Functions\when( 'wp_insert_post' )->alias( static function ( $args ) use ( &$inserted ) { $inserted[] = $args; return 99; } );
		Functions\when( 'update_post_meta' )->justReturn( true );
		$mail = array();
		Functions\when( 'wp_mail' )->alias( static function ( $to, $subj, $body ) use ( &$mail ) { $mail = array( $to, $subj, $body ); return true; } );

		( new Notifications( new Settings() ) )->on_booking_created( $this->payload(), array( 'booking' => array( 'ref' => 'KWG-1' ) ) );

		$this->assertNotEmpty( $inserted );
		$this->assertStringContainsString( 'Jane', $inserted[0]['post_title'] );
		$this->assertSame( 'ops@x.com', $mail[0] );
		$this->assertStringContainsString( 'KWG-1', $mail[2] );
	}

	public function test_does_nothing_when_disabled(): void {
		Functions\when( 'get_option' )->justReturn( array() ); // both off
		Functions\expect( 'wp_insert_post' )->never();
		Functions\expect( 'wp_mail' )->never();
		( new Notifications( new Settings() ) )->on_booking_created( $this->payload(), array() );
		$this->assertTrue( true );
	}

	public function test_falls_back_to_admin_email(): void {
		Functions\when( 'get_option' )->alias( static function ( $key ) {
			if ( 'admin_email' === $key ) { return 'admin@site.com'; }
			return array( 'notify_enabled' => '1', 'notify_email' => '', 'capture_leads' => '' );
		} );
		$mail = array();
		Functions\when( 'wp_mail' )->alias( static function ( $to ) use ( &$mail ) { $mail[] = $to; return true; } );
		( new Notifications( new Settings() ) )->on_booking_created( $this->payload(), array() );
		$this->assertSame( array( 'admin@site.com' ), $mail );
	}
}
