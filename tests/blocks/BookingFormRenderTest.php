<?php
namespace KwaWingu\Tours\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class BookingFormRenderTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        require_once dirname( __DIR__, 2 ) . '/blocks/booking/render-fn.php';
        foreach ( array( 'esc_attr', 'esc_attr__', 'esc_html__' ) as $f ) { Functions\when( $f )->returnArg(); }
        Functions\when( 'wp_enqueue_script' )->justReturn( null );
        Functions\when( 'get_the_ID' )->justReturn( 7 );
        Functions\when( 'get_post_meta' )->justReturn( 'safari' );
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_renders_booking_form_with_fields(): void {
        $html = kwt_render_booking_form( array(), '' );
        $this->assertStringContainsString( 'id="kwt-book"', $html );
        $this->assertStringContainsString( 'name="firstName"', $html );
        $this->assertStringContainsString( 'name="lastName"', $html );
        $this->assertStringContainsString( 'name="email"', $html );
        $this->assertStringContainsString( 'name="phone"', $html );
        $this->assertStringContainsString( 'name="adults"', $html );
        $this->assertStringContainsString( 'kwt-booking__departure', $html );
        $this->assertStringContainsString( 'kwt-booking__price', $html );
        $this->assertStringContainsString( 'kwt-booking__status', $html );
    }
}
