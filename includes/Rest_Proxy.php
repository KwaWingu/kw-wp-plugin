<?php
namespace KwaWingu\Tours;

/**
 * Same-origin REST proxy so the browser can use the operator's API without ever
 * seeing the keys. Reads use the public key; writes use the private key — both
 * only ever on the server, inside these handlers.
 */
class Rest_Proxy {

    const NS = 'kwawingu/v1';

    /** @var Api_Client */
    private $api;

    public function __construct( Api_Client $api ) {
        $this->api = $api;
    }

    public function register(): void {
        add_action( 'rest_api_init', array( $this, 'routes' ) );
    }

    public function routes(): void {
        $auth = array( $this, 'check_nonce' );

        register_rest_route( self::NS, '/search', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_search' ),
            'permission_callback' => $auth,
            'args'                => array( 'q' => array( 'sanitize_callback' => 'sanitize_text_field' ) ),
        ) );
        register_rest_route( self::NS, '/availability', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_availability' ),
            'permission_callback' => $auth,
        ) );
        register_rest_route( self::NS, '/calculator/estimate', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_calculator' ),
            'permission_callback' => $auth,
        ) );
        register_rest_route( self::NS, '/bookings', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_create_booking' ),
            'permission_callback' => $auth,
        ) );
        register_rest_route( self::NS, '/payment-intent', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_payment_intent' ),
            'permission_callback' => $auth,
        ) );
        register_rest_route( self::NS, '/booking', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_booking_lookup' ),
            'permission_callback' => $auth,
        ) );
    }

    /** Same-origin protection: a valid wp_rest nonce. */
    public function check_nonce( $request ): bool {
        $nonce = is_object( $request ) && method_exists( $request, 'get_header' ) ? (string) $request->get_header( 'X-WP-Nonce' ) : '';
        return (bool) wp_verify_nonce( $nonce, 'wp_rest' );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function handle_search( $request ) {
        return $this->guard( function () use ( $request ) {
            return $this->api->get( '/search', array( 'q' => (string) $request->get_param( 'q' ) ) );
        } );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function handle_availability( $request ) {
        return $this->guard( function () use ( $request ) {
            $slug = (string) $request->get_param( 'tourSlug' );
            $args = array();
            foreach ( array( 'from', 'to' ) as $k ) {
                $v = $request->get_param( $k );
                if ( null !== $v && '' !== $v ) {
                    $args[ $k ] = (string) $v;
                }
            }
            return $this->api->get( '/tours/' . rawurlencode( $slug ) . '/availability', $args );
        } );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function handle_calculator( $request ) {
        return $this->guard( function () use ( $request ) {
            $body = is_array( $request->get_json_params() ) ? $request->get_json_params() : array();
            return $this->api->post( '/calculator/estimate', $body, false );
        } );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function handle_create_booking( $request ) {
        if ( ! $this->rate_ok( 'book' ) ) {
            return new \WP_Error( 'rate_limited', __( 'Too many requests. Please wait a moment.', 'kwawingu-tours' ), array( 'status' => 429 ) );
        }
        return $this->guard( function () use ( $request ) {
            $body = is_array( $request->get_json_params() ) ? $request->get_json_params() : array();
            return $this->api->post( '/bookings', $body, true );
        } );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function handle_payment_intent( $request ) {
        if ( ! $this->rate_ok( 'pay' ) ) {
            return new \WP_Error( 'rate_limited', __( 'Too many requests. Please wait a moment.', 'kwawingu-tours' ), array( 'status' => 429 ) );
        }
        return $this->guard( function () use ( $request ) {
            $ref   = (string) $request->get_param( 'ref' );
            $phone = (string) $request->get_param( 'phone' );
            return $this->api->post( '/bookings/' . rawurlencode( $ref ) . '/payment-intent', array( 'phone' => $phone ), true );
        } );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function handle_booking_lookup( $request ) {
        return $this->guard( function () use ( $request ) {
            $ref = (string) $request->get_param( 'ref' );
            return $this->api->get( '/bookings/' . rawurlencode( $ref ), array( 'email' => (string) $request->get_param( 'email' ) ) );
        } );
    }

    /**
     * Run an API call, mapping Api_Exception to a WP_Error with the API code/status.
     *
     * @param callable():array<string,mixed> $fn
     * @return array<string,mixed>|\WP_Error
     */
    private function guard( callable $fn ) {
        try {
            return $fn();
        } catch ( Api_Exception $e ) {
            $status = $e->getCode() >= 400 ? $e->getCode() : 502;
            $code   = '' !== $e->get_code_string() ? $e->get_code_string() : 'api_error';
            return new \WP_Error( $code, $e->getMessage(), array( 'status' => $status ) );
        }
    }

    /** Simple per-visitor rate limit for write routes: 20 per 10 minutes. */
    private function rate_ok( string $bucket ): bool {
        if ( ! function_exists( 'get_transient' ) ) {
            return true;
        }
        $ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'anon';
        $key = 'kwt_rl_' . $bucket . '_' . md5( $ip );
        $n   = (int) get_transient( $key );
        if ( $n >= 20 ) {
            return false;
        }
        set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );
        return true;
    }
}
