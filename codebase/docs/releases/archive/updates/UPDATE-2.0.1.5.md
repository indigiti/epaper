# EP-DI v2.0.1.5 — In-app Android Reader & Local Mobile Signup

## Install over v2.0.1.4

1. Back up files, MariaDB, live configuration and storage.
2. Run `database/upgrade-v2.0.1.5-mobile-auth.sql`.
3. Deploy v2.0.1.5 while preserving `config/config.php`, `config/runtime.php`, secrets and customer storage.
4. Merge only the `auth.mobile` block from `config/config.example.php` into live configuration. Keep mobile auth disabled until provider credentials are configured.
5. Reload PHP/OPcache and verify the existing reader, publisher login and `/admin` before enabling mobile auth.
6. Enable mobile auth and test one new and one returning mobile reader. Disable the test subscriber from Admin and confirm the session is blocked.
7. Build `mobile-android/` using Android Studio or the included GitHub Actions workflow. The app opens EP-DI inside WebView and transfers the authenticated PHP session from the native mobile-auth API client.

See `MOBILE-AUTH-2.0.1.5.md` for configuration, channel notes and rollback.
