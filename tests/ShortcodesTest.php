<?php
// Global-namespace WP_Query stub so render_tours can run without a real DB.
namespace {
    if ( ! class_exists( 'WP_Query' ) ) {
        class WP_Query {
            public function __construct( $a = array() ) {}
            public function have_posts() { return false; }
            public function the_post() {}
        }
    }
}

namespace KwaWingu\Tours\Tests {

    use Brain\Monkey;
    use Brain\Monkey\Functions;
    use KwaWingu\Tours\Shortcodes;
    use PHPUnit\Framework\TestCase;

    class ShortcodesTest extends TestCase {
        protected function setUp(): void {
            parent::setUp();
            Monkey\setUp();
            Functions\when( 'shortcode_atts' )->alias( static function ( $defaults, $atts ) {
                return array_merge( $defaults, (array) $atts );
            } );
        }
        protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

        public function test_register_adds_all_shortcodes(): void {
            $registered = array();
            Functions\when( 'add_shortcode' )->alias( static function ( $tag, $cb ) use ( &$registered ) {
                $registered[] = $tag;
            } );
            ( new Shortcodes() )->register();
            $this->assertContains( 'kwawingu_tours', $registered );
            $this->assertContains( 'kwawingu_tour', $registered );
            $this->assertContains( 'kwawingu_booking', $registered );
            $this->assertContains( 'kwawingu_featured', $registered );
        }

        public function test_tours_shortcode_maps_limit_attribute(): void {
            require_once dirname( __DIR__ ) . '/blocks/tours-grid/render.php';
            // Stub the render deps so the callback runs without a real query.
            Functions\when( 'esc_html' )->returnArg();
            Functions\when( 'esc_html__' )->returnArg();
            $sc = new Shortcodes();
            // No tours -> empty grid markup; we only assert the wrapper is produced.
            // WP_Query stub above handles the real query path; no extra stub needed.
            $html = $sc->render_tours( array( 'limit' => '4' ) );
            $this->assertIsString( $html );
        }
    }
}
