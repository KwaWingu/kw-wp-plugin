<?php
/**
 * Block pattern registration for the KwaWingu Tours starter site.
 *
 * @package KwaWingu\Tours
 */

namespace KwaWingu\Tours;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Registers block patterns used to scaffold the starter site.
 */
class Patterns {

	const CATEGORY = 'kwawingu';

	/** Slugs the Importer turns into pages: slug => page title. */
	const PAGES = array(
		'kwawingu/home'    => 'Home',
		'kwawingu/tours'   => 'Tours',
		'kwawingu/about'   => 'About',
		'kwawingu/contact' => 'Contact',
	);

	/**
	 * Hook pattern registration onto the init action.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Register the pattern category and all starter-site patterns.
	 *
	 * @return void
	 */
	public function init(): void {
		register_block_pattern_category( self::CATEGORY, array( 'label' => __( 'KwaWingu Tours', 'kwawingu-tours' ) ) );

		$this->add(
			'kwawingu/home',
			__( 'Home', 'kwawingu-tours' ),
			'<!-- wp:heading {"level":1} --><h1>' . esc_html__( 'Explore our tours', 'kwawingu-tours' ) . '</h1><!-- /wp:heading -->'
			. '<!-- wp:kwawingu/featured-tours {"heading":"Featured tours","limit":3} /-->'
		);
		$this->add(
			'kwawingu/tours',
			__( 'Tours', 'kwawingu-tours' ),
			'<!-- wp:heading --><h2>' . esc_html__( 'All tours', 'kwawingu-tours' ) . '</h2><!-- /wp:heading -->'
			. '<!-- wp:kwawingu/tours-grid {"limit":24} /-->'
		);
		$this->add(
			'kwawingu/tour-detail',
			__( 'Tour detail', 'kwawingu-tours' ),
			'<!-- wp:kwawingu/tour-detail /-->'
		);
		$this->add(
			'kwawingu/about',
			__( 'About', 'kwawingu-tours' ),
			'<!-- wp:heading --><h2>' . esc_html__( 'About us', 'kwawingu-tours' ) . '</h2><!-- /wp:heading -->'
			. '<!-- wp:paragraph --><p>' . esc_html__( 'Tell your story here.', 'kwawingu-tours' ) . '</p><!-- /wp:paragraph -->'
		);
		$this->add(
			'kwawingu/contact',
			__( 'Contact', 'kwawingu-tours' ),
			'<!-- wp:heading --><h2>' . esc_html__( 'Get in touch', 'kwawingu-tours' ) . '</h2><!-- /wp:heading -->'
			. '<!-- wp:paragraph --><p>' . esc_html__( 'Have a question about one of our tours? Fill in the form below and we\'ll get back to you shortly.', 'kwawingu-tours' ) . '</p><!-- /wp:paragraph -->'
			. '<!-- wp:kwawingu/inquiry-form {"heading":"' . esc_attr__( 'Send us an inquiry', 'kwawingu-tours' ) . '"} /-->'
		);
	}

	/**
	 * Register a single block pattern.
	 *
	 * @param string $slug    Unique pattern slug (e.g. "kwawingu/home").
	 * @param string $title   Human-readable pattern title.
	 * @param string $content Block markup for the pattern.
	 * @return void
	 */
	private function add( string $slug, string $title, string $content ): void {
		register_block_pattern(
			$slug,
			array(
				'title'      => $title,
				'categories' => array( self::CATEGORY ),
				'content'    => $content,
			)
		);
	}
}
