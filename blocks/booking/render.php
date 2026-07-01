<?php
/**
 * WP block template for kwawingu/booking.
 *
 * @package KwaWingu\Tours
 */
require_once __DIR__ . '/render-fn.php';
$kwt_attrs   = isset( $attributes ) && is_array( $attributes ) ? $attributes : array();
$kwt_content = isset( $content ) ? (string) $content : '';
echo kwt_render_booking_form( $kwt_attrs, $kwt_content ); // phpcs:ignore WordPress.Security.EscapeOutput -- render fn returns escaped HTML.
