# ePaper SaaS v1.1.5.1 — Settings Form Normalization Hotfix

## Fix
PHP normalizes dots in normal form field names into underscores. v1.1.5 rendered settings such as `brand.site_name`, then attempted to read the impossible `$_POST['brand.site_name']` key. This caused valid Brand edits to fail with `Site name is required`.

v1.1.5.1:
- renders settings fields as `settings[brand.site_name]`, preserving the exact dotted key;
- accepts old cached v1.1.5 forms by also recognizing PHP-normalized keys such as `brand_site_name`;
- uses existing saved Brand values as fallback during asset-only/partial updates;
- adds `formnovalidate` to Remove Asset so removing a logo does not depend on unrelated required text fields;
- applies the same normalized setting reader to Appearance, SEO, Reader, Processing, Clipping and other grouped Settings pages.

No database, Reader, Source Page QR, clipping, RabbitMQ, Supervisor or Platform changes.
