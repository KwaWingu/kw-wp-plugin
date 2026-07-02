# KwaWingu WP Plugin v1.6 — Gallery sideloading + Gallery block

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Import each tour's gallery images into the WordPress media library (sideload mode) and display them with a new Gallery block.

**Architecture:** Extend `Media` with `ingest_gallery()` (best-effort, deduped by a stored source list), called from `Sync::write_meta` like the cover. Add a server-rendered `kwawingu/gallery` block (render-fn/template split) that reads sideloaded attachment IDs (`kwt_gallery_ids`) or falls back to the raw `kwt_gallery` URLs, plus its shortcode + editor component + CSS.

**Tech Stack:** PHP 7.4+ (tests on PHP 8.3), WordPress 6.2+, PHPUnit + Brain\Monkey; `@wordpress/scripts` build for the editor component; Jest available.

## Global Constraints

- Namespace `KwaWingu\Tours`; text domain `kwawingu-tours`. Block namespace `kwawingu/*`, category `widgets`.
- Media ingest is **best-effort** (try/catch \Throwable, never throws into sync) and **only in `sideload` mode**; `hotlink` mode leaves the raw URLs for display. Dedup so re-sync doesn't re-import.
- Block render: `render.php` is a thin template that ECHOES `kwt_render_gallery()`; `render-fn.php` holds the function (a function-only render.php renders blank). Shortcode + tests `require_once` the `render-fn.php`. Editor `index.js` registers via `registerBlockType(metadata, {edit, save:()=>null})` + block.json `editorScript: file:../../build/gallery/index.js`; `edit.js` uses `ServerSideRender` + `InspectorControls`.
- All image output escaped (`esc_url` src, `esc_attr` attributes). No `innerHTML` with server data.
- `vendor/bin/phpunit` + `vendor/bin/phpcs -q` (exit 0) + `npm run build` + `npm run test:js` stay green. Committed `build/` bundles stay up to date. Version bumps to 1.6.0.

---

### Task 1: Media::ingest_gallery + wire into sync

**Files:**
- Modify: `includes/Media.php` (add `ingest_gallery` + a `META_GALLERY_IDS`/`META_GALLERY_SRC` const)
- Modify: `includes/Sync.php` (`write_meta` calls `ingest_gallery`)
- Test: `tests/MediaTest.php`

**Interfaces:**
- Produces: `Media::ingest_gallery( int $post_id, array $urls ): array` — in `sideload` mode, sideloads each URL not already in the stored source list, appends the new attachment IDs to `kwt_gallery_ids` meta + the URLs to `kwt_gallery_src` meta, returns the full attachment-ID array; no-op (returns existing IDs or `array()`) in `hotlink` mode / on empty input; best-effort. Consts `Media::META_GALLERY_IDS = 'kwt_gallery_ids'`, `Media::META_GALLERY_SRC = 'kwt_gallery_src'`.

- [ ] **Step 1: Add tests to `tests/MediaTest.php`**

```php
	public function test_gallery_hotlink_mode_does_not_sideload(): void {
		Functions\when( 'get_option' )->justReturn( array( 'media_mode' => 'hotlink' ) );
		Functions\expect( 'media_sideload_image' )->never();
		$out = ( new Media( new Settings() ) )->ingest_gallery( 7, array( 'https://img/a.jpg' ) );
		$this->assertSame( array(), $out );
	}

	public function test_gallery_sideloads_new_urls_and_dedups(): void {
		Functions\when( 'get_option' )->justReturn( array( 'media_mode' => 'sideload' ) );
		// a.jpg already ingested (src list), b.jpg is new.
		Functions\when( 'get_post_meta' )->alias( static function ( $id, $key, $single ) {
			if ( 'kwt_gallery_src' === $key ) { return array( 'https://img/a.jpg' ); }
			if ( 'kwt_gallery_ids' === $key ) { return array( 11 ); }
			return '';
		} );
		$sideloaded = array();
		Functions\when( 'media_sideload_image' )->alias( static function ( $url ) use ( &$sideloaded ) {
			$sideloaded[] = $url;
			return 22; // new attachment id
		} );
		$saved = array();
		Functions\when( 'update_post_meta' )->alias( static function ( $id, $key, $val ) use ( &$saved ) {
			$saved[ $key ] = $val;
			return true;
		} );

		$out = ( new Media( new Settings() ) )->ingest_gallery( 7, array( 'https://img/a.jpg', 'https://img/b.jpg' ) );

		$this->assertSame( array( 'https://img/b.jpg' ), $sideloaded );      // only the new one
		$this->assertSame( array( 11, 22 ), $out );                          // existing + new id
		$this->assertSame( array( 11, 22 ), $saved['kwt_gallery_ids'] );
		$this->assertSame( array( 'https://img/a.jpg', 'https://img/b.jpg' ), $saved['kwt_gallery_src'] );
	}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter MediaTest`
