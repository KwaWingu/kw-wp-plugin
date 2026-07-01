<?php
namespace KwaWingu\Tours\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class CalculatorRenderTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        require_once dirname( __DIR__, 2 ) . '/blocks/calculator/render-fn.php';
        foreach ( array( 'esc_attr', 'esc_attr__', 'esc_html__' ) as $f ) { Functions\when( $f )->returnArg(); }
        Functions\when( 'wp_enqueue_script' )->justReturn( null );
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_renders_calculator_form(): void {
        $html = kwt_render_calculator( array(), '' );
        $this->assertStringContainsString( 'kwt-calculator', $html );
        $this->assertStringContainsString( 'kwt-calculator__total', $html );
        $this->assertStringContainsString( 'name="adults"', $html );
    }
}
