# KwaWingu Tours WordPress Plugin — v0.2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn v0.1's data foundation into a usable website builder: server-rendered Gutenberg blocks over the synced CPTs, shortcode bridges, block patterns, a one-click setup wizard that auto-brands from the KwaWingu profile and scaffolds a full site, and redirect-mode booking — plus the v0.1 carry-over sync guard and v0.2 documentation.

**Architecture:** Blocks are **server-rendered** (`block.json` + a `render.php` callback that reads the `kwt_tour`/`kwt_destination` CPTs via `WP_Query` — no API call on page view, SEO-friendly). A shared `View` helper centralizes escaping + money/format helpers so block renderers stay small. Shortcodes reuse the block render callbacks. The wizard orchestrates existing pieces: `Branding` (pull `/profile`), `Importer` (create pages from patterns + nav + front page), and the existing `Sync`. Booking is a thin `Booking` URL/label helper (redirect mode this phase).

**Tech Stack:** PHP 7.4+ (runtime floor; tests run on PHP 8.3), WordPress 6.2+, Composer PSR-4, PHPUnit 9 + Brain\Monkey + Mockery (pure-PHP unit tests, no Docker). Blocks use `block.json` metadata + PHP render callbacks (no build step / JSX required in v0.2 — `edit` uses `useBlockProps` via a tiny inline script or the generic block from metadata).

## Global Constraints

- Namespace `KwaWingu\Tours`; PSR-4 → `includes/`. Prefix `KWT_`/`kwt_`. Text domain `kwawingu-tours` on every user-facing string.
- Block namespace: `kwawingu/<name>` (e.g. `kwawingu/tours-grid`). Block category: `widgets` (or a custom `kwawingu` category — use `widgets` to avoid extra registration).
- All output escaped (`esc_html`/`esc_attr`/`esc_url`); all input sanitized; nonces + `current_user_can('manage_options')` on every admin write. Private key never reaches the front end.
- CPT constants from v0.1: `Cpt::TOUR='kwt_tour'`, `Cpt::DESTINATION='kwt_destination'`, `Cpt::TYPE_TAX='kwt_tour_type'`. Tour meta keys from v0.1 sync: `kwt_id`, `kwt_slug`, `kwt_price` (int TZS), `kwt_duration_days` (int), `kwt_difficulty`, `kwt_type`, `kwt_cover_url`, `kwt_synced_at`.
- Booking modes: `redirect|widget|onsite` (from `Settings::get_booking_mode()`). Only `redirect` is implemented in v0.2; `widget`/`onsite` fall back to redirect this phase (documented).
- Hosted booking URL (redirect mode): `https://tours.kwawingu.com/{operatorSlug}/tours/{tourSlug}`. Operator slug from `Settings::get_slug()`; tour slug from the `kwt_slug` meta.
- Money: prices are integer TZS. Format as `TZS ` + thousands-separated integer.
- Targets PHP 7.4+, WP 6.2+. No bundled minified libraries. Tests run with `vendor/bin/phpunit` and must stay green.

---

### Task 1: Sync empty-`/site` guard (v0.1 carry-over must-fix)

**Files:**
- Modify: `includes/Sync.php` (the `run()` method)
- Test: `tests/SyncTest.php` (add one test)

**Interfaces:**
- Consumes: existing `Sync::run()`.
- Produces: no signature change — behavior change only: when the API call succeeds but yields zero created and zero updated tours, the soft-unpublish sweep is skipped (guards against a blank/partial upstream response drafting the whole catalog).

- [ ] **Step 1: Add the failing test to `tests/SyncTest.php`** (inside the class, alongside the existing tests)

```php
    public function test_empty_tours_response_does_not_unpublish_catalog(): void {
        // A successful /site with an empty tours[] must NOT draft existing posts.
        Functions\when( 'get_posts' )->justReturn( array( 999 ) ); // an existing published tour
        Functions\when( 'get_post_meta' )->alias( static function ( $id, $key, $single ) {
            return 'kwt_id' === $key ? 'STILL-HERE' : '';
        } );
        $drafted = array();
        Functions\when( 'wp_update_post' )->alias( static function ( $args ) use ( &$drafted ) {
            $drafted[] = $args;
            return $args['ID'] ?? 0;
        } );

        $api = Mockery::mock( Api_Client::class );
        $api->shouldReceive( 'get_site' )->once()->andReturn( array( 'tours' => array() ) );

        $out = ( new Sync( $api ) )->run();

        $this->assertSame( 0, $out['created'] );
        $this->assertSame( 0, $out['updated'] );
        $this->assertSame( 0, $out['unpublished'] );      // guard engaged
        $this->assertSame( array(), $drafted );            // nothing drafted
        $this->assertNotEmpty( $out['errors'] );           // a warning is recorded
    }
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter test_empty_tours_response_does_not_unpublish_catalog`
Expected: FAIL — `unpublished` is 1 and a post was drafted (current behavior).

- [ ] **Step 3: Implement the guard in `includes/Sync.php`** — in `run()`, replace the line that calls `unpublish_missing( $seen_ids )` with a guarded version:

```php
        // Guard: never soft-unpublish the whole catalog on a blank/partial upstream
        // response. Only sweep when this run actually saw tours.
        if ( $result['created'] + $result['updated'] > 0 ) {
            $result['unpublished'] = $this->unpublish_missing( $seen_ids );
        } elseif ( ! empty( $tours ) === false ) {
            // Successful response but zero tours parsed — record and skip the sweep.
            $result['errors'][] = 'Sync returned no tours; skipped unpublish to protect the catalog.';
        }
```

Note: `$tours` is the array read from `$site['tours']` earlier in `run()`. If `created+updated` is 0 because every tour failed to parse, the sweep is still skipped and a warning recorded.

- [ ] **Step 4: Run to verify it passes + full suite**

Run: `vendor/bin/phpunit --filter SyncTest` then `vendor/bin/phpunit`
Expected: all PASS (SyncTest gains 1 test; note the existing `test_unpublishes_tour_missing_from_response` still passes because that test has a created tour, so the sweep still runs).

- [ ] **Step 5: Commit**

```bash
git add includes/Sync.php tests/SyncTest.php
git commit -m "fix(sync): skip unpublish sweep on empty/parse-failed /site response (v0.2 task 1)"
```

---

### Task 2: Blocks infrastructure + View helper

**Files:**
- Create: `includes/View.php`
- Create: `includes/Blocks.php`
- Modify: `includes/Plugin.php` (register `Blocks` in `boot()`)
- Test: `tests/ViewTest.php`, `tests/BlocksTest.php`

**Interfaces:**
- Produces:
  - `View::money( int $tzs ): string` → e.g. `"TZS 450,000"`.
  - `View::tour_query( array $args = array() ): \WP_Query` → a `WP_Query` for published `kwt_tour` posts merged with sane defaults (`post_type=kwt_tour`, `post_status=publish`, `posts_per_page` default 12).
  - `View::tour_booking_url( int $post_id ): string` — placeholder that returns `''` in this task; the real implementation arrives in Task 5 (`Booking`). (Keep it here so block renderers have one call site; Task 5 rewires it.)
  - `Blocks::register(): void` — hooks `init` to `register_block_type()` for each block directory under `blocks/` (added in later tasks). With no block dirs yet, it registers nothing but must not error.
  - `Blocks::BLOCK_DIR` = plugin path `blocks/`.

- [ ] **Step 1: Write `tests/ViewTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\View;
use PHPUnit\Framework\TestCase;

class ViewTest extends TestCase {
    protected function setUp(): void { parent::setUp(); Monkey\setUp(); }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_money_formats_tzs_with_thousands(): void {
        $this->assertSame( 'TZS 450,000', View::money( 450000 ) );
        $this->assertSame( 'TZS 0', View::money( 0 ) );
        $this->assertSame( 'TZS 1,250', View::money( 1250 ) );
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter ViewTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write `includes/View.php`**

```php
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
```

- [ ] **Step 4: Run ViewTest to pass**

Run: `vendor/bin/phpunit --filter ViewTest`
Expected: PASS.

- [ ] **Step 5: Write `tests/BlocksTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use KwaWingu\Tours\Blocks;
use PHPUnit\Framework\TestCase;

class BlocksTest extends TestCase {
    protected function setUp(): void { parent::setUp(); Monkey\setUp(); }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_register_hooks_init(): void {
        ( new Blocks() )->register();
        $this->assertNotFalse( has_action( 'init' ) );
    }

