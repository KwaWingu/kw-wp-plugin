<?php
/**
 * Registers the native content model: tours, destinations, and tour-type taxonomy.
 *
 * @package KwaWingu\Tours
 */

namespace KwaWingu\Tours;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Registers the native content model: tours, destinations, tour-type taxonomy.
 */
class Cpt {

	const TOUR        = 'kwt_tour';
	const DESTINATION = 'kwt_destination';
	const TYPE_TAX    = 'kwt_tour_type';
	const LEAD        = 'kwt_lead';

	/**
	 * Registers the init action hook for post type and taxonomy registration.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Registers the tour and destination post types and the tour-type taxonomy.
	 *
	 * @return void
	 */
	public function init(): void {
		register_post_type(
			self::TOUR,
			array(
				'labels'       => array(
					'name'          => __( 'Tours', 'kwawingu-tours' ),
					'singular_name' => __( 'Tour', 'kwawingu-tours' ),
				),
				'public'       => true,
				'has_archive'  => true,
				'menu_icon'    => 'dashicons-palmtree',
				'rewrite'      => array(
					'slug'       => 'tours',
					'with_front' => false,
				),
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
				'show_in_rest' => true,
			)
		);

		register_post_type(
			self::DESTINATION,
			array(
				'labels'       => array(
					'name'          => __( 'Destinations', 'kwawingu-tours' ),
					'singular_name' => __( 'Destination', 'kwawingu-tours' ),
				),
				'public'       => true,
				'has_archive'  => false,
				'menu_icon'    => 'dashicons-location',
				'rewrite'      => array(
					'slug'       => 'destinations',
					'with_front' => false,
				),
				'supports'     => array( 'title', 'editor', 'thumbnail' ),
				'show_in_rest' => true,
			)
		);

		register_post_type(
			self::LEAD,
			array(
				'labels'          => array(
					'name'          => __( 'Leads', 'kwawingu-tours' ),
					'singular_name' => __( 'Lead', 'kwawingu-tours' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-groups',
				'supports'        => array( 'title', 'custom-fields' ),
				'capability_type' => 'post',
			)
		);

		register_taxonomy(
			self::TYPE_TAX,
			array( self::TOUR ),
			array(
				'labels'            => array(
					'name'          => __( 'Tour Types', 'kwawingu-tours' ),
					'singular_name' => __( 'Tour Type', 'kwawingu-tours' ),
				),
				'public'            => true,
				'hierarchical'      => false,
				'show_admin_column' => true,
				'rewrite'           => array(
					'slug'       => 'tour-type',
					'with_front' => false,
				),
				'show_in_rest'      => true,
			)
		);
	}
}
