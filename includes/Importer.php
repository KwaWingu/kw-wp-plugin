<?php
namespace KwaWingu\Tours;

/**
 * Creates the starter-site pages from block patterns and sets the front page.
 */
class Importer {

    const META = 'kwt_pattern';

    /** @return array{created:array<int,int>,front:int} */
    public function run(): array {
        $created = array();
        $front   = 0;

        foreach ( Patterns::PAGES as $slug => $title ) {
            if ( $this->page_exists( $slug ) ) {
                continue;
            }
            $page_id = wp_insert_post( array(
                'post_type'    => 'page',
                'post_status'  => 'publish',
                'post_title'   => sanitize_text_field( $title ),
                'post_content' => $this->pattern_content( $slug ),
            ) );
            if ( is_int( $page_id ) && $page_id > 0 ) {
                update_post_meta( $page_id, self::META, $slug );
                $created[] = $page_id;
                if ( 'kwawingu/home' === $slug ) {
                    $front = $page_id;
                }
            }
        }

        if ( $front > 0 ) {
            update_option( 'show_on_front', 'page' );
            update_option( 'page_on_front', $front );
        }

        return array( 'created' => $created, 'front' => $front );
    }

    private function page_exists( string $slug ): bool {
        $found = get_posts( array(
            'post_type'      => 'page',
            'post_status'    => array( 'publish', 'draft' ),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => array( array( 'key' => self::META, 'value' => $slug ) ),
        ) );
        return ! empty( $found );
    }

    /** Resolve pattern block markup from the registry, falling back to empty. */
    private function pattern_content( string $slug ): string {
        if ( class_exists( '\WP_Block_Patterns_Registry' ) ) {
            $registry = \WP_Block_Patterns_Registry::get_instance();
            if ( $registry->is_registered( $slug ) ) {
                $pattern = $registry->get_registered( $slug );
                return isset( $pattern['content'] ) ? (string) $pattern['content'] : '';
            }
        }
        return '';
    }
}
