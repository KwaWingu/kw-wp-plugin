# Blocks & shortcodes

All blocks are server-rendered from your synced tours — no JavaScript needed to display them, and they're crawlable for SEO.

## Tours Grid — `kwawingu/tours-grid` / `[kwawingu_tours]`
A responsive grid of tours.
- `limit` (number, default 12)
- `type` (string) — filter by tour type (e.g. `safari`)

## Tour Detail — `kwawingu/tour-detail` / `[kwawingu_tour]`
Full detail for one tour (cover, facts, description, book button).
- `postId` / `id` (number) — the tour to show; defaults to the current post in a tour template.

## Featured Tours — `kwawingu/featured-tours` / `[kwawingu_featured]`
A short highlighted set with a heading.
- `heading` (string)
- `limit` (number, default 3)

## Book Button — `kwawingu/book-button` / `[kwawingu_booking]`
A booking link/button for a tour.
- `postId` / `id` (number)
- `label` (string, default "Book now")

## Reviews — `kwawingu/reviews` / `[kwawingu_reviews]`
Displays the rating and guest reviews for a tour.
- `postId` / `id` (number) — the tour to show reviews for; defaults to the current post in a tour template.

## Destinations Grid — `kwawingu/destinations-grid` / `[kwawingu_destinations]`
A responsive grid of destination cards synced from your KwaWingu catalog.
- `limit` (number, default 12)

## Gallery — `kwawingu/gallery` / `[kwawingu_gallery]`
Displays a tour's photo gallery sourced from images imported into your media library.
- `postId` / `id` (number) — the tour to show the gallery for; defaults to the current post in a tour template.
- `columns` (number, default 3) — number of columns in the gallery grid.

## Availability Calendar — `kwawingu/availability-calendar` / `[kwawingu_availability]`
A month grid of a tour's upcoming departures showing available seats and sold-out status.
- `postId` / `id` (number) — the tour to show departures for; defaults to the current post in a tour template.

Prices display in TZS. Styling uses the `kwt-*` CSS classes and the `--kwt-primary` / `--kwt-accent` custom properties set from your KwaWingu branding.
