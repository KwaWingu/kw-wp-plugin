# KwaWingu Tours for WordPress

Build a fast, SEO-friendly tour-operator website on your [KwaWingu Tours](https://tours.kwawingu.com) data. This plugin syncs your tour catalog into native WordPress content, gives you blocks + a one-click setup wizard, and gets you a live site in minutes.

> **Requires a paid KwaWingu Developer API add-on.** The plugin is free and GPL-licensed; it connects to your own KwaWingu account using your operator slug + API key. Enable API access in your KwaWingu dashboard (Developer API).

## Features

- **Native content sync** — your tours become a `Tour` custom post type: real URLs, editable in WordPress, great for SEO.
- **SEO** — JSON-LD (Product + AggregateRating) and Open Graph tags are injected automatically on every tour page.
- **Media** — tour cover images are sideloaded into your WordPress media library so they're served from your own domain.
- **Blocks** — Tours Grid, Tour Detail, Featured Tours, Book Button, Reviews, Destinations Grid (+ classic-theme shortcodes for all six).
- **One-click setup** — the wizard pulls your branding, scaffolds Home / Tours / About / Contact pages, and imports your tours.
- **Booking** — redirect guests to your hosted KwaWingu booking page, or embed the KwaWingu widget so they book without leaving your site. On-site API mode is on the roadmap for v0.4.
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
| KwaWingu Book Button | `[kwawingu_booking id="0" label=""]` | Booking link / widget embed |
| KwaWingu Reviews | `[kwawingu_reviews id="0"]` | Rating + guest reviews for a tour |
| KwaWingu Destinations Grid | `[kwawingu_destinations limit="12"]` | Grid of destination cards |

Full reference: [docs/blocks.md](docs/blocks.md).

## Documentation

- [Getting started](docs/getting-started.md)
- [Blocks & shortcodes](docs/blocks.md)
- [Booking modes](docs/booking-modes.md)

## Contributing

PRs welcome. Run tests with `composer install && vendor/bin/phpunit`. Coding standard: `vendor/bin/phpcs`. Please keep changes covered by tests.

## License

GPL-2.0-or-later.
