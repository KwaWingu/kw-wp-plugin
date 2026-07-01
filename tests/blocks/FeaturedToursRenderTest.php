<?php
namespace KwaWingu\Tours\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class FeaturedToursRenderTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        require_once dirname( __DIR__, 2 ) . '/blocks/tours-grid/render.php';
        require_once dirname( __DIR__, 2 ) . '/blocks/featured-tours/render.php';
        foreach ( array( 'esc_html', 'esc_attr', 'esc_url', 'esc_html__' ) as $f ) {
            Functions\when( $f )->returnArg();
        }
        Functions\when( '_n' )->alias( static fn( $s, $p, $n ) => 1 === $n ? $s : $p );
        Functions\when( 'wp_reset_postdata' )->justReturn( null );
        Functions\when( 'get_the_ID' )->justReturn( 7 );
        Functions\when( 'get_the_title' )->justReturn( 'Zanzibar Beach' );
        Functions\when( 'get_permalink' )->justReturn( 'https://site/tours/zanzibar/' );
        Functions\when( 'get_the_post_thumbnail_url' )->justReturn( '' );
        Functions\when( 'get_post_meta' )->justReturn( 0 );
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_renders_heading_and_delegates_to_grid(): void {
        $query = new \WP_Query_Stub( array( 7 ) );
        $html  = kwt_render_featured_tours( array( 'heading' => 'Popular trips', '_query' => $query ), '' );
        $this->assertStringContainsString( 'Popular trips', $html );
        $this->assertStringContainsString( 'Zanzibar Beach', $html );
        $this->assertStringContainsString( 'kwt-featured', $html );
    }
}
