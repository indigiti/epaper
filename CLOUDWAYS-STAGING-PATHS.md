# Cloudways isolated staging: source layout and webroot safety

**Do not run these steps in either currently live Cloudways application.** Verify first on independently cloned, isolated staging applications. The source branches here are not production-approved.

## Cloudways Git layout to validate

Cloudways' Git Deployment defaults to placing repository files inside the application's `public_html/` when Deployment Path is empty. Cloudways Flexible also supports setting the webroot to a subfolder inside that directory. Consult current Cloudways documentation:

- https://support.cloudways.com/en/articles/5124087-how-to-deploy-code-to-your-application-using-git-on-cloudways-flexible
- https://support.cloudways.com/en/articles/5124748-how-to-change-the-web-root-of-an-application

**For a fresh staging clone only**, candidate layout with Git Deployment Path left empty:

```text
<APP_DIR>/
  public_html/                     # Git application/source root
    app/                            # private PHP code; NOT HTTP accessible
    bin/                            # worker and migration commands
    config/                         # private staging config, persistent outside Git
    database/                       # migrations and schema
    storage/                        # persistent staging runtime state
    public_html/                    # actual HTTP document root (webroot subfolder)
      .htaccess
      index.php
      assets/
      ...
```

Before deploying source, configure **Application Settings → Webroot** to the nested `public_html` subfolder, relative to Cloudways' default `public_html/` (the exact UI may differ by product). A misconfigured webroot can expose `app/`, `config/`, `database/` and application internals. Do not try this live.

Ensure PHP-FPM uses the nested document root, and that direct requests for `/app/bootstrap.php`, `/config/config.php`, `/database/schema.sql`, `/bin/worker.php`, `/.git/config`, and `/CLOUDWAYS-RELEASE-NOTICE.md` are rejected with 403/404 **rather than serving source or content**. This is a security gate. The installer endpoint must be appropriately locked; do not treat a new empty storage directory as a valid existing installation.

## Persistent data and dependency gate

- **Platform**: restore its own MariaDB database and private configuration into an isolated staging environment; prepare the staging install lock and any workers/cron for the *actual* source root.
- **ePaper**: restore its separate MariaDB plus PDFs, page/media assets, clips, original uploads and any public branding files; preserve existing lock files. Populate Composer dependencies from the pinned `composer.lock` into staging release. This source-only Git branch intentionally does **not** include `vendor/`.
- Maintain persistent `config/config.php`, `config/runtime.php` (if used), `storage/` and `public_html/uploads/` outside any replaceable or deleted tree, mounting/copying them into staging as appropriate. Never commit their contents.
- Configure distinct staging DB names, Redis prefixes, staging-only HMAC secret, Platform→ePaper receiver URL, cron and workers. The effective CLI source path is `<APP_DIR>/public_html/bin/*.php` in the layout above, **not** `<APP_DIR>/bin/*.php`.
- Back up/restore and compare database/media counts and SHA256 before and after any deployment and rollback. Do not direct-pull a new branch into an existing Git checkout when it changes tracked files (particularly ePaper historical `vendor/`), or point either staging app at production databases.
- Verify HTTPS/WAF, login, product/tenant/domain isolation, Platform signed invalidation ACK, suspension propagation, Reader, PDF/clipping, process worker, scheduler, cache and rollback separately. Source CI does not do this.

Only after all gates succeed should a reviewed deployment package or updated `cw-platform`/`cw-epaper` live branch be considered. Historical credential/key revocation and Git-history remediation remain required.
