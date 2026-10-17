# EP-DI v2.0.1.8 Overlay Patch

Apply only to a clean or deployed **v2.0.1.7** codebase.

After overlay:
1. Run `database/upgrade-v2.0.1.8-trusted-app-desktop-login.sql`.
2. Preserve production configuration and add optional Firebase server settings manually.
3. Restart PHP/Supervisor workers.
4. Rebuild the Android application with Firebase project variables if push notifications are required.
5. Run `php bin/release-check.php`.
