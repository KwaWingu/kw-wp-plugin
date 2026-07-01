<?php
namespace KwaWingu\Tours\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class BookButtonRenderTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        require_once dirname( __DIR__, 2 ) . '/blocks/book-button/render-fn.php';
        foreach ( array( 'esc_html', 'esc_attr', 'esc_url', 'esc_html__' ) as $f ) {
            Functions\when( $f )->returnArg();
        }
        Functions\when( 'get_the_ID' )->justReturn( 7 );
        Functions\when( 'get_option' )->justReturn( array( 'slug' => 'acme', 'booking_mode' => 'redirect' ) );
        Functions\when( 'get_post_meta' )->justReturn( 'safari' );
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_renders_anchor_to_hosted_booking(): void {
        $html = kwt_render_book_button( array( 'label' => 'Book now' ), '' );
        $this->assertStringContainsString( 'https://tours.kwawingu.com/acme/tours/safari', $html );
        $this->assertStringContainsString( 'Book now', $html );
        $this->assertStringContainsString( 'kwt-book-btn', $html );
    }

    public function test_renders_widget_embed_in_widget_mode(): void {
        \Brain\Monkey\Functions\when( 'get_option' )->justReturn( array( 'slug' => 'acme', 'booking_mode' => 'widget' ) );
        $html = kwt_render_book_button( array(), '' );
        $this->assertStringContainsString( 'widget.js', $html );
        $this->assertStringContainsString( 'kwt-booking-widget', $html );
    }
}
