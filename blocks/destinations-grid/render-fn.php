<?php
/**
 * Render function for kwawingu/destinations-grid.
 *
 * @package KwaWingu\Tours
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use KwaWingu\Tours\Booking;
use KwaWingu\Tours\Cpt;
use KwaWingu\Tours\Settings;

if ( ! function_exists( 'kwawingu_tours_destination_url' ) ) {
	/**
	 * Where a destination card goes.
	 *
	 * The hosted storefront page for the place — description, highlights, best months, the
	 * official park tariff and the operator's tours that go there — when the sync recorded the
	 * API's slug and the operator slug is configured. The local kwt_destination permalink only
	 * as a fallback (a post synced before 1.14.2 has no slug yet; the next sync adds it), so a
	 * card never links to an empty local page when the real one exists.
	 *
	 * @param int $post_id kwt_destination post ID.
	 * @return string
	 */
	function kwawingu_tours_destination_url( int $post_id ): string {
		$slug     = (string) get_post_meta( $post_id, 'kwt_slug', true );
		$operator = ( new Settings() )->get_slug();
		if ( '' !== $slug && '' !== $operator ) {
			return Booking::hosted_base() . '/' . rawurlencode( $operator ) . '/destinations/' . rawurlencode( $slug );
		}
		$link = get_permalink( $post_id );
		return is_string( $link ) ? $link : '';
	}
}

if ( ! function_exists( 'kwawingu_tours_render_destinations_grid' ) ) {
	/**
	 * Render callback for kwawingu/destinations-grid.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 * @param string              $content    Block inner content (unused).
	 */
	function kwawingu_tours_render_destinations_grid( array $attributes, string $content = '' ): string {
		$limit = isset( $attributes['limit'] ) ? (int) $attributes['limit'] : 12;
		$query = $attributes['_query'] ?? null;
		if ( null === $query ) {
			$query = new \WP_Query(
				array(
					'post_type'      => Cpt::DESTINATION,
					'post_status'    => 'publish',
					'posts_per_page' => $limit,
				)
			);
		}
		if ( ! $query->have_posts() ) {
			return '<div class="kwt-destinations-grid kwt-empty">' . esc_html__( 'No destinations yet.', 'kwawingu-tours' ) . '</div>';
		}
		$out = '<div class="kwt-destinations-grid">';
		while ( $query->have_posts() ) {
			$query->the_post();
			$img   = (string) get_the_post_thumbnail_url( get_the_ID(), 'medium' );
			$title = get_the_title();
			$out  .= '<article class="kwt-destination-card">';
			if ( $img ) {
				$out .= '<img class="kwt-destination-card__img" src="' . esc_url( $img ) . '" alt="' . esc_attr( $title ) . '" loading="lazy" />';
			}
			$out .= '<h3 class="kwt-destination-card__title"><a href="' . esc_url( kwawingu_tours_destination_url( (int) get_the_ID() ) ) . '">' . esc_html( $title ) . '</a></h3>';
			$out .= '</article>';
		}
		$out .= '</div>';
		if ( function_exists( 'wp_reset_postdata' ) ) {
			wp_reset_postdata();
		}
		return $out;
	}
}
