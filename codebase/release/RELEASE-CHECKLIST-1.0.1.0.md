# Release Checklist — ePaper SaaS v1.0.1.0

Release state: **TESTED**

Before production VERIFIED:

- [x] PHP syntax
- [x] JavaScript syntax
- [x] UUID/analytics smoke tests
- [x] route/capability/PWA guards
- [x] 146 tenant SQL statements checked
- [x] no schema migration
- [x] Rabbit topology/retry/dead-letter static guards
- [x] transactional outbox/publisher-confirm static guards
- [x] Supervisor lane/heartbeat/stale-recovery static guards
- [ ] Cloudways RabbitMQ package enabled and authenticated
- [ ] `composer install --no-dev --optimize-autoloader` completed on Cloudways
- [ ] `/health/queue` = QUEUE_READY
- [ ] outbox consumer = 1
- [ ] render consumer = 1
- [ ] asset consumers = 2 (or intentionally configured count)
- [ ] OCR consumer = 1
- [ ] finalize consumer = 1
- [ ] real PDF reaches READY through new pipeline
- [ ] retry/dead-letter behavior exercised safely
- [ ] Reader/media/tiles/OCR/search/clip regression on generated edition
- [ ] cross-tenant isolation diagnostic PASS on live database
