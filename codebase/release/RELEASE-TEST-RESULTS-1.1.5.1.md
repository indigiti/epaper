# Release Test Results — ePaper SaaS v1.1.5.1

State: **TESTED**

## Root-cause regression test
PHP normalizes a direct form field named `brand.site_name` to `brand_site_name` in `$_POST`. v1.1.5.1 no longer depends on the dotted direct key.

## Settings families exercised by form-parser regression
- Appearance: PASS
- Brand: PASS
- SEO: PASS
- Reader: PASS
- Processing: PASS
- Clipping: PASS

For every family the test covers:
- new `settings[exact.dotted.key]` submission;
- legacy cached v1.1.5 direct dotted submission normalized by PHP;
- optional empty-value preservation;
- missing-field fallback for partial updates.

## Preservation checks
- v1.1.4.1 `QrCode.php` exact baseline hash: PASS
- v1.1.4.1 `ClipService.php` exact baseline hash: PASS
- Source Page QR markers: PASS
- v1.1.5 Brand Assets foundation: PASS
- v1.1.5 CRUD action families: PASS
- tenant-scoped destructive SQL checks: PASS
- immutable Audit/Error/Processing history guards: PASS
- no direct dotted form fields remain in the patched Settings renderer: PASS
- no direct dotted `$_POST` reads remain: PASS

## Syntax/release checks
- PHP syntax: PASS
- `crud.js`: PASS
- service worker: PASS
- v1.1.5.1 build guard: PASS
- release check: PASS

Live verification remains required for one save in Brand and one non-Brand Settings group before promoting to VERIFIED.
