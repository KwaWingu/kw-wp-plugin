<?php
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Define the minimal constants the classes reference at load time.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', sys_get_temp_dir() . '/' );
}
if ( ! defined( 'KWT_API_BASE' ) ) {
    define( 'KWT_API_BASE', 'https://tours.kwawingu.com/api/v1' );
}
if ( ! defined( 'KWT_VERSION' ) ) {
    define( 'KWT_VERSION', '0.4.0' );
}
if ( ! defined( 'KWT_PLUGIN_FILE' ) ) {
    define( 'KWT_PLUGIN_FILE', dirname( __DIR__ ) . '/kwawingu-tours.php' );
}
