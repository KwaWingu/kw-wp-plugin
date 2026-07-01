<?php
/**
 * Server render for kwawingu/tour-detail.
 *
 * @package KwaWingu\Tours
 */

use KwaWingu\Tours\View;

if ( ! function_exists( 'kwt_render_tour_detail' ) ) {
	/**
	 * Render callback for kwawingu/tour-detail.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 * @param string              $content    Block inner content (unused).
	 */
	function kwt_render_tour_detail( array $attributes, string $content = '' ): string {
		$id = ! empty( $attributes['postId'] ) ? (int) $attributes['postId'] : (int) get_the_ID();
		if ( $id <= 0 ) {
			return '';
		}
		$price      = (int) get_post_meta( $id, 'kwt_price', true );
		$days       = (int) get_post_meta( $id, 'kwt_duration_days', true );
		$difficulty = (string) get_post_meta( $id, 'kwt_difficulty', true );
		$img        = (string) get_the_post_thumbnail_url( $id, 'large' );

		$out  = '<div class="kwt-tour-detail">';
		$out .= '<h1 class="kwt-tour-detail__title">' . esc_html( get_the_title( $id ) ) . '</h1>';
		if ( $img ) {
			$out .= '<img class="kwt-tour-detail__cover" src="' . esc_url( $img ) . '" alt="' . esc_attr( get_the_title( $id ) ) . '" />';
		}
		$out .= '<ul class="kwt-tour-detail__facts">';
		if ( $days > 0 ) {
			/* translators: %d: number of days */
			$out .= '<li>' . esc_html( sprintf( _n( '%d day', '%d days', $days, 'kwawingu-tours' ), $days ) ) . '</li>';
		}
		if ( '' !== $difficulty ) {
			$out .= '<li>' . esc_html( $difficulty ) . '</li>';
		}
		if ( $price > 0 ) {
			$out .= '<li class="kwt-price">' . esc_html( View::money( $price ) ) . '</li>';
		}
		$out     .= '</ul>';
		$kwt_post = get_post( $id );
		$kwt_body = $kwt_post ? apply_filters( 'the_content', $kwt_post->post_content ) : '';
		$out     .= '<div class="kwt-tour-detail__body">' . wp_kses_post( $kwt_body ) . '</div>';
		$booking  = View::tour_booking_url( $id );
		if ( '' !== $booking ) {
			$out .= '<a class="kwt-book-btn" href="' . esc_url( $booking ) . '">' . esc_html__( 'Book this tour', 'kwawingu-tours' ) . '</a>';
		}
		$out .= '</div>';
		return $out;
	}
}
