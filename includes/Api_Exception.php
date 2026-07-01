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
 */
class Api_Exception extends \RuntimeException {

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
	 * @param int    $status      HTTP status code.
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
}
