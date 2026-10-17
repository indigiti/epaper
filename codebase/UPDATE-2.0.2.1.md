# Update to EP-DI v2.0.2.1

Base: **v2.0.2.0**. No database migration is required.

1. Overlay the v2.0.2.1 patch on the existing v2.0.2.0 application.
2. Reload PHP OPcache/PHP-FPM if your deployment workflow requires it.
3. No Supervisor worker restart is required for the Reader-only change.
4. Open Admin → Settings → Reader and set **Edition navigation style** to `dropdown`, `strip`, or `off`.
5. Test a Reader edition with multiple back dates on desktop, tablet, and mobile.
6. Confirm the dropdown only contains the exact same Publication + City + Language variant.
7. Run `php bin/release-check.php` and `sha256sum -c SHA256SUMS`.
