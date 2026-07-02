<?php
namespace KwaWingu\Tours\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class GalleryRenderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		require_once dirname( __DIR__, 2 ) . '/blocks/gallery/render-fn.php';
		foreach ( array( 'esc_url', 'esc_attr', 'esc_html__' ) as $f ) {
			Functions\when( $f )->returnArg();
		}
		Functions\when( 'get_the_ID' )->justReturn( 7 );
	}
	protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

	public function test_renders_from_attachment_ids(): void {
		Functions\when( 'get_post_meta' )->alias( static function ( $id, $key, $single ) {
			return 'kwt_gallery_ids' === $key ? array( 11, 12 ) : '';
		} );
		Functions\when( 'wp_get_attachment_image_url' )->alias( static function ( $aid ) {
			return 'https://img/' . $aid . '.jpg';
		} );
		$html = kwt_render_gallery( array( 'columns' => 4 ), '' );
		$this->assertStringContainsString( 'kwt-gallery', $html );
		$this->assertStringContainsString( 'https://img/11.jpg', $html );
		$this->assertStringContainsString( '--kwt-cols:4', $html );
	}

	public function test_falls_back_to_urls_and_empty(): void {
		Functions\when( 'get_post_meta' )->alias( static function ( $id, $key, $single ) {
			return 'kwt_gallery' === $key ? array( 'https://img/x.jpg' ) : '';
		} );
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( false );
		$html = kwt_render_gallery( array(), '' );
		$this->assertStringContainsString( 'https://img/x.jpg', $html );

		Functions\when( 'get_post_meta' )->justReturn( '' );
		$this->assertSame( '', kwt_render_gallery( array(), '' ) );
	}
}
