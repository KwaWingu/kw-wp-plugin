<?php
/**
 * Remote image ingestion into the WordPress media library.
 *
 * @package KwaWingu\Tours
 */

namespace KwaWingu\Tours;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Sideloads remote tour images into the WP media library (sideload mode).
 */
class Media {

	const META_SRC = 'kwt_cover_src';

	/**
	 * Plugin settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Plugin settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Sideload a remote cover image and set it as the post thumbnail.
	 *
	 * @param int    $post_id Tour post ID.
	 * @param string $url     Remote image URL to sideload.
	 * @return void
	 */
	public function ingest_cover( int $post_id, string $url ): void {
		if ( '' === $url || 'sideload' !== $this->settings->get_media_mode() ) {
			return;
		}
		// Skip if we've already ingested this exact source URL.
		if ( (string) get_post_meta( $post_id, self::META_SRC, true ) === $url ) {
			return;
		}
		$this->require_media_functions();
		try {
			$attachment_id = media_sideload_image( $url, $post_id, null, 'id' );
			if ( is_int( $attachment_id ) && $attachment_id > 0 ) {
				set_post_thumbnail( $post_id, $attachment_id );
				update_post_meta( $post_id, self::META_SRC, $url );
			}
		} catch ( \Throwable $e ) {
			// Best-effort: never break sync on a media error.
		}
	}

	/** Load the WP admin media helpers if not already available. */
	private function require_media_functions(): void {
		if ( ! function_exists( 'media_sideload_image' ) && defined( 'ABSPATH' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
	}
}
