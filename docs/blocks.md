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

## Reviews — `kwawingu/reviews` / `[kwawingu_reviews]`
Displays the rating and guest reviews for a tour.
- `postId` / `id` (number) — the tour to show reviews for; defaults to the current post in a tour template.

## Destinations Grid — `kwawingu/destinations-grid` / `[kwawingu_destinations]`
A responsive grid of destination cards. Destinations are synced from your KwaWingu catalog (the `/site` bundle) into `kwt_destination` posts on every catalog sync, with their cover image imported into the media library.
- `limit` (number, default 12)

## Gallery — `kwawingu/gallery` / `[kwawingu_gallery]`
Displays a tour's photo gallery sourced from images imported into your media library.
- `postId` / `id` (number) — the tour to show the gallery for; defaults to the current post in a tour template.
- `columns` (number, default 3) — number of columns in the gallery grid.

## Availability Calendar — `kwawingu/availability-calendar` / `[kwawingu_availability]`
A month grid of a tour's upcoming departures showing available seats and sold-out status.
- `tourSlug` (block) / `slug` or `id` (shortcode; `id` is the tour post ID) — the tour whose departures to show; defaults to the current tour's slug in a tour template.

The grid is rendered in the page from your live departures (`GET /api/v1/{slug}/tours/{tour}/departures`, fetched through the same-origin proxy below) — there is no iframe and nothing is embedded from tours.kwawingu.com, so it inherits your theme and works on any host. An older, pre-1.0 shortcode named `[kwawingu_calendar]` that iframed a hosted calendar page no longer exists; use `[kwawingu_availability]`.

Prices display in TZS. Styling uses the `kwt-*` CSS classes and the `--kwt-primary` / `--kwt-accent` custom properties set from your KwaWingu branding.

## Inquiry Form — `kwawingu/inquiry-form` / `[kwawingu_inquiry]`
Captures a visitor inquiry and forwards it to your KwaWingu booking inbox. The operator receives an email notification; no guest-facing email is sent from WordPress (the KwaWingu platform handles that).
- `heading` (string, default "Send us an inquiry")
- `tourSlug` / `tour_slug` (shortcode) (string, optional) — pre-fill the tour of interest

Includes a honeypot field that silently blocks bot submissions without a CAPTCHA. Submissions are rate-limited (20 per 10 minutes per visitor IP).

## On-site Booking — `kwawingu/booking` / `[kwawingu_booking_form]`
The in-page booking form (see [booking-modes.md](booking-modes.md)). Loads the tour's real departures and shows a live quote (`POST /quote`, private key) before payment.
- `tourSlug` (block) / `slug` or `id` (shortcode) — the tour to book; defaults to the current tour in a tour template.

## Shortcodes on classic themes
The five interactive shortcodes (`[kwawingu_search]`, `[kwawingu_calculator]`, `[kwawingu_booking_form]`, `[kwawingu_availability]`, `[kwawingu_inquiry]`) enqueue their block's view script themselves — WordPress only does that for the block — so they work on classic themes without any extra setup.

## Interactive blocks & full-page caching

The interactive blocks (Search, Trip Calculator, Booking, Availability Calendar) call your data through a same-origin REST proxy (`/wp-json/kwawingu/v1/*`) so the API keys stay on the server and never reach the browser. Each request carries a WordPress REST nonce (`wp_rest`).

On sites with **full-page caching** (a CDN or a page cache plugin), the nonce is baked into the cached HTML and expires after ~12–24h, which would otherwise make these blocks return `403` on cached pages. To stay resilient, the proxy exposes a public `GET /wp-json/kwawingu/v1/nonce` endpoint: the browser client (`kwt-proxy.js`) refreshes the nonce and retries **once** on a `403`.

**Security note:** the `/nonce` endpoint is intentionally public, which slightly weakens the CSRF value of the nonce for these proxy routes. This is an accepted trade-off — the write routes (`/bookings`, `/payment-intent`) are additionally per-visitor rate-limited, and the upstream KwaWingu API independently validates the operator key and every booking payload. No privileged action is reachable through the proxy.

## When the API refuses a request

The Developer API is a paid, per-operator add-on, so a refusal is usually something only the site owner can fix. The plugin separates the two audiences:

| API answer | Site owner sees (wp-admin) | Visitor sees |
|---|---|---|
| `403 api_access_required` (plan does not include API access, or it lapsed) | Site-wide notice naming the fix, with a link to the KwaWingu dashboard | Synced tour content as normal; interactive blocks show "Online booking is not available at the moment" |
| `401 api_key_required` / `api_key_invalid` | Site-wide notice: check the public API key in Settings | Same as above |
| `403 api_key_scope_missing` | Site-wide notice: the key lacks the scope (private key for on-site booking) | Same as above |
| `404 not_found` | Site-wide notice: check the operator slug | Same as above |
| `429 rate_limited`, `5xx`, unreachable | Notice on the plugin's settings page only ("nothing to do") | The last live prices/availability the API returned (kept up to a day), retried a minute later; interactive blocks say "busy right now, try again" |
| Business refusals (`price_changed`, `hold_unavailable`, …) | — | The API's own message, verbatim |

The notice clears itself on the next successful call. Logged-in administrators using an interactive block on the front end also get the owner sentence in the proxy error's `data.owner_message`.

