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
        Functions\expect( 'download_url' )->never();
        ( new Media( new Settings() ) )->ingest_cover( 7, 'https://img/x.jpg' );
        $this->assertTrue( true );
    }

    public function test_sideload_sets_thumbnail_once(): void {
        Functions\when( 'get_option' )->justReturn( array( 'media_mode' => 'sideload' ) );
        Functions\when( 'get_post_meta' )->justReturn( '' );   // not yet ingested
        Functions\when( 'has_post_thumbnail' )->justReturn( false );
        $this->stub_download( 55 );
        $set = array();
        Functions\when( 'set_post_thumbnail' )->alias( static function ( $p, $a ) use ( &$set ) { $set[] = array( $p, $a ); return true; } );
        Functions\when( 'update_post_meta' )->justReturn( true );

        ( new Media( new Settings() ) )->ingest_cover( 7, 'https://img/x.jpg' );
        $this->assertSame( array( array( 7, 55 ) ), $set );
    }

    public function test_sideload_skips_when_already_ingested(): void {
        Functions\when( 'get_option' )->justReturn( array( 'media_mode' => 'sideload' ) );
        Functions\when( 'get_post_meta' )->justReturn( 'https://img/x.jpg' ); // same src already recorded
        Functions\expect( 'download_url' )->never();
        ( new Media( new Settings() ) )->ingest_cover( 7, 'https://img/x.jpg' );
        $this->assertTrue( true );
    }

    public function test_gallery_hotlink_mode_does_not_sideload(): void {
        Functions\when( 'get_option' )->justReturn( array( 'media_mode' => 'hotlink' ) );
        Functions\expect( 'download_url' )->never();
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
        Functions\when( 'download_url' )->alias( static function ( $url ) use ( &$sideloaded ) {
            $sideloaded[] = $url;
            return '/tmp/kwt-test-download';
        } );
        Functions\when( 'wp_get_image_mime' )->justReturn( 'image/jpeg' );
        Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
        Functions\when( 'sanitize_file_name' )->returnArg();
        Functions\when( 'is_wp_error' )->justReturn( false );
        Functions\when( 'media_handle_sideload' )->justReturn( 22 ); // new attachment id
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

    /** Stubs a successful download + import returning $attachment_id; records the file array. */
    private function stub_download( int $attachment_id, string $mime = 'image/jpeg', array &$files = null ): void {
        Functions\when( 'download_url' )->justReturn( '/tmp/kwt-test-download' );
        Functions\when( 'wp_get_image_mime' )->justReturn( $mime );
        Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
        Functions\when( 'sanitize_file_name' )->returnArg();
        Functions\when( 'is_wp_error' )->justReturn( false );
        Functions\when( 'media_handle_sideload' )->alias( static function ( $file ) use ( &$files, $attachment_id ) {
            $files[] = $file;
            return $attachment_id;
        } );
    }

    public function test_sideload_imports_an_extensionless_cloudflare_images_url(): void {
        // Production photos are imagedelivery.net/{hash}/{id}/public: media_sideload_image()
        // refused them ("Invalid image URL.") because it keys off a URL extension.
        Functions\when( 'get_option' )->justReturn( array( 'media_mode' => 'sideload' ) );
        $files = array();
        $this->stub_download( 77, 'image/webp', $files );

        $id = ( new Media( new Settings() ) )->sideload( 'https://imagedelivery.net/abc123/9f1e/public', 7 );

        $this->assertSame( 77, $id );
        $this->assertCount( 1, $files );
        $this->assertSame( 'image/webp', $files[0]['type'] );
        $this->assertSame( '/tmp/kwt-test-download', $files[0]['tmp_name'] );
        $this->assertMatchesRegularExpression( '/^kwt-[0-9a-f]{12}\\.webp$/', $files[0]['name'] );
    }

    public function test_sideload_keeps_a_real_filename_and_rejects_non_images(): void {
        Functions\when( 'get_option' )->justReturn( array( 'media_mode' => 'sideload' ) );
        $files = array();
        $this->stub_download( 78, 'image/jpeg', $files );
        $media = new Media( new Settings() );

        $this->assertSame( 78, $media->sideload( 'https://img.test/photos/kili.jpg?w=800', 7 ) );
        $this->assertSame( 'kili.jpg', $files[0]['name'] );

        // A download that is not an image is discarded, never handed to the media library.
        Functions\when( 'wp_get_image_mime' )->justReturn( false );
        Functions\when( 'wp_delete_file' )->justReturn( true );
        Functions\expect( 'media_handle_sideload' )->never();
        $this->assertSame( 0, $media->sideload( 'https://img.test/not-an-image', 7 ) );
    }
}
