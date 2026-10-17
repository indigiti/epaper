# Release Checklist — ePaper SaaS v1.1.0

## Before upload

- [x] City ePaper v2.2.9.1 parity manifest present
- [x] Route deletion guard PASS
- [x] Capability deletion guard PASS
- [x] Settings deletion guard PASS
- [x] Tenant SQL guard PASS
- [x] PHP syntax PASS
- [x] JS syntax PASS
- [x] Reader static parity PASS
- [x] Headless Chromium Reader smoke 12/12 PASS
- [x] HIGHRES/PREVIEW/THUMB/Deep Zoom Reader asset policy WebP PASS
- [x] RabbitMQ/Outbox/Worker static reliability guards PASS
- [x] Analytics registry PASS
- [x] PWA/service-worker version alignment PASS

## Cloudways deployment

- [ ] Take on-demand backup
- [ ] Extract cumulative ZIP into outer `public_html`
- [ ] Keep Webroot `public_html/public_html`
- [ ] Preserve `config/config.php` and `config/runtime.php`
- [ ] Run Composer if vendor dependencies are not already installed
- [ ] Restart Outbox / Render / Assets / OCR / Finalize Supervisor jobs
- [ ] Purge Cloudways/Varnish cache
- [ ] Hard-refresh Reader/browser cache

## Live release audit

- [ ] `/health/live` READY
- [ ] `/health/ready` READY
- [ ] `/health/platform` PLATFORM_BOUND
- [ ] `/health/queue` workers OK
- [ ] Infrastructure end-to-end probe PASS
- [ ] `php bin/release-check.php` PASS
- [ ] `/admin/system/release-audit` PASS
- [ ] Real PDF reaches READY
- [ ] WebP reader assets verified
- [ ] Preview + Publish PASS
- [ ] Mobile Reader gesture QA PASS
- [ ] Desktop Reader viewport QA PASS
- [ ] Clip / Smart Article / Search / Offline / Account Library PASS
- [ ] Cross-tenant isolation smoke PASS

Only after the live section passes should the release be promoted from **TESTED** to **VERIFIED**.
