<?php

namespace KwaWingu\Tours\Tests\Blocks {

    use Brain\Monkey;
    use Brain\Monkey\Functions;
    use KwaWingu\Tours\Api_Client;
    use KwaWingu\Tours\Api_Exception;
    use KwaWingu\Tours\Live_Catalog;
    use Mockery;
    use PHPUnit\Framework\TestCase;

    class ToursGridRenderTest extends TestCase {
        protected function setUp(): void {
            parent::setUp();
            Monkey\setUp();
            Functions\when( 'get_option' )->justReturn( false );
            Functions\when( 'update_option' )->justReturn( true );
            Functions\when( 'delete_option' )->justReturn( true );
            Functions\when( '__' )->returnArg();
            Live_Catalog::set_instance( null );
            require_once dirname( __DIR__, 2 ) . '/blocks/tours-grid/render-fn.php';
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
                $map = array(
                    'kwt_price'          => 450000,
                    'kwt_duration_days'  => 3,
                    'kwt_slug'           => 'serengeti',
                    'kwt_currency'       => 'TZS',
                );
                return $map[ $key ] ?? '';
            } );
            Functions\when( 'get_transient' )->justReturn( false );
            Functions\when( 'set_transient' )->justReturn( true );
        }
        protected function tearDown(): void {
            Live_Catalog::set_instance( null );
            Monkey\tearDown();
            Mockery::close();
            parent::tearDown();
        }

        /** Boots a Live_Catalog whose one upstream call returns $rows. */
        private function live( array $rows ): void {
            $api = Mockery::mock( Api_Client::class );
            $api->shouldReceive( 'get' )->andReturn( array( 'data' => $rows ) );
            Live_Catalog::set_instance( new Live_Catalog( $api ) );
        }

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

        public function test_live_price_and_currency_beat_the_synced_meta(): void {
            $this->live(
                array(
                    array(
                        'slug'                  => 'serengeti',
                        'basePriceAdult'        => 1250000,
                        'currency'              => 'USD',
                        'activeDeparturesCount' => 3,
                        'nextDepartureDate'     => '2026-09-14',
                    ),
                )
            );

            $html = kwt_render_tours_grid( array( '_query' => new \WP_Query_Stub( array( 7 ) ) ), '' );

            $this->assertStringContainsString( 'USD 1,250,000', $html );
            $this->assertStringNotContainsString( '450,000', $html );
            $this->assertStringNotContainsString( 'Sold out', $html );
        }

        public function test_zero_active_departures_renders_sold_out(): void {
            $this->live(
                array(
                    array(
                        'slug'                  => 'serengeti',
                        'basePriceAdult'        => 1250000,
                        'currency'              => 'USD',
                        'activeDeparturesCount' => 0,
                    ),
                )
            );

            $html = kwt_render_tours_grid( array( '_query' => new \WP_Query_Stub( array( 7 ) ) ), '' );

            $this->assertStringContainsString( 'Sold out', $html );
        }

        public function test_falls_back_to_stored_price_when_the_api_is_down(): void {
            $api = Mockery::mock( Api_Client::class );
            $api->shouldReceive( 'get' )->andThrow( new Api_Exception( 'down', 502 ) );
            Live_Catalog::set_instance( new Live_Catalog( $api ) );

            $html = kwt_render_tours_grid( array( '_query' => new \WP_Query_Stub( array( 7 ) ) ), '' );

            // The card still shows a price — the stored one — and never an error.
            $this->assertStringContainsString( 'TZS 450,000', $html );
            $this->assertStringNotContainsString( 'Sold out', $html );
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
