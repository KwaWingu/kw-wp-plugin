<?php
/**
 * Imports the operator's KwaWingu catalog into kwt_tour posts.
 *
 * @package KwaWingu\Tours
 */

namespace KwaWingu\Tours;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Imports the operator's KwaWingu catalog into kwt_tour posts.
 *
 * Upserts by the kwt_id meta. Structured meta is always refreshed; title/body
 * are written only for new posts or posts the operator has not locked by
 * editing. Tours that vanish from the API are drafted (never hard-deleted).
 */
class Sync {

	const META_ID   = 'kwt_id';
	const META_LOCK = 'kwt_content_locked';

	/**
	 * API client instance.
	 *
	 * @var Api_Client
	 */
	private $api;

	/**
	 * Media handler instance.
	 *
	 * @var Media|null
	 */
	private $media;

	/**
	 * Initialises the sync service with an API client and optional media handler.
	 *
	 * @param Api_Client $api   API client to fetch tour data.
	 * @param Media|null $media Optional media handler for cover images.
	 */
	public function __construct( Api_Client $api, ?Media $media = null ) {
		$this->api   = $api;
		$this->media = $media;
	}

	/**
	 * Runs a full sync of all tours from the KwaWingu API.
	 *
	 * @return array{created:int,updated:int,unpublished:int,errors:array<int,string>}
	 */
	public function run(): array {
		$result = array(
			'created'      => 0,
			'updated'      => 0,
			'unpublished'  => 0,
			'destinations' => array(
				'created'     => 0,
				'updated'     => 0,
				'unpublished' => 0,
			),
			'errors'       => array(),
		);

		try {
			$site  = $this->api->get_site();
			$tours = isset( $site['tours'] ) && is_array( $site['tours'] ) ? $site['tours'] : array();
		} catch ( Api_Exception $e ) {
			// The owner reads this on the settings page: name the fix, not the status.
			Api_Status::record_failure( $e );
			$result['errors'][] = Api_Status::owner_message( $e );
			return $result;
		}
		Api_Status::record_success();

		$seen_ids = array();

		foreach ( $tours as $tour ) {
			if ( ! is_array( $tour ) ) {
				continue;
			}
			$kwt_id = (string) ( $tour['id'] ?? '' );
			if ( '' === $kwt_id ) {
				$result['errors'][] = 'Skipped a tour with no id.';
				continue;
			}
			$seen_ids[] = $kwt_id;

			$existing = $this->find_post_by_kwt_id( $kwt_id );
			if ( 0 === $existing ) {
				$new_id = $this->insert_tour( $tour, $kwt_id );
				if ( $new_id > 0 ) {
					++$result['created'];
				} else {
					$result['errors'][] = "Failed to create tour {$kwt_id}.";
				}
			} else {
				$this->update_tour( $existing, $tour );
				++$result['updated'];
			}
		}

		// Guard: never soft-unpublish the whole catalog on a blank/partial upstream
		// response. Only sweep when this run actually saw tours.
		if ( $result['created'] + $result['updated'] > 0 ) {
			$result['unpublished'] = $this->unpublish_missing( $seen_ids );
		} else {
			// Nothing created or updated (empty or all-parse-failed response) — skip the
			// sweep so a blank/partial upstream response can't draft the whole catalog.
			$result['errors'][] = 'Sync returned no usable tours; skipped unpublish to protect the catalog.';
		}

		$destinations           = isset( $site['destinations'] ) && is_array( $site['destinations'] ) ? $site['destinations'] : array();
		$result['destinations'] = $this->sync_destinations( $destinations );

		return $result;
	}

