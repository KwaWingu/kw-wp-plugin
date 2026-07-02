<?php
/**
 * Integration: CPT registration + rewrite, and REST proxy route registration,
 * against a real WordPress.
 *
 * @package KwaWingu\Tours
 */

/**
 * @group integration
 */
class KWT_CptAndRestTest extends WP_UnitTestCase {

	/**
	 * The tour CPT is registered, public, and has an archive.
	 */
	public function test_tour_cpt_registered() {
		$pt = get_post_type_object( 'kwt_tour' );
		$this->assertNotNull( $pt, 'kwt_tour not registered' );
		$this->assertTrue( (bool) $pt->public );
		$this->assertTrue( (bool) $pt->has_archive );

		$dest = get_post_type_object( 'kwt_destination' );
		$this->assertNotNull( $dest );

		$lead = get_post_type_object( 'kwt_lead' );
		$this->assertNotNull( $lead, 'kwt_lead not registered' );
		$this->assertFalse( (bool) $lead->public, 'kwt_lead should be private' );
	}

	/**
	 * The proxy REST routes are registered under kwawingu/v1.
	 */
	public function test_proxy_routes_registered() {
		do_action( 'rest_api_init' );
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/kwawingu/v1/search', $routes );
		$this->assertArrayHasKey( '/kwawingu/v1/departures', $routes );
		$this->assertArrayHasKey( '/kwawingu/v1/quote', $routes );
		$this->assertArrayHasKey( '/kwawingu/v1/bookings', $routes );
		$this->assertArrayHasKey( '/kwawingu/v1/payment-intent', $routes );
	}

	/**
	 * A proxy route rejects requests without a valid REST nonce.
	 */
	public function test_proxy_route_requires_nonce() {
		do_action( 'rest_api_init' );
		$request  = new WP_REST_Request( 'GET', '/kwawingu/v1/search' );
		$response = rest_get_server()->dispatch( $request );
		// 401/403 (nonce fail) — NOT 200. (No X-WP-Nonce header set.)
		$this->assertContains( (int) $response->get_status(), array( 401, 403 ) );
	}
}
