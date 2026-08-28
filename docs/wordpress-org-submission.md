# WordPress.org submission — KwaWingu Tours 1.14.2

Prepared 2026-08-28 the way the plugin review team evaluates a first submission: the release zip
installed on a fresh WordPress 7.1 (Docker `wordpress:latest` + MariaDB), Plugin Check 2.1.0 run
with every category including experimental checks, and the 18 directory guidelines walked one by
one. This file replaces the older `docs/wporg-compliance.md` (status at v1.5.0).

## 1. Plugin Check

### Final result (the artefact reviewers see)

```
$ wp plugin install /dist/kwawingu-tours-1.14.2.zip --activate
$ wp plugin check kwawingu-tours --format=table --include-experimental
Success: Checks complete. No errors found.
$ wp plugin check kwawingu-tours --format=table --categories=plugin_repo
Success: Checks complete. No errors found.
```

Zero errors and zero warnings across all 34 checks (`general`, `plugin_repo`, `security`,
`performance`, experimental included). The settings page (`Settings → KwaWingu Tours`) renders
HTTP 200 as an administrator, the front page and the block editor load, and `wp-content/debug.log`
(`WP_DEBUG` + `WP_DEBUG_LOG` on) stays empty through activation, the checks and those page loads.

### Before / after

Plugin Check was first run on the development checkout (bind-mounted, so it also saw files that
never ship). Rows are counted per Plugin Check line item.

| Scope | Before | After |
|---|---|---|
| Whole development tree | 49 errors, 54 warnings | 0 errors, 30 warnings (all in files `.distignore` excludes: `tests/`, `tests-integration/`, `.github`, `.claude`, `CLAUDE.md`, `graphify-out/`, `.wp-env.json`, phpcs/phpunit configs, `.gitignore`, `.distignore`, `.phpunit.result.cache`) |
| Shipped paths of the development tree | 30 errors, 46 warnings | 0 / 0 |
| Release zip installed on a fresh site | (first build) 0 errors, 1 warning `missing_composer_json_file` | 0 / 0 |

Before, by code (shipped paths only):

| Type | Code | Count |
|---|---|---|
| ERROR | `missing_direct_file_access_protection` | 24 |
| ERROR | `WordPress.Security.EscapeOutput.ExceptionNotEscaped` | 5 |
| ERROR | `WordPress.WP.EnqueuedResources.NonEnqueuedScript` | 1 |
| WARNING | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | 25 |
| WARNING | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound` | 13 |
| WARNING | `WordPress.DB.SlowDBQuery.slow_db_query_meta_query` | 3 |
| WARNING | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound` | 1 |
| WARNING | `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound` | 1 |
| WARNING | `ai_instruction_directory` (`.claude`), `github_directory` (`.github`), `unexpected_markdown_file` (`CLAUDE.md`) | 3 |

### Fixes

| Finding | Fix |
|---|---|
| `missing_direct_file_access_protection` × 24 — every `blocks/*/render.php` and `blocks/*/render-fn.php` | `if ( ! defined( 'ABSPATH' ) ) { exit; }` after the file docblock in all 24 files. |
| `ExceptionNotEscaped` × 5 — `includes/Api_Client.php:118,132,136` | The remote API's `message` and `error.code` are passed through `esc_html()` and the status is cast to `int` before `Api_Exception` is constructed, so an uncaught exception cannot print markup the API could have injected. `esc_html()` does not double-encode, so the owner notice (which escapes again) is unchanged. The one path that relays a business refusal to the visitor as JSON (`Api_Status::visitor_message`, consumed by `textContent` in the view scripts) decodes with `wp_specialchars_decode()` so apostrophes do not surface as entities. Pinned by `tests/ApiClientTest.php::test_api_error_message_is_html_escaped_when_thrown`, which was run against the unfixed file and fails there. |
| `NonEnqueuedScript` — `includes/Booking.php:100` | The Widget booking mode's embed tag is now built by core's `wp_get_script_tag()` (attributes escaped by core, `async` boolean handled) instead of a string literal. The script itself is KwaWingu's own `widget.js` on the operator's hosted booking site — see guideline 8 below. Verified on the fresh site: `<script async data-operator="acme" data-tour="safari" src="https://tours.kwawingu.com/widget.js"></script>`. |
| `NonPrefixedFunctionFound` × 13 — `kwt_render_*` in every `render-fn.php`, `kwt_destination_url` | Plugin Check derives the accepted prefixes from the slug, so the three-letter `kwt_` is not one. Global helpers renamed to `kwawingu_tours_render_*` / `kwawingu_tours_destination_url` (call sites in `includes/Shortcodes.php` and `tests/blocks/*` updated). |
| `NonPrefixedVariableFound` × 25 — `$kwt_attrs` / `$kwt_content` in every `render.php`, `$kwt_autoload` in the main file | Renamed `$kwawingu_tours_*`. (Variables inside function scope are not globals and were never flagged.) |
| `load_plugin_textdomainFound` — `kwawingu-tours.php:46` | Call removed. `Requires at least: 6.2`, and WordPress has loaded a wp.org plugin's language packs by slug since 4.6. `Text Domain` / `Domain Path` headers and the shipped `.pot` stay. |
| `missing_composer_json_file` (zip, first build) | `vendor/` held only Composer's autoloader for the plugin's own 24 classes — there are no runtime dependencies. The main file now registers a PSR-4 `spl_autoload_register` for `includes/`, `vendor/` is `.distignore`d, and the deploy workflow no longer runs Composer. The dev checkout still loads `vendor/autoload.php` when present (test suite). |
| `.claude`, `.github`, `CLAUDE.md`, hidden files, `phpcs.xml.dist`, `phpunit*.xml.dist`, `graphify-out/`, `patchwork.json`, `webpack.config.js`, `.phpunit.result.cache` | Added to `.distignore` (the 10up deploy action honours it; the local zip build applies the same file). Confirmed absent from the zip. |

