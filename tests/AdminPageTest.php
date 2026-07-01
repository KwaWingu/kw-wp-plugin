<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Admin_Page;
use KwaWingu\Tours\Settings;
use PHPUnit\Framework\TestCase;

class AdminPageTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_hooks_admin_menu(): void {
        $page = new Admin_Page( new Settings() );
        $page->register();
        $this->assertNotFalse( has_action( 'admin_menu' ) );
    }
}
