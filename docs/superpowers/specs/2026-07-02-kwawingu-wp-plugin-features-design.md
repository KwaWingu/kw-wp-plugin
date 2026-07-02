# KwaWingu Tours WordPress Plugin — feature program (v1.6 → v1.9)

**Date:** 2026-07-02
**Status:** Approved design
**Repo:** `kw-wp-plugin` (v1.5.0 shipped)

## Purpose

Four additional features on top of the complete, WP.org-prepped v1.5 plugin, each an independent phase (spec-slice → plan → build → merge → tag).

## v1.6 — Gallery sideloading + Gallery block

- **Sideload gallery images.** v0.3 stored a tour's gallery as the `kwt_gallery` meta (array of URLs) but only sideloaded the *cover*. Extend `Media` with `ingest_gallery( int $post_id, array $urls ): array` that (in `sideload` mode) imports each not-yet-ingested URL into the media library, tracks it via a `kwt_gallery_ids` meta (attachment IDs) keyed to avoid re-ingesting, and is best-effort (never throws). `hotlink` mode keeps using the raw URLs. `Sync::write_meta` calls it when a `Media` is present (like the cover).
- **Gallery block** `kwawingu/gallery` — server-rendered from `kwt_gallery_ids` (sideload) or `kwt_gallery` URLs (hotlink); a responsive image grid with `esc_url`/`esc_attr` on every image; empty when no gallery. Split render-fn/template pattern + shortcode `[kwawingu_gallery]` + an `edit.js` (ServerSideRender + a "columns" RangeControl).

## v1.7 — Availability-Calendar block

- **Block** `kwawingu/availability-calendar` — a visual month grid of a tour's departures. Server shell (current tour slug via `data-tour`) + `view.js` that calls the existing proxy `GET /departures?tourSlug=` and paints departures onto a month grid (date → available seats / status), with prev/next month navigation. All DOM built via `createElement`/`textContent` (no `innerHTML`); dependency-free. `edit.js` (ServerSideRender preview) + shortcode `[kwawingu_calendar]`.
- Reuses the v1.3 build pipeline + v1.1 proxy routes; no new PHP data path. PHP shell + block registration unit-tested; the calendar-painting JS is verified structurally (Jest-testable pure helpers where practical, e.g. a `buildMonthGrid(departures, year, month)` function).

## v1.8 — wp-env integration tests (Docker / CI-only)

- A real-WordPress integration suite (the layer that catches the block-render-echo class of bug, which pure-PHP unit tests can't). Covers: each block's **front-end render** actually emits markup (`do_blocks`/`render_block`), `Sync` writing real `kwt_tour` posts + content-lock + soft-unpublish against a real DB, CPT registration + rewrite rules, and the `Rest_Proxy` routes end-to-end (nonce, key selection via a mocked HTTP layer).
- Runs via `.wp-env.json` (already present) + a WP-test-suite PHPUnit config; **requires Docker**, so it runs on CI runners, not the dev sandbox. Documented as CI-only; authored carefully since it can't be executed here.

## v1.9 — Operator notification + lead capture (scoped)

- **Scope decision (important):** the KwaWingu backend already owns guest-facing emails — booking confirmations, abandoned-booking recovery, and nurture sequences (backend Modules 17/20). The WP plugin must NOT duplicate those. WP owns only:
  - **Operator notification** — when a guest completes an on-site booking through the WP site, email the site's admin (`wp_mail` to `get_option('admin_email')` or a configured address) a short "New booking via your website" notice. Triggered from the `Rest_Proxy` create-booking success (server-side), so it fires reliably.
  - **Lead capture** — persist the on-site booking form's guest details as a lightweight `kwt_lead` record (custom post type or option rows) when a booking is *started*, so the operator has a follow-up list in WP even if payment isn't completed. A settings toggle enables/disables notifications + a recipient override.
- No guest emails are sent from WP. All new writes sanitized; recipient address sanitized; nonce/cap on settings.

## Delivery

Per phase: feature branch → subagent-driven TDD tasks → per-task reviews → whole-branch review → merge → local tag (`v1.6.0`…`v1.9.0`; user pushes). PHPUnit + PHPCS + Jest + `npm run build` stay green. Block render templates keep the split-echo pattern (render.php ECHOES; a function-only render.php renders blank — verified every phase).

## Constraints (locked)

- No Docker in the sandbox → v1.8 integration tests run only in CI.
- Keys stay server-side (proxy); nonce + rate-limit on writes; escaped output; DOM-safe JS (no `innerHTML` with server data).
- Node v22 + npm + `@wordpress/scripts` build available; PHP 8.3 test runtime, PHP 7.4 floor (PHPCompatibilityWP).

## Out of scope

Guest-facing emails (owned by the backend); multisite-specific features; a full block theme; the standalone media/CDN pipeline beyond WP's media library.
