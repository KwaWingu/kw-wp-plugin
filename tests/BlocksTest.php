<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use KwaWingu\Tours\Blocks;
use PHPUnit\Framework\TestCase;

class BlocksTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        \Brain\Monkey\Functions\when( 'register_block_type' )->justReturn( null );
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_register_hooks_init(): void {
        ( new Blocks() )->register();
        $this->assertNotFalse( has_action( 'init' ) );
    }

    public function test_init_with_no_block_dirs_does_not_error(): void {
        // BLOCK_DIR points at a real, possibly-empty directory; init must be safe.
        ( new Blocks() )->init();
        $this->assertTrue( true );
    }
}
