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
        $this->assertNotFalse( has_action( 'kwt_sync_push' ) );
        $this->assertNotFalse( has_action( 'admin_post_kwt_sync_now' ) );
        // Without this hook, changing the interval in settings did nothing until the
        // plugin was deactivated and reactivated.
        $this->assertNotFalse( has_action( 'update_option_kwt_settings' ) );
    }

    public function test_changing_the_interval_reschedules_the_recurring_event(): void {
        Functions\when( 'get_option' )->justReturn( array( 'sync_interval' => 'daily' ) );
        $cleared = array();
        Functions\when( 'wp_clear_scheduled_hook' )->alias( static function ( $hook ) use ( &$cleared ) {
            $cleared[] = $hook;
            return 1;
        } );
        $scheduled = array();
        Functions\when( 'wp_schedule_event' )->alias( static function ( $ts, $recur, $hook ) use ( &$scheduled ) {
            $scheduled[] = array( $recur, $hook );
            return true;
        } );
        Functions\when( 'time' )->justReturn( 0 );

        $ctrl = new Sync_Controller( Mockery::mock( Sync::class ), new Settings() );
        $ctrl->on_settings_saved( array( 'sync_interval' => 'hourly' ), array( 'sync_interval' => 'daily' ) );

        $this->assertSame( array( 'kwt_sync_cron' ), $cleared );
        $this->assertSame( array( 'daily', 'kwt_sync_cron' ), $scheduled[0] );
    }

    public function test_saving_settings_without_an_interval_change_leaves_cron_alone(): void {
        Functions\when( 'get_option' )->justReturn( array( 'sync_interval' => 'daily' ) );
        Functions\expect( 'wp_clear_scheduled_hook' )->never();
        Functions\expect( 'wp_schedule_event' )->never();

        $ctrl = new Sync_Controller( Mockery::mock( Sync::class ), new Settings() );
        $ctrl->on_settings_saved( array( 'sync_interval' => 'daily' ), array( 'sync_interval' => 'daily' ) );

        $this->assertTrue( true ); // The never() expectations are the assertion.
    }

    public function test_reschedule_rejects_an_interval_wordpress_does_not_know(): void {
        Functions\when( 'wp_clear_scheduled_hook' )->justReturn( 1 );
        $scheduled = array();
        Functions\when( 'wp_schedule_event' )->alias( static function ( $ts, $recur, $hook ) use ( &$scheduled ) {
            $scheduled[] = $recur;
            return true;
        } );
        Functions\when( 'time' )->justReturn( 0 );

        ( new Sync_Controller( Mockery::mock( Sync::class ), new Settings() ) )->reschedule( 'every-second' );

        $this->assertSame( array( 'hourly' ), $scheduled );
    }

    public function test_schedule_immediate_queues_a_one_off_run(): void {
        Functions\when( 'wp_next_scheduled' )->justReturn( false );
        Functions\when( 'time' )->justReturn( 1234 );
        $single = array();
        Functions\when( 'wp_schedule_single_event' )->alias( static function ( $ts, $hook ) use ( &$single ) {
            $single[] = array( $ts, $hook );
            return true;
        } );

        $ctrl = new Sync_Controller( Mockery::mock( Sync::class ), new Settings() );

        $this->assertTrue( $ctrl->schedule_immediate() );
        $this->assertSame( array( array( 1234, 'kwt_sync_push' ) ), $single );
    }

    public function test_schedule_immediate_coalesces_when_a_run_is_already_pending(): void {
        Functions\when( 'wp_next_scheduled' )->justReturn( 999 );
        Functions\expect( 'wp_schedule_single_event' )->never();

        $ctrl = new Sync_Controller( Mockery::mock( Sync::class ), new Settings() );

        $this->assertFalse( $ctrl->schedule_immediate() );
    }
}