Also done while here: `uninstall.php` additionally deletes `kwt_api_status` and the
`kwt_live_catalog_last_good` transient (guideline 18 / plugin_uninstall check).

### Justified `phpcs:ignore` annotations (false positives)

Plugin Check honours inline annotations, so each carries its reason in the source.

1. **`WordPress.DB.SlowDBQuery.slow_db_query_meta_query`** — `includes/Sync.php:208`,
   `includes/Importer.php:74`, `blocks/tours-grid/render-fn.php:29`. Each is an exact-match
   lookup on one plugin-owned meta key (`kwt_id`, `kwt_pattern`, `kwt_type`) over this site's own
   tour/page posts — a catalogue of at most a few hundred rows, `posts_per_page => 1` for the two
   lookups, never a user-driven search. The sniff cannot tell a bounded key lookup from a
   free-text meta search; the warning is the generic "meta queries can be slow" advice and does
   not apply at this size.
2. **`WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound`** —
   `blocks/tour-detail/render-fn.php:70`. `apply_filters( 'the_content', … )` applies core's own
   content filter to the tour body (so shortcodes, `wpautop`, embeds and other plugins' filters
   run). The sniff reads any `apply_filters` as *defining* a hook; nothing is defined here.
3. **`WordPress.Security.EscapeOutput`** on the single `echo` in each `blocks/*/render.php`
   (pre-existing). Each render function returns fully escaped HTML (every value goes through
   `esc_html` / `esc_attr` / `esc_url` / `wp_kses_post` at the point it is built, covered by the
   block render tests); escaping the assembled markup again would strip it.

## 2. Guideline review (18 directory guidelines)

