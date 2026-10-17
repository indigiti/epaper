# ePaper SaaS v1.1.6.1 — Header Brand Deduplication Hotfix

## Header rule

When `brand.logo_path` is configured, the public/Reader header shows the uploaded publisher logo only. The visible site/publication name is not repeated next to a full wordmark.

When no publisher logo is configured, the header falls back to the configured Site name as text.

Accessibility remains explicit: the configured logo receives the Site name as its `alt` text and the brand link retains an accessible `<Site name> home` label.

## Responsive masthead limits

- Desktop: up to 190px × 36px
- Tablet: up to 150px × 30px
- Mobile: up to 130px × 28px

The image keeps its intrinsic aspect ratio; it is never forced into a square.

## Scope

No database migration. No route/API change. No Reader/social-sharing behavior change. No RabbitMQ/Supervisor change. `QrCode.php` and `ClipService.php` remain byte-for-byte identical to the v1.1.4.1 Source Page QR protected baseline.