    public function test_init_with_no_block_dirs_does_not_error(): void {
        // BLOCK_DIR points at a real, possibly-empty directory; init must be safe.
        ( new Blocks() )->init();
        $this->assertTrue( true );
    }
}
```

- [ ] **Step 6: Write `includes/Blocks.php`**

```php
<?php
namespace KwaWingu\Tours;

/**
 * Registers all server-rendered blocks by scanning blocks/ for block.json dirs.
 */
class Blocks {

    /** Absolute path to the blocks directory (with trailing slash). */
    public static function block_dir(): string {
        return defined( 'KWT_PLUGIN_DIR' ) ? KWT_PLUGIN_DIR . 'blocks/' : __DIR__ . '/../blocks/';
    }

    public function register(): void {
        add_action( 'init', array( $this, 'init' ) );
    }

    public function init(): void {
        $dir = self::block_dir();
        if ( ! is_dir( $dir ) ) {
            return;
        }
        foreach ( (array) glob( $dir . '*', GLOB_ONLYDIR ) as $block_path ) {
            if ( file_exists( $block_path . '/block.json' ) ) {
                register_block_type( $block_path );
            }
        }
    }
}
```

- [ ] **Step 7: Wire into `includes/Plugin.php`** — inside `boot()`, after the `Cpt` registration line, add:

```php
        ( new Blocks() )->register();
```

- [ ] **Step 8: Run full suite**

Run: `vendor/bin/phpunit`
Expected: all PASS (ViewTest + BlocksTest added).

- [ ] **Step 9: Commit**

```bash
git add includes/View.php includes/Blocks.php includes/Plugin.php tests/ViewTest.php tests/BlocksTest.php
git commit -m "feat(blocks): block registration infra + View helper (v0.2 task 2)"
```

---

### Task 3: Tours Grid block

**Files:**
- Create: `blocks/tours-grid/block.json`
- Create: `blocks/tours-grid/render.php`
- Test: `tests/blocks/ToursGridRenderTest.php`

**Interfaces:**
- Consumes: `View::money`, `View::tour_query`, `View::tour_booking_url`, CPT meta keys.
- Produces: block `kwawingu/tours-grid`; render callback `kwt_render_tours_grid( array $attributes, string $content ): string`.

- [ ] **Step 1: Write `tests/blocks/ToursGridRenderTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class ToursGridRenderTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        require_once dirname( __DIR__, 2 ) . '/blocks/tours-grid/render.php';
        Functions\when( 'esc_html' )->returnArg();
        Functions\when( 'esc_attr' )->returnArg();
        Functions\when( 'esc_url' )->returnArg();
        Functions\when( 'get_the_ID' )->justReturn( 7 );
        Functions\when( 'get_the_title' )->justReturn( 'Serengeti Safari' );
        Functions\when( 'get_permalink' )->justReturn( 'https://site/tours/serengeti/' );
        Functions\when( 'get_the_post_thumbnail_url' )->justReturn( 'https://img/cover.jpg' );
        Functions\when( 'get_post_meta' )->alias( static function ( $id, $key, $single ) {
            $map = array( 'kwt_price' => 450000, 'kwt_duration_days' => 3 );
            return $map[ $key ] ?? '';
        } );
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_renders_tour_cards_with_title_and_price(): void {
        // Fake WP_Query: one tour in the loop.
        $query = new \WP_Query_Stub( array( 7 ) );
        Functions\when( 'KwaWingu\Tours\View::tour_query' ); // not used; we inject the query
        // Render directly with an injected query via the attribute hook:
        $html = kwt_render_tours_grid( array( 'limit' => 6, '_query' => $query ), '' );

        $this->assertStringContainsString( 'Serengeti Safari', $html );
        $this->assertStringContainsString( 'TZS 450,000', $html );
        $this->assertStringContainsString( 'kwt-tours-grid', $html );
    }
}
```

Add the WP_Query stub in the same file's global namespace block at the bottom:

```php
namespace {
    if ( ! class_exists( 'WP_Query_Stub' ) ) {
        class WP_Query_Stub {
            private $ids; private $i = -1;
            public function __construct( array $ids ) { $this->ids = $ids; }
            public function have_posts(): bool { return $this->i + 1 < count( $this->ids ); }
            public function the_post(): void { $this->i++; }
            public function get_ids(): array { return $this->ids; }
        }
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter ToursGridRenderTest`
Expected: FAIL — `render.php` not found / function undefined.

- [ ] **Step 3: Write `blocks/tours-grid/block.json`**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "kwawingu/tours-grid",
  "title": "KwaWingu Tours Grid",
  "category": "widgets",
  "icon": "grid-view",
  "description": "A grid of your tours.",
  "textdomain": "kwawingu-tours",
  "attributes": {
    "limit": { "type": "number", "default": 12 },
    "type":  { "type": "string", "default": "" }
  },
  "supports": { "html": false, "align": ["wide", "full"] },
  "render": "file:./render.php"
}
```

- [ ] **Step 4: Write `blocks/tours-grid/render.php`**

```php
<?php
/**
 * Server render for kwawingu/tours-grid.
 *
 * @package KwaWingu\Tours
 */

use KwaWingu\Tours\View;

if ( ! function_exists( 'kwt_render_tours_grid' ) ) {
    /**
     * @param array<string,mixed> $attributes
     */
    function kwt_render_tours_grid( array $attributes, string $content = '' ): string {
        $limit = isset( $attributes['limit'] ) ? (int) $attributes['limit'] : 12;

        // Tests inject a query via _query; production builds one from View.
        $query = $attributes['_query'] ?? null;
        if ( null === $query ) {
            $args = array( 'posts_per_page' => $limit );
            if ( ! empty( $attributes['type'] ) ) {
                $args['meta_query'] = array( array( 'key' => 'kwt_type', 'value' => (string) $attributes['type'] ) );
            }
            $query = View::tour_query( $args );
        }

        if ( ! $query->have_posts() ) {
            return '<div class="kwt-tours-grid kwt-empty">' . esc_html__( 'No tours yet.', 'kwawingu-tours' ) . '</div>';
        }

        $out = '<div class="kwt-tours-grid">';
        while ( $query->have_posts() ) {
            $query->the_post();
            $id    = (int) get_the_ID();
            $price = (int) get_post_meta( $id, 'kwt_price', true );
            $days  = (int) get_post_meta( $id, 'kwt_duration_days', true );
            $img   = (string) get_the_post_thumbnail_url( $id, 'medium' );
            $out  .= '<article class="kwt-tour-card">';
            if ( $img ) {
                $out .= '<img class="kwt-tour-card__img" src="' . esc_url( $img ) . '" alt="' . esc_attr( get_the_title() ) . '" loading="lazy" />';
            }
            $out .= '<h3 class="kwt-tour-card__title"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h3>';
            if ( $days > 0 ) {
                /* translators: %d: number of days */
                $out .= '<p class="kwt-tour-card__meta">' . esc_html( sprintf( _n( '%d day', '%d days', $days, 'kwawingu-tours' ), $days ) ) . '</p>';
            }
            if ( $price > 0 ) {
                $out .= '<p class="kwt-tour-card__price">' . esc_html( View::money( $price ) ) . '</p>';
            }
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

Note: the test stubs `_n` implicitly? Add `Functions\when( '_n' )->alias( static fn( $s, $p, $n ) => $n === 1 ? $s : $p );` and `Functions\when( 'wp_reset_postdata' )->justReturn( null );` and `Functions\when( 'esc_html__' )->returnArg();` to the test `setUp()` — update the test file to include these stubs before Step 2's run.

- [ ] **Step 5: Run to pass + full suite**

Run: `vendor/bin/phpunit --filter ToursGridRenderTest` then `vendor/bin/phpunit`
Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add blocks/tours-grid/ tests/blocks/ToursGridRenderTest.php
git commit -m "feat(blocks): Tours Grid server-rendered block (v0.2 task 3)"
```

---

### Task 4: Tour Detail block

**Files:**
- Create: `blocks/tour-detail/block.json`
- Create: `blocks/tour-detail/render.php`
- Test: `tests/blocks/TourDetailRenderTest.php`

**Interfaces:**
- Produces: block `kwawingu/tour-detail`; `kwt_render_tour_detail( array $attributes, string $content ): string`. Renders the current/selected tour's title, cover, price, duration, difficulty, content. Uses `get_post_meta` + `get_post`.

- [ ] **Step 1: Write `tests/blocks/TourDetailRenderTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class TourDetailRenderTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        require_once dirname( __DIR__, 2 ) . '/blocks/tour-detail/render.php';
        foreach ( array( 'esc_html', 'esc_attr', 'esc_url', 'esc_html__' ) as $f ) {
            Functions\when( $f )->returnArg();
        }
        Functions\when( 'get_the_ID' )->justReturn( 7 );
        Functions\when( 'get_the_title' )->justReturn( 'Kilimanjaro Trek' );
        Functions\when( 'get_the_content' )->justReturn( 'Climb the roof of Africa.' );
        Functions\when( 'get_the_post_thumbnail_url' )->justReturn( 'https://img/kili.jpg' );
        Functions\when( 'wp_kses_post' )->returnArg();
        Functions\when( 'get_post_meta' )->alias( static function ( $id, $key, $single ) {
            $map = array( 'kwt_price' => 1200000, 'kwt_duration_days' => 7, 'kwt_difficulty' => 'Challenging' );
            return $map[ $key ] ?? '';
        } );
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_renders_detail_with_price_and_difficulty(): void {
        $html = kwt_render_tour_detail( array( 'postId' => 7 ), '' );
        $this->assertStringContainsString( 'Kilimanjaro Trek', $html );
        $this->assertStringContainsString( 'TZS 1,200,000', $html );
        $this->assertStringContainsString( 'Challenging', $html );
        $this->assertStringContainsString( 'kwt-tour-detail', $html );
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter TourDetailRenderTest`
Expected: FAIL — function undefined.

- [ ] **Step 3: Write `blocks/tour-detail/block.json`**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "kwawingu/tour-detail",
  "title": "KwaWingu Tour Detail",
  "category": "widgets",
  "icon": "location",
  "description": "Full detail for a single tour.",
  "textdomain": "kwawingu-tours",
  "attributes": { "postId": { "type": "number", "default": 0 } },
  "supports": { "html": false },
  "render": "file:./render.php"
}
```

- [ ] **Step 4: Write `blocks/tour-detail/render.php`**

```php
<?php
/**
 * Server render for kwawingu/tour-detail.
 *
 * @package KwaWingu\Tours
 */

use KwaWingu\Tours\View;

if ( ! function_exists( 'kwt_render_tour_detail' ) ) {
    /**
     * @param array<string,mixed> $attributes
     */
    function kwt_render_tour_detail( array $attributes, string $content = '' ): string {
        $id = ! empty( $attributes['postId'] ) ? (int) $attributes['postId'] : (int) get_the_ID();
        if ( $id <= 0 ) {
            return '';
        }
        $price      = (int) get_post_meta( $id, 'kwt_price', true );
        $days       = (int) get_post_meta( $id, 'kwt_duration_days', true );
        $difficulty = (string) get_post_meta( $id, 'kwt_difficulty', true );
        $img        = (string) get_the_post_thumbnail_url( $id, 'large' );

        $out = '<div class="kwt-tour-detail">';
        $out .= '<h1 class="kwt-tour-detail__title">' . esc_html( get_the_title() ) . '</h1>';
        if ( $img ) {
            $out .= '<img class="kwt-tour-detail__cover" src="' . esc_url( $img ) . '" alt="' . esc_attr( get_the_title() ) . '" />';
        }
        $out .= '<ul class="kwt-tour-detail__facts">';
        if ( $days > 0 ) {
            $out .= '<li>' . esc_html( sprintf( _n( '%d day', '%d days', $days, 'kwawingu-tours' ), $days ) ) . '</li>';
        }
        if ( '' !== $difficulty ) {
            $out .= '<li>' . esc_html( $difficulty ) . '</li>';
        }
        if ( $price > 0 ) {
            $out .= '<li class="kwt-price">' . esc_html( View::money( $price ) ) . '</li>';
        }
        $out .= '</ul>';
        $out .= '<div class="kwt-tour-detail__body">' . wp_kses_post( get_the_content() ) . '</div>';
        $booking = View::tour_booking_url( $id );
        if ( '' !== $booking ) {
            $out .= '<a class="kwt-book-btn" href="' . esc_url( $booking ) . '">' . esc_html__( 'Book this tour', 'kwawingu-tours' ) . '</a>';
        }
        $out .= '</div>';
        return $out;
    }
}
```

Add `Functions\when( '_n' )->alias( static fn( $s, $p, $n ) => 1 === $n ? $s : $p );` to the test `setUp()`.

- [ ] **Step 5: Run to pass + full suite**

Run: `vendor/bin/phpunit --filter TourDetailRenderTest` then `vendor/bin/phpunit`
Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add blocks/tour-detail/ tests/blocks/TourDetailRenderTest.php
git commit -m "feat(blocks): Tour Detail server-rendered block (v0.2 task 4)"
```

---

### Task 5: Booking helper + Book Button block

**Files:**
- Create: `includes/Booking.php`
- Create: `blocks/book-button/block.json`
- Create: `blocks/book-button/render.php`
- Test: `tests/BookingTest.php`, `tests/blocks/BookButtonRenderTest.php`

**Interfaces:**
- Consumes: `Settings::get_slug`, `Settings::get_booking_mode`, tour meta `kwt_slug`.
- Produces:
  - `Booking::__construct( Settings $settings )` and a static convenience `Booking::url_for( int $post_id ): string` that constructs a URL using a process-wide `Settings` (see note). To keep `View::tour_booking_url` static + simple, `Booking` exposes `Booking::url_for( int $post_id ): string` which reads a `Settings` instance it lazily creates.
  - Redirect URL: `https://tours.kwawingu.com/{slug}/tours/{tourSlug}` when booking mode is `redirect` (and, in v0.2, for `widget`/`onsite` too — they fall back to redirect). Returns `''` when slug or tour slug is missing.
  - Block `kwawingu/book-button`; `kwt_render_book_button( array $attributes, string $content ): string`.

- [ ] **Step 1: Write `tests/BookingTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Booking;
use PHPUnit\Framework\TestCase;

class BookingTest extends TestCase {
    protected function setUp(): void { parent::setUp(); Monkey\setUp(); }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_redirect_url_built_from_slugs(): void {
        Functions\when( 'get_option' )->justReturn( array( 'slug' => 'serengeti-tours', 'booking_mode' => 'redirect' ) );
        Functions\when( 'get_post_meta' )->justReturn( 'kilimanjaro' );
        $this->assertSame(
            'https://tours.kwawingu.com/serengeti-tours/tours/kilimanjaro',
            Booking::url_for( 7 )
        );
    }

    public function test_returns_empty_when_slug_missing(): void {
        Functions\when( 'get_option' )->justReturn( array( 'slug' => '' ) );
        Functions\when( 'get_post_meta' )->justReturn( 'kilimanjaro' );
        $this->assertSame( '', Booking::url_for( 7 ) );
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter BookingTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write `includes/Booking.php`**

```php
<?php
namespace KwaWingu\Tours;

/**
 * Builds booking links. v0.2 implements redirect mode (widget/onsite fall back
 * to redirect until v0.3/v0.4).
 */
class Booking {

