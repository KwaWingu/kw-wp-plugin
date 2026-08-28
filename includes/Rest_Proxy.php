<?php
/**
 * Same-origin REST proxy for the KwaWingu operator API.
 *
 * @package KwaWingu\Tours
 */

namespace KwaWingu\Tours;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Same-origin REST proxy so the browser can use the operator's API without ever
 * seeing the keys. Reads use the public key; writes use the private key — both
 * only ever on the server, inside these handlers.
 */
class Rest_Proxy {

	const NS = 'kwawingu/v1';

	/** Header carrying the guest's per-booking portal token (BookingResult.portalToken). */
	const PORTAL_TOKEN_HEADER = 'X-Portal-Token';

	/**
	 * API client instance.
	 *
	 * @var Api_Client
	 */
	private $api;

	/**
	 * Optional operator notifier.
	 *
	 * @var Notifications|null
	 */
	private $notifications;

	/**
	 * Stores the API client dependency.
	 *
	 * @param Api_Client         $api           API client instance.
	 * @param Notifications|null $notifications Optional operator notifier.
	 */
	public function __construct( Api_Client $api, ?Notifications $notifications = null ) {
		$this->api           = $api;
		$this->notifications = $notifications;
	}

	/**
	 * Hooks the route registration into the REST API init action.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Registers all proxy REST routes under the kwawingu/v1 namespace.
	 */
	public function routes(): void {
		$auth = array( $this, 'check_nonce' );

		// Public: mint a fresh wp_rest nonce so interactive blocks recover on
		// full-page-cached sites where the page-baked nonce has expired.
		register_rest_route(
			self::NS,
			'/nonce',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_nonce' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NS,
			'/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_search' ),
				'permission_callback' => $auth,
				'args'                => array( 'q' => array( 'sanitize_callback' => 'sanitize_text_field' ) ),
			)
		);
		register_rest_route(
			self::NS,
			'/availability',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_availability' ),
				'permission_callback' => $auth,
			)
		);
		register_rest_route(
			self::NS,
			'/calculator/estimate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_calculator' ),
				'permission_callback' => $auth,
			)
		);
		register_rest_route(
			self::NS,
			'/bookings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_create_booking' ),
				'permission_callback' => $auth,
			)
		);
		register_rest_route(
			self::NS,
			'/payment-intent',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_payment_intent' ),
				'permission_callback' => $auth,
			)
		);
		register_rest_route(
			self::NS,
			'/booking',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_booking_lookup' ),
				'permission_callback' => $auth,
			)
		);
		register_rest_route(
			self::NS,
			'/departures',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_departures' ),
				'permission_callback' => $auth,
				'args'                => array( 'tourSlug' => array( 'sanitize_callback' => array( __CLASS__, 'sanitize_slug' ) ) ),
			)
		);
		register_rest_route(
			self::NS,
			'/quote',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_quote' ),
				'permission_callback' => $auth,
			)
		);
		register_rest_route(
			self::NS,
			'/inquiry',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_inquiry' ),
				'permission_callback' => $auth,
			)
		);
	}

	/**
	 * Sanitizes a tour slug REST argument.
	 *
	 * Not `sanitize_title` directly: REST calls a sanitize_callback as
	 * `( $value, $request, $param )`, and sanitize_title's second parameter is a
	 * *fallback title* returned whenever the value is empty — so `?tourSlug=` handed
	 * the handler the WP_REST_Request object, which blew up as "could not be
	 * converted to string" and turned every all-tours departures lookup into a 502.
	 *
	 * @param mixed $value Raw argument.
	 * @return string
	 */
	public static function sanitize_slug( $value ): string {
		return sanitize_title( is_scalar( $value ) ? (string) $value : '' );
	}

	/**
	 * Same-origin protection: validates the wp_rest nonce from the request header.
	 *
	 * @param mixed $request The REST request object.
	 * @return bool
	 */
	public function check_nonce( $request ): bool {
		$nonce = is_object( $request ) && method_exists( $request, 'get_header' ) ? (string) $request->get_header( 'X-WP-Nonce' ) : '';
		return (bool) wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * Returns a fresh wp_rest nonce (public — for cached-page recovery).
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return array<string,string>
	 */
	public function handle_nonce( $request ) {
		unset( $request );
		return array( 'nonce' => wp_create_nonce( 'wp_rest' ) );
	}

	/**
	 * Proxies a tour search request to the KwaWingu API.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function handle_search( $request ) {
		return $this->guard(
			function () use ( $request ) {
				$raw = $this->api->get( '/search', array( 'q' => (string) $request->get_param( 'q' ) ) );
				return self::search_results( $raw );
			}
		);
	}

	/**
	 * Shapes the API's search response for the search block.
	 *
	 * The API answers `{ tours: [...], destinations: [...], ... }` — its `SearchResults`
	 * schema has never had a `data` key — and a tour row carries a slug but no URL,
	 * because the API does not know where this site publishes its tours. The block
	 * reads `data[].url` / `data[].title`, so this resolves each hit to the synced
	 * kwt_tour permalink (falling back to the hosted booking page for a tour that has
	 * not been synced yet) and drops hits that resolve to nothing.
	 *
	 * @param array<string,mixed> $raw Decoded API response.
	 * @return array<string,mixed> `{ data: [ { title, slug, url, price, currency } ], total }`.
	 */
	public static function search_results( array $raw ): array {
		$rows = array();
		if ( isset( $raw['tours'] ) && is_array( $raw['tours'] ) ) {
			$rows = $raw['tours'];
		} elseif ( isset( $raw['data'] ) && is_array( $raw['data'] ) ) {
			$rows = $raw['data'];
		}
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$slug = (string) ( $row['slug'] ?? '' );
			$url  = (string) ( $row['url'] ?? '' );
			if ( '' === $url && '' !== $slug ) {
				$url = self::tour_url_for_slug( $slug );
			}
			if ( '' === $url ) {
				continue;
			}
			$price = $row['basePriceAdult'] ?? ( $row['price'] ?? null );
			$out[] = array(
				'title'    => (string) ( $row['title'] ?? $slug ),
				'slug'     => $slug,
				'url'      => $url,
				'price'    => null !== $price ? (int) round( (float) $price ) : null,
				'currency' => (string) ( $row['currency'] ?? '' ),
			);
		}
		return array(
			'data'  => $out,
			'total' => count( $out ),
		);
	}

	/**
	 * The local permalink of the synced tour with this slug, else the hosted booking
	 * page, else empty.
	 *
	 * @param string $slug KwaWingu tour slug.
	 * @return string
	 */
	private static function tour_url_for_slug( string $slug ): string {
		if ( function_exists( 'get_posts' ) ) {
			$ids = get_posts(
				array(
					'post_type'      => Cpt::TOUR,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => 'kwt_slug', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			if ( ! empty( $ids ) && function_exists( 'get_permalink' ) ) {
				$link = get_permalink( (int) $ids[0] );
				if ( is_string( $link ) && '' !== $link ) {
					return $link;
				}
			}
		}
		$operator = ( new Settings() )->get_slug();
		if ( '' === $operator ) {
			return '';
		}
		return Booking::hosted_base() . '/' . rawurlencode( $operator ) . '/tours/' . rawurlencode( $slug );
	}

	/**
	 * Proxies a tour availability request to the KwaWingu API.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function handle_availability( $request ) {
		return $this->guard(
			function () use ( $request ) {
				$slug = (string) $request->get_param( 'tourSlug' );
				$args = array();
				foreach ( array( 'from', 'to' ) as $k ) {
						$v = $request->get_param( $k );
					if ( null !== $v && '' !== $v ) {
						$args[ $k ] = (string) $v;
					}
				}
				return $this->api->get( '/tours/' . rawurlencode( $slug ) . '/availability', $args );
			}
		);
	}

	/**
	 * Proxies a trip calculator estimate request to the KwaWingu API.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function handle_calculator( $request ) {
		return $this->guard(
			function () use ( $request ) {
				$body = is_array( $request->get_json_params() ) ? $request->get_json_params() : array();
				return $this->api->post( '/calculator/estimate', $body, false );
			}
		);
	}

	/**
	 * Proxies a booking creation request to the KwaWingu API.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function handle_create_booking( $request ) {
		if ( ! $this->rate_ok( 'book' ) ) {
			return new \WP_Error( 'rate_limited', __( 'Too many requests. Please wait a moment.', 'kwawingu-tours' ), array( 'status' => 429 ) );
		}
		$body   = is_array( $request->get_json_params() ) ? $request->get_json_params() : array();
		$result = $this->guard(
			function () use ( $body ) {
				return $this->api->post( '/bookings', $body, true );
			}
		);
		if ( ! is_wp_error( $result ) && null !== $this->notifications ) {
			$this->notifications->on_booking_created( $body, is_array( $result ) ? $result : array() );
		}
		return $result;
	}

	/**
	 * Proxies a payment intent creation request to the KwaWingu API.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function handle_payment_intent( $request ) {
		if ( ! $this->rate_ok( 'pay' ) ) {
			return new \WP_Error( 'rate_limited', __( 'Too many requests. Please wait a moment.', 'kwawingu-tours' ), array( 'status' => 429 ) );
		}
		return $this->guard(
			function () use ( $request ) {
				$ref   = (string) $request->get_param( 'ref' );
				$phone = (string) $request->get_param( 'phone' );
				return $this->api->post( '/bookings/' . rawurlencode( $ref ) . '/payment-intent', array( 'phone' => $phone ), true );
			}
		);
	}

	/**
	 * Proxies a booking lookup request to the KwaWingu API.
	 *
	 * The guest is identified by the `X-Portal-Token` header — the per-booking
	 * secret returned as `portalToken` when the booking was created — and the
	 * header is forwarded as a header, never as a query parameter, so it stays
	 * out of access logs, CDN logs and Referer headers. The `?email=` lookup is
	 * deprecated by the API (Sunset 2027-07-01) and is used only when the caller
	 * has no token, e.g. a booking created before this version.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function handle_booking_lookup( $request ) {
		return $this->guard(
			function () use ( $request ) {
				$ref   = (string) $request->get_param( 'ref' );
				$token = trim( (string) $request->get_header( self::PORTAL_TOKEN_HEADER ) );
				if ( '' !== $token ) {
					return $this->api->get( '/bookings/' . rawurlencode( $ref ), array(), 15, array( self::PORTAL_TOKEN_HEADER => $token ) );
				}
				return $this->api->get( '/bookings/' . rawurlencode( $ref ), array( 'email' => (string) $request->get_param( 'email' ) ) );
			}
		);
	}

	/**
	 * List upcoming departures (optionally for one tour).
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function handle_departures( $request ) {
		return $this->guard(
			function () use ( $request ) {
				$slug = (string) $request->get_param( 'tourSlug' );
				if ( '' !== $slug ) {
					return $this->api->get( '/tours/' . rawurlencode( $slug ) . '/departures', array() );
				}
				return $this->api->get( '/departures', array() );
			}
		);
	}

	/**
	 * Price a trip (no booking created).
	 *
	 * POST /quote needs the `quotes:write` scope, which only a secret key can carry —
	 * the API answers `publishable_key_read_only` to the public key, so the on-site
	 * form's live price never appeared. The quote is only ever requested by the
	 * on-site booking form, which already requires the private key.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function handle_quote( $request ) {
		return $this->guard(
			function () use ( $request ) {
				$body = is_array( $request->get_json_params() ) ? $request->get_json_params() : array();
				return $this->api->post( '/quote', $body, true );
			}
		);
	}

	/**
	 * Submit a website inquiry to the KwaWingu API.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function handle_inquiry( $request ) {
		if ( ! $this->rate_ok( 'inquiry' ) ) {
			return new \WP_Error( 'rate_limited', __( 'Too many requests. Please wait a moment.', 'kwawingu-tours' ), array( 'status' => 429 ) );
		}
		$params = is_array( $request->get_json_params() ) ? $request->get_json_params() : array();

		$body = array(
			'name'   => sanitize_text_field( (string) ( $params['name'] ?? '' ) ),
			'email'  => sanitize_email( (string) ( $params['email'] ?? '' ) ),
			'adults' => max( 1, (int) ( $params['adults'] ?? 2 ) ),
		);

		$children = (int) ( $params['children'] ?? 0 );
		if ( $children > 0 ) {
			$body['children'] = $children;
		}

		foreach ( array( 'phone', 'message', 'tourSlug' ) as $field ) {
			if ( ! empty( $params[ $field ] ) ) {
				$body[ $field ] = sanitize_text_field( (string) $params[ $field ] );
			}
		}
		// The form field is `date`; the API's InquiryRequest names it `preferredDate` and
		// silently defaults an unknown key to today — so the guest's date was being lost.
		$date = sanitize_text_field( (string) ( $params['preferredDate'] ?? ( $params['date'] ?? '' ) ) );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$body['preferredDate'] = $date;
		}

		$result = $this->guard(
			function () use ( $body ) {
				return $this->api->post( '/inquiries', $body, true );
			}
		);

		if ( ! is_wp_error( $result ) && null !== $this->notifications ) {
			$this->notifications->on_inquiry_created( $body );
		}

		return $result;
	}

	/**
	 * Run an API call, mapping Api_Exception to a WP_Error with the API code/status.
	 * Also catches any other \Throwable to prevent raw 500s leaking internals.
	 *
	 * @param callable $callback The API call to execute.
	 * @return array<string,mixed>|\WP_Error
	 */
	private function guard( callable $callback ) {
		try {
			$out = $callback();
			Api_Status::record_success();
			return $out;
		} catch ( Api_Exception $e ) {
			Api_Status::record_failure( $e );
			$status = $e->get_status() >= 400 ? $e->get_status() : 502;
			$code   = '' !== $e->get_code_string() ? $e->get_code_string() : 'api_error';
			// The body reaches the visitor's browser: it carries the visitor-safe
			// sentence. The owner's sentence (plan, key, slug) is added only when the
			// caller is an administrator, so a logged-in owner testing the site
			// sees the fix rather than a generic "unavailable".
			$data = array( 'status' => $status );
			if ( function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) ) {
				$data['owner_message'] = Api_Status::owner_message( $e );
			}
			return new \WP_Error( $code, Api_Status::visitor_message( $e ), $data );
		} catch ( \Throwable $e ) {
			// Do NOT surface $e->getMessage() — avoids leaking internals. It goes to the
			// debug log instead, so a site owner with WP_DEBUG_LOG can see what failed.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KwaWingu Tours proxy: ' . get_class( $e ) . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return new \WP_Error( 'proxy_error', __( 'The request could not be completed.', 'kwawingu-tours' ), array( 'status' => 502 ) );
		}
	}

	/**
	 * Simple per-visitor rate limit for write routes: 20 per 10 minutes.
	 *
	 * @param string $bucket Rate-limit bucket name.
	 * @return bool
	 */
	private function rate_ok( string $bucket ): bool {
		if ( ! function_exists( 'get_transient' ) ) {
			return true;
		}
		$ip  = $this->client_ip();
		$key = 'kwt_rl_' . $bucket . '_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= 20 ) {
			return false;
		}
		set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Best-effort client IP for rate-limiting.
	 *
	 * Prefers CF-Connecting-IP (set by Cloudflare), then the first hop of
	 * X-Forwarded-For, then REMOTE_ADDR. Falls back to 'anon' if nothing
	 * is set.
	 *
	 * NOTE: X-Forwarded-For is spoofable unless the host is configured with
	 * a trusted-proxy allowlist. This is best-effort defense-in-depth; the
	 * real enforcement is the upstream API's own rate limit.
	 *
	 * @return string
	 */
	private function client_ip(): string {
		// Cloudflare sets this header and it cannot be spoofed by the end client.
		if ( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		}

		// X-Forwarded-For may contain a comma-separated chain; take only the first hop.
		if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$parts     = explode( ',', $forwarded );
			$first     = trim( $parts[0] );
			if ( '' !== $first ) {
				return $first;
			}
		}

		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return 'anon';
	}
}
