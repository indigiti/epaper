# ePaper Publishing Studio — Admin Module Audit (19 October 2026)

**Source reviewed:** `indigiti/epaper`, staging baseline `cw-epaper`.  
**Evidence level:** static review of PHP route dispatch, feature catalog, key admin methods, database schema, screenshot of Audit Trail, and existing QA contracts. **This is not live authenticated browser acceptance or a Cloudways performance benchmark.**

## Scope and principles

- Preserve tenant isolation, role authorization, CSRF checks, audit history, and direct routes even when relocating an item in navigation.
- Regular editorial work belongs near Publishing Desk; infrequent diagnostics belong under a collapsed **Advanced** section, not deleted.
- Do not delete storage, migrations, log tables, queue workers, backups, or an operational module on the basis of low usage alone.
- Any actual module retirement requires traffic/dependency analysis, approved deprecation, migration plan, and staging regression.

## Navigation audit and decision by page

| Route / admin module | Current functional purpose (source) | Treatment | Follow-up |
|---|---|---|---|
| `/admin` Publishing Desk | Role-aware launchpad, edition counts, editorial action center, recent editions, runtime alerts | **Keep / primary** | Measure snapshot/query latency; reduce repeated panels |
| `/admin/editions` Editions | Edition creation, metadata, schedule, publish/archive, reprocess, status, removal, page sequencing | **Keep / primary** | Separate large controller into edition actions and listing; regression test delete and sequence |
| `/admin/manual-clipping` Clip Marking | Editorial page-area outlines and optional private clipping | **Keep / primary** | Validate staging geometry overlay in all Reader views |
| `/admin/publications` | Publication/brand records and status | **Keep / primary** | Validate create/edit/remove confirmation and domain scopes |
| `/admin/articles` | Semantic article content and publishing | **Keep / editorial** | Audit list pagination, published visibility, authorization |
| `/admin/highlights` | Promoted/homepage editorial content | **Keep / editorial** | Confirm used by public homepage; combine editor UX if possible |
| `/admin/ai-review` | Review and correction of OCR/AI article regions | **Conditional / editorial** | Only advertise when AI candidates enabled; test approval isolation |
| `/admin/ai-clipping` | AI feature readiness, setup and configuration diagnostics | **Keep / advanced** | Do not confuse with AI Editorial Review |
| `/admin/navigation` | Reader navigation scope drafts, preview, publish, version history | **Keep / discovery** | Simplify dense editor forms using progressive disclosure |
| `/admin/topics` | Topic taxonomy and discovery | **Keep / discovery** | Merge management UI with entity/author tabs only after workflow review |
| `/admin/entities` | Named entities and semantic associations | **Keep / discovery** | Verify editorial linking and search relevance |
| `/admin/authors` | Bylines and author profiles | **Keep / discovery** | Verify uniqueness and article references before cleanup |
| `/admin/subscribers` | Reader/subscriber status | **Keep / audience** | Check filtering, counts and authorization |
| `/admin/plans` | Subscriptions and entitlements | **Keep / audience** | Protect billing and read-only access |
| `/admin/master-data` | Cities, languages, sections | **Keep / settings** | Separate master-data panels and protect referenced rows |
| `/admin/settings` | Brand, SEO, reader, processing and clipping configuration | **Keep / settings** | Investigate overlapping `appearance.reader_theme` vs `reader.theme` keys |
| `/admin/access` | Publisher membership and access | **Keep / settings** | Review route-level owner/role enforcement |
| `/admin/processing` | Job statuses and queue recovery | **Keep / operations** | Verify retries, idempotency, failure states |
| `/admin/system/testing` | Non-destructive staging acceptance/readiness | **Keep / operations** | Clarify automated probes vs manual functional acceptance |
| `/admin/system/health` | Operational readiness and dependency checks | **Keep / operations** | Link problem checks to specialized recovery actions |
| `/admin/errors` | Error events and failed job diagnostics | **Keep / operations** | Add server-side filters/pagination next; currently capped at 200 errors + 50 jobs |
| `/admin/audit` | Security/admin audit history | **Keep / advanced; rebuilt** | 50-row keyset paging, role-bound, date/action/result/request filtering |
| `/admin/performance` | Cache/performance telemetry and actions | **Keep / advanced** | Separate read-only metrics and mutation actions |
| `/admin/storage` | Storage and backup diagnostics | **Keep / advanced** | Confirm backup/restore safety; never remove stored customer assets |
| `/admin/system/release-audit` | Release provenance/integrity | **Keep / advanced** | Don't merge with audit-event log; different purpose |
| `/admin/system/infrastructure` | RabbitMQ and media/service configuration | **Keep / infrastructure-gated advanced** | Limit to canonical admin host |
| `/admin/system/infrastructure/supervisor` | Worker/supervisor diagnostic instructions | **Keep / infrastructure-gated advanced** | Consider linking under Infrastructure UI, keep deep links |
| `/admin/system/recovery` | Queue, scheduler, Redis, outbox safe recovery | **Keep / infrastructure-gated advanced** | Read-only diagnosis before any mutation |
| `/admin/system/upgrade` | Migration/schema preflight and repair | **Keep / infrastructure-gated advanced** | Group failed checks first; preserve idempotency |
| `/admin/account` | Account profile entry point | **Keep / sidebar footer** | Remain discoverable without expanding sidebar |

