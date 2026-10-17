# ePaper SaaS v1.1.6 — Smart Social Sharing + Responsive Brand Header

## Baseline

This hotfix is cumulative from **v1.1.5.1** and preserves the exact **v1.1.4.1 Source Page QR** implementation.

## Social sharing without extra image storage

v1.1.6 does **not** generate 1200x630 social-card derivative files.

For each published Reader page, Open Graph/Twitter metadata points to a stable route:

`/social/edition/{edition-slug}/page/{page}.webp?v={asset-guid}`

That route streams the page's already-generated `THUMB` WebP. If `THUMB` is unavailable, it falls back to the existing `PREVIEW` WebP.

Benefits:
- zero additional social-card files per page,
- no duplicate disk usage,
- stable URL for WhatsApp/Facebook/X/Telegram crawlers,
- asset-GUID query version changes when the page asset changes,
- ETag and public cache headers reduce repeated origin traffic,
- draft/unpublished editions are never exposed through the social thumbnail route.

Reader page metadata includes Open Graph title/description/url/image/image-alt and Twitter summary-large-image metadata.

Public clipping pages use the existing branded clipping image for Open Graph. Private clipping images are not exposed as social previews.

## Responsive publisher logo

The uploaded primary/dark logo is no longer forced into 30x30px.

Desktop:
- natural aspect ratio,
- maximum logo width 140px,
- maximum logo height 34px,
- site name remains visually adjacent.

Tablet:
- maximum logo width 108px,
- maximum logo height 30px.

Mobile:
- maximum logo width 82px,
- maximum logo height 26px,
- compact site-name text and no header overflow.

Accessibility:
- logo `alt` text uses the configured Site name,
- visible adjacent Site name is marked `aria-hidden` when a logo is present so screen readers do not announce the same brand twice,
- brand link has a descriptive Home accessible label.

## Deployment

1. Take a Cloudways on-demand backup.
2. Extract this hotfix into the existing outer `public_html` and overwrite matching files.
3. Keep Webroot as `public_html/public_html`.
4. Do not run `install.php`; no database migration is required.
5. Purge Varnish/application cache and hard-refresh once so service-worker cache v1.1.6 activates.
6. Share a published Reader page into WhatsApp/Telegram and confirm the page thumbnail appears.
7. Test desktop and mobile header with the uploaded logo.
