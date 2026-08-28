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
            Functions\when( 'wp_specialchars_decode' )->alias( static function ( $t ) { return html_entity_decode( (string) $t, ENT_QUOTES ); } );
            Functions\when( 'rest_ensure_response' )->returnArg();
            Functions\when( 'get_option' )->justReturn( false );
            Functions\when( 'update_option' )->justReturn( true );
            Functions\when( 'delete_option' )->justReturn( true );
            Functions\when( '__' )->returnArg();
            Functions\when( 'current_user_can' )->justReturn( false );
        }
        protected function tearDown(): void { Monkey\tearDown(); Mockery::close(); parent::tearDown(); }

        public function test_register_hooks_rest_api_init(): void {
            ( new Rest_Proxy( Mockery::mock( Api_Client::class ) ) )->register();
            $this->assertNotFalse( has_action( 'rest_api_init' ) );
        }

        public function test_search_reshapes_the_api_tours_section_into_data_with_local_urls(): void {
            // The API's SearchResults schema is { tours: [...] } with no url per tour. The
            // block reads data[].url, so the proxy resolves each slug to the synced post.
            $api = Mockery::mock( Api_Client::class );
            $api->shouldReceive( 'get' )->once()->with( '/search', array( 'q' => 'safari' ) )
                ->andReturn(
                    array(
                        'tours'        => array(
                            array( 'title' => 'Safari', 'slug' => 'safari', 'basePriceAdult' => 450000.0, 'currency' => 'TZS' ),
                        ),
                        'destinations' => array( array( 'name' => 'Serengeti' ) ),
                        'total'        => 1,
                    )
                );
            Functions\when( 'get_posts' )->justReturn( array( 7 ) );
            Functions\when( 'get_permalink' )->justReturn( 'https://example.test/tours/safari/' );

            $req = Mockery::mock();
            $req->shouldReceive( 'get_param' )->with( 'q' )->andReturn( 'safari' );

            $out = ( new Rest_Proxy( $api ) )->handle_search( $req );
            $this->assertSame(
                array(
                    array(
                        'title'    => 'Safari',
                        'slug'     => 'safari',
                        'url'      => 'https://example.test/tours/safari/',
                        'price'    => 450000,
                        'currency' => 'TZS',
                    ),
                ),
                $out['data']
            );
            $this->assertSame( 1, $out['total'] );
        }

        public function test_search_falls_back_to_the_hosted_booking_page_for_an_unsynced_tour(): void {
            Functions\when( 'get_posts' )->justReturn( array() );
            Functions\when( 'get_option' )->justReturn( array( 'slug' => 'acme' ) );

            $out = Rest_Proxy::search_results( array( 'tours' => array( array( 'title' => 'New', 'slug' => 'new-tour' ) ) ) );

            $this->assertSame( 'https://tours.kwawingu.com/acme/tours/new-tour', $out['data'][0]['url'] );
        }

        public function test_search_drops_rows_it_cannot_link_and_accepts_a_data_envelope(): void {
            Functions\when( 'get_posts' )->justReturn( array() );
            // No operator slug configured and no slug on the row: nothing to link to.
            $out = Rest_Proxy::search_results( array( 'data' => array( array( 'title' => 'Orphan' ), array( 'title' => 'Linked', 'url' => 'https://x.test/t' ) ) ) );

            $this->assertCount( 1, $out['data'] );
            $this->assertSame( 'Linked', $out['data'][0]['title'] );
        }

        public function test_payment_intent_uses_ref_and_phone(): void {
            // Rate limiting runs when get_transient exists; another test file may have
            // defined it already, so stub it here rather than depend on test order.
            Functions\when( 'get_transient' )->justReturn( 0 );
            Functions\when( 'set_transient' )->justReturn( true );

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

        public function test_booking_lookup_forwards_the_portal_token_as_a_header_not_a_query_param(): void {
            $api = Mockery::mock( Api_Client::class );
            $api->shouldReceive( 'get' )->once()
                ->with( '/bookings/KWG-1', array(), 15, array( 'X-Portal-Token' => 'tok-1' ) )
                ->andReturn( array( 'status' => 'paid' ) );

            $req = Mockery::mock();
            $req->shouldReceive( 'get_param' )->with( 'ref' )->andReturn( 'KWG-1' );
            // Even if a stale client sends both, the token wins and the email is never forwarded.
            $req->shouldReceive( 'get_param' )->with( 'email' )->andReturn( 'g@example.com' );
            $req->shouldReceive( 'get_header' )->with( 'X-Portal-Token' )->andReturn( ' tok-1 ' );

            $out = ( new Rest_Proxy( $api ) )->handle_booking_lookup( $req );
            $this->assertSame( 'paid', $out['status'] );
        }

        public function test_booking_lookup_falls_back_to_the_deprecated_email_lookup_without_a_token(): void {
            $api = Mockery::mock( Api_Client::class );
            $api->shouldReceive( 'get' )->once()
                ->with( '/bookings/KWG-1', array( 'email' => 'g@example.com' ) )
                ->andReturn( array( 'status' => 'pending' ) );

            $req = Mockery::mock();
            $req->shouldReceive( 'get_param' )->with( 'ref' )->andReturn( 'KWG-1' );
            $req->shouldReceive( 'get_param' )->with( 'email' )->andReturn( 'g@example.com' );
            $req->shouldReceive( 'get_header' )->with( 'X-Portal-Token' )->andReturn( null );

            $out = ( new Rest_Proxy( $api ) )->handle_booking_lookup( $req );
            $this->assertSame( 'pending', $out['status'] );
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

        public function test_entitlement_refusal_is_hidden_from_visitors(): void {
            $api = Mockery::mock( Api_Client::class );
            $api->shouldReceive( 'get' )->andThrow( new \KwaWingu\Tours\Api_Exception( 'Enable API access in your dashboard to use the API.', 403, 'api_access_required' ) );
            $req = Mockery::mock();
            $req->shouldReceive( 'get_param' )->andReturn( 'x' );

            $out = ( new Rest_Proxy( $api ) )->handle_search( $req );

            $this->assertSame( 'api_access_required', $out->code );
            $this->assertSame( 403, $out->data['status'] );
            $this->assertStringNotContainsString( 'API', $out->message );
            $this->assertStringContainsString( 'not available at the moment', $out->message );
            $this->assertArrayNotHasKey( 'owner_message', $out->data );
        }

        public function test_entitlement_refusal_tells_a_logged_in_admin_the_fix(): void {
            Functions\when( 'current_user_can' )->justReturn( true );
            $recorded = null;
            Functions\when( 'update_option' )->alias( static function ( $k, $v ) use ( &$recorded ) { $recorded = $v; return true; } );
            $api = Mockery::mock( Api_Client::class );
            $api->shouldReceive( 'get' )->andThrow( new \KwaWingu\Tours\Api_Exception( 'nope', 403, 'api_access_required' ) );
            $req = Mockery::mock();
            $req->shouldReceive( 'get_param' )->andReturn( 'x' );

            $out = ( new Rest_Proxy( $api ) )->handle_search( $req );

            $this->assertStringContainsString( 'plan does not include API access', $out->data['owner_message'] );
            $this->assertSame( 'entitlement', $recorded['kind'] );
        }

        public function test_rate_limit_asks_the_visitor_to_retry_and_keeps_429(): void {
            $api = Mockery::mock( Api_Client::class );
            $api->shouldReceive( 'get' )->andThrow( new \KwaWingu\Tours\Api_Exception( 'Too many requests', 429, 'rate_limited' ) );
            $req = Mockery::mock();
            $req->shouldReceive( 'get_param' )->andReturn( 'x' );

            $out = ( new Rest_Proxy( $api ) )->handle_search( $req );

            $this->assertSame( 429, $out->data['status'] );
            $this->assertStringContainsString( 'try again in a moment', $out->message );
        }

        public function test_business_refusal_reaches_the_visitor_verbatim(): void {
            $api = Mockery::mock( Api_Client::class );
            $api->shouldReceive( 'get' )->andThrow( new \KwaWingu\Tours\Api_Exception( 'That departure is sold out.', 409, 'hold_unavailable' ) );
            $req = Mockery::mock();
            $req->shouldReceive( 'get_param' )->andReturn( 'x' );

            $out = ( new Rest_Proxy( $api ) )->handle_search( $req );

            $this->assertSame( 'hold_unavailable', $out->code );
            $this->assertSame( 'That departure is sold out.', $out->message );
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

	public function test_quote_forwards_body_with_private_key(): void {
		// POST /quote needs quotes:write, a scope only a secret key can hold; the API
		// answers the public key with 403 publishable_key_read_only.
		$api = \Mockery::mock( \KwaWingu\Tours\Api_Client::class );
		$api->shouldReceive( 'post' )->once()
			->with( '/quote', array( 'tourSlug' => 'safari', 'adults' => 2 ), true )
			->andReturn( array( 'data' => array( 'total' => 900000 ) ) );
		$req = \Mockery::mock();
		$req->shouldReceive( 'get_json_params' )->andReturn( array( 'tourSlug' => 'safari', 'adults' => 2 ) );
		$out = ( new \KwaWingu\Tours\Rest_Proxy( $api ) )->handle_quote( $req );
		$this->assertSame( 900000, $out['data']['total'] );
	}

	public function test_slug_sanitizer_ignores_the_request_object_rest_passes_as_second_arg(): void {
		// REST calls sanitize callbacks as ( $value, $request, $param ). sanitize_title()
		// treats arg 2 as a fallback title for an empty value, so wiring it directly
		// returned the WP_REST_Request object for `?tourSlug=`.
		Functions\when( 'sanitize_title' )->alias( static function ( $title, $fallback = '' ) {
			return '' === $title ? $fallback : strtolower( $title );
		} );
		$request = Mockery::mock();
		$this->assertSame( '', Rest_Proxy::sanitize_slug( '' ) );
		$this->assertSame( '', call_user_func( array( Rest_Proxy::class, 'sanitize_slug' ), '', $request, 'tourSlug' ) );
		$this->assertSame( 'kili', call_user_func( array( Rest_Proxy::class, 'sanitize_slug' ), 'Kili', $request, 'tourSlug' ) );
		$this->assertSame( '', Rest_Proxy::sanitize_slug( $request ) );
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

	public function test_inquiry_forwards_to_inquiries_with_private_key(): void {
		$api = \Mockery::mock( \KwaWingu\Tours\Api_Client::class );
		$api->shouldReceive( 'post' )->once()
			->with( '/inquiries', \Mockery::on( function ( $body ) {
				// The form's `date` must reach the API as `preferredDate` (its InquiryRequest
				// field); an unknown `date` key was silently dropped and defaulted to today.
				return isset( $body['name'] ) && isset( $body['email'] )
					&& '2026-10-05' === ( $body['preferredDate'] ?? null )
					&& ! array_key_exists( 'date', $body );
			} ), true )
			->andReturn( array( 'status' => 'received' ) );

		$req = \Mockery::mock();
		$req->shouldReceive( 'get_json_params' )->andReturn( array(
			'name'    => 'Jane Doe',
			'email'   => 'jane@example.com',
			'adults'  => 2,
			'message' => 'Hello',
			'date'    => '2026-10-05',
		) );

		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'get_transient' )->justReturn( 0 );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'is_wp_error' )->justReturn( false );

		$out = ( new \KwaWingu\Tours\Rest_Proxy( $api ) )->handle_inquiry( $req );
		$this->assertSame( 'received', $out['status'] );
	}

	public function test_inquiry_rejected_when_rate_limited(): void {
		$api = \Mockery::mock( \KwaWingu\Tours\Api_Client::class );
		$api->shouldNotReceive( 'post' );

		$req = \Mockery::mock();
		$req->shouldReceive( 'get_json_params' )->andReturn( array() );

		Functions\when( 'get_transient' )->justReturn( 20 );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( '__' )->returnArg();

		$out = ( new \KwaWingu\Tours\Rest_Proxy( $api ) )->handle_inquiry( $req );
		$this->assertInstanceOf( \WP_Error::class, $out );
		$this->assertSame( 429, $out->data['status'] );
	}

	public function test_inquiry_nonce_rejection_via_check_nonce(): void {
		Functions\when( 'wp_verify_nonce' )->justReturn( false );
		$req = \Mockery::mock();
		$req->shouldReceive( 'get_header' )->with( 'X-WP-Nonce' )->andReturn( 'bad' );

		$result = ( new \KwaWingu\Tours\Rest_Proxy( \Mockery::mock( \KwaWingu\Tours\Api_Client::class ) ) )->check_nonce( $req );
		$this->assertFalse( $result );
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
