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

		$l_name  = esc_html__( 'Full name', 'kwawingu-tours' );
		$l_email = esc_html__( 'Email', 'kwawingu-tours' );
		$l_phone = esc_html__( 'Mobile money number', 'kwawingu-tours' );
		$l_date  = esc_html__( 'Date', 'kwawingu-tours' );
		$l_pax   = esc_html__( 'Guests', 'kwawingu-tours' );
		$l_book  = esc_html__( 'Book & pay', 'kwawingu-tours' );

		return '<form id="kwt-book" class="kwt-booking" data-tour="' . esc_attr( $tour_slug ) . '">'
			. '<label>' . $l_name . ' <input type="text" name="name" required /></label>'
			. '<label>' . $l_email . ' <input type="email" name="email" required /></label>'
			. '<label>' . $l_phone . ' <input type="tel" name="phone" required placeholder="2557…" /></label>'
			. '<label>' . $l_date . ' <input type="date" name="date" required /></label>'
			. '<label>' . $l_pax . ' <input type="number" name="pax" min="1" value="1" /></label>'
			. '<button type="submit" class="kwt-btn">' . $l_book . '</button>'
			. '<p class="kwt-booking__status" aria-live="polite"></p>'
			. '</form>';
	}
}
