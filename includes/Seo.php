<?php
/**
 * Structured data and Open Graph tags for single tour pages.
 *
 * @package KwaWingu\Tours
 */

namespace KwaWingu\Tours;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Structured data (JSON-LD) + Open Graph for single tour pages.
 */
class Seo {

	/**
	 * Hook SEO output onto wp_head.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_head', array( $this, 'emit' ) );
	}

	/**
	 * Output JSON-LD and Open Graph meta tags for the current single tour page.
	 *
	 * @return void
	 */
	public function emit(): void {
		if ( ! function_exists( 'is_singular' ) || ! is_singular( Cpt::TOUR ) ) {
			return;
		}
		$id   = (int) get_the_ID();
		$data = $this->json_ld( $id );

		echo '<script type="application/ld+json">' . wp_json_encode( $data ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_json_encode output inside a JSON-LD script tag.

		$img = (string) get_the_post_thumbnail_url( $id, 'large' );
		echo '<meta property="og:type" content="product" />' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( get_the_title() ) . '" />' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( get_the_excerpt() ) . '" />' . "\n";
		if ( $img ) {
			echo '<meta property="og:image" content="' . esc_url( $img ) . '" />' . "\n";
		}
	}

	/**
	 * Build the JSON-LD schema.org Product array for a tour post.
	 *
	 * @param int $post_id Tour post ID.
	 * @return array<string,mixed> JSON-LD structured data array.
	 */
	public function json_ld( int $post_id ): array {
		$price  = (int) get_post_meta( $post_id, 'kwt_price', true );
		$rating = (float) get_post_meta( $post_id, 'kwt_rating', true );
		$count  = (int) get_post_meta( $post_id, 'kwt_review_count', true );

		$data = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Product',
			'name'        => get_the_title(),
			'description' => get_the_excerpt(),
			'url'         => get_permalink( $post_id ),
		);
		$img  = (string) get_the_post_thumbnail_url( $post_id, 'large' );
		if ( $img ) {
			$data['image'] = $img;
		}
		if ( $price > 0 ) {
			$data['offers'] = array(
				'@type'         => 'Offer',
				'price'         => $price,
				'priceCurrency' => 'TZS',
				'availability'  => 'https://schema.org/InStock',
			);
		}
		if ( $rating > 0 && $count > 0 ) {
			$data['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => $rating,
				'reviewCount' => $count,
			);
		}
		return $data;
	}
}
