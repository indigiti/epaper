# EP-DI v2.0.0-rc.3.1.1 — Rollback

Rollback target: exact `v2.0.0-rc.3.1` release.

No database rollback is required because rc.3.1.1 contains no schema migration. No queue/Supervisor rollback is required.

Restore the rc.3.1 versions of `VERSION`, `public_html/assets/app.css`, `public_html/service-worker.js`, the three offline shell HTML files, release-check/test files and release documentation; or restore the complete exact rc.3.1 package.

After rollback, hard-refresh once so the rc.3.1 Service Worker and CSS assets become active.
