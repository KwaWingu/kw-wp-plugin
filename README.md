# KwaWingu Tours for WordPress

Build a fast, SEO-friendly tour-operator website on your [KwaWingu Tours](https://tours.kwawingu.com) data. This plugin syncs your tour catalog into native WordPress content, gives you blocks + a one-click setup wizard, and gets you a live site in minutes.

> **Requires a paid KwaWingu Developer API add-on.** The plugin is free and GPL-licensed; it connects to your own KwaWingu account using your operator slug + API key. Enable API access in your KwaWingu dashboard (Developer API).

## Features

- **Native content sync** — your tours become a `Tour` custom post type: real URLs, editable in WordPress, great for SEO.
- **SEO** — JSON-LD (Product + AggregateRating) and Open Graph tags are injected automatically on every tour page.
- **Media** — tour cover images and gallery photos are sideloaded into your WordPress media library so they're served from your own domain.
- **Blocks** — Tours Grid, Tour Detail, Featured Tours, Book Button, Reviews, Destinations Grid, Tour Search, Trip Calculator, On-site Booking Form, Gallery, Availability Calendar Inquiry Form (+ classic-theme shortcodes for all twelve).
- **One-click setup** — the wizard pulls your branding, scaffolds Home / Tours / About / Contact pages, and imports your tours.
- **Booking** — redirect guests to your hosted KwaWingu booking page, embed the KwaWingu widget, or use On-site mode for a fully in-WordPress booking + Snippe payment flow (requires private API key; keys are proxied server-side and never reach the browser).
- **Operator notifications + leads** — on-site bookings can trigger an email to the operator and save guest details as a Lead in WordPress admin. Guest confirmations are still sent by KwaWingu.
- **Internationalization** — ships with a `.pot` template; every user-facing string uses the `kwawingu-tours` text domain. Translate via Loco Translate or GlotPress.
- **Keeps your edits** — once you edit a synced tour, sync stops overwriting your content.

## Install

**From WordPress.org:** search "KwaWingu Tours" in Plugins → Add New.

**From source:**
```bash
git clone https://github.com/KwaWingu/kw-wp-plugin.git wp-content/plugins/kwawingu-tours
```
Then activate the plugin in WordPress. Nothing to build: the plugin autoloads its own classes and the compiled block bundles are committed. `composer install` is only for running the test suite.

## Configure

1. In your KwaWingu dashboard, enable the **Developer API** (paid add-on — without it every call answers `403 api_access_required`) and create a **publishable** key with your WordPress site address as its **allowed origin**. For the Inquiry Form or on-site booking also create a **secret** key with `inquiries:write`, `quotes:write`, `bookings:write`, `payments:write`, `bookings:read`.
2. **Settings → KwaWingu Tours** — enter your operator slug + public API key (and the private key if needed), choose a booking mode, save.
3. **Settings → KwaWingu Setup** — click **Build my site**.
4. Visit your site.

Pointing at a staging/self-hosted KwaWingu: `define( 'KWT_SITE_BASE', 'https://staging.example' );` in `wp-config.php` (API root becomes `…/api/v1`; `KWT_API_BASE` overrides the API root alone).

See [docs/getting-started.md](docs/getting-started.md).

## Blocks & shortcodes

| Block | Shortcode | Purpose |
|---|---|---|
| KwaWingu Tours Grid | `[kwawingu_tours limit="12" type=""]` | Grid of tours |
| KwaWingu Tour Detail | `[kwawingu_tour id="0"]` | Single tour |
| KwaWingu Featured Tours | `[kwawingu_featured heading="" limit="3"]` | Highlighted set |
| KwaWingu Book Button | `[kwawingu_booking id="0" label=""]` | Booking link / widget embed |
| KwaWingu Reviews | `[kwawingu_reviews id="0"]` | Rating + guest reviews for a tour |
| KwaWingu Destinations Grid | `[kwawingu_destinations limit="12"]` | Grid of destination cards |
| KwaWingu Tour Search | `[kwawingu_search]` | Live tour search form |
| KwaWingu Trip Calculator | `[kwawingu_calculator slug=""]` | Multi-step trip price calculator |
| KwaWingu On-site Booking | `[kwawingu_booking_form id="0" slug=""]` | In-WordPress booking + Snippe payment |
| KwaWingu Gallery | `[kwawingu_gallery id="0" columns="3"]` | Tour photo gallery |
| KwaWingu Inquiry Form | `[kwawingu_inquiry heading="" tour_slug=""]` | Inquiry form → your KwaWingu inbox |
| KwaWingu Availability Calendar | `[kwawingu_availability id="0" slug=""]` | Month grid of departures with seats/sold-out status |

Full reference: [docs/blocks.md](docs/blocks.md).

## Documentation

- [Getting started](docs/getting-started.md)
- [Blocks & shortcodes](docs/blocks.md)
- [Booking modes](docs/booking-modes.md)

## Contributing

PRs welcome. Run tests with `composer install && vendor/bin/phpunit`. Coding standard: `vendor/bin/phpcs`. Please keep changes covered by tests.

## License

GPL-2.0-or-later.
