# WordPress.org assets

These files are deployed to the plugin's WordPress.org SVN `assets/` directory
(they are NOT shipped inside the plugin zip).

- `icon-256x256.png`, `icon-128x128.png`, `banner-1544x500.png`, `banner-772x250.png` —
  rendered from the SVG sources alongside them (`icon.svg`, `banner-*.svg`) at exact pixel
  sizes. Re-render with Playwright (`page.setContent(svg)` at the target viewport) after
  editing an SVG; WordPress.org reads the PNGs.
- `screenshot-1.png` … `screenshot-10.png` — 1280×800 captures from a live WordPress install
  running the plugin against a KwaWingu backend (settings page, setup wizard, block inserter,
  Tours Grid, Tour Detail, Availability Calendar, On-site Booking with its live quote, Trip
  Calculator, Tour Search, Inquiry Form). Their order and captions are the numbered
  `== Screenshots ==` section of `readme.txt` — keep the two in step.
