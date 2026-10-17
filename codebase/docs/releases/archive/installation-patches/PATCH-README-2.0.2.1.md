# EP-DI v2.0.2.1 Patch

Apply this overlay only to **EP-DI v2.0.2.0**.

No database migration is required.

After overlay:

1. Reload PHP OPcache/PHP-FPM if required by your deployment process.
2. Open **Admin → Settings → Reader**.
3. Keep **Enable edition / back-date navigation = On**.
4. Choose **Edition navigation style = Dropdown**.
5. Verify Reader toolbar order: Download → Date/Calendar → Share.
6. Verify desktop, tablet, and mobile responsive behavior.
7. Run `php bin/release-check.php`.
8. Run `sha256sum -c SHA256SUMS`.