	/**
	 * Upserts the operator's destinations into kwt_destination posts.
	 *
	 * The Destinations Grid block has always queried kwt_destination, but nothing ever
	 * wrote one, so the grid rendered "No destinations yet." on every site. The /site
	 * bundle carries the destinations; this mirrors them the same way tours are.
	 *
	 * @param array<int,mixed> $rows Destination rows from the /site bundle.
	 * @return array{created:int,updated:int,unpublished:int}
	 */
	private function sync_destinations( array $rows ): array {
		$out  = array(
			'created'     => 0,
			'updated'     => 0,
			'unpublished' => 0,
		);
		$seen = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$kwt_id = (string) ( $row['id'] ?? '' );
			$name   = (string) ( $row['name'] ?? '' );
			if ( '' === $kwt_id || '' === $name ) {
				continue;
			}
			$seen[]   = $kwt_id;
			$existing = $this->find_post_by_kwt_id( $kwt_id, Cpt::DESTINATION );
			$content  = wp_strip_all_tags( (string) ( $row['description'] ?? '' ) );
			if ( 0 === $existing ) {
				$id = wp_insert_post(
					array(
						'post_type'    => Cpt::DESTINATION,
						'post_status'  => 'publish',
						'post_title'   => sanitize_text_field( $name ),
						'post_content' => $content,
					)
				);
				if ( ! is_int( $id ) || $id <= 0 ) {
					continue;
				}
				++$out['created'];
			} else {
				$id      = $existing;
				$payload = array( 'ID' => $id );
				if ( '1' !== (string) get_post_meta( $id, self::META_LOCK, true ) ) {
					$payload['post_title']   = sanitize_text_field( $name );
					$payload['post_content'] = $content;
				}
				wp_update_post( $payload );
				++$out['updated'];
			}
			update_post_meta( $id, self::META_ID, $kwt_id );
			// The API's slug is what the hosted destination page is addressed by
			// ({hostedBase}/{operator}/destinations/{slug}); the grid links there.
			update_post_meta( $id, 'kwt_slug', sanitize_title( (string) ( $row['slug'] ?? '' ) ) );
			update_post_meta( $id, 'kwt_region', sanitize_text_field( (string) ( $row['region'] ?? '' ) ) );
			update_post_meta( $id, 'kwt_country', sanitize_text_field( (string) ( $row['country'] ?? '' ) ) );
			update_post_meta( $id, 'kwt_destination_type', sanitize_text_field( (string) ( $row['destinationType'] ?? '' ) ) );
			$cover = $this->esc_url_raw_or_empty( $row['coverImageUrl'] ?? '' );
			update_post_meta( $id, 'kwt_cover_url', $cover );
			update_post_meta( $id, 'kwt_synced_at', time() );
			if ( null !== $this->media && '' !== $cover ) {
				$this->media->ingest_cover( $id, $cover );
			}
		}
		if ( $out['created'] + $out['updated'] > 0 ) {
			$out['unpublished'] = $this->unpublish_missing( $seen, Cpt::DESTINATION );
		}
		return $out;
	}

	/**
	 * Returns the WordPress post ID for the given KwaWingu tour ID, or 0 if not found.
	 *
	 * @param string $kwt_id    KwaWingu tour ID.
	 * @param string $post_type Post type to search (tours by default).
	 * @return int
	 */
	private function find_post_by_kwt_id( string $kwt_id, string $post_type = Cpt::TOUR ): int {
		$ids = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => self::META_ID,
						'value' => $kwt_id,
					),
				),
			)
		);
		return ! empty( $ids ) ? (int) $ids[0] : 0;
	}

	/**
	 * Inserts a new tour post and writes its meta.
	 *
	 * @param array<string,mixed> $tour   Tour data from the API.
	 * @param string              $kwt_id KwaWingu tour ID.
	 * @return int New post ID on success, 0 on failure.
	 */
	private function insert_tour( array $tour, string $kwt_id ): int {
		$id = wp_insert_post(
			array(
				'post_type'    => Cpt::TOUR,
				'post_status'  => 'publish',
				'post_title'   => sanitize_text_field( (string) ( $tour['title'] ?? '' ) ),
				'post_excerpt' => sanitize_text_field( (string) ( $tour['descriptionShort'] ?? '' ) ),
				'post_content' => wp_strip_all_tags( (string) ( $tour['description'] ?? $tour['descriptionFull'] ?? $tour['descriptionShort'] ?? '' ) ),
			)
		);
		if ( is_int( $id ) && $id > 0 ) {
			$this->write_meta( $id, $tour, $kwt_id );
			return $id;
		}
		return 0;
	}

	/**
	 * Updates an existing tour post from the API data.
	 *
	 * @param int                 $post_id WordPress post ID.
	 * @param array<string,mixed> $tour    Tour data from the API.
	 */
	private function update_tour( int $post_id, array $tour ): void {
		$locked = '1' === (string) get_post_meta( $post_id, self::META_LOCK, true );

		$payload = array( 'ID' => $post_id );
		if ( ! $locked ) {
			$payload['post_title']   = sanitize_text_field( (string) ( $tour['title'] ?? '' ) );
			$payload['post_excerpt'] = sanitize_text_field( (string) ( $tour['descriptionShort'] ?? '' ) );
			$payload['post_content'] = wp_strip_all_tags( (string) ( $tour['description'] ?? $tour['descriptionFull'] ?? $tour['descriptionShort'] ?? '' ) );
		}
		wp_update_post( $payload );
		$existing_kwt_id = (string) get_post_meta( $post_id, self::META_ID, true );
		$this->write_meta( $post_id, $tour, ! empty( $existing_kwt_id ) ? $existing_kwt_id : (string) ( $tour['id'] ?? '' ) );
	}

	/**
	 * Writes all structured meta fields to the post.
	 *
	 * @param int                 $post_id WordPress post ID.
	 * @param array<string,mixed> $tour    Tour data from the API.
	 * @param string              $kwt_id  KwaWingu tour ID.
	 */
	private function write_meta( int $post_id, array $tour, string $kwt_id ): void {
		update_post_meta( $post_id, self::META_ID, $kwt_id );
		update_post_meta( $post_id, 'kwt_slug', sanitize_title( (string) ( $tour['slug'] ?? '' ) ) );
		// The API's public "from" price is basePriceAdult; 'price' is kept as a fallback
		// so an older/summary payload shape still populates the meta.
		update_post_meta( $post_id, 'kwt_price', (int) round( (float) ( $tour['basePriceAdult'] ?? $tour['price'] ?? 0 ) ) );
		update_post_meta( $post_id, 'kwt_currency', sanitize_text_field( (string) ( $tour['currency'] ?? '' ) ) );
		update_post_meta( $post_id, 'kwt_duration_days', (int) ( $tour['durationDays'] ?? 0 ) );
		update_post_meta( $post_id, 'kwt_difficulty', sanitize_text_field( (string) ( $tour['difficulty'] ?? '' ) ) );
		update_post_meta( $post_id, 'kwt_type', sanitize_text_field( (string) ( $tour['type'] ?? $tour['productType'] ?? $tour['category'] ?? '' ) ) );
		update_post_meta( $post_id, 'kwt_cover_url', $this->esc_url_raw_or_empty( $tour['coverImageUrl'] ?? '' ) );
		update_post_meta( $post_id, 'kwt_rating', (float) ( $tour['rating'] ?? $tour['averageRating'] ?? 0 ) );
		update_post_meta( $post_id, 'kwt_review_count', (int) ( $tour['reviewCount'] ?? 0 ) );
		$gallery     = array();
		$gallery_src = $tour['gallery'] ?? ( $tour['galleryImageUrls'] ?? null );
		if ( is_array( $gallery_src ) ) {
			foreach ( $gallery_src as $url ) {
				$clean = $this->esc_url_raw_or_empty( $url );
				if ( '' !== $clean ) {
					$gallery[] = $clean;
				}
			}
		}
		update_post_meta( $post_id, 'kwt_gallery', $gallery );
		update_post_meta( $post_id, 'kwt_synced_at', time() );

		if ( null !== $this->media ) {
			$cover = (string) ( $tour['coverImageUrl'] ?? '' );
			if ( '' !== $cover ) {
				$this->media->ingest_cover( $post_id, $cover );
			}
			$gallery = get_post_meta( $post_id, 'kwt_gallery', true );
			if ( is_array( $gallery ) && ! empty( $gallery ) ) {
				$this->media->ingest_gallery( $post_id, $gallery );
			}
		}
	}

	/**
	 * Sanitizes a URL value, returning an empty string for non-string or empty input.
	 *
	 * @param mixed $url URL value to sanitize.
	 * @return string
	 */
	private function esc_url_raw_or_empty( $url ): string {
		$url = is_string( $url ) ? $url : '';
		return '' === $url ? '' : ( function_exists( 'esc_url_raw' ) ? esc_url_raw( $url ) : $url );
	}

	/**
	 * Drafts any published tours whose KWT IDs were not seen in the latest sync.
	 *
	 * @param array<int,string> $seen_ids  KWT IDs seen during the current sync run.
	 * @param string            $post_type Post type to sweep (tours by default).
	 * @return int Number of posts drafted.
	 */
	private function unpublish_missing( array $seen_ids, string $post_type = Cpt::TOUR ): int {
		$all = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$count = 0;
		foreach ( (array) $all as $post_id ) {
			$kwt_id = (string) get_post_meta( (int) $post_id, self::META_ID, true );
			if ( '' !== $kwt_id && ! in_array( $kwt_id, $seen_ids, true ) ) {
				wp_update_post(
					array(
						'ID'          => (int) $post_id,
						'post_status' => 'draft',
					)
				);
				++$count;
			}
		}
		return $count;
	}
}
