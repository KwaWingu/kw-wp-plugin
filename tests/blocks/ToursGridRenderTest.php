<?php

namespace KwaWingu\Tours\Tests\Blocks {

    use Brain\Monkey;
    use Brain\Monkey\Functions;
    use PHPUnit\Framework\TestCase;

    class ToursGridRenderTest extends TestCase {
        protected function setUp(): void {
            parent::setUp();
            Monkey\setUp();
            require_once dirname( __DIR__, 2 ) . '/blocks/tours-grid/render.php';
            Functions\when( 'esc_html' )->returnArg();
            Functions\when( 'esc_attr' )->returnArg();
            Functions\when( 'esc_url' )->returnArg();
            Functions\when( 'esc_html__' )->returnArg();
            Functions\when( '_n' )->alias( static fn( $s, $p, $n ) => $n === 1 ? $s : $p );
            Functions\when( 'wp_reset_postdata' )->justReturn( null );
            Functions\when( 'get_the_ID' )->justReturn( 7 );
            Functions\when( 'get_the_title' )->justReturn( 'Serengeti Safari' );
            Functions\when( 'get_permalink' )->justReturn( 'https://site/tours/serengeti/' );
            Functions\when( 'get_the_post_thumbnail_url' )->justReturn( 'https://img/cover.jpg' );
            Functions\when( 'get_post_meta' )->alias( static function ( $id, $key, $single ) {
                $map = array( 'kwt_price' => 450000, 'kwt_duration_days' => 3 );
                return $map[ $key ] ?? '';
            } );
        }
        protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

        public function test_renders_tour_cards_with_title_and_price(): void {
            // Fake WP_Query: one tour in the loop.
            $query = new \WP_Query_Stub( array( 7 ) );
            // View::tour_query not called; query is injected via _query attribute.
            // Render directly with an injected query via the attribute hook:
            $html = kwt_render_tours_grid( array( 'limit' => 6, '_query' => $query ), '' );

            $this->assertStringContainsString( 'Serengeti Safari', $html );
            $this->assertStringContainsString( 'TZS 450,000', $html );
            $this->assertStringContainsString( 'kwt-tours-grid', $html );
        }
    }
}

namespace {
    if ( ! class_exists( 'WP_Query_Stub' ) ) {
        class WP_Query_Stub {
            private $ids; private $i = -1;
            public function __construct( array $ids ) { $this->ids = $ids; }
            public function have_posts(): bool { return $this->i + 1 < count( $this->ids ); }
            public function the_post(): void { $this->i++; }
            public function get_ids(): array { return $this->ids; }
        }
    }
}
