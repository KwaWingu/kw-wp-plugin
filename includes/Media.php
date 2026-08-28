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
			$attachment_id = $this->sideload( $url, $post_id );
			if ( $attachment_id > 0 ) {
				set_post_thumbnail( $post_id, $attachment_id );
				update_post_meta( $post_id, self::META_SRC, $url );
			}
		} catch ( \Throwable $e ) {
			// Best-effort: never break sync on a media error.
		}
	}

	/**
	 * Image types accepted from the API, keyed by the MIME type the bytes sniff as.
	 *
	 * @var array<string,string>
	 */
	const IMAGE_EXTENSIONS = array(
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/gif'  => 'gif',
		'image/webp' => 'webp',
		'image/avif' => 'avif',
	);

	/**
	 * Downloads a remote image into the media library, attached to $post_id.
	 *
	 * Not media_sideload_image(): that helper decides whether a URL is an image by
	 * looking for a file extension in the URL, and KwaWingu serves every photo from
	 * Cloudflare Images as `imagedelivery.net/{hash}/{id}/public` — no extension —
	 * so it answered "Invalid image URL." for every real cover and gallery photo,
	 * and no site ever got its images imported. The bytes are sniffed instead and the
	 * attachment is named from what they turn out to be.
	 *
	 * @param string $url     Remote image URL.
	 * @param int    $post_id Post to attach the image to.
	 * @return int Attachment ID, or 0 when the download or import failed.
	 */
	public function sideload( string $url, int $post_id ): int {
		$tmp = download_url( $url, 30 );
		if ( ! is_string( $tmp ) || '' === $tmp || is_wp_error( $tmp ) ) {
			return 0;
		}
		$mime = function_exists( 'wp_get_image_mime' ) ? wp_get_image_mime( $tmp ) : false;
		$ext  = is_string( $mime ) && isset( self::IMAGE_EXTENSIONS[ $mime ] ) ? self::IMAGE_EXTENSIONS[ $mime ] : '';
		if ( '' === $ext ) {
			$this->discard( $tmp );
			return 0;
		}
		$name = (string) wp_parse_url( $url, PHP_URL_PATH );
		$name = sanitize_file_name( basename( $name ) );
		if ( '' === $name || 'public' === $name || ! preg_match( '/\.' . $ext . '$/i', $name ) ) {
			$name = 'kwt-' . substr( md5( $url ), 0, 12 ) . '.' . $ext;
		}
		$attachment_id = media_handle_sideload(
			array(
				'name'     => $name,
				'tmp_name' => $tmp,
				'type'     => $mime,
			),
			$post_id
		);
		if ( ! is_int( $attachment_id ) || $attachment_id <= 0 || is_wp_error( $attachment_id ) ) {
			$this->discard( $tmp );
			return 0;
		}
		return $attachment_id;
	}

	/**
	 * Removes a temp file media_handle_sideload() did not consume.
	 *
	 * @param string $tmp Temp file path.
	 * @return void
	 */
	private function discard( string $tmp ): void {
		if ( file_exists( $tmp ) ) {
			wp_delete_file( $tmp );
		}
	}

	/**
	 * Sideload a tour's gallery images into the media library (sideload mode).
	 *
	 * Skips any URL already present in the stored source list (dedup). Each URL
	 * is attempted individually; failures are swallowed (best-effort). In hotlink
	 * mode or when $urls is empty, returns an empty array without importing
	 * anything (the Gallery block falls back to the raw kwt_gallery URLs).
	 *
	 * @param int              $post_id Tour post ID.
	 * @param array<int,mixed> $urls    Remote image URLs to sideload.
	 * @return array<int,int> Attachment-ID array (existing + newly ingested), or empty in hotlink/empty mode.
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
				$attachment_id = $this->sideload( $url, $post_id );
			} catch ( \Throwable $e ) {
				continue; // Best-effort: never break sync on a media error.
			}
			if ( $attachment_id > 0 ) {
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
		if ( ! function_exists( 'media_handle_sideload' ) && defined( 'ABSPATH' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
	}
}
