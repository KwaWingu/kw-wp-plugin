<?php
/**
 * Live price/availability reads for the freshness-critical parts of a tour.
 *
 * @package KwaWingu\Tours
 */

namespace KwaWingu\Tours;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * The kwt_tour post carries the SEO-bearing content — title, body, images,
 * permalink — and is worth keeping stale-tolerant. Price and availability are not:
 * a price that is hours old is a price you may have to honour.
 *
 * This reads those fields from the operator API on render, keyed by tour slug, and
 * hands the renderers a small array. One upstream call serves the whole page (and
 * the next 60 seconds of page views); when it fails, callers fall back to the stored
 * post meta rather than showing an error or an empty price.
 */
class Live_Catalog {

	const CACHE_KEY = 'kwt_live_catalog';

	/**
	 * The last snapshot the API actually returned, kept well beyond TTL. When the
	 * API is rate-limiting us or is down, this is what the page shows — a price a
	 * few minutes old rather than a blank one — and it is retried on the next TTL.
	 */
	const LAST_GOOD_KEY = 'kwt_live_catalog_last_good';

	/**
	 * Seconds a fetched catalog snapshot is reused for. Bounds the upstream load to
	 * one call per minute per site while keeping the displayed price a minute old at
	 * worst, instead of up to 24 hours.
	 */
	const TTL = 60;

	/**
	 * Seconds the last good snapshot is kept for. A day is long enough to ride out
	 * an outage and short enough that a price cannot go stale for a week unnoticed.
	 */
	const LAST_GOOD_TTL = 86400;

	/**
	 * Tours fetched per request. Matches the API's page size ceiling for the listing.
	 */
	const PAGE_SIZE = 100;

	/**
	 * Request timeout, in seconds. This call sits in front of a page render, so it
	 * gives up quickly and falls back rather than holding the visitor for 15 seconds.
	 */
	const TIMEOUT = 5;

	/**
	 * API client instance.
	 *
	 * @var Api_Client
	 */
	private $api;

	/**
	 * Per-request memo of the slug-keyed catalog.
	 *
	 * @var array<string,array<string,mixed>>|null
	 */
	private $memo = null;

	/**
	 * Shared instance used by the block render functions.
	 *
	 * @var Live_Catalog|null
	 */
	private static $instance = null;

	/**
	 * Constructor.
	 *
	 * @param Api_Client $api API client instance.
	 */
	public function __construct( Api_Client $api ) {
		$this->api = $api;
	}

	/**
	 * Sets (or clears) the instance the render functions use.
	 *
	 * @param Live_Catalog|null $instance Instance to share.
	 * @return void
	 */
	public static function set_instance( ?Live_Catalog $instance ): void {
		self::$instance = $instance;
	}

	/**
	 * Returns the shared instance, or null when the plugin has not booted it.
	 *
	 * @return Live_Catalog|null
	 */
	public static function instance(): ?Live_Catalog {
		return self::$instance;
	}

	/**
	 * Live fields for a tour post, or an empty array when unavailable.
	 *
	 * Static so block render functions — which receive no dependencies — can call it.
	 *
	 * @param int $post_id Tour post ID.
	 * @return array<string,mixed> Keys: price, currency, currentlyBookable, activeDepartures, nextDeparture.
	 */
	public static function for_post( int $post_id ): array {
		if ( null === self::$instance || $post_id <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return array();
		}
		$slug = (string) get_post_meta( $post_id, 'kwt_slug', true );
		return self::$instance->tour( $slug );
	}

	/**
	 * Live fields for one tour slug.
	 *
	 * @param string $slug Tour slug.
	 * @return array<string,mixed>
	 */
	public function tour( string $slug ): array {
		if ( '' === $slug ) {
			return array();
		}
		$all = $this->tours();
		return isset( $all[ $slug ] ) && is_array( $all[ $slug ] ) ? $all[ $slug ] : array();
	}

	/**
	 * The whole catalog, keyed by tour slug. Empty array when the API is unreachable.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function tours(): array {
		if ( null !== $this->memo ) {
			return $this->memo;
		}

		$cached = function_exists( 'get_transient' ) ? get_transient( self::CACHE_KEY ) : false;
		if ( is_array( $cached ) ) {
			$this->memo = $cached;
			return $this->memo;
		}

		$map = array();
		$ok  = false;
		try {
			$response = $this->api->get( '/tours', array( 'size' => self::PAGE_SIZE ), self::TIMEOUT );
			$rows     = array();
			if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
				$rows = $response['data'];
			} elseif ( isset( $response['tours'] ) && is_array( $response['tours'] ) ) {
				$rows = $response['tours'];
			}
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$slug = (string) ( $row['slug'] ?? '' );
				if ( '' === $slug ) {
					continue;
				}
				$map[ $slug ] = self::normalise( $row );
			}
			$ok = true;
			if ( function_exists( 'set_transient' ) ) {
				set_transient( self::LAST_GOOD_KEY, $map, self::LAST_GOOD_TTL );
			}
		} catch ( Api_Exception $e ) {
			// A refused or slow API must never break a page. Cache the outcome for the
			// same short window so one outage is not one upstream call per page view.
			Api_Status::record_failure( $e );
			$map = $e->is_retryable() ? $this->last_good() : array();
		} catch ( \Throwable $e ) {
			$map = $this->last_good();
		}

		if ( $ok ) {
			// Outside the try: bookkeeping must never be mistaken for an API failure.
			Api_Status::record_success();
		}
		if ( function_exists( 'set_transient' ) ) {
			set_transient( self::CACHE_KEY, $map, self::TTL );
		}
		$this->memo = $map;
		return $this->memo;
	}

	/**
	 * The last snapshot the API returned, or an empty array when there is none.
	 *
	 * Only served for retryable failures (rate limit, 5xx, transport). An
	 * entitlement or key refusal is not a reason to keep quoting live prices the
	 * operator is no longer being served — the synced post meta is the honest
	 * fallback there, and the owner is told why in wp-admin.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function last_good(): array {
		$v = function_exists( 'get_transient' ) ? get_transient( self::LAST_GOOD_KEY ) : false;
		return is_array( $v ) ? $v : array();
	}

	/**
	 * Reduces an API tour row to the freshness-critical fields.
	 *
	 * @param array<string,mixed> $row Tour row from the API.
	 * @return array<string,mixed>
	 */
	private static function normalise( array $row ): array {
		// basePriceAdult is the API's public "from" price (already the effective rate);
		// 'price' is accepted too so a summary payload that uses it still works.
		$price  = $row['basePriceAdult'] ?? ( $row['price'] ?? null );
		$active = isset( $row['activeDeparturesCount'] ) ? (int) $row['activeDeparturesCount'] : null;
		$next   = isset( $row['nextDepartureDate'] ) ? (string) $row['nextDepartureDate'] : '';

		return array(
			'price'            => null !== $price ? (int) round( (float) $price ) : null,
			'currency'         => (string) ( $row['currency'] ?? '' ),
			'activeDepartures' => $active,
			'nextDeparture'    => $next,
			// Unknown (null) is not the same as sold out: only say sold out when the API
			// actually told us there is nothing to sell.
			'soldOut'          => ( null !== $active && 0 === $active ) ? true : null,
		);
	}

	/**
	 * Drops the cached snapshot — called after a push so the next render is current.
	 *
	 * @return void
	 */
	public static function flush(): void {
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( self::CACHE_KEY );
		}
		if ( null !== self::$instance ) {
			self::$instance->memo = null;
		}
	}
}
