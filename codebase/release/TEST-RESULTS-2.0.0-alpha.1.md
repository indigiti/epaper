# Test Results — EP-DI v2.0.0-alpha.1

- PHP syntax lint: **PASS** — 81 PHP files.
- JavaScript syntax (`node --check`): **PASS** — app.js, crud.js, reader.js, reader-prefetch.js, guest-clipping.js, service-worker.js.
- `tests/tenant-query-guard.php`: **PASS** — 207 tenant-owned application SQL statements checked.
- `tests/reader-parity.php`: **PASS**.
- `php bin/release-check.php`: **PASS** — all v2.0.0-alpha.1 build guards.
- Composer metadata validation: **NOT RUN** — global `composer` executable is not installed in this build container; bundled `vendor/` and `composer.lock` were not modified by this milestone.

Historical guards pinned to earlier release identities are retained for recovery/history but are not used as v2 release gates.
