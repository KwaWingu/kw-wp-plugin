# KwaWingu WP Plugin v1.1 — Fix on-site booking against the real API

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the on-site booking flow work against the live KwaWingu API: pick a real departure, show a live price, and create the booking with the correct field names + idempotency key, then pay and poll — replacing the guessed `{tourSlug, customer, date, pax}` payload.

**Architecture:** Extend the key-hiding `Rest_Proxy` with `GET /departures` + `POST /quote` read routes. Rewrite the on-site booking block: the form collects the correct guest fields (`guestFirstName/…`) + pax (`adults/children/infants`) and a departure chosen from a proxy-loaded list; `view.js` prices via `/quote`, creates with the real payload + a generated `idempotencyKey`, starts `payment-intent`, polls, then links to the returned `portalUrl`.

**Tech Stack:** PHP 7.4+ (tests on PHP 8.3), WordPress REST API, PHPUnit + Brain\Monkey + Mockery for the proxy; vanilla ES5-safe JS for `view.js` (not unit-tested until Phase C — verified structurally here).

## Global Constraints

- Namespace `KwaWingu\Tours`; text domain `kwawingu-tours`. Block namespace `kwawingu/*`.
- **Keys stay server-side** — the browser only ever calls the same-origin, nonce-protected `Rest_Proxy` (`kwawingu/v1`). `/quote` uses the PUBLIC key (`Api_Client::post(path, body, false)`); `/departures` uses the public key via `Api_Client::get`. No key in JS.
- Real `POST /bookings` body fields (verbatim from the backend contract): `tourSlug` OR `departureId`; `adults`,`children`,`infants` (ints); `guestFirstName`,`guestLastName`,`guestEmail`,`guestPhone`; optional `date`,`bookingModel`,`accommodationTier`,`addonSelections[]`,`promoCode`,`specialRequests`,`idempotencyKey`. Response `{ booking, portalToken, portalUrl }`.
- `idempotencyKey` ≤ 30 chars.
- All PHP output escaped; REST handlers map `Api_Exception` → `WP_Error` via the existing `guard()`; nonce on every route (existing `check_nonce`). JS builds DOM via `textContent`/`createElement` (no `innerHTML` with server data); poll capped.
- Targets PHP 7.4+, WP 6.2+. `vendor/bin/phpunit` + `vendor/bin/phpcs -q` stay green (exit 0). Version bumps to 1.1.0 at the end.

---

### Task 1: Proxy routes for departures + quote

**Files:**
- Modify: `includes/Rest_Proxy.php` (add 2 routes + 2 handlers)
- Test: `tests/RestProxyTest.php` (add 2 tests)

**Interfaces:**
- Consumes: `Api_Client::get`, `Api_Client::post`.
- Produces: `Rest_Proxy::handle_departures($request)` → GET `kwawingu/v1/departures?tourSlug=` → `Api_Client::get('/tours/{tourSlug}/departures')` (or `/departures` when no tourSlug); `Rest_Proxy::handle_quote($request)` → POST `kwawingu/v1/quote` → `Api_Client::post('/quote', body, false)` (public key). Both nonce-guarded, exceptions via `guard()`.

- [ ] **Step 1: Add the failing tests to `tests/RestProxyTest.php`** (inside the class)

```php
	public function test_departures_forwards_to_tour_departures(): void {
		$api = \Mockery::mock( \KwaWingu\Tours\Api_Client::class );
		$api->shouldReceive( 'get' )->once()->with( '/tours/safari/departures', array() )
			->andReturn( array( 'data' => array( array( 'id' => 'D1' ) ) ) );
		$req = \Mockery::mock();
		$req->shouldReceive( 'get_param' )->with( 'tourSlug' )->andReturn( 'safari' );
		$out = ( new \KwaWingu\Tours\Rest_Proxy( $api ) )->handle_departures( $req );
		$this->assertSame( 'D1', $out['data'][0]['id'] );
	}

	public function test_quote_forwards_body_with_public_key(): void {
		$api = \Mockery::mock( \KwaWingu\Tours\Api_Client::class );
		$api->shouldReceive( 'post' )->once()
			->with( '/quote', array( 'tourSlug' => 'safari', 'adults' => 2 ), false )
			->andReturn( array( 'data' => array( 'total' => 900000 ) ) );
		$req = \Mockery::mock();
		$req->shouldReceive( 'get_json_params' )->andReturn( array( 'tourSlug' => 'safari', 'adults' => 2 ) );
		$out = ( new \KwaWingu\Tours\Rest_Proxy( $api ) )->handle_quote( $req );
		$this->assertSame( 900000, $out['data']['total'] );
	}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter RestProxyTest`
