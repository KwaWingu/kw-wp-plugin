<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Settings;
use KwaWingu\Tours\Sync;
use KwaWingu\Tours\Sync_Controller;
use Mockery;
use PHPUnit\Framework\TestCase;

class SyncControllerTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    public function test_run_and_store_persists_summary(): void {
        $sync = Mockery::mock( Sync::class );
        $sync->shouldReceive( 'run' )->once()->andReturn(
            array( 'created' => 2, 'updated' => 1, 'unpublished' => 0, 'errors' => array() )
        );

        $stored = array();
        Functions\when( 'update_option' )->alias( static function ( $k, $v ) use ( &$stored ) {
            $stored[ $k ] = $v;
            return true;
        } );
        Functions\when( 'time' )->justReturn( 1000 );

        $ctrl = new Sync_Controller( $sync, new Settings() );
        $summary = $ctrl->run_and_store();

        $this->assertSame( 2, $summary['created'] );
        $this->assertSame( 2, $stored['kwt_sync_status']['created'] );
        $this->assertSame( 1000, $stored['kwt_sync_status']['ran_at'] );
    }

    public function test_register_schedules_cron_when_missing(): void {
        Functions\when( 'get_option' )->justReturn( array( 'sync_interval' => 'hourly' ) );
        Functions\when( 'wp_next_scheduled' )->justReturn( false );
        $scheduled = array();
        Functions\when( 'wp_schedule_event' )->alias( static function ( $ts, $recur, $hook ) use ( &$scheduled ) {
            $scheduled[] = array( $recur, $hook );
            return true;
        } );
        Functions\when( 'time' )->justReturn( 0 );

        $sync = Mockery::mock( Sync::class );
        ( new Sync_Controller( $sync, new Settings() ) )->register();

        $this->assertSame( array( 'hourly', 'kwt_sync_cron' ), $scheduled[0] );
        $this->assertNotFalse( has_action( 'kwt_sync_cron' ) );
        $this->assertNotFalse( has_action( 'admin_post_kwt_sync_now' ) );
    }
}
