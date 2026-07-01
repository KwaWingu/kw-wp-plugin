# KwaWingu Tours for WordPress — Design

**Date:** 2026-07-01
**Status:** Approved design
**Repo:** `kw-wp-plugin` (standalone, open-source, GPL-2.0-or-later) — sibling of `kw-tours`
**Plugin slug:** `kwawingu-tours`
**Distribution:** WordPress.org plugin directory + GitHub

## Purpose

A free WordPress plugin that gets a tour operator a **complete, SEO-strong, self-updating website in minutes** by syncing their KwaWingu Tours catalog into native WordPress content, shipping blocks + block patterns, and a one-click setup wizard that scaffolds a full multi-page site auto-branded from their KwaWingu profile. Booking is delegated to KwaWingu (configurable modes). The plugin is free; it connects to the operator's own KwaWingu account.

**Chosen approach:** *Plugin + one-click starter site*, data **synced into native WP custom post types** (Approach A: CPT + Gutenberg blocks + block-pattern importer), booking mode **configurable** (redirect / widget / on-site API).

### Reality it respects — the paid API
The KwaWingu `/api/v1` developer API is a **paid per-operator add-on**. The plugin itself is free/GPL; it consumes the operator's paid API. The setup wizard states this plainly and links to the KwaWingu dashboard Developer page to enable access. A non-entitled operator's key returns `403 { error: { code: "api_access_required" } }` — the plugin surfaces this as an actionable admin notice.

**Credentials:** operator **slug + public API key** (read) for catalog/browsing. **On-site booking mode** additionally requires the **private key** (write), used server-side only.

## API surface consumed

Base: `https://tours.kwawingu.com/api/v1/{slug}`. Auth header: `X-API-Key`. List envelope `{ data, page, size, total, hasMore }`; error `{ error: { code, message } }`.

- **Read (public key):** `GET /site` (full bundle: profile + tours + destinations + gallery + team + reviews), `GET /profile` (branding), `GET /tours` (+filters), `GET /tours/{slug}`, `GET /tours/{slug}/availability` (live, never synced), `GET /departures`, `GET /addons`, `GET /packages`, `GET /offers`, `GET /faqs`, `GET /blog`, `GET /destinations`, `GET /gallery`, `GET /team`, `GET /reviews`, `GET /categories`, `GET /policies`, `GET /calculator/options`, `GET /search`, `GET /openapi.json`.
- **Write (private key, on-site booking only):** `POST /quote`, `POST /bookings/{ref}/payment-intent`, `POST /inquiries`, `POST /newsletter`, `POST /tours/{slug}/reviews`.
- **Hosted booking (redirect mode):** `tours.kwawingu.com/{slug}` and `/{slug}/tours/{tourSlug}`, `/{slug}/calculator`.
- **Widget mode:** `<script src="https://tours.kwawingu.com/widget.js" data-operator="{slug}" async></script>`.

## Repo structure & standards

```
kw-wp-plugin/
  kwawingu-tours.php          # bootstrap, constants, autoload
  uninstall.php               # option/CPT/transient cleanup
  readme.txt                  # WP.org readme (stable tag, tested-up-to, external-service disclosure)
  composer.json               # dev tooling only (PHPCS, WPCS)
  includes/
    Settings.php  Api_Client.php  Sync.php  Cpt.php  Seo.php  Booking.php  Setup_Wizard.php
  blocks/                     # one dir per block (block.json + edit.js + render.php)
  patterns/                   # PHP-registered block patterns (home, tours, tour detail, destinations, about, contact)
  languages/                  # .pot
  assets/                     # css/js (source + built), icons
  tests/                      # WP-PHPUnit + wp-env
  .github/workflows/          # lint (PHPCS/WPCS) + tests + build zip
```

**Standards:** WordPress Coding Standards (PHPCS + WPCS), full escaping/sanitization/nonces + capability checks, i18n (text domain `kwawingu-tours`), no bundled minified libraries without source, external-service disclosure in `readme.txt`. Targets PHP 7.4+, WordPress 6.2+.

## Data model (native custom post types)

- **CPT `kwt_tour`** — one post per KwaWingu tour. `post_title` / `post_content` / `post_excerpt` from the tour; meta: `kwt_id`, `kwt_slug`, `price`, `duration_days`, `difficulty`, `type`, `rating`, `gallery[]`, `inclusions`, `itinerary`, `booking_url`, `raw_json`, `synced_at`, `kwt_content_locked`. Featured image = cover.
- **CPT `kwt_destination`** (lightweight) + **taxonomy `kwt_tour_type`** (safari, trek, day-trip, …) for filterable archives + clean URLs (`/tours/`, `/tours/{slug}/`, `/tour-type/safari/`).
- Operators can **enrich** synced posts in WP; sync never clobbers operator-authored prose (see Sync engine → content-preservation).

## Sync engine

