<?php
/**
 * Render function for kwawingu/availability-calendar (server shell).
 *
 * @package KwaWingu\Tours
 */

if ( ! function_exists( 'kwt_render_availability_calendar' ) ) {
	/**
	 * Render callback for kwawingu/availability-calendar.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 * @param string              $content    Inner content (unused).
	 * @return string
	 */
	function kwt_render_availability_calendar( array $attributes, string $content = '' ): string {
		if ( function_exists( 'wp_enqueue_script' ) ) {
			wp_enqueue_script( 'kwt-grid' );
			wp_enqueue_script( 'kwt-proxy' );
		}
		$id        = (int) get_the_ID();
		$tour_slug = ! empty( $attributes['tourSlug'] ) ? (string) $attributes['tourSlug'] : (string) get_post_meta( $id, 'kwt_slug', true );
		return '<div class="kwt-availcal" data-tour="' . esc_attr( $tour_slug ) . '">'
			. '<div class="kwt-availcal__head"></div>'
			. '<div class="kwt-availcal__grid" aria-live="polite"></div>'
			. '</div>';
	}
}
