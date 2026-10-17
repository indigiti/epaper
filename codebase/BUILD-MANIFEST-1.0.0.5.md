# Build Manifest — ePaper SaaS v1.0.0.5

Changed files:

- `VERSION`
- `app/App.php`
- `app/Core/View.php`
- `app/Services/Processing.php`
- `bin/process.php`
- `bin/worker.php`
- `bin/scheduler.php`
- `public_html/assets/app.css`
- `public_html/assets/app.js`
- `public_html/service-worker.js`
- release documentation/test guard files

Protected behavior retained:

- Platform/domain/tenant resolution
- first-party secure `/login`
- public reader URLs
- `/media/{token}`
- Deep Zoom tile routes
- OCR/Smart Article data path
- clipping/bookmarks/account routes
- publishing states and tenant isolation

No database schema changes. No public route removal.
