<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Api_Client;
use KwaWingu\Tours\Branding;
use Mockery;
use PHPUnit\Framework\TestCase;

class BrandingTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'esc_url_raw' )->returnArg();
        Functions\when( 'sanitize_hex_color' )->returnArg();
    }
    protected function tearDown(): void { Monkey\tearDown(); Mockery::close(); parent::tearDown(); }

    public function test_apply_stores_brand_from_profile(): void {
        $api = Mockery::mock( Api_Client::class );
        $api->shouldReceive( 'get' )->with( '/profile' )->andReturn( array(
            'name' => 'Serengeti Tours', 'logoUrl' => 'https://img/logo.png',
            'brandPrimary' => '#0a4a3a', 'brandAccent' => '#e8920a', 'description' => 'Safaris.',
        ) );
        $stored = array();
        Functions\when( 'update_option' )->alias( static function ( $k, $v ) use ( &$stored ) { $stored[ $k ] = $v; return true; } );

        $brand = ( new Branding( $api ) )->apply();
        $this->assertSame( 'Serengeti Tours', $brand['name'] );
        $this->assertSame( '#0a4a3a', $brand['primary'] );
        $this->assertSame( '#0a4a3a', $stored['kwawingu_tours_brand']['primary'] );
    }

    public function test_apply_returns_empty_on_api_error(): void {
        $api = Mockery::mock( Api_Client::class );
        $api->shouldReceive( 'get' )->andThrow( new \KwaWingu\Tours\Api_Exception( 'fail', 500 ) );
        $this->assertSame( array(), ( new Branding( $api ) )->apply() );
    }

    public function test_css_vars_emits_custom_properties_without_a_style_tag(): void {
        Functions\when( 'get_option' )->justReturn( array( 'primary' => '#0a4a3a', 'accent' => '#e8920a' ) );
        $css = ( new Branding( Mockery::mock( Api_Client::class ) ) )->css_vars();
        $this->assertStringContainsString( '--kwt-primary:#0a4a3a', $css );
        $this->assertStringContainsString( '--kwt-accent:#e8920a', $css );
        // The CSS goes through wp_add_inline_style; a literal <style> tag would be double-wrapped.
        $this->assertStringNotContainsString( '<style', $css );
    }

    public function test_enqueue_attaches_the_brand_css_to_the_block_stylesheet_handle(): void {
        Functions\when( 'get_option' )->justReturn( array( 'primary' => '#0a4a3a', 'accent' => '' ) );
        $attached = array();
        Functions\when( 'wp_add_inline_style' )->alias( static function ( $handle, $css ) use ( &$attached ) {
            $attached[ $handle ] = $css;
            return true;
        } );

        ( new Branding( Mockery::mock( Api_Client::class ) ) )->enqueue();

        $this->assertArrayHasKey( \KwaWingu\Tours\Assets::STYLE_HANDLE, $attached );
        $this->assertStringContainsString( '--kwt-primary:#0a4a3a', $attached[ \KwaWingu\Tours\Assets::STYLE_HANDLE ] );
    }

    public function test_enqueue_adds_nothing_when_no_brand_colours_are_stored(): void {
        Functions\when( 'get_option' )->justReturn( array() );
        $called = false;
        Functions\when( 'wp_add_inline_style' )->alias( static function () use ( &$called ) {
            $called = true;
            return true;
        } );

        ( new Branding( Mockery::mock( Api_Client::class ) ) )->enqueue();

        $this->assertFalse( $called );
    }

    public function test_register_hooks_wp_enqueue_scripts_not_wp_head(): void {
        ( new Branding( Mockery::mock( Api_Client::class ) ) )->register();
        $this->assertNotFalse( has_action( 'wp_enqueue_scripts' ) );
        $this->assertFalse( has_action( 'wp_head' ) );
    }
}
