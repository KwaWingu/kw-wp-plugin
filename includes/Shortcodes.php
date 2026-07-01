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
    }

    /** @param array<string,mixed> $atts */
    public function render_tours( $atts ): string {
        require_once Blocks::block_dir() . 'tours-grid/render.php';
        $atts = shortcode_atts( array( 'limit' => 12, 'type' => '' ), $atts );
        return kwt_render_tours_grid( array( 'limit' => (int) $atts['limit'], 'type' => (string) $atts['type'] ), '' );
    }

    /** @param array<string,mixed> $atts */
    public function render_tour( $atts ): string {
        require_once Blocks::block_dir() . 'tour-detail/render.php';
        $atts = shortcode_atts( array( 'id' => 0 ), $atts );
        return kwt_render_tour_detail( array( 'postId' => (int) $atts['id'] ), '' );
    }

    /** @param array<string,mixed> $atts */
    public function render_booking( $atts ): string {
        require_once Blocks::block_dir() . 'book-button/render.php';
        $atts = shortcode_atts( array( 'id' => 0, 'label' => '' ), $atts );
        return kwt_render_book_button( array( 'postId' => (int) $atts['id'], 'label' => (string) $atts['label'] ), '' );
    }

    /** @param array<string,mixed> $atts */
    public function render_featured( $atts ): string {
        require_once Blocks::block_dir() . 'featured-tours/render.php';
        $atts = shortcode_atts( array( 'heading' => '', 'limit' => 3 ), $atts );
        return kwt_render_featured_tours( array( 'heading' => (string) $atts['heading'], 'limit' => (int) $atts['limit'] ), '' );
    }
}
