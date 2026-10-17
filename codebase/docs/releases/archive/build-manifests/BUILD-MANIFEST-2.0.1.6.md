# EP-DI v2.0.1.6 Build Manifest

Base: v2.0.1.5 mobile-auth full source. This is a cumulative full-source hardening release with no new schema migration.

## Runtime changes

- `VERSION`
- `CHANGELOG.md`
- `manifest.json`
- `app/App.php`
- `app/Core/Auth.php`
- `app/Services/MobileAuth.php`
- `app/Services/MobileAuthPendingException.php`
- `app/Services/QueueService.php`
- `bin/scheduler.php`
- `bin/release-check.php`
- `config/config.example.php`
- `public_html/service-worker.js`
- `mobile-android/app/build.gradle`
- `mobile-android/build.gradle`
- `mobile-android/app/src/main/java/in/digiti/epdi/MainActivity.java`
- `mobile-android/build-official.sh`
- `mobile-android/README.md`
- `.github/workflows/build-android-apk.yml`
- `mobile-android/.github/workflows/build-apk.yml`
- `tests/mobile-auth-hardening-v2.0.1.6.php`
- `tests/build-guard-v2.0.1.6.php`
- `tests/processing-control-static.php`
- `tests/editions-operations-static.php`
- `tests/tenant-query-guard.php`
- `RELEASE-CHECK-2.0.1.6.txt`

## Database

No new v2.0.1.6 DDL. `database/upgrade-v2.0.1.5-mobile-auth.sql` remains required on installations that have not yet installed the v2.0.1.5 mobile-auth schema.

## Android baseline

`in.digiti.epdi` · min SDK 23 · compile/target SDK 36 · AGP 8.13.2 · Gradle 8.13 · Java 17.

## Release integrity

`SHA256SUMS` is regenerated after all source and release documents are finalized and is verified before ZIP creation.
