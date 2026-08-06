<?php
/**
 * Signed push endpoint: lets KwaWingu trigger a catalog resync within seconds.
 *
 * @package KwaWingu\Tours
 */

namespace KwaWingu\Tours;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * POST /wp-json/kwt/v1/resync — an HMAC-signed request from KwaWingu that queues
 * an immediate catalog sync, so an edit in KwaWingu reaches WordPress in seconds
 * instead of waiting for the next cron tick.
 *
 * Authentication is by shared secret, not by WordPress user: there is no logged-in
 * user on a server-to-server call. The signature check IS the permission callback.
 */
class Push_Endpoint {

	const NS = 'kwt/v1';

	/**
	 * Maximum accepted clock skew, in seconds, between signer and this site.
	 */
	const MAX_SKEW = 300;

	/**
	 * Largest body we will hash. A push payload is a few dozen bytes.
	 */
	const MAX_BODY = 8192;

	/**
	 * Plugin settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Sync controller instance.
	 *
	 * @var Sync_Controller
	 */
	private $controller;

	/**
	 * Constructor.
	 *
	 * @param Settings        $settings   Plugin settings instance.
	 * @param Sync_Controller $controller Sync controller instance.
	 */
	public function __construct( Settings $settings, Sync_Controller $controller ) {
		$this->settings   = $settings;
		$this->controller = $controller;
	}

	/**
	 * Hooks route registration into the REST API init action.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Registers the resync route.
	 *
	 * @return void
	 */
	public function routes(): void {
		register_rest_route(
			self::NS,
			'/resync',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_resync' ),
				'permission_callback' => array( $this, 'verify_signature' ),
			)
		);
	}

	/**
	 * The public URL callers should sign and POST to.
	 *
	 * @return string
	 */
	public static function endpoint_url(): string {
		return rest_url( self::NS . '/resync' );
	}

	/**
	 * Permission callback: verifies the HMAC signature and replay window.
	 *
	 * Contract:
	 *   X-KW-Timestamp: unix seconds
	 *   X-KW-Signature: lowercase hex of HMAC-SHA256( secret, "{timestamp}.{rawBody}" )
	 *
	 * @param mixed $request The REST request object.
	 * @return true|\WP_Error True when the signature is valid, WP_Error otherwise.
	 */
	public function verify_signature( $request ) {
		$secret = $this->settings->get_push_secret();
		if ( '' === $secret ) {
			return new \WP_Error(
				'kwt_push_not_configured',
				__( 'Push resync is not configured on this site.', 'kwawingu-tours' ),
				array( 'status' => 503 )
			);
		}

		$timestamp = $this->header( $request, 'X-KW-Timestamp' );
		$signature = strtolower( $this->header( $request, 'X-KW-Signature' ) );
		$body      = is_object( $request ) && method_exists( $request, 'get_body' ) ? (string) $request->get_body() : '';

		if ( '' === $timestamp || '' === $signature || strlen( $body ) > self::MAX_BODY ) {
			return $this->unauthorized();
		}
		if ( 1 !== preg_match( '/^[0-9]{1,12}$/', $timestamp ) ) {
			return $this->unauthorized();
		}
		// Replay protection: a captured request stops working after MAX_SKEW seconds.
		if ( abs( time() - (int) $timestamp ) > self::MAX_SKEW ) {
			return $this->unauthorized();
		}

		$expected = hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
		if ( ! hash_equals( $expected, $signature ) ) {
			return $this->unauthorized();
		}

		return true;
	}

	/**
	 * Queues the sync and answers immediately.
	 *
	 * @param mixed $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_resync( $request ) {
		$params = is_object( $request ) && method_exists( $request, 'get_json_params' ) ? $request->get_json_params() : array();
		$params = is_array( $params ) ? $params : array();

		$configured = $this->settings->get_slug();
		$slug       = sanitize_key( (string) ( $params['operatorSlug'] ?? '' ) );
		if ( '' !== $slug && '' !== $configured && $slug !== $configured ) {
			return new \WP_Error(
				'kwt_slug_mismatch',
				__( 'This site is connected to a different operator.', 'kwawingu-tours' ),
				array( 'status' => 409 )
			);
		}

		$reason = sanitize_text_field( (string) ( $params['reason'] ?? '' ) );
		$queued = $this->controller->schedule_immediate();

		// Price and availability are read live with a short cache; drop it now so the
		// very next page view reflects the change even before the sync run lands.
		Live_Catalog::flush();

		return new \WP_REST_Response(
			array(
				'status'        => 'accepted',
				'queued'        => $queued,
				'alreadyQueued' => ! $queued,
				'operatorSlug'  => $configured,
				'reason'        => $reason,
			),
			202
		);
	}

	/**
	 * Reads a request header, tolerating request objects without one.
	 *
	 * @param mixed  $request The REST request object.
	 * @param string $name    Header name.
	 * @return string
	 */
	private function header( $request, string $name ): string {
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_header' ) ) {
			return '';
		}
		return trim( (string) $request->get_header( $name ) );
	}

	/**
	 * One generic rejection for every failed check — never say which check failed.
	 *
	 * @return \WP_Error
	 */
	private function unauthorized(): \WP_Error {
		return new \WP_Error(
			'kwt_push_unauthorized',
			__( 'Invalid signature.', 'kwawingu-tours' ),
			array( 'status' => 401 )
		);
	}
}
