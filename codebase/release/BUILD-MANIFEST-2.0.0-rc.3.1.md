# Build Manifest — EP-DI v2.0.0-rc.3.1

Release: **Public Newsstand Polish**  
Baseline: **EP-DI v2.0.0-rc.3**

## Runtime changes
- `VERSION`
- `public_html/assets/app.css`
- `public_html/service-worker.js`
- `public_html/offline.html`
- `public_html/offline-library.html`
- `public_html/offline-reader.html`
- `bin/release-check.php`

## Release/test changes
- `tests/build-guard-v2.0.0-rc.3.1.php`
- `UPDATE-2.0.0-rc.3.1.md`
- `BUILD-MANIFEST-2.0.0-rc.3.1.md`
- `ROLLBACK-2.0.0-rc.3.1.md`
- `PUBLIC-NEWSSTAND-PATCH-2.0.0-rc.3.1.md`
- `V2-BLUEPRINT.md`
- `CHANGELOG.md`

## Functional contract
- No application route added/removed.
- No schema change and no rc.3.1 SQL migration.
- No worker topology or queue change.
- Strict THUMB listing behavior from RC.1.2 is preserved.
- Reader/Offline Vault behavior is unchanged.
