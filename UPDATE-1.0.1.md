# Update Existing `platform.digiti.in` to v1.0.1

1. Back up the current application files and Platform database.
2. Extract the v1.0.1 hotfix into the existing Cloudways application's outer `public_html`, overwriting matching files.
3. Keep the Webroot unchanged: `public_html/public_html`.
4. Sign in to Platform Admin.
5. Open `https://platform.digiti.in/update.php`.
6. Click **Apply v1.0.1 update** once.
7. The update renames the EPAPER display product/application from `City ePaper` to `ePaper SaaS`. Stable integration keys remain unchanged.
8. Open `/admin/products`, `/admin/applications`, `/admin/organisations`, `/admin/domains`, `/admin/commercial`, `/admin/subscriptions` and verify the new Actions controls.
9. Purge Cloudways/Varnish cache if it is enabled.

`/update.php` is administrator-authenticated and idempotent through `schema_migrations`.
