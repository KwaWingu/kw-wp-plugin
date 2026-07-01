<?php
namespace KwaWingu\Tours;

/**
 * Plugin settings: a single serialized option array + typed getters + sanitizer.
 */
class Settings {

    const OPTION = 'kwt_settings';

    const SYNC_INTERVALS = array( 'hourly', 'twicedaily', 'daily' );
    const MEDIA_MODES    = array( 'sideload', 'hotlink' );
    const BOOKING_MODES  = array( 'redirect', 'widget', 'onsite' );

    /** @return array<string,mixed> */
    private function all(): array {
        $stored = get_option( self::OPTION, array() );
        return is_array( $stored ) ? $stored : array();
    }

    public function get_slug(): string {
        return (string) ( $this->all()['slug'] ?? '' );
    }

    public function get_public_key(): string {
        return (string) ( $this->all()['public_key'] ?? '' );
    }

    public function get_private_key(): string {
        return (string) ( $this->all()['private_key'] ?? '' );
    }

    public function get_sync_interval(): string {
        $v = (string) ( $this->all()['sync_interval'] ?? 'hourly' );
        return in_array( $v, self::SYNC_INTERVALS, true ) ? $v : 'hourly';
    }

    public function get_media_mode(): string {
        $v = (string) ( $this->all()['media_mode'] ?? 'sideload' );
        return in_array( $v, self::MEDIA_MODES, true ) ? $v : 'sideload';
    }

    public function get_booking_mode(): string {
        $v = (string) ( $this->all()['booking_mode'] ?? 'redirect' );
        return in_array( $v, self::BOOKING_MODES, true ) ? $v : 'redirect';
    }

    /**
     * register_setting sanitize callback.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function sanitize( $input ): array {
        $input = is_array( $input ) ? $input : array();

        $slug          = sanitize_key( strtolower( trim( (string) ( $input['slug'] ?? '' ) ) ) );
        $public_key    = sanitize_text_field( trim( (string) ( $input['public_key'] ?? '' ) ) );
        $private_key   = sanitize_text_field( trim( (string) ( $input['private_key'] ?? '' ) ) );
        $sync_interval = (string) ( $input['sync_interval'] ?? 'hourly' );
        $media_mode    = (string) ( $input['media_mode'] ?? 'sideload' );
        $booking_mode  = (string) ( $input['booking_mode'] ?? 'redirect' );

        return array(
            'slug'          => $slug,
            'public_key'    => $public_key,
            'private_key'   => $private_key,
            'sync_interval' => in_array( $sync_interval, self::SYNC_INTERVALS, true ) ? $sync_interval : 'hourly',
            'media_mode'    => in_array( $media_mode, self::MEDIA_MODES, true ) ? $media_mode : 'sideload',
            'booking_mode'  => in_array( $booking_mode, self::BOOKING_MODES, true ) ? $booking_mode : 'redirect',
        );
    }

    /** Register the setting (admin page rendering wired in Task 3). */
    public function register(): void {
        add_action( 'admin_init', function () {
            register_setting(
                'kwt_settings_group',
                self::OPTION,
                array(
                    'type'              => 'array',
                    'sanitize_callback' => array( $this, 'sanitize' ),
                    'default'           => array(),
                )
            );
        } );
    }
}
