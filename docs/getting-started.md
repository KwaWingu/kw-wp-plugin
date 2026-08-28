# Getting started

## 1. Enable API access (paid add-on)

The plugin reads your data through the KwaWingu Developer API, a paid per-operator add-on. Without an active entitlement every request answers `403 api_access_required` (the plugin shows you exactly that in wp-admin). In your KwaWingu dashboard, open **Developer API** and enable access, then:

- create a **publishable key** (`kw_pk_…`) for the **public API key** field. KwaWingu only issues one with an **allowed origin** — enter your WordPress site address (scheme + host, e.g. `https://www.example.com`);
- if you will use the Inquiry Form or on-site booking, create a **secret key** (`kw_sk_…`) with the `inquiries:write`, `quotes:write`, `bookings:write`, `payments:write` and `bookings:read` scopes for the **private API key** field;
- note your **operator slug** — the last part of your booking page address, `tours.kwawingu.com/your-slug`.

Testing against a staging or local KwaWingu? Put `define( 'KWT_SITE_BASE', 'http://host.docker.internal:8085' );` in `wp-config.php` — the API root becomes `KWT_SITE_BASE/api/v1` and booking links follow it. `KWT_API_BASE` overrides the API root alone.

## 2. Connect

In WordPress: **Settings → KwaWingu Tours**. Paste your slug + public key, choose a **booking mode** (start with *Redirect*), and save. Use **Sync now** to pull your tours immediately, or let the scheduled sync run.

## 3. Build your site

**Settings → KwaWingu Setup → Build my site.** This will:

- pull your branding (logo + colours) from your KwaWingu profile,
- create starter pages (Home, Tours, About, Contact) and set your home page,
- import your tours and destinations (covers and gallery photos go into your media library).

Everything is normal WordPress content afterwards — edit freely. Once you edit a tour, future syncs won't overwrite your text (they still refresh price, photos, and the booking link).

## 4. Keep it in sync

Tours re-sync automatically (hourly by default; change the interval in Settings). Removed tours are set to Draft, never deleted.

## 5. If something stops working

Open any wp-admin screen. If the KwaWingu API is refusing this site, a red **KwaWingu Tours** notice says exactly what to fix — enable the paid API add-on, correct the public key, or correct the operator slug. Visitors are never shown that; they keep seeing your synced tours, and the interactive blocks fall back to a quiet "not available at the moment". Rate limits and outages are handled for you: the last live prices stay on the page and the request is retried a minute later (see [blocks.md](blocks.md#when-the-api-refuses-a-request)).

