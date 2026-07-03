<?php
namespace KwaWingu\Tours\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class InquiryFormRenderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		require_once dirname( __DIR__, 2 ) . '/blocks/inquiry-form/render-fn.php';
		foreach ( array( 'esc_attr', 'esc_html__', 'esc_attr__', 'esc_html' ) as $f ) {
			Functions\when( $f )->returnArg();
		}
		Functions\when( '__' )->returnArg();
		Functions\when( 'wp_enqueue_script' )->justReturn( null );
	}
	protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

	public function test_renders_inquiry_form_with_required_fields(): void {
		$html = kwt_render_inquiry_form( array(), '' );
		$this->assertStringContainsString( 'kwt-inquiry', $html );
		$this->assertStringContainsString( 'name="name"', $html );
		$this->assertStringContainsString( 'name="email"', $html );
		$this->assertStringContainsString( 'name="adults"', $html );
		$this->assertStringContainsString( 'name="children"', $html );
		$this->assertStringContainsString( 'name="message"', $html );
	}

	public function test_renders_honeypot_field(): void {
		$html = kwt_render_inquiry_form( array(), '' );
		$this->assertStringContainsString( 'name="kwt_hp"', $html );
		$this->assertStringContainsString( 'kwt-hp', $html );
	}

	public function test_heading_attribute_is_rendered(): void {
		$html = kwt_render_inquiry_form( array( 'heading' => 'Ask about our safaris' ), '' );
		$this->assertStringContainsString( 'Ask about our safaris', $html );
	}

	public function test_tour_slug_applied_to_data_attribute(): void {
		$html = kwt_render_inquiry_form( array( 'tourSlug' => 'kilimanjaro-trek' ), '' );
		$this->assertStringContainsString( 'data-tour="kilimanjaro-trek"', $html );
	}

	public function test_status_container_present_for_aria_live(): void {
		$html = kwt_render_inquiry_form( array(), '' );
		$this->assertStringContainsString( 'kwt-inquiry__status', $html );
		$this->assertStringContainsString( 'aria-live="polite"', $html );
	}
}
