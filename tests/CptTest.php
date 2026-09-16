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

        $this->assertArrayHasKey( 'kwawingu_tour', $captured );
        $this->assertTrue( $captured['kwawingu_tour']['public'] );
        $this->assertTrue( $captured['kwawingu_tour']['has_archive'] );
        $this->assertSame( 'tours', $captured['kwawingu_tour']['rewrite']['slug'] );
        $this->assertContains( 'title', $captured['kwawingu_tour']['supports'] );
        $this->assertContains( 'editor', $captured['kwawingu_tour']['supports'] );
        $this->assertContains( 'thumbnail', $captured['kwawingu_tour']['supports'] );
    }

    public function test_init_registers_lead_cpt_private_with_ui(): void {
        $captured = array();
        Functions\when( 'register_post_type' )->alias( static function ( $type, $args ) use ( &$captured ) {
            $captured[ $type ] = $args;
        } );
        Functions\when( 'register_taxonomy' )->justReturn( true );
        ( new Cpt() )->init();
        $this->assertArrayHasKey( 'kwawingu_lead', $captured );
        $this->assertFalse( $captured['kwawingu_lead']['public'] );
        $this->assertTrue( $captured['kwawingu_lead']['show_ui'] );
    }
}
