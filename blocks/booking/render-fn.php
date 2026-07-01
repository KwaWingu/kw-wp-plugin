<?php
/**
 * Render function for kwawingu/booking (on-site booking form shell).
 *
 * @package KwaWingu\Tours
 */

if ( ! function_exists( 'kwt_render_booking_form' ) ) {
	/**
	 * Render callback for kwawingu/booking.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 * @param string              $content    Block inner content (unused).
	 */
	function kwt_render_booking_form( array $attributes, string $content = '' ): string {
		if ( function_exists( 'wp_enqueue_script' ) ) {
			wp_enqueue_script( 'kwt-proxy' );
		}
		$id        = (int) get_the_ID();
		$tour_slug = ! empty( $attributes['tourSlug'] ) ? (string) $attributes['tourSlug'] : (string) get_post_meta( $id, 'kwt_slug', true );

		$l_first  = esc_html__( 'First name', 'kwawingu-tours' );
		$l_last   = esc_html__( 'Last name', 'kwawingu-tours' );
		$l_email  = esc_html__( 'Email', 'kwawingu-tours' );
		$l_phone  = esc_html__( 'Mobile money number', 'kwawingu-tours' );
		$l_dep    = esc_html__( 'Departure', 'kwawingu-tours' );
		$l_dep_ph = esc_html__( 'Select a departure…', 'kwawingu-tours' );
		$l_adults = esc_html__( 'Adults', 'kwawingu-tours' );
		$l_child  = esc_html__( 'Children', 'kwawingu-tours' );
		$l_infant = esc_html__( 'Infants', 'kwawingu-tours' );
		$l_book   = esc_html__( 'Book & pay', 'kwawingu-tours' );

		return '<form id="kwt-book" class="kwt-booking" data-tour="' . esc_attr( $tour_slug ) . '">'
			. '<label>' . $l_dep . ' <select name="departure" class="kwt-booking__departure" required>'
			. '<option value="">' . $l_dep_ph . '</option></select></label>'
			. '<label>' . $l_first . ' <input type="text" name="firstName" required /></label>'
			. '<label>' . $l_last . ' <input type="text" name="lastName" required /></label>'
			. '<label>' . $l_email . ' <input type="email" name="email" required /></label>'
			. '<label>' . $l_phone . ' <input type="tel" name="phone" required placeholder="2557…" /></label>'
			. '<label>' . $l_adults . ' <input type="number" name="adults" min="1" value="2" /></label>'
			. '<label>' . $l_child . ' <input type="number" name="children" min="0" value="0" /></label>'
			. '<label>' . $l_infant . ' <input type="number" name="infants" min="0" value="0" /></label>'
			. '<p class="kwt-booking__price" aria-live="polite"></p>'
			. '<button type="submit" class="kwt-btn">' . $l_book . '</button>'
			. '<p class="kwt-booking__status" aria-live="polite"></p>'
			. '</form>';
	}
}
