=== KwaWingu Tours ===
Contributors: kwawingu
Tags: tours, travel, tour operator, booking, safari
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.14.1
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
3. Go to **Settings → KwaWingu Tours**, enter your operator slug + public API key, choose a booking mode, and save. (Enable API access in your KwaWingu dashboard first — it is a paid add-on: without an active Developer API entitlement every API call answers `403 api_access_required` and the plugin tells you so in wp-admin.)
   * The **public API key** is a *publishable* key (`kw_pk_…`). KwaWingu only issues one with an **allowed origin**, so enter your WordPress site address (e.g. `https://www.example.com`) as the allowed origin when you create it in Dashboard → Developers.
   * The **private API key** (`kw_sk_…`) is only needed for the Inquiry Form and on-site booking. Grant it the `inquiries:write`, `quotes:write`, `bookings:write`, `payments:write` and `bookings:read` scopes.
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

= Can I point the plugin at a staging or self-hosted KwaWingu? =
Yes. Add `define( 'KWT_SITE_BASE', 'https://staging.example' );` to `wp-config.php` — the Developer API root becomes `KWT_SITE_BASE/api/v1` and the hosted booking links and dashboard link follow it. `KWT_API_BASE` can still be defined separately to override only the API root.

= How do tours stay up to date? =
Tour content re-syncs automatically (hourly by default; configurable), via a "Sync now" button, and instantly when KwaWingu pushes a change to the Instant resync endpoint. Price, currency and sold-out state are not synced at all — they are read live from your account on every page view, with the last synced values as a fallback if the API is unreachable. Your manual edits to a tour are preserved on future syncs.

== Screenshots ==

1. Settings → KwaWingu Tours: operator slug, public API key, booking mode, private key for on-site booking, operator notifications and lead capture, and the catalog sync status.
2. The one-click setup wizard after "Build my site": branding pulled from your account, Home / Tours / About / Contact scaffolded, tours imported.
3. The block inserter: all twelve KwaWingu blocks (Tours Grid, Tour Detail, Featured Tours, Book Button, Reviews, Destinations Grid, Tour Search, Trip Calculator, On-site Booking, Gallery, Availability Calendar, Inquiry Form).
4. Tours Grid block on the front end — synced tours with their cover photos from your own media library and live prices.
5. Tour Detail block — cover, duration, difficulty, live price, next departure and description, with the Book button.
6. Availability Calendar block — a month grid of a tour's departures with seats left.
7. On-site Booking block — a real departure picked, guest details, and the live quote from your account before "Book & pay".
8. Trip Calculator block — the party and nights entered, with the estimate computed by KwaWingu.
9. Tour Search block — live results linking to the synced tour pages on your site.
10. Inquiry Form block — an inquiry that lands in your KwaWingu inbox and is kept as a Lead in WordPress.

== External services ==

