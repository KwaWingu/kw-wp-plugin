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

delete_option( 'kwawingu_tours_settings' );
delete_option( 'kwawingu_tours_sync_status' );
delete_option( 'kwawingu_tours_api_status' );
delete_transient( 'kwawingu_tours_live_catalog' );
delete_transient( 'kwawingu_tours_live_catalog_last_good' );
wp_clear_scheduled_hook( 'kwawingu_tours_sync_cron' );
wp_clear_scheduled_hook( 'kwawingu_tours_sync_push' );