Expected: FAIL — `ingest_gallery` undefined.

- [ ] **Step 3: Add to `includes/Media.php`** — the consts (next to `META_SRC`) and the method (after `ingest_cover`):

```php
	const META_GALLERY_IDS = 'kwt_gallery_ids';
	const META_GALLERY_SRC = 'kwt_gallery_src';
```

```php
	/**
	 * Sideload a tour's gallery images into the media library (sideload mode).
	 *
	 * @param int           $post_id Tour post ID.
	 * @param array<int,mixed> $urls  Remote image URLs.
	 * @return array<int,int> Attachment IDs (existing + newly ingested).
	 */
	public function ingest_gallery( int $post_id, array $urls ): array {
		$existing_ids = get_post_meta( $post_id, self::META_GALLERY_IDS, true );
		$existing_ids = is_array( $existing_ids ) ? array_values( array_map( 'intval', $existing_ids ) ) : array();
		if ( empty( $urls ) || 'sideload' !== $this->settings->get_media_mode() ) {
			return $existing_ids;
		}
		$done_src = get_post_meta( $post_id, self::META_GALLERY_SRC, true );
		$done_src = is_array( $done_src ) ? $done_src : array();

		$this->require_media_functions();
		$changed = false;
		foreach ( $urls as $url ) {
			$url = is_string( $url ) ? $url : '';
			if ( '' === $url || in_array( $url, $done_src, true ) ) {
				continue;
			}
			try {
				$attachment_id = media_sideload_image( $url, $post_id, null, 'id' );
			} catch ( \Throwable $e ) {
				continue; // best-effort
			}
			if ( is_int( $attachment_id ) && $attachment_id > 0 ) {
				$existing_ids[] = $attachment_id;
				$done_src[]     = $url;
				$changed        = true;
			}
		}
		if ( $changed ) {
			update_post_meta( $post_id, self::META_GALLERY_IDS, $existing_ids );
			update_post_meta( $post_id, self::META_GALLERY_SRC, $done_src );
		}
		return $existing_ids;
	}
```

- [ ] **Step 4: Wire into `includes/Sync.php`** — in `write_meta`, inside the existing `if ( null !== $this->media )` block, after the cover ingest, add:

```php
			$gallery = get_post_meta( $post_id, 'kwt_gallery', true );
			if ( is_array( $gallery ) && ! empty( $gallery ) ) {
				$this->media->ingest_gallery( $post_id, $gallery );
			}
```

(The `kwt_gallery` meta was written earlier in `write_meta`, so it's available. In the existing Sync tests `$this->media` is null, so this is a no-op there — they stay green.)

- [ ] **Step 5: Run to pass + full suite + phpcs**

Run: `vendor/bin/phpunit --filter MediaTest` then `vendor/bin/phpunit` then `vendor/bin/phpcs -q`
Expected: PASS; phpcs exit 0.

- [ ] **Step 6: Commit**

```bash
git add includes/Media.php includes/Sync.php tests/MediaTest.php
git commit -m "feat(media): sideload tour gallery images (deduped, best-effort) (v1.6 task 1)"
```

---

### Task 2: Gallery block (render + editor + CSS)

**Files:**
- Create: `blocks/gallery/block.json`, `blocks/gallery/render-fn.php`, `blocks/gallery/render.php`, `blocks/gallery/edit.js`, `blocks/gallery/index.js`
- Modify: `assets/css/kwt-blocks.css` (gallery grid styles)
- Test: `tests/blocks/GalleryRenderTest.php`

**Interfaces:**
- Produces: block `kwawingu/gallery`; `kwt_render_gallery( array $attributes, string $content ): string` — renders a grid of `<img>` from `kwt_gallery_ids` (via `wp_get_attachment_image_url`) or the `kwt_gallery` URLs; `columns` attribute (1–6, default 3); empty string when no gallery.

- [ ] **Step 1: Write `tests/blocks/GalleryRenderTest.php`**

```php
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
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter GalleryRenderTest`
Expected: FAIL — file not found.

- [ ] **Step 3: Write `blocks/gallery/block.json`**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "kwawingu/gallery",
  "title": "KwaWingu Gallery",
  "category": "widgets",
  "icon": "format-gallery",
  "description": "A tour's photo gallery.",
  "textdomain": "kwawingu-tours",
  "attributes": {
    "postId":  { "type": "number", "default": 0 },
    "columns": { "type": "number", "default": 3 }
  },
  "supports": { "html": false, "align": [ "wide", "full" ] },
  "editorScript": "file:../../build/gallery/index.js",
  "render": "file:./render.php"
}
```

- [ ] **Step 4: Write `blocks/gallery/render-fn.php`**

```php
<?php
/**
 * Render function for kwawingu/gallery.
 *
 * @package KwaWingu\Tours
 */

