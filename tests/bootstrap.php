<?php
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Define the minimal constants the classes reference at load time.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', sys_get_temp_dir() . '/' );
}
if ( ! defined( 'KWT_API_BASE' ) ) {
    define( 'KWT_API_BASE', 'https://tours.kwawingu.com/api/v1' );
}