    const HOSTED_BASE = 'https://tours.kwawingu.com';

    /** @var Settings */
    private $settings;

    public function __construct( Settings $settings ) {
        $this->settings = $settings;
    }

    /** Convenience: build the booking URL for a tour post. */
    public static function url_for( int $post_id ): string {
        return ( new self( new Settings() ) )->url( $post_id );
    }

    public function url( int $post_id ): string {
        $slug      = $this->settings->get_slug();
        $tour_slug = (string) get_post_meta( $post_id, 'kwt_slug', true );
        if ( '' === $slug || '' === $tour_slug ) {
            return '';
        }
        // All modes resolve to the hosted flow in v0.2.
        return self::HOSTED_BASE . '/' . rawurlencode( $slug ) . '/tours/' . rawurlencode( $tour_slug );
    }
}
```

- [ ] **Step 4: Run BookingTest to pass**

Run: `vendor/bin/phpunit --filter BookingTest`
Expected: PASS.

- [ ] **Step 5: Write `tests/blocks/BookButtonRenderTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class BookButtonRenderTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        require_once dirname( __DIR__, 2 ) . '/blocks/book-button/render.php';
        foreach ( array( 'esc_html', 'esc_attr', 'esc_url', 'esc_html__' ) as $f ) {
            Functions\when( $f )->returnArg();
        }
        Functions\when( 'get_the_ID' )->justReturn( 7 );
        Functions\when( 'get_option' )->justReturn( array( 'slug' => 'acme', 'booking_mode' => 'redirect' ) );
        Functions\when( 'get_post_meta' )->justReturn( 'safari' );
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_renders_anchor_to_hosted_booking(): void {
        $html = kwt_render_book_button( array( 'label' => 'Book now' ), '' );
        $this->assertStringContainsString( 'https://tours.kwawingu.com/acme/tours/safari', $html );
        $this->assertStringContainsString( 'Book now', $html );
        $this->assertStringContainsString( 'kwt-book-btn', $html );
    }
}
```

- [ ] **Step 6: Write `blocks/book-button/block.json`**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "kwawingu/book-button",
  "title": "KwaWingu Book Button",
  "category": "widgets",
  "icon": "cart",
  "description": "A booking button for the current tour.",
  "textdomain": "kwawingu-tours",
  "attributes": {
    "label":  { "type": "string", "default": "Book now" },
    "postId": { "type": "number", "default": 0 }
  },
  "supports": { "html": false },
  "render": "file:./render.php"
}
```

