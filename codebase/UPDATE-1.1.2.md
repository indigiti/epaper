# Update to ePaper SaaS v1.1.2

## Admin Appearance + Luxury Operations

- Adds per-user Light/Dark mode for Publisher Admin, stored in `ep_subscribers.preferences_json`.
- Adds a compact admin request-performance HUD: DB query count/time and Redis cache hit rate.
- Rebuilds System Health into a premium operations dashboard using only live application checks.
- Adds grouped Core / Processing / Queue & Worker health cards, score, operational metrics and safe diagnostic links.
- No database migration. No RabbitMQ/Supervisor configuration change.

## Deploy

Extract the hotfix into the outer Cloudways `public_html` and overwrite matching files. Keep Webroot `public_html/public_html`. Purge Varnish/app cache and hard refresh.
