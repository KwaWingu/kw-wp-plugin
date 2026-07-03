<?php
/**
 * Operator notifications + lead capture for on-site bookings.
 *
 * @package KwaWingu\Tours
 */

namespace KwaWingu\Tours;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Emails the operator and stores a lead when a guest books on-site. Never emails
 * the guest — guest-facing mail is handled by the KwaWingu backend.
 */
class Notifications {

	/**
	 * Plugin settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Plugin settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Handle a successful on-site booking: capture a lead + notify the operator.
	 * Best-effort — never throws into the REST flow.
	 *
	 * @param array<string,mixed> $payload The create-booking request body.
	 * @param array<string,mixed> $result  The create-booking API response.
	 * @return void
	 */
	public function on_booking_created( array $payload, array $result ): void {
		try {
			$name  = trim( sanitize_text_field( (string) ( $payload['guestFirstName'] ?? '' ) ) . ' ' . sanitize_text_field( (string) ( $payload['guestLastName'] ?? '' ) ) );
			$email = sanitize_email( (string) ( $payload['guestEmail'] ?? '' ) );
			$phone = sanitize_text_field( (string) ( $payload['guestPhone'] ?? '' ) );
			$tour  = sanitize_text_field( (string) ( $payload['tourSlug'] ?? '' ) );
			$ref   = $this->ref_from_result( $result );

			if ( $this->settings->lead_capture_enabled() ) {
				$this->capture_lead( $name, $email, $phone, $tour, $ref );
			}
			if ( $this->settings->notifications_enabled() ) {
				$this->notify_operator( $name, $email, $phone, $tour, $ref );
			}
		} catch ( \Throwable $e ) {
			// Best-effort — swallow.
		}
	}

	/**
	 * Handle a successful website inquiry: capture a lead + notify the operator.
	 * Best-effort — never throws into the REST flow.
	 *
	 * @param array<string,mixed> $payload The inquiry request body.
	 * @return void
	 */
	public function on_inquiry_created( array $payload ): void {
		try {
			$name  = sanitize_text_field( (string) ( $payload['name'] ?? '' ) );
			$email = sanitize_email( (string) ( $payload['email'] ?? '' ) );
			$phone = sanitize_text_field( (string) ( $payload['phone'] ?? '' ) );
			$tour  = sanitize_text_field( (string) ( $payload['tourSlug'] ?? '' ) );

			if ( $this->settings->lead_capture_enabled() ) {
				$this->capture_lead( $name, $email, $phone, $tour, '' );
			}
			if ( $this->settings->notifications_enabled() ) {
				$this->notify_inquiry_operator( $name, $email, $phone, $tour, (string) ( $payload['message'] ?? '' ) );
			}
		} catch ( \Throwable $e ) {
			// Best-effort — swallow.
		}
	}

	/**
	 * Store a kwt_lead post.
	 *
	 * @param string $name  Guest name.
	 * @param string $email Guest email.
	 * @param string $phone Guest phone.
	 * @param string $tour  Tour slug.
	 * @param string $ref   Booking reference.
	 * @return void
	 */
	private function capture_lead( string $name, string $email, string $phone, string $tour, string $ref ): void {
		$post_id = wp_insert_post(
			array(
				'post_type'   => Cpt::LEAD,
				'post_status' => 'publish',
				'post_title'  => '' !== $name ? $name : $email,
			)
		);
		if ( is_int( $post_id ) && $post_id > 0 ) {
			update_post_meta( $post_id, 'kwt_lead_email', $email );
			update_post_meta( $post_id, 'kwt_lead_phone', $phone );
			update_post_meta( $post_id, 'kwt_lead_tour', $tour );
			update_post_meta( $post_id, 'kwt_lead_ref', $ref );
		}
	}

	/**
	 * Email the operator a new-booking notice.
	 *
	 * @param string $name  Guest name.
	 * @param string $email Guest email.
	 * @param string $phone Guest phone.
	 * @param string $tour  Tour slug.
	 * @param string $ref   Booking reference.
	 * @return void
	 */
	private function notify_operator( string $name, string $email, string $phone, string $tour, string $ref ): void {
		$to = $this->settings->notification_recipient();
		if ( '' === $to ) {
			$to = (string) get_option( 'admin_email' );
		}
		if ( '' === $to ) {
			return;
		}
		/* translators: %s: tour slug */
		$subject = sprintf( __( 'New booking via your website — %s', 'kwawingu-tours' ), $tour );
		$body    = wp_strip_all_tags(
			__( 'A guest booked on your website:', 'kwawingu-tours' ) . "\n\n"
			. __( 'Name:', 'kwawingu-tours' ) . ' ' . $name . "\n"
			. __( 'Email:', 'kwawingu-tours' ) . ' ' . $email . "\n"
			. __( 'Phone:', 'kwawingu-tours' ) . ' ' . $phone . "\n"
			. __( 'Tour:', 'kwawingu-tours' ) . ' ' . $tour . "\n"
			. __( 'Reference:', 'kwawingu-tours' ) . ' ' . $ref . "\n"
		);
		wp_mail( $to, $subject, $body );
	}

	/**
	 * Email the operator a new-inquiry notice.
	 *
	 * @param string $name    Enquirer name.
	 * @param string $email   Enquirer email.
	 * @param string $phone   Enquirer phone.
	 * @param string $tour    Tour slug.
	 * @param string $message Enquirer message.
	 * @return void
	 */
	private function notify_inquiry_operator( string $name, string $email, string $phone, string $tour, string $message ): void {
		$to = $this->settings->notification_recipient();
		if ( '' === $to ) {
			$to = (string) get_option( 'admin_email' );
		}
		if ( '' === $to ) {
			return;
		}
		if ( '' !== $tour ) {
			/* translators: %s: tour slug */
			$subject = sprintf( __( 'New inquiry via your website — %s', 'kwawingu-tours' ), $tour );
		} else {
			$subject = __( 'New inquiry via your website', 'kwawingu-tours' );
		}
		$body = wp_strip_all_tags(
			__( 'A visitor submitted an inquiry on your website:', 'kwawingu-tours' ) . "\n\n"
			. __( 'Name:', 'kwawingu-tours' ) . ' ' . $name . "\n"
			. __( 'Email:', 'kwawingu-tours' ) . ' ' . $email . "\n"
			. ( '' !== $phone ? __( 'Phone:', 'kwawingu-tours' ) . ' ' . $phone . "\n" : '' )
			. ( '' !== $tour ? __( 'Tour:', 'kwawingu-tours' ) . ' ' . $tour . "\n" : '' )
			. ( '' !== $message ? "\n" . __( 'Message:', 'kwawingu-tours' ) . "\n" . $message . "\n" : '' )
		);
		wp_mail( $to, $subject, $body );
	}

	/**
	 * Extract a booking ref from the API response (shape varies).
	 *
	 * @param array<string,mixed> $result API response.
	 * @return string
	 */
	private function ref_from_result( array $result ): string {
		$booking = isset( $result['booking'] ) && is_array( $result['booking'] )
			? $result['booking']
			: ( isset( $result['data']['booking'] ) && is_array( $result['data']['booking'] )
				? $result['data']['booking']
				: array()
			);
		$ref     = $booking['ref'] ?? ( $booking['bookingReference'] ?? ( $result['ref'] ?? '' ) );
		return sanitize_text_field( (string) $ref );
	}
}
