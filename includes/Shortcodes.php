<?php
/**
 * Classic-theme shortcode bridges that reuse the block render callbacks.
 *
 * @package KwaWingu\Tours
 */

namespace KwaWingu\Tours;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Classic-theme shortcode bridges that reuse the block render callbacks.
 */
class Shortcodes {

	/**
	 * Every shortcode and the block directory (under blocks/) whose render function
	 * it bridges to. This is the contract BlockManifestTest enforces: each entry
	 * must have a block.json, a server render and a committed editor bundle, so a
	 * shortcode can never exist without its block appearing in the inserter.
	 *
	 * @var array<string,string>
	 */
	const BLOCKS = array(
		'kwawingu_tours'        => 'tours-grid',
		'kwawingu_tour'         => 'tour-detail',
		'kwawingu_booking'      => 'book-button',
		'kwawingu_featured'     => 'featured-tours',
		'kwawingu_reviews'      => 'reviews',
		'kwawingu_destinations' => 'destinations-grid',
		'kwawingu_search'       => 'search',
		'kwawingu_calculator'   => 'calculator',
		'kwawingu_booking_form' => 'booking',
		'kwawingu_gallery'      => 'gallery',
		'kwawingu_availability' => 'availability-calendar',
		'kwawingu_inquiry'      => 'inquiry-form',
	);

	/**
	 * Shortcodes whose markup is only a shell that the block's `viewScript` brings
	 * to life. WordPress enqueues a viewScript when the *block* renders, never for
	 * a shortcode — so on a classic theme these five rendered dead forms: no search
	 * results, no estimate, no departures, no calendar, no inquiry submission.
	 *
	 * @var array<int,string>
	 */
	const INTERACTIVE = array(
		'kwawingu_search',
		'kwawingu_calculator',
		'kwawingu_booking_form',
		'kwawingu_availability',
		'kwawingu_inquiry',
	);

	/**
	 * Registers all shortcodes with WordPress.
	 */
	public function register(): void {
		add_shortcode( 'kwawingu_tours', array( $this, 'render_tours' ) );
		add_shortcode( 'kwawingu_tour', array( $this, 'render_tour' ) );
		add_shortcode( 'kwawingu_booking', array( $this, 'render_booking' ) );
		add_shortcode( 'kwawingu_featured', array( $this, 'render_featured' ) );
		add_shortcode( 'kwawingu_reviews', array( $this, 'render_reviews' ) );
		add_shortcode( 'kwawingu_destinations', array( $this, 'render_destinations' ) );
		add_shortcode( 'kwawingu_search', array( $this, 'render_search' ) );
		add_shortcode( 'kwawingu_calculator', array( $this, 'render_calculator' ) );
		add_shortcode( 'kwawingu_booking_form', array( $this, 'render_booking_form' ) );
		add_shortcode( 'kwawingu_gallery', array( $this, 'render_gallery' ) );
		add_shortcode( 'kwawingu_availability', array( $this, 'render_availability' ) );
		add_shortcode( 'kwawingu_inquiry', array( $this, 'render_inquiry' ) );
	}

	/**
	 * Enqueues the view script the block registers for this shortcode's block.
	 *
	 * @param string $shortcode Shortcode tag (a key of self::BLOCKS).
	 * @return void
	 */
	private function enqueue_view_script( string $shortcode ): void {
		if ( ! function_exists( 'wp_enqueue_script' ) || ! function_exists( 'generate_block_asset_handle' ) ) {
			return;
		}
		$block = self::BLOCKS[ $shortcode ] ?? '';
		if ( '' === $block ) {
			return;
		}
		wp_enqueue_script( generate_block_asset_handle( 'kwawingu/' . $block, 'viewScript' ) );
	}

