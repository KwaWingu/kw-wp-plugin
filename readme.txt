=== KwaWingu Tours ===
Contributors: kwawingu
Tags: tours, travel, tour operator, booking, safari
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build a tour-operator website fast on your KwaWingu Tours data. Sync your catalog into WordPress, add blocks, and go live in minutes.

== Description ==

KwaWingu Tours turns your KwaWingu operator account into a fast, SEO-friendly WordPress site. It syncs your tour catalog into native WordPress content (Tours & Destinations), gives you blocks and a one-click setup wizard, and lets guests book — without you maintaining a separate site.

This plugin connects to the KwaWingu Tours developer API using your operator slug and API key. **The Developer API is a paid add-on on your KwaWingu account** — the plugin is free and GPL-licensed; it talks to your own account.

**What you get:**

* **Native content sync** — your tours become a `Tour` custom post type: real URLs, editable in WordPress, great for SEO. Removed tours are set to Draft, never deleted; once you edit a tour, sync stops overwriting your content.
* **Blocks with editor UI** — Tours Grid, Tour Detail, Featured Tours, Book Button, Reviews, Destinations, Search, Trip Calculator, and Booking. Each has a live preview and sidebar controls in the block editor. Classic-theme shortcodes included.
* **Brand-true styles** — blocks are styled with your KwaWingu brand colours out of the box.
* **One-click setup wizard** — auto-brands from your profile, scaffolds starter pages (Home, Tours, About, Contact), and imports your tours.
* **Three booking modes** — *Redirect* (send guests to your hosted KwaWingu booking page), *Widget* (embed the KwaWingu booking widget), or *On-site* (a full in-page booking + mobile-money payment flow). Your API keys never reach the browser — a same-origin server-side proxy holds them.
* **SEO** — JSON-LD (Product / AggregateRating) + Open Graph on tour pages; local media for fast pages.
* **Internationalization** — translation-ready (`.pot` included).

== Installation ==

1. Install from **Plugins → Add New** (search "KwaWingu Tours"), or upload the plugin zip, or `git clone` into `wp-content/plugins/` and run `composer install --no-dev`.
2. Activate the plugin.
3. Go to **Settings → KwaWingu Tours**, enter your operator slug + public API key, choose a booking mode, and save. (Enable API access in your KwaWingu dashboard first — it is a paid add-on.)
4. Go to **Settings → KwaWingu Setup** and click **Build my site**.
5. Visit your site.

== Frequently Asked Questions ==

= Do I need a KwaWingu account? =
Yes. This plugin is a client for the KwaWingu Tours platform. You need an operator account with the Developer API add-on enabled, and your operator slug + public API key.

= Is the plugin itself paid? =
No — the plugin is free and GPL-licensed. It connects to your paid KwaWingu Developer API, similar to how a payment-gateway plugin connects to your paid payment account.

= Are my API keys safe? =
Yes. Keys are stored server-side and never sent to the browser. Interactive features (search, calculator, on-site booking) call a same-origin, nonce-protected WordPress REST endpoint that forwards to the KwaWingu API on the server. The private key is only used for booking writes, server-side.

= Which booking mode should I use? =
Start with **Redirect** — zero setup, always-correct availability, payment handled by KwaWingu. Use **Widget** to embed booking in a page, or **On-site** for a fully in-page booking + mobile-money flow (needs your private key configured).

= Will it work with my theme? =
Yes. Blocks are theme-agnostic and server-rendered; classic themes can use the `[kwawingu_*]` shortcodes.

= How do tours stay up to date? =
Tours re-sync automatically (hourly by default; configurable) and via a "Sync now" button. Your manual edits to a tour are preserved on future syncs.

== Screenshots ==

1. Settings → connect your KwaWingu account (slug + API key, booking mode).
2. The one-click setup wizard building your starter site.
3. A tour catalog page (Tours Grid block).
4. A single tour page with the Book Button.
5. The block editor showing a tour block's sidebar controls + live preview.
6. The on-site booking form (departure, guest details, live price).

(Screenshot images are added from a live install; see `.wordpress-org/README.md`.)

== External services ==

This plugin connects to the KwaWingu Tours API (https://tours.kwawingu.com) to fetch your tour catalog, availability, pricing, and related content, and — in on-site booking mode — to create bookings and start payments on your behalf. It uses the operator slug and API keys you configure. Data sent: your API key (in a request header) and the parameters for the content or booking requested (including guest details a visitor enters in the on-site booking form). No visitor personal data is sent during catalog sync. See https://tours.kwawingu.com for the Terms and the KwaWingu privacy policy.

== Changelog ==

= 1.8.0 =
* Operator notifications: get an email when a guest books on-site through your site, and keep their details as a Lead in WordPress. (Guest confirmations are still sent by KwaWingu.)

= 1.7.0 =
* Availability Calendar block: a month grid of a tour's departures with seats/sold-out status (and [kwawingu_availability] shortcode).

= 1.6.0 =
* Gallery: tour gallery images are imported into your media library and shown with a new Gallery block (and [kwawingu_gallery] shortcode).

= 1.5.0 =
* WordPress.org submission prep: refreshed readme (description, installation, FAQ, screenshots), brand assets (icon + banner), and translation (.pot) tooling.

= 1.4.0 =
* Testing: JS unit tests (Jest) for the booking payload, wired into CI alongside PHPUnit + coding standards + the block build.

= 1.3.0 =
* Block editor: every block now has a live preview + sidebar controls (limits, filters, headings) in the WordPress editor.

= 1.2.0 =
* Front-end styles for all blocks (cards, grids, forms, reviews) using your brand colours.

= 1.1.0 =
* On-site booking now uses the live booking API: pick a real departure, see a live price, and book with correct guest details. Fixes a mismatched request that could prevent on-site bookings.

= 1.0.0 =
* On-site booking mode: book + pay (mobile money) without leaving your site.
* Live blocks: Search, Trip Calculator, On-site Booking (via a secure server-side proxy — your API keys never reach the browser).
* Internationalization (.pot) + text domain loading.
* Hardening for WordPress.org: input/output security review, ABSPATH guards, blocking coding-standards check.

= 0.3.0 =
* SEO: JSON-LD (Product/AggregateRating) + Open Graph on tour pages.
* Media: tour cover images are imported into your media library.
* New blocks: Reviews, Destinations.
* Widget booking mode (embed the KwaWingu booking widget).

= 0.2.0 =
* Blocks: Tours Grid, Tour Detail, Featured Tours, Book Button (+ shortcodes).
* One-click setup wizard: auto-brand from your profile, scaffold pages, import tours.
* Redirect booking mode.
* Sync safeguard: an empty catalog response no longer drafts your tours.

= 0.1.0 =
* Initial release: settings, API client, Tours/Destinations post types, and scheduled catalog sync.

== Upgrade Notice ==

= 1.1.0 =
Fixes on-site booking against the live API — upgrade if you use on-site booking mode.
