# EP-DI v2.0.1 — Build Manifest

Baseline: exact EP-DI v2.0.0 Production Stable.

Changed/new files (excluding this manifest and regenerated `SHA256SUMS`): **27**.

## Functional scope
- Guest Sign in icon; authenticated Account icon; role-aware Admin shortcut
- Publisher-controlled public appearance default: Light / System / Dark
- Publisher enable/disable control for public light-dark switcher
- Compact cached Reader edition-date switcher, enable/disable from Reader settings
- Archive date-rail unavailable-day noise reduction and selected-date auto-centering
- No route/API change
- No database migration
- No worker-topology change
- No Offline Vault cryptography/media-policy change

## Changed / added files

- `CHANGELOG.md` — MODIFIED
- `DATABASE-DIFF-2.0.1.txt` — ADDED
- `RELEASE-CHECK-2.0.1.txt` — ADDED
- `ROLLBACK-2.0.1.md` — ADDED
- `ROUTE-DIFF-2.0.1.txt` — ADDED
- `UI-AUDIT-2.0.1.md` — ADDED
- `UPDATE-2.0.1.md` — ADDED
- `V2-BLUEPRINT.md` — MODIFIED
- `VERSION` — MODIFIED
- `app/App.php` — MODIFIED
- `app/Core/View.php` — MODIFIED
- `app/Services/PublicReadCache.php` — MODIFIED
- `app/Services/SettingsCatalog.php` — MODIFIED
- `public_html/assets/app.css` — MODIFIED
- `public_html/assets/app.js` — MODIFIED
- `public_html/assets/reader.css` — MODIFIED
- `public_html/service-worker.js` — MODIFIED
- `release/DATABASE-DIFF-2.0.1.txt` — ADDED
- `release/RELEASE-CHECK-2.0.1.txt` — ADDED
- `release/ROLLBACK-2.0.1.md` — ADDED
- `release/ROUTE-DIFF-2.0.1.txt` — ADDED
- `release/UI-AUDIT-2.0.1.md` — ADDED
- `release/UPDATE-2.0.1.md` — ADDED
- `release/V2-BLUEPRINT.md` — MODIFIED
- `tests-cumulative-check.php` — MODIFIED
- `tests/build-guard-v2.0.1.php` — ADDED
- `tests/public-newsstand-archive-static.php` — MODIFIED

## Verification summary
- PHP syntax: 559 files PASS
- JavaScript/service-worker syntax: 8 files PASS
- CSS: 16 files / 2114 rules / 0 parser errors
- Tenant SQL guard: 190 statements PASS
- Offline Vault crypto interoperability: `ECDH_WRAP_MATCH true 32`
- Route-bearing dispatcher parity vs v2.0.0: PASS
- `database/` byte parity vs v2.0.0: PASS

## Database
`database/` is byte-identical to v2.0.0. `database/upgrade-v2.0.1.sql` does not exist.

## Route/API
Canonical v2.0.0 route inventory remains unchanged (80 exact + 49 regex/pattern signatures). See `ROUTE-DIFF-2.0.1.txt`.

## Rollback
Rollback target is checksum-locked EP-DI v2.0.0 Production Stable; no DB rollback is required.
