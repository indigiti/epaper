# EP-DI v2.0.1.6 — Mobile Production Hardening

## Upgrade

1. Back up application files and MariaDB.
2. If upgrading from v2.0.1.4 or earlier, first run `database/upgrade-v2.0.1.5-mobile-auth.sql`. There is no additional v2.0.1.6 schema migration.
3. Deploy the full v2.0.1.6 source while preserving `config/config.php`, `config/runtime.php`, credentials, generated media and customer storage.
4. Merge `auth.mobile.challenge_retention_days` from `config/config.example.php` into live configuration if desired; default is 14 days.
5. Reload PHP/OPcache and restart long-running workers so they load the new classes.
6. Confirm `/health/ready`, then test browser mobile login, subscriber disable/re-enable, Android SMS and any enabled WhatsApp/SNA channel.
7. For Android production publishing, configure Play upload-key secrets in CI before using the generated release AAB.

## Security changes

`LOCAL_MOBILE` sessions now require an ACTIVE local account, ACTIVE subscriber row and ACTIVE local identity. Validation is fail-closed for local mobile identities. Platform publisher/admin identities retain their existing availability behavior during a local subscriber-store fault.

Provider-specific error details are recorded through Error Center but are no longer returned directly to readers.

## Android

The official Android project now uses:

- application id `in.digiti.epdi`
- min SDK 23
- compile/target SDK 36
- Android Gradle Plugin 8.13.2
- Gradle 8.13
- Java 17

SNA verification retries a short-lived pending result before offering SMS/WhatsApp fallback. The CI workflow always builds a debug APK and release AAB; the AAB is signed when `EPDI_UPLOAD_KEYSTORE_B64`, `EPDI_UPLOAD_KEYSTORE_PASSWORD`, `EPDI_UPLOAD_KEY_ALIAS`, and `EPDI_UPLOAD_KEY_PASSWORD` secrets are present.

## Rollback

Disable `auth.mobile.enabled` before rollback. v2.0.1.5 database additions are backward-compatible and can remain in place. Restore the previous application package and Service Worker together so cache/version markers remain aligned.
