<?php
namespace KwaWingu\Tours;

/**
 * Classic-theme shortcode bridges that reuse the block render callbacks.
 */
class Shortcodes {

    public function register(): void {
        add_shortcode( 'kwawingu_tours', array( $this, 'render_tours' ) );
        add_shortcode( 'kwawingu_tour', array( $this, 'render_tour' ) );
        add_shortcode( 'kwawingu_booking', array( $this, 'render_booking' ) );
        add_shortcode( 'kwawingu_featured', array( $this, 'render_featured' ) );
        add_shortcode( 'kwawingu_reviews', array( $this, 'render_reviews' ) );
        add_shortcode( 'kwawingu_destinations', array( $this, 'render_destinations' ) );
        add_shortcode( 'kwawingu_search', array( $this, 'render_search' ) );
        add_shortcode( 'kwawingu_calculator', array( $this, 'render_calculator' ) );
        add_shortcode( 'kwawingu_booking_form', array( $this, 'render_booking_form' ) );
    }

    /** @param array<string,mixed> $atts */
    public function render_tours( $atts ): string {
        require_once Blocks::block_dir() . 'tours-grid/render-fn.php';
        $atts = shortcode_atts( array( 'limit' => 12, 'type' => '' ), $atts );
        return kwt_render_tours_grid( array( 'limit' => (int) $atts['limit'], 'type' => (string) $atts['type'] ), '' );
    }

    /** @param array<string,mixed> $atts */
    public function render_tour( $atts ): string {
        require_once Blocks::block_dir() . 'tour-detail/render-fn.php';
        $atts = shortcode_atts( array( 'id' => 0 ), $atts );
        return kwt_render_tour_detail( array( 'postId' => (int) $atts['id'] ), '' );
    }

    /** @param array<string,mixed> $atts */
    public function render_booking( $atts ): string {
        require_once Blocks::block_dir() . 'book-button/render-fn.php';
        $atts = shortcode_atts( array( 'id' => 0, 'label' => '' ), $atts );
        return kwt_render_book_button( array( 'postId' => (int) $atts['id'], 'label' => (string) $atts['label'] ), '' );
    }

    /** @param array<string,mixed> $atts */
    public function render_featured( $atts ): string {
        require_once Blocks::block_dir() . 'featured-tours/render-fn.php';
        $atts = shortcode_atts( array( 'heading' => '', 'limit' => 3 ), $atts );
        return kwt_render_featured_tours( array( 'heading' => (string) $atts['heading'], 'limit' => (int) $atts['limit'] ), '' );
    }

    /** @param array<string,mixed> $atts */
    public function render_reviews( $atts ): string {
        require_once Blocks::block_dir() . 'reviews/render-fn.php';
        $atts = shortcode_atts( array( 'id' => 0 ), $atts );
        return kwt_render_reviews( array( 'postId' => (int) $atts['id'] ), '' );
    }

    /** @param array<string,mixed> $atts */
    public function render_destinations( $atts ): string {
        require_once Blocks::block_dir() . 'destinations-grid/render-fn.php';
        $atts = shortcode_atts( array( 'limit' => 12 ), $atts );
        return kwt_render_destinations_grid( array( 'limit' => (int) $atts['limit'] ), '' );
    }

    /** @param array<string,mixed> $atts */
    public function render_search( $atts ): string {
        require_once Blocks::block_dir() . 'search/render-fn.php';
        return kwt_render_search( array(), '' );
    }

    /** @param array<string,mixed> $atts */
    public function render_calculator( $atts ): string {
        require_once Blocks::block_dir() . 'calculator/render-fn.php';
        return kwt_render_calculator( array(), '' );
    }

    /** @param array<string,mixed> $atts */
    public function render_booking_form( $atts ): string {
        require_once Blocks::block_dir() . 'booking/render-fn.php';
        return kwt_render_booking_form( array(), '' );
    }
}
