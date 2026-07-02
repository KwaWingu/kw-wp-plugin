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

    public function test_gallery_hotlink_mode_does_not_sideload(): void {
        Functions\when( 'get_option' )->justReturn( array( 'media_mode' => 'hotlink' ) );
        Functions\expect( 'media_sideload_image' )->never();
        $out = ( new Media( new Settings() ) )->ingest_gallery( 7, array( 'https://img/a.jpg' ) );
        $this->assertSame( array(), $out );
    }

    public function test_gallery_sideloads_new_urls_and_dedups(): void {
        Functions\when( 'get_option' )->justReturn( array( 'media_mode' => 'sideload' ) );
        // a.jpg already ingested (src list), b.jpg is new.
        Functions\when( 'get_post_meta' )->alias( static function ( $id, $key, $single ) {
            if ( 'kwt_gallery_src' === $key ) { return array( 'https://img/a.jpg' ); }
            if ( 'kwt_gallery_ids' === $key ) { return array( 11 ); }
            return '';
        } );
        $sideloaded = array();
        Functions\when( 'media_sideload_image' )->alias( static function ( $url ) use ( &$sideloaded ) {
            $sideloaded[] = $url;
            return 22; // new attachment id
        } );
        $saved = array();
        Functions\when( 'update_post_meta' )->alias( static function ( $id, $key, $val ) use ( &$saved ) {
            $saved[ $key ] = $val;
            return true;
        } );

        $out = ( new Media( new Settings() ) )->ingest_gallery( 7, array( 'https://img/a.jpg', 'https://img/b.jpg' ) );

        $this->assertSame( array( 'https://img/b.jpg' ), $sideloaded );      // only the new one
        $this->assertSame( array( 11, 22 ), $out );                          // existing + new id
        $this->assertSame( array( 11, 22 ), $saved['kwt_gallery_ids'] );
        $this->assertSame( array( 'https://img/a.jpg', 'https://img/b.jpg' ), $saved['kwt_gallery_src'] );
    }
}