- [ ] **Step 7: Write `blocks/book-button/render.php`**

```php
<?php
/**
 * Server render for kwawingu/book-button.
 *
 * @package KwaWingu\Tours
 */

use KwaWingu\Tours\Booking;

if ( ! function_exists( 'kwt_render_book_button' ) ) {
    /**
     * @param array<string,mixed> $attributes
     */
    function kwt_render_book_button( array $attributes, string $content = '' ): string {
        $id    = ! empty( $attributes['postId'] ) ? (int) $attributes['postId'] : (int) get_the_ID();
        $label = isset( $attributes['label'] ) && '' !== $attributes['label']
            ? (string) $attributes['label']
            : __( 'Book now', 'kwawingu-tours' );
        $url   = Booking::url_for( $id );
        if ( '' === $url ) {
            return '';
        }
        return '<a class="kwt-book-btn" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
    }
}
```

- [ ] **Step 8: Run to pass + full suite**

Run: `vendor/bin/phpunit --filter BookButtonRenderTest` then `vendor/bin/phpunit`
Expected: all PASS. (Note: `View::tour_booking_url` from Task 2 now resolves to `Booking::url_for` because the class exists — verify Tour Detail still renders its book link.)

- [ ] **Step 9: Commit**

```bash
git add includes/Booking.php blocks/book-button/ tests/BookingTest.php tests/blocks/BookButtonRenderTest.php
git commit -m "feat(booking): Booking URL helper + Book Button block (redirect mode) (v0.2 task 5)"
```

---

### Task 6: Featured Tours block

**Files:**
- Create: `blocks/featured-tours/block.json`
- Create: `blocks/featured-tours/render.php`
- Test: `tests/blocks/FeaturedToursRenderTest.php`

**Interfaces:**
- Produces: block `kwawingu/featured-tours`; `kwt_render_featured_tours( array $attributes, string $content ): string`. Reuses the Tours Grid rendering with a small count (default 3) + an optional heading. To stay DRY, it delegates to `kwt_render_tours_grid` after requiring that file.

- [ ] **Step 1: Write `tests/blocks/FeaturedToursRenderTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class FeaturedToursRenderTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        require_once dirname( __DIR__, 2 ) . '/blocks/tours-grid/render.php';
        require_once dirname( __DIR__, 2 ) . '/blocks/featured-tours/render.php';
        foreach ( array( 'esc_html', 'esc_attr', 'esc_url', 'esc_html__' ) as $f ) {
            Functions\when( $f )->returnArg();
        }
        Functions\when( '_n' )->alias( static fn( $s, $p, $n ) => 1 === $n ? $s : $p );
        Functions\when( 'wp_reset_postdata' )->justReturn( null );
        Functions\when( 'get_the_ID' )->justReturn( 7 );
        Functions\when( 'get_the_title' )->justReturn( 'Zanzibar Beach' );
        Functions\when( 'get_permalink' )->justReturn( 'https://site/tours/zanzibar/' );
        Functions\when( 'get_the_post_thumbnail_url' )->justReturn( '' );
        Functions\when( 'get_post_meta' )->justReturn( 0 );
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_renders_heading_and_delegates_to_grid(): void {
        $query = new \WP_Query_Stub( array( 7 ) );
        $html  = kwt_render_featured_tours( array( 'heading' => 'Popular trips', '_query' => $query ), '' );
        $this->assertStringContainsString( 'Popular trips', $html );
        $this->assertStringContainsString( 'Zanzibar Beach', $html );
        $this->assertStringContainsString( 'kwt-featured', $html );
    }
}
```

