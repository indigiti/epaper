# Platform post-login security, domain lifecycle and UI audit — 18 October 2026

> **Status:** audited source and disposable CI passed. **NOT deployed to Cloudways.**
> **Audit branch:** `audit/platform-multilayer-20261017` (draft PR #23). **Do not merge/deploy to `cw-platform` until the release gates below pass.**

## Evidence and boundaries

1. Operator screenshot demonstrated `https://platform.digiti.in/admin` authenticated Overview rendering after Cloudways Platform Varnish exclusions. The operator's earlier HTTP 419 logs had `reason=token_mismatch`; cookie/session diagnostics passed but the complete causal chain was not proved. Issue #26 retains these facts. **Do not remove the Platform `/login` and `digiti_platform` cache exclusions.**
2. Platform suite includes PHP syntax, static policy contracts, MariaDB fixture and synthetic authenticated HTTP admin testing:
   [GitHub Actions, all Platform jobs green at SHA `12d408b8`](https://github.com/indigiti/epaper/actions/runs/39069807674).
3. The build is deliberately separated from `cw-platform` and Cloudways. No live admin credential, customer domain DNS, real SSL issuance, database or media data was changed by these GitHub tests.
4. User's Overview screenshot was visually inspected; authenticated CI fetches every Admin page and checks page titles/semantic form presence. **This is not an automated pixel-diff audit or cross-device screenshot certification.** Follow up with actual mobile/browser staging screenshots before release.

## Confirmed pre/post changes

| Area | Before | After (audit branch only) |
|---|---|---|
| Unapproved domain routing | `ROUTING_VALID` and `SSL_PROVISIONING` may resolve from Platform API | Only `ACTIVE` domain state resolves; existing parent/tenant/product checks remain |
| New domain primary flag | Admin can set `is_primary=1` while awaiting DNS | New domain is always nonprimary until verification, activation, then explicit Edit |
| Competing primaries | Edits could set multiple primary hostnames for one tenant | Parent tenant `FOR UPDATE` lock serializes primary changes and clears previous primary |
| Suspend/resume | Suspended host cannot be verified or directly edited active; no resume path | Explicit `POST /admin/domains/{guid}/resume`: `SUSPENDED→AWAITING_DNS`, then Verify DNS/TLS and explicit Activate |
| Cloudflare TLS activation | Admin Activate could mark `SSL_PROVISIONING` active before edge reports readiness | `canActivate()` gates Cloudflare on `edge.status=ACTIVE`; manual edge uses confirmed `ROUTING_VALID` |
| Background revalidation | Edge provider `ACTIVE` could silently promote a pre-approved status | Automated checks cannot bypass explicit Admin activation. Cloudflare TLS-loss can downgrade active routing |
| Domain picker | No product-linked option filtering; mismatched choices led to generic 500 | Tenant and application options carry product GUID; incompatible apps disabled client-side; server-side integrity check preserved and validation message rendered inline |
| Removed domain editor | A removed record could be edited without reactivation | Removed records read-only; actions hidden in registry; history preserved |
| Login CSRF / proxies | Browser 419 on Cloudways, affected by Varnish configuration | Cloudways URL/cookie exclusions restored operator access; application additionally emits `private, no-store` for authenticated routes |
| Admin sign-out | GET `/logout` mutated authenticated state | CSRF-protected POST only; GET is a harmless redirect |
| Dashboard counts | Organisation KPI included historic removed organisations | Active organisations only |
| Keyboard/mobile polish | Limited visible keyboard focus styling | Added focus-visible state and compact mobile improvements |

## Disposable regression coverage

**Pure PHP policy and contract tests:** domain activation invariants, preapproved resolution denied, removed domain prohibition, resume route, invalidation staging, CSRF/session roundtrip, pre-existing billing, tenant, subscription, metering and policy suites.

**MariaDB lifecycle integration (`platform/tests/domain-mariadb-integration.php`):** create two `@example.invalid` hostname fixtures; prevent primary-before-active; validate product-scoped ePaper outbox; deny preapproved resolution; activate manual edges; choose/transfer primary; reject platform-reserved domain type edits; suspend and verify immediate deny; require explicit resume+DNS approval; block premature Cloudflare TLS activation; allow ready edge activation; soft-remove and deny routing/recovery.

**Authenticated HTTP smoke (`tools/platform_admin_http_smoke.py`):** CI-only admin seeded in disposable database; redirects for unauthenticated access; CSRF-valid wrong-password behavior; successful login/session regeneration; GET coverage of Overview, Organisations, Users, Products, Applications, Product Tenants, Domains, Service Accounts, Commercial, Subscriptions, Billing, Operations, Audit, Errors, Health and Security. Tests missing/invalid CSRF rejection; matching-tenant domain creation and registry rendering; edit-page HTML/CSRF; private no-store headers; GET logout nonmutation; signed-out session revocation.

**Protection:** Both CI fixtures use synthetic non-real identities. Database mutation script fails closed unless `EPDI_BILLING_INTEGRATION=disposable-ci`. Never run against live MariaDB.

## Deployment gates — NOT YET SATISFIED

1. **Restore production PHP files to canonical state:** delete ALL temporary public `platform-session-check-*.php`, `platform-login-csrf-route-*.php`, recovery scripts and backup diagnostics; restore the original diagnostic-patched `app/Core/Http.php` without discarding unrelated live fixes. Confirm Cloudways config/config.php and install.lock remain private and unmodified.
2. **Secrets and history:** resolve credential rotation / historical tracked runtime secrets. Address issue #2/#22. Source-only deploy must not delete protected runtime configuration.
3. **Platform isolated backup and restore:** ePaper's past backup is not a substitute. Verify current Platform DB, config, session handler, media/file runtime state in separate disposable Cloudways staging.
4. **Staging browser/UI matrix:** desktop and narrow mobile widths, both color schemes, login/logoff and all Admin form workflows; test two separate tenants and products; verify duplicate primary denial; test invalid domain and duplicate hostname inline errors.
5. **Cloudways Varnish:** keep URL `/login` and cookie `digiti_platform` exclusions, add secure-session bypass for other authenticated routes if necessary; validate actual GET→POST CSRF from clean browser and never cache `/admin`, `/auth`, `/logout`.
6. **ePaper integration:** staging `epaper.digiti.in` must deny unapproved/suspended/removed hostnames and accept active approved tenants. Verify signed cache invalidation outbox receiver, app-bound access, Reader, archive, clipping and PDF after revocation.
7. **Edge provisioning and restore:** test Cloudflare TLS readiness/renewal with a disposable owned hostname. Customer-domain **registrar renewal** remains an external registrar task, not a capability implemented by Platform.
8. **Release tooling:** promote through reviewed draft PR, regenerate manifests, compare deployed Cloudways branch/source roots, rollout Platform and ePaper independently, monitor errors and 419s. Production unchanged until the gates above are signed off.

## Safe operator flow

Add domain → provide tenant and matching app → configure DNS → **Verify DNS** → wait for edge TLS readiness → **Activate** → optional **Edit → Primary**. To suspend, Edit → SUSPENDED (routing revoked). To resume, **Resume verification** → Verify DNS → wait for TLS → Activate. To remove, use Remove (soft delete, retained audit trail; removed record cannot be restored through edit).

Subscription period edits manage product subscriptions, **not domain registrar bills**. Domain registrar renewal and real certificate lifecycle involve the authoritative registrar/edge provider.

## Source and tracking

- PR: https://github.com/indigiti/epaper/pull/23
- Cloudways incident: https://github.com/indigiti/epaper/issues/26
- Security/draft release checks: `platform/docs/architecture/STAGING-PERFORMANCE-DEPLOYMENT-GATES.md`
