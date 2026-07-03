<?php
/**
 * Render function for kwawingu/inquiry-form.
 *
 * @package KwaWingu\Tours
 */

if ( ! function_exists( 'kwt_render_inquiry_form' ) ) {
	/**
	 * Render callback for kwawingu/inquiry-form.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 * @param string              $content    Block inner content (unused).
	 * @return string
	 */
	function kwt_render_inquiry_form( array $attributes, string $content = '' ): string {
		if ( function_exists( 'wp_enqueue_script' ) ) {
			wp_enqueue_script( 'kwt-proxy' );
		}
		$heading   = ! empty( $attributes['heading'] ) ? (string) $attributes['heading'] : __( 'Send us an inquiry', 'kwawingu-tours' );
		$tour_slug = ! empty( $attributes['tourSlug'] ) ? (string) $attributes['tourSlug'] : '';

		$l_name     = esc_html__( 'Full name', 'kwawingu-tours' );
		$l_email    = esc_html__( 'Email address', 'kwawingu-tours' );
		$l_phone    = esc_html__( 'Phone (optional)', 'kwawingu-tours' );
		$l_date     = esc_html__( 'Preferred date', 'kwawingu-tours' );
		$l_adults   = esc_html__( 'Adults', 'kwawingu-tours' );
		$l_children = esc_html__( 'Children', 'kwawingu-tours' );
		$l_message  = esc_html__( 'Message (optional)', 'kwawingu-tours' );
		$l_submit   = esc_html__( 'Send inquiry', 'kwawingu-tours' );

		return '<div class="kwt-inquiry-wrap">'
			. '<h3 class="kwt-inquiry__heading">' . esc_html( $heading ) . '</h3>'
			. '<form class="kwt-inquiry" data-tour="' . esc_attr( $tour_slug ) . '" novalidate>'
			. '<label>' . $l_name . ' <input type="text" name="name" required /></label>'
			. '<label>' . $l_email . ' <input type="email" name="email" required /></label>'
			. '<label>' . $l_phone . ' <input type="tel" name="phone" /></label>'
			. '<label>' . $l_date . ' <input type="date" name="date" /></label>'
			. '<label>' . $l_adults . ' <input type="number" name="adults" min="1" value="2" /></label>'
			. '<label>' . $l_children . ' <input type="number" name="children" min="0" value="0" /></label>'
			. '<label>' . $l_message . ' <textarea name="message" rows="4"></textarea></label>'
			. '<input type="text" name="kwt_hp" class="kwt-hp" aria-hidden="true" tabindex="-1" autocomplete="off" />'
			. '<button type="submit" class="kwt-btn">' . $l_submit . '</button>'
			. '<p class="kwt-inquiry__status" aria-live="polite"></p>'
			. '</form>'
			. '</div>';
	}
}
