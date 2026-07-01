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
wp_clear_scheduled_hook( 'kwt_sync_cron' );
