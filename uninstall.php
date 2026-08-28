<?php
/**
 * Uninstall cleanup. Removes plugin options + the scheduled sync.
 * CPT content (tours) is intentionally left in place so the site does not lose pages.
 *
 * @package KwaWingu\Tours
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'kwt_settings' );
delete_option( 'kwt_sync_status' );
delete_option( 'kwt_api_status' );
delete_transient( 'kwt_live_catalog' );
delete_transient( 'kwt_live_catalog_last_good' );
wp_clear_scheduled_hook( 'kwt_sync_cron' );
wp_clear_scheduled_hook( 'kwt_sync_push' );
