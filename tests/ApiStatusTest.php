<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Api_Exception;
use KwaWingu\Tours\Api_Status;
use PHPUnit\Framework\TestCase;

/**
 * The Developer API is paid per operator, so a 403 is usually "not entitled":
 * the owner must be told the fix and the visitor must be told nothing about it.
 */
class ApiStatusTest extends TestCase {

	/** @var array<string,mixed> */
	private $options = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->options = array();
		$opts          = &$this->options;
		Functions\when( 'get_option' )->alias(
			static function ( $k, $d = false ) use ( &$opts ) {
				return array_key_exists( $k, $opts ) ? $opts[ $k ] : $d;
			}
		);
		Functions\when( 'update_option' )->alias(
			static function ( $k, $v ) use ( &$opts ) {
				$opts[ $k ] = $v;
				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			static function ( $k ) use ( &$opts ) {
				unset( $opts[ $k ] );
				return true;
			}
		);
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/** @return array<string,array{0:Api_Exception,1:string}> */
	public function kinds(): array {
		return array(
			'403 api_access_required'   => array( new Api_Exception( 'Enable API access in your dashboard to use the API.', 403, 'api_access_required' ), Api_Exception::KIND_ENTITLEMENT ),
			'403 with no code'          => array( new Api_Exception( 'Forbidden', 403 ), Api_Exception::KIND_ENTITLEMENT ),
			'401 api_key_invalid'       => array( new Api_Exception( 'Invalid or revoked API key', 401, 'api_key_invalid' ), Api_Exception::KIND_AUTH ),
			'401 api_key_required'      => array( new Api_Exception( 'An API key is required', 401, 'api_key_required' ), Api_Exception::KIND_AUTH ),
			'401 with no code'          => array( new Api_Exception( 'Unauthorized', 401 ), Api_Exception::KIND_AUTH ),
			'403 api_key_scope_missing' => array( new Api_Exception( 'missing scope', 403, 'api_key_scope_missing' ), Api_Exception::KIND_SCOPE ),
			'404 not_found'             => array( new Api_Exception( 'no such operator', 404, 'not_found' ), Api_Exception::KIND_NOT_FOUND ),
			'429 rate_limited'          => array( new Api_Exception( 'slow down', 429, 'rate_limited' ), Api_Exception::KIND_RATE_LIMITED ),
			'502'                       => array( new Api_Exception( 'KwaWingu API returned status 502.', 502 ), Api_Exception::KIND_TRANSIENT ),
			'transport (status 0)'      => array( new Api_Exception( 'Request to KwaWingu API failed: cURL error 28', 0 ), Api_Exception::KIND_TRANSIENT ),
			'409 business refusal'      => array( new Api_Exception( 'Price changed', 409, 'price_changed' ), Api_Exception::KIND_OTHER ),
		);
	}

	/**
	 * @dataProvider kinds
	 */
	public function test_classifies_by_api_code_then_status( Api_Exception $e, string $kind ): void {
		$this->assertSame( $kind, $e->kind() );
	}

	public function test_only_rate_limit_and_transient_are_retryable(): void {
		$this->assertTrue( ( new Api_Exception( 'x', 429, 'rate_limited' ) )->is_retryable() );
		$this->assertTrue( ( new Api_Exception( 'x', 503 ) )->is_retryable() );
		$this->assertTrue( ( new Api_Exception( 'x', 0 ) )->is_retryable() );
		$this->assertFalse( ( new Api_Exception( 'x', 403, 'api_access_required' ) )->is_retryable() );
		$this->assertFalse( ( new Api_Exception( 'x', 401, 'api_key_invalid' ) )->is_retryable() );
	}

	public function test_owner_message_for_entitlement_names_the_paid_add_on(): void {
		$msg = Api_Status::owner_message( new Api_Exception( 'api_access_required', 403, 'api_access_required' ) );
		$this->assertStringContainsString( 'plan does not include API access', $msg );
		$this->assertStringContainsString( 'Developer API', $msg );
	}

	public function test_owner_message_for_401_points_at_the_public_key(): void {
		$msg = Api_Status::owner_message( new Api_Exception( 'Invalid or revoked API key', 401, 'api_key_invalid' ) );
		$this->assertStringContainsString( 'public API key', $msg );
	}

	public function test_owner_message_for_429_and_5xx_says_nothing_to_do(): void {
		$this->assertStringContainsString( 'retried automatically', Api_Status::owner_message( new Api_Exception( 'x', 429, 'rate_limited' ) ) );
		$msg = Api_Status::owner_message( new Api_Exception( 'KwaWingu API returned status 503.', 503 ) );
		$this->assertStringContainsString( 'HTTP 503', $msg );
		$this->assertStringContainsString( 'last copy', $msg );
	}

	public function test_visitor_message_never_mentions_plans_keys_or_status(): void {
		foreach ( $this->kinds() as $label => $pair ) {
			if ( Api_Exception::KIND_OTHER === $pair[1] ) {
				continue;
			}
			$msg = Api_Status::visitor_message( $pair[0] );
			foreach ( array( 'API', 'key', 'plan', '403', '401', '429', 'HTTP', 'slug' ) as $needle ) {
				$this->assertStringNotContainsString( $needle, $msg, "$label leaks '$needle' to the visitor" );
			}
		}
	}

	public function test_visitor_message_relays_business_refusals_verbatim(): void {
		$e = new Api_Exception( 'The price changed while you were booking.', 409, 'price_changed' );
		$this->assertSame( 'The price changed while you were booking.', Api_Status::visitor_message( $e ) );
	}

	public function test_failure_is_recorded_and_cleared_on_success(): void {
		Api_Status::record_failure( new Api_Exception( 'x', 403, 'api_access_required' ) );
		$last = Api_Status::last_failure();
		$this->assertSame( Api_Exception::KIND_ENTITLEMENT, $last['kind'] );
		$this->assertSame( 403, $last['status'] );
		$this->assertTrue( Api_Status::needs_owner_action() );

		Api_Status::record_success();
		$this->assertNull( Api_Status::last_failure() );
		$this->assertFalse( Api_Status::needs_owner_action() );
	}

	public function test_retryable_failure_is_recorded_but_needs_no_owner_action(): void {
		Api_Status::record_failure( new Api_Exception( 'x', 429, 'rate_limited' ) );
		$this->assertNotNull( Api_Status::last_failure() );
		$this->assertFalse( Api_Status::needs_owner_action() );
	}

	public function test_success_does_not_write_when_already_healthy(): void {
		$deleted = 0;
		Functions\when( 'delete_option' )->alias(
			static function () use ( &$deleted ) {
				++$deleted;
				return true;
			}
		);
		Api_Status::record_success();
		$this->assertSame( 0, $deleted );
	}

	public function test_notice_is_site_wide_for_entitlement_and_links_to_dashboard(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Api_Status::record_failure( new Api_Exception( 'x', 403, 'api_access_required' ) );

		ob_start();
		( new Api_Status() )->render_notice();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'notice-error', $html );
		$this->assertStringContainsString( 'plan does not include API access', $html );
		$this->assertStringContainsString( Api_Status::dashboard_url(), $html );
	}

	public function test_retryable_notice_only_shows_on_the_plugin_settings_page(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();
		Api_Status::record_failure( new Api_Exception( 'x', 429, 'rate_limited' ) );

		unset( $_GET['page'] );
		ob_start();
		( new Api_Status() )->render_notice();
		$this->assertSame( '', (string) ob_get_clean(), 'a rate limit must not nag on every admin screen' );

		$_GET['page'] = 'kwawingu-tours';
		ob_start();
		( new Api_Status() )->render_notice();
		$html = (string) ob_get_clean();
		unset( $_GET['page'] );
		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringContainsString( 'rate-limiting', $html );
	}

	public function test_notice_is_hidden_from_non_admins(): void {
		Functions\when( 'current_user_can' )->justReturn( false );
		Api_Status::record_failure( new Api_Exception( 'x', 403, 'api_access_required' ) );
		ob_start();
		( new Api_Status() )->render_notice();
		$this->assertSame( '', (string) ob_get_clean() );
	}
}
