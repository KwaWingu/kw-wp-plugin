<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\View;
use PHPUnit\Framework\TestCase;

class ViewTest extends TestCase {
    protected function setUp(): void { parent::setUp(); Monkey\setUp(); }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_money_formats_tzs_with_thousands(): void {
        $this->assertSame( 'TZS 450,000', View::money( 450000 ) );
        $this->assertSame( 'TZS 0', View::money( 0 ) );
        $this->assertSame( 'TZS 1,250', View::money( 1250 ) );
    }
}
