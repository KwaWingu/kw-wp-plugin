<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Booking;
use PHPUnit\Framework\TestCase;

class BookingTest extends TestCase {
    protected function setUp(): void { parent::setUp(); Monkey\setUp(); }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_redirect_url_built_from_slugs(): void {
        Functions\when( 'get_option' )->justReturn( array( 'slug' => 'serengeti-tours', 'booking_mode' => 'redirect' ) );
        Functions\when( 'get_post_meta' )->justReturn( 'kilimanjaro' );
        $this->assertSame(
            'https://tours.kwawingu.com/serengeti-tours/tours/kilimanjaro',
            Booking::url_for( 7 )
        );
    }

    public function test_returns_empty_when_slug_missing(): void {
        Functions\when( 'get_option' )->justReturn( array( 'slug' => '' ) );
        Functions\when( 'get_post_meta' )->justReturn( 'kilimanjaro' );
        $this->assertSame( '', Booking::url_for( 7 ) );
    }

    public function test_widget_embed_contains_operator_and_tour(): void {
        \Brain\Monkey\Functions\when( 'get_option' )->justReturn( array( 'slug' => 'acme', 'booking_mode' => 'widget' ) );
        \Brain\Monkey\Functions\when( 'get_post_meta' )->justReturn( 'safari' );
        \Brain\Monkey\Functions\when( 'esc_url' )->returnArg();
        \Brain\Monkey\Functions\when( 'esc_attr' )->returnArg();
        $embed = \KwaWingu\Tours\Booking::widget_embed_for( 7 );
        $this->assertStringContainsString( 'widget.js', $embed );
        $this->assertStringContainsString( 'data-operator="acme"', $embed );
        $this->assertStringContainsString( 'data-tour="safari"', $embed );
    }
}