**Scope count:** 30 top-level admin destinations listed in the feature catalog (depending on role, AI flag and infrastructure entitlement). Dynamic action subroutes, JSON exports and edition/page-specific detail routes remain functional and are not menu items.

## Findings

### Verified by source
1. The prior audit handler was `SELECT * ... ORDER BY occurred_at DESC LIMIT 200`. No built-in filters or pagination. **Replaced** by `AdminAuditController` with a fixed 51-row fetch (50 display + one "has more") and tenant-bound keyset pagination against the existing `ix_audit_tenant(tenant_guid, occurred_at)` index.
2. The `App.php` entry point remains extremely large (~400 KB / ~1,850 lines) with many admin dispatch/actions in one class. Prioritize incremental extraction; do **not** rewrite the routing stack wholesale.
3. The sidebar and Publishing Desk launcher share `AdminFeatureCatalog`; reorganizing this catalog, not duplicating route lists, minimizes maintenance.
4. `adminDashboardMetrics()` was a private method with no call sites in `App.php`. **Retired** after reference check.
5. Audit events include `metadata_json` and actor/resource identifiers. The new default audit table intentionally excludes metadata to reduce data exposure and row width.
6. Admin `Error Center` and `Upgrade Center` have distinct diagnostic responsibilities; automatically removing them because similar health pages exist would be unsafe.
7. Existing side nav persists expanded/collapsed groups in local storage. Group-storage version bumped to reset old group keys; search now matches descriptions as well as labels.

### Not yet verified
- Real Cloudways tenant permissions for every deep link, particularly routes not visible to a role in the navigation catalog.
- Whether any named module has zero user traffic, zero dependent tables, and no integrations.
- Real browser screenshots after deployment at mobile/tablet/desktop widths.
- Database query plans or wall-time latency in staging.
- AI/PDF worker performance under load, route-specific errors, and workflow completion.

## Retirement register

| Candidate | Decision | Rationale |
|---|---|---|
| `App::adminAudit()` legacy 200-row view | **Retired in PR** | Replaced with paginated, filtered controller; existing route preserved |
| `App::adminDashboardMetrics()` private helper | **Retired in PR** | No static call sites; other Publishing Desk snapshot remains authoritative |
| AI Clipping setup when feature flag off | **Move to Advanced; retain** | Needed to diagnose/enable safely |
| Supervisor Doctor as a standalone sidebar item | **Move to Advanced; retain** | Distinct from core Infrastructure action; avoid breaking direct links |
| Release Audit vs security Audit Trail | **Retain separately** | Release integrity differs from action history |
| Articles / Highlights / Topics / Entities / Authors | **Retain** | Different data workflows; no safe evidence of redundancy |
| Storage, backups, upgrade, recovery | **Retain and privilege-gate** | Destructive or safety-critical to remove or conflate |
| Legacy PHP/data/migrations | **No deletion approved** | Requires dependency graph and restore evidence |

## Implementation in this PR

- Reclassify navigation into Overview, Publishing, Editorial, Discovery, Audience, Settings, Operations, Advanced and Account; all former destinations remain discoverable.
- Keep role-based nav filtering and open the group containing the active page.
- Include module descriptions in sidebar search, update group preference version to prevent stale group IDs.
- Audit Trail: date/action/result/request filters; strict UUID validation; tenant-bound prepared SQL; keyset pagination; result badges; readable timestamps; no metadata loaded.
- Retire the two specific obsolete methods above.
- Add source/role contract tests and CI lint for the new controller.

## Subsequent gates

1. GitHub CI: PHP 8.3 lint, role catalog contracts, audit filters contracts, and existing ePaper pipeline tests.
2. Cloudways **staging only**: authenticated owner/editor/operator access checks for *every* route; test restricted deep links, CSRF on POST, and tenant A/B separation.
3. UX: capture desktop/tablet/mobile snapshots; check typography, accessible focus, form save errors, advanced group discoverability and click targets.
4. Performance: audit SQL EXPLAIN, admin page navigation timing, cache staleness, slow queries, real edition and job volume.
5. Retirement: only after an explicit unused-code dependency review and rollback approval; no storage/database purges as part of a navigation cleanup.

No production deployment is part of this source-only PR.
