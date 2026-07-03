<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Patterns;
use PHPUnit\Framework\TestCase;

class PatternsTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\when( '__' )->returnArg();
        Functions\when( 'esc_html__' )->returnArg();
        Functions\when( 'esc_attr__' )->returnArg();
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_register_hooks_init(): void {
        ( new Patterns() )->register();
        $this->assertNotFalse( has_action( 'init' ) );
    }

    public function test_init_registers_category_and_patterns(): void {
        Functions\expect( 'register_block_pattern_category' )->atLeast()->once();
        $registered = array();
        Functions\when( 'register_block_pattern' )->alias( static function ( $slug, $args ) use ( &$registered ) {
            $registered[] = $slug;
        } );
        ( new Patterns() )->init();
        $this->assertContains( 'kwawingu/home', $registered );
        $this->assertContains( 'kwawingu/tours', $registered );
        $this->assertContains( 'kwawingu/contact', $registered );
    }
}
