<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Admin_Page;
use KwaWingu\Tours\Settings;
use Mockery;
use PHPUnit\Framework\TestCase;

class AdminPageTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    public function test_register_hooks_admin_menu(): void {
        $settings   = new Settings();
        $controller = \Mockery::mock( \KwaWingu\Tours\Sync_Controller::class );
        $page       = new Admin_Page( $settings, $controller );
        $page->register();
        $this->assertNotFalse( has_action( 'admin_menu' ) );
    }
}
