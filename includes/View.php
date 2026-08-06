<?php
/**
 * Shared presentation helpers for block and shortcode renderers.
 *
 * @package KwaWingu\Tours
 */

namespace KwaWingu\Tours;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Small presentation helpers shared by block/shortcode renderers.
 */
final class View {

	/**
	 * Private constructor — this class is a static utility class.
	 */
	private function __construct() {}

	/**
	 * Format an integer amount, e.g. 450000 => "TZS 450,000".
	 *
	 * The currency belongs to the operator being paid, so it is passed in when known;
	 * TZS remains the default for stored values written before currency was synced.
	 *
	 * @param int    $amount   Amount in the currency's whole units (integer).
	 * @param string $currency ISO currency code. Defaults to TZS.
	 * @return string Formatted currency string.
	 */
	public static function money( int $amount, string $currency = 'TZS' ): string {
		$currency = preg_replace( '/[^A-Za-z]/', '', $currency );
		$currency = '' === (string) $currency ? 'TZS' : strtoupper( (string) $currency );
		return $currency . ' ' . number_format( $amount, 0, '.', ',' );
	}

	/**
	 * A WP_Query for published tours with sane defaults, merged with $args.
	 *
	 * @param array<string,mixed> $args WP_Query arguments to merge with defaults.
	 * @return \WP_Query Configured query object.
	 */
	public static function tour_query( array $args = array() ): \WP_Query {
		$defaults = array(
			'post_type'      => Cpt::TOUR,
			'post_status'    => 'publish',
			'posts_per_page' => 12,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		);
		return new \WP_Query( array_merge( $defaults, $args ) );
	}

	/**
	 * Booking URL for a tour post. Real implementation lands in Task 5 (Booking);
	 * returns '' until then so callers have a stable call site.
	 *
	 * @param int $post_id Tour post ID.
	 * @return string Booking URL, or empty string if Booking class is not available.
	 */
	public static function tour_booking_url( int $post_id ): string {
		if ( class_exists( __NAMESPACE__ . '\\Booking' ) ) {
			return Booking::url_for( $post_id );
		}
		return '';
	}
}
