# EP-DI v2.0.0 — Production Acceptance Report

## Release status

**Package status:** RELEASE-GATE VERIFIED  
**Promotion status:** READY FOR CONTROLLED PRODUCTION PROMOTION, subject to live environment gates below.

The v2.0.0 package is built from the exact v2.0.0-rc.3.1.1 functional baseline. The user confirmed the corrected publication newsstand experience is working on the live installation. This environment has not independently executed authenticated Cloudways backup/restore, physical device/browser, or controlled service-degradation tests; those remain operator acceptance items and are not falsely marked as passed.

## Automated/package acceptance

- [x] Stable VERSION/PWA cache identity aligned.
- [x] Cumulative release check passes in packaged source.
- [x] PHP syntax validation passes.
- [x] JavaScript/service-worker syntax validation passes.
- [x] Tenant SQL isolation guard passes.
- [x] Reader parity regression passes.
- [x] Offline Vault static/security regression passes.
- [x] Admin Console regression passes.
- [x] Public Newsstand/Archive regression passes.
- [x] Production readiness static guard passes.
- [x] Database directory is byte-identical to rc.3.1.1.
- [x] `app/App.php` is byte-identical to rc.3.1.1.
- [x] Route dispatcher is unchanged from rc.3.1.1.
- [x] Stable internal SHA-256 manifest verifies.
- [x] Stable hotfix applied over exact rc.3.1.1 is byte-identical to the cumulative stable package.

## Live/operator acceptance required before irreversible promotion decision

- [ ] `/admin/system/health` reports `READY` with no required `ACTION REQUIRED` / `DEGRADED` checks.
- [ ] Current Cloudways application + MariaDB backup recorded.
- [ ] Backup/restore rehearsal completed or accepted under the operator's production policy.
- [ ] Publishing process/reprocess/schedule path verified on the live environment.
- [ ] Reader checked at representative desktop/tablet/mobile widths.
- [ ] One encrypted Offline Vault edition checked offline on a real browser/device.
- [x] Publication newsstand horizontal rail/non-stretched cover behavior reported working by the user.
- [ ] Live robots/sitemap/NewsArticle structured-data checks completed.
- [ ] RabbitMQ/Supervisor/scheduler health verified after promotion.

## Decision rule

If all required live checks are acceptable and no critical/high defect remains, the exact checksum-locked v2.0.0 artifact may be treated as the Production Stable release. If a critical/high regression is found, do not mutate the stable ZIP; correct the defect in a new release/hotfix and preserve the original checksum for auditability.
