# Editor QA checklist (v1.3 — block editor UI)

The block editor components (`edit.js`) are React and **cannot be verified by the plugin's PHP unit tests** — they build cleanly (`npm run build` + CI), but their in-editor behaviour must be checked by hand on a real WordPress install. Run this once after installing/updating.

## Setup
1. Build the bundles: `npm ci && npm run build` (produces `build/<block>/index.js` + `index.asset.php`).
2. Activate the plugin on a WP 6.2+ site; configure Settings → KwaWingu Tours (slug + public key) and run a sync so there are tours to preview.

## Per-block checks (in a new post/page, add each block)
For **every** block: it appears in the inserter under "KwaWingu Tours"/Widgets, shows a **server-rendered preview** (real tours/data, not "block not found" or a blank box), and saving + viewing the front end matches the preview.

| Block | Sidebar controls to verify |
|---|---|
| Tours Grid | "Number of tours" slider + "Filter by type" text → preview updates |
| Featured Tours | Heading text + limit slider |
| Tour Detail | Tour post ID (0 = current) |
| Book Button | Label text + post ID |
| Reviews | Post ID (0 = current) |
| Destinations | Limit slider |
| Search | Placeholder text |
| Trip Calculator | No controls — just a preview of the form |
| Booking | Tour slug (blank = current) |

## Known-good expectations
- Interactive blocks (Search, Calculator, Booking) preview their **static form shell** in the editor; the live fetch/JS only runs on the front end (the proxy config isn't localized in the editor) — this is expected.
- Changing a control should re-render the ServerSideRender preview after a short debounce.

## If a block shows "This block contains unexpected or invalid content"
That means the client registration (`registerBlockType` in `blocks/<block>/index.js`) and the server registration disagree. Fix: ensure `save: () => null` (dynamic block) and that `block.json` `editorScript` points at the built `../../build/<block>/index.js`. Rebuild.
