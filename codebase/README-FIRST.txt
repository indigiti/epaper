EP-DI v2.0.1.8 — Trusted App & Desktop Login

Upgrade baseline: v2.0.1.7.

1. Take an application + MariaDB backup.
2. Preserve config/config.php, config/runtime.php, credentials and storage.
3. Deploy the v2.0.1.8 full source (or overlay patch on v2.0.1.7).
4. Run database/upgrade-v2.0.1.8-trusted-app-desktop-login.sql once.
5. Configure auth.desktop.firebase.project_id and auth.desktop.firebase.service_account_file if OS push notifications are required. Keep the service-account JSON outside public_html.
6. In Android CI configure EPDI_FIREBASE_APPLICATION_ID, EPDI_FIREBASE_API_KEY, EPDI_FIREBASE_PROJECT_ID and EPDI_FIREBASE_SENDER_ID repository variables.
7. Restart PHP workers/Supervisor processes used by EP-DI.
8. Hard-refresh/PWA reload once so epdi-v2.0.1.8 activates.
9. Open Admin → Settings → Reader access and review Desktop reader login / Trusted mobile app approval / fallback policy.
10. Verify one trusted mobile app + one desktop session, replacement of an older desktop session, and Approve/Deny with matching code.
11. Run php bin/release-check.php and verify /admin/system/health before production promotion.

Android binary compilation, FCM delivery and physical-device notification approval require Android CI/device validation.
