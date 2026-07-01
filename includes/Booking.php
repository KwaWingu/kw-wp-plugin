<?php
namespace KwaWingu\Tours;

/**
 * Builds booking links. Redirect mode (hosted booking URL) and widget mode
 * (widget.js embed) are implemented; on-site mode arrives in v0.4 and falls
 * back to redirect until then.
 */
class Booking {

    const HOSTED_BASE = 'https://tours.kwawingu.com';
    const WIDGET_SRC  = 'https://tours.kwawingu.com/widget.js';

    /** @var Settings */
    private $settings;

    public function __construct( Settings $settings ) {
        $this->settings = $settings;
    }

    /** Convenience: build the booking URL for a tour post. */
    public static function url_for( int $post_id ): string {
        return ( new self( new Settings() ) )->url( $post_id );
    }

    public function mode(): string {
        return $this->settings->get_booking_mode();
    }

    public static function widget_embed_for( int $post_id ): string {
        return ( new self( new Settings() ) )->widget_embed( $post_id );
    }

    public function widget_embed( int $post_id ): string {
        $slug      = $this->settings->get_slug();
        $tour_slug = (string) get_post_meta( $post_id, 'kwt_slug', true );
        if ( '' === $slug || '' === $tour_slug ) {
            return '';
        }
        return '<script src="' . esc_url( self::WIDGET_SRC ) . '"'
            . ' data-operator="' . esc_attr( $slug ) . '"'
            . ' data-tour="' . esc_attr( $tour_slug ) . '" async></script>';
    }

    public function url( int $post_id ): string {
        $slug      = $this->settings->get_slug();
        $tour_slug = (string) get_post_meta( $post_id, 'kwt_slug', true );
        if ( '' === $slug || '' === $tour_slug ) {
            return '';
        }
        // All modes resolve to the hosted flow in v0.2.
        return self::HOSTED_BASE . '/' . rawurlencode( $slug ) . '/tours/' . rawurlencode( $tour_slug );
    }
}
