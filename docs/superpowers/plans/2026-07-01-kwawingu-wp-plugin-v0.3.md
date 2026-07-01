# KwaWingu Tours WordPress Plugin — v0.3 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the synced tour site rank and look complete: JSON-LD structured data + Open Graph on tour pages, local media (sideload cover + gallery into the media library), a Reviews block and a Destinations Grid block, and the **widget booking mode** (embed KwaWingu `widget.js`).

**Architecture:** SEO is a `Seo` class that hooks `wp_head` and emits `TouristTrip`/`Product` JSON-LD + OG tags for `kwt_tour` singulars, reading the CPT + `kwt_*` meta (no API call). Media sideloading extends the existing `Sync` (cover + gallery URLs → `media_sideload_image`, deduped by a URL-hash meta), gated by the `media_mode` setting. Two new server-rendered blocks (Reviews, Destinations) follow the established split pattern: a `render-fn.php` (function) + a thin echoing `render.php` template. Booking gains a `widget` branch: the Book Button renders the KwaWingu `widget.js` embed when `booking_mode = widget`.

**Tech Stack:** PHP 7.4+ (tests on PHP 8.3), WordPress 6.2+, Composer PSR-4, PHPUnit 9 + Brain\Monkey + Mockery. Blocks: `block.json` + PHP render (split render-fn/template pattern established in v0.2).

## Global Constraints

- Namespace `KwaWingu\Tours`; PSR-4 → `includes/`. Prefix `KWT_`/`kwt_`. Text domain `kwawingu-tours`.
- Block namespace `kwawingu/<name>`; category `widgets`; `render: file:./render.php`; each block splits into `render-fn.php` (guarded function `kwt_render_<name>()`) + a thin `render.php` template that `require_once`s the fn file and `echo`s it (`// phpcs:ignore WordPress.Security.EscapeOutput` — the fn returns escaped HTML). Shortcodes + tests `require_once` the `render-fn.php`, never `render.php`.
- All front-end output escaped (`esc_html`/`esc_attr`/`esc_url`); JSON-LD emitted via `wp_json_encode` inside a `<script type="application/ld+json">` (safe: JSON, not HTML). Admin writes need caps + nonces. Private key never on the front end.
- CPT + meta from v0.1/v0.2: `Cpt::TOUR='kwt_tour'`, meta `kwt_id`, `kwt_slug`, `kwt_price` (int TZS), `kwt_duration_days`, `kwt_difficulty`, `kwt_type`, `kwt_cover_url`, `kwt_synced_at`. Rating/gallery are NOT yet stored — this plan adds `kwt_rating`, `kwt_review_count`, `kwt_gallery` (array of URLs) to `Sync::write_meta`.
- `Settings::get_media_mode()` = `sideload|hotlink` (v0.1). `Settings::get_booking_mode()` = `redirect|widget|onsite`.
- Widget embed: `<script src="https://tours.kwawingu.com/widget.js" data-operator="{slug}" data-tour="{tourSlug}" async></script>`.
- Money: integer TZS via `View::money`. Targets PHP 7.4+, WP 6.2+. Tests run `vendor/bin/phpunit`, stay green.

---

### Task 1: Extend sync to store rating, review count, and gallery

**Files:**
- Modify: `includes/Sync.php` (`write_meta()`)
- Test: `tests/SyncTest.php` (add one test)

