<?php
/**
 * Thin HTTP client for the KwaWingu per-operator developer API.
 *
 * @package KwaWingu\Tours
 */

namespace KwaWingu\Tours;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Thin client for the KwaWingu per-operator developer API (server-side only).
 */
class Api_Client {

	/**
	 * Plugin settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Stores the settings dependency.
	 *
	 * @param Settings $settings Plugin settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * GET a path under /api/v1/{slug}. Returns the decoded JSON body.
	 *
	 * @param string              $path  API path relative to the slug root.
	 * @param array<string,mixed> $query Optional query parameters.
	 * @return array<string,mixed>
	 * @throws Api_Exception When the API request fails or returns a non-2xx status.
	 */
	public function get( string $path, array $query = array() ): array {
		$slug = $this->settings->get_slug();
		$key  = $this->settings->get_public_key();
		if ( '' === $slug || '' === $key ) {
			throw new Api_Exception( 'KwaWingu Tours is not configured (slug or public key missing).', 0 );
		}

		$url = KWT_API_BASE . '/' . rawurlencode( $slug ) . '/' . ltrim( $path, '/' );
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$response = wp_remote_get(
			esc_url_raw( $url ),
			array(
				'timeout' => 15,
				'headers' => array(
					'X-API-Key' => $key,
					'Accept'    => 'application/json',
				),
			)
		);

		return $this->handle_response( $response );
	}

	/**
	 * POST JSON to a path under /api/v1/{slug}. Uses the private key by default.
	 *
	 * @param string              $path            API path relative to the slug root.
	 * @param array<string,mixed> $body            JSON request body.
	 * @param bool                $use_private_key Whether to authenticate with the private key.
	 * @return array<string,mixed>
	 * @throws Api_Exception When the API request fails or returns a non-2xx status.
	 */
	public function post( string $path, array $body, bool $use_private_key = true ): array {
		$slug = $this->settings->get_slug();
		$key  = $use_private_key ? $this->settings->get_private_key() : $this->settings->get_public_key();
		if ( '' === $slug || '' === $key ) {
			throw new Api_Exception( 'KwaWingu Tours is not configured (slug or API key missing).', 0 );
		}
		$url = KWT_API_BASE . '/' . rawurlencode( $slug ) . '/' . ltrim( $path, '/' );

		$response = wp_remote_post(
			esc_url_raw( $url ),
			array(
				'timeout' => 20,
				'headers' => array(
					'X-API-Key'    => $key,
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		return $this->handle_response( $response );
	}

	/**
	 * Parses a wp_remote_* response and returns the decoded JSON body.
	 *
	 * @param mixed $response WP_Error or the wp_remote_* response array.
	 * @return array<string,mixed>
	 * @throws Api_Exception When the request errored or returned a non-2xx status.
	 */
	private function handle_response( $response ): array {
		if ( is_wp_error( $response ) ) {
			$reason = method_exists( $response, 'get_error_message' ) ? $response->get_error_message() : '';
			throw new Api_Exception( 'Request to KwaWingu API failed' . ( '' !== $reason ? ': ' . $reason : '.' ), 0 );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = (string) wp_remote_retrieve_body( $response );
		$json   = json_decode( $body, true );

		if ( $status < 200 || $status >= 300 ) {
			$code_string = '';
			$message     = 'KwaWingu API returned status ' . $status . '.';
			if ( is_array( $json ) && isset( $json['error'] ) && is_array( $json['error'] ) ) {
				$code_string = (string) ( $json['error']['code'] ?? '' );
				$message     = (string) ( $json['error']['message'] ?? $message );
			}
			throw new Api_Exception( $message, $status, $code_string );
		}

		if ( ! is_array( $json ) ) {
			throw new Api_Exception( 'KwaWingu API returned an invalid JSON body.', $status );
		}

		return $json;
	}

	/**
	 * Fetches the full site data for the configured operator slug.
	 *
	 * @return array<string,mixed>
	 */
	public function get_site(): array {
		return $this->get( '/site' );
	}
}
