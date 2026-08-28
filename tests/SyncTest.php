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
        Functions\when( 'get_option' )->justReturn( false );
        Functions\when( 'update_option' )->justReturn( true );
        Functions\when( 'delete_option' )->justReturn( true );
        Functions\when( '__' )->returnArg();
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_strip_all_tags' )->returnArg();
        Functions\when( 'sanitize_title' )->returnArg();
        Functions\when( 'update_post_meta' )->justReturn( true );
        Functions\when( 'wp_update_post' )->justReturn( 1 );
        Functions\when( 'esc_url_raw' )->returnArg();
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

    public function test_write_meta_stores_rating_and_gallery(): void {
        $saved = array();
        \Brain\Monkey\Functions\when( 'update_post_meta' )->alias( static function ( $id, $key, $val ) use ( &$saved ) {
            $saved[ $key ] = $val;
            return true;
        } );
        \Brain\Monkey\Functions\when( 'get_posts' )->justReturn( array() );
        \Brain\Monkey\Functions\when( 'wp_insert_post' )->justReturn( 101 );

        $api = \Mockery::mock( \KwaWingu\Tours\Api_Client::class );
        $api->shouldReceive( 'get_site' )->andReturn( array( 'tours' => array(
            array(
                'id' => 'T1', 'slug' => 'safari', 'title' => 'Safari', 'price' => 1,
                'rating' => 4.5, 'reviewCount' => 12,
                'gallery' => array( 'https://img/a.jpg', 'https://img/b.jpg' ),
            ),
        ) ) );
        ( new \KwaWingu\Tours\Sync( $api ) )->run();

        $this->assertSame( 4.5, $saved['kwt_rating'] );
        $this->assertSame( 12, $saved['kwt_review_count'] );
        $this->assertSame( array( 'https://img/a.jpg', 'https://img/b.jpg' ), $saved['kwt_gallery'] );
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

    public function test_api_refusal_reports_the_owner_fix_not_the_status(): void {
        Functions\when( 'get_posts' )->justReturn( array() );
        $api = Mockery::mock( Api_Client::class );
        $api->shouldReceive( 'get_site' )->once()->andThrow(
            new \KwaWingu\Tours\Api_Exception( 'Enable API access in your dashboard to use the API.', 403, 'api_access_required' )
        );

        $out = ( new Sync( $api ) )->run();

        $this->assertCount( 1, $out['errors'] );
        $this->assertStringContainsString( 'plan does not include API access', $out['errors'][0] );
        $this->assertStringNotContainsString( '403', $out['errors'][0] );
    }

    public function test_syncs_destinations_from_the_site_bundle_into_kwt_destination_posts(): void {
        // Before this, nothing ever wrote a kwt_destination post, so the Destinations
        // Grid rendered its empty state on every site regardless of the catalog.
        $lookups = array();
        Functions\when( 'get_posts' )->alias( static function ( $args ) use ( &$lookups ) {
            $lookups[] = $args;
            return array();
        } );
        $inserted = array();
        Functions\when( 'wp_insert_post' )->alias( static function ( $args ) use ( &$inserted ) {
            $inserted[] = $args;
            return count( $inserted ) + 200;
        } );
        $meta = array();
        Functions\when( 'update_post_meta' )->alias( static function ( $id, $key, $value ) use ( &$meta ) {
            $meta[ $id ][ $key ] = $value;
            return true;
        } );
        Functions\when( 'get_post_meta' )->justReturn( '' );

        $api = Mockery::mock( Api_Client::class );
        $api->shouldReceive( 'get_site' )->once()->andReturn(
            array(
                'tours'        => array( array( 'id' => 'T1', 'slug' => 'safari', 'title' => 'Safari' ) ),
                'destinations' => array(
                    array( 'id' => 'D1', 'name' => 'Serengeti', 'slug' => 'serengeti', 'region' => 'Mara', 'country' => 'Tanzania', 'description' => 'Plains', 'coverImageUrl' => 'https://img.test/s.jpg' ),
                    array( 'id' => '', 'name' => 'No id — skipped' ),
                ),
            )
        );
        $out = ( new Sync( $api ) )->run();

        $this->assertSame( 1, $out['created'] );
        $this->assertSame( array( 'created' => 1, 'updated' => 0, 'unpublished' => 0 ), $out['destinations'] );
        $dest = array_values( array_filter( $inserted, static fn( $a ) => 'kwt_destination' === $a['post_type'] ) );
        $this->assertCount( 1, $dest );
        $this->assertSame( 'Serengeti', $dest[0]['post_title'] );
        $this->assertSame( 'publish', $dest[0]['post_status'] );
        $this->assertSame( 'D1', $meta[202]['kwt_id'] );
        $this->assertSame( 'https://img.test/s.jpg', $meta[202]['kwt_cover_url'] );
        $this->assertSame( 'Mara', $meta[202]['kwt_region'] );
        // The API slug is kept so the grid can link to the hosted destination page.
        $this->assertSame( 'serengeti', $meta[202]['kwt_slug'] );
        // The destination lookup and sweep are scoped to kwt_destination, never the tours.
        $types = array_unique( array_map( static fn( $a ) => $a['post_type'], $lookups ) );
        $this->assertContains( 'kwt_destination', $types );
    }
}