- **Source:** `GET /site` for the full sync (one call); `GET /tours` paginated as fallback. `GET /tours/{slug}/availability` is **never** synced — always fetched live at booking time.
- **Trigger:** WP-Cron (default hourly, configurable) + a manual **"Sync now"** button. No real-time push: the KwaWingu developer webhooks cover `booking/payment/inquiry/review`, **not** catalog changes (documented limitation; a future `tour.updated` webhook would slot in here without redesign).
- **Upsert by `kwt_id`:** create/update the matching `kwt_tour`; **soft-unpublish** (set to draft) tours that disappear from the API rather than deleting — preserves URLs and operator edits.
- **Content-preservation:** once an operator edits a synced post, set `kwt_content_locked`; subsequent syncs update only structured meta (price, gallery, booking URL, availability link), never the operator's prose.
- **Media:** sideload cover + gallery into the WP media library once (dedupe by source-URL hash) so images are local (LCP/SEO), with an option to hot-link instead for low-storage hosts.
- **Resilience:** each sync run is try/catch-wrapped, partial-failure tolerant, and logged to a status panel (last run, counts, errors). A `403 api_access_required` surfaces the "enable API access" admin notice.

## Blocks, patterns & the starter site

- **Blocks** (`block.json` + server-rendered `render.php` reading CPTs — server-side render = SEO + no API call on page view): `Tours Grid` (filter by type/price/duration), `Tour Detail`, `Book Button`, `Availability Calendar`, `Trip Calculator`, `Tour Search`, `Destinations Grid`, `Reviews`, `Featured Tours`. **Shortcode equivalents kept** for classic themes (bridges the existing `kwawingu-tours-plugin` users).
- **Patterns:** prebuilt page layouts — Home (hero + featured tours + destinations + reviews + CTA), Tours archive, Tour detail, Destinations, About, Contact.
- **One-click importer (in the wizard):** creates the actual WP pages from patterns, sets the front page, builds a primary nav menu, and (for classic themes) registers archive/single templates via `template_include`. Result: a full site immediately after setup.

## Booking modes (setting — all three)

A "Booking mode" setting, globally and overridable per Book button:
1. **Redirect (default):** Book → `tours.kwawingu.com/{slug}/tours/{tourSlug}` (live availability, 4-step flow, Snippe payment). Zero payment code in WP; always-correct seats; fastest to ship.
2. **Widget embed:** renders KwaWingu `widget.js` in-page (native browsing, embedded booking).
3. **On-site via API (advanced, later phase):** in-page availability + quote + Snippe `payment-intent` using the **private key** server-side; polls booking status. Flagged advanced; requires the private key; shipped after modes 1 & 2 are solid.

## Setup wizard & auto-branding

5 steps: (1) enter slug + public key → **test connection** (`GET /profile`); (2) pull branding from `/profile` (logo → site logo, `brandPrimary`/`brandAccent` → CSS variables/theme colors, description → tagline); (3) choose booking mode; (4) **one-click import** starter pages + run the first sync; (5) done → "View your site." Re-runnable.

## SEO

Server-rendered blocks (real HTML, no JS-only content); `kwt_tour` public with clean permalinks + in sitemaps (native WP + Yoast/RankMath compatible); **JSON-LD** per tour (`TouristTrip` / `Product` + `AggregateRating` from reviews); Open Graph / Twitter tags; canonical URLs; image alts from the API. Local media = fast LCP.

## Settings, security & compliance

- **Settings page:** connection (slug / keys, test button), sync (interval, media mode, "Sync now", status log), booking mode, branding overrides, uninstall behavior.
- **Security:** keys stored in options; the **private key is never exposed to the front end or REST** — used server-side only; all output escaped, all input sanitized; nonces + capability checks on every admin action; all API calls server-side.
- **WP.org compliance:** GPL-2.0-or-later; external-service (KwaWingu API) disclosure + privacy note in `readme.txt`; full i18n; no phone-home beyond the operator's own API; `uninstall.php` cleans options/transients (retains or removes CPT content per the user's choice).

## Testing

- **PHPUnit (wp-env):** `Api_Client` (mocked HTTP), `Sync` upsert/lock/soft-delete logic, CPT registration, block render output, SEO JSON-LD, settings sanitization.
- **CI:** PHPCS/WPCS lint + tests + "build zip" artifact.
- **Manual:** fresh WP install → wizard → live-site smoke test.

## Phasing

1. **v0.1** — scaffold, settings, `Api_Client`, CPTs, sync engine, "Sync now".
2. **v0.2** — blocks + patterns + one-click importer + wizard + auto-brand (redirect booking).
3. **v0.3** — SEO (JSON-LD, sitemaps), widget booking mode, media sideloading.
4. **v0.4** — on-site API booking, i18n polish, WordPress.org submission prep.

## Out of scope

- Managing KwaWingu data *from* WordPress (the plugin is read-first; edits happen in the KwaWingu dashboard). WP-side enrichment is additive content only.
- Payment/PCI handling in WordPress beyond delegating to Snippe via the KwaWingu API (on-site mode starts a payment intent; it never touches card data).
- A bundled WordPress theme (the plugin is theme-agnostic; block patterns provide layout).
- Real-time catalog push (no `tour.updated` webhook exists yet; sync is scheduled polling).
