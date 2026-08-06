<?php
namespace KwaWingu\Tours\Tests {

    use Brain\Monkey;
    use Brain\Monkey\Functions;
    use KwaWingu\Tours\Live_Catalog;
    use KwaWingu\Tours\Push_Endpoint;
    use KwaWingu\Tours\Settings;
    use KwaWingu\Tours\Sync_Controller;
    use Mockery;
    use PHPUnit\Framework\TestCase;

    /** Minimal stand-in for WP_REST_Request. */
    class Fake_Request {
        private $headers;
        private $body;
        public function __construct( array $headers, string $body ) {
            $this->headers = $headers;
            $this->body    = $body;
        }
        public function get_header( $name ) {
            return $this->headers[ $name ] ?? '';
        }
        public function get_body() {
            return $this->body;
        }
        public function get_json_params() {
            $decoded = json_decode( $this->body, true );
            return is_array( $decoded ) ? $decoded : array();
        }
    }

    class PushEndpointTest extends TestCase {

        const SECRET = 'shhh-super-secret';

        protected function setUp(): void {
            parent::setUp();
            Monkey\setUp();
            Functions\when( '__' )->returnArg();
            Functions\when( 'sanitize_key' )->returnArg();
            Functions\when( 'sanitize_text_field' )->returnArg();
            Functions\when( 'delete_transient' )->justReturn( true );
            Live_Catalog::set_instance( null );
        }

        protected function tearDown(): void {
            Monkey\tearDown();
            Mockery::close();
            parent::tearDown();
        }

        /** A settings double with a configured (or empty) push secret. */
        private function settings( string $secret = self::SECRET, string $slug = 'acme' ): Settings {
            $settings = Mockery::mock( Settings::class );
            $settings->shouldReceive( 'get_push_secret' )->andReturn( $secret );
            $settings->shouldReceive( 'get_slug' )->andReturn( $slug );
            return $settings;
        }

        /**
         * A request double carrying headers, raw body and decoded params.
         *
         * A real object rather than a Mockery mock: the endpoint gates on
         * method_exists(), which is false for a mock's magic __call methods.
         */
        private function request( string $timestamp, string $signature, string $body ) {
            return new Fake_Request(
                array(
                    'X-KW-Timestamp' => $timestamp,
                    'X-KW-Signature' => $signature,
                ),
                $body
            );
        }

        private function sign( string $timestamp, string $body, string $secret = self::SECRET ): string {
            return hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
        }

        public function test_register_hooks_rest_api_init(): void {
            ( new Push_Endpoint( $this->settings(), Mockery::mock( Sync_Controller::class ) ) )->register();
            $this->assertNotFalse( has_action( 'rest_api_init' ) );
        }

        public function test_valid_signature_is_accepted(): void {
            $ts   = (string) time();
            $body = '{"operatorSlug":"acme","reason":"tour.updated"}';

            $endpoint = new Push_Endpoint( $this->settings(), Mockery::mock( Sync_Controller::class ) );
            $this->assertTrue(
                $endpoint->verify_signature( $this->request( $ts, $this->sign( $ts, $body ), $body ) )
            );
        }

        public function test_uppercase_hex_signature_is_accepted(): void {
            $ts   = (string) time();
            $body = '{"reason":"tour.updated"}';

            $endpoint = new Push_Endpoint( $this->settings(), Mockery::mock( Sync_Controller::class ) );
            $this->assertTrue(
                $endpoint->verify_signature( $this->request( $ts, strtoupper( $this->sign( $ts, $body ) ), $body ) )
            );
        }

        public function test_bad_signature_is_401(): void {
            $ts   = (string) time();
            $body = '{"reason":"tour.updated"}';

            $endpoint = new Push_Endpoint( $this->settings(), Mockery::mock( Sync_Controller::class ) );
            $out      = $endpoint->verify_signature( $this->request( $ts, str_repeat( 'a', 64 ), $body ) );

            $this->assertInstanceOf( \WP_Error::class, $out );
            $this->assertSame( 401, $out->data['status'] );
        }

        public function test_signature_over_a_reencoded_body_is_rejected(): void {
            // The raw body is what gets signed. A re-encoded equivalent must NOT verify —
            // this is the check that catches a handler hashing get_json_params() instead.
            $ts       = (string) time();
            $raw      = '{"operatorSlug":"acme", "reason":"tour.updated"}';
            $reencode = (string) json_encode( json_decode( $raw, true ) );

            $endpoint = new Push_Endpoint( $this->settings(), Mockery::mock( Sync_Controller::class ) );
            $out      = $endpoint->verify_signature( $this->request( $ts, $this->sign( $ts, $reencode ), $raw ) );

            $this->assertInstanceOf( \WP_Error::class, $out );
            $this->assertSame( 401, $out->data['status'] );
        }

