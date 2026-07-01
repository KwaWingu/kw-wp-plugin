<?php
namespace KwaWingu\Tours\Tests {
    use Brain\Monkey;
    use Brain\Monkey\Functions;
    use KwaWingu\Tours\Branding;
    use KwaWingu\Tours\Importer;
    use KwaWingu\Tours\Settings;
    use KwaWingu\Tours\Setup_Wizard;
    use KwaWingu\Tours\Sync;
    use Mockery;
    use PHPUnit\Framework\TestCase;

    class SetupWizardTest extends TestCase {
        protected function setUp(): void { parent::setUp(); Monkey\setUp(); }
        protected function tearDown(): void { Monkey\tearDown(); Mockery::close(); parent::tearDown(); }

        private function wizard( $branding, $importer, $sync ): Setup_Wizard {
            return new Setup_Wizard( new Settings(), $branding, $importer, $sync );
        }

        public function test_register_hooks_menu_and_admin_post(): void {
            $w = $this->wizard(
                Mockery::mock( Branding::class ), Mockery::mock( Importer::class ), Mockery::mock( Sync::class )
            );
            $w->register();
            $this->assertNotFalse( has_action( 'admin_menu' ) );
            $this->assertNotFalse( has_action( 'admin_post_kwt_setup_scaffold' ) );
        }

        public function test_scaffold_runs_branding_importer_sync(): void {
            Functions\when( 'current_user_can' )->justReturn( true );
            Functions\when( 'check_admin_referer' )->justReturn( true );
            Functions\when( 'wp_safe_redirect' )->justReturn( true );
            Functions\when( 'admin_url' )->returnArg();
            Functions\when( 'add_query_arg' )->justReturn( 'x' );

            $branding = Mockery::mock( Branding::class );
            $branding->shouldReceive( 'apply' )->once()->andReturn( array( 'name' => 'X' ) );
            $importer = Mockery::mock( Importer::class );
            $importer->shouldReceive( 'run' )->once()->andReturn( array( 'created' => array( 1 ), 'front' => 1 ) );
            $sync = Mockery::mock( Sync::class );
            $sync->shouldReceive( 'run' )->once()->andReturn( array( 'created' => 2, 'updated' => 0, 'unpublished' => 0, 'errors' => array() ) );

            // wp_safe_redirect + exit: intercept exit via a thrown marker.
            $this->expectException( \KwaWingu\Tours\Tests\WizardExit::class );
            $w = new class( new Settings(), $branding, $importer, $sync ) extends Setup_Wizard {
                protected function terminate(): void { throw new \KwaWingu\Tours\Tests\WizardExit(); }
            };
            $w->handle_scaffold();
        }
    }

    class WizardExit extends \RuntimeException {}
}
