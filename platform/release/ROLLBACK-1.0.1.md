# Rollback — v1.0.1

The release has no schema changes.

To roll back UI/runtime code, restore the v1.0.0 copies of `app/App.php`, `app/Core/View.php` and `public_html/assets/app.css`, and remove `app/Admin/Crud.php` / `public_html/update.php`.

The display-name update from `City ePaper` to `ePaper SaaS` is intentionally data-safe and may remain after rollback. If required, Platform Admin can edit the product name through SQL or restore the previous display name manually. Do not change product key `EPAPER` or application key `city_epaper` during rollback.
