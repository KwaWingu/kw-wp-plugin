<?php
/**
 * Server render for kwawingu/book-button.
 *
 * @package KwaWingu\Tours
 */

use KwaWingu\Tours\Booking;
use KwaWingu\Tours\Settings;

if ( ! function_exists( 'kwt_render_book_button' ) ) {
    /**
     * @param array<string,mixed> $attributes
     */
    function kwt_render_book_button( array $attributes, string $content = '' ): string {
        $id = ! empty( $attributes['postId'] ) ? (int) $attributes['postId'] : (int) get_the_ID();

        $booking = new Booking( new Settings() );
        if ( 'widget' === $booking->mode() ) {
            $embed = $booking->widget_embed( $id );
            if ( '' !== $embed ) {
                return '<div class="kwt-booking-widget">' . $embed . '</div>';
            }
        }

        if ( 'onsite' === $booking->mode() ) {
            // The on-site booking form lives in the kwawingu/booking block (anchor #kwt-book).
            $label = isset( $attributes['label'] ) && '' !== $attributes['label']
                ? (string) $attributes['label']
                : __( 'Book now', 'kwawingu-tours' );
            return '<a class="kwt-book-btn" href="#kwt-book">' . esc_html( $label ) . '</a>';
        }

        $label = isset( $attributes['label'] ) && '' !== $attributes['label']
            ? (string) $attributes['label']
            : __( 'Book now', 'kwawingu-tours' );

        $url = Booking::url_for( $id );
        if ( '' === $url ) {
            return '';
        }
        return '<a class="kwt-book-btn" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
    }
}
