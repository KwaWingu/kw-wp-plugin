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
}
