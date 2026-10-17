# ePaper SaaS v1.0.0.1 — Health & Runtime Compatibility Hotfix

Apply over an existing ePaper SaaS v1.0.0 installation.

1. Back up `app/App.php`, `app/Services/Processing.php`, and `VERSION`.
2. Extract this ZIP into the application's outer Cloudways `public_html` directory and overwrite files.
3. Do not run database migrations. There are no schema changes.
4. Purge PHP OPcache/application cache if applicable.
5. Re-open `/health/ready`.

Expected behavior: the endpoint always returns JSON. If shell functions are disabled or processing binaries are missing, the JSON reports them as failed checks instead of rendering the generic `Request needs attention` page.