Expected: FAIL — `handle_departures`/`handle_quote` undefined.

- [ ] **Step 3: Register the routes** in `Rest_Proxy::routes()` — add after the existing `/search` route (using the same `$auth` permission callback):

```php
		register_rest_route( self::NS, '/departures', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_departures' ),
			'permission_callback' => $auth,
			'args'                => array( 'tourSlug' => array( 'sanitize_callback' => 'sanitize_title' ) ),
		) );
		register_rest_route( self::NS, '/quote', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_quote' ),
			'permission_callback' => $auth,
		) );
```

- [ ] **Step 4: Add the handlers** to `Rest_Proxy` (near the other `handle_*` methods)

```php
	/**
	 * List upcoming departures (optionally for one tour).
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function handle_departures( $request ) {
		return $this->guard(
			function () use ( $request ) {
				$slug = (string) $request->get_param( 'tourSlug' );
				if ( '' !== $slug ) {
					return $this->api->get( '/tours/' . rawurlencode( $slug ) . '/departures', array() );
				}
				return $this->api->get( '/departures', array() );
			}
		);
	}

	/**
	 * Price a trip (public key — no booking created).
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function handle_quote( $request ) {
		return $this->guard(
			function () use ( $request ) {
				$body = is_array( $request->get_json_params() ) ? $request->get_json_params() : array();
				return $this->api->post( '/quote', $body, false );
			}
		);
	}
```

- [ ] **Step 5: Run to pass + full suite + phpcs**

Run: `vendor/bin/phpunit --filter RestProxyTest` then `vendor/bin/phpunit` then `vendor/bin/phpcs -q`
Expected: tests PASS; phpcs exit 0.

- [ ] **Step 6: Commit**

```bash
git add includes/Rest_Proxy.php tests/RestProxyTest.php
git commit -m "feat(rest): proxy routes for departures + quote (v1.1 task 1)"
```

---

### Task 2: Booking form — correct fields + departure select + price area

**Files:**
- Modify: `blocks/booking/render-fn.php`
- Test: `tests/blocks/BookingFormRenderTest.php`

**Interfaces:**
- Produces: the on-site form shell with fields the real API needs — first/last name, email, phone, a `<select name="departure" class="kwt-booking__departure">` (populated by view.js), `adults`/`children`/`infants` number inputs, a price area `<p class="kwt-booking__price">`, submit, status. `data-tour` = the tour slug.

- [ ] **Step 1: Update the test `tests/blocks/BookingFormRenderTest.php`** — replace the field assertions to match the new form:

```php
	public function test_renders_booking_form_with_fields(): void {
		$html = kwt_render_booking_form( array(), '' );
		$this->assertStringContainsString( 'id="kwt-book"', $html );
		$this->assertStringContainsString( 'name="firstName"', $html );
		$this->assertStringContainsString( 'name="lastName"', $html );
		$this->assertStringContainsString( 'name="email"', $html );
		$this->assertStringContainsString( 'name="phone"', $html );
		$this->assertStringContainsString( 'name="adults"', $html );
		$this->assertStringContainsString( 'kwt-booking__departure', $html );
		$this->assertStringContainsString( 'kwt-booking__price', $html );
		$this->assertStringContainsString( 'kwt-booking__status', $html );
	}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter BookingFormRenderTest`
Expected: FAIL — `firstName`/`departure`/`price` not present.

- [ ] **Step 3: Rewrite the form in `blocks/booking/render-fn.php`** — replace the `return '<form ...>' ... '</form>';` block with:

