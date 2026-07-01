<?php
/**
 * Render function for kwawingu/reviews.
 *
 * @package KwaWingu\Tours
 */

if ( ! function_exists( 'kwt_render_reviews' ) ) {
    /**
     * @param array<string,mixed> $attributes
     */
    function kwt_render_reviews( array $attributes, string $content = '' ): string {
        $id     = ! empty( $attributes['postId'] ) ? (int) $attributes['postId'] : (int) get_the_ID();
        $rating = (float) get_post_meta( $id, 'kwt_rating', true );
        $count  = (int) get_post_meta( $id, 'kwt_review_count', true );
        if ( $rating <= 0 || $count <= 0 ) {
            return '';
        }
        $full  = (int) floor( $rating );
        $stars = str_repeat( '★', $full ) . str_repeat( '☆', max( 0, 5 - $full ) );
        $out   = '<div class="kwt-reviews">';
        $out  .= '<span class="kwt-reviews__stars" aria-hidden="true">' . esc_html( $stars ) . '</span>';
        $out  .= '<span class="kwt-reviews__score">' . esc_html( number_format_i18n( $rating, 1 ) ) . '</span>';
        /* translators: %s: number of reviews */
        $out  .= '<span class="kwt-reviews__count">' . esc_html( sprintf( _n( '%s review', '%s reviews', $count, 'kwawingu-tours' ), number_format_i18n( $count ) ) ) . '</span>';
        $out  .= '</div>';
        return $out;
    }
}
