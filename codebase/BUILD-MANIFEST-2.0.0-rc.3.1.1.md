# Build Manifest — EP-DI v2.0.0-rc.3.1.1

Baseline: exact released `EP-DI-v2.0.0-rc.3.1.zip`.

## Functional intent
Correct the publication landing-page edition list so it matches the public Newsstand interaction language introduced in rc.3.1: horizontal swipe rail, fixed newspaper proportions, no stretched/cropped cover images.

## Runtime files changed
- `VERSION`
- `public_html/assets/app.css`
- `public_html/service-worker.js`
- `public_html/offline.html`
- `public_html/offline-library.html`
- `public_html/offline-reader.html`
- `bin/release-check.php`
- `tests/public-newsstand-archive-static.php`
- `tests/build-guard-v2.0.0-rc.3.1.1.php` (new)

## Release documentation changed/added
- `CHANGELOG.md`
- `V2-BLUEPRINT.md`
- `UPDATE-2.0.0-rc.3.1.1.md`
- `BUILD-MANIFEST-2.0.0-rc.3.1.1.md`
- `ROLLBACK-2.0.0-rc.3.1.1.md`
- `RELEASE-CHECK-2.0.0-rc.3.1.1.txt`
- matching copies under `release/`

## Guardrails
- No application route changes.
- No `app/App.php` change.
- No database/schema change and no rc.3.1.1 SQL migration.
- No queue, Supervisor, processing, Reader or Offline Vault behavior change.
- Publication page continues to request strict `THUMB` assets.
