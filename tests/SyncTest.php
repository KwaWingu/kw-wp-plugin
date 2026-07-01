<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Api_Client;
use KwaWingu\Tours\Sync;
use Mockery;
use PHPUnit\Framework\TestCase;

class SyncTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_strip_all_tags' )->returnArg();
        Functions\when( 'sanitize_title' )->returnArg();
        Functions\when( 'update_post_meta' )->justReturn( true );
        Functions\when( 'wp_update_post' )->justReturn( 1 );
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    private function api_returning( array $tours ): Api_Client {
        $api = Mockery::mock( Api_Client::class );
        $api->shouldReceive( 'get_site' )->once()->andReturn(
            array( 'tours' => $tours )
        );
        return $api;
    }

    public function test_creates_new_tour_when_no_existing_post(): void {
        // No existing kwt_tour posts at all.
        Functions\when( 'get_posts' )->justReturn( array() );
        // wp_insert_post returns a new ID; capture the args.
        $inserted = array();
        Functions\when( 'wp_insert_post' )->alias( static function ( $args ) use ( &$inserted ) {
            $inserted[] = $args;
            return 101;
        } );

        $api  = $this->api_returning( array(
            array( 'id' => 'T1', 'slug' => 'safari', 'title' => 'Safari', 'descriptionShort' => 'Wild', 'price' => 450000 ),
        ) );
        $out = ( new Sync( $api ) )->run();

        $this->assertSame( 1, $out['created'] );
        $this->assertSame( 0, $out['updated'] );
        $this->assertSame( 'Safari', $inserted[0]['post_title'] );
        $this->assertSame( 'publish', $inserted[0]['post_status'] );
    }

    public function test_updates_existing_but_preserves_locked_content(): void {
        // Existing post 55 for kwt_id T1, content locked.
        Functions\when( 'get_posts' )->alias( static function ( $args ) {
            // First call: lookup by meta kwt_id=T1 -> returns [55]; the "all existing" sweep also returns [55].
            return array( 55 );
        } );
        Functions\when( 'get_post_meta' )->alias( static function ( $id, $key, $single ) {
            if ( 'kwt_id' === $key ) { return 'T1'; }
            if ( 'kwt_content_locked' === $key ) { return '1'; }
            return '';
        } );
        $updates = array();
        Functions\when( 'wp_update_post' )->alias( static function ( $args ) use ( &$updates ) {
            $updates[] = $args;
            return 55;
        } );

        $api = $this->api_returning( array(
            array( 'id' => 'T1', 'slug' => 'safari', 'title' => 'NEW TITLE', 'descriptionShort' => 'x', 'price' => 500000 ),
        ) );
        $out = ( new Sync( $api ) )->run();

        $this->assertSame( 1, $out['updated'] );
        // Locked: title must NOT be overwritten -> no post_title key in the update payload.
        $this->assertArrayNotHasKey( 'post_title', $updates[0] );
        $this->assertArrayNotHasKey( 'post_excerpt', $updates[0] );
        $this->assertArrayNotHasKey( 'post_content', $updates[0] );
    }

    public function test_unpublishes_tour_missing_from_response(): void {
        // Existing post 77 (kwt_id GONE) is absent from the API response.
        Functions\when( 'get_posts' )->alias( static function ( $args ) {
            if ( isset( $args['meta_query'] ) ) { return array(); } // no match for incoming ids
            return array( 77 ); // the "all existing" sweep
        } );
        Functions\when( 'get_post_meta' )->alias( static function ( $id, $key, $single ) {
            return 'kwt_id' === $key ? 'GONE' : '';
        } );
        Functions\when( 'wp_insert_post' )->justReturn( 78 );
        $drafted = array();
        Functions\when( 'wp_update_post' )->alias( static function ( $args ) use ( &$drafted ) {
            $drafted[] = $args;
            return $args['ID'];
        } );

        $api = $this->api_returning( array(
            array( 'id' => 'T1', 'slug' => 'safari', 'title' => 'Safari', 'descriptionShort' => 'x', 'price' => 1 ),
        ) );
        $out = ( new Sync( $api ) )->run();

        $this->assertSame( 1, $out['unpublished'] );
        $this->assertSame( 77, $drafted[0]['ID'] );
        $this->assertSame( 'draft', $drafted[0]['post_status'] );
    }

    public function test_empty_tours_response_does_not_unpublish_catalog(): void {
        // A successful /site with an empty tours[] must NOT draft existing posts.
        Functions\when( 'get_posts' )->justReturn( array( 999 ) ); // an existing published tour
        Functions\when( 'get_post_meta' )->alias( static function ( $id, $key, $single ) {
            return 'kwt_id' === $key ? 'STILL-HERE' : '';
        } );
        $drafted = array();
        Functions\when( 'wp_update_post' )->alias( static function ( $args ) use ( &$drafted ) {
            $drafted[] = $args;
            return $args['ID'] ?? 0;
        } );

        $api = Mockery::mock( Api_Client::class );
        $api->shouldReceive( 'get_site' )->once()->andReturn( array( 'tours' => array() ) );

        $out = ( new Sync( $api ) )->run();

        $this->assertSame( 0, $out['created'] );
        $this->assertSame( 0, $out['updated'] );
        $this->assertSame( 0, $out['unpublished'] );      // guard engaged
        $this->assertSame( array(), $drafted );            // nothing drafted
        $this->assertNotEmpty( $out['errors'] );           // a warning is recorded
    }
}
