<?php
namespace KwaWingu\Tours\Tests;

use KwaWingu\Tours\Plugin;
use PHPUnit\Framework\TestCase;

class PluginTest extends TestCase {

    public function test_instance_is_singleton(): void {
        $this->assertSame( Plugin::instance(), Plugin::instance() );
    }

    public function test_version_constant_matches(): void {
        $this->assertSame( '1.1.0', Plugin::VERSION );
    }
}
