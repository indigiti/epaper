# Release Checklist — ePaper SaaS v1.1.1

## Static/build gates
- [x] PHP syntax
- [x] JS syntax
- [x] JSON manifests
- [x] Baseline route preservation
- [x] Baseline capability preservation
- [x] Baseline settings preservation
- [x] Tenant SQL guard
- [x] Reader parity guard
- [x] Service-worker version alignment

## Live gates before VERIFIED
- [ ] Reader global header remains visible after load/idle
- [ ] Reader header and bottom pager remain visible
- [ ] Mobile pinch/zoom/pan regression
- [ ] Account cards render WebP previews on all library routes
- [ ] Continue Reading has one latest card per edition
- [ ] Admin role-specific navigation is correct
- [ ] Settings sidebar entry opens existing settings tabs
- [ ] Mobile Admin/Account drawer opens/closes correctly
- [ ] RabbitMQ/Supervisor health remains green after deploy