```php
		$l_first  = esc_html__( 'First name', 'kwawingu-tours' );
		$l_last   = esc_html__( 'Last name', 'kwawingu-tours' );
		$l_email  = esc_html__( 'Email', 'kwawingu-tours' );
		$l_phone  = esc_html__( 'Mobile money number', 'kwawingu-tours' );
		$l_dep    = esc_html__( 'Departure', 'kwawingu-tours' );
		$l_dep_ph = esc_html__( 'Select a departure…', 'kwawingu-tours' );
		$l_adults = esc_html__( 'Adults', 'kwawingu-tours' );
		$l_child  = esc_html__( 'Children', 'kwawingu-tours' );
		$l_infant = esc_html__( 'Infants', 'kwawingu-tours' );
		$l_book   = esc_html__( 'Book & pay', 'kwawingu-tours' );

		return '<form id="kwt-book" class="kwt-booking" data-tour="' . esc_attr( $tour_slug ) . '">'
			. '<label>' . $l_dep . ' <select name="departure" class="kwt-booking__departure" required>'
			. '<option value="">' . $l_dep_ph . '</option></select></label>'
			. '<label>' . $l_first . ' <input type="text" name="firstName" required /></label>'
			. '<label>' . $l_last . ' <input type="text" name="lastName" required /></label>'
			. '<label>' . $l_email . ' <input type="email" name="email" required /></label>'
			. '<label>' . $l_phone . ' <input type="tel" name="phone" required placeholder="2557…" /></label>'
			. '<label>' . $l_adults . ' <input type="number" name="adults" min="1" value="2" /></label>'
			. '<label>' . $l_child . ' <input type="number" name="children" min="0" value="0" /></label>'
			. '<label>' . $l_infant . ' <input type="number" name="infants" min="0" value="0" /></label>'
			. '<p class="kwt-booking__price" aria-live="polite"></p>'
			. '<button type="submit" class="kwt-btn">' . $l_book . '</button>'
			. '<p class="kwt-booking__status" aria-live="polite"></p>'
			. '</form>';
```

- [ ] **Step 4: Run to pass + full suite + phpcs**

Run: `vendor/bin/phpunit --filter BookingFormRenderTest` then `vendor/bin/phpunit` then `vendor/bin/phpcs -q`
Expected: PASS; phpcs exit 0.

- [ ] **Step 5: Commit**

```bash
git add blocks/booking/render-fn.php tests/blocks/BookingFormRenderTest.php
git commit -m "feat(booking): form fields matching the real API (departure/first/last/pax) (v1.1 task 2)"
```

---

### Task 3: Booking view.js — real payload, departures, live price, portalUrl

**Files:**
- Modify: `blocks/booking/view.js`

**Interfaces:** none (front-end behavior). Not unit-tested until Phase C — verify structure + safety by reading.

- [ ] **Step 1: Rewrite `blocks/booking/view.js`** with the corrected flow

