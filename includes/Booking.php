<?php
namespace KwaWingu\Tours;

/**
 * Builds booking links. v0.2 implements redirect mode (widget/onsite fall back
 * to redirect until v0.3/v0.4).
 */
class Booking {

    const HOSTED_BASE = 'https://tours.kwawingu.com';

    /** @var Settings */
    private $settings;

    public function __construct( Settings $settings ) {
        $this->settings = $settings;
    }

    /** Convenience: build the booking URL for a tour post. */
    public static function url_for( int $post_id ): string {
        return ( new self( new Settings() ) )->url( $post_id );
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
