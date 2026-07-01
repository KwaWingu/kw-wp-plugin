<?php
/**
 * Render function for kwawingu/search (server shell; view.js does the fetching).
 *
 * @package KwaWingu\Tours
 */

if ( ! function_exists( 'kwt_render_search' ) ) {
    /**
     * @param array<string,mixed> $attributes
     */
    function kwt_render_search( array $attributes, string $content = '' ): string {
        if ( function_exists( 'wp_enqueue_script' ) ) {
            wp_enqueue_script( 'kwt-proxy' );
        }
        $placeholder = isset( $attributes['placeholder'] ) && '' !== $attributes['placeholder']
            ? esc_attr( (string) $attributes['placeholder'] )
            : esc_attr__( 'Search tours…', 'kwawingu-tours' );
        return '<div class="kwt-search">'
            . '<input type="search" class="kwt-search__input" placeholder="' . $placeholder . '" aria-label="' . esc_attr__( 'Search tours', 'kwawingu-tours' ) . '" />'
            . '<ul class="kwt-search__results" aria-live="polite"></ul>'
            . '</div>';
    }
}
