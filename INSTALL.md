# Digiti Platform v1.0.2 — install

1. Back up the current Platform application files.
2. Extract this ZIP into the existing outer Cloudways `public_html` and overwrite matching files.
3. Do not change the Webroot and do not reinstall the database.
4. In Platform Admin → Service Accounts → ePaper SaaS Production → Edit, add `auth.direct` to the comma-separated scopes.
5. Keep existing scopes including `domains.resolve`, `tenant.read`, `identity.verify`, `auth.exchange`, `errors.write`, etc.
6. Confirm `https://platform.digiti.in/health/ready` remains healthy.

No schema migration is required.
