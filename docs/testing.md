# Testing

Three layers, run in CI (`.github/workflows/ci.yml`):

## 1. PHP unit tests — `vendor/bin/phpunit`
Pure-PHP, no WordPress/Docker (Brain\Monkey + Mockery mock WP functions). Covers Settings, Api_Client, Rest_Proxy, Sync, CPT registration, block render functions, Seo, Media, Branding, Importer, Setup_Wizard, Assets. **61 tests.** Run anywhere with `composer install`.

## 2. Coding standards — `vendor/bin/phpcs -q`
WPCS + PHPCompatibilityWP (7.4 floor). Blocking in CI. `build/`, `node_modules/`, `tests/`, and `*.js` are excluded.

## 3. JS unit tests — `npm run test:js`
Jest (via `@wordpress/scripts`). Covers the on-site booking payload builder (`blocks/booking/view.test.js`) — a regression guard on the exact field mapping (`guestFirstName`/… + `adults/children/infants`, not `customer`/`pax`) that was broken in 1.0 and fixed in 1.1. Run with `npm ci && npm run test:js`.

## Editor block bundles — `npm run build`
Compiles `blocks/<block>/index.js` (edit components) → `build/<block>/index.js` (+ `.asset.php`). CI rebuilds and asserts the committed bundles are up to date (`git diff --exit-code build/`).

## Integration tests (wp-env) — requires Docker
`.wp-env.json` sets up a real WordPress + this plugin. Intended for tests that need a live WP DB (sync writing real posts, CPT rewrite rules, REST routes end-to-end, **block front-end render** — the layer that would have caught the render.php-echo regression). These need Docker, so they run on CI runners / a local Docker host, not in the constrained dev sandbox. To run locally:

```bash
npx wp-env start
# then a phpunit config targeting the WP test suite (to be added)
```

Writing the wp-env integration suite is the next testing task; it is tracked separately because it can only be authored+verified against a running WordPress.

## Manual QA
The block **editor UI** (React `edit.js`) can't be unit-tested — see [editor-qa.md](editor-qa.md) for the hands-on checklist.
