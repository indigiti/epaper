# ePaper SaaS v1.1.3 — Fixed Reader Viewport + Pages Toggle + Data Saver

## Purpose
This release consolidates the Reader fixes requested after live testing. It keeps the browser document fixed to the viewport and gives the page browser, newspaper canvas, Reader header and pager independent layout responsibilities.

## Reader layout
- Browser/document does not scroll while the Reader is active.
- Global site header remains fixed.
- Reader header remains fixed inside the Reader workspace.
- Bottom Previous / Page / Next pager remains visible at all times.
- Newspaper pan/zoom happens only inside the Reader canvas.
- Page thumbnails scroll independently.
- Header/pager use border-box sizing so their declared heights cannot overlap the canvas.

## Pages control
- `Pages` moved beside `Search` in the Reader header.
- Desktop: click toggles the thumbnail column and remembers the preference.
- Mobile/tablet: page list is a slide-over drawer and defaults closed.
- Selecting a page closes the drawer on mobile but preserves the desktop preference.

## Adaptive Data Saver
Three Reader media modes are available from the header:
- **Auto** — adapts to Save-Data and slow 2G/3G connection hints.
- **Low Data** — uses PREVIEW WebP first, disables adjacent image prefetch, and delays Deep Zoom until a higher zoom threshold.
- **High Quality** — promotes HIGHRES WebP immediately and prefetches adjacent high-resolution pages.

The preference is stored locally per browser. No schema migration is required.

## Progressive image delivery
The Reader state API now exposes both `preview` and `highres` image URLs. The Reader starts from the smaller PREVIEW WebP and promotes to HIGHRES according to the selected data-quality mode.

## Security / regression preservation
The v1.1.2.1 authentication cache/CSRF protections are included in the cumulative release. No RabbitMQ, Supervisor, MariaDB schema, Platform, entitlement or media-route changes are made.
