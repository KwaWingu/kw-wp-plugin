<?php
namespace KwaWingu\Tours\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class AvailabilityCalendarRenderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		require_once dirname( __DIR__, 2 ) . '/blocks/availability-calendar/render-fn.php';
		foreach ( array( 'esc_attr', 'esc_html__' ) as $f ) {
			Functions\when( $f )->returnArg();
		}
		Functions\when( 'wp_enqueue_script' )->justReturn( null );
		Functions\when( 'get_the_ID' )->justReturn( 7 );
		Functions\when( 'get_post_meta' )->justReturn( 'safari' );
	}
	protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

	public function test_renders_calendar_shell(): void {
		$html = kwt_render_availability_calendar( array(), '' );
		$this->assertStringContainsString( 'kwt-availcal', $html );
		$this->assertStringContainsString( 'data-tour="safari"', $html );
		$this->assertStringContainsString( 'kwt-availcal__grid', $html );
	}
}
