<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Shortcodes;
use PHPUnit\Framework\TestCase;

/**
 * A block that is registered without a block.json or an editor script is
 * invisible in the inserter and fails silently. This pins, for every shortcode,
 * the block behind it and the three files WordPress needs to show it.
 *
 * It runs without WordPress: it reads the files the way register_block_type()
 * would, so it also catches a build/ bundle that was never committed.
 */
class BlockManifestTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function root(): string {
		return dirname( __DIR__ );
	}

	/** @return array<int,array{0:string,1:string}> */
	public function shortcode_block_pairs(): array {
		$out = array();
		foreach ( Shortcodes::BLOCKS as $shortcode => $block ) {
			$out[ $shortcode ] = array( $shortcode, $block );
		}
		return $out;
	}

	public function test_every_registered_shortcode_is_in_the_manifest_and_vice_versa(): void {
		$registered = array();
		Functions\when( 'add_shortcode' )->alias(
			static function ( $tag ) use ( &$registered ) {
				$registered[] = $tag;
			}
		);
		( new Shortcodes() )->register();
		sort( $registered );
		$manifest = array_keys( Shortcodes::BLOCKS );
		sort( $manifest );
		$this->assertSame( $manifest, $registered, 'Shortcodes::BLOCKS must list exactly the shortcodes register() adds.' );
	}

	public function test_every_block_directory_is_reachable_from_a_shortcode(): void {
		$dirs = array_map( 'basename', (array) glob( $this->root() . '/blocks/*', GLOB_ONLYDIR ) );
		sort( $dirs );
		$mapped = array_values( Shortcodes::BLOCKS );
		sort( $mapped );
		$this->assertSame( $mapped, $dirs, 'Every blocks/<name> directory must have a shortcode bridge, and every mapped block must exist.' );
	}

	/**
	 * @dataProvider shortcode_block_pairs
	 */
	public function test_block_has_a_complete_manifest( string $shortcode, string $block ): void {
		$dir  = $this->root() . '/blocks/' . $block;
		$file = $dir . '/block.json';
		$this->assertFileExists( $file, "[$shortcode] has no block.json" );

		$json = json_decode( (string) file_get_contents( $file ), true );
		$this->assertIsArray( $json, "[$shortcode] block.json is not valid JSON" );

		$this->assertSame( 3, $json['apiVersion'] ?? null, "[$shortcode] block.json must use apiVersion 3" );
		$this->assertSame( 'kwawingu/' . $block, $json['name'] ?? null, "[$shortcode] block name must match its directory" );
		$this->assertNotEmpty( $json['title'] ?? '', "[$shortcode] block has no title" );
		$this->assertSame( 'kwawingu-tours', $json['textdomain'] ?? null, "[$shortcode] block must use the plugin text domain" );
		$this->assertIsArray( $json['attributes'] ?? null, "[$shortcode] block must declare attributes (empty object is fine)" );

		// The editor script is what puts the block in the inserter.
		$editor = (string) ( $json['editorScript'] ?? '' );
		$this->assertStringStartsWith( 'file:', $editor, "[$shortcode] block.json has no editorScript" );
		$bundle = $dir . '/' . substr( $editor, 5 );
		$this->assertFileExists( $bundle, "[$shortcode] editor bundle is not committed: $editor" );
		$this->assertFileExists( dirname( $bundle ) . '/index.asset.php', "[$shortcode] editor bundle has no index.asset.php (dependencies/version)" );
		$this->assertStringContainsString( 'registerBlockType', (string) file_get_contents( $bundle ), "[$shortcode] editor bundle does not register the block" );

		// Server render, so the block and the shortcode share one renderer.
		$this->assertSame( 'file:./render.php', $json['render'] ?? null, "[$shortcode] block must render server-side via render.php" );
		$this->assertFileExists( $dir . '/render.php' );
		$this->assertFileExists( $dir . '/render-fn.php', "[$shortcode] block has no render-fn.php for the shortcode bridge" );

		$view = (string) ( $json['viewScript'] ?? '' );
		if ( '' !== $view ) {
			$this->assertFileExists( $dir . '/' . substr( $view, 5 ), "[$shortcode] viewScript points at a missing file" );
		}
	}
}
