<?php
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Define the minimal constants the classes reference at load time.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', sys_get_temp_dir() . '/' );
}
if ( ! defined( 'KWAWINGU_TOURS_API_BASE' ) ) {
    define( 'KWAWINGU_TOURS_API_BASE', 'https://tours.kwawingu.com/api/v1' );
}
if ( ! defined( 'KWAWINGU_TOURS_VERSION' ) ) {
    define( 'KWAWINGU_TOURS_VERSION', '0.4.0' );
}
if ( ! defined( 'KWAWINGU_TOURS_PLUGIN_FILE' ) ) {
    define( 'KWAWINGU_TOURS_PLUGIN_FILE', dirname( __DIR__ ) . '/kwawingu-tours.php' );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
    define( 'MINUTE_IN_SECONDS', 60 );
}
