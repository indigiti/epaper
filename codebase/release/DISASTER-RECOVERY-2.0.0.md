# EP-DI v2.0.0 — Disaster Recovery Guide

## Recovery priority

1. Restore application configuration/secrets and canonical domain/platform binding.
2. Restore MariaDB durable data.
3. Restore original publication PDFs/media source assets.
4. Restore RabbitMQ connectivity and Supervisor worker configuration.
5. Rebuild Redis/cache state as needed.
6. Resume scheduler and processing consumers.
7. Verify generated media/manifests; regenerate derived assets if missing.
8. Verify Reader and publishing before reopening normal operations.

## Component authority

- MariaDB: durable source of truth.
- Original PDFs/media: durable publication source assets.
- Redis: cache/runtime/locks/heartbeats; rebuildable, not authoritative publication state.
- RabbitMQ: asynchronous job transport; reconcile with durable DB/outbox state after recovery.
- Supervisor: process lifecycle configuration.
- Compiled cache/manifests: rebuildable acceleration artifacts.

## Recovery validation

Run `/admin/system/health`, `/admin/system/release-audit`, one full edition process, one publication, one Reader session, one Archive/publication browse and one encrypted Offline Vault check before declaring service restored.
