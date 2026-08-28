<?php
/**
 * WP block template for kwawingu/featured-tours. Echoes the rendered markup.
 * $attributes / $content / $block are provided by WordPress.
 *
 * @package KwaWingu\Tours
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/render-fn.php';
$kwawingu_tours_attrs   = isset( $attributes ) && is_array( $attributes ) ? $attributes : array();
$kwawingu_tours_content = isset( $content ) ? (string) $content : '';
echo kwawingu_tours_render_featured_tours( $kwawingu_tours_attrs, $kwawingu_tours_content ); // phpcs:ignore WordPress.Security.EscapeOutput -- render fn returns fully-escaped HTML.