| # | Guideline | Finding |
|---|---|---|
| 1 | GPL-compatible | `License: GPL-2.0-or-later` + `License URI` in `kwawingu-tours.php`; `License: GPLv2 or later` in `readme.txt`; `composer.json` / `package.json` say `GPL-2.0-or-later`. No bundled third-party code: the zip's PHP is all the plugin's own, and the editor bundles are compiled from the plugin's own `blocks/*/index.js` (`@wordpress/*` packages are externals resolved to core's scripts, not bundled). Icons, banners and screenshots are the vendor's own work. **Pass.** |
| 2 | Developer responsibility | First submission; nothing previously removed. No third-party SDK bundled. **Pass.** |
| 3 | Stable version from wp.org | `Stable tag: 1.14.2` = `Version: 1.14.2` = `Plugin::VERSION` = `KWT_VERSION` = `package.json`. GitHub releases will mirror the directory (`deploy.yml` publishes to SVN on the same tag). No self-updater. **Pass.** |
| 4 | Human-readable code | `build/*/index.js` are minified `@wordpress/scripts` output; the readable sources `blocks/*/index.js` and `blocks/*/edit.js` ship in the zip beside them, and `readme.txt` now has a `== Source code and build ==` section naming the repository and the build command. No obfuscation. **Pass.** |
| 5 | No trialware | No licence key, plan check, trial, expiry or quota exists anywhere in the plugin (`grep -rni "licen\|premium\|upgrade\|trial" includes blocks` finds only the wording of the admin notice). The only "entitlement" concept is `Api_Exception::KIND_ENTITLEMENT`, which *classifies a 403 the KwaWingu API returned* so the owner is told what to fix; it gates nothing locally. Synced content stays published either way. **Pass.** |
| 6 | SaaS integrations | The plugin is a client for the vendor's own SaaS (KwaWingu Tours). The service does real work — catalogue, live pricing/availability, search, calculator, booking, payments — and the readme's Description and FAQ now state plainly, in three places, that a KwaWingu Tours operator account with the (paid) Developer API add-on is required, that the plugin itself has no paid features, and that the entitlement decision is made server-side. The 403 handling in `Api_Status` is the *reporting* of the service's answer, not a gate. **Pass — this is the one a reviewer will read closely; the wording is written for that.** |
| 7 | No data collection without consent | Nothing is sent on activation or on a timer except the catalogue sync the operator configures by entering their own API credentials (consent is the configuration, per the named-service exception). No analytics or telemetry. Visitor data leaves the site only when a visitor submits the on-site booking or Inquiry Form, to the operator's own account, and the readme's `== External services ==` lists every endpoint, direction and payload. **Pass.** |
| 8 | No remotely loaded executable code | All PHP/JS/CSS executed on the site ships in the zip (`build/` committed; `assets/js/kwt-proxy.js`; `blocks/*/view.js`, `grid.js`). Nothing is `eval`ed or fetched-then-run; there is no self-update. The one external script is `https://tours.kwawingu.com/widget.js`, loaded **only** when the operator chooses the *Widget* booking mode and places a Book Button — the plugin's own SaaS loading its own embed, the exception the guideline names, and now disclosed under External services. Images downloaded during sync (`imagedelivery.net`) are data, stored in the media library. **Pass.** |
| 9 | No illegal / dishonest behaviour | None. **Pass.** |
| 10 | No forced external links | No front-end links or credits are injected; the only outbound links are the operator's own hosted booking/destination pages, which are the feature. **Pass.** |
| 11 | No admin hijacking | One admin notice (`Api_Status::render_notice`), shown **only** while the API is refusing the site for a reason the owner must fix (entitlement, key, slug), only to `manage_options`, and it clears itself on the next successful call; retryable failures show only on the plugin's own settings page. No dashboard widgets, no advertising, no nag, no external admin menu links (Plugin Check `external_admin_menu_links` passes). **Pass.** |
| 12 | No readme spam | Five tags, no keyword lists, no affiliate links, no competitor names. **Pass.** |
| 13 | Use bundled libraries | No jQuery/React copies; `@wordpress/*` are externals. **Pass.** |
| 14 | SVN is a release repository | Development on GitHub; `deploy.yml` pushes tagged releases only. **Pass.** |
| 15 | Increment versions | See 3. **Pass.** |
| 16 | Complete at submission | Every block, shortcode, booking mode and the setup wizard is implemented and covered (176 PHPUnit, 26 Jest, wp-env integration tests in CI). No "(Coming soon)" text remains in `readme.txt` or `README.md`. **Pass.** |
| 17 | Trademarks | Display name **"KwaWingu Tours"**, slug `kwawingu-tours`: the vendor's own brand, ≥ 5 alphanumerics, no WordPress/WP/Woo/Meta terms, not generic (the invented brand term leads). Plugin Check's `trademarks` check passes. Only *KwaWingu*'s own trademark appears; "Cloudflare" is mentioned in the readme only to name the CDN in the disclosure. **Pass — see owner decisions.** |
| 18 | Directory rights / respect users | Uninstall removes all options, transients and cron hooks; synced tour/destination content is intentionally left (it is the operator's site content) and the readme says so. **Pass.** |

### Readme header audit

`Contributors: kwawingu` · `Stable tag: 1.14.2` · `Tested up to: 7.1` (current per api.wordpress.org)
· `Requires at least: 6.2` · `Requires PHP: 7.4` · license + URI present · `Requires Plugins`
deliberately absent (no dependencies) · plugin name identical in header and readme · short
description ≤ 150 chars · ten screenshots captioned and present in `.wordpress-org/`.

## 3. The submission form

Answers for each confirmation the "Add your plugin" form asks for:

| Form item | Answer |
|---|---|
| Plugin name | KwaWingu Tours |
| Zip | `dist/kwawingu-tours-1.14.2.zip` (121 KB, 119 files, top-level folder `kwawingu-tours/`) |
| I have read the guidelines / developer FAQ | Yes |
| The plugin is GPL-compatible and all included code/assets are | Yes — GPL-2.0-or-later, nothing bundled |
| The name does not use a trademark I do not own | Yes — "KwaWingu" is our own brand; no other trademark in the name |
| The plugin does not load external code / is not a wrapper for a remote service | It is a documented client for our own service (guideline 6); no executable code is downloaded (guideline 8) |
| External services are documented | Yes — `== External services ==` in `readme.txt` |
| The plugin is complete and not a placeholder | Yes |

### Ready-to-paste "Additional Information"

> KwaWingu Tours is the free, GPL-2.0-or-later WordPress client for KwaWingu Tours
> (https://tours.kwawingu.com), the tour-operator platform we operate. A tour operator who already
> keeps their catalogue, departures, seat inventory, reviews and payment gateway in their KwaWingu
> account installs this plugin to build their own WordPress site on that data: the catalogue is
> synced into a native Tour post type (editable, indexable, images copied into the media library),
> twelve server-rendered blocks with matching shortcodes read prices and availability live, and
> booking is handed to the operator's account (redirect, embedded widget, or an on-site form that
> goes through a same-origin, nonce-protected REST proxy so API keys never reach the browser).
>
> The plugin requires a KwaWingu Tours operator account with the Developer API add-on, which is a
> paid feature of our service — in the same way a payment-gateway plugin requires a merchant
> account. We want to be explicit about this so the 403 handling in the code is not read as
> trialware: the plugin contains no licence key, plan check, trial or quota, and disables nothing
> locally. The entitlement decision is made entirely on our servers; when API access is off, our
> API answers `403 api_access_required`, the plugin keeps the already-synced tours published and
> shows the site owner one admin notice with the fix. The readme's Description, FAQ and External
> services section all state this.
>
> External endpoints (all listed in the readme): our Developer API at
> tours.kwawingu.com/api/v1 (server-to-server); the operator's hosted booking pages on
> tours.kwawingu.com and, only in Widget mode, our own widget.js embed; and imagedelivery.net
> (Cloudflare Images), from which the sync downloads the operator's own photos once into the
> media library. No analytics or telemetry of any kind. Nothing is sent on activation.
>
> Source, tests (176 PHPUnit, 26 Jest, wp-env integration in CI) and build instructions:
> https://github.com/KwaWingu/kw-wp-plugin — the block bundles under build/ are compiled from the
> readable blocks/*/index.js sources that ship alongside them. Documentation:
> https://github.com/KwaWingu/kw-wp-plugin/tree/main/docs and https://tours.kwawingu.com.
> Plugin Check 2.1.0 (all categories, experimental included) reports no errors or warnings on
> the submitted zip installed on WordPress 7.1.
>
> Contact: info@kwawingu.com.

## 4. Decisions for the owner

1. **Contributors / uploading account.** `readme.txt` says `Contributors: kwawingu`. The
   wordpress.org username that submits the zip becomes the committer and *must* appear in
   `Contributors`. If the account that will upload is not literally `kwawingu`, change that line
   (or register `kwawingu` first, with `info@kwawingu.com`). The SVN username also goes into the
   `SVN_USERNAME` / `SVN_PASSWORD` GitHub secrets that `deploy.yml` uses.
2. **Display name.** "KwaWingu Tours" passes every naming rule and matches the header, readme,
   blocks and the product. The reviewers occasionally ask a brand-only name to say what it does;
   if they do, the fallback that keeps the slug and the brand first is
   **"KwaWingu Tours – Tour Website & Booking Sync"**. Do not change the slug either way.
3. **Legal pages.** The readme links `https://tours.kwawingu.com/legal/terms` and
   `/legal/privacy` (the routes in `kw-frontend/app/sitemap.ts`); confirm both are live before
   submitting, since reviewers open them.
4. **Support email.** `info@kwawingu.com` is quoted in the Additional Information text; the
   Author URI stays `https://tours.kwawingu.com`.

## 5. How this was verified (repeatable)

```
# fresh WordPress, plugin installed from the zip, no bind mount of the dev tree
docker compose -f docker-compose.zip.yml up -d        # wordpress:latest + mariadb:11, port 8090
wp core install …; wp plugin install plugin-check --activate
wp plugin install /dist/kwawingu-tours-1.14.2.zip --activate
wp plugin check kwawingu-tours --format=table --include-experimental
# repo gates after every fix
vendor/bin/phpunit          # 176 tests, 645 assertions
vendor/bin/phpcs -q         # exit 0
npm run test:js             # 26 tests
npm run build               # build/ unchanged against git
```

Zip build: `.distignore` applied to the working tree (same file the 10up deploy action uses),
zipped with `kwawingu-tours/` as the only top-level folder. `dist/` is gitignored.
