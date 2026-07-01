<?php
/**
 * Render function for kwawingu/destinations-grid.
 *
 * @package KwaWingu\Tours
 */

use KwaWingu\Tours\Cpt;

if ( ! function_exists( 'kwt_render_destinations_grid' ) ) {
    /**
     * @param array<string,mixed> $attributes
     */
    function kwt_render_destinations_grid( array $attributes, string $content = '' ): string {
        $limit = isset( $attributes['limit'] ) ? (int) $attributes['limit'] : 12;
        $query = $attributes['_query'] ?? null;
        if ( null === $query ) {
            $query = new \WP_Query( array(
                'post_type'      => Cpt::DESTINATION,
                'post_status'    => 'publish',
                'posts_per_page' => $limit,
            ) );
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
            $out .= '<h3 class="kwt-destination-card__title"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( $title ) . '</a></h3>';
            $out .= '</article>';
        }
        $out .= '</div>';
        if ( function_exists( 'wp_reset_postdata' ) ) {
            wp_reset_postdata();
        }
        return $out;
    }
}
