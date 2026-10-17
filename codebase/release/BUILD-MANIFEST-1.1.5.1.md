# Build Manifest — ePaper SaaS v1.1.5.1

## Purpose
Settings POST normalization hotfix for v1.1.5.

## Runtime change
- `app/App.php`
  - adds centralized `postedSetting()` normalization;
  - Brand form uses `settings[exact.dotted.key]` names;
  - Appearance/SEO/Reader/Processing/Clipping renderer uses the same nested form encoding;
  - Brand asset-only/partial updates fall back to stored values;
  - Remove Asset uses `formnovalidate`.

## Release metadata/test changes
- `VERSION`
- `manifest.json`
- `CHANGELOG.md`
- `UPDATE-1.1.5.1.md`
- `bin/release-check.php`
- `tests/build-guard-v1.1.5.1.php`
- `tests/settings-post-v1.1.5.1.php`
- v1.1.5.1 release reports

## Explicitly NOT overwritten
- `app/Services/QrCode.php`
- `app/Services/ClipService.php`
- Reader JavaScript/CSS
- CRUD JavaScript/CSS
- service worker
- RabbitMQ/Supervisor/processing files
- database/schema

The hotfix therefore cannot revert the v1.1.4.1 Source Page QR implementation.
