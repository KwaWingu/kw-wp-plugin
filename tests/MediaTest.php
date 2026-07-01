<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Media;
use KwaWingu\Tours\Settings;
use PHPUnit\Framework\TestCase;

class MediaTest extends TestCase {
    protected function setUp(): void { parent::setUp(); Monkey\setUp(); }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_hotlink_mode_does_not_sideload(): void {
        Functions\when( 'get_option' )->justReturn( array( 'media_mode' => 'hotlink' ) );
        Functions\expect( 'media_sideload_image' )->never();
        ( new Media( new Settings() ) )->ingest_cover( 7, 'https://img/x.jpg' );
        $this->assertTrue( true );
    }

    public function test_sideload_sets_thumbnail_once(): void {
        Functions\when( 'get_option' )->justReturn( array( 'media_mode' => 'sideload' ) );
        Functions\when( 'get_post_meta' )->justReturn( '' );   // not yet ingested
        Functions\when( 'has_post_thumbnail' )->justReturn( false );
        Functions\when( 'media_sideload_image' )->justReturn( 55 ); // attachment id
        $set = array();
        Functions\when( 'set_post_thumbnail' )->alias( static function ( $p, $a ) use ( &$set ) { $set[] = array( $p, $a ); return true; } );
        Functions\when( 'update_post_meta' )->justReturn( true );

        ( new Media( new Settings() ) )->ingest_cover( 7, 'https://img/x.jpg' );
        $this->assertSame( array( array( 7, 55 ) ), $set );
    }

    public function test_sideload_skips_when_already_ingested(): void {
        Functions\when( 'get_option' )->justReturn( array( 'media_mode' => 'sideload' ) );
        Functions\when( 'get_post_meta' )->justReturn( 'https://img/x.jpg' ); // same src already recorded
        Functions\expect( 'media_sideload_image' )->never();
        ( new Media( new Settings() ) )->ingest_cover( 7, 'https://img/x.jpg' );
        $this->assertTrue( true );
    }
}
