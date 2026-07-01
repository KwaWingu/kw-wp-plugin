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

Prices display in TZS. Styling uses the `kwt-*` CSS classes and the `--kwt-primary` / `--kwt-accent` custom properties set from your KwaWingu branding.