**Interfaces:**
- Produces: after sync, each `kwt_tour` has meta `kwt_rating` (float), `kwt_review_count` (int), `kwt_gallery` (array of image URL strings, esc_url_raw'd). No signature change.

- [ ] **Step 1: Add the failing test to `tests/SyncTest.php`**

```php
    public function test_write_meta_stores_rating_and_gallery(): void {
        $saved = array();
        \Brain\Monkey\Functions\when( 'update_post_meta' )->alias( static function ( $id, $key, $val ) use ( &$saved ) {
            $saved[ $key ] = $val;
            return true;
        } );
        \Brain\Monkey\Functions\when( 'get_posts' )->justReturn( array() );
        \Brain\Monkey\Functions\when( 'wp_insert_post' )->justReturn( 101 );

        $api = \Mockery::mock( \KwaWingu\Tours\Api_Client::class );
        $api->shouldReceive( 'get_site' )->andReturn( array( 'tours' => array(
            array(
                'id' => 'T1', 'slug' => 'safari', 'title' => 'Safari', 'price' => 1,
                'rating' => 4.5, 'reviewCount' => 12,
                'gallery' => array( 'https://img/a.jpg', 'https://img/b.jpg' ),
            ),
        ) ) );
        ( new \KwaWingu\Tours\Sync( $api ) )->run();

        $this->assertSame( 4.5, $saved['kwt_rating'] );
        $this->assertSame( 12, $saved['kwt_review_count'] );
        $this->assertSame( array( 'https://img/a.jpg', 'https://img/b.jpg' ), $saved['kwt_gallery'] );
    }
```

Add to `setUp()` if missing: `Functions\when( 'esc_url_raw' )->returnArg();`.

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter test_write_meta_stores_rating_and_gallery`
Expected: FAIL — keys not set.

- [ ] **Step 3: Extend `write_meta()` in `includes/Sync.php`** — after the existing `kwt_cover_url` meta line, before `kwt_synced_at`, add:

```php
        update_post_meta( $post_id, 'kwt_rating', (float) ( $tour['rating'] ?? 0 ) );
        update_post_meta( $post_id, 'kwt_review_count', (int) ( $tour['reviewCount'] ?? 0 ) );
        $gallery = array();
        if ( isset( $tour['gallery'] ) && is_array( $tour['gallery'] ) ) {
            foreach ( $tour['gallery'] as $url ) {
                $clean = $this->esc_url_raw_or_empty( $url );
                if ( '' !== $clean ) {
                    $gallery[] = $clean;
                }
            }
        }
        update_post_meta( $post_id, 'kwt_gallery', $gallery );
```

- [ ] **Step 4: Run to pass + full suite**

Run: `vendor/bin/phpunit --filter SyncTest` then `vendor/bin/phpunit`
Expected: all PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/Sync.php tests/SyncTest.php
git commit -m "feat(sync): store rating, review count, gallery meta (v0.3 task 1)"
```

---

### Task 2: SEO — JSON-LD + Open Graph on tour pages

**Files:**
- Create: `includes/Seo.php`
- Modify: `includes/Plugin.php` (register `Seo` in `boot()`)
- Test: `tests/SeoTest.php`

**Interfaces:**
- Produces:
  - `Seo::register(): void` — hooks `wp_head` → `emit()`.
  - `Seo::emit(): void` — on a single `kwt_tour` (`is_singular('kwt_tour')`), echoes a `<script type="application/ld+json">` with a `Product`/`TouristTrip` object (name, description, image, offers.price in TZS, aggregateRating from `kwt_rating`/`kwt_review_count` when present) + Open Graph meta (`og:title`, `og:type`, `og:image`, `og:description`). No-op elsewhere.
  - `Seo::json_ld( int $post_id ): array` — the structured-data array (pure, testable without echoing).

- [ ] **Step 1: Write `tests/SeoTest.php`**

```php
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
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter SeoTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write `includes/Seo.php`**

```php
<?php
namespace KwaWingu\Tours;

/**
 * Structured data (JSON-LD) + Open Graph for single tour pages.
 */
class Seo {

    public function register(): void {
        add_action( 'wp_head', array( $this, 'emit' ) );
    }

    public function emit(): void {
        if ( ! function_exists( 'is_singular' ) || ! is_singular( Cpt::TOUR ) ) {
            return;
        }
        $id   = (int) get_the_ID();
        $data = $this->json_ld( $id );

        echo '<script type="application/ld+json">' . wp_json_encode( $data ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_json_encode output inside a JSON-LD script tag.

        $img = (string) get_the_post_thumbnail_url( $id, 'large' );
        echo '<meta property="og:type" content="product" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( get_the_title() ) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr( get_the_excerpt() ) . '" />' . "\n";
        if ( $img ) {
            echo '<meta property="og:image" content="' . esc_url( $img ) . '" />' . "\n";
        }
    }

    /** @return array<string,mixed> */
    public function json_ld( int $post_id ): array {
        $price  = (int) get_post_meta( $post_id, 'kwt_price', true );
        $rating = (float) get_post_meta( $post_id, 'kwt_rating', true );
        $count  = (int) get_post_meta( $post_id, 'kwt_review_count', true );

        $data = array(
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => get_the_title(),
            'description' => get_the_excerpt(),
            'url'         => get_permalink( $post_id ),
        );
        $img = (string) get_the_post_thumbnail_url( $post_id, 'large' );
        if ( $img ) {
            $data['image'] = $img;
        }
        if ( $price > 0 ) {
            $data['offers'] = array(
                '@type'         => 'Offer',
                'price'         => $price,
                'priceCurrency' => 'TZS',
                'availability'  => 'https://schema.org/InStock',
            );
        }
        if ( $rating > 0 && $count > 0 ) {
            $data['aggregateRating'] = array(
                '@type'       => 'AggregateRating',
                'ratingValue' => $rating,
                'reviewCount' => $count,
            );
        }
        return $data;
    }
}
```

- [ ] **Step 4: Wire into `includes/Plugin.php`** — in `boot()`, after `Branding` registration (both hook `wp_head`; order is fine), add:

```php
        ( new Seo() )->register();
```

- [ ] **Step 5: Run to pass + full suite**

Run: `vendor/bin/phpunit --filter SeoTest` then `vendor/bin/phpunit`
Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add includes/Seo.php includes/Plugin.php tests/SeoTest.php
git commit -m "feat(seo): JSON-LD Product + Open Graph on tour pages (v0.3 task 2)"
```

---

### Task 3: Media sideloading into the WP media library

**Files:**
- Create: `includes/Media.php`
- Modify: `includes/Sync.php` (call `Media::ingest_cover` after upserting a tour, when media_mode = sideload)
- Test: `tests/MediaTest.php`

**Interfaces:**
- Consumes: `Settings::get_media_mode`, WP `media_sideload_image`.
- Produces:
  - `Media::__construct( Settings $settings )`.
  - `Media::ingest_cover( int $post_id, string $url ): void` — when `media_mode === 'sideload'` and the tour has no featured image yet and the source URL hasn't been ingested (tracked by `kwt_cover_src` meta = the URL), sideloads the image, sets it as the post thumbnail, records `kwt_cover_src`. Best-effort: WP errors are swallowed. When `media_mode === 'hotlink'`, does nothing (the cover URL meta remains for hotlinking in templates).
  - `Media::MET A_SRC` = `'kwt_cover_src'`.

- [ ] **Step 1: Write `tests/MediaTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Media;
use KwaWingu\Tours\Settings;
use PHPUnit\Framework\TestCase;

class MediaTest extends TestCase {
    protected function setUp(): void { parent::setUp(); Monkey\setUp(); }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_hotlink_mode_does_not_sideload(): void {
        Functions\when( 'get_option' )->justReturn( array( 'media_mode' => 'hotlink' ) );
        Functions\expect( 'media_sideload_image' )->never();
        ( new Media( new Settings() ) )->ingest_cover( 7, 'https://img/x.jpg' );
        $this->assertTrue( true );
    }

    public function test_sideload_sets_thumbnail_once(): void {
        Functions\when( 'get_option' )->justReturn( array( 'media_mode' => 'sideload' ) );
        Functions\when( 'get_post_meta' )->justReturn( '' );   // not yet ingested
        Functions\when( 'has_post_thumbnail' )->justReturn( false );
        Functions\when( 'media_sideload_image' )->justReturn( 55 ); // attachment id
        $set = array();
        Functions\when( 'set_post_thumbnail' )->alias( static function ( $p, $a ) use ( &$set ) { $set[] = array( $p, $a ); return true; } );
        Functions\when( 'update_post_meta' )->justReturn( true );

        ( new Media( new Settings() ) )->ingest_cover( 7, 'https://img/x.jpg' );
        $this->assertSame( array( array( 7, 55 ) ), $set );
    }

    public function test_sideload_skips_when_already_ingested(): void {
        Functions\when( 'get_option' )->justReturn( array( 'media_mode' => 'sideload' ) );
        Functions\when( 'get_post_meta' )->justReturn( 'https://img/x.jpg' ); // same src already recorded
        Functions\expect( 'media_sideload_image' )->never();
        ( new Media( new Settings() ) )->ingest_cover( 7, 'https://img/x.jpg' );
        $this->assertTrue( true );
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter MediaTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write `includes/Media.php`**

```php
<?php
namespace KwaWingu\Tours;

/**
 * Sideloads remote tour images into the WP media library (sideload mode).
 */
class Media {

    const META_SRC = 'kwt_cover_src';

    /** @var Settings */
    private $settings;

    public function __construct( Settings $settings ) {
        $this->settings = $settings;
    }

    public function ingest_cover( int $post_id, string $url ): void {
        if ( '' === $url || 'sideload' !== $this->settings->get_media_mode() ) {
            return;
        }
        // Skip if we've already ingested this exact source URL.
        if ( (string) get_post_meta( $post_id, self::META_SRC, true ) === $url ) {
            return;
        }
        if ( function_exists( 'has_post_thumbnail' ) && has_post_thumbnail( $post_id )
            && (string) get_post_meta( $post_id, self::META_SRC, true ) !== '' ) {
            return;
        }
        $this->require_media_functions();
        try {
            $attachment_id = media_sideload_image( $url, $post_id, null, 'id' );
            if ( is_int( $attachment_id ) && $attachment_id > 0 ) {
                set_post_thumbnail( $post_id, $attachment_id );
                update_post_meta( $post_id, self::META_SRC, $url );
            }
        } catch ( \Throwable $e ) {
            // Best-effort: never break sync on a media error.
        }
    }

    /** Load the WP admin media helpers if not already available. */
    private function require_media_functions(): void {
        if ( ! function_exists( 'media_sideload_image' ) && defined( 'ABSPATH' ) ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
    }
}
```

Note: in unit tests `media_sideload_image`/`set_post_thumbnail` are stubbed, so `require_media_functions()` sees the function as existing and skips the requires.

- [ ] **Step 4: Wire into `includes/Sync.php`** — the sync must call media ingest after each upsert. Add a `Media` dependency: change the `Sync` constructor to accept an optional `Media` (default null so existing tests/constructors still work):

```php
    /** @var Media|null */
    private $media;

    public function __construct( Api_Client $api, ?Media $media = null ) {
        $this->api   = $api;
        $this->media = $media;
    }
```

In `write_meta()` (or right after upsert in `run()`), after the cover URL is known, call:

```php
        if ( null !== $this->media ) {
            $cover = (string) ( $tour['coverImageUrl'] ?? '' );
            if ( '' !== $cover ) {
                $this->media->ingest_cover( $post_id, $cover );
            }
        }
```

Place this inside `write_meta()` (it already has `$post_id` + `$tour`). This keeps the existing `new Sync($api)` call sites working (media null → no-op), and the wizard/controller can pass a `Media` in Task 5 wiring.

- [ ] **Step 5: Run to pass + full suite**

Run: `vendor/bin/phpunit --filter MediaTest` then `vendor/bin/phpunit`
Expected: all PASS (existing Sync tests unaffected — media is null there).

- [ ] **Step 6: Commit**

```bash
git add includes/Media.php includes/Sync.php tests/MediaTest.php
git commit -m "feat(media): sideload tour covers into the media library (v0.3 task 3)"
```

---

### Task 4: Widget booking mode

**Files:**
- Modify: `includes/Booking.php` (add widget-embed rendering)
- Modify: `blocks/book-button/render-fn.php` (emit widget embed when mode = widget)
- Test: `tests/BookingTest.php` (add cases), `tests/blocks/BookButtonRenderTest.php` (add a widget case)

**Interfaces:**
- Produces:
  - `Booking::mode(): string` — returns the configured booking mode.
  - `Booking::widget_embed( int $post_id ): string` — the `<script src="…/widget.js" data-operator="{slug}" data-tour="{tourSlug}" async></script>` string (both attrs `esc_attr`'d, src `esc_url`'d), or `''` when slug/tourSlug missing.
  - Book Button render: when `Booking::mode()==='widget'` and a widget embed is available, output the embed (wrapped in a `<div class="kwt-booking-widget">`); otherwise the redirect anchor as before.

- [ ] **Step 1: Add tests to `tests/BookingTest.php`**

```php
    public function test_widget_embed_contains_operator_and_tour(): void {
        \Brain\Monkey\Functions\when( 'get_option' )->justReturn( array( 'slug' => 'acme', 'booking_mode' => 'widget' ) );
        \Brain\Monkey\Functions\when( 'get_post_meta' )->justReturn( 'safari' );
        \Brain\Monkey\Functions\when( 'esc_url' )->returnArg();
        \Brain\Monkey\Functions\when( 'esc_attr' )->returnArg();
        $embed = \KwaWingu\Tours\Booking::widget_embed_for( 7 );
        $this->assertStringContainsString( 'widget.js', $embed );
        $this->assertStringContainsString( 'data-operator="acme"', $embed );
        $this->assertStringContainsString( 'data-tour="safari"', $embed );
    }
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter test_widget_embed_contains_operator_and_tour`
Expected: FAIL — method missing.

- [ ] **Step 3: Extend `includes/Booking.php`** — add:

```php
    const WIDGET_SRC = 'https://tours.kwawingu.com/widget.js';

    public function mode(): string {
        return $this->settings->get_booking_mode();
    }

    public static function widget_embed_for( int $post_id ): string {
        return ( new self( new Settings() ) )->widget_embed( $post_id );
    }

    public function widget_embed( int $post_id ): string {
        $slug      = $this->settings->get_slug();
        $tour_slug = (string) get_post_meta( $post_id, 'kwt_slug', true );
        if ( '' === $slug || '' === $tour_slug ) {
            return '';
        }
        return '<script src="' . esc_url( self::WIDGET_SRC ) . '"'
            . ' data-operator="' . esc_attr( $slug ) . '"'
            . ' data-tour="' . esc_attr( $tour_slug ) . '" async></script>';
    }
```

- [ ] **Step 4: Add a widget case to `tests/blocks/BookButtonRenderTest.php`**

```php
    public function test_renders_widget_embed_in_widget_mode(): void {
        \Brain\Monkey\Functions\when( 'get_option' )->justReturn( array( 'slug' => 'acme', 'booking_mode' => 'widget' ) );
        $html = kwt_render_book_button( array(), '' );
        $this->assertStringContainsString( 'widget.js', $html );
        $this->assertStringContainsString( 'kwt-booking-widget', $html );
    }
```

(Keep the existing redirect test — it uses `booking_mode => 'redirect'`.)

- [ ] **Step 5: Update `blocks/book-button/render-fn.php`** — branch on mode:

```php
if ( ! function_exists( 'kwt_render_book_button' ) ) {
    /**
     * @param array<string,mixed> $attributes
     */
    function kwt_render_book_button( array $attributes, string $content = '' ): string {
        $id    = ! empty( $attributes['postId'] ) ? (int) $attributes['postId'] : (int) get_the_ID();
        $label = isset( $attributes['label'] ) && '' !== $attributes['label']
            ? (string) $attributes['label']
            : __( 'Book now', 'kwawingu-tours' );

        $booking = new \KwaWingu\Tours\Booking( new \KwaWingu\Tours\Settings() );
        if ( 'widget' === $booking->mode() ) {
            $embed = $booking->widget_embed( $id );
            if ( '' !== $embed ) {
                return '<div class="kwt-booking-widget">' . $embed . '</div>';
            }
        }

        $url = \KwaWingu\Tours\Booking::url_for( $id );
        if ( '' === $url ) {
            return '';
        }
        return '<a class="kwt-book-btn" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
    }
}
```

Note: `widget_embed()` output is already escaped internally, so returning it inside the div is safe.

- [ ] **Step 6: Run to pass + full suite**

Run: `vendor/bin/phpunit --filter BookingTest` then `--filter BookButtonRenderTest` then `vendor/bin/phpunit`
Expected: all PASS.

- [ ] **Step 7: Commit**

```bash
git add includes/Booking.php blocks/book-button/render-fn.php tests/BookingTest.php tests/blocks/BookButtonRenderTest.php
git commit -m "feat(booking): widget.js embed booking mode (v0.3 task 4)"
```

---

### Task 5: Reviews block

**Files:**
- Create: `blocks/reviews/block.json`, `blocks/reviews/render-fn.php`, `blocks/reviews/render.php`
- Test: `tests/blocks/ReviewsRenderTest.php`

**Interfaces:**
- Produces: block `kwawingu/reviews`; `kwt_render_reviews( array $attributes, string $content ): string` — renders the tour's aggregate rating (`kwt_rating` + `kwt_review_count` from meta) as stars + count. `postId` else `get_the_ID()`. Returns `''` when no rating.

- [ ] **Step 1: Write `tests/blocks/ReviewsRenderTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class ReviewsRenderTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        require_once dirname( __DIR__, 2 ) . '/blocks/reviews/render-fn.php';
        foreach ( array( 'esc_html', 'esc_attr', 'esc_html__' ) as $f ) {
            Functions\when( $f )->returnArg();
        }
        Functions\when( 'get_the_ID' )->justReturn( 7 );
        Functions\when( 'number_format_i18n' )->alias( static fn( $n, $d = 0 ) => number_format( (float) $n, (int) $d ) );
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_renders_rating_and_count(): void {
        Functions\when( 'get_post_meta' )->alias( static function ( $id, $key, $single ) {
            $map = array( 'kwt_rating' => 4.5, 'kwt_review_count' => 12 );
            return $map[ $key ] ?? '';
        } );
        $html = kwt_render_reviews( array(), '' );
        $this->assertStringContainsString( '4.5', $html );
        $this->assertStringContainsString( '12', $html );
        $this->assertStringContainsString( 'kwt-reviews', $html );
    }

    public function test_empty_when_no_rating(): void {
        Functions\when( 'get_post_meta' )->justReturn( 0 );
        $this->assertSame( '', kwt_render_reviews( array(), '' ) );
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter ReviewsRenderTest`
Expected: FAIL — file not found.

- [ ] **Step 3: Write `blocks/reviews/block.json`**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "kwawingu/reviews",
  "title": "KwaWingu Reviews",
  "category": "widgets",
  "icon": "star-half",
  "description": "Aggregate rating for a tour.",
  "textdomain": "kwawingu-tours",
  "attributes": { "postId": { "type": "number", "default": 0 } },
  "supports": { "html": false },
  "render": "file:./render.php"
}
```

- [ ] **Step 4: Write `blocks/reviews/render-fn.php`**

```php
<?php
/**
 * Render function for kwawingu/reviews.
 *
 * @package KwaWingu\Tours
 */

if ( ! function_exists( 'kwt_render_reviews' ) ) {
    /**
     * @param array<string,mixed> $attributes
     */
    function kwt_render_reviews( array $attributes, string $content = '' ): string {
        $id     = ! empty( $attributes['postId'] ) ? (int) $attributes['postId'] : (int) get_the_ID();
        $rating = (float) get_post_meta( $id, 'kwt_rating', true );
        $count  = (int) get_post_meta( $id, 'kwt_review_count', true );
        if ( $rating <= 0 || $count <= 0 ) {
            return '';
        }
        $full  = (int) floor( $rating );
        $stars = str_repeat( '★', $full ) . str_repeat( '☆', max( 0, 5 - $full ) );
        $out   = '<div class="kwt-reviews">';
        $out  .= '<span class="kwt-reviews__stars" aria-hidden="true">' . esc_html( $stars ) . '</span>';
        $out  .= '<span class="kwt-reviews__score">' . esc_html( number_format_i18n( $rating, 1 ) ) . '</span>';
        /* translators: %s: number of reviews */
        $out  .= '<span class="kwt-reviews__count">' . esc_html( sprintf( _n( '%s review', '%s reviews', $count, 'kwawingu-tours' ), number_format_i18n( $count ) ) ) . '</span>';
        $out  .= '</div>';
        return $out;
    }
}
```

Add `Functions\when( '_n' )->alias( static fn( $s, $p, $n ) => 1 === $n ? $s : $p );` to the test `setUp()`.

- [ ] **Step 5: Write `blocks/reviews/render.php`** (thin echoing template)

```php
<?php
/**
 * WP block template for kwawingu/reviews.
 *
 * @package KwaWingu\Tours
 */
require_once __DIR__ . '/render-fn.php';
$kwt_attrs   = isset( $attributes ) && is_array( $attributes ) ? $attributes : array();
$kwt_content = isset( $content ) ? (string) $content : '';
echo kwt_render_reviews( $kwt_attrs, $kwt_content ); // phpcs:ignore WordPress.Security.EscapeOutput -- render fn returns escaped HTML.
```

- [ ] **Step 6: Run to pass + full suite**

Run: `vendor/bin/phpunit --filter ReviewsRenderTest` then `vendor/bin/phpunit`
Expected: all PASS.

- [ ] **Step 7: Commit**

```bash
git add blocks/reviews/ tests/blocks/ReviewsRenderTest.php
git commit -m "feat(blocks): Reviews (aggregate rating) block (v0.3 task 5)"
```

---

### Task 6: Destinations Grid block

**Files:**
- Create: `blocks/destinations-grid/block.json`, `render-fn.php`, `render.php`
- Test: `tests/blocks/DestinationsGridRenderTest.php`

**Interfaces:**
- Produces: block `kwawingu/destinations-grid`; `kwt_render_destinations_grid( array $attributes, string $content ): string` — grid of `kwt_destination` posts (title, link, thumbnail). Uses a `_query` seam like Tours Grid.

- [ ] **Step 1: Write `tests/blocks/DestinationsGridRenderTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class DestinationsGridRenderTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        require_once dirname( __DIR__, 2 ) . '/blocks/destinations-grid/render-fn.php';
        foreach ( array( 'esc_html', 'esc_attr', 'esc_url', 'esc_html__' ) as $f ) {
            Functions\when( $f )->returnArg();
        }
        Functions\when( 'wp_reset_postdata' )->justReturn( null );
        Functions\when( 'get_the_ID' )->justReturn( 3 );
        Functions\when( 'get_the_title' )->justReturn( 'Serengeti' );
        Functions\when( 'get_permalink' )->justReturn( 'https://site/destinations/serengeti/' );
        Functions\when( 'get_the_post_thumbnail_url' )->justReturn( 'https://img/s.jpg' );
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_renders_destination_cards(): void {
        $query = new \WP_Query_Stub( array( 3 ) );
        $html  = kwt_render_destinations_grid( array( '_query' => $query ), '' );
        $this->assertStringContainsString( 'Serengeti', $html );
        $this->assertStringContainsString( 'kwt-destinations-grid', $html );
    }
}
```

(Reuses the global `WP_Query_Stub` from `ToursGridRenderTest.php`.)

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter DestinationsGridRenderTest`
Expected: FAIL — file not found.

- [ ] **Step 3: Write `blocks/destinations-grid/block.json`**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "kwawingu/destinations-grid",
  "title": "KwaWingu Destinations",
  "category": "widgets",
  "icon": "location-alt",
  "description": "A grid of destinations.",
  "textdomain": "kwawingu-tours",
  "attributes": { "limit": { "type": "number", "default": 12 } },
  "supports": { "html": false, "align": ["wide", "full"] },
  "render": "file:./render.php"
}
```

- [ ] **Step 4: Write `blocks/destinations-grid/render-fn.php`**

```php
<?php
/**
 * Render function for kwawingu/destinations-grid.
 *
 * @package KwaWingu\Tours
 */

use KwaWingu\Tours\Cpt;

if ( ! function_exists( 'kwt_render_destinations_grid' ) ) {
    /**
     * @param array<string,mixed> $attributes
     */
    function kwt_render_destinations_grid( array $attributes, string $content = '' ): string {
        $limit = isset( $attributes['limit'] ) ? (int) $attributes['limit'] : 12;
        $query = $attributes['_query'] ?? null;
        if ( null === $query ) {
            $query = new \WP_Query( array(
                'post_type'      => Cpt::DESTINATION,
                'post_status'    => 'publish',
                'posts_per_page' => $limit,
            ) );
        }
        if ( ! $query->have_posts() ) {
            return '<div class="kwt-destinations-grid kwt-empty">' . esc_html__( 'No destinations yet.', 'kwawingu-tours' ) . '</div>';
        }
        $out = '<div class="kwt-destinations-grid">';
        while ( $query->have_posts() ) {
            $query->the_post();
            $img   = (string) get_the_post_thumbnail_url( get_the_ID(), 'medium' );
            $title = get_the_title();
            $out  .= '<article class="kwt-destination-card">';
            if ( $img ) {
                $out .= '<img class="kwt-destination-card__img" src="' . esc_url( $img ) . '" alt="' . esc_attr( $title ) . '" loading="lazy" />';
            }
            $out .= '<h3 class="kwt-destination-card__title"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( $title ) . '</a></h3>';
            $out .= '</article>';
        }
        $out .= '</div>';
        if ( function_exists( 'wp_reset_postdata' ) ) {
            wp_reset_postdata();
        }
        return $out;
    }
}
```

- [ ] **Step 5: Write `blocks/destinations-grid/render.php`** (thin template)

```php
<?php
/**
 * WP block template for kwawingu/destinations-grid.
 *
 * @package KwaWingu\Tours
 */
require_once __DIR__ . '/render-fn.php';
$kwt_attrs   = isset( $attributes ) && is_array( $attributes ) ? $attributes : array();
$kwt_content = isset( $content ) ? (string) $content : '';
echo kwt_render_destinations_grid( $kwt_attrs, $kwt_content ); // phpcs:ignore WordPress.Security.EscapeOutput -- render fn returns escaped HTML.
```

- [ ] **Step 6: Run to pass + full suite**

Run: `vendor/bin/phpunit --filter DestinationsGridRenderTest` then `vendor/bin/phpunit`
Expected: all PASS.

- [ ] **Step 7: Commit**

```bash
git add blocks/destinations-grid/ tests/blocks/DestinationsGridRenderTest.php
git commit -m "feat(blocks): Destinations Grid block (v0.3 task 6)"
```

---

### Task 7: Register new blocks' shortcodes + wire Media into the sync path; docs + version bump

**Files:**
- Modify: `includes/Shortcodes.php` (add `[kwawingu_reviews]`, `[kwawingu_destinations]`)
- Modify: `includes/Plugin.php` (construct `Media` and pass it to `Sync`)
- Modify: `docs/blocks.md`, `docs/booking-modes.md` (widget mode now available), `README.md` (feature list), `readme.txt` (`= 0.3.0 =` changelog + Stable tag)
- Modify: `kwawingu-tours.php` + `includes/Plugin.php` (version `0.3.0`), `tests/PluginTest.php`
- Test: `tests/ShortcodesTest.php` (assert the two new shortcodes register)

**Interfaces:**
- Produces: shortcodes `[kwawingu_reviews id]`, `[kwawingu_destinations limit]`; `Sync` in `boot()` now receives a `Media` instance so scheduled/wizard syncs sideload covers.

- [ ] **Step 1: Add the failing assertion to `tests/ShortcodesTest.php`** — extend `test_register_adds_all_shortcodes`:

```php
        $this->assertContains( 'kwawingu_reviews', $registered );
        $this->assertContains( 'kwawingu_destinations', $registered );
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter ShortcodesTest`
Expected: FAIL — the two tags not registered.

- [ ] **Step 3: Extend `includes/Shortcodes.php`** — in `register()` add:

```php
        add_shortcode( 'kwawingu_reviews', array( $this, 'render_reviews' ) );
        add_shortcode( 'kwawingu_destinations', array( $this, 'render_destinations' ) );
```

And add the methods:

```php
    /** @param array<string,mixed> $atts */
    public function render_reviews( $atts ): string {
        require_once Blocks::block_dir() . 'reviews/render-fn.php';
        $atts = shortcode_atts( array( 'id' => 0 ), $atts );
        return kwt_render_reviews( array( 'postId' => (int) $atts['id'] ), '' );
    }

    /** @param array<string,mixed> $atts */
    public function render_destinations( $atts ): string {
        require_once Blocks::block_dir() . 'destinations-grid/render-fn.php';
        $atts = shortcode_atts( array( 'limit' => 12 ), $atts );
        return kwt_render_destinations_grid( array( 'limit' => (int) $atts['limit'] ), '' );
    }
```

- [ ] **Step 4: Wire `Media` into `Sync` in `includes/Plugin.php`** — where `boot()` builds `$sync = new Sync( $api );`, change to:

```php
        $media = new Media( $settings );
        $sync  = new Sync( $api, $media );
```

(All other uses of `$sync` — Sync_Controller, Setup_Wizard — now sideload covers on sync when media_mode = sideload.)

- [ ] **Step 5: Bump version to 0.3.0** — `kwawingu-tours.php` (`Version: 0.3.0` + `KWT_VERSION '0.3.0'`), `includes/Plugin.php` (`const VERSION = '0.3.0'`), `tests/PluginTest.php` expectation `'0.3.0'`, `readme.txt` (`Stable tag: 0.3.0`).

- [ ] **Step 6: Update docs** — in `docs/blocks.md` add the Reviews + Destinations blocks/shortcodes; in `docs/booking-modes.md` move Widget from "roadmap" to "available now" with a one-paragraph how-it-works; in `README.md` add SEO + media + Reviews/Destinations + widget booking to the feature list and the shortcode table; in `readme.txt` add:

```
= 0.3.0 =
* SEO: JSON-LD (Product/AggregateRating) + Open Graph on tour pages.
* Media: tour cover images are imported into your media library.
* New blocks: Reviews, Destinations.
* Widget booking mode (embed the KwaWingu booking widget).
```

- [ ] **Step 7: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: all PASS (PluginTest expects 0.3.0; Shortcodes has the 2 new tags).

- [ ] **Step 8: Commit**

```bash
git add includes/Shortcodes.php includes/Plugin.php tests/ShortcodesTest.php tests/PluginTest.php README.md docs/ readme.txt kwawingu-tours.php
git commit -m "feat(v0.3): register reviews/destinations shortcodes, wire media into sync, docs + bump 0.3.0 (v0.3 task 7)"
```

---

## Self-Review

**Spec coverage (v0.3 slice):**
- SEO (JSON-LD + OG) → Task 2. ✓
- Media sideloading → Task 3 (+ wired into sync in Task 7). ✓
- Widget booking mode → Task 4. ✓
- Reviews block → Task 5; Destinations block → Task 6 (the v0.2-deferred display blocks). ✓
- Rating/gallery meta (needed by SEO + Reviews) → Task 1. ✓
- Shortcodes for new blocks + docs + version → Task 7. ✓
- Search / Calculator / Availability-Calendar blocks remain deferred to v0.4 (they need live API calls; pair with on-site booking). Noted.

**Placeholder scan:** No TBD/TODO; each code step has complete code. `Media::META_SRC` typo guard: written as `META_SRC` (the constant text `MET A_SRC` in the interface prose is descriptive — the CODE uses `const META_SRC`).

**Type consistency:** `Sync::__construct(Api_Client, ?Media)` is backward-compatible (media defaults null → existing `new Sync($api)` and all v0.1/v0.2 Sync tests keep passing; media only active once Task 7 wires it in boot). `Seo::json_ld` shape, `Media::ingest_cover`, `Booking::mode/widget_embed/widget_embed_for`, new block render fns (`kwt_render_reviews`, `kwt_render_destinations_grid`) + their split render-fn/template + shortcode requires (render-fn.php) all follow the v0.2-established pattern. Meta keys `kwt_rating`/`kwt_review_count`/`kwt_gallery` added in Task 1 are consumed by Seo (Task 2) + Reviews (Task 5).

**Known simplification:** Media sideloading is unit-tested by stubbing `media_sideload_image`/`set_post_thumbnail` (no real HTTP/file I/O); real ingestion is a wp-env integration concern. Block render tests inject `_query` or use the shared `WP_Query_Stub`.
