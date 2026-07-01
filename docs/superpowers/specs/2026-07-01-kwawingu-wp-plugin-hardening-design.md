# KwaWingu Tours WordPress Plugin — post-1.0 hardening program

**Date:** 2026-07-01
**Status:** Approved design
**Repo:** `kw-wp-plugin` (v1.0.0 shipped; this program is v1.1 → v1.4)

## Purpose

Close the real gaps that remain after the 1.0.0 completion: the on-site booking flow uses a *guessed* API contract (it would fail against the live API), the blocks have no editor UI or styles, the JS layer is untested, and the plugin isn't yet WordPress.org-listing-ready. Four independent phases, each its own spec-slice → plan → build → merge → tag.

## Ground truth — the real `POST /api/v1/{slug}/bookings` contract

Verified against the kw-tours backend (`PublicBookingApiController.createBooking` → `PublicBookingService.createPublicBooking`). The request body (`Map<String,Object>`) reads these fields:

- **Departure selection:** `departureId` **or** (`tourSlug` + `date`). Optional `holdId` (seat hold).
- **Pax:** `adults`, `children`, `infants` (integers) — NOT `pax`.
- **Guest:** `guestFirstName`, `guestLastName`, `guestEmail`, `guestPhone` (+ optional `guestNationality`, `guestWhatsapp`) — NOT a nested `customer` object.
- **Options:** `bookingModel`, `accommodationTier`, `addonSelections[]` (`{addonId, quantity}`), `promoCode`, `specialRequests`, `idempotencyKey`, `utmSource/Medium/Campaign`.
- **Response:** `{ booking, portalToken, portalUrl }` — the booking ref is inside `booking`; `portalUrl` is where the guest manages/pays.

Related read endpoints the plugin should use before booking: `GET /departures` (+ `GET /tours/{slug}/availability`) to pick a valid departure, and `POST /quote` to show a live price. Payment is then started with `POST /bookings/{ref}/payment-intent` (already proxied).

The plugin's current on-site `view.js` posts `{tourSlug, customer:{name,email,phone}, date, pax}` — **wrong shape** → Phase A rewrites it.

## Phase A — Fix on-site booking against the real API (correctness; do first)

- Extend `Rest_Proxy` with read routes `GET /departures` (→ `/departures` or `/tours/{slug}/departures`) and `POST /quote` (→ `/quote`, public key) so the browser can list departures + price a trip through the key-hiding proxy.
- Rewrite `blocks/booking/render-fn.php` + `view.js`: step 1 load departures for the tour and let the guest pick one (or pick a date that maps to a departure); step 2 collect `adults/children/infants` + guest fields (`guestFirstName/…`), show a live price via `/quote`; step 3 create the booking with the CORRECT payload incl. a generated `idempotencyKey`; step 4 start payment-intent, poll status, then link to the returned `portalUrl` on success.
- Map the WP form fields → the exact API field names. Keep the create→pay→poll structure + textContent DOM safety + capped poll from 1.0.0.
- Tests: proxy route tests (departures/quote forwarding) in PHPUnit; the corrected payload shape asserted where testable.

## Phase B — Editor UX + block styles

- Adopt **`@wordpress/scripts`** (`package.json` + `wp-scripts build`) — a real JS build. Ship both source (`src/`) and built (`build/`) assets; WP.org allows this with source present.
- Each block gets an `edit.js`: a `ServerSideRender` preview (reuse the PHP render) + `InspectorControls` panel exposing its attributes (Tours Grid: limit/type; Featured: heading/limit; Reviews/Tour Detail: postId context; Search: placeholder; Booking: tourSlug). Register via the built `editorScript`.
- Ship a real stylesheet: a `style.css` (front-end, `style` in block.json or a shared enqueue) styling the `kwt-*` classes (cards, grid, search, calculator, booking form, reviews stars) using the brand tokens (`--kwt-primary`/`--kwt-accent` from Branding) + an `editorStyle` so the editor preview matches.
- CI gains a `wp-scripts build` step + commits/artifacts the built assets.

## Phase C — Testing

- **Jest** (`@wordpress/scripts test-unit-js` or a standalone jest) covering the `view.js` files — especially the booking create→pay→poll flow (mock `window.kwtProxy`, assert the correct payload is posted, poll stops on completion, errors surface). Runs locally (Node available) + in CI.
- **wp-env integration tests** (PHPUnit against a real WP DB) for: sync upsert against real posts, CPT registration + rewrite, block registration + actual front-end render (would have caught the render.php regression), REST proxy routes end-to-end. These require Docker → **CI-only** (documented; not run in this sandbox).
- CI runs: PHPUnit (unit) + Jest always; wp-env integration on CI runners with Docker.

## Phase D — WordPress.org submission prep

- `assets/` (WP.org SVN `assets/`, kept out of the plugin zip): a placeholder **`icon.svg`** + **`banner-772x250`/`banner-1544x500`** (brand-colored placeholders), and a **screenshots** section in `readme.txt`. Real screenshot PNGs need a live site — structured + flagged for the user to add before submission.
- `readme.txt`: expand the FAQ, keep "Tested up to" current, verify the external-services disclosure + changelog.
- A `composer`/npm script wrapping `wp i18n make-pot` to regenerate `languages/kwawingu-tours.pot` from source (keeps strings from drifting).
- Final pre-submission pass: Plugin Check plugin (`wp plugin check`) if runnable, escaping/nonce/i18n sweep, version + readme consistency.

## Delivery

Per phase: feature branch → subagent-driven TDD tasks → per-task reviews → whole-branch review → merge to `main` → local tag (`v1.1.0`/`v1.2.0`/`v1.3.0`/`v1.4.0`; user pushes). PHPUnit + PHPCS stay green; Jest green from Phase C on.

## Constraints (locked)

- **No Docker in the dev sandbox** → wp-env integration tests are written but run only in CI.
- **No live site in the sandbox** → screenshots are placeholders; real PNGs added by the user pre-submission.
- Node (v22) IS available → `@wordpress/scripts` build + Jest run locally.
- Keep the 1.0.0 security posture: keys server-side only (proxy), nonce + rate-limit on writes, escaped output, DOM-safe JS (no `innerHTML` with server data).

## Out of scope (this program)

Standalone availability-*calendar* visual block; gallery sideloading + gallery block; marketing/abandoned-cart emails; multisite-specific features; a full block theme.
