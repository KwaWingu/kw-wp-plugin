<?php
/**
 * Global stub: WP_Error_Stub must live in the global namespace so the
 * is_wp_error alias (`$t instanceof \WP_Error_Stub`) resolves correctly.
 * Because PHP forbids mixing bracketed and un-bracketed namespace declarations
 * in a single file, BOTH blocks use the braced form.
 */
namespace {
    class WP_Error_Stub {}
}

namespace KwaWingu\Tours\Tests {

    use Brain\Monkey;
    use Brain\Monkey\Functions;
    use KwaWingu\Tours\Api_Client;
    use KwaWingu\Tours\Api_Exception;
    use KwaWingu\Tours\Settings;
    use PHPUnit\Framework\TestCase;

    class ApiClientTest extends TestCase {

        protected function setUp(): void {
            parent::setUp();
            Monkey\setUp();
            Functions\when( 'get_option' )->justReturn( array( 'slug' => 'acme', 'public_key' => 'kw_live_x' ) );
            Functions\when( 'is_wp_error' )->alias( static function ( $t ) { return $t instanceof \WP_Error_Stub; } );
            Functions\when( 'wp_remote_retrieve_response_code' )->alias( static function ( $r ) { return $r['code']; } );
            Functions\when( 'wp_remote_retrieve_body' )->alias( static function ( $r ) { return $r['body']; } );
            Functions\when( 'add_query_arg' )->alias( static function ( $args, $url ) {
                return $url . '?' . http_build_query( $args );
            } );
            Functions\when( 'esc_url_raw' )->returnArg();
            Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
        }

        protected function tearDown(): void {
            Monkey\tearDown();
            parent::tearDown();
        }

        private function client(): Api_Client {
            return new Api_Client( new Settings() );
        }

        public function test_get_sends_api_key_header_and_returns_decoded_body(): void {
            Functions\expect( 'wp_remote_get' )->once()->andReturnUsing( function ( $url, $args ) {
                $this->assertStringContainsString( '/acme/tours', $url );
                $this->assertSame( 'kw_live_x', $args['headers']['X-API-Key'] );
                return array( 'code' => 200, 'body' => wp_json_encode_stub( array( 'data' => array( 1, 2 ) ) ) );
            } );

            $body = $this->client()->get( '/tours', array( 'page' => 0 ) );
            $this->assertSame( array( 1, 2 ), $body['data'] );
        }

        public function test_get_throws_with_error_code_on_403(): void {
            Functions\when( 'wp_remote_get' )->justReturn(
                array( 'code' => 403, 'body' => '{"error":{"code":"api_access_required","message":"Enable API access."}}' )
            );

            try {
                $this->client()->get( '/tours' );
                $this->fail( 'Expected Api_Exception' );
            } catch ( Api_Exception $e ) {
                $this->assertSame( 'api_access_required', $e->get_code_string() );
                $this->assertSame( 403, $e->getCode() );
            }
        }

        public function test_get_throws_on_transport_error(): void {
            Functions\when( 'wp_remote_get' )->justReturn( new \WP_Error_Stub() );
            $this->expectException( Api_Exception::class );
            $this->client()->get( '/tours' );
        }

        public function test_post_sends_private_key_and_body(): void {
            Functions\when( 'get_option' )->justReturn( array( 'slug' => 'acme', 'public_key' => 'kw_pub', 'private_key' => 'kw_priv' ) );
            Functions\expect( 'wp_remote_post' )->once()->andReturnUsing( function ( $url, $args ) {
                $this->assertStringContainsString( '/acme/bookings', $url );
                $this->assertSame( 'kw_priv', $args['headers']['X-API-Key'] );
                $this->assertSame( 'application/json', $args['headers']['Content-Type'] );
                $this->assertSame( '{"ref":"KWG-1"}', $args['body'] );
                return array( 'code' => 200, 'body' => '{"data":{"ok":true}}' );
            } );
            $out = $this->client()->post( '/bookings', array( 'ref' => 'KWG-1' ) );
            $this->assertTrue( $out['data']['ok'] );
        }

        public function test_post_uses_public_key_when_requested(): void {
            Functions\when( 'get_option' )->justReturn( array( 'slug' => 'acme', 'public_key' => 'kw_pub', 'private_key' => 'kw_priv' ) );
            Functions\expect( 'wp_remote_post' )->once()->andReturnUsing( function ( $url, $args ) {
                $this->assertSame( 'kw_pub', $args['headers']['X-API-Key'] );
                return array( 'code' => 200, 'body' => '{"ok":1}' );
            } );
            $this->client()->post( '/calculator/estimate', array(), false );
        }
    }

    // Minimal stub used above.
    if ( ! function_exists( __NAMESPACE__ . '\\wp_json_encode_stub' ) ) {
        function wp_json_encode_stub( $v ) { return json_encode( $v ); }
    }

} // end namespace KwaWingu\Tours\Tests
