<?php
namespace KwaWingu\Tours\Tests {

    use Brain\Monkey;
    use Brain\Monkey\Functions;
    use KwaWingu\Tours\Api_Client;
    use KwaWingu\Tours\Rest_Proxy;
    use Mockery;
    use PHPUnit\Framework\TestCase;

    class RestProxyTest extends TestCase {
        protected function setUp(): void {
            parent::setUp();
            Monkey\setUp();
            Functions\when( 'rest_ensure_response' )->returnArg();
        }
        protected function tearDown(): void { Monkey\tearDown(); Mockery::close(); parent::tearDown(); }

        public function test_register_hooks_rest_api_init(): void {
            ( new Rest_Proxy( Mockery::mock( Api_Client::class ) ) )->register();
            $this->assertNotFalse( has_action( 'rest_api_init' ) );
        }

        public function test_search_forwards_to_api_and_returns_data(): void {
            $api = Mockery::mock( Api_Client::class );
            $api->shouldReceive( 'get' )->once()->with( '/search', array( 'q' => 'safari' ) )
                ->andReturn( array( 'data' => array( array( 'title' => 'Safari' ) ) ) );

            $req = Mockery::mock();
            $req->shouldReceive( 'get_param' )->with( 'q' )->andReturn( 'safari' );

            $out = ( new Rest_Proxy( $api ) )->handle_search( $req );
            $this->assertSame( array( array( 'title' => 'Safari' ) ), $out['data'] );
        }

        public function test_payment_intent_uses_ref_and_phone(): void {
            $api = Mockery::mock( Api_Client::class );
            $api->shouldReceive( 'post' )->once()
                ->with( '/bookings/KWG-1/payment-intent', array( 'phone' => '255700' ), true )
                ->andReturn( array( 'reference' => 'r', 'paymentUrl' => '' ) );

            $req = Mockery::mock();
            $req->shouldReceive( 'get_param' )->with( 'ref' )->andReturn( 'KWG-1' );
            $req->shouldReceive( 'get_param' )->with( 'phone' )->andReturn( '255700' );

            $out = ( new Rest_Proxy( $api ) )->handle_payment_intent( $req );
            $this->assertSame( 'r', $out['reference'] );
        }

        public function test_handler_returns_wp_error_on_api_exception(): void {
            $api = Mockery::mock( Api_Client::class );
            $api->shouldReceive( 'get' )->andThrow( new \KwaWingu\Tours\Api_Exception( 'nope', 403, 'api_access_required' ) );
            $captured = null;
            Functions\when( 'is_wp_error' )->justReturn( false );
            // WP_Error is stubbed globally below; capture its construction args.
            $req = Mockery::mock();
            $req->shouldReceive( 'get_param' )->andReturn( 'x' );
            $out = ( new Rest_Proxy( $api ) )->handle_search( $req );
            $this->assertInstanceOf( \WP_Error::class, $out );
        }

	public function test_departures_forwards_to_tour_departures(): void {
		$api = \Mockery::mock( \KwaWingu\Tours\Api_Client::class );
		$api->shouldReceive( 'get' )->once()->with( '/tours/safari/departures', array() )
			->andReturn( array( 'data' => array( array( 'id' => 'D1' ) ) ) );
		$req = \Mockery::mock();
		$req->shouldReceive( 'get_param' )->with( 'tourSlug' )->andReturn( 'safari' );
		$out = ( new \KwaWingu\Tours\Rest_Proxy( $api ) )->handle_departures( $req );
		$this->assertSame( 'D1', $out['data'][0]['id'] );
	}

	public function test_quote_forwards_body_with_public_key(): void {
		$api = \Mockery::mock( \KwaWingu\Tours\Api_Client::class );
		$api->shouldReceive( 'post' )->once()
			->with( '/quote', array( 'tourSlug' => 'safari', 'adults' => 2 ), false )
			->andReturn( array( 'data' => array( 'total' => 900000 ) ) );
		$req = \Mockery::mock();
		$req->shouldReceive( 'get_json_params' )->andReturn( array( 'tourSlug' => 'safari', 'adults' => 2 ) );
		$out = ( new \KwaWingu\Tours\Rest_Proxy( $api ) )->handle_quote( $req );
		$this->assertSame( 900000, $out['data']['total'] );
	}

	public function test_nonce_endpoint_returns_fresh_nonce(): void {
		Functions\when( 'wp_create_nonce' )->justReturn( 'fresh123' );
		$out = ( new Rest_Proxy( Mockery::mock( Api_Client::class ) ) )->handle_nonce( Mockery::mock() );
		$this->assertSame( array( 'nonce' => 'fresh123' ), $out );
	}

        public function test_generic_throwable_maps_to_proxy_error(): void {
            Functions\when( '__' )->returnArg();

            $api = Mockery::mock( Api_Client::class );
            $api->shouldReceive( 'get' )->andThrow( new \RuntimeException( 'boom' ) );

            $req = Mockery::mock();
            $req->shouldReceive( 'get_param' )->andReturn( 'x' );

            $out = ( new Rest_Proxy( $api ) )->handle_search( $req );
            $this->assertInstanceOf( \WP_Error::class, $out );
            $this->assertSame( 'proxy_error', $out->code );
            $this->assertSame( 502, $out->data['status'] );
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
}
