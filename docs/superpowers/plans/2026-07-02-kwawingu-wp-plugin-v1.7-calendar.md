# KwaWingu WP Plugin v1.7 — Availability-Calendar block

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A visual month-grid block showing a tour's upcoming departures (date → seats / status), navigable by month, powered by the existing key-hiding proxy.

**Architecture:** Server-rendered shell (`kwawingu/availability-calendar`) + a dependency-free `view.js` that fetches `GET /departures?tourSlug=` through the proxy and paints a month grid. The date-bucketing logic lives in a pure, **Jest-tested** `buildMonthGrid(departures, year, month)` helper (UMD-exported like `blocks/booking/view.js`). Editor component (ServerSideRender) + shortcode + CSS.

**Tech Stack:** PHP 7.4+ (tests on PHP 8.3), WordPress 6.2+, PHPUnit + Brain\Monkey; `@wordpress/scripts` build for the editor component; Jest for the grid helper.

## Global Constraints

- Namespace `KwaWingu\Tours`; text domain `kwawingu-tours`. Block `kwawingu/availability-calendar`, category `widgets`.
- Block render: `render.php` ECHOES `kwt_render_availability_calendar()`; `render-fn.php` holds the function (function-only render.php renders blank). Shortcode + PHP tests require `render-fn.php`. Editor `index.js` = `registerBlockType(metadata,{edit,save:()=>null})`; block.json `editorScript: file:../../build/availability-calendar/index.js`; `edit.js` = ServerSideRender. Add `availability-calendar` to `webpack.config.js` `BLOCKS`.
- `view.js`: dependency-free; all DOM via `createElement`/`textContent` (NO `innerHTML` with server data); uses `window.kwtProxy.get('/departures', {tourSlug})`; the pure `buildMonthGrid` is UMD-exported for Jest. Dates are read as ISO `YYYY-MM-DD` strings.
- No new PHP data path (reuses the v1.1 `/departures` proxy route). All PHP output escaped.
- `vendor/bin/phpunit` + `vendor/bin/phpcs -q` (exit 0) + `npm run build` + `npm run test:js` stay green; committed `build/` up to date. Version bumps to 1.7.0.

---

### Task 1: buildMonthGrid helper + Jest test

**Files:**
- Create: `blocks/availability-calendar/grid.js`
- Test: `blocks/availability-calendar/grid.test.js`

