# Testing

Three layers, run in CI (`.github/workflows/ci.yml`):

## 1. PHP unit tests — `vendor/bin/phpunit`
Pure-PHP, no WordPress/Docker (Brain\Monkey + Mockery mock WP functions). Covers Settings, Api_Client, Rest_Proxy, Sync (tours + destinations), CPT registration, block render functions, Seo, Media (byte-sniffing sideload), Branding, Importer, Setup_Wizard, Assets, Api_Status, Shortcodes (view-script enqueue, id/slug), the booking-lookup proxy (`X-Portal-Token` header forwarded, `?email=` only as fallback), and the block stylesheet (brand defaults that are not self-referencing custom properties; honeypot hidden). **175 tests.** Run anywhere with `composer install`.

## 2. Coding standards — `vendor/bin/phpcs -q`
WPCS + PHPCompatibilityWP (7.4 floor). Blocking in CI. `build/`, `node_modules/`, `tests/`, and `*.js` are excluded.

## 3. JS unit tests — `npm run test:js`
Jest (via `@wordpress/scripts`). Covers the on-site booking payload builder (`blocks/booking/view.test.js`) — a regression guard on the exact field mapping (`guestFirstName`/… + `adults/children/infants`, not `customer`/`pax`) that was broken in 1.0 and fixed in 1.1 — the availability-calendar month grid (`blocks/availability-calendar/grid.test.js`), the calculator and quote totals (`blocks/calculator/view.test.js`, `quoteTotal` in the booking test), the post-booking poll (`bookingLookupRequest` sends the portal token as a header and falls back to email only without one; `paymentReceived` reads balance-vs-total, never the upper-case lifecycle status), and the proxy client's stale-nonce recovery (`assets/js/kwt-proxy.test.js`: a `403` triggers exactly one `/nonce` refresh + retry, never a loop; caller headers survive the retry and cannot replace the nonce). Run with `npm ci && npm run test:js`.

## Editor block bundles — `npm run build`
Compiles `blocks/<block>/index.js` (edit components) → `build/<block>/index.js` (+ `.asset.php`). CI rebuilds and asserts the committed bundles are up to date (`git diff --exit-code build/`).

## Integration tests (wp-env) — requires Docker (CI)
A real-WordPress suite in `tests-integration/`, bootstrapped via `wp-phpunit` (`tests-integration/bootstrap.php`, config `phpunit-integration.xml.dist`). Covers the layers pure-PHP unit tests can't:

- **Block front-end render** (`BlockRenderTest`) — every `kwawingu/*` block emits real markup via `do_blocks` (the check that catches the "render.php defines a function but never echoes" regression that bit v0.2 and v1.7).
- **CPT registration + REST routes** (`CptAndRestTest`) — `kwt_tour`/`kwt_destination`/`kwt_lead` registered correctly; the `kwawingu/v1` proxy routes exist and reject a request with no REST nonce.

Run with Docker:
```bash
npm run env:start          # npx @wordpress/env start
npm run test:integration   # runs phpunit-integration.xml.dist inside wp-env
npm run env:activate       # dev site only: activate the plugin at http://localhost:8888

# The plugin is mounted at wp-content/plugins/kwawingu-tours via `mappings` in .wp-env.json —
# NOT via `plugins: ["."]`, which mounts under the checkout folder's name (kw-wp-plugin in CI)
# and broke `--env-cwd`. The test bootstrap requires the plugin file itself, so no activation
# is needed for phpunit.
```
CI runs this as the `integration` job.

> **Status:** this suite is **authored but has not been executed in the dev sandbox** (no Docker). It follows the standard `@wordpress/env` + `wp-phpunit` pattern; the first CI run may need a small env tweak (e.g. `WP_PHPUNIT__DIR` / test-DB wiring) — treat a first-run failure as harness config, not a plugin bug, and adjust `tests-integration/bootstrap.php` / the CI job accordingly.

## Manual QA
The block **editor UI** (React `edit.js`) can't be unit-tested — see [editor-qa.md](editor-qa.md) for the hands-on checklist.