(The `WP_Query_Stub` is defined in `ToursGridRenderTest.php`'s global namespace block; PHPUnit loads all test files, so it is available. If run in isolation it is re-declared safely by the `class_exists` guard.)

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter FeaturedToursRenderTest`
Expected: FAIL — function undefined.

- [ ] **Step 3: Write `blocks/featured-tours/block.json`**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "kwawingu/featured-tours",
  "title": "KwaWingu Featured Tours",
  "category": "widgets",
  "icon": "star-filled",
  "description": "A short, highlighted set of tours with a heading.",
  "textdomain": "kwawingu-tours",
  "attributes": {
    "heading": { "type": "string", "default": "Featured tours" },
    "limit":   { "type": "number", "default": 3 }
  },
  "supports": { "html": false, "align": ["wide", "full"] },
  "render": "file:./render.php"
}
```

- [ ] **Step 4: Write `blocks/featured-tours/render.php`**

```php
<?php
/**
 * Server render for kwawingu/featured-tours. Delegates to the Tours Grid renderer.
 *
 * @package KwaWingu\Tours
 */

if ( ! function_exists( 'kwt_render_featured_tours' ) ) {
    /**
     * @param array<string,mixed> $attributes
     */
    function kwt_render_featured_tours( array $attributes, string $content = '' ): string {
        require_once __DIR__ . '/../tours-grid/render.php';
        $heading = isset( $attributes['heading'] ) ? (string) $attributes['heading'] : __( 'Featured tours', 'kwawingu-tours' );
        $limit   = isset( $attributes['limit'] ) ? (int) $attributes['limit'] : 3;

        $grid_attrs = array( 'limit' => $limit );
        if ( isset( $attributes['_query'] ) ) {
            $grid_attrs['_query'] = $attributes['_query'];
        }
        $grid = kwt_render_tours_grid( $grid_attrs, '' );

        $out = '<section class="kwt-featured">';
        if ( '' !== $heading ) {
            $out .= '<h2 class="kwt-featured__heading">' . esc_html( $heading ) . '</h2>';
        }
        $out .= $grid . '</section>';
        return $out;
    }
}
```

- [ ] **Step 5: Run to pass + full suite**

Run: `vendor/bin/phpunit --filter FeaturedToursRenderTest` then `vendor/bin/phpunit`
Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add blocks/featured-tours/ tests/blocks/FeaturedToursRenderTest.php
git commit -m "feat(blocks): Featured Tours block (delegates to grid) (v0.2 task 6)"
```

---

### Task 7: Shortcode bridges

**Files:**
- Create: `includes/Shortcodes.php`
- Modify: `includes/Plugin.php` (register `Shortcodes` in `boot()`)
- Test: `tests/ShortcodesTest.php`

**Interfaces:**
- Consumes: the block render functions (`kwt_render_tours_grid`, `kwt_render_tour_detail`, `kwt_render_book_button`, `kwt_render_featured_tours`).
- Produces: `Shortcodes::register(): void` registering `[kwawingu_tours]`, `[kwawingu_tour]`, `[kwawingu_booking]`, `[kwawingu_featured]`, each mapping atts → the matching render function.

- [ ] **Step 1: Write `tests/ShortcodesTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Shortcodes;
use PHPUnit\Framework\TestCase;

class ShortcodesTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\when( 'shortcode_atts' )->alias( static function ( $defaults, $atts ) {
            return array_merge( $defaults, (array) $atts );
        } );
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_register_adds_all_shortcodes(): void {
        $registered = array();
        Functions\when( 'add_shortcode' )->alias( static function ( $tag, $cb ) use ( &$registered ) {
            $registered[] = $tag;
        } );
        ( new Shortcodes() )->register();
        $this->assertContains( 'kwawingu_tours', $registered );
        $this->assertContains( 'kwawingu_tour', $registered );
        $this->assertContains( 'kwawingu_booking', $registered );
        $this->assertContains( 'kwawingu_featured', $registered );
    }

    public function test_tours_shortcode_maps_limit_attribute(): void {
        require_once dirname( __DIR__ ) . '/blocks/tours-grid/render.php';
        // Stub the render deps so the callback runs without a real query.
        Functions\when( 'esc_html' )->returnArg();
        Functions\when( 'esc_html__' )->returnArg();
        $sc = new Shortcodes();
        // No tours -> empty grid markup; we only assert the wrapper is produced.
        Functions\when( 'KwaWingu\\Tours\\View::tour_query' );
        $html = $sc->render_tours( array( 'limit' => '4' ) );
        $this->assertIsString( $html );
    }
}
```

Note: `render_tours` must tolerate the absence of a real `WP_Query`. Since building one calls `new \WP_Query`, provide a `WP_Query` stub in the test global namespace (reuse the pattern) OR have the shortcode accept the injected `_query` only in tests. Simplest: in `test_tours_shortcode_maps_limit_attribute`, define a global `WP_Query` class stub returning no posts:

```php
namespace {
    if ( ! class_exists( 'WP_Query' ) ) {
        class WP_Query { public function __construct( $a = array() ) {} public function have_posts() { return false; } public function the_post() {} }
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter ShortcodesTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write `includes/Shortcodes.php`**

```php
<?php
namespace KwaWingu\Tours;

/**
 * Classic-theme shortcode bridges that reuse the block render callbacks.
 */
class Shortcodes {

    public function register(): void {
        add_shortcode( 'kwawingu_tours', array( $this, 'render_tours' ) );
        add_shortcode( 'kwawingu_tour', array( $this, 'render_tour' ) );
        add_shortcode( 'kwawingu_booking', array( $this, 'render_booking' ) );
        add_shortcode( 'kwawingu_featured', array( $this, 'render_featured' ) );
    }

    /** @param array<string,mixed> $atts */
    public function render_tours( $atts ): string {
        require_once Blocks::block_dir() . 'tours-grid/render.php';
        $atts = shortcode_atts( array( 'limit' => 12, 'type' => '' ), $atts );
        return kwt_render_tours_grid( array( 'limit' => (int) $atts['limit'], 'type' => (string) $atts['type'] ), '' );
    }

    /** @param array<string,mixed> $atts */
    public function render_tour( $atts ): string {
        require_once Blocks::block_dir() . 'tour-detail/render.php';
        $atts = shortcode_atts( array( 'id' => 0 ), $atts );
        return kwt_render_tour_detail( array( 'postId' => (int) $atts['id'] ), '' );
    }

    /** @param array<string,mixed> $atts */
    public function render_booking( $atts ): string {
        require_once Blocks::block_dir() . 'book-button/render.php';
        $atts = shortcode_atts( array( 'id' => 0, 'label' => '' ), $atts );
        return kwt_render_book_button( array( 'postId' => (int) $atts['id'], 'label' => (string) $atts['label'] ), '' );
    }

    /** @param array<string,mixed> $atts */
    public function render_featured( $atts ): string {
        require_once Blocks::block_dir() . 'featured-tours/render.php';
        $atts = shortcode_atts( array( 'heading' => '', 'limit' => 3 ), $atts );
        return kwt_render_featured_tours( array( 'heading' => (string) $atts['heading'], 'limit' => (int) $atts['limit'] ), '' );
    }
}
```

- [ ] **Step 4: Wire into `includes/Plugin.php`** — inside `boot()`, after the `Blocks` registration line:

```php
        ( new Shortcodes() )->register();
```

- [ ] **Step 5: Run to pass + full suite**

Run: `vendor/bin/phpunit --filter ShortcodesTest` then `vendor/bin/phpunit`
Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add includes/Shortcodes.php includes/Plugin.php tests/ShortcodesTest.php
git commit -m "feat(blocks): shortcode bridges for classic themes (v0.2 task 7)"
```

---

### Task 8: Block patterns

**Files:**
- Create: `includes/Patterns.php`
- Modify: `includes/Plugin.php` (register `Patterns` in `boot()`)
- Test: `tests/PatternsTest.php`

**Interfaces:**
- Produces: `Patterns::register(): void` (hooks `init`), `Patterns::init(): void` registering a pattern category `kwawingu` + patterns `kwawingu/home`, `kwawingu/tours`, `kwawingu/tour-detail`, `kwawingu/about`, `kwawingu/contact`. `Patterns::PATTERNS` = list of slugs (used by the Importer in Task 10). Each pattern's `content` is block markup composed from the v0.2 blocks + core blocks.

- [ ] **Step 1: Write `tests/PatternsTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Patterns;
use PHPUnit\Framework\TestCase;

class PatternsTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\when( '__' )->returnArg();
        Functions\when( 'esc_html__' )->returnArg();
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_register_hooks_init(): void {
        ( new Patterns() )->register();
        $this->assertNotFalse( has_action( 'init' ) );
    }

    public function test_init_registers_category_and_patterns(): void {
        Functions\expect( 'register_block_pattern_category' )->atLeast()->once();
        $registered = array();
        Functions\when( 'register_block_pattern' )->alias( static function ( $slug, $args ) use ( &$registered ) {
            $registered[] = $slug;
        } );
        ( new Patterns() )->init();
        $this->assertContains( 'kwawingu/home', $registered );
        $this->assertContains( 'kwawingu/tours', $registered );
        $this->assertContains( 'kwawingu/contact', $registered );
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter PatternsTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write `includes/Patterns.php`**

```php
<?php
namespace KwaWingu\Tours;

/**
 * Registers block patterns used to scaffold the starter site.
 */
class Patterns {

    const CATEGORY = 'kwawingu';

    /** Slugs the Importer turns into pages: slug => page title. */
    const PAGES = array(
        'kwawingu/home'        => 'Home',
        'kwawingu/tours'       => 'Tours',
        'kwawingu/about'       => 'About',
        'kwawingu/contact'     => 'Contact',
    );

    public function register(): void {
        add_action( 'init', array( $this, 'init' ) );
    }

    public function init(): void {
        register_block_pattern_category( self::CATEGORY, array( 'label' => __( 'KwaWingu Tours', 'kwawingu-tours' ) ) );

        $this->add( 'kwawingu/home', __( 'Home', 'kwawingu-tours' ),
            '<!-- wp:heading {"level":1} --><h1>' . esc_html__( 'Explore our tours', 'kwawingu-tours' ) . '</h1><!-- /wp:heading -->'
            . '<!-- wp:kwawingu/featured-tours {"heading":"Featured tours","limit":3} /-->'
        );
        $this->add( 'kwawingu/tours', __( 'Tours', 'kwawingu-tours' ),
            '<!-- wp:heading --><h2>' . esc_html__( 'All tours', 'kwawingu-tours' ) . '</h2><!-- /wp:heading -->'
            . '<!-- wp:kwawingu/tours-grid {"limit":24} /-->'
        );
        $this->add( 'kwawingu/tour-detail', __( 'Tour detail', 'kwawingu-tours' ),
            '<!-- wp:kwawingu/tour-detail /-->'
        );
        $this->add( 'kwawingu/about', __( 'About', 'kwawingu-tours' ),
            '<!-- wp:heading --><h2>' . esc_html__( 'About us', 'kwawingu-tours' ) . '</h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>' . esc_html__( 'Tell your story here.', 'kwawingu-tours' ) . '</p><!-- /wp:paragraph -->'
        );
        $this->add( 'kwawingu/contact', __( 'Contact', 'kwawingu-tours' ),
            '<!-- wp:heading --><h2>' . esc_html__( 'Contact us', 'kwawingu-tours' ) . '</h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>' . esc_html__( 'Add your contact details or a form here.', 'kwawingu-tours' ) . '</p><!-- /wp:paragraph -->'
        );
    }

    private function add( string $slug, string $title, string $content ): void {
        register_block_pattern( $slug, array(
            'title'      => $title,
            'categories' => array( self::CATEGORY ),
            'content'    => $content,
        ) );
    }
}
```

- [ ] **Step 4: Wire into `includes/Plugin.php`** — inside `boot()`, after `Shortcodes`:

```php
        ( new Patterns() )->register();
```

- [ ] **Step 5: Run to pass + full suite**

Run: `vendor/bin/phpunit --filter PatternsTest` then `vendor/bin/phpunit`
Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add includes/Patterns.php includes/Plugin.php tests/PatternsTest.php
git commit -m "feat(patterns): starter-site block patterns + category (v0.2 task 8)"
```

---

### Task 9: Branding (auto-brand from /profile)

**Files:**
- Create: `includes/Branding.php`
- Test: `tests/BrandingTest.php`

**Interfaces:**
- Consumes: `Api_Client::get( '/profile' )`.
- Produces:
  - `Branding::__construct( Api_Client $api )`.
  - `Branding::apply(): array` — fetches `/profile`, stores `kwt_brand` option `array{ name, logo, primary, accent, description }` (sanitized), sets the site logo (custom-logo) when a logo URL is present and importable, and returns the stored brand array. On `Api_Exception`, returns `array()` and does not throw.
  - `Branding::css_vars(): string` — a `<style>` string exposing `--kwt-primary` / `--kwt-accent` from the stored brand (empty when unset). Hooked to `wp_head` by `Plugin` in a later phase; in v0.2 the wizard calls `apply()` and the CSS is emitted via a `register()` that hooks `wp_head`.
  - `Branding::register(): void` — hooks `wp_head` → prints `css_vars()`.

- [ ] **Step 1: Write `tests/BrandingTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Api_Client;
use KwaWingu\Tours\Branding;
use Mockery;
use PHPUnit\Framework\TestCase;

class BrandingTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'esc_url_raw' )->returnArg();
        Functions\when( 'sanitize_hex_color' )->returnArg();
    }
    protected function tearDown(): void { Monkey\tearDown(); Mockery::close(); parent::tearDown(); }

