# ePaper SaaS v1.1.1 Build Manifest

Release: **Reader Chrome + Account Experience + Publisher Admin IA**
Baseline: ePaper SaaS v1.1.0 City Feature Parity
City behavior baseline: City ePaper v2.2.9.1

## Changed protected/runtime files
- `app/Core/View.php` — persistent Admin/Account workspace shells, active navigation, role-oriented Publisher navigation.
- `app/App.php` — visual Account Library cards, WebP thumbnails/clips, deduplicated Continue Reading, richer empty states.
- `public_html/assets/app.css` — workspace/sidebar, account cards, notifications, responsive drawers.
- `public_html/assets/app.js` — role-aware command palette and responsive Admin/Account drawer.
- `public_html/assets/reader.css` — persistent Reader header/pager; only secondary toolbar may fade.
- `public_html/assets/reader.js` — idle threshold raised to ~4.6 seconds.
- `public_html/service-worker.js` — cache version aligned to v1.1.1.

## Intentionally unchanged
- Routes and public URLs
- MariaDB schema
- Tenant ownership model
- RabbitMQ topology and processing business logic
- Digiti Platform contracts
- Entitlement keys
- Media/token APIs
- Clip business API

## Rollback
Restore the v1.1.0 package files listed above and restart browser/service-worker cache. No database rollback is required.
