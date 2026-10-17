# Upgrade to EP-DI v2.0.1.8

Baseline: **v2.0.1.7 Flexible Reader Identity**.

1. Back up the application tree and MariaDB.
2. Preserve `config/config.php`, `config/runtime.php`, storage and all credentials.
3. Deploy the v2.0.1.8 source or overlay patch.
4. Run `database/upgrade-v2.0.1.8-trusted-app-desktop-login.sql` once.
5. Add the optional server-side Firebase configuration under `auth.desktop.firebase` in `config/config.php`:
   - `project_id`
   - `service_account_file` — absolute path outside `public_html`.
6. For Android CI, configure repository variables:
   - `EPDI_FIREBASE_APPLICATION_ID`
   - `EPDI_FIREBASE_API_KEY`
   - `EPDI_FIREBASE_PROJECT_ID`
   - `EPDI_FIREBASE_SENDER_ID`
7. Rebuild/install the Android app. A reader must verify their mobile number in the app once before that app can approve desktop logins.
8. Open **Admin → Settings → Reader access** and review Desktop reader login, Trusted mobile app approval, fallbacks, challenge lifetime and session lifetimes.
9. Restart long-running EP-DI workers and run `php bin/release-check.php`.
10. Test: desktop mobile number → app notification/dialog → matching code → Approve → desktop authenticated. Approving a second desktop must replace the first desktop session.

If Firebase is not configured or push delivery is delayed, opening the trusted app still checks the server for a pending approval request. Authentication does not rely on the notification payload itself.
