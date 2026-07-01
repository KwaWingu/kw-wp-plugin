# WordPress.org Plugin Directory — compliance summary

Reviewed against the 18 WordPress.org Plugin Directory guidelines. Status at v1.5.0.

| Guideline | Status | Notes |
|---|---|---|
| 1. GPL-compatible | ✅ | `License: GPLv2 or later` + `License URI` in `kwawingu-tours.php` and `readme.txt`. No bundled third-party libraries. Composer/npm dev deps are not shipped in the plugin zip. |
| 2. Developer responsible for code | ✅ | — |
| 3. Stable version from wordpress.org | ✅ | Distributed here; `Stable tag` matches the plugin header. |
| 4. Human-readable / no obfuscation | ✅ | Minified editor bundles in `build/` ship **with source** (`blocks/*/index.js`, `blocks/*/edit.js`) and a reproducible build (`npm run build`, verified in CI). |
| 5. No trialware | ✅ | The plugin is fully functional and free; it is a client for the operator's own paid KwaWingu account (external-service model), not a crippled trial. |
| 6. Software as a Service is permitted | ✅ | Connects to the KwaWingu SaaS; disclosed. |
| 7. No tracking without consent / disclose external services | ✅ | `== External services ==` discloses the KwaWingu API calls + data sent (API key header, request params, on-site booking guest details). No visitor tracking. |
| 8. No executable code via third-party systems | ✅ | Only fetches data/JSON from the KwaWingu API; no remote code execution. |
| 9. No illegal/dishonest actions | ✅ | — |
| 10. No hijacking the admin | ✅ | Notices are contextual (sync status, enable-API-access when 403). |
| 11. No unauthorized spam | ✅ | — |
| 12. Respect trademarks | ✅ | "KwaWingu" is the vendor's own brand. |
| 13. No blackhat SEO | ✅ | — |
| 14. Plugin directory maintenance | ✅ | — |
| 15. Reserved compliance | n/a | — |
| 16. Marked as adult if needed | n/a | — |
| 17. Naming / slug | ✅ | Name "KwaWingu Tours" (header = readme), slug `kwawingu-tours` (lowercase, hyphenated, < 50 chars). |
| 18. Respect users | ✅ | Uninstall cleans plugin options + cron; leaves content. |

## Security posture (WP.org review focus)
- All input sanitized; all output escaped; REST args validated. Nonces + `manage_options` on admin writes; REST nonce (`wp_rest`) on every proxy route; per-visitor rate limit on write routes.
- **Private API key never reaches the browser** — only used server-side in `Rest_Proxy` write handlers.
- ABSPATH guard on every PHP class file. Blocking PHPCS (WPCS) in CI (exit 0).

## Remaining before submission (needs a live environment)
- **Screenshot PNGs** — capture from a running install (see `.wordpress-org/README.md`) and they already have descriptions in `readme.txt`.
- Run `wp plugin check` (Plugin Check plugin) on a live install.
- Regenerate the full `.pot`: `npm run make-pot` (requires WP-CLI).
- Manual editor QA: `docs/editor-qa.md`.
