<?php
namespace KwaWingu\Tours;

/**
 * Small presentation helpers shared by block/shortcode renderers.
 */
final class View {

    private function __construct() {}

    /** Format an integer TZS amount, e.g. 450000 => "TZS 450,000". */
    public static function money( int $tzs ): string {
        return 'TZS ' . number_format( $tzs, 0, '.', ',' );
    }

    /**
     * A WP_Query for published tours with sane defaults, merged with $args.
     *
     * @param array<string,mixed> $args
     */
    public static function tour_query( array $args = array() ): \WP_Query {
        $defaults = array(
            'post_type'      => Cpt::TOUR,
            'post_status'    => 'publish',
            'posts_per_page' => 12,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
        );
        return new \WP_Query( array_merge( $defaults, $args ) );
    }

    /**
     * Booking URL for a tour post. Real implementation lands in Task 5 (Booking);
     * returns '' until then so callers have a stable call site.
     */
    public static function tour_booking_url( int $post_id ): string {
        if ( class_exists( __NAMESPACE__ . '\\Booking' ) ) {
            return Booking::url_for( $post_id );
        }
        return '';
    }
}
