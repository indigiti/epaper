# Digiti Platform — query performance and replay-safe usage (2026-10-17)

## Status

Implemented on **draft PR #23 only**, not merged or deployed. Tests are controlled GitHub Actions fixture runs, not Cloudways latency benchmarks. No production credentials, data, backups or runtimes were changed.

## Improvements

| Path | Previous behavior | New behavior |
|---|---|---|
| Admin Commercial catalog | 2 catalog reads + 1 SELECT per plan (N+1) | Exactly **3 SQL reads**: features, plans and all prices; prices are grouped in PHP by plan GUID |
| Admin overview | 4 separate SELECT COUNT(*) calls | A single SQL SELECT containing four count subqueries |
| Platform entitlement cache | An empty `[]` was evaluated as a cache miss, re-running 3 SQL queries | Version-tagged cache envelope allows cached empty results |
| ePaper entitlement cache | Empty `[]` retriggered remote Platform API calls | Version-tagged envelope caches empty results; old cached payloads remain readable |
| Redis unavailable | Up to 1.5s connection attempt may repeat for each cache operation within one PHP request | Optional bounded connect timeout (default 0.5s), one failed connect marks cache unavailable for the rest of the request; database fallback continues |
| Usage metering | Atomic meter upsert, but HTTP retries could count twice | MariaDB `usage_events` ledger and meter increment committed together; stable event GUID duplicates are acknowledged without reapplying |

These are **structural improvements**, not measured P50/P95 speedups; live benchmarks will require staging with realistic data and Cloudways/Redis fault injection.

## Replay-safe usage API

The existing `POST /internal/v1/usage` endpoint accepts the optional `event_guid` field (UUID). A first valid event increments the meter and returns `{"ok":true,"duplicate":false}`. Repeating it with the **same tenant, feature, period and delta** returns `{"ok":true,"duplicate":true}` and never increments again. Reusing the GUID with different values returns HTTP 409 `USAGE_EVENT_CONFLICT`.

Legacy calls without `event_guid` are still accepted for compatibility; the Platform creates a fresh GUID per invocation, so **repeated legacy requests remain non-idempotent**. ePaper's `PlatformClient::usage(...,$eventGuid='')` can now forward a stable caller-owned event GUID. A newly generated ID for every business event attempt does not provide replay safety. Producers must durably reuse their IDs across retries.

The new table is created by additive migration `database/migrations/20261017_01_usage_events.sql`, invoked by the ordered, locked `bin/migrate.php`. CI calls the migrator twice to prove that its journal is repeatable and then checks `schema_migrations`.

**Data-growth policy:** The usage event ledger grows with metering traffic. Retention and archival require an explicit downstream idempotency horizon and validated reporting/reconciliation; do not delete event history casually.

## Remaining performance and security gates

- Measure real Cloudways latency/TTFB/P50/P95/P99 and MariaDB query plans for dashboard, catalog, Reader and clipping flows. This PR has no production benchmark access.
- Test Redis outage, recovery, and multi-process behavior under actual PHP-FPM/CLI/worker staging, including fail-closed authentication rate limiting.
- Add idempotency GUID persistence at actual ePaper business-event producers and delivery queue; the client convenience parameter alone does not persist the event across a crash.
- Build signed cross-application domain/entitlement invalidation or an explicit bounded-freshness cutoff. The ePaper domain cache can still retain a Platform suspension until expiration.
- Move other Admin CRUD entities to application/domain/infrastructure layers and add independent query-cost budgets for pagination.
- Continue isolated staging tests, private-config migration, operator credential rotation/history remediation, Platform database/media restore, and independent DigiOps two-app deployment.

**Do not deploy this draft directly**: earlier tracked `platform/config/config.php` and installer lock are deleted at the draft branch tip. A live `git pull` may delete working runtime config. The past public secret disclosure must be revoked and cleaned separately; see #2, #21 and #22.
