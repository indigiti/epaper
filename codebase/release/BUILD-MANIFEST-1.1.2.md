# ePaper SaaS v1.1.2 Build Manifest

Changed protected/application files:

- `app/Core/View.php` — admin theme control, persisted mode bootstrapping, request metrics HUD, v1.1.2 assets.
- `app/Core/Store.php` — request-local DB query count/time instrumentation.
- `app/Core/RedisStore.php` — request-local JSON cache hit/miss/write instrumentation.
- `app/App.php` — admin theme preference endpoint and luxury System Health.
- `public_html/assets/app.js` — optimistic Light/Dark toggle + persisted save/rollback.
- `public_html/assets/app.css` — premium dark admin tokens, System Health, metrics HUD.
- `public_html/service-worker.js` — cache version v1.1.2.
- release manifests — versioned route/capability/parity copies.

No schema migration. No processing engine changes. No Reader geometry changes.
