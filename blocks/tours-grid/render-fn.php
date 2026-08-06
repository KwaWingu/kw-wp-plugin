<?php
/**
 * Server render for kwawingu/tours-grid.
 *
 * @package KwaWingu\Tours
 */

use KwaWingu\Tours\Live_Catalog;
use KwaWingu\Tours\View;

if ( ! function_exists( 'kwt_render_tours_grid' ) ) {
	/**
	 * Render callback for kwawingu/tours-grid.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 * @param string              $content    Block inner content (unused).
	 */
	function kwt_render_tours_grid( array $attributes, string $content = '' ): string {
		$limit = isset( $attributes['limit'] ) ? (int) $attributes['limit'] : 12;

		// Tests inject a query via _query; production builds one from View.
		$query = $attributes['_query'] ?? null;
		if ( null === $query ) {
			$args = array( 'posts_per_page' => $limit );
			if ( ! empty( $attributes['type'] ) ) {
				$args['meta_query'] = array(
					array(
						'key'   => 'kwt_type',
						'value' => (string) $attributes['type'],
					),
				);
			}
			$query = View::tour_query( $args );
		}

		if ( ! $query->have_posts() ) {
			return '<div class="kwt-tours-grid kwt-empty">' . esc_html__( 'No tours yet.', 'kwawingu-tours' ) . '</div>';
		}

		$out = '<div class="kwt-tours-grid">';
		while ( $query->have_posts() ) {
			$query->the_post();
			$id = (int) get_the_ID();
			// Price and availability are read live; the stored meta is the fallback when
			// the API call fails, so a card never renders an error or a blank price.
			$live     = Live_Catalog::for_post( $id );
			$price    = isset( $live['price'] ) && null !== $live['price']
				? (int) $live['price']
				: (int) get_post_meta( $id, 'kwt_price', true );
			$currency = ! empty( $live['currency'] )
				? (string) $live['currency']
				: (string) get_post_meta( $id, 'kwt_currency', true );
			$sold_out = ! empty( $live['soldOut'] );
			$days     = (int) get_post_meta( $id, 'kwt_duration_days', true );
			$img      = (string) get_the_post_thumbnail_url( $id, 'medium' );
			$out     .= '<article class="kwt-tour-card">';
			if ( $img ) {
				$out .= '<img class="kwt-tour-card__img" src="' . esc_url( $img ) . '" alt="' . esc_attr( get_the_title() ) . '" loading="lazy" />';
			}
			$out .= '<h3 class="kwt-tour-card__title"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h3>';
			if ( $days > 0 ) {
				/* translators: %d: number of days */
				$out .= '<p class="kwt-tour-card__meta">' . esc_html( sprintf( _n( '%d day', '%d days', $days, 'kwawingu-tours' ), $days ) ) . '</p>';
			}
			if ( $price > 0 ) {
				$out .= '<p class="kwt-tour-card__price">' . esc_html( View::money( $price, '' !== $currency ? $currency : 'TZS' ) ) . '</p>';
			}
			if ( $sold_out ) {
				$out .= '<p class="kwt-tour-card__soldout">' . esc_html__( 'Sold out', 'kwawingu-tours' ) . '</p>';
			}
			$out .= '</article>';
		}
		$out .= '</div>';
		if ( function_exists( 'wp_reset_postdata' ) ) {
			wp_reset_postdata();
		}
		return $out;
	}
}