if ( ! function_exists( 'kwt_render_gallery' ) ) {
	/**
	 * Render callback for kwawingu/gallery.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 * @param string              $content    Inner content (unused).
	 * @return string
	 */
	function kwt_render_gallery( array $attributes, string $content = '' ): string {
		$id   = ! empty( $attributes['postId'] ) ? (int) $attributes['postId'] : (int) get_the_ID();
		$cols = isset( $attributes['columns'] ) ? (int) $attributes['columns'] : 3;
		if ( $cols < 1 ) {
			$cols = 1;
		}
		if ( $cols > 6 ) {
			$cols = 6;
		}

		$items = array();
		$ids   = get_post_meta( $id, 'kwt_gallery_ids', true );
		if ( is_array( $ids ) && ! empty( $ids ) ) {
			foreach ( $ids as $aid ) {
				$url = wp_get_attachment_image_url( (int) $aid, 'large' );
				if ( $url ) {
					$items[] = $url;
				}
			}
		}
		if ( empty( $items ) ) {
			$urls = get_post_meta( $id, 'kwt_gallery', true );
			if ( is_array( $urls ) ) {
				foreach ( $urls as $u ) {
					if ( is_string( $u ) && '' !== $u ) {
						$items[] = $u;
					}
				}
			}
		}
		if ( empty( $items ) ) {
			return '';
		}

		$out = '<div class="kwt-gallery" style="--kwt-cols:' . esc_attr( (string) $cols ) . '">';
		foreach ( $items as $u ) {
			$out .= '<img class="kwt-gallery__img" src="' . esc_url( $u ) . '" alt="" loading="lazy" />';
		}
		$out .= '</div>';
		return $out;
	}
}
```

- [ ] **Step 5: Write `blocks/gallery/render.php`** (thin echoing template)

```php
<?php
/**
 * WP block template for kwawingu/gallery.
 *
 * @package KwaWingu\Tours
 */
require_once __DIR__ . '/render-fn.php';
$kwt_attrs   = isset( $attributes ) && is_array( $attributes ) ? $attributes : array();
$kwt_content = isset( $content ) ? (string) $content : '';
echo kwt_render_gallery( $kwt_attrs, $kwt_content ); // phpcs:ignore WordPress.Security.EscapeOutput -- render fn returns escaped HTML.
```

- [ ] **Step 6: Write `blocks/gallery/edit.js`**

```js
/**
 * Editor component for kwawingu/gallery.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Gallery', 'kwawingu-tours' ) }>
					<RangeControl
						label={ __( 'Columns', 'kwawingu-tours' ) }
						value={ attributes.columns }
						onChange={ ( value ) => setAttributes( { columns: value } ) }
						min={ 1 }
						max={ 6 }
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender block={ metadata.name } attributes={ attributes } />
		</div>
	);
}
```

- [ ] **Step 7: Write `blocks/gallery/index.js`**

```js
/**
 * Client (editor) registration for kwawingu/gallery.
 */
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
```

- [ ] **Step 8: Add gallery styles to `assets/css/kwt-blocks.css`** — append:

```css
/* ── Gallery ───────────────────────────────────────────────────────────── */

.kwt-gallery {
	display: grid;
	grid-template-columns: repeat( var( --kwt-cols, 3 ), 1fr );
	gap: 10px;
	margin: 0;
}

