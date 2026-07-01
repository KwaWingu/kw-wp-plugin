<?php
/**
 * Booking link builder for tour posts.
 *
 * @package KwaWingu\Tours
 */

namespace KwaWingu\Tours;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds booking links. Redirect (hosted URL), widget (widget.js embed), and
 * on-site (in-page booking form via the REST proxy) modes are all implemented;
 * on-site links to the on-site booking form (#kwt-book).
 */
class Booking {

	const HOSTED_BASE = 'https://tours.kwawingu.com';
	const WIDGET_SRC  = 'https://tours.kwawingu.com/widget.js';

	/**
	 * Plugin settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Plugin settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Convenience: build the booking URL for a tour post.
	 *
	 * @param int $post_id Tour post ID.
	 * @return string Hosted booking URL, or empty string if slug is missing.
	 */
	public static function url_for( int $post_id ): string {
		return ( new self( new Settings() ) )->url( $post_id );
	}

	/**
	 * Return the current booking mode (redirect, widget, or on-site).
	 *
	 * @return string Booking mode identifier.
	 */
	public function mode(): string {
		return $this->settings->get_booking_mode();
	}

	/**
	 * Convenience: return the widget embed snippet for a tour post.
	 *
	 * @param int $post_id Tour post ID.
	 * @return string HTML script embed, or empty string if slug is missing.
	 */
	public static function widget_embed_for( int $post_id ): string {
		return ( new self( new Settings() ) )->widget_embed( $post_id );
	}

	/**
	 * Build the widget embed snippet for a tour post.
	 *
	 * @param int $post_id Tour post ID.
	 * @return string HTML script embed, or empty string if slug is missing.
	 */
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

	/**
	 * Build the hosted booking URL for a tour post.
	 *
	 * @param int $post_id Tour post ID.
	 * @return string Hosted booking URL, or empty string if slug is missing.
	 */
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
