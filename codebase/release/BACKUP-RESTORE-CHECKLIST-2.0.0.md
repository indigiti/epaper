# EP-DI v2.0.0 — Backup & Restore Checklist

Use this checklist as an operator record. The package cannot independently certify Cloudways backup/restore state.

## Before promotion

- [ ] On-demand application backup created.
- [ ] MariaDB backup created/included.
- [ ] Backup timestamp recorded.
- [ ] Deployed rc.3.1.1 version and package checksum recorded.
- [ ] `.env` / runtime configuration recovery path known.
- [ ] Original PDF storage and generated media locations included in recovery plan.
- [ ] Supervisor/RabbitMQ configuration reference recorded.

## Restore rehearsal

- [ ] Restore to a safe/staging target or use the approved Cloudways restore process.
- [ ] Application boots without PHP fatal errors.
- [ ] Database schema/version is correct.
- [ ] Publications/editions/pages are present.
- [ ] Original PDFs and generated THUMB/PREVIEW/HIGHRES/tiles are reachable.
- [ ] Redis is treated as rebuildable cache/runtime state, not authoritative data.
- [ ] RabbitMQ connectivity is restored.
- [ ] Supervisor worker lanes are healthy.
- [ ] Scheduler heartbeat resumes.
- [ ] Reader can open a published edition.
- [ ] Encrypted Offline Vault entitlement/device behavior is rechecked.

## Recovery ownership

MariaDB and original media are durable recovery assets. Redis cache state may be rebuilt. RabbitMQ/Supervisor runtime should be restored from known configuration and durable job/outbox state rather than from ad-hoc process memory.
