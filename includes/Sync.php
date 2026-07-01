<?php
namespace KwaWingu\Tours;

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

    /** @var Api_Client */
    private $api;

    /** @var Media|null */
    private $media;

    public function __construct( Api_Client $api, ?Media $media = null ) {
        $this->api   = $api;
        $this->media = $media;
    }

    /**
     * @return array{created:int,updated:int,unpublished:int,errors:array<int,string>}
     */
    public function run(): array {
        $result = array( 'created' => 0, 'updated' => 0, 'unpublished' => 0, 'errors' => array() );

        try {
            $site  = $this->api->get_site();
            $tours = isset( $site['tours'] ) && is_array( $site['tours'] ) ? $site['tours'] : array();
        } catch ( Api_Exception $e ) {
            $result['errors'][] = $e->getMessage();
            return $result;
        }

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
                    $result['created']++;
                } else {
                    $result['errors'][] = "Failed to create tour {$kwt_id}.";
                }
            } else {
                $this->update_tour( $existing, $tour );
                $result['updated']++;
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

        return $result;
    }

    private function find_post_by_kwt_id( string $kwt_id ): int {
        $ids = get_posts( array(
            'post_type'      => Cpt::TOUR,
            'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array( 'key' => self::META_ID, 'value' => $kwt_id ),
            ),
        ) );
        return ! empty( $ids ) ? (int) $ids[0] : 0;
    }

    /**
     * @param array<string,mixed> $tour
     * @return int New post ID on success, 0 on failure.
     */
    private function insert_tour( array $tour, string $kwt_id ): int {
        $id = wp_insert_post( array(
            'post_type'    => Cpt::TOUR,
            'post_status'  => 'publish',
            'post_title'   => sanitize_text_field( (string) ( $tour['title'] ?? '' ) ),
            'post_excerpt' => sanitize_text_field( (string) ( $tour['descriptionShort'] ?? '' ) ),
            'post_content' => wp_strip_all_tags( (string) ( $tour['description'] ?? $tour['descriptionShort'] ?? '' ) ),
        ) );
        if ( is_int( $id ) && $id > 0 ) {
            $this->write_meta( $id, $tour, $kwt_id );
            return $id;
        }
        return 0;
    }

    /** @param array<string,mixed> $tour */
    private function update_tour( int $post_id, array $tour ): void {
        $locked = '1' === (string) get_post_meta( $post_id, self::META_LOCK, true );

        $payload = array( 'ID' => $post_id );
        if ( ! $locked ) {
            $payload['post_title']   = sanitize_text_field( (string) ( $tour['title'] ?? '' ) );
            $payload['post_excerpt'] = sanitize_text_field( (string) ( $tour['descriptionShort'] ?? '' ) );
            $payload['post_content'] = wp_strip_all_tags( (string) ( $tour['description'] ?? $tour['descriptionShort'] ?? '' ) );
        }
        wp_update_post( $payload );
        $this->write_meta( $post_id, $tour, (string) get_post_meta( $post_id, self::META_ID, true ) ?: (string) ( $tour['id'] ?? '' ) );
    }

    /** @param array<string,mixed> $tour */
    private function write_meta( int $post_id, array $tour, string $kwt_id ): void {
        update_post_meta( $post_id, self::META_ID, $kwt_id );
        update_post_meta( $post_id, 'kwt_slug', sanitize_title( (string) ( $tour['slug'] ?? '' ) ) );
        update_post_meta( $post_id, 'kwt_price', (int) ( $tour['price'] ?? 0 ) );
        update_post_meta( $post_id, 'kwt_duration_days', (int) ( $tour['durationDays'] ?? 0 ) );
        update_post_meta( $post_id, 'kwt_difficulty', sanitize_text_field( (string) ( $tour['difficulty'] ?? '' ) ) );
        update_post_meta( $post_id, 'kwt_type', sanitize_text_field( (string) ( $tour['type'] ?? '' ) ) );
        update_post_meta( $post_id, 'kwt_cover_url', $this->esc_url_raw_or_empty( $tour['coverImageUrl'] ?? '' ) );
        update_post_meta( $post_id, 'kwt_rating', (float) ( $tour['rating'] ?? 0 ) );
        update_post_meta( $post_id, 'kwt_review_count', (int) ( $tour['reviewCount'] ?? 0 ) );
        $gallery = array();
        if ( isset( $tour['gallery'] ) && is_array( $tour['gallery'] ) ) {
            foreach ( $tour['gallery'] as $url ) {
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
        }
    }

    /** esc_url_raw that tolerates empty/non-string input without a WP dependency in unit tests. */
    private function esc_url_raw_or_empty( $url ): string {
        $url = is_string( $url ) ? $url : '';
        return '' === $url ? '' : ( function_exists( 'esc_url_raw' ) ? esc_url_raw( $url ) : $url );
    }

    /**
     * @param array<int,string> $seen_ids
     * @return int number of posts drafted
     */
    private function unpublish_missing( array $seen_ids ): int {
        $all = get_posts( array(
            'post_type'      => Cpt::TOUR,
            'post_status'    => array( 'publish' ),
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) );

        $count = 0;
        foreach ( (array) $all as $post_id ) {
            $kwt_id = (string) get_post_meta( (int) $post_id, self::META_ID, true );
            if ( '' !== $kwt_id && ! in_array( $kwt_id, $seen_ids, true ) ) {
                wp_update_post( array( 'ID' => (int) $post_id, 'post_status' => 'draft' ) );
                $count++;
            }
        }
        return $count;
    }
}
