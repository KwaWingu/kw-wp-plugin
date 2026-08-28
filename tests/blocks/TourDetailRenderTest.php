<?php
namespace KwaWingu\Tours\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Api_Client;
use KwaWingu\Tours\Live_Catalog;
use Mockery;
use PHPUnit\Framework\TestCase;

class TourDetailRenderTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Live_Catalog::set_instance( null );
        require_once dirname( __DIR__, 2 ) . '/blocks/tour-detail/render-fn.php';
        foreach ( array( 'esc_html', 'esc_attr', 'esc_url', 'esc_html__' ) as $f ) {
            Functions\when( $f )->returnArg();
        }
        Functions\when( '_n' )->alias( static fn( $s, $p, $n ) => 1 === $n ? $s : $p );
        Functions\when( 'get_option' )->justReturn( array() );
        Functions\when( 'get_the_ID' )->justReturn( 7 );
        Functions\when( 'get_the_title' )->justReturn( 'Kilimanjaro Trek' );
        Functions\when( 'get_post' )->justReturn( (object) array( 'post_content' => 'Climb the roof of Africa.' ) );
        Functions\when( 'apply_filters' )->alias( static fn( $tag, $val ) => $val );
        Functions\when( 'get_the_post_thumbnail_url' )->justReturn( 'https://img/kili.jpg' );
        Functions\when( 'wp_kses_post' )->returnArg();
        Functions\when( 'get_post_meta' )->alias( static function ( $id, $key, $single ) {
            $map = array(
                'kwt_price'         => 1200000,
                'kwt_duration_days' => 7,
                'kwt_difficulty'    => 'Challenging',
                'kwt_slug'          => 'kili',
            );
            return $map[ $key ] ?? '';
        } );
        Functions\when( 'get_transient' )->justReturn( false );
        Functions\when( 'set_transient' )->justReturn( true );
        Functions\when( '__' )->returnArg();
        Functions\when( 'get_option' )->justReturn( false );
        Functions\when( 'update_option' )->justReturn( true );
        Functions\when( 'delete_option' )->justReturn( true );
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

    public function test_renders_detail_with_price_and_difficulty(): void {
        $html = kwawingu_tours_render_tour_detail( array( 'postId' => 7 ), '' );
        $this->assertStringContainsString( 'Kilimanjaro Trek', $html );
        $this->assertStringContainsString( 'TZS 1,200,000', $html );
        $this->assertStringContainsString( 'Challenging', $html );
        $this->assertStringContainsString( 'kwt-tour-detail', $html );
    }

    public function test_live_price_and_next_departure_replace_the_stale_meta(): void {
        $this->live(
            array(
                array(
                    'slug'                  => 'kili',
                    'basePriceAdult'        => 1500000,
                    'currency'              => 'USD',
                    'activeDeparturesCount' => 5,
                    'nextDepartureDate'     => '2026-09-14',
                ),
            )
        );

        $html = kwawingu_tours_render_tour_detail( array( 'postId' => 7 ), '' );

        $this->assertStringContainsString( 'USD 1,500,000', $html );
        $this->assertStringNotContainsString( '1,200,000', $html );
        $this->assertStringContainsString( '2026-09-14', $html );
        // The SEO-bearing content still comes from the post, not the API.
        $this->assertStringContainsString( 'Climb the roof of Africa.', $html );
    }

    public function test_sold_out_is_shown_instead_of_a_next_departure(): void {
        $this->live(
            array(
                array(
                    'slug'                  => 'kili',
                    'basePriceAdult'        => 1500000,
                    'currency'              => 'USD',
                    'activeDeparturesCount' => 0,
                    'nextDepartureDate'     => null,
                ),
            )
        );

        $html = kwawingu_tours_render_tour_detail( array( 'postId' => 7 ), '' );

        $this->assertStringContainsString( 'Sold out', $html );
        $this->assertStringNotContainsString( 'Next departure', $html );
    }
}
