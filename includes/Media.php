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

	/** Meta key for the stored gallery attachment IDs. */
	const META_GALLERY_IDS = 'kwt_gallery_ids';

	/** Meta key for the stored gallery source URLs (dedup list). */
	const META_GALLERY_SRC = 'kwt_gallery_src';

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

	/**
	 * Sideload a tour's gallery images into the media library (sideload mode).
	 *
	 * Skips any URL already present in the stored source list (dedup). Each URL
	 * is attempted individually; failures are swallowed (best-effort). In hotlink
	 * mode or when $urls is empty, returns the existing attachment-ID array
	 * unchanged (or an empty array if none were previously stored).
	 *
	 * @param int              $post_id Tour post ID.
	 * @param array<int,mixed> $urls    Remote image URLs to sideload.
	 * @return array<int,int> Full attachment-ID array (existing + newly ingested).
	 */
	public function ingest_gallery( int $post_id, array $urls ): array {
		if ( empty( $urls ) || 'sideload' !== $this->settings->get_media_mode() ) {
			return array();
		}
		$existing_ids = get_post_meta( $post_id, self::META_GALLERY_IDS, true );
		$existing_ids = is_array( $existing_ids ) ? array_values( array_map( 'intval', $existing_ids ) ) : array();
		$done_src     = get_post_meta( $post_id, self::META_GALLERY_SRC, true );
		$done_src     = is_array( $done_src ) ? $done_src : array();

		$this->require_media_functions();
		$changed = false;
		foreach ( $urls as $url ) {
			$url = is_string( $url ) ? $url : '';
			if ( '' === $url || in_array( $url, $done_src, true ) ) {
				continue;
			}
			try {
				$attachment_id = media_sideload_image( $url, $post_id, null, 'id' );
			} catch ( \Throwable $e ) {
				continue; // Best-effort: never break sync on a media error.
			}
			if ( is_int( $attachment_id ) && $attachment_id > 0 ) {
				$existing_ids[] = $attachment_id;
				$done_src[]     = $url;
				$changed        = true;
			}
		}
		if ( $changed ) {
			update_post_meta( $post_id, self::META_GALLERY_IDS, $existing_ids );
			update_post_meta( $post_id, self::META_GALLERY_SRC, $done_src );
		}
		return $existing_ids;
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