        public function test_stale_timestamp_is_401(): void {
            $ts   = (string) ( time() - 301 );
            $body = '{"reason":"tour.updated"}';

            $endpoint = new Push_Endpoint( $this->settings(), Mockery::mock( Sync_Controller::class ) );
            $out      = $endpoint->verify_signature( $this->request( $ts, $this->sign( $ts, $body ), $body ) );

            $this->assertInstanceOf( \WP_Error::class, $out );
            $this->assertSame( 401, $out->data['status'] );
        }

        public function test_future_timestamp_beyond_skew_is_401(): void {
            $ts   = (string) ( time() + 3600 );
            $body = '{}';

            $endpoint = new Push_Endpoint( $this->settings(), Mockery::mock( Sync_Controller::class ) );
            $out      = $endpoint->verify_signature( $this->request( $ts, $this->sign( $ts, $body ), $body ) );

            $this->assertInstanceOf( \WP_Error::class, $out );
            $this->assertSame( 401, $out->data['status'] );
        }

        public function test_missing_headers_are_401(): void {
            $endpoint = new Push_Endpoint( $this->settings(), Mockery::mock( Sync_Controller::class ) );
            $out      = $endpoint->verify_signature( $this->request( '', '', '{}' ) );

            $this->assertInstanceOf( \WP_Error::class, $out );
            $this->assertSame( 401, $out->data['status'] );
        }

        public function test_unset_secret_is_503(): void {
            $ts   = (string) time();
            $body = '{}';

            $endpoint = new Push_Endpoint( $this->settings( '' ), Mockery::mock( Sync_Controller::class ) );
            $out      = $endpoint->verify_signature( $this->request( $ts, $this->sign( $ts, $body ), $body ) );

            $this->assertInstanceOf( \WP_Error::class, $out );
            $this->assertSame( 503, $out->data['status'] );
        }

        public function test_handler_queues_sync_and_returns_202(): void {
            $controller = Mockery::mock( Sync_Controller::class );
            $controller->shouldReceive( 'schedule_immediate' )->once()->andReturn( true );

            $body = '{"operatorSlug":"acme","reason":"tour.updated"}';
            $out  = ( new Push_Endpoint( $this->settings(), $controller ) )
                ->handle_resync( $this->request( (string) time(), '', $body ) );

            $this->assertInstanceOf( \WP_REST_Response::class, $out );
            $this->assertSame( 202, $out->status );
            $this->assertSame( 'accepted', $out->data['status'] );
            $this->assertTrue( $out->data['queued'] );
            $this->assertSame( 'tour.updated', $out->data['reason'] );
        }

        public function test_repeat_push_coalesces_into_the_pending_run(): void {
            $controller = Mockery::mock( Sync_Controller::class );
            $controller->shouldReceive( 'schedule_immediate' )->once()->andReturn( false );

            $out = ( new Push_Endpoint( $this->settings(), $controller ) )
                ->handle_resync( $this->request( (string) time(), '', '{}' ) );

            $this->assertSame( 202, $out->status );
            $this->assertFalse( $out->data['queued'] );
            $this->assertTrue( $out->data['alreadyQueued'] );
        }

        public function test_push_for_another_operator_is_409(): void {
            $controller = Mockery::mock( Sync_Controller::class );
            $controller->shouldNotReceive( 'schedule_immediate' );

            $body = '{"operatorSlug":"someone-else"}';
            $out  = ( new Push_Endpoint( $this->settings(), $controller ) )
                ->handle_resync( $this->request( (string) time(), '', $body ) );

            $this->assertInstanceOf( \WP_Error::class, $out );
            $this->assertSame( 409, $out->data['status'] );
        }
    }
}

namespace {
    if ( ! class_exists( 'WP_Error' ) ) {
        class WP_Error {
            public $code; public $message; public $data;
            public function __construct( $code = '', $message = '', $data = null ) {
                $this->code = $code; $this->message = $message; $this->data = $data;
            }
        }
    }
    if ( ! class_exists( 'WP_REST_Response' ) ) {
        class WP_REST_Response {
            public $data; public $status;
            public function __construct( $data = null, $status = 200 ) {
                $this->data = $data; $this->status = $status;
            }
        }
    }
}
