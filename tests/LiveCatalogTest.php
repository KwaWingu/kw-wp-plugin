<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Api_Client;
use KwaWingu\Tours\Api_Exception;
use KwaWingu\Tours\Live_Catalog;
use Mockery;
use PHPUnit\Framework\TestCase;

class LiveCatalogTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\when( 'get_transient' )->justReturn( false );
        Functions\when( 'set_transient' )->justReturn( true );
        Functions\when( 'delete_transient' )->justReturn( true );
        Functions\when( 'get_option' )->justReturn( false );
        Functions\when( 'update_option' )->justReturn( true );
        Functions\when( 'delete_option' )->justReturn( true );
        Functions\when( '__' )->returnArg();
        Live_Catalog::set_instance( null );
    }

    protected function tearDown(): void {
        Live_Catalog::set_instance( null );
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    /** @return array<int,array<string,mixed>> */
    private function rows(): array {
        return array(
            array(
                'slug'                  => 'serengeti',
                'basePriceAdult'        => 1250000,
                'currency'              => 'USD',
                'activeDeparturesCount' => 4,
                'nextDepartureDate'     => '2026-09-14',
            ),
            array(
                'slug'                  => 'kili',
                'basePriceAdult'        => 2000000,
                'currency'              => 'USD',
                'activeDeparturesCount' => 0,
                'nextDepartureDate'     => null,
            ),
        );
    }

    public function test_reads_price_and_availability_from_the_list_envelope(): void {
        $api = Mockery::mock( Api_Client::class );
        $api->shouldReceive( 'get' )->once()->with( '/tours', array( 'size' => 100 ), 5 )
            ->andReturn( array( 'data' => $this->rows() ) );

        $tour = ( new Live_Catalog( $api ) )->tour( 'serengeti' );

        $this->assertSame( 1250000, $tour['price'] );
        $this->assertSame( 'USD', $tour['currency'] );
        $this->assertSame( '2026-09-14', $tour['nextDeparture'] );
        $this->assertNull( $tour['soldOut'] );
    }

    public function test_zero_active_departures_is_sold_out(): void {
        $api = Mockery::mock( Api_Client::class );
        $api->shouldReceive( 'get' )->andReturn( array( 'data' => $this->rows() ) );

        $this->assertTrue( ( new Live_Catalog( $api ) )->tour( 'kili' )['soldOut'] );
    }

    public function test_one_upstream_call_serves_the_whole_page(): void {
        $api = Mockery::mock( Api_Client::class );
        $api->shouldReceive( 'get' )->once()->andReturn( array( 'data' => $this->rows() ) );

        $catalog = new Live_Catalog( $api );
        $catalog->tour( 'serengeti' );
        $catalog->tour( 'kili' );
        $catalog->tour( 'serengeti' );

        $this->assertTrue( true ); // Mockery's ->once() is the assertion.
    }

    public function test_api_failure_yields_empty_so_callers_fall_back(): void {
        $api = Mockery::mock( Api_Client::class );
        $api->shouldReceive( 'get' )->andThrow( new Api_Exception( 'down', 502 ) );

        $this->assertSame( array(), ( new Live_Catalog( $api ) )->tour( 'serengeti' ) );
    }

    public function test_failure_is_cached_so_an_outage_is_not_a_call_per_view(): void {
        $stored = array();
        Functions\when( 'set_transient' )->alias(
            static function ( $key, $value, $ttl ) use ( &$stored ) {
                $stored[] = array( $key, $value, $ttl );
                return true;
            }
        );
        $api = Mockery::mock( Api_Client::class );
        $api->shouldReceive( 'get' )->once()->andThrow( new Api_Exception( 'down', 502 ) );

        ( new Live_Catalog( $api ) )->tours();

        $this->assertSame( 'kwt_live_catalog', $stored[0][0] );
        $this->assertSame( array(), $stored[0][1] );
        $this->assertSame( 60, $stored[0][2] );
    }

    public function test_cached_snapshot_is_used_without_calling_the_api(): void {
        Functions\when( 'get_transient' )->justReturn(
            array( 'serengeti' => array( 'price' => 999, 'currency' => 'TZS' ) )
        );
        $api = Mockery::mock( Api_Client::class );
        $api->shouldNotReceive( 'get' );

        $this->assertSame( 999, ( new Live_Catalog( $api ) )->tour( 'serengeti' )['price'] );
    }

    public function test_for_post_returns_empty_when_no_instance_is_booted(): void {
        $this->assertSame( array(), Live_Catalog::for_post( 7 ) );
    }

    public function test_for_post_looks_the_tour_up_by_its_synced_slug(): void {
        Functions\when( 'get_post_meta' )->justReturn( 'serengeti' );
        $api = Mockery::mock( Api_Client::class );
        $api->shouldReceive( 'get' )->andReturn( array( 'data' => $this->rows() ) );
        Live_Catalog::set_instance( new Live_Catalog( $api ) );

        $this->assertSame( 1250000, Live_Catalog::for_post( 7 )['price'] );
    }

    public function test_flush_drops_the_snapshot_so_the_next_read_refetches(): void {
        $deleted = array();
        Functions\when( 'delete_transient' )->alias(
            static function ( $key ) use ( &$deleted ) {
                $deleted[] = $key;
                return true;
            }
        );
        $api = Mockery::mock( Api_Client::class );
        $api->shouldReceive( 'get' )->twice()->andReturn( array( 'data' => $this->rows() ) );

        $catalog = new Live_Catalog( $api );
        Live_Catalog::set_instance( $catalog );
        $catalog->tours();
        Live_Catalog::flush();
        $catalog->tours();

        $this->assertSame( array( 'kwt_live_catalog' ), $deleted );
    }

    /** get_transient stub that answers the last-good key and nothing else. */
    private function with_last_good( array $snapshot ): void {
        Functions\when( 'get_transient' )->alias(
            static function ( $key ) use ( $snapshot ) {
                return Live_Catalog::LAST_GOOD_KEY === $key ? $snapshot : false;
            }
        );
    }

    public function test_success_keeps_a_last_good_snapshot_for_a_day(): void {
        $stored = array();
        Functions\when( 'set_transient' )->alias(
            static function ( $key, $value, $ttl ) use ( &$stored ) {
                $stored[ $key ] = array( $value, $ttl );
                return true;
            }
        );
        $api = Mockery::mock( Api_Client::class );
        $api->shouldReceive( 'get' )->once()->andReturn( array( 'data' => $this->rows() ) );

        ( new Live_Catalog( $api ) )->tours();

        $this->assertSame( 86400, $stored[ Live_Catalog::LAST_GOOD_KEY ][1] );
        $this->assertSame( 1250000, $stored[ Live_Catalog::LAST_GOOD_KEY ][0]['serengeti']['price'] );
        $this->assertSame( 60, $stored[ Live_Catalog::CACHE_KEY ][1] );
    }

    public function test_rate_limit_serves_the_last_good_prices_and_retries_after_ttl(): void {
        $this->with_last_good( array( 'serengeti' => array( 'price' => 1250000, 'currency' => 'USD' ) ) );
        $stored = array();
        Functions\when( 'set_transient' )->alias(
            static function ( $key, $value, $ttl ) use ( &$stored ) {
                $stored[ $key ] = array( $value, $ttl );
                return true;
            }
        );
        $api = Mockery::mock( Api_Client::class );
        $api->shouldReceive( 'get' )->once()->andThrow( new Api_Exception( 'slow down', 429, 'rate_limited' ) );

        $this->assertSame( 1250000, ( new Live_Catalog( $api ) )->tour( 'serengeti' )['price'] );
        // The stale copy is cached for one TTL only, so the API is retried a minute later.
        $this->assertSame( 60, $stored[ Live_Catalog::CACHE_KEY ][1] );
        $this->assertArrayNotHasKey( Live_Catalog::LAST_GOOD_KEY, $stored, 'a failure must not refresh the last-good stamp' );
    }

    public function test_outage_serves_the_last_good_prices(): void {
        $this->with_last_good( array( 'serengeti' => array( 'price' => 1250000, 'currency' => 'USD' ) ) );
        $api = Mockery::mock( Api_Client::class );
        $api->shouldReceive( 'get' )->andThrow( new Api_Exception( 'KwaWingu API returned status 503.', 503 ) );

        $this->assertSame( 1250000, ( new Live_Catalog( $api ) )->tour( 'serengeti' )['price'] );
    }

    public function test_entitlement_refusal_does_not_serve_stale_live_prices(): void {
        // The operator is no longer served live data: fall back to the synced meta
        // (empty here), and tell the owner why in wp-admin.
        $this->with_last_good( array( 'serengeti' => array( 'price' => 1250000, 'currency' => 'USD' ) ) );
        $recorded = null;
        Functions\when( 'update_option' )->alias(
            static function ( $key, $value ) use ( &$recorded ) {
                $recorded = array( $key, $value );
                return true;
            }
        );
        $api = Mockery::mock( Api_Client::class );
        $api->shouldReceive( 'get' )->andThrow( new Api_Exception( 'api_access_required', 403, 'api_access_required' ) );

        $this->assertSame( array(), ( new Live_Catalog( $api ) )->tour( 'serengeti' ) );
        $this->assertSame( 'kwt_api_status', $recorded[0] );
        $this->assertSame( 'entitlement', $recorded[1]['kind'] );
    }
}