```js
/**
 * kwawingu/booking view: load departures -> live quote -> create booking
 * (correct payload) -> start payment -> poll status -> link to portal.
 */
( function () {
	'use strict';

	function money( n ) {
		return 'TZS ' + ( Number( n ) || 0 ).toLocaleString();
	}

	/** ≤30-char idempotency key. */
	function idemKey() {
		return ( 'wp-' + Date.now().toString( 36 ) + Math.random().toString( 36 ).slice( 2, 8 ) ).slice( 0, 30 );
	}

	function init( form ) {
		var status = form.querySelector( '.kwt-booking__status' );
		var priceEl = form.querySelector( '.kwt-booking__price' );
		var select = form.querySelector( '.kwt-booking__departure' );
		var tourSlug = form.getAttribute( 'data-tour' );

		function pax() {
			return {
				adults: Number( form.adults.value ) || 1,
				children: Number( form.children.value ) || 0,
				infants: Number( form.infants.value ) || 0
			};
		}

		// 1. Load departures for this tour into the select.
		window.kwtProxy.get( '/departures', { tourSlug: tourSlug } ).then( function ( res ) {
			var items = ( res && res.data ) || [];
			items.forEach( function ( d ) {
				var opt = document.createElement( 'option' );
				opt.value = d.id || d.departureId || '';
				var label = ( d.date || d.departureDate || '' );
				if ( d.availableSeats != null ) { label += ' (' + d.availableSeats + ')'; }
				opt.textContent = label;
				select.appendChild( opt );
			} );
		} ).catch( function () { /* leave the select with just the placeholder */ } );

		// 2. Live price when inputs change.
		function refreshPrice() {
			var p = pax();
			var body = { tourSlug: tourSlug, adults: p.adults, children: p.children, infants: p.infants };
			if ( select.value ) { body.departureId = select.value; }
			priceEl.textContent = window.kwtProxy.i18n.loading;
			window.kwtProxy.post( '/quote', body ).then( function ( res ) {
				var data = ( res && res.data ) || res || {};
				var total = data.total || data.perPersonTotal || 0;
				priceEl.textContent = window.kwtProxy.i18n.priceFrom + ' ' + money( total );
			} ).catch( function () { priceEl.textContent = ''; } );
		}
		[ 'change' ].forEach( function ( ev ) {
			select.addEventListener( ev, refreshPrice );
			form.adults.addEventListener( ev, refreshPrice );
			form.children.addEventListener( ev, refreshPrice );
			form.infants.addEventListener( ev, refreshPrice );
		} );

		// 3. Submit: create booking with the REAL payload, then pay + poll.
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			status.textContent = window.kwtProxy.i18n.loading;
			var email = form.email.value.trim();
			var phone = form.phone.value.trim();
			var p = pax();
			var payload = {
				tourSlug: tourSlug,
				adults: p.adults,
				children: p.children,
				infants: p.infants,
				guestFirstName: form.firstName.value.trim(),
				guestLastName: form.lastName.value.trim(),
				guestEmail: email,
				guestPhone: phone,
				idempotencyKey: idemKey()
			};
			if ( select.value ) { payload.departureId = select.value; }

			window.kwtProxy.post( '/bookings', payload ).then( function ( res ) {
				var booking = ( res && ( res.booking || ( res.data && res.data.booking ) ) ) || res || {};
				var ref = booking.ref || booking.bookingReference || res.ref;
				var portalUrl = ( res && ( res.portalUrl || ( res.data && res.data.portalUrl ) ) ) || ( booking && booking.portalUrl );
				if ( ! ref ) { throw new Error( window.kwtProxy.i18n.error ); }
				return window.kwtProxy.post( '/payment-intent', { ref: ref, phone: phone } ).then( function () {
					status.textContent = window.kwtProxy.i18n.checkPhone;
					poll( ref, email, portalUrl, 0 );
				} );
			} ).catch( function ( err ) { status.textContent = err.message || window.kwtProxy.i18n.error; } );
		} );

		function poll( ref, email, portalUrl, tries ) {
			if ( tries > 40 ) { return; }
			setTimeout( function () {
				window.kwtProxy.get( '/booking', { ref: ref, email: email } ).then( function ( res ) {
					var data = res && res.data ? res.data : res;
					var st = data && ( data.status || data.paymentStatus );
					if ( st === 'paid' || st === 'confirmed' || st === 'completed' ) {
						status.textContent = '';
						var msg = document.createElement( 'span' );
						msg.textContent = window.kwtProxy.i18n.paymentReceived + ' ';
						status.appendChild( msg );
						if ( portalUrl ) {
							var a = document.createElement( 'a' );
							a.href = portalUrl;
							a.textContent = window.kwtProxy.i18n.manageBooking;
							status.appendChild( a );
						}
					} else {
						poll( ref, email, portalUrl, tries + 1 );
					}
				} ).catch( function () { poll( ref, email, portalUrl, tries + 1 ); } );
			}, 5000 );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.kwt-booking' ), init );
	} );
} )();
```

- [ ] **Step 2: Verify the full suite still passes (view.js is not covered by unit tests, but nothing else should break)**

Run: `vendor/bin/phpunit` then `vendor/bin/phpcs -q`
Expected: PASS; phpcs exit 0 (JS is excluded from phpcs).

- [ ] **Step 3: Sanity-check the JS by eye** — confirm: payload uses `guestFirstName/guestLastName/guestEmail/guestPhone` + `adults/children/infants` + `departureId` + `idempotencyKey`; departures loaded via `/departures`; price via `/quote`; success links to `portalUrl` via `createElement`/`textContent` (no `innerHTML`); poll capped at 40.

