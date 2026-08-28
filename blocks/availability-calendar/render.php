<?php
/**
 * WP block template for kwawingu/availability-calendar.
 *
 * @package KwaWingu\Tours
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/render-fn.php';
$kwawingu_tours_attrs   = isset( $attributes ) && is_array( $attributes ) ? $attributes : array();
$kwawingu_tours_content = isset( $content ) ? (string) $content : '';
echo kwawingu_tours_render_availability_calendar( $kwawingu_tours_attrs, $kwawingu_tours_content ); // phpcs:ignore WordPress.Security.EscapeOutput -- render fn returns escaped HTML.
