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
A responsive grid of destination cards synced from your KwaWingu catalog.
- `limit` (number, default 12)

## Gallery — `kwawingu/gallery` / `[kwawingu_gallery]`
Displays a tour's photo gallery sourced from images imported into your media library.
- `postId` / `id` (number) — the tour to show the gallery for; defaults to the current post in a tour template.
- `columns` (number, default 3) — number of columns in the gallery grid.

## Availability Calendar — `kwawingu/availability-calendar` / `[kwawingu_availability]`
A month grid of a tour's upcoming departures showing available seats and sold-out status.
- `tourSlug` (block) / `slug` (shortcode) — the tour whose departures to show; defaults to the current tour's slug in a tour template.

Prices display in TZS. Styling uses the `kwt-*` CSS classes and the `--kwt-primary` / `--kwt-accent` custom properties set from your KwaWingu branding.

## Inquiry Form — `kwawingu/inquiry-form` / `[kwawingu_inquiry]`
Captures a visitor inquiry and forwards it to your KwaWingu booking inbox. The operator receives an email notification; no guest-facing email is sent from WordPress (the KwaWingu platform handles that).
- `heading` (string, default "Send us an inquiry")
- `tourSlug` / `tour_slug` (shortcode) (string, optional) — pre-fill the tour of interest

Includes a honeypot field that silently blocks bot submissions without a CAPTCHA. Submissions are rate-limited (20 per 10 minutes per visitor IP).

## Interactive blocks & full-page caching

The interactive blocks (Search, Trip Calculator, Booking, Availability Calendar) call your data through a same-origin REST proxy (`/wp-json/kwawingu/v1/*`) so the API keys stay on the server and never reach the browser. Each request carries a WordPress REST nonce (`wp_rest`).

On sites with **full-page caching** (a CDN or a page cache plugin), the nonce is baked into the cached HTML and expires after ~12–24h, which would otherwise make these blocks return `403` on cached pages. To stay resilient, the proxy exposes a public `GET /wp-json/kwawingu/v1/nonce` endpoint: the browser client (`kwt-proxy.js`) refreshes the nonce and retries **once** on a `403`.

**Security note:** the `/nonce` endpoint is intentionally public, which slightly weakens the CSRF value of the nonce for these proxy routes. This is an accepted trade-off — the write routes (`/bookings`, `/payment-intent`) are additionally per-visitor rate-limited, and the upstream KwaWingu API independently validates the operator key and every booking payload. No privileged action is reachable through the proxy.
