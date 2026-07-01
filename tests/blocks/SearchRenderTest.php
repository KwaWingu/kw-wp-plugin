<?php
namespace KwaWingu\Tours\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class SearchRenderTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        require_once dirname( __DIR__, 2 ) . '/blocks/search/render-fn.php';
        foreach ( array( 'esc_attr', 'esc_attr__', 'esc_html__' ) as $f ) { Functions\when( $f )->returnArg(); }
        Functions\when( 'wp_enqueue_script' )->justReturn( null );
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_renders_search_shell(): void {
        $html = kwt_render_search( array(), '' );
        $this->assertStringContainsString( 'kwt-search', $html );
        $this->assertStringContainsString( '<input', $html );
        $this->assertStringContainsString( 'kwt-search__results', $html );
    }
}
