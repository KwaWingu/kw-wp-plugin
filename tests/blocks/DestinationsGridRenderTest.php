<?php
namespace KwaWingu\Tours\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class DestinationsGridRenderTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        require_once dirname( __DIR__, 2 ) . '/blocks/destinations-grid/render-fn.php';
        foreach ( array( 'esc_html', 'esc_attr', 'esc_url', 'esc_html__' ) as $f ) {
            Functions\when( $f )->returnArg();
        }
        Functions\when( 'wp_reset_postdata' )->justReturn( null );
        Functions\when( 'get_the_ID' )->justReturn( 3 );
        Functions\when( 'get_the_title' )->justReturn( 'Serengeti' );
        Functions\when( 'get_permalink' )->justReturn( 'https://site/destinations/serengeti/' );
        Functions\when( 'get_the_post_thumbnail_url' )->justReturn( 'https://img/s.jpg' );
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_renders_destination_cards(): void {
        $query = new \WP_Query_Stub( array( 3 ) );
        $html  = kwt_render_destinations_grid( array( '_query' => $query ), '' );
        $this->assertStringContainsString( 'Serengeti', $html );
        $this->assertStringContainsString( 'kwt-destinations-grid', $html );
    }
}
