# Getting started

## 1. Enable API access (paid add-on)

The plugin reads your data through the KwaWingu Developer API, a paid per-operator add-on. In your KwaWingu dashboard, open **Developer API** and enable access, then copy your **operator slug** and **public API key**.

## 2. Connect

In WordPress: **Settings → KwaWingu Tours**. Paste your slug + public key, choose a **booking mode** (start with *Redirect*), and save. Use **Sync now** to pull your tours immediately, or let the scheduled sync run.

## 3. Build your site

**Settings → KwaWingu Setup → Build my site.** This will:

- pull your branding (logo + colours) from your KwaWingu profile,
- create starter pages (Home, Tours, About, Contact) and set your home page,
- import your tours.

Everything is normal WordPress content afterwards — edit freely. Once you edit a tour, future syncs won't overwrite your text (they still refresh price, photos, and the booking link).

## 4. Keep it in sync

Tours re-sync automatically (hourly by default; change the interval in Settings). Removed tours are set to Draft, never deleted.

## 5. If something stops working

Open any wp-admin screen. If the KwaWingu API is refusing this site, a red **KwaWingu Tours** notice says exactly what to fix — enable the paid API add-on, correct the public key, or correct the operator slug. Visitors are never shown that; they keep seeing your synced tours, and the interactive blocks fall back to a quiet "not available at the moment". Rate limits and outages are handled for you: the last live prices stay on the page and the request is retried a minute later (see [blocks.md](blocks.md#when-the-api-refuses-a-request)).

