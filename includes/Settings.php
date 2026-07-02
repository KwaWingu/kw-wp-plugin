<?php
/**
 * Plugin settings: a single serialized option array with typed getters and a sanitizer.
 *
 * @package KwaWingu\Tours
 */

namespace KwaWingu\Tours;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Plugin settings: a single serialized option array + typed getters + sanitizer.
 */
class Settings {

	const OPTION = 'kwt_settings';

	const SYNC_INTERVALS = array( 'hourly', 'twicedaily', 'daily' );
	const MEDIA_MODES    = array( 'sideload', 'hotlink' );
	const BOOKING_MODES  = array( 'redirect', 'widget', 'onsite' );

	/**
	 * Returns all stored settings as an associative array.
	 *
	 * @return array<string,mixed>
	 */
	private function all(): array {
		$stored = get_option( self::OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Returns the operator slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return (string) ( $this->all()['slug'] ?? '' );
	}

	/**
	 * Returns the public API key.
	 *
	 * @return string
	 */
	public function get_public_key(): string {
		return (string) ( $this->all()['public_key'] ?? '' );
	}

	/**
	 * Returns the private API key.
	 *
	 * @return string
	 */
	public function get_private_key(): string {
		return (string) ( $this->all()['private_key'] ?? '' );
	}

	/**
	 * Returns the sync interval, falling back to 'hourly' if invalid.
	 *
	 * @return string
	 */
	public function get_sync_interval(): string {
		$v = (string) ( $this->all()['sync_interval'] ?? 'hourly' );
		return in_array( $v, self::SYNC_INTERVALS, true ) ? $v : 'hourly';
	}

	/**
	 * Returns the media mode, falling back to 'sideload' if invalid.
	 *
	 * @return string
	 */
	public function get_media_mode(): string {
		$v = (string) ( $this->all()['media_mode'] ?? 'sideload' );
		return in_array( $v, self::MEDIA_MODES, true ) ? $v : 'sideload';
	}

	/**
	 * Returns the booking mode, falling back to 'redirect' if invalid.
	 *
	 * @return string
	 */
	public function get_booking_mode(): string {
		$v = (string) ( $this->all()['booking_mode'] ?? 'redirect' );
		return in_array( $v, self::BOOKING_MODES, true ) ? $v : 'redirect';
	}

	/**
	 * Whether to email the operator on a new on-site booking.
	 *
	 * @return bool
	 */
	public function notifications_enabled(): bool {
		return '1' === (string) ( $this->all()['notify_enabled'] ?? '' );
	}

	/**
	 * Operator notification recipient (empty → caller falls back to admin_email).
	 *
	 * @return string
	 */
	public function notification_recipient(): string {
		return (string) ( $this->all()['notify_email'] ?? '' );
	}

	/**
	 * Whether to store on-site booking guest details as leads.
	 *
	 * @return bool
	 */
	public function lead_capture_enabled(): bool {
		return '1' === (string) ( $this->all()['capture_leads'] ?? '' );
	}

	/**
	 * Sanitize callback for register_setting.
	 *
	 * @param array<string,mixed> $input Raw input values from the settings form.
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();

		$slug           = sanitize_key( strtolower( trim( (string) ( $input['slug'] ?? '' ) ) ) );
		$public_key     = sanitize_text_field( trim( (string) ( $input['public_key'] ?? '' ) ) );
		$private_key    = sanitize_text_field( trim( (string) ( $input['private_key'] ?? '' ) ) );
		$sync_interval  = (string) ( $input['sync_interval'] ?? 'hourly' );
		$media_mode     = (string) ( $input['media_mode'] ?? 'sideload' );
		$booking_mode   = (string) ( $input['booking_mode'] ?? 'redirect' );
		$notify_enabled = ! empty( $input['notify_enabled'] ) ? '1' : '';
		$notify_email   = sanitize_email( trim( (string) ( $input['notify_email'] ?? '' ) ) );
		$capture_leads  = ! empty( $input['capture_leads'] ) ? '1' : '';

		return array(
			'slug'           => $slug,
			'public_key'     => $public_key,
			'private_key'    => $private_key,
			'sync_interval'  => in_array( $sync_interval, self::SYNC_INTERVALS, true ) ? $sync_interval : 'hourly',
			'media_mode'     => in_array( $media_mode, self::MEDIA_MODES, true ) ? $media_mode : 'sideload',
			'booking_mode'   => in_array( $booking_mode, self::BOOKING_MODES, true ) ? $booking_mode : 'redirect',
			'notify_enabled' => $notify_enabled,
			'notify_email'   => $notify_email,
			'capture_leads'  => $capture_leads,
		);
	}

	/**
	 * Registers the plugin setting via WordPress admin_init.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			'admin_init',
			function () {
				register_setting(
					'kwt_settings_group',
					self::OPTION,
					array(
						'type'              => 'array',
						'sanitize_callback' => array( $this, 'sanitize' ),
						'default'           => array(),
					)
				);
			}
		);
	}
}
