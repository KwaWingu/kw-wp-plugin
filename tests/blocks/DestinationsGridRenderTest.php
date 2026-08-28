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
        Functions\when( 'get_post_meta' )->justReturn( '' );
        Functions\when( 'get_option' )->justReturn( array( 'slug' => 'acme' ) );
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'sanitize_title' )->returnArg();
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_renders_destination_cards(): void {
        $query = new \WP_Query_Stub( array( 3 ) );
        $html  = kwt_render_destinations_grid( array( '_query' => $query ), '' );
        $this->assertStringContainsString( 'Serengeti', $html );
        $this->assertStringContainsString( 'kwt-destinations-grid', $html );
    }

    /**
     * Owner's report: "the destinations go to google". A synced destination links to its hosted
     * storefront page — the one with the description, park fees and the tours that go there —
     * not to the bare local post.
     */
    public function test_card_links_to_the_hosted_destination_page_when_the_slug_is_synced(): void {
        Functions\when( 'get_post_meta' )->alias( static fn( $id, $key ) => 'kwt_slug' === $key ? 'serengeti-national-park' : '' );
        $query = new \WP_Query_Stub( array( 3 ) );
        $html  = kwt_render_destinations_grid( array( '_query' => $query ), '' );
        $this->assertStringContainsString( 'href="https://tours.kwawingu.com/acme/destinations/serengeti-national-park"', $html );
        $this->assertStringNotContainsString( 'https://site/destinations/serengeti/', $html );
    }

    public function test_card_falls_back_to_the_local_permalink_without_a_synced_slug(): void {
        $query = new \WP_Query_Stub( array( 3 ) );
        $html  = kwt_render_destinations_grid( array( '_query' => $query ), '' );
        $this->assertStringContainsString( 'href="https://site/destinations/serengeti/"', $html );
    }
}
