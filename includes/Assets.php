<?php
/**
 * Registers the shared proxy JS and config object for interactive blocks.
 *
 * @package KwaWingu\Tours
 */

namespace KwaWingu\Tours;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Registers the shared proxy JS + config for interactive blocks.
 */
class Assets {

	const HANDLE = 'kwt-proxy';

	/**
	 * Registers the wp_enqueue_scripts action hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Registers and localizes the proxy script for front-end blocks.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		wp_register_script(
			self::HANDLE,
			plugins_url( 'assets/js/kwt-proxy.js', KWT_PLUGIN_FILE ),
			array(),
			KWT_VERSION,
			true
		);
		$settings = new Settings();
		wp_localize_script(
			self::HANDLE,
			'kwtProxy',
			array(
				'root'  => rest_url( Rest_Proxy::NS ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'slug'  => $settings->get_slug(),
				'i18n'  => array(
					'loading'         => __( 'Loading…', 'kwawingu-tours' ),
					'error'           => __( 'Something went wrong. Please try again.', 'kwawingu-tours' ),
					'noResults'       => __( 'No results.', 'kwawingu-tours' ),
					'checkPhone'      => __( 'Check your phone to approve the payment.', 'kwawingu-tours' ),
					'paymentReceived' => __( 'Payment received — you are booked!', 'kwawingu-tours' ),
				),
			)
		);
	}
}
