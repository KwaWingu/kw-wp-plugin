<?php
namespace KwaWingu\Tours;

class Api_Exception extends \RuntimeException {

    /** @var string */
    private $code_string;

    public function __construct( string $message, int $status = 0, string $code_string = '' ) {
        parent::__construct( $message, $status );
        $this->code_string = $code_string;
    }

    public function get_code_string(): string {
        return $this->code_string;
    }
}
