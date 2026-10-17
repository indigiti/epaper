# Build Manifest — EP-DI v2.0.2.0

Release: **Realistic Newspaper Reader**  
Base: **2.0.1.9**  
Database migration: **none**

Primary additions/changes:

- `public_html/assets/reader-flip.js` — EP-DI native flip/spread/continuous presentation adapter.
- `public_html/assets/reader.js` — exposes the canonical reader API and coordinates zoom/clip/page changes with presentation adapters.
- `public_html/assets/reader.css` — paper/spread/turn/continuous UI.
- `app/App.php` — publication/user Reader settings passed as reader data attributes.
- `app/Services/SettingsCatalog.php` — Reader View and flip policy controls.
- `app/Core/View.php` — versioned Reader adapter asset.
- `public_html/service-worker.js` — v2.0.2.0 cache namespace.

Design rule: the presentation adapter never becomes the source of truth for edition state, media authorization, progress, analytics, zoom tiles or clipping.

## Final validation

- PHP syntax: **161 application/test files PASS**
- Web JavaScript syntax: **8 files PASS**
- JSON parse: **101 files PASS**
- Workflow YAML parse: **2 application workflow files PASS**
- `bin/release-check.php`: **PASS**
- Database migration: **none**
- Base release required for overlay patch: **2.0.1.9**
