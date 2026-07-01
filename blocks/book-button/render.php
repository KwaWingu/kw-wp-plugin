<?php
/**
 * WP block template for kwawingu/book-button. Echoes the rendered markup.
 * $attributes / $content / $block are provided by WordPress.
 *
 * @package KwaWingu\Tours
$1

$2 __DIR__ . '/render-fn.php';
$kwt_attrs   = isset( $attributes ) && is_array( $attributes ) ? $attributes : array();
$kwt_content = isset( $content ) ? (string) $content : '';
echo kwt_render_book_button( $kwt_attrs, $kwt_content ); // phpcs:ignore WordPress.Security.EscapeOutput -- render fn returns fully-escaped HTML.
