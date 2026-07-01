<?php
namespace KwaWingu\Tours;

/**
 * Registers all server-rendered blocks by scanning blocks/ for block.json dirs.
 */
class Blocks {

    /** Absolute path to the blocks directory (with trailing slash). */
    public static function block_dir(): string {
        return defined( 'KWT_PLUGIN_DIR' ) ? KWT_PLUGIN_DIR . 'blocks/' : __DIR__ . '/../blocks/';
    }

    public function register(): void {
        add_action( 'init', array( $this, 'init' ) );
    }

    public function init(): void {
        $dir = self::block_dir();
        if ( ! is_dir( $dir ) ) {
            return;
        }
        foreach ( (array) glob( $dir . '*', GLOB_ONLYDIR ) as $block_path ) {
            if ( file_exists( $block_path . '/block.json' ) ) {
                register_block_type( $block_path );
            }
        }
    }
}
