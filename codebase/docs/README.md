# EP-DI documentation map

The **active** documents live here. Historical release evidence remains at its original versioned paths until a coordinated artifact rebuild, because `codebase/SHA256SUMS` enumerates the old locations.

| Area | Start here | Purpose |
|---|---|---|
| Architecture | [Module map](architecture/MODULE-MAP.md) | How the multi-layer PHP application is assembled |
| Development | [Development workflow](development/README.md) | Where to change code, add tests and avoid brittle refactors |
| Operations | [Operational index](operations/README.md) | Staging, backup, CLI and Cloudways runbooks |
| Security | [Security cutover runbook](security/SECURITY-CUTOVER-RUNBOOK.md) | Secret incident and release-blocking verification |
| Security | [Application-key inventory](security/APP-KEY-ROTATION-INVENTORY.md) | Offline/FCM/media encryption dependencies |
| Security | [Security stabilization](security/SECURITY-STABILIZATION-2026-10-17.md) | Source remediation checklist |
| Security | [Cutover attestation template](security/SECURITY-CUTOVER-ATTESTATION.md) | Evidence required from an authorized operator |
| Testing | [Staging acceptance](testing/STAGING-ACCEPTANCE-HARNESS-2026-10-17.md) | Authenticated test identities and outage simulations |
| Audits | [Multi-layer security pre/post](audits/MULTILAYER-SECURITY-PREPOST-2026-10-17.md) | Platform, cache and rate-limiter changes |
| Releases | [Historical release policy](releases/README.md) | Pinned build manifests, update notes and checksums |

## Executable source ownership

- `../app/App.php`: PHP front controller and request dispatcher
- `../app/Core/`: HTTP, authentication, configuration, database and rate-limiting
- `../app/Services/`: publishing, reader, clipping, tenancy, workers, entitlement and integration services
- `../public_html/`: browser-facing assets and entry points
- `../bin/`: cron, queue workers, diagnostics and operator CLI
- `../database/`: DDL and sequential upgrades
- `../tests/`: executable regression tests
- `../release/`: pinned historical artifacts and parity evidence

New long-lived documents go under their relevant `docs/` category, **not** the application root. Do not place mutable customer PDFs, clips, caches, logs, credentials, or key files into version control.
