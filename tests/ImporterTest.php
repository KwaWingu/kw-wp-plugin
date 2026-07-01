<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Importer;
use PHPUnit\Framework\TestCase;

class ImporterTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_strip_all_tags' )->returnArg();
        Functions\when( 'update_post_meta' )->justReturn( true );
        Functions\when( 'update_option' )->justReturn( true );
        // No existing importer pages.
        Functions\when( 'get_posts' )->justReturn( array() );
        // Pattern registry returns known content by slug.
        Functions\when( 'WP_Block_Patterns_Registry' ); // not used; content comes from Patterns::PAGES via registry stub below
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_run_creates_a_page_per_pattern_and_sets_front(): void {
        $created = array();
        Functions\when( 'wp_insert_post' )->alias( static function ( $args ) use ( &$created ) {
            $created[] = $args;
            return count( $created ) + 100; // 101, 102, ...
        } );
        $front = null;
        Functions\when( 'update_option' )->alias( static function ( $k, $v ) use ( &$front ) {
            if ( 'page_on_front' === $k ) { $front = $v; }
            return true;
        } );

        $out = ( new Importer() )->run();

        $this->assertCount( count( \KwaWingu\Tours\Patterns::PAGES ), $out['created'] );
        $this->assertSame( 'page', $created[0]['post_type'] );
        $this->assertGreaterThan( 0, $out['front'] );
        $this->assertSame( $out['front'], $front );
    }

    public function test_run_is_idempotent(): void {
        // A page for every pattern already exists.
        Functions\when( 'get_posts' )->justReturn( array( 55 ) );
        Functions\expect( 'wp_insert_post' )->never();
        $out = ( new Importer() )->run();
        $this->assertSame( array(), $out['created'] );
    }
}
