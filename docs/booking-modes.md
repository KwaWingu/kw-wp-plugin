# Booking modes

Choose a mode in **Settings → KwaWingu Tours → Booking mode**.

## Redirect (available now)
Book buttons link to your hosted KwaWingu booking page:
`https://tours.kwawingu.com/{your-slug}/tours/{tour-slug}`. Availability, the booking steps, and payment (Snippe) are handled by KwaWingu — nothing to maintain on your site, and seat counts are always correct.

## Widget (available now)
Embeds the KwaWingu booking widget in a page so guests complete their booking without leaving your site. The widget is served from KwaWingu's CDN and mounted into a page element by a small inline script. Availability and payment are handled by KwaWingu; no booking state is stored in WordPress. To enable this mode, choose **Widget** in Settings → KwaWingu Tours → Booking mode — the plugin inserts the correct embed code into your tour pages automatically.

## On-site via API (roadmap — v0.4)
A fully in-site booking + payment flow using the KwaWingu API. Requires your **private API key** (stored server-side, never exposed to visitors). No card data touches WordPress — payment is completed through Snippe.

Until On-site ships, that mode falls back to Redirect.
