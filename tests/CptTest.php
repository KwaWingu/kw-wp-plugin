<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Cpt;
use PHPUnit\Framework\TestCase;

class CptTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\when( '__' )->returnArg();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_hooks_init(): void {
        ( new Cpt() )->register();
        $this->assertNotFalse( has_action( 'init' ) );
    }

    public function test_init_registers_tour_cpt_as_public_with_rewrite(): void {
        $captured = array();
        Functions\when( 'register_post_type' )->alias( static function ( $type, $args ) use ( &$captured ) {
            $captured[ $type ] = $args;
        } );
        Functions\when( 'register_taxonomy' )->justReturn( true );

        ( new Cpt() )->init();

        $this->assertArrayHasKey( 'kwt_tour', $captured );
        $this->assertTrue( $captured['kwt_tour']['public'] );
        $this->assertTrue( $captured['kwt_tour']['has_archive'] );
        $this->assertSame( 'tours', $captured['kwt_tour']['rewrite']['slug'] );
        $this->assertContains( 'title', $captured['kwt_tour']['supports'] );
        $this->assertContains( 'editor', $captured['kwt_tour']['supports'] );
        $this->assertContains( 'thumbnail', $captured['kwt_tour']['supports'] );
    }
}
