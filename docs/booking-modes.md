# Booking modes

Choose a mode in **Settings → KwaWingu Tours → Booking mode**.

## Redirect (available now)
Book buttons link to your hosted KwaWingu booking page:
`https://tours.kwawingu.com/{your-slug}/tours/{tour-slug}`. Availability, the booking steps, and payment (Snippe) are handled by KwaWingu — nothing to maintain on your site, and seat counts are always correct.

## Widget (available now)
Embeds the KwaWingu booking widget in a page so guests complete their booking without leaving your site. The widget is served from KwaWingu's CDN and mounted into a page element by a small inline script. Availability and payment are handled by KwaWingu; no booking state is stored in WordPress. To enable this mode, choose **Widget** in Settings → KwaWingu Tours → Booking mode — the plugin inserts the correct embed code into your tour pages automatically.

## On-site via API (available now — requires private key)
A fully in-site booking + payment flow using the KwaWingu REST proxy. Requires your **private API key** (stored server-side in Settings, never exposed to visitors). The browser calls a same-origin WordPress REST endpoint (`/wp-json/kwawingu/v1/`) that forwards requests to the KwaWingu API — your key never reaches the browser. Payment is completed through Snippe (mobile money push). No card data touches WordPress.

As of v1.1.0, the on-site booking form loads **real departures** from the live API so guests select an actual available departure date, and a **live price** is fetched from the API before the guest proceeds to payment. This ensures seat counts and pricing are always accurate.

To enable: choose **On-site** in Settings → KwaWingu Tours → Booking mode and enter your **Private API key**.

### Operator notifications and lead capture (v1.8.0+)

When a guest completes an on-site booking you can receive an email notification and have the guest's details saved as a **Lead** (custom post type `kwt_lead`) in your WordPress admin. Both behaviours are optional and controlled by toggles in **Settings → KwaWingu Tours**:

- **Operator notification email** — sent to the address configured in Settings (falls back to the WordPress admin email). Contains the booking reference, tour name, guest name, email, and phone.
- **Save lead in WordPress** — creates a `kwt_lead` post with the guest email, phone, tour name, and booking reference stored as post meta. Find leads under **Leads** in the WordPress admin.

**Guest confirmations are still sent by KwaWingu** — this plugin never emails the guest directly. The notification and lead capture are operator-only features.
