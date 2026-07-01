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

	const OPTION = 'kwt_brand';

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
	 * Hook into wp_head to output CSS custom properties.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			'wp_head',
			function () {
				echo $this->css_vars(); // phpcs:ignore WordPress.Security.EscapeOutput -- css_vars() builds an escaped <style> block.
			}
		);
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
	 * Build a <style> block containing CSS custom properties for the stored brand colours.
	 *
	 * @return string Escaped <style> element, or empty string if no colours are stored.
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
		return '<style id="kwt-brand">' . $css . '</style>';
	}
}
