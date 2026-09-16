<?php
/**
 * Operator branding loader and CSS variable injector.
 *
 * @package KwaWingu\Tours
 */

namespace KwaWingu\Tours;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Pulls operator branding from GET /profile and applies it to the site.
 */
class Branding {

	const OPTION = 'kwawingu_tours_brand';

	/**
	 * API client instance.
	 *
	 * @var Api_Client
	 */
	private $api;

	/**
	 * Constructor.
	 *
	 * @param Api_Client $api API client instance.
	 */
	public function __construct( Api_Client $api ) {
		$this->api = $api;
	}

	/**
	 * Attach the brand CSS custom properties through the enqueue API.
	 *
	 * Runs after Assets (default priority 10) has registered the block stylesheet,
	 * so the variables ride along as an inline addition to that handle instead of
	 * a hand-printed <style> tag in wp_head.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
	}

	/**
	 * Add the brand CSS variables as an inline style on the block stylesheet handle.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		$css = $this->css_vars();
		if ( '' === $css ) {
			return;
		}
		wp_add_inline_style( Assets::STYLE_HANDLE, $css );
	}

	/**
	 * Fetch branding from the API and persist it to a WP option.
	 *
	 * @return array<string,string> Brand data keyed by field name.
	 */
	public function apply(): array {
		try {
			$profile = $this->api->get( '/profile' );
		} catch ( Api_Exception $e ) {
			return array();
		}
		$brand = array(
			'name'        => sanitize_text_field( (string) ( $profile['name'] ?? '' ) ),
			'logo'        => esc_url_raw( (string) ( $profile['logoUrl'] ?? '' ) ),
			'primary'     => (string) sanitize_hex_color( (string) ( $profile['brandPrimary'] ?? '' ) ),
			'accent'      => (string) sanitize_hex_color( (string) ( $profile['brandAccent'] ?? '' ) ),
			'description' => sanitize_text_field( (string) ( $profile['description'] ?? '' ) ),
		);
		update_option( self::OPTION, $brand );
		return $brand;
	}

	/**
	 * Build the CSS custom-property declarations for the stored brand colours.
	 *
	 * @return string Raw CSS (no <style> wrapper — it is attached via wp_add_inline_style),
	 *                or empty string if no colours are stored.
	 */
	public function css_vars(): string {
		$brand   = get_option( self::OPTION, array() );
		$primary = is_array( $brand ) ? (string) ( $brand['primary'] ?? '' ) : '';
		$accent  = is_array( $brand ) ? (string) ( $brand['accent'] ?? '' ) : '';
		if ( '' === $primary && '' === $accent ) {
			return '';
		}
		$css = ':root{';
		if ( '' !== $primary ) {
			$css .= '--kwt-primary:' . $primary . ';';
		}
		if ( '' !== $accent ) {
			$css .= '--kwt-accent:' . $accent . ';';
		}
		$css .= '}';
		return $css;
	}
}
