# ePaper SaaS v1.0.0 — Release Test Results

## Passed in packaging environment

- PHP source syntax: **PASS — 35 PHP files**
- JavaScript syntax: **PASS**
- UUIDv7 generation: **PASS — 50 iterations**
- UUID BINARY(16) roundtrip: **PASS — 50 iterations**
- canonical analytics `page_view`: **PASS**
- unknown analytics event rejection: **PASS**
- core build guard: **PASS**
- table ownership manifest coverage: **PASS**
- tenant application SQL guard: **PASS — 97 SQL statements checked**
- critical route/capability guards: **PASS**
- frontend analytics events vs backend catalogue: **PASS**
- service worker/app version alignment: **PASS**
- JSON manifests: **PASS**
- undefined CSS custom properties: **0**
- `pdftoppm` executable present in packaging environment: **YES**
- `tesseract` executable present in packaging environment: **YES**
- ImageMagick `convert` present in packaging environment: **YES**

## Not available in packaging environment

The packaging PHP runtime does not provide:

- `pdo_mysql`
- `redis` PHP extension
- `imagick` PHP extension
- `curl` PHP extension

Therefore this release does **not** claim the following as executed here:

- fresh MariaDB schema execution
- live Redis ACL/cache operations
- live Digiti Platform service-token calls
- live OAuth callback
- live custom-domain resolution
- tenant-isolation diagnostic against installed MariaDB
- database-backed upload/processing/publishing flow
- live Deep Zoom generation through PHP Imagick

The browser installer and included CLI diagnostics are specifically provided to perform these checks in the target Cloudways runtime.
