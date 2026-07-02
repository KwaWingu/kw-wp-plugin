<?php
/**
 * Integration: blocks actually render markup on the front end.
 *
 * This is the layer that catches the "render.php defines a function but never
 * echoes" class of bug (which passed every pure-PHP unit test in v0.2 and v1.7).
 *
 * @package KwaWingu\Tours
 */

/**
 * @group integration
 */
class KWT_BlockRenderTest extends WP_UnitTestCase {

	/**
	 * A synced tour renders inside the Tours Grid block with its title + price.
	 */
	public function test_tours_grid_block_renders_synced_tour() {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'kwt_tour',
				'post_status' => 'publish',
				'post_title'  => 'Serengeti Safari',
			)
		);
		update_post_meta( $post_id, 'kwt_price', 450000 );
		update_post_meta( $post_id, 'kwt_slug', 'serengeti' );
		update_post_meta( $post_id, 'kwt_duration_days', 3 );

		$html = do_blocks( '<!-- wp:kwawingu/tours-grid {"limit":6} /-->' );

		$this->assertNotEmpty( trim( $html ), 'Tours Grid block rendered empty markup — render.php may not echo.' );
		$this->assertStringContainsString( 'kwt-tours-grid', $html );
		$this->assertStringContainsString( 'Serengeti Safari', $html );
		$this->assertStringContainsString( 'TZS 450,000', $html );
	}

	/**
	 * Every registered kwawingu/* block produces a non-fatal render (may be empty
	 * when there's no data, but must not throw or emit a PHP error).
	 */
	public function test_all_blocks_render_without_error() {
		$registry = WP_Block_Type_Registry::get_instance();
		foreach ( $registry->get_all_registered() as $name => $type ) {
			if ( 0 !== strpos( $name, 'kwawingu/' ) ) {
				continue;
			}
			$html = do_blocks( '<!-- wp:' . $name . ' /-->' );
			$this->assertIsString( $html, $name . ' did not return a string' );
		}
	}

	/**
	 * The Gallery block renders nothing when the tour has no gallery.
	 */
	public function test_gallery_block_empty_without_gallery() {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'kwt_tour',
				'post_status' => 'publish',
				'post_title'  => 'No Gallery Tour',
			)
		);
		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );
		$html = do_blocks( '<!-- wp:kwawingu/gallery /-->' );
		wp_reset_postdata();
		$this->assertSame( '', trim( wp_strip_all_tags( $html ) ) );
	}
}
