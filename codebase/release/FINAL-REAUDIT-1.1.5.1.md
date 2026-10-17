# Final Re-audit — ePaper SaaS v1.1.5.1

State: **TESTED**

## Root cause
PHP converts dots/spaces in ordinary HTML form field names to underscores when populating `$_POST`. v1.1.5 rendered values such as `brand.site_name`, then the Brand handler attempted to read `$_POST['brand.site_name']`. PHP actually exposed that submission as `brand_site_name`, so a populated Site name appeared empty and the request failed with `Site name is required`.

The same PHP rule affected the generic Settings forms because Appearance, SEO, Reader, Processing and Clipping also use dotted setting keys.

## Resolution
- Added one centralized `postedSetting()` reader.
- New forms use `settings[<exact.dotted.key>]`, which preserves the exact setting key inside PHP's nested array.
- Backward compatibility accepts old cached v1.1.5 forms through PHP-normalized underscore keys.
- Brand partial/asset-only updates fall back to current stored identity values instead of inventing required-field failures.
- Remove Asset uses `formnovalidate`, so a file removal cannot be blocked by unrelated required text fields.
- Generic Settings POST persistence uses the same `postedSetting()` reader.

## Settings save audit
The regression suite explicitly exercises representative keys from every editable Settings group:
- Appearance — `appearance.admin_theme`
- Brand — `brand.site_name`, `brand.accent`
- SEO — `seo.title_suffix`
- Reader — `reader.default_fit`
- Processing — `processing.pdf_dpi`
- Clipping — `clipping.default_visibility`

Both the new nested encoding and legacy PHP-normalized submissions pass for all groups.

## Protected v1.1.4.1 / v1.1.5 behavior
- `app/Services/QrCode.php`: not modified.
- `app/Services/ClipService.php`: not modified.
- Source Page QR implementation: protected by exact baseline hashes and behavior markers.
- Brand upload storage/optimization: unchanged.
- CRUD Foundation routes/actions: unchanged.
- Reader, clipping, RabbitMQ, Supervisor, Platform and database behavior: unchanged.
- Frontend asset/service-worker version remains v1.1.5 because frontend content did not change.

## Tests
- Nested form encoding for all six Settings groups: PASS.
- Legacy normalized form encoding for all six Settings groups: PASS.
- Intentional empty optional value: PASS.
- Partial-update fallback: PASS.
- Direct dotted form-field scan: 0 remaining.
- Direct dotted `$_POST` read scan: 0 remaining.
- PHP syntax: PASS.
- v1.1.5.1 build guard: PASS.
- v1.1.4.1 Source Page QR/Clip exact hashes retained: PASS.

Promote to VERIFIED after a live save on `/admin/settings/brand` and one save on any other Settings page.
