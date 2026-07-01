=== KwaWingu Tours ===
Contributors: kwawingu
Tags: tours, travel, tour operator, booking, safari
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build a tour-operator website fast on your KwaWingu Tours data. Sync your catalog into WordPress, add blocks, and go live in minutes.

== Description ==

KwaWingu Tours syncs your tour catalog from your KwaWingu Tours account into native WordPress content (Tours & Destinations), so you get fast, SEO-friendly pages you can extend like any other post.

This plugin connects to the KwaWingu Tours developer API using your operator slug and API key. The Developer API is a paid add-on on your KwaWingu account.

**v0.2 adds:**

* **Blocks** — Tours Grid, Tour Detail, Featured Tours, Book Button (server-rendered, SEO-friendly). Classic-theme shortcodes included.
* **One-click setup wizard** — auto-brands from your KwaWingu profile (logo + colours), scaffolds starter pages (Home, Tours, About, Contact), and imports your tours.
* **Redirect booking mode** — Book buttons link guests to your hosted KwaWingu booking page. Availability and payment are handled by KwaWingu.
* **Sync safeguard** — an empty catalog response no longer drafts your tours.

== External services ==

This plugin connects to the KwaWingu Tours API (https://tours.kwawingu.com) to fetch your tour catalog, availability, and related content, using the operator slug and API key you configure. Data sent: your API key (in a request header) and query parameters for the content requested. No visitor personal data is sent by this plugin during catalog sync. See https://tours.kwawingu.com (Terms) and the KwaWingu privacy policy.

== Changelog ==

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
