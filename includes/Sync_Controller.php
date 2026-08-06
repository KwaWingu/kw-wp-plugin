<?php
/**
 * WP-Cron scheduler and manual sync action handler.
 *
 * @package KwaWingu\Tours
 */

namespace KwaWingu\Tours;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Drives Sync via WP-Cron + a manual "Sync now" admin action, and records status.
 */
class Sync_Controller {

	const CRON_HOOK  = 'kwt_sync_cron';
	const PUSH_HOOK  = 'kwt_sync_push';
	const STATUS_OPT = 'kwt_sync_status';
	const ACTION     = 'kwt_sync_now';

	/**
	 * Sync service instance.
	 *
	 * @var Sync
	 */
	private $sync;

	/**
	 * Plugin settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Sync     $sync     Sync service instance.
	 * @param Settings $settings Plugin settings instance.
	 */
	public function __construct( Sync $sync, Settings $settings ) {
		$this->sync     = $sync;
		$this->settings = $settings;
	}

	/**
	 * Register cron hook, admin action hook, and schedule the cron event if needed.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( self::CRON_HOOK, array( $this, 'run_and_store' ) );
		add_action( self::PUSH_HOOK, array( $this, 'run_and_store' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_sync_now' ) );
		// Without this, changing the interval in settings did nothing until the plugin
		// was deactivated and reactivated: the recurring event keeps its old schedule.
		add_action( 'update_option_' . Settings::OPTION, array( $this, 'on_settings_saved' ), 10, 2 );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, $this->settings->get_sync_interval(), self::CRON_HOOK );
		}
	}

	/**
	 * Reschedule the recurring sync when the saved interval actually changed.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $new_value New option value (already stored).
	 * @return void
	 */
	public function on_settings_saved( $old_value, $new_value ): void {
		unset( $new_value );
		$old = is_array( $old_value ) ? (string) ( $old_value['sync_interval'] ?? 'hourly' ) : 'hourly';
		$new = $this->settings->get_sync_interval();
		if ( $old === $new ) {
			return;
		}
		$this->reschedule( $new );
	}

	/**
	 * Clear and re-create the recurring sync event on the given interval.
	 *
	 * @param string $interval One of Settings::SYNC_INTERVALS.
	 * @return void
	 */
	public function reschedule( string $interval ): void {
		if ( ! in_array( $interval, Settings::SYNC_INTERVALS, true ) ) {
			$interval = 'hourly';
		}
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_schedule_event( time() + 60, $interval, self::CRON_HOOK );
	}

	/**
	 * Queue a one-off sync to run as soon as WP-Cron next fires.
	 *
	 * The catalog sync makes an upstream API call per run and can sideload media for
	 * every tour, so it is never run inside the pushing request — the caller would time
	 * out. Repeat pushes inside the same pending window coalesce into the queued run.
	 *
	 * @return bool True if this call queued the run, false if one was already pending.
	 */
	public function schedule_immediate(): bool {
		if ( wp_next_scheduled( self::PUSH_HOOK ) ) {
			return false;
		}
		wp_schedule_single_event( time(), self::PUSH_HOOK );
		// Nudge WP-Cron so the queued run does not wait for a visitor.
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
		return true;
	}

	/**
	 * Run the sync and persist the summary to a WP option.
	 *
	 * @return array{ran_at:int,created:int,updated:int,unpublished:int,errors:array<int,string>} Sync summary.
	 */
	public function run_and_store(): array {
		$summary           = $this->sync->run();
		$summary['ran_at'] = time();
		update_option( self::STATUS_OPT, $summary );
		return $summary;
	}

	/**
	 * Handle the manual "Sync now" admin POST action.
	 *
	 * Verifies capability and nonce, runs the sync, then redirects back to the settings page.
	 *
	 * @return void
	 */
	public function handle_sync_now(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'kwawingu-tours' ) );
		}
		check_admin_referer( self::ACTION );
		$this->run_and_store();
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'kwawingu-tours',
					'kwt_synced' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}
}
