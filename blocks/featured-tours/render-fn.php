<?php
/**
 * Server render for kwawingu/featured-tours. Delegates to the Tours Grid renderer.
 *
 * @package KwaWingu\Tours
 */

if ( ! function_exists( 'kwt_render_featured_tours' ) ) {
    /**
     * @param array<string,mixed> $attributes
     */
    function kwt_render_featured_tours( array $attributes, string $content = '' ): string {
        require_once __DIR__ . '/../tours-grid/render-fn.php';
        $heading = isset( $attributes['heading'] ) ? (string) $attributes['heading'] : __( 'Featured tours', 'kwawingu-tours' );
        $limit   = isset( $attributes['limit'] ) ? (int) $attributes['limit'] : 3;

        $grid_attrs = array( 'limit' => $limit );
        if ( isset( $attributes['_query'] ) ) {
            $grid_attrs['_query'] = $attributes['_query'];
        }
        $grid = kwt_render_tours_grid( $grid_attrs, '' );

        $out = '<section class="kwt-featured">';
        if ( '' !== $heading ) {
            $out .= '<h2 class="kwt-featured__heading">' . esc_html( $heading ) . '</h2>';
        }
        $out .= $grid . '</section>';
        return $out;
    }
}
