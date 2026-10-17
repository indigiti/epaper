# Release Checklist — ePaper SaaS v1.1.5

## Build gates
- [x] v1.1.4.1 Source Page QR exact file hashes
- [x] Brand upload UI and validation
- [x] Brand replace/remove
- [x] Dark logo / favicon / Apple Touch wiring
- [x] Admin CRUD actions
- [x] Account CRUD actions
- [x] Immutable system record protections
- [x] POST/CSRF route model retained
- [x] Tenant-scoped destructive SQL guard
- [x] Headless Chromium UI smoke
- [x] Source QR decode smoke
- [x] ZIP/SHA verification

## Live gates before VERIFIED
- [ ] Brand save no longer throws the reported Request needs attention error
- [ ] Primary/Dark logo uploads render
- [ ] Favicon renders
- [ ] Square mark derivatives generate on Cloudways Imagick
- [ ] Source Page QR hotfix remains correct
- [ ] Publication/Highlight/Master-data CRUD smoke
- [ ] Edition edit/archive/restore smoke
- [ ] Subscriber local status smoke
- [ ] Account edit/remove/clear smoke
- [ ] Full `php bin/release-check.php` PASS on live complete installation
