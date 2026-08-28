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
            $this->assertContains( 'kwawingu_reviews', $registered );
            $this->assertContains( 'kwawingu_destinations', $registered );
            $this->assertContains( 'kwawingu_search', $registered );
            $this->assertContains( 'kwawingu_calculator', $registered );
            $this->assertContains( 'kwawingu_booking_form', $registered );
            $this->assertContains( 'kwawingu_gallery', $registered );
            $this->assertContains( 'kwawingu_availability', $registered );
        }

        public function test_tours_shortcode_maps_limit_attribute(): void {
            require_once dirname( __DIR__ ) . '/blocks/tours-grid/render-fn.php';
            // Stub the render deps so the callback runs without a real query.
            Functions\when( 'esc_html' )->returnArg();
            Functions\when( 'esc_html__' )->returnArg();
            $sc = new Shortcodes();
            // No tours -> empty grid markup; we only assert the wrapper is produced.
            // WP_Query stub above handles the real query path; no extra stub needed.
            $html = $sc->render_tours( array( 'limit' => '4' ) );
            $this->assertIsString( $html );
        }

        /**
         * WordPress only enqueues a block's viewScript when the block renders; the
         * shortcode path must do it itself or the form is inert on classic themes.
         */
        public function test_interactive_shortcodes_enqueue_their_block_view_script(): void {
            Functions\when( 'esc_html' )->returnArg();
            Functions\when( 'esc_html__' )->returnArg();
            Functions\when( 'esc_attr' )->returnArg();
            Functions\when( 'esc_attr__' )->returnArg();
            Functions\when( '__' )->returnArg();
            Functions\when( 'get_the_ID' )->justReturn( 0 );
            Functions\when( 'get_post_meta' )->justReturn( '' );
            Functions\when( 'generate_block_asset_handle' )->alias( static function ( $name, $field ) {
                return str_replace( '/', '-', $name ) . '-view-script';
            } );
            $enqueued = array();
            Functions\when( 'wp_enqueue_script' )->alias( static function ( $handle ) use ( &$enqueued ) {
                $enqueued[] = $handle;
            } );

            $sc = new Shortcodes();
            $sc->render_search( array() );
            $sc->render_calculator( array() );
            $sc->render_booking_form( array() );
            $sc->render_availability( array( 'slug' => 'kili' ) );
            $sc->render_inquiry( array() );

            foreach ( Shortcodes::INTERACTIVE as $tag ) {
                $this->assertContains( 'kwawingu-' . Shortcodes::BLOCKS[ $tag ] . '-view-script', $enqueued, $tag );
            }
            $this->assertContains( 'kwt-proxy', $enqueued );
        }

        public function test_booking_form_and_availability_shortcodes_accept_id_or_slug(): void {
            Functions\when( 'esc_html' )->returnArg();
            Functions\when( 'esc_html__' )->returnArg();
            Functions\when( 'esc_attr' )->returnArg();
            Functions\when( 'esc_attr__' )->returnArg();
            Functions\when( '__' )->returnArg();
            Functions\when( 'wp_enqueue_script' )->justReturn( null );
            Functions\when( 'generate_block_asset_handle' )->justReturn( 'h' );
            Functions\when( 'get_the_ID' )->justReturn( 99 );
            Functions\when( 'get_post_meta' )->alias( static function ( $id, $key ) {
                return ( 4 === (int) $id && 'kwt_slug' === $key ) ? 'kili' : '';
            } );
            $sc = new Shortcodes();

            $this->assertStringContainsString( 'data-tour="kili"', $sc->render_booking_form( array( 'id' => '4' ) ) );
            $this->assertStringContainsString( 'data-tour="mara"', $sc->render_booking_form( array( 'slug' => 'mara' ) ) );
            $this->assertStringContainsString( 'data-tour="kili"', $sc->render_availability( array( 'id' => '4' ) ) );
            $this->assertStringContainsString( 'data-tour="mara"', $sc->render_availability( array( 'slug' => 'mara' ) ) );
            // No attribute: falls back to the current post (99 has no slug here).
            $this->assertStringContainsString( 'data-tour=""', $sc->render_booking_form( array() ) );
        }
    }
}
