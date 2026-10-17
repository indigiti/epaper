# Rollback — ePaper SaaS v1.1.5

No database migration is included.

1. Take a Cloudways on-demand backup before installing v1.1.5.
2. To roll back, re-apply the exact v1.1.4.1 Source Page QR Hotfix package used as this release baseline, or restore the pre-deploy Cloudways snapshot.
3. Remove `public_html/assets/crud.js` if restoring manually.
4. Purge Varnish/application cache and hard-refresh the browser/service worker.
5. Re-run the v1.1.4.1 release guard on the restored installation.

Brand files uploaded while v1.1.5 was live may remain under tenant-scoped `public_html/uploads/branding/{tenant_guid}`. They are inert after rollback unless referenced by a setting.
