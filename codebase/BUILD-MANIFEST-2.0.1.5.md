# EP-DI v2.0.1.5 Build Manifest

Baseline: v2.0.1.4.

Server/runtime changes:

- `VERSION`
- `app/App.php`
- `app/Core/Auth.php`
- `app/Services/MobileAuth.php` (new)
- `config/config.example.php`
- `database/schema.sql`
- `database/upgrade-v2.0.1.5-mobile-auth.sql` (new)

Release/build documentation:

- `MOBILE-AUTH-2.0.1.5.md`
- `UPDATE-2.0.1.5.md`
- `BUILD-MANIFEST-2.0.1.5.md`
- `.github/workflows/build-android-apk.yml`

Android source is under `mobile-android/` and uses application id `in.digiti.epdi`, min SDK 23, target/compile SDK 35, Android Gradle Plugin 8.7.3, Gradle 8.9 and Java 17.

No vendor PHP package is added. Mobile verification uses PHP cURL to the configured verification provider. Existing publisher/admin Platform authentication remains in place.

Validation additions:

- `tests/mobile-auth-static.php`
- `RELEASE-CHECK-2.0.1.5.txt`
