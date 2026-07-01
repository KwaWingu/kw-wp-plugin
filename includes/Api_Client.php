<?php
namespace KwaWingu\Tours;

/**
 * Thin client for the KwaWingu per-operator developer API (server-side only).
 */
class Api_Client {

    /** @var Settings */
    private $settings;

    public function __construct( Settings $settings ) {
        $this->settings = $settings;
    }

    /**
     * GET a path under /api/v1/{slug}. Returns the decoded JSON body.
     *
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     * @throws Api_Exception
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

        if ( is_wp_error( $response ) ) {
            throw new Api_Exception( 'Request to KwaWingu API failed.', 0 );
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

    /** @return array<string,mixed> */
    public function get_site(): array {
        return $this->get( '/site' );
    }
}
