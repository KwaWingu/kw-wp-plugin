<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Assets;
use PHPUnit\Framework\TestCase;

class AssetsTest extends TestCase {
    protected function setUp(): void { parent::setUp(); Monkey\setUp(); }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_register_hooks_enqueue(): void {
        ( new Assets() )->register();
        $this->assertNotFalse( has_action( 'wp_enqueue_scripts' ) );
    }

    public function test_enqueue_registers_and_localizes_proxy(): void {
        Functions\when( 'plugins_url' )->justReturn( 'https://site/wp-content/plugins/kwawingu-tours/assets/js/kwt-proxy.js' );
        Functions\when( 'rest_url' )->justReturn( 'https://site/wp-json/kwawingu/v1' );
        Functions\when( 'wp_create_nonce' )->justReturn( 'abc123' );
        Functions\when( 'get_option' )->justReturn( array( 'slug' => 'acme' ) );
        Functions\when( '__' )->returnArg();
        $registered = array();
        Functions\when( 'wp_register_script' )->alias( static function ( $h ) use ( &$registered ) { $registered[] = $h; return true; } );
        $localized = array();
        Functions\when( 'wp_localize_script' )->alias( static function ( $h, $obj, $data ) use ( &$localized ) { $localized = array( $h, $obj, $data ); return true; } );
        $styles = array();
        Functions\when( 'wp_register_style' )->alias( static function ( $h ) use ( &$styles ) { $styles['reg'] = $h; return true; } );
        Functions\when( 'wp_enqueue_style' )->alias( static function ( $h ) use ( &$styles ) { $styles['enq'] = $h; return true; } );

        ( new Assets() )->enqueue();

        $this->assertContains( 'kwt-proxy', $registered );
        $this->assertSame( 'kwtProxy', $localized[1] );
        $this->assertSame( 'abc123', $localized[2]['nonce'] );
        $this->assertSame( 'acme', $localized[2]['slug'] );
        $this->assertSame( 'kwt-blocks', $styles['reg'] );
        $this->assertSame( 'kwt-blocks', $styles['enq'] );
    }

    /**
     * `--kwt-primary: var( --kwt-primary, … )` is a self-reference — a cycle the CSS spec makes
     * invalid at computed-value time — so every button was white-on-transparent and the brand
     * colour never reached a block. The defaults must be plain values, on a selector Branding's
     * `:root{}` outranks.
     */
    public function test_block_css_declares_brand_defaults_without_self_referencing_custom_properties(): void {
        $css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/kwt-blocks.css' );
        $css = (string) preg_replace( '#/\*.*?\*/#s', '', $css ); // The header comment quotes the broken form.

        $this->assertDoesNotMatchRegularExpression( '/--kwt-(primary|accent)\s*:\s*var\(\s*--kwt-\1/', $css );
        $this->assertMatchesRegularExpression( '/html\s*\{[^}]*--kwt-primary:\s*#0a4a3a;[^}]*--kwt-accent:\s*#e8920a;/s', $css );
    }

    /**
     * The inquiry form shipped with no styles and a visible honeypot — a person who filled the
     * stray box in had their inquiry silently dropped as a bot.
     */
    public function test_block_css_styles_the_inquiry_form_and_hides_the_honeypot(): void {
        $css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/kwt-blocks.css' );

        $this->assertMatchesRegularExpression( '/\.kwt-inquiry\s*\{/', $css );
        $this->assertMatchesRegularExpression( '/\.kwt-hp\s*\{[^}]*(position:\s*absolute;[^}]*left:\s*-9999px|display:\s*none)/s', $css );
    }
}
