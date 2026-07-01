<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Seo;
use PHPUnit\Framework\TestCase;

class SeoTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\when( 'get_the_title' )->justReturn( 'Serengeti Safari' );
        Functions\when( 'get_permalink' )->justReturn( 'https://site/tours/serengeti/' );
        Functions\when( 'get_the_post_thumbnail_url' )->justReturn( 'https://img/cover.jpg' );
        Functions\when( 'get_the_excerpt' )->justReturn( 'A wild ride.' );
        Functions\when( 'get_post_meta' )->alias( static function ( $id, $key, $single ) {
            $map = array( 'kwt_price' => 450000, 'kwt_rating' => 4.5, 'kwt_review_count' => 12 );
            return $map[ $key ] ?? '';
        } );
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_json_ld_has_product_with_offer_and_rating(): void {
        $data = ( new Seo() )->json_ld( 7 );
        $this->assertSame( 'Product', $data['@type'] );
        $this->assertSame( 'Serengeti Safari', $data['name'] );
        $this->assertSame( 450000, $data['offers']['price'] );
        $this->assertSame( 'TZS', $data['offers']['priceCurrency'] );
        $this->assertSame( 4.5, $data['aggregateRating']['ratingValue'] );
        $this->assertSame( 12, $data['aggregateRating']['reviewCount'] );
    }

    public function test_json_ld_omits_rating_when_zero(): void {
        Functions\when( 'get_post_meta' )->alias( static function ( $id, $key, $single ) {
            return 'kwt_price' === $key ? 100000 : 0;
        } );
        $data = ( new Seo() )->json_ld( 7 );
        $this->assertArrayNotHasKey( 'aggregateRating', $data );
    }
}