- [ ] **Step 4: Commit**

```bash
git add blocks/booking/view.js
git commit -m "fix(booking): real create-booking payload + departures + live quote + portal link (v1.1 task 3)"
```

---

### Task 4: i18n strings + docs + bump 1.1.0

**Files:**
- Modify: `includes/Assets.php` (add i18n strings the booking view uses)
- Modify: `docs/booking-modes.md`, `README.md`, `readme.txt` (changelog `= 1.1.0 =` + Stable tag), `kwawingu-tours.php` + `includes/Plugin.php` (version), `tests/PluginTest.php`

**Interfaces:**
- Produces: localized strings `priceFrom`, `manageBooking` (used by booking `view.js`).

- [ ] **Step 1: Add the strings to `includes/Assets.php`** — inside the `'i18n' => array( … )` block, add:

```php
					'priceFrom'       => __( 'From', 'kwawingu-tours' ),
					'manageBooking'   => __( 'Manage your booking', 'kwawingu-tours' ),
```

- [ ] **Step 2: Bump version to 1.1.0** — `kwawingu-tours.php` (`Version: 1.1.0` + `KWT_VERSION '1.1.0'`), `includes/Plugin.php` (`const VERSION = '1.1.0'`), `tests/PluginTest.php` (expect `'1.1.0'`), `readme.txt` (`Stable tag: 1.1.0`).

- [ ] **Step 3: Update docs** — `docs/booking-modes.md` On-site section: note it now loads real departures + shows a live price before payment. `readme.txt` changelog:

```
= 1.1.0 =
* On-site booking now uses the live booking API: pick a real departure, see a live price, and book with correct guest details. Fixes a mismatched request that could prevent on-site bookings.
```

`README.md`: no structural change required (mention on-site improvements in the feature note if present).

- [ ] **Step 4: Run the full suite + phpcs**

Run: `vendor/bin/phpunit` then `vendor/bin/phpcs -q`
Expected: PASS (PluginTest expects 1.1.0); phpcs exit 0.

- [ ] **Step 5: Commit**

```bash
git add includes/Assets.php kwawingu-tours.php includes/Plugin.php tests/PluginTest.php readme.txt docs/booking-modes.md README.md
git commit -m "chore(release): booking i18n strings + docs; bump to 1.1.0 (v1.1 task 4)"
```

---

## Self-Review

**Spec coverage (Phase A slice):**
- Proxy `/departures` + `/quote` routes → Task 1. ✓
- Booking form correct fields (guest first/last, adults/children/infants, departure) → Task 2. ✓
- view.js real payload + departures + live quote + `idempotencyKey` + `portalUrl` → Task 3. ✓
- i18n + docs + version → Task 4. ✓

**Placeholder scan:** No TBD/TODO; each code step has complete code. view.js is intentionally not unit-tested (JS harness arrives in Phase C) — Task 3 Step 3 is a concrete eyeball checklist, not a placeholder.

**Type consistency:** `Rest_Proxy::handle_departures`/`handle_quote` match the tests + route registrations; the booking payload field names (`guestFirstName`,`guestLastName`,`guestEmail`,`guestPhone`,`adults`,`children`,`infants`,`departureId`,`tourSlug`,`idempotencyKey`) match the verified backend contract; the form `name=` attributes (`firstName`,`lastName`,`email`,`phone`,`adults`,`children`,`infants`,`departure`) are read by view.js consistently; new i18n keys (`priceFrom`,`manageBooking`) are added in Task 4 and referenced in Task 3's view.js (sequencing note: Task 3 lands the JS that reads them, Task 4 adds them — both merge together in this phase before release; the strings degrade to `undefined`→ shown as empty only if run between tasks, harmless).

**Contract note:** the booking response ref is read defensively (`booking.ref || booking.bookingReference || res.ref`) and `portalUrl` from `res.portalUrl || res.data.portalUrl` because the exact booking sub-object shape isn't pinned in this plan — Phase C's wp-env integration test (against the real API shape) is where this gets nailed down.