	/**
	 * Renders the tours grid shortcode.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_tours( $atts ): string {
		require_once Blocks::block_dir() . 'tours-grid/render-fn.php';
		$atts = shortcode_atts(
			array(
				'limit' => 12,
				'type'  => '',
			),
			$atts
		);
		return kwawingu_tours_render_tours_grid(
			array(
				'limit' => (int) $atts['limit'],
				'type'  => (string) $atts['type'],
			),
			''
		);
	}

	/**
	 * Renders the single tour detail shortcode.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_tour( $atts ): string {
		require_once Blocks::block_dir() . 'tour-detail/render-fn.php';
		$atts = shortcode_atts( array( 'id' => 0 ), $atts );
		return kwawingu_tours_render_tour_detail( array( 'postId' => (int) $atts['id'] ), '' );
	}

	/**
	 * Renders the book button shortcode.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_booking( $atts ): string {
		require_once Blocks::block_dir() . 'book-button/render-fn.php';
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'label' => '',
			),
			$atts
		);
		return kwawingu_tours_render_book_button(
			array(
				'postId' => (int) $atts['id'],
				'label'  => (string) $atts['label'],
			),
			''
		);
	}

	/**
	 * Renders the featured tours shortcode.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_featured( $atts ): string {
		require_once Blocks::block_dir() . 'featured-tours/render-fn.php';
		$atts = shortcode_atts(
			array(
				'heading' => '',
				'limit'   => 3,
			),
			$atts
		);
		return kwawingu_tours_render_featured_tours(
			array(
				'heading' => (string) $atts['heading'],
				'limit'   => (int) $atts['limit'],
			),
			''
		);
	}

	/**
	 * Renders the reviews shortcode.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_reviews( $atts ): string {
		require_once Blocks::block_dir() . 'reviews/render-fn.php';
		$atts = shortcode_atts( array( 'id' => 0 ), $atts );
		return kwawingu_tours_render_reviews( array( 'postId' => (int) $atts['id'] ), '' );
	}

	/**
	 * Renders the destinations grid shortcode.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_destinations( $atts ): string {
		require_once Blocks::block_dir() . 'destinations-grid/render-fn.php';
		$atts = shortcode_atts( array( 'limit' => 12 ), $atts );
		return kwawingu_tours_render_destinations_grid( array( 'limit' => (int) $atts['limit'] ), '' );
	}

	/**
	 * Renders the search shortcode.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_search( $atts ): string {
		$this->enqueue_view_script( 'kwawingu_search' );
		require_once Blocks::block_dir() . 'search/render-fn.php';
		return kwawingu_tours_render_search( array(), '' );
	}

	/**
	 * Renders the calculator shortcode.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_calculator( $atts ): string {
		$this->enqueue_view_script( 'kwawingu_calculator' );
		require_once Blocks::block_dir() . 'calculator/render-fn.php';
		return kwawingu_tours_render_calculator( array(), '' );
	}

	/**
	 * Renders the booking form shortcode.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_booking_form( $atts ): string {
		$this->enqueue_view_script( 'kwawingu_booking_form' );
		require_once Blocks::block_dir() . 'booking/render-fn.php';
		// `id`/`slug` were documented but never read: the form always bound to the
		// current post, which on any page other than a tour is a form with no tour.
		return kwawingu_tours_render_booking_form( array( 'tourSlug' => $this->tour_slug_att( $atts ) ), '' );
	}

	/**
	 * Resolves a shortcode's `slug` / `id` attribute to a KwaWingu tour slug.
	 *
	 * @param array<string,mixed> $atts Raw shortcode attributes.
	 * @return string Tour slug, or '' to let the renderer use the current post.
	 */
	private function tour_slug_att( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'   => 0,
				'slug' => '',
			),
			$atts
		);
		if ( '' !== (string) $atts['slug'] ) {
			return (string) $atts['slug'];
		}
		$id = (int) $atts['id'];
		if ( $id > 0 && function_exists( 'get_post_meta' ) ) {
			return (string) get_post_meta( $id, 'kwt_slug', true );
		}
		return '';
	}

	/**
	 * [kwawingu_gallery] — a tour's photo gallery.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_gallery( $atts ): string {
		require_once Blocks::block_dir() . 'gallery/render-fn.php';
		$atts = shortcode_atts(
			array(
				'id'      => 0,
				'columns' => 3,
			),
			$atts
		);
		return kwawingu_tours_render_gallery(
			array(
				'postId'  => (int) $atts['id'],
				'columns' => (int) $atts['columns'],
			),
			''
		);
	}

	/**
	 * [kwawingu_availability] — a tour's departures calendar.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_availability( $atts ): string {
		$this->enqueue_view_script( 'kwawingu_availability' );
		require_once Blocks::block_dir() . 'availability-calendar/render-fn.php';
		return kwawingu_tours_render_availability_calendar( array( 'tourSlug' => $this->tour_slug_att( $atts ) ), '' );
	}

	/**
	 * Renders the inquiry form shortcode.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_inquiry( $atts ): string {
		$this->enqueue_view_script( 'kwawingu_inquiry' );
		require_once Blocks::block_dir() . 'inquiry-form/render-fn.php';
		$atts = shortcode_atts(
			array(
				'heading'   => '',
				'tour_slug' => '',
			),
			$atts
		);
		return kwawingu_tours_render_inquiry_form(
			array(
				'heading'  => (string) $atts['heading'],
				'tourSlug' => (string) $atts['tour_slug'],
			),
			''
		);
	}
}