This plugin connects to the KwaWingu Tours API (https://tours.kwawingu.com) to fetch your tour catalog, availability, pricing, and related content, and — in on-site booking mode — to create bookings and start payments on your behalf. It uses the operator slug and API keys you configure. Data sent: your API key (in a request header) and the parameters for the content or booking requested (including guest details a visitor enters in the on-site booking form). No visitor personal data is sent during catalog sync. Current prices and availability are requested from the same API when a tour is displayed (the response is reused for 60 seconds), and KwaWingu can call back to this site's signed `kwt/v1/resync` endpoint to trigger a catalog sync; neither carries visitor data. See https://tours.kwawingu.com for the Terms and the KwaWingu privacy policy.

== Changelog ==

= 1.14.1 =
Release follow-ups, found while capturing the WordPress.org screenshots from a live install.

* **Fix: every block button was invisible** (white text on a transparent background) and the brand colour never reached a block. The stylesheet set `--kwt-primary: var( --kwt-primary, … )` on the block roots — a custom property referencing itself is a cycle, which the browser treats as invalid — so "Book & pay", "Estimate", "Send inquiry" and the Book button had no background. Brand defaults are now plain values that your Branding colours override.
* **Fix: the Inquiry Form had no styles and its spam-trap field was visible.** A visitor who filled the stray box in had their inquiry silently dropped as a bot. The form is now styled like the booking form and the honeypot is off-screen.
* **On-site booking polls with the guest's portal token.** After "Book & pay" the form checks payment status with the `X-Portal-Token` the API returns when the booking is created — sent as a header, so it never lands in access logs — instead of the `?email=` lookup the API retires on 2027-07-01. The email lookup remains only as a fallback when no token was issued.
* **Fix: the payment poll could never finish.** It compared the booking status against `paid`/`confirmed`/`completed`, but the API's status is upper-case and a booking is `CONFIRMED` the moment it is created, before any money. The poll now reads the balance against the total (exact minor units) and stops once a payment has landed.
* Translation template regenerated: 181 strings (it was empty), including the compiled block editor strings WordPress.org's language packs are keyed on.
* WordPress.org assets: ten screenshots, banners and icons.

= 1.14.0 =
Found by running the plugin end-to-end against a real KwaWingu backend (WordPress in Docker, every shortcode and block, the editor, the proxy, the paid gate). Nine things did not work:

* **Fix: no image was ever imported.** KwaWingu serves photos from Cloudflare Images (`imagedelivery.net/…/public`, no file extension) and `media_sideload_image()` refuses a URL without one, so covers and galleries silently never reached the media library. Images are now downloaded and identified by their bytes.
* **Fix: the Destinations Grid was always empty.** Nothing ever wrote a Destination post. Destinations are now synced from your catalog (`/site`) alongside tours.
* **Fix: Tour Search never showed results.** The API returns `tours`, the block read `data`; results now also link to the synced tour page on your site.
* **Fix: the interactive shortcodes were dead on classic themes.** `[kwawingu_search]`, `[kwawingu_calculator]`, `[kwawingu_booking_form]`, `[kwawingu_availability]` and `[kwawingu_inquiry]` rendered forms without their scripts, because WordPress only loads a block's view script for the block.
* **Fix: `[kwawingu_booking_form id=…]` and `[kwawingu_availability id=…]` ignored `id`** (and `slug`); the form bound to the page it sat on.
* **Fix: the on-site form's live price was never shown** — `POST /quote` needs the private key, and the response field is `totalAmount`.
* **Fix: the Trip Calculator showed the per-person figure as the total** and always labelled it TZS; it now shows `grandTotal` in your currency.
* **Fix: the inquiry's preferred date was dropped** (sent as `date`, the API reads `preferredDate`).
* **Fix: listing all departures (`?tourSlug=`) returned a 502** — a WordPress REST quirk with `sanitize_title` as a sanitize callback.
* Gallery block: a Tour Post ID control in the editor, so it can show a tour's gallery on any page.
* Block titles now match the documentation ("KwaWingu Destinations Grid", "KwaWingu On-site Booking").
* Calculator and search show the API's visitor-safe message ("not available at the moment") instead of a generic error when the paid API is off.
* `KWT_SITE_BASE` constant to point a site at a staging/self-hosted KwaWingu; proxy failures are written to the debug log when `WP_DEBUG` is on.

= 1.13.0 =
* **API refusals now say what to do — to the right person.** When the KwaWingu Developer API refuses a request, wp-admin shows the site owner a notice naming the fix: enable the paid API add-on, correct the public key, or correct the operator slug. Visitors never see a status code or anything about plans and keys — interactive blocks show a quiet "not available at the moment" instead. The notice clears itself on the next successful call.
* **Rate limits and outages keep showing your prices.** If KwaWingu answers 429 or 5xx (or cannot be reached), tour cards and tour pages keep the last live prices and availability the API returned (for up to a day) and retry a minute later, instead of dropping to the last synced values.
* **Catalog sync errors** on the settings page use the same plain-language messages.
* **Blocks are verified in CI**: a test now asserts every shortcode has a matching Gutenberg block with a `block.json` (API v3, editor script, server render) and a committed editor bundle, so a block can no longer silently vanish from the inserter.
* Tested up to WordPress 7.1.

= 1.12.0 =
* **Prices and availability are now read live.** Tour cards, tour pages and their structured data show the current price, currency and sold-out state on every page view instead of whatever the last scheduled sync stored. If the API cannot be reached, the last synced values are shown — never an error or a blank price.
* **Instant resync.** A new signed endpoint (`POST /wp-json/kwt/v1/resync`) lets KwaWingu tell your site to re-sync within seconds of you editing a tour. Copy the endpoint URL and signing secret from **Settings → KwaWingu Tours → Instant resync** into your KwaWingu dashboard.
* Fix: changing the sync interval in settings now takes effect immediately. Previously the new interval was ignored until the plugin was deactivated and reactivated.
* Fix: tour prices are read from the API's `basePriceAdult` field, so synced prices are no longer always zero. Tour currency, category, gallery and rating fields are now mapped correctly too.
* Prices render in the operator's own currency rather than always TZS.

= 1.11.0 =
* Inquiry Form block (`kwawingu/inquiry-form`) and `[kwawingu_inquiry]` shortcode: visitors submit inquiries directly from your WordPress site; the operator gets an email notification and a Lead is captured in WordPress.
* Contact page starter pattern updated to embed the Inquiry Form block.
* Server-side proxy route `POST /wp-json/kwawingu/v1/inquiry` — nonce-guarded, rate-limited, forwards via private API key to your KwaWingu inbox.

= 1.10.0 =
* Fix: interactive blocks (Search, Trip Calculator, Booking, Availability Calendar) now recover automatically on full-page-cached sites, where an expired security token previously broke them.
* Release automation: publishing to the WordPress.org plugin directory on version tags.

= 1.9.0 =
* Testing: WordPress integration tests (wp-env) verifying block front-end render, CPT registration, and REST routes — run in CI.

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

= 1.14.1 =
Buttons in every block were invisible and the inquiry form was unstyled with a visible spam-trap field. Recommended for everyone.

= 1.14.0 =
Images, destinations, search, the calculator, the live price and every interactive shortcode were broken against the real API. Upgrade and run Sync now once to import your photos and destinations.

= 1.12.0 =
Prices and availability now come from your account live instead of going stale between syncs, and the sync interval setting finally takes effect when you change it. Recommended for everyone.

= 1.10.0 =
Keeps the booking and search blocks working on sites with full-page caching or a CDN. Recommended for all cached sites.

= 1.1.0 =
Fixes on-site booking against the live API — upgrade if you use on-site booking mode.
