<?php
/**
 * Render function for kwawingu/calculator.
 *
 * @package KwaWingu\Tours
 */

if ( ! function_exists( 'kwt_render_calculator' ) ) {
	/**
	 * Render callback for kwawingu/calculator.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 * @param string              $content    Block inner content (unused).
	 */
	function kwt_render_calculator( array $attributes, string $content = '' ): string {
		if ( function_exists( 'wp_enqueue_script' ) ) {
			wp_enqueue_script( 'kwt-proxy' );
		}
		$l_adults   = esc_html__( 'Adults', 'kwawingu-tours' );
		$l_children = esc_html__( 'Children', 'kwawingu-tours' );
		$l_nights   = esc_html__( 'Nights', 'kwawingu-tours' );
		$l_estimate = esc_html__( 'Estimate', 'kwawingu-tours' );
		return '<form class="kwt-calculator">'
			. '<label>' . $l_adults . ' <input type="number" name="adults" min="1" value="2" /></label>'
			. '<label>' . $l_children . ' <input type="number" name="children" min="0" value="0" /></label>'
			. '<label>' . $l_nights . ' <input type="number" name="nights" min="1" value="3" /></label>'
			. '<button type="submit" class="kwt-btn">' . $l_estimate . '</button>'
			. '<p class="kwt-calculator__total" aria-live="polite"></p>'
			. '</form>';
	}
}