.kwt-gallery__img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	border-radius: 8px;
	display: block;
}
```

Also register the block namespace's `--kwt-cols`-aware selector by adding `.kwt-gallery` to the top-of-file custom-property + `box-sizing` selector lists (the `--kwt-primary`/`box-sizing` groups) so it inherits the base tokens.

- [ ] **Step 9: Build the editor bundle**

Run: `npm run build`
Expected: exit 0; `build/gallery/index.js` + `build/gallery/index.asset.php` created.

Note: add `'gallery'` to the `BLOCKS` array in `webpack.config.js` so it's built.

- [ ] **Step 10: Run to pass + full suite + phpcs + build check**

Run: `vendor/bin/phpunit --filter GalleryRenderTest` then `vendor/bin/phpunit` then `vendor/bin/phpcs -q` then `npm run build`
Expected: PASS; phpcs 0; build 0.

- [ ] **Step 11: Commit**

```bash
git add blocks/gallery/ assets/css/kwt-blocks.css webpack.config.js build/gallery/ tests/blocks/GalleryRenderTest.php
git commit -m "feat(blocks): Gallery block (sideloaded IDs or URLs) + editor + styles (v1.6 task 2)"
```

---

### Task 3: Gallery shortcode + docs + bump 1.6.0

**Files:**
- Modify: `includes/Shortcodes.php` (`[kwawingu_gallery]`)
- Modify: `docs/blocks.md`, `README.md`, `readme.txt` (changelog + Stable tag), `kwawingu-tours.php` + `includes/Plugin.php` (version), `tests/PluginTest.php`, `package.json` (version)
- Test: `tests/ShortcodesTest.php`

**Interfaces:**
- Produces: shortcode `[kwawingu_gallery columns id]` → `kwt_render_gallery`.

- [ ] **Step 1: Extend `test_register_adds_all_shortcodes` in `tests/ShortcodesTest.php`**

```php
		$this->assertContains( 'kwawingu_gallery', $registered );
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter ShortcodesTest`
Expected: FAIL.

- [ ] **Step 3: Extend `includes/Shortcodes.php`** — in `register()`:

```php
		add_shortcode( 'kwawingu_gallery', array( $this, 'render_gallery' ) );
```

And the method:

```php
	/**
	 * [kwawingu_gallery] — a tour's photo gallery.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_gallery( $atts ): string {
		require_once Blocks::block_dir() . 'gallery/render-fn.php';
		$atts = shortcode_atts( array( 'id' => 0, 'columns' => 3 ), $atts );
		return kwt_render_gallery( array( 'postId' => (int) $atts['id'], 'columns' => (int) $atts['columns'] ), '' );
	}
```

- [ ] **Step 4: Bump version to 1.6.0** — `kwawingu-tours.php` (`Version: 1.6.0` + `KWT_VERSION '1.6.0'`), `includes/Plugin.php` (`const VERSION = '1.6.0'`), `tests/PluginTest.php` (expect `'1.6.0'`), `readme.txt` (`Stable tag: 1.6.0`), `package.json` (`"version": "1.6.0"`).

- [ ] **Step 5: Update docs** — `docs/blocks.md`: add the Gallery block + `[kwawingu_gallery]`. `readme.txt` changelog:

```
= 1.6.0 =
* Gallery: tour gallery images are imported into your media library and shown with a new Gallery block (and [kwawingu_gallery] shortcode).
```

`README.md`: add Gallery to the shortcode table / feature list.

- [ ] **Step 6: Run full suite + phpcs + build**

Run: `vendor/bin/phpunit` then `vendor/bin/phpcs -q` then `npm run build`
Expected: PASS (PluginTest expects 1.6.0); phpcs 0; build 0.

- [ ] **Step 7: Commit**

```bash
git add includes/Shortcodes.php tests/ShortcodesTest.php docs/ README.md readme.txt kwawingu-tours.php includes/Plugin.php tests/PluginTest.php package.json
git commit -m "feat(v1.6): [kwawingu_gallery] shortcode + docs; bump 1.6.0 (v1.6 task 3)"
```

---

## Self-Review

**Spec coverage (v1.6):**
- `Media::ingest_gallery` (sideload, dedup, best-effort) + sync wiring → Task 1. ✓
- `kwawingu/gallery` block (IDs or URLs, escaped, editor, CSS) → Task 2. ✓
- Shortcode + docs + version → Task 3. ✓

**Placeholder scan:** No TBD/TODO; each code step has complete code.

**Type consistency:** `Media::ingest_gallery(int, array): array` + consts `META_GALLERY_IDS`/`META_GALLERY_SRC` used consistently; `kwt_render_gallery` + its render-fn/template + shortcode require (render-fn.php) + editorScript path (`../../build/gallery/index.js`) + `webpack.config.js` `BLOCKS` entry all align; meta keys `kwt_gallery` (URLs, from v0.3 sync), `kwt_gallery_ids`/`kwt_gallery_src` (new) consistent. Block render.php ECHOES (split pattern).

**Known simplification:** `ingest_gallery` is unit-tested with `media_sideload_image` stubbed (no real HTTP/file I/O); real ingestion is a wp-env concern (v1.8). Gallery block render tested via the shell + item URLs.
