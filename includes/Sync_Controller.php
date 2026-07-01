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
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_sync_now' ) );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, $this->settings->get_sync_interval(), self::CRON_HOOK );
		}
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
