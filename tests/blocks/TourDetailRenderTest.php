<?php
namespace KwaWingu\Tours\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class TourDetailRenderTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
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
            $map = array( 'kwt_price' => 1200000, 'kwt_duration_days' => 7, 'kwt_difficulty' => 'Challenging' );
            return $map[ $key ] ?? '';
        } );
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_renders_detail_with_price_and_difficulty(): void {
        $html = kwt_render_tour_detail( array( 'postId' => 7 ), '' );
        $this->assertStringContainsString( 'Kilimanjaro Trek', $html );
        $this->assertStringContainsString( 'TZS 1,200,000', $html );
        $this->assertStringContainsString( 'Challenging', $html );
        $this->assertStringContainsString( 'kwt-tour-detail', $html );
    }
}
