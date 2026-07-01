<?php
namespace KwaWingu\Tours\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class ReviewsRenderTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        require_once dirname( __DIR__, 2 ) . '/blocks/reviews/render-fn.php';
        foreach ( array( 'esc_html', 'esc_attr', 'esc_html__' ) as $f ) {
            Functions\when( $f )->returnArg();
        }
        Functions\when( '_n' )->alias( static fn( $s, $p, $n ) => 1 === $n ? $s : $p );
        Functions\when( 'get_the_ID' )->justReturn( 7 );
        Functions\when( 'number_format_i18n' )->alias( static fn( $n, $d = 0 ) => number_format( (float) $n, (int) $d ) );
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_renders_rating_and_count(): void {
        Functions\when( 'get_post_meta' )->alias( static function ( $id, $key, $single ) {
            $map = array( 'kwt_rating' => 4.5, 'kwt_review_count' => 12 );
            return $map[ $key ] ?? '';
        } );
        $html = kwt_render_reviews( array(), '' );
        $this->assertStringContainsString( '4.5', $html );
        $this->assertStringContainsString( '12', $html );
        $this->assertStringContainsString( 'kwt-reviews', $html );
    }

    public function test_empty_when_no_rating(): void {
        Functions\when( 'get_post_meta' )->justReturn( 0 );
        $this->assertSame( '', kwt_render_reviews( array(), '' ) );
    }
}
