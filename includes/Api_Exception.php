<?php
/**
 * Exception thrown when the KwaWingu Tours API returns an error response.
 *
 * @package KwaWingu\Tours
 */

namespace KwaWingu\Tours;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Exception thrown when the KwaWingu Tours API returns an error response.
 *
 * Carries the HTTP status (as the exception code) and the API's machine-readable
 * error code, and classifies the failure into the handful of kinds the plugin
 * treats differently. The Developer API is a paid, per-operator add-on, so a 403
 * is usually "not entitled" — a state only the site owner can fix — and must not
 * be shown to visitors as a bare status number.
 */
class Api_Exception extends \RuntimeException {

	/** The operator's plan does not include API access, or it has lapsed (403 api_access_required). */
	const KIND_ENTITLEMENT = 'entitlement';

	/** The key is missing, invalid, revoked or belongs to another operator (401). */
	const KIND_AUTH = 'auth';

	/** The key is valid but lacks the scope the endpoint needs (403 api_key_scope_missing). */
	const KIND_SCOPE = 'scope';

	/** The operator slug or the resource does not exist (404). */
	const KIND_NOT_FOUND = 'not_found';

	/** Too many requests in the window (429 rate_limited). Retrying later will succeed. */
	const KIND_RATE_LIMITED = 'rate_limited';

	/** Upstream 5xx or a transport failure. The last good copy is the best answer. */
	const KIND_TRANSIENT = 'transient';

	/** Anything else (4xx business refusals such as price_changed, invalid input). */
	const KIND_OTHER = 'other';

	/**
	 * Machine-readable error code string returned by the API.
	 *
	 * @var string
	 */
	private $code_string;

	/**
	 * Constructor.
	 *
	 * @param string $message     Human-readable error message.
	 * @param int    $status      HTTP status code (0 for a transport failure).
	 * @param string $code_string Machine-readable error code string.
	 */
	public function __construct( string $message, int $status = 0, string $code_string = '' ) {
		parent::__construct( $message, $status );
		$this->code_string = $code_string;
	}

	/**
	 * Returns the machine-readable error code string from the API response.
	 *
	 * @return string
	 */
	public function get_code_string(): string {
		return $this->code_string;
	}

	/**
	 * HTTP status the API answered with, or 0 when the request never completed.
	 *
	 * @return int
	 */
	public function get_status(): int {
		return (int) $this->getCode();
	}

	/**
	 * Classifies the failure into one of the KIND_* constants.
	 *
	 * The API code is authoritative where it is present (it is what the contract
	 * says to switch on); the status is the fallback for bodies that carry none.
	 *
	 * @return string One of the KIND_* constants.
	 */
	public function kind(): string {
		$status = $this->get_status();
		switch ( $this->code_string ) {
			case 'api_access_required':
				return self::KIND_ENTITLEMENT;
			case 'api_key_required':
			case 'api_key_invalid':
				return self::KIND_AUTH;
			case 'api_key_scope_missing':
			case 'secret_key_in_browser':
			case 'origin_not_allowed':
				return self::KIND_SCOPE;
			case 'rate_limited':
				return self::KIND_RATE_LIMITED;
			case 'not_found':
				return self::KIND_NOT_FOUND;
		}
		if ( 401 === $status ) {
			return self::KIND_AUTH;
		}
		if ( 403 === $status ) {
			// A 403 with no code is, on this API, the entitlement gate.
			return self::KIND_ENTITLEMENT;
		}
		if ( 404 === $status ) {
			return self::KIND_NOT_FOUND;
		}
		if ( 429 === $status ) {
			return self::KIND_RATE_LIMITED;
		}
		if ( 0 === $status || $status >= 500 ) {
			return self::KIND_TRANSIENT;
		}
		return self::KIND_OTHER;
	}

	/**
	 * Whether a later retry can be expected to succeed without the owner doing anything.
	 *
	 * @return bool
	 */
	public function is_retryable(): bool {
		$kind = $this->kind();
		return self::KIND_RATE_LIMITED === $kind || self::KIND_TRANSIENT === $kind;
	}

	/**
	 * Whether the failure is one only the site owner can fix (plan, key, slug).
	 *
	 * @return bool
	 */
	public function needs_owner_action(): bool {
		$kind = $this->kind();
		return self::KIND_ENTITLEMENT === $kind
			|| self::KIND_AUTH === $kind
			|| self::KIND_SCOPE === $kind
			|| self::KIND_NOT_FOUND === $kind;
	}
}
