<?php
/**
 * Render function for kwawingu/gallery.
 *
 * @package KwaWingu\Tours
 */

if ( ! function_exists( 'kwt_render_gallery' ) ) {
	/**
	 * Render callback for kwawingu/gallery.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 * @param string              $content    Inner content (unused).
	 * @return string
	 */
	function kwt_render_gallery( array $attributes, string $content = '' ): string {
		$id   = ! empty( $attributes['postId'] ) ? (int) $attributes['postId'] : (int) get_the_ID();
		$cols = isset( $attributes['columns'] ) ? (int) $attributes['columns'] : 3;
		if ( $cols < 1 ) {
			$cols = 1;
		}
		if ( $cols > 6 ) {
			$cols = 6;
		}

		$items = array();
		$ids   = get_post_meta( $id, 'kwt_gallery_ids', true );
		if ( is_array( $ids ) && ! empty( $ids ) ) {
			foreach ( $ids as $aid ) {
				$url = wp_get_attachment_image_url( (int) $aid, 'large' );
				if ( $url ) {
					$items[] = $url;
				}
			}
		}
		if ( empty( $items ) ) {
			$urls = get_post_meta( $id, 'kwt_gallery', true );
			if ( is_array( $urls ) ) {
				foreach ( $urls as $u ) {
					if ( is_string( $u ) && '' !== $u ) {
						$items[] = $u;
					}
				}
			}
		}
		if ( empty( $items ) ) {
			return '';
		}

		$out = '<div class="kwt-gallery" style="--kwt-cols:' . esc_attr( (string) $cols ) . '">';
		foreach ( $items as $u ) {
			$out .= '<img class="kwt-gallery__img" src="' . esc_url( $u ) . '" alt="" loading="lazy" />';
		}
		$out .= '</div>';
		return $out;
	}
}
