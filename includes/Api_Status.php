<?php
/**
 * Turns an API refusal into the right words for the right audience, and remembers
 * the last one so the site owner is told in wp-admin.
 *
 * @package KwaWingu\Tours
 */

namespace KwaWingu\Tours;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Two audiences see an API failure and they need different sentences.
 *
 * The *site owner* needs to know what to do: enable the paid Developer API add-on,
 * fix the key, fix the slug — or nothing, because the API is merely busy and the
 * plugin is already showing the last good copy. A *visitor* must never learn any
 * of that; they get a quiet empty state or a "try again in a moment".
 *
 * The last failure is persisted in one option so wp-admin can show a notice on
 * every screen while owner action is needed, and drop it on the next success.
 */
class Api_Status {

	/** Option holding the last recorded failure (or nothing when the API is healthy). */
	const OPTION = 'kwt_api_status';

	/** Dashboard path where the operator enables the Developer API add-on. */
	const DASHBOARD_PATH = '/dashboard/booking-engine';

	/**
	 * URL of the dashboard page where the operator enables the Developer API add-on.
	 *
	 * @return string
	 */
	public static function dashboard_url(): string {
		return Booking::hosted_base() . self::DASHBOARD_PATH;
	}

	/**
	 * A sentence for the site owner, naming the fix.
	 *
	 * @param Api_Exception $e The refusal.
	 * @return string Plain text (not escaped).
	 */
	public static function owner_message( Api_Exception $e ): string {
		switch ( $e->kind() ) {
			case Api_Exception::KIND_ENTITLEMENT:
				return __( 'This operator\'s KwaWingu plan does not include API access — enable the Developer API add-on in your KwaWingu dashboard (Booking engine → Developer API). Until then your synced tours still show, but live prices, availability, search and on-site booking are paused.', 'kwawingu-tours' );
			case Api_Exception::KIND_AUTH:
				return __( 'KwaWingu rejected the API key. Check that the public API key in Settings → KwaWingu Tours matches the one in your KwaWingu dashboard — keys can be rotated or revoked there.', 'kwawingu-tours' );
			case Api_Exception::KIND_SCOPE:
				return __( 'The configured API key is not allowed to do this. On-site booking needs a private key with the booking scope; check the keys in Settings → KwaWingu Tours.', 'kwawingu-tours' );
			case Api_Exception::KIND_NOT_FOUND:
				return __( 'KwaWingu does not recognise the operator slug in Settings → KwaWingu Tours. It is the last part of your booking page address, e.g. tours.kwawingu.com/your-slug.', 'kwawingu-tours' );
			case Api_Exception::KIND_RATE_LIMITED:
				return __( 'KwaWingu is rate-limiting this site (1000 requests per hour). The last copy of your prices and availability is being shown and the request will be retried automatically — nothing to do.', 'kwawingu-tours' );
			case Api_Exception::KIND_TRANSIENT:
				return sprintf(
					/* translators: %s: the HTTP status or transport error */
					__( 'KwaWingu could not be reached (%s). The last copy of your prices and availability is being shown and the request will be retried automatically.', 'kwawingu-tours' ),
					$e->get_status() > 0 ? 'HTTP ' . $e->get_status() : $e->getMessage()
				);
		}
		return $e->getMessage();
	}

	/**
	 * A sentence safe to put in front of a visitor.
	 *
	 * Never mentions plans, keys or slugs: those are the owner's business, and an
	 * interactive block that says "the operator has not paid" is broken in a worse
	 * way than one that says "unavailable".
	 *
	 * @param Api_Exception $e The refusal.
	 * @return string Plain text (not escaped).
	 */
	public static function visitor_message( Api_Exception $e ): string {
		switch ( $e->kind() ) {
			case Api_Exception::KIND_RATE_LIMITED:
			case Api_Exception::KIND_TRANSIENT:
				return __( 'Live availability is busy right now — please try again in a moment.', 'kwawingu-tours' );
			case Api_Exception::KIND_ENTITLEMENT:
			case Api_Exception::KIND_AUTH:
			case Api_Exception::KIND_SCOPE:
			case Api_Exception::KIND_NOT_FOUND:
				return __( 'Online booking is not available at the moment. Please contact us directly.', 'kwawingu-tours' );
		}
		// Business refusals (price changed, sold out, invalid input) are written by the
		// API for the guest and are safe to relay as-is.
		return $e->getMessage();
	}

	/**
	 * Records a failure so wp-admin can surface it. Only the latest one is kept.
	 *
	 * Retryable failures are recorded too (they explain a stale price on the
	 * settings page) but only owner-actionable ones raise the site-wide notice.
	 *
	 * @param Api_Exception $e The refusal.
	 * @return void
	 */
	public static function record_failure( Api_Exception $e ): void {
		if ( ! function_exists( 'update_option' ) ) {
			return;
		}
		update_option(
			self::OPTION,
			array(
				'kind'    => $e->kind(),
				'code'    => $e->get_code_string(),
				'status'  => $e->get_status(),
				'message' => self::owner_message( $e ),
				'at'      => time(),
			),
			false
		);
	}

	/**
	 * Clears the recorded failure after a successful call.
	 *
	 * Reads before writing so a healthy site does not pay an option write per
	 * page view.
	 *
	 * @return void
	 */
	public static function record_success(): void {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'delete_option' ) ) {
			return;
		}
		if ( false !== get_option( self::OPTION, false ) ) {
			delete_option( self::OPTION );
		}
	}

	/**
	 * The last recorded failure, or null when the API is healthy.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function last_failure(): ?array {
		if ( ! function_exists( 'get_option' ) ) {
			return null;
		}
		$v = get_option( self::OPTION, false );
		return is_array( $v ) && ! empty( $v['message'] ) ? $v : null;
	}

	/**
	 * Whether the last failure is one the owner has to act on.
	 *
	 * @return bool
	 */
	public static function needs_owner_action(): bool {
		$last = self::last_failure();
		if ( null === $last ) {
			return false;
		}
		return in_array(
			(string) ( $last['kind'] ?? '' ),
			array(
				Api_Exception::KIND_ENTITLEMENT,
				Api_Exception::KIND_AUTH,
				Api_Exception::KIND_SCOPE,
				Api_Exception::KIND_NOT_FOUND,
			),
			true
		);
	}

	/**
	 * Hooks the admin notice.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
	}

	/**
	 * Prints the notice for administrators when the API is refusing this site.
	 *
	 * Site-wide for owner-actionable failures (they will not fix themselves);
	 * restricted to the plugin's own settings page for retryable ones, where it
	 * explains why a price may be a minute old.
	 *
	 * @return void
	 */
	public function render_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$last = self::last_failure();
		if ( null === $last ) {
			return;
		}
		$owner_action = self::needs_owner_action();
		if ( ! $owner_action ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check.
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
			if ( 'kwawingu-tours' !== $page ) {
				return;
			}
		}
		$class = $owner_action ? 'notice notice-error' : 'notice notice-warning';
		echo '<div class="' . esc_attr( $class ) . '"><p><strong>' . esc_html__( 'KwaWingu Tours:', 'kwawingu-tours' ) . '</strong> '
			. esc_html( (string) $last['message'] );
		if ( Api_Exception::KIND_ENTITLEMENT === ( $last['kind'] ?? '' ) ) {
			echo ' <a href="' . esc_url( self::dashboard_url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open KwaWingu dashboard', 'kwawingu-tours' ) . '</a>';
		}
		echo '</p></div>';
	}
}
