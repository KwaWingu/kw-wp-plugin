<?php
namespace KwaWingu\Tours\Tests;

use KwaWingu\Tours\Plugin;
use PHPUnit\Framework\TestCase;

class PluginTest extends TestCase {

    public function test_instance_is_singleton(): void {
        $this->assertSame( Plugin::instance(), Plugin::instance() );
    }

    /**
     * The constant drifted from the plugin header for three releases because it was
     * asserted against a hard-coded string. Assert it against the header instead.
     */
    public function test_version_constant_matches_plugin_header(): void {
        $main = file_get_contents( dirname( __DIR__ ) . '/kwawingu-tours.php' );
        $this->assertSame( 1, preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $main, $header ) );
        $this->assertSame( 1, preg_match( "/define\(\s*'KWT_VERSION',\s*'([^']+)'/", $main, $constant ) );

        $this->assertSame( $header[1], Plugin::VERSION );
        $this->assertSame( $header[1], $constant[1] );
    }
}