    public function test_apply_stores_brand_from_profile(): void {
        $api = Mockery::mock( Api_Client::class );
        $api->shouldReceive( 'get' )->with( '/profile' )->andReturn( array(
            'name' => 'Serengeti Tours', 'logoUrl' => 'https://img/logo.png',
            'brandPrimary' => '#0a4a3a', 'brandAccent' => '#e8920a', 'description' => 'Safaris.',
        ) );
        $stored = array();
        Functions\when( 'update_option' )->alias( static function ( $k, $v ) use ( &$stored ) { $stored[ $k ] = $v; return true; } );

        $brand = ( new Branding( $api ) )->apply();
        $this->assertSame( 'Serengeti Tours', $brand['name'] );
        $this->assertSame( '#0a4a3a', $brand['primary'] );
        $this->assertSame( '#0a4a3a', $stored['kwt_brand']['primary'] );
    }

    public function test_apply_returns_empty_on_api_error(): void {
        $api = Mockery::mock( Api_Client::class );
        $api->shouldReceive( 'get' )->andThrow( new \KwaWingu\Tours\Api_Exception( 'fail', 500 ) );
        $this->assertSame( array(), ( new Branding( $api ) )->apply() );
    }

    public function test_css_vars_emits_custom_properties(): void {
        Functions\when( 'get_option' )->justReturn( array( 'primary' => '#0a4a3a', 'accent' => '#e8920a' ) );
        $css = ( new Branding( Mockery::mock( Api_Client::class ) ) )->css_vars();
        $this->assertStringContainsString( '--kwt-primary:#0a4a3a', $css );
        $this->assertStringContainsString( '--kwt-accent:#e8920a', $css );
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter BrandingTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write `includes/Branding.php`**

```php
<?php
namespace KwaWingu\Tours;

/**
 * Pulls operator branding from GET /profile and applies it to the site.
 */
class Branding {

    const OPTION = 'kwt_brand';

    /** @var Api_Client */
    private $api;

    public function __construct( Api_Client $api ) {
        $this->api = $api;
    }

    public function register(): void {
        add_action( 'wp_head', function () {
            echo $this->css_vars(); // phpcs:ignore WordPress.Security.EscapeOutput -- css_vars() builds an escaped <style> block.
        } );
    }

    /** @return array<string,string> */
    public function apply(): array {
        try {
            $profile = $this->api->get( '/profile' );
        } catch ( Api_Exception $e ) {
            return array();
        }
        $brand = array(
            'name'        => sanitize_text_field( (string) ( $profile['name'] ?? '' ) ),
            'logo'        => esc_url_raw( (string) ( $profile['logoUrl'] ?? '' ) ),
            'primary'     => (string) sanitize_hex_color( (string) ( $profile['brandPrimary'] ?? '' ) ),
            'accent'      => (string) sanitize_hex_color( (string) ( $profile['brandAccent'] ?? '' ) ),
            'description' => sanitize_text_field( (string) ( $profile['description'] ?? '' ) ),
        );
        update_option( self::OPTION, $brand );
        return $brand;
    }

    public function css_vars(): string {
        $brand   = get_option( self::OPTION, array() );
        $primary = is_array( $brand ) ? (string) ( $brand['primary'] ?? '' ) : '';
        $accent  = is_array( $brand ) ? (string) ( $brand['accent'] ?? '' ) : '';
        if ( '' === $primary && '' === $accent ) {
            return '';
        }
        $css = ':root{';
        if ( '' !== $primary ) {
            $css .= '--kwt-primary:' . $primary . ';';
        }
        if ( '' !== $accent ) {
            $css .= '--kwt-accent:' . $accent . ';';
        }
        $css .= '}';
        return '<style id="kwt-brand">' . $css . '</style>';
    }
}
```

Note: `css_vars()` only ever interpolates values that passed `sanitize_hex_color` (so they are `#rrggbb` or empty) — safe to echo.

- [ ] **Step 4: Run to pass + full suite**

Run: `vendor/bin/phpunit --filter BrandingTest` then `vendor/bin/phpunit`
Expected: all PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/Branding.php tests/BrandingTest.php
git commit -m "feat(branding): auto-brand from /profile + CSS custom properties (v0.2 task 9)"
```

---

### Task 10: Importer (scaffold the starter site)

**Files:**
- Create: `includes/Importer.php`
- Test: `tests/ImporterTest.php`

**Interfaces:**
- Consumes: `Patterns::PAGES`.
- Produces:
  - `Importer::run(): array` — for each entry in `Patterns::PAGES`, creates a WP page whose `post_content` is the pattern content (only if a page with that `kwt_pattern` meta does not already exist), records the created IDs, sets the `kwawingu/home` page as the static front page (`show_on_front=page`, `page_on_front`), and returns `array{ created:int[], front:int }`. Idempotent (re-running does not duplicate pages).
  - `Importer::META` = `'kwt_pattern'` (marks pages the importer created, storing the pattern slug).

- [ ] **Step 1: Write `tests/ImporterTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Importer;
use PHPUnit\Framework\TestCase;

class ImporterTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_strip_all_tags' )->returnArg();
        Functions\when( 'update_post_meta' )->justReturn( true );
        Functions\when( 'update_option' )->justReturn( true );
        // No existing importer pages.
        Functions\when( 'get_posts' )->justReturn( array() );
        // Pattern registry returns known content by slug.
        Functions\when( 'WP_Block_Patterns_Registry' ); // not used; content comes from Patterns::PAGES via registry stub below
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_run_creates_a_page_per_pattern_and_sets_front(): void {
        $created = array();
        Functions\when( 'wp_insert_post' )->alias( static function ( $args ) use ( &$created ) {
            $created[] = $args;
            return count( $created ) + 100; // 101, 102, ...
        } );
        $front = null;
        Functions\when( 'update_option' )->alias( static function ( $k, $v ) use ( &$front ) {
            if ( 'page_on_front' === $k ) { $front = $v; }
            return true;
        } );

        $out = ( new Importer() )->run();

        $this->assertCount( count( \KwaWingu\Tours\Patterns::PAGES ), $out['created'] );
        $this->assertSame( 'page', $created[0]['post_type'] );
        $this->assertGreaterThan( 0, $out['front'] );
        $this->assertSame( $out['front'], $front );
    }

    public function test_run_is_idempotent(): void {
        // A page for every pattern already exists.
        Functions\when( 'get_posts' )->justReturn( array( 55 ) );
        Functions\expect( 'wp_insert_post' )->never();
        $out = ( new Importer() )->run();
        $this->assertSame( array(), $out['created'] );
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter ImporterTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write `includes/Importer.php`**

```php
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
```

Note: in the unit test the registry class is absent, so `pattern_content` returns `''` — fine, the test asserts page creation + front page, not content. In production the patterns from Task 8 are registered on `init` before the wizard runs.

- [ ] **Step 4: Run to pass + full suite**

Run: `vendor/bin/phpunit --filter ImporterTest` then `vendor/bin/phpunit`
Expected: all PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/Importer.php tests/ImporterTest.php
git commit -m "feat(importer): scaffold starter pages from patterns + set front page (v0.2 task 10)"
```

---

### Task 11: Setup Wizard

**Files:**
- Create: `includes/Setup_Wizard.php`
- Modify: `includes/Plugin.php` (register `Setup_Wizard` in `boot()`, passing the deps)
- Test: `tests/SetupWizardTest.php`

**Interfaces:**
- Consumes: `Settings`, `Api_Client`, `Branding`, `Importer`, `Sync`.
- Produces:
  - `Setup_Wizard::__construct( Settings $settings, Branding $branding, Importer $importer, Sync $sync )`.
  - `Setup_Wizard::register(): void` — adds a hidden admin page (`add_submenu_page` under the KwaWingu settings, or `add_dashboard_page`) `kwawingu-setup` + registers `admin_post_kwt_setup_scaffold`.
  - `Setup_Wizard::handle_scaffold(): void` — capability + nonce checks, then runs `Branding::apply()` + `Importer::run()` + `Sync::run()` and redirects back with a done flag.

- [ ] **Step 1: Write `tests/SetupWizardTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Branding;
use KwaWingu\Tours\Importer;
use KwaWingu\Tours\Settings;
use KwaWingu\Tours\Setup_Wizard;
use KwaWingu\Tours\Sync;
use Mockery;
use PHPUnit\Framework\TestCase;

class SetupWizardTest extends TestCase {
    protected function setUp(): void { parent::setUp(); Monkey\setUp(); }
    protected function tearDown(): void { Monkey\tearDown(); Mockery::close(); parent::tearDown(); }

    private function wizard( $branding, $importer, $sync ): Setup_Wizard {
        return new Setup_Wizard( new Settings(), $branding, $importer, $sync );
    }

    public function test_register_hooks_menu_and_admin_post(): void {
        $w = $this->wizard(
            Mockery::mock( Branding::class ), Mockery::mock( Importer::class ), Mockery::mock( Sync::class )
        );
        $w->register();
        $this->assertNotFalse( has_action( 'admin_menu' ) );
        $this->assertNotFalse( has_action( 'admin_post_kwt_setup_scaffold' ) );
    }

    public function test_scaffold_runs_branding_importer_sync(): void {
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( 'check_admin_referer' )->justReturn( true );
        Functions\when( 'wp_safe_redirect' )->justReturn( true );
        Functions\when( 'admin_url' )->returnArg();
        Functions\when( 'add_query_arg' )->justReturn( 'x' );

        $branding = Mockery::mock( Branding::class );
        $branding->shouldReceive( 'apply' )->once()->andReturn( array( 'name' => 'X' ) );
        $importer = Mockery::mock( Importer::class );
        $importer->shouldReceive( 'run' )->once()->andReturn( array( 'created' => array( 1 ), 'front' => 1 ) );
        $sync = Mockery::mock( Sync::class );
        $sync->shouldReceive( 'run' )->once()->andReturn( array( 'created' => 2, 'updated' => 0, 'unpublished' => 0, 'errors' => array() ) );

        // wp_safe_redirect + exit: intercept exit via a thrown marker.
        $this->expectException( \KwaWingu\Tours\Tests\WizardExit::class );
        $w = $this->wizard( $branding, $importer, $sync );
        $w->handle_scaffold();
    }
}

namespace KwaWingu\Tours\Tests {
    class WizardExit extends \RuntimeException {}
}
```

Note: to make `handle_scaffold()` testable without a real `exit`, the implementation calls a protected `terminate()` that the test overrides — OR wrap `exit` so it is not literally called under test. Use the approach below: `handle_scaffold()` calls `$this->finish()` which does `wp_safe_redirect(...)` then `$this->terminate();`, and `terminate()` calls `exit;`. In the test, subclass to throw `WizardExit` from `terminate()`. Adjust the test to use an anonymous subclass:

```php
        $w = new class( new Settings(), $branding, $importer, $sync ) extends Setup_Wizard {
            protected function terminate(): void { throw new \KwaWingu\Tours\Tests\WizardExit(); }
        };
        $w->handle_scaffold();
```

Replace the `$w = $this->wizard(...)` + `handle_scaffold()` lines in `test_scaffold_runs_branding_importer_sync` with the anonymous subclass above.

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter SetupWizardTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write `includes/Setup_Wizard.php`**

```php
<?php
namespace KwaWingu\Tours;

/**
 * One-click setup: connect (in Settings), then auto-brand + scaffold pages + first sync.
 */
class Setup_Wizard {

    const ACTION = 'kwt_setup_scaffold';

    /** @var Settings */  private $settings;
    /** @var Branding */  private $branding;
    /** @var Importer */  private $importer;
    /** @var Sync */      private $sync;

    public function __construct( Settings $settings, Branding $branding, Importer $importer, Sync $sync ) {
        $this->settings = $settings;
        $this->branding = $branding;
        $this->importer = $importer;
        $this->sync     = $sync;
    }

    public function register(): void {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_scaffold' ) );
    }

    public function add_menu(): void {
        add_submenu_page(
            'options-general.php',
            __( 'Set up your tour site', 'kwawingu-tours' ),
            __( 'KwaWingu Setup', 'kwawingu-tours' ),
            'manage_options',
            'kwawingu-setup',
            array( $this, 'render' )
        );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $connected = '' !== $this->settings->get_slug() && '' !== $this->settings->get_public_key();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Set up your tour site', 'kwawingu-tours' ); ?></h1>
            <?php if ( ! $connected ) : ?>
                <p><?php echo esc_html__( 'First connect your KwaWingu account under Settings → KwaWingu Tours (operator slug + public API key). API access is a paid add-on on your KwaWingu dashboard.', 'kwawingu-tours' ); ?></p>
            <?php else : ?>
                <p><?php echo esc_html__( 'This will pull your branding, create your starter pages (Home, Tours, About, Contact), set your home page, and import your tours. You can edit everything afterwards.', 'kwawingu-tours' ); ?></p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
                    <?php wp_nonce_field( self::ACTION ); ?>
                    <?php submit_button( __( 'Build my site', 'kwawingu-tours' ) ); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handle_scaffold(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to do this.', 'kwawingu-tours' ) );
        }
        check_admin_referer( self::ACTION );

        $this->branding->apply();
        $this->importer->run();
        $this->sync->run();

        wp_safe_redirect( add_query_arg(
            array( 'page' => 'kwawingu-setup', 'kwt_done' => '1' ),
            admin_url( 'options-general.php' )
        ) );
        $this->terminate();
    }

    /** Seam so tests can intercept the terminal exit. */
    protected function terminate(): void {
        exit;
    }
}
```

- [ ] **Step 4: Wire into `includes/Plugin.php`** — in `boot()`, after the objects exist (you already build `$settings`, `$api`, `$sync`), add a `Branding`, `Importer`, and `Setup_Wizard`:

```php
        $branding = new Branding( $api );
        $branding->register();
        $importer = new Importer();
        ( new Setup_Wizard( $settings, $branding, $importer, $sync ) )->register();
        ( new Patterns() )->register();      // if not already added in Task 8 ordering
```

Ensure `Patterns`, `Blocks`, `Shortcodes` registrations from earlier tasks remain present; the final `boot()` registers: Settings, Cpt, Blocks, Shortcodes, Patterns, Branding, Setup_Wizard, Sync_Controller, Admin_Page. (Order: Settings first; Cpt/Blocks/Patterns before anything that renders; Branding->register hooks wp_head; Setup_Wizard + Admin_Page add menus.)

- [ ] **Step 5: Run to pass + full suite**

Run: `vendor/bin/phpunit --filter SetupWizardTest` then `vendor/bin/phpunit`
Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add includes/Setup_Wizard.php includes/Plugin.php tests/SetupWizardTest.php
git commit -m "feat(wizard): one-click setup — auto-brand + scaffold + first sync (v0.2 task 11)"
```

---

### Task 12: v0.2 documentation

**Files:**
- Create: `README.md`
- Create: `docs/getting-started.md`
- Create: `docs/blocks.md`
- Create: `docs/booking-modes.md`
- Modify: `readme.txt` (expand description + changelog `= 0.2.0 =`)
- Modify: `kwawingu-tours.php` (bump `Version` header to `0.2.0`), `includes/Plugin.php` (`VERSION = '0.2.0'`), `readme.txt` (`Stable tag: 0.2.0`)

**Interfaces:** none (docs + version bump).

- [ ] **Step 1: Write `README.md`** (GitHub front page)

```markdown
# KwaWingu Tours for WordPress

Build a fast, SEO-friendly tour-operator website on your [KwaWingu Tours](https://tours.kwawingu.com) data. This plugin syncs your tour catalog into native WordPress content, gives you blocks + a one-click setup wizard, and gets you a live site in minutes.

> **Requires a paid KwaWingu Developer API add-on.** The plugin is free and GPL-licensed; it connects to your own KwaWingu account using your operator slug + API key. Enable API access in your KwaWingu dashboard (Developer API).

## Features

- **Native content sync** — your tours become a `Tour` custom post type: real URLs, editable in WordPress, great for SEO.
- **Blocks** — Tours Grid, Tour Detail, Featured Tours, Book Button (+ classic-theme shortcodes).
- **One-click setup** — the wizard pulls your branding, scaffolds Home / Tours / About / Contact pages, and imports your tours.
- **Booking** — send guests to your hosted KwaWingu booking flow (redirect mode). Widget + on-site modes are on the roadmap.
- **Keeps your edits** — once you edit a synced tour, sync stops overwriting your content.

## Install

**From WordPress.org:** search "KwaWingu Tours" in Plugins → Add New. (Coming soon.)

**From source:**
```bash
git clone https://github.com/KwaWingu/kw-wp-plugin.git wp-content/plugins/kwawingu-tours
cd wp-content/plugins/kwawingu-tours && composer install --no-dev
```
Then activate the plugin in WordPress.

## Configure

1. **Settings → KwaWingu Tours** — enter your operator slug + public API key, choose a booking mode, save.
2. **Settings → KwaWingu Setup** — click **Build my site**.
3. Visit your site.

See [docs/getting-started.md](docs/getting-started.md).

## Blocks & shortcodes

| Block | Shortcode | Purpose |
|---|---|---|
| KwaWingu Tours Grid | `[kwawingu_tours limit="12" type=""]` | Grid of tours |
| KwaWingu Tour Detail | `[kwawingu_tour id="0"]` | Single tour |
| KwaWingu Featured Tours | `[kwawingu_featured heading="" limit="3"]` | Highlighted set |
| KwaWingu Book Button | `[kwawingu_booking id="0" label=""]` | Booking link |

Full reference: [docs/blocks.md](docs/blocks.md).

## Documentation

- [Getting started](docs/getting-started.md)
- [Blocks & shortcodes](docs/blocks.md)
- [Booking modes](docs/booking-modes.md)

## Contributing

PRs welcome. Run tests with `composer install && vendor/bin/phpunit`. Coding standard: `vendor/bin/phpcs`. Please keep changes covered by tests.

## License

GPL-2.0-or-later.
```

- [ ] **Step 2: Write `docs/getting-started.md`**

```markdown
# Getting started

## 1. Enable API access (paid add-on)

The plugin reads your data through the KwaWingu Developer API, a paid per-operator add-on. In your KwaWingu dashboard, open **Developer API** and enable access, then copy your **operator slug** and **public API key**.

## 2. Connect

In WordPress: **Settings → KwaWingu Tours**. Paste your slug + public key, choose a **booking mode** (start with *Redirect*), and save. Use **Sync now** to pull your tours immediately, or let the scheduled sync run.

## 3. Build your site

**Settings → KwaWingu Setup → Build my site.** This will:

- pull your branding (logo + colours) from your KwaWingu profile,
- create starter pages (Home, Tours, About, Contact) and set your home page,
- import your tours.

Everything is normal WordPress content afterwards — edit freely. Once you edit a tour, future syncs won't overwrite your text (they still refresh price, photos, and the booking link).

## 4. Keep it in sync

Tours re-sync automatically (hourly by default; change the interval in Settings). Removed tours are set to Draft, never deleted.
```

- [ ] **Step 3: Write `docs/blocks.md`**

```markdown
# Blocks & shortcodes

All blocks are server-rendered from your synced tours — no JavaScript needed to display them, and they're crawlable for SEO.

## Tours Grid — `kwawingu/tours-grid` / `[kwawingu_tours]`
A responsive grid of tours.
- `limit` (number, default 12)
- `type` (string) — filter by tour type (e.g. `safari`)

## Tour Detail — `kwawingu/tour-detail` / `[kwawingu_tour]`
Full detail for one tour (cover, facts, description, book button).
- `postId` / `id` (number) — the tour to show; defaults to the current post in a tour template.

## Featured Tours — `kwawingu/featured-tours` / `[kwawingu_featured]`
A short highlighted set with a heading.
- `heading` (string)
- `limit` (number, default 3)

## Book Button — `kwawingu/book-button` / `[kwawingu_booking]`
A booking link/button for a tour.
- `postId` / `id` (number)
- `label` (string, default "Book now")

Prices display in TZS. Styling uses the `kwt-*` CSS classes and the `--kwt-primary` / `--kwt-accent` custom properties set from your KwaWingu branding.
```

- [ ] **Step 4: Write `docs/booking-modes.md`**

```markdown
# Booking modes

Choose a mode in **Settings → KwaWingu Tours → Booking mode**.

## Redirect (available now)
Book buttons link to your hosted KwaWingu booking page:
`https://tours.kwawingu.com/{your-slug}/tours/{tour-slug}`. Availability, the booking steps, and payment (Snippe) are handled by KwaWingu — nothing to maintain on your site, and seat counts are always correct.

## Widget (roadmap — v0.3)
Embeds the KwaWingu booking widget in a page so guests book without leaving your site.

## On-site via API (roadmap — v0.4)
A fully in-site booking + payment flow using the KwaWingu API. Requires your **private API key** (stored server-side, never exposed to visitors). No card data touches WordPress — payment is completed through Snippe.

Until Widget and On-site ship, those modes fall back to Redirect.
```

- [ ] **Step 5: Update `readme.txt`** — bump `Stable tag: 0.2.0`, expand the `== Description ==` with the block/wizard features, and add a changelog entry:

```
= 0.2.0 =
* Blocks: Tours Grid, Tour Detail, Featured Tours, Book Button (+ shortcodes).
* One-click setup wizard: auto-brand from your profile, scaffold pages, import tours.
* Redirect booking mode.
* Sync safeguard: an empty catalog response no longer drafts your tours.
```

- [ ] **Step 6: Bump version** — in `kwawingu-tours.php` change `Version: 0.1.0` → `Version: 0.2.0` and `define( 'KWT_VERSION', '0.1.0' )` → `'0.2.0'`; in `includes/Plugin.php` change `const VERSION = '0.1.0'` → `'0.2.0'`; update the `PluginTest::test_version_constant_matches` expectation to `'0.2.0'`.

- [ ] **Step 7: Run full suite**

Run: `vendor/bin/phpunit`
Expected: all PASS (PluginTest updated to 0.2.0).

- [ ] **Step 8: Commit**

```bash
git add README.md docs/ readme.txt kwawingu-tours.php includes/Plugin.php tests/PluginTest.php
git commit -m "docs(v0.2): README + docs/ guide + readme.txt; bump to 0.2.0 (v0.2 task 12)"
```

---

## Self-Review

**Spec coverage (v0.2 slice):**
- Blocks (server-rendered, SSR) → Tasks 2-6 (Tours Grid, Tour Detail, Book Button, Featured Tours). ✓ (Reviews/Destinations/Search/Calculator/Availability blocks deferred to v0.3 per the architecture note — they need live availability + SEO.)
- Shortcode bridges → Task 7. ✓
- Block patterns → Task 8. ✓
- Auto-branding from `/profile` → Task 9. ✓
- One-click importer → Task 10. ✓
- Setup wizard → Task 11. ✓
- Redirect booking mode → Task 5 (`Booking`) + used by Book Button/Tour Detail. ✓
- v0.1 carry-over empty-`/site` guard → Task 1. ✓
- Docs (README.md, docs/ guide, readme.txt) → Task 12. ✓

**Placeholder scan:** No TBD/TODO; each code step has complete code. The test files note explicit stubs to add (e.g. `_n`, `wp_reset_postdata`) — these are concrete instructions, not placeholders.

**Type consistency:** `View::money/tour_query/tour_booking_url`, `Booking::url_for`, block render fn names (`kwt_render_*`), `Patterns::PAGES`, `Importer::META`/`run()` shape, `Setup_Wizard::__construct(Settings,Branding,Importer,Sync)` are used consistently across tasks. `View::tour_booking_url` (Task 2) resolves to `Booking::url_for` once Task 5 lands — sequenced correctly. Meta keys (`kwt_price`, `kwt_duration_days`, `kwt_slug`, `kwt_type`, `kwt_difficulty`) match v0.1's `Sync::write_meta`.

**Known simplification:** block render tests inject a `_query` attribute (or a global `WP_Query` stub) to exercise the loop without a DB — production ignores `_query` and builds a real `WP_Query` via `View::tour_query`. This validates the rendering/branching logic; full DB-backed rendering is a wp-env integration concern (later). The importer's `pattern_content()` returns `''` under unit test (registry absent) — production resolves real pattern markup registered on `init`.

**Boot order note for the implementer:** after Task 11, `Plugin::boot()` must register (in this order): Settings → Cpt → Blocks → Shortcodes → Patterns → Branding(+register wp_head) → Setup_Wizard → Sync_Controller → Admin_Page. Each later task that adds a `->register()` call must not drop earlier ones.
