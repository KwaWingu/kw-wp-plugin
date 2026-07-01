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

	/**
	 * API client instance.
	 *
	 * @var Api_Client
	 */
	private $api;

	/**
	 * Stores the API client dependency.
	 *
	 * @param Api_Client $api API client instance.
	 */
	public function __construct( Api_Client $api ) {
		$this->api = $api;
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
	 * Proxies a tour search request to the KwaWingu API.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function handle_search( $request ) {
		return $this->guard(
			function () use ( $request ) {
				return $this->api->get( '/search', array( 'q' => (string) $request->get_param( 'q' ) ) );
			}
		);
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
		return $this->guard(
			function () use ( $request ) {
				$body = is_array( $request->get_json_params() ) ? $request->get_json_params() : array();
				return $this->api->post( '/bookings', $body, true );
			}
		);
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
	 * @param \WP_REST_Request $request The REST request object.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function handle_booking_lookup( $request ) {
		return $this->guard(
			function () use ( $request ) {
				$ref = (string) $request->get_param( 'ref' );
				return $this->api->get( '/bookings/' . rawurlencode( $ref ), array( 'email' => (string) $request->get_param( 'email' ) ) );
			}
		);
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
			return $callback();
		} catch ( Api_Exception $e ) {
			$status = $e->getCode() >= 400 ? $e->getCode() : 502;
			$code   = '' !== $e->get_code_string() ? $e->get_code_string() : 'api_error';
			return new \WP_Error( $code, $e->getMessage(), array( 'status' => $status ) );
		} catch ( \Throwable $e ) {
			// Do NOT surface $e->getMessage() — avoids leaking internals.
			// Real enforcement is the upstream API's own error handling.
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
