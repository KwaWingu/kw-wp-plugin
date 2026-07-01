# Booking modes

Choose a mode in **Settings → KwaWingu Tours → Booking mode**.

## Redirect (available now)
Book buttons link to your hosted KwaWingu booking page:
`https://tours.kwawingu.com/{your-slug}/tours/{tour-slug}`. Availability, the booking steps, and payment (Snippe) are handled by KwaWingu — nothing to maintain on your site, and seat counts are always correct.

## Widget (roadmap — v0.3)
Embeds the KwaWingu booking widget in a page so guests book without leaving your site.

## On-site via API (roadmap — v0.4)
A fully in-site booking + payment flow using the KwaWingu API. Requires your **private API key** (stored server-side, never exposed to visitors). No card data touches WordPress — payment is completed through Snippe.

Until Widget and On-site ship, those modes fall back to Redirect.