**Interfaces:**
- Produces (CommonJS module, also usable in the browser via the view bundle's require-less inclusion — see note): `buildMonthGrid( departures, year, month )` where `month` is 0-based (0 = January). Returns `{ year, month, weeks: [][] }` — `weeks` is an array of 6 rows × 7 cells; each cell is `null` (padding) or `{ day: <1..31>, iso: 'YYYY-MM-DD', departures: [ {id,date,availableSeats,status}, … ] }`. Departures are matched to a cell by their `date`/`departureDate` ISO day.

- [ ] **Step 1: Write `blocks/availability-calendar/grid.test.js`**

```js
const { buildMonthGrid } = require( './grid' );

describe( 'buildMonthGrid', () => {
	it( 'lays out a 6x7 grid with correct leading padding', () => {
		// July 2026: 1 Jul is a Wednesday (day index 3).
		const grid = buildMonthGrid( [], 2026, 6 );
		expect( grid.year ).toBe( 2026 );
		expect( grid.month ).toBe( 6 );
		expect( grid.weeks ).toHaveLength( 6 );
		expect( grid.weeks[ 0 ] ).toHaveLength( 7 );
		// First three cells (Sun,Mon,Tue) are padding, the 4th is the 1st.
		expect( grid.weeks[ 0 ][ 0 ] ).toBeNull();
		expect( grid.weeks[ 0 ][ 2 ] ).toBeNull();
		expect( grid.weeks[ 0 ][ 3 ] ).toEqual(
			expect.objectContaining( { day: 1, iso: '2026-07-01' } )
		);
	} );

	it( 'buckets departures onto their day cell', () => {
		const deps = [
			{ id: 'D1', date: '2026-07-01', availableSeats: 4, status: 'open' },
			{ id: 'D2', departureDate: '2026-07-15', availableSeats: 0, status: 'soldout' },
			{ id: 'D3', date: '2026-08-01', availableSeats: 9 }, // other month — ignored
		];
		const grid = buildMonthGrid( deps, 2026, 6 );
		expect( grid.weeks[ 0 ][ 3 ].departures ).toHaveLength( 1 );
		expect( grid.weeks[ 0 ][ 3 ].departures[ 0 ].id ).toBe( 'D1' );
		// 15 Jul 2026 is a Wednesday in the 3rd week.
		const cell15 = grid.weeks.flat().find( ( c ) => c && c.day === 15 );
		expect( cell15.departures[ 0 ].id ).toBe( 'D2' );
		// No cell holds the August departure.
		const hasAug = grid.weeks.flat().some( ( c ) => c && c.departures.some( ( d ) => d.id === 'D3' ) );
		expect( hasAug ).toBe( false );
	} );
} );
```

- [ ] **Step 2: Run to verify it fails**

Run: `npm run test:js -- grid.test.js`
Expected: FAIL — `./grid` not found.

- [ ] **Step 3: Write `blocks/availability-calendar/grid.js`**

```js
/**
 * Pure month-grid builder for the availability calendar (Jest-testable).
 */
( function ( root ) {
	'use strict';

	function pad2( n ) {
		return ( n < 10 ? '0' : '' ) + n;
	}

	function depDate( d ) {
		return ( d && ( d.date || d.departureDate ) ) || '';
	}

	/**
	 * @param {Array} departures - each {id, date|departureDate, availableSeats?, status?}
	 * @param {number} year
	 * @param {number} month - 0-based (0 = January)
	 * @returns {{year:number, month:number, weeks:Array}}
	 */
	function buildMonthGrid( departures, year, month ) {
		var first = new Date( Date.UTC( year, month, 1 ) );
		var startDow = first.getUTCDay(); // 0 = Sunday
		var daysInMonth = new Date( Date.UTC( year, month + 1, 0 ) ).getUTCDate();

		// Bucket departures by ISO day within this month.
		var byIso = {};
		( departures || [] ).forEach( function ( d ) {
			var iso = depDate( d ).slice( 0, 10 );
			if ( ! iso ) { return; }
			if ( ! byIso[ iso ] ) { byIso[ iso ] = []; }
			byIso[ iso ].push( d );
		} );

		var weeks = [];
		var cellIndex = 0; // 0-based across the whole grid
		for ( var w = 0; w < 6; w++ ) {
			var row = [];
			for ( var dow = 0; dow < 7; dow++ ) {
				var day = cellIndex - startDow + 1;
				if ( day < 1 || day > daysInMonth ) {
					row.push( null );
				} else {
					var iso = year + '-' + pad2( month + 1 ) + '-' + pad2( day );
					row.push( { day: day, iso: iso, departures: byIso[ iso ] || [] } );
				}
				cellIndex++;
			}
			weeks.push( row );
		}
		return { year: year, month: month, weeks: weeks };
	}

	root.kwtBuildMonthGrid = buildMonthGrid;
	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = { buildMonthGrid: buildMonthGrid };
	}
}( typeof window !== 'undefined' ? window : this ) );
```

Note: `grid.js` attaches `window.kwtBuildMonthGrid` in the browser (so `view.js` can call it without a bundler) and exports for Jest. It is enqueued alongside `view.js` (Task 2 registers both as the block's view scripts, `grid.js` first).

- [ ] **Step 4: Run to pass**

Run: `npm run test:js -- grid.test.js`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add blocks/availability-calendar/grid.js blocks/availability-calendar/grid.test.js
git commit -m "feat(calendar): pure buildMonthGrid helper + Jest tests (v1.7 task 1)"
```

---

### Task 2: Availability-Calendar block (shell + view + editor + CSS)

**Files:**
- Create: `blocks/availability-calendar/block.json`, `render-fn.php`, `render.php`, `view.js`, `edit.js`, `index.js`
- Modify: `includes/Assets.php` (register + enqueue `kwt-grid` script so the block can depend on it), `assets/css/kwt-blocks.css`, `webpack.config.js` (`BLOCKS` += `availability-calendar`)
- Test: `tests/blocks/AvailabilityCalendarRenderTest.php`

**Interfaces:**
- Produces: block `kwawingu/availability-calendar`; `kwt_render_availability_calendar( array $attributes, string $content ): string` — a shell `<div class="kwt-availcal" data-tour="{slug}"><div class="kwt-availcal__head"></div><div class="kwt-availcal__grid"></div></div>`; enqueues `kwt-proxy` + `kwt-grid`. `view.js` reads `window.kwtBuildMonthGrid`, fetches departures, and paints the grid with prev/next month nav.

- [ ] **Step 1: Write `tests/blocks/AvailabilityCalendarRenderTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class AvailabilityCalendarRenderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		require_once dirname( __DIR__, 2 ) . '/blocks/availability-calendar/render-fn.php';
		foreach ( array( 'esc_attr', 'esc_html__' ) as $f ) {
			Functions\when( $f )->returnArg();
		}
		Functions\when( 'wp_enqueue_script' )->justReturn( null );
		Functions\when( 'get_the_ID' )->justReturn( 7 );
		Functions\when( 'get_post_meta' )->justReturn( 'safari' );
	}
	protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

	public function test_renders_calendar_shell(): void {
		$html = kwt_render_availability_calendar( array(), '' );
		$this->assertStringContainsString( 'kwt-availcal', $html );
		$this->assertStringContainsString( 'data-tour="safari"', $html );
		$this->assertStringContainsString( 'kwt-availcal__grid', $html );
	}
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter AvailabilityCalendarRenderTest`
Expected: FAIL — file not found.

- [ ] **Step 3: Write `blocks/availability-calendar/block.json`**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "kwawingu/availability-calendar",
  "title": "KwaWingu Availability Calendar",
  "category": "widgets",
  "icon": "calendar-alt",
  "description": "A month calendar of a tour's departures.",
  "textdomain": "kwawingu-tours",
  "attributes": { "tourSlug": { "type": "string", "default": "" } },
  "supports": { "html": false, "align": [ "wide" ] },
  "editorScript": "file:../../build/availability-calendar/index.js",
  "render": "file:./render.php"
}
```

- [ ] **Step 4: Write `blocks/availability-calendar/render-fn.php`**

```php
<?php
/**
 * Render function for kwawingu/availability-calendar (server shell).
 *
 * @package KwaWingu\Tours
 */

if ( ! function_exists( 'kwt_render_availability_calendar' ) ) {
	/**
	 * Render callback for kwawingu/availability-calendar.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 * @param string              $content    Inner content (unused).
	 * @return string
	 */
	function kwt_render_availability_calendar( array $attributes, string $content = '' ): string {
		if ( function_exists( 'wp_enqueue_script' ) ) {
			wp_enqueue_script( 'kwt-grid' );
			wp_enqueue_script( 'kwt-proxy' );
		}
		$id        = (int) get_the_ID();
		$tour_slug = ! empty( $attributes['tourSlug'] ) ? (string) $attributes['tourSlug'] : (string) get_post_meta( $id, 'kwt_slug', true );
		return '<div class="kwt-availcal" data-tour="' . esc_attr( $tour_slug ) . '">'
			. '<div class="kwt-availcal__head"></div>'
			. '<div class="kwt-availcal__grid" aria-live="polite"></div>'
			. '</div>';
	}
}
```

- [ ] **Step 5: Write `blocks/availability-calendar/render.php`** (thin echoing template)

```php
<?php
/**
 * WP block template for kwawingu/availability-calendar.
 *
 * @package KwaWingu\Tours
 */
require_once __DIR__ . '/render-fn.php';
$kwt_attrs   = isset( $attributes ) && is_array( $attributes ) ? $attributes : array();
$kwt_content = isset( $content ) ? (string) $content : '';
echo kwt_render_availability_calendar( $kwt_attrs, $kwt_content ); // phpcs:ignore WordPress.Security.EscapeOutput -- render fn returns escaped HTML.
```

- [ ] **Step 6: Write `blocks/availability-calendar/view.js`**

```js
/**
 * kwawingu/availability-calendar view: fetch departures, paint a month grid.
 */
( function () {
	'use strict';
	var MONTHS = [ 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ];
	var DOW = [ 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ];

	function init( root ) {
		var tourSlug = root.getAttribute( 'data-tour' );
		var head = root.querySelector( '.kwt-availcal__head' );
		var gridEl = root.querySelector( '.kwt-availcal__grid' );
		var now = new Date();
		var year = now.getUTCFullYear();
		var month = now.getUTCMonth();
		var departures = [];

		window.kwtProxy.get( '/departures', { tourSlug: tourSlug } ).then( function ( res ) {
			departures = ( res && res.data ) || [];
			render();
		} ).catch( function () { render(); } );

		function header() {
			head.textContent = '';
			var prev = document.createElement( 'button' );
			prev.type = 'button';
			prev.className = 'kwt-availcal__nav';
			prev.textContent = '‹';
			prev.addEventListener( 'click', function () { shift( -1 ); } );
			var title = document.createElement( 'span' );
			title.className = 'kwt-availcal__title';
			title.textContent = MONTHS[ month ] + ' ' + year;
			var next = document.createElement( 'button' );
			next.type = 'button';
			next.className = 'kwt-availcal__nav';
			next.textContent = '›';
			next.addEventListener( 'click', function () { shift( 1 ); } );
			head.appendChild( prev );
			head.appendChild( title );
			head.appendChild( next );
		}

		function shift( delta ) {
			month += delta;
			if ( month < 0 ) { month = 11; year--; }
			if ( month > 11 ) { month = 0; year++; }
			render();
		}

		function render() {
			header();
			gridEl.textContent = '';
			var grid = window.kwtBuildMonthGrid( departures, year, month );
			var table = document.createElement( 'table' );
			table.className = 'kwt-availcal__table';
			var thead = document.createElement( 'tr' );
			DOW.forEach( function ( d ) {
				var th = document.createElement( 'th' );
				th.textContent = d;
				thead.appendChild( th );
			} );
			table.appendChild( thead );
			grid.weeks.forEach( function ( week ) {
				var tr = document.createElement( 'tr' );
				week.forEach( function ( cell ) {
					var td = document.createElement( 'td' );
					if ( cell ) {
						var dayEl = document.createElement( 'span' );
						dayEl.className = 'kwt-availcal__day';
						dayEl.textContent = String( cell.day );
						td.appendChild( dayEl );
						if ( cell.departures.length ) {
							var seats = cell.departures[ 0 ].availableSeats;
							td.className = ( seats === 0 ) ? 'kwt-availcal__cell is-soldout' : 'kwt-availcal__cell is-open';
							var tag = document.createElement( 'span' );
							tag.className = 'kwt-availcal__seats';
							tag.textContent = ( seats === 0 ) ? window.kwtProxy.i18n.soldOut : ( seats != null ? seats + '' : '•' );
							td.appendChild( tag );
						}
					}
					tr.appendChild( td );
				} );
				table.appendChild( tr );
			} );
			gridEl.appendChild( table );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.kwt-availcal' ), init );
	} );
}() );
```

- [ ] **Step 7: Write `blocks/availability-calendar/edit.js`**

```js
/**
 * Editor component for kwawingu/availability-calendar.
 */
import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

export default function Edit( { attributes } ) {
	const blockProps = useBlockProps();
	return (
		<div { ...blockProps }>
			<ServerSideRender block={ metadata.name } attributes={ attributes } />
		</div>
	);
}
```

- [ ] **Step 8: Write `blocks/availability-calendar/index.js`**

```js
/**
 * Client (editor) registration for kwawingu/availability-calendar.
 */
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
```

- [ ] **Step 9: Register the `kwt-grid` script + a `soldOut` i18n string in `includes/Assets.php`** — in `enqueue()`, after the `kwt-proxy` registration + before the localize, register grid:

```php
		wp_register_script(
			'kwt-grid',
			plugins_url( 'blocks/availability-calendar/grid.js', KWT_PLUGIN_FILE ),
			array(),
			KWT_VERSION,
			true
		);
```

And add to the `'i18n'` array in the `kwtProxy` localize:

```php
					'soldOut'         => __( 'Sold out', 'kwawingu-tours' ),
```

- [ ] **Step 10: Add calendar CSS to `assets/css/kwt-blocks.css`** — append:

```css
/* ── Availability calendar ─────────────────────────────────────────────── */

.kwt-availcal { --kwt-primary: var( --kwt-primary, #0a4a3a ); --kwt-accent: var( --kwt-accent, #e8920a ); max-width: 520px; }
.kwt-availcal__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.kwt-availcal__title { font-weight: 700; color: var( --kwt-primary ); }
.kwt-availcal__nav { background: none; border: 1px solid var( --kwt-primary ); color: var( --kwt-primary ); border-radius: 6px; width: 32px; height: 32px; cursor: pointer; }
.kwt-availcal__table { width: 100%; border-collapse: collapse; }
.kwt-availcal__table th { font-size: 0.75rem; color: #667085; padding: 4px; text-align: center; }
.kwt-availcal__table td { height: 46px; border: 1px solid #eee; vertical-align: top; padding: 2px 4px; text-align: right; }
.kwt-availcal__cell.is-open { background: #e1f5ee; }
.kwt-availcal__cell.is-soldout { background: #fbeaea; color: #9b1c1c; }
.kwt-availcal__day { font-size: 0.8rem; }
.kwt-availcal__seats { display: block; font-size: 0.7rem; font-weight: 700; color: var( --kwt-primary ); }
```

Also add `.kwt-availcal` to the top-of-file base-token + box-sizing selector groups.

- [ ] **Step 11: Add `availability-calendar` to `webpack.config.js` `BLOCKS` + build**

Run: `npm run build`
Expected: exit 0; `build/availability-calendar/index.js` + `.asset.php` created.

- [ ] **Step 12: Run to pass + full suite + phpcs + build + jest**

Run: `vendor/bin/phpunit --filter AvailabilityCalendarRenderTest` then `vendor/bin/phpunit` then `vendor/bin/phpcs -q` then `npm run build` then `npm run test:js`
Expected: all PASS; phpcs 0; build 0; jest green.

Note: `Assets.php` changed → update `tests/AssetsTest.php` if it asserts an exact list of registered scripts (it asserts `kwt-proxy` register/localize + `kwt-blocks` style — adding a `kwt-grid` register shouldn't break those; if the test stubs `wp_register_script` to capture only the last handle, keep it capturing `kwt-proxy` by ordering or broaden the stub). Verify the AssetsTest still passes; adjust its stub to tolerate the extra `wp_register_script` call if needed.

- [ ] **Step 13: Commit**

```bash
git add blocks/availability-calendar/ includes/Assets.php assets/css/kwt-blocks.css webpack.config.js build/availability-calendar/ tests/blocks/AvailabilityCalendarRenderTest.php tests/AssetsTest.php
git commit -m "feat(blocks): Availability Calendar block (month grid via proxy) + editor + styles (v1.7 task 2)"
```

---

### Task 3: Calendar shortcode + docs + bump 1.7.0

**Files:**
- Modify: `includes/Shortcodes.php` (`[kwawingu_availability]`)
- Modify: `docs/blocks.md`, `README.md`, `readme.txt` (changelog + Stable tag), `kwawingu-tours.php` + `includes/Plugin.php` (version), `tests/PluginTest.php`, `package.json`
- Test: `tests/ShortcodesTest.php`

**Interfaces:**
- Produces: shortcode `[kwawingu_availability]` → `kwt_render_availability_calendar`.

- [ ] **Step 1: Extend `test_register_adds_all_shortcodes`** — add:

```php
		$this->assertContains( 'kwawingu_availability', $registered );
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter ShortcodesTest`
Expected: FAIL.

- [ ] **Step 3: Extend `includes/Shortcodes.php`** — in `register()`:

```php
		add_shortcode( 'kwawingu_availability', array( $this, 'render_availability' ) );
```

Method:

```php
	/**
	 * [kwawingu_availability] — a tour's departures calendar.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_availability( $atts ): string {
		require_once Blocks::block_dir() . 'availability-calendar/render-fn.php';
		return kwt_render_availability_calendar( array(), '' );
	}
```

- [ ] **Step 4: Bump to 1.7.0** — `kwawingu-tours.php`, `includes/Plugin.php`, `tests/PluginTest.php`, `readme.txt` (Stable tag), `package.json`.

- [ ] **Step 5: Docs** — `docs/blocks.md` + `README.md` add the Availability Calendar block/shortcode. `readme.txt` changelog:

```
= 1.7.0 =
* Availability Calendar block: a month grid of a tour's departures with seats/sold-out status (and [kwawingu_availability] shortcode).
```

- [ ] **Step 6: Run full suite + phpcs + build + jest**

Run: `vendor/bin/phpunit` then `vendor/bin/phpcs -q` then `npm run build` then `npm run test:js`
Expected: all PASS; phpcs 0; build 0; jest green.

- [ ] **Step 7: Commit**

```bash
git add includes/Shortcodes.php tests/ShortcodesTest.php docs/ README.md readme.txt kwawingu-tours.php includes/Plugin.php tests/PluginTest.php package.json
git commit -m "feat(v1.7): [kwawingu_availability] shortcode + docs; bump 1.7.0 (v1.7 task 3)"
```

---

## Self-Review

**Spec coverage (v1.7):**
- `buildMonthGrid` pure helper + Jest test → Task 1. ✓
- Availability-calendar block (shell + view.js paint + prev/next nav + editor + CSS, reuses `/departures` proxy) → Task 2. ✓
- Shortcode + docs + version → Task 3. ✓

**Placeholder scan:** No TBD/TODO; each code step has complete code. `view.js` (calendar painting) is verified by the Jest-tested `buildMonthGrid` (the logic) + PHP shell test; the DOM painting is structural (no `innerHTML`).

**Type consistency:** `buildMonthGrid(departures, year, month)` return shape (`{year,month,weeks:[][]}` with `{day,iso,departures}` cells) is consumed identically by `view.js`; `kwt_render_availability_calendar` + render-fn/template split + shortcode require (render-fn.php) + editorScript path + `webpack.config.js` `BLOCKS` entry align; `kwt-grid` script registered in Assets is enqueued by the block's render-fn; `soldOut` i18n added in Assets + used in view.js. Block render.php ECHOES.

**Known simplification:** `view.js` calendar painting isn't unit-tested (no DOM harness beyond the Jest-tested grid logic); wp-env (v1.8) covers real rendering. `AssetsTest` may need its `wp_register_script` stub broadened for the added `kwt-grid` registration — handled in Task 2 Step 12.
