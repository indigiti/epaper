# Digiti Platform — Pre- and Post-Change Audit

**Date:** 2026-11-01  
**Repository:** indigiti/epaper  
**Production deployment target:** cw-platform only  
**Baseline reviewed:** 152ae5b24d596155245ba83cb95e5ad18a83a20d  
**Change branch:** fix/platform-recovery-audit-20261101  
**Scope:** Platform PHP source, administrative routes/actions, domain creation/recovery/tenant reassignment, schema consistency, associated UI, CI and smoke/integration tests.  
**Out of scope:** Cloudways authenticated administration, production database inspection, DNS/SSL changes, production content migration, ePaper reader code/deployment.

## 1. What was inspected

- Entire \`app/\` source tree: 38 PHP source files including authentication, HTTP, database, tenant/permission resolver, onboarding, legacy registries, domain mapping, Restore and staging controls.
- Additional PHP entry points and setup/worker scripts: 51 tracked PHP files total as of audit.
- \`database/schema.sql\`, particularly unique hostname, tenant, product, organisation, application and audit relationships.
- The route dispatch chain in \`app/App.php\` and \`app/Admin/Crud.php\`, 40+ top-level handler methods and ~30 CRUD action handlers.
- CSS/HTML forms, links, status actions, protected settings, authentication flow and previous regressions.
- Existing three smoke/contract suites and two new functional/MySQL integration suites.

This is a source/CI audit with targeted database integration, not evidence that every production workflow has been click-tested.

## 2. Pre-change findings and outcome

| Severity | Finding before change | Change applied | Post-change evidence |
|---|---|---|---|
| High | GET /restore redirected to /admin instead of showing recovery steps | Confirm-before-restore GET page; typed record key; explicit CSRF-checked POST; return to existing domain Edit page | Functional audit; accessible action regression |
| High | REMOVED hostname still reserved, yet Add tried creating a duplicate | Both publisher and legacy domain create routes detect existing hostname and redirect to record or Restore | Functional audit; unique-key integration |
| High | Archived domains were invisible in onboarding | Added Archived domains inventory with recovery links | Source contract and UI smoke |
| High | Domain resolution accepted active but mismatched product/application/organisation/tenant records | Fail-closed relational joins, plus identical consistency checks in onboarding approval | Source and real MySQL consistency tests |
| High | Cascading organisation, product, tenant, application and plan archives could partially persist | Wrapped related mutations in transactions; retain audit and history | Source assertions + schema-backed coverage |
| High | User editor updated fields before validating a short replacement password | Validate first, apply all changes in a transaction | Functional audit |
| Medium | Edit domain could silently select first alternate tenant/app when current mapping no longer available | Explicit unavailable placeholder and deliberate selection requirement | Source and runtime tests |
| Medium | POST-only mapping errors returned to GET that bounced away from the edit page | Explicit safe error-return routing for mapping, restore, verification and onboarding | Runtime return-path tests |
| Medium | Direct service-account rotate could revive REMOVED token | Require Restore before rotation | Functional audit |
| Medium | Domain creation could flag publisher domains as canonical/primary | Force is_primary=0 for CUSTOMER_CUSTOM domains | Functional audit |
| Medium | Pasted newspaper URLs with https:// were rejected, and server hostname validation differed across flows | Shared strict publicHostname() that accepts a copied homepage URL but rejects credentials, IP, paths and unsupported schemes | Negative and positive runtime tests |
| Medium | Multi-insert application/domain/user creation could leave half-created data | Transactions and validation of URLs/roles | Functional audit + PHP lint |
| Low | Archive/Restore and onboarding navigation gave confusing recovery information | Recovery preview, annotated archived inventory, correct return paths | UI/source contract |

## 3. Automated post-change verification

The \`cw-platform\` workflow runs for pull requests targeting \`cw-platform\` and for deployment-branch pushes:

1. **PHP lint:** all tracked PHP files in app/, public_html/ and tests/.
2. **Onboarding source contracts:** routes, lifecycle, token and approval gates.
3. **Operator UI checks:** navigation, selected account, current tenant and visible warnings.
4. **Registry action checks:** row actions, restore/audit and protected staging reset.
5. **Platform functional audit:** restore navigation, duplicate handling, URL safety, entity consistency, transaction guards, archived-action behaviour and error return routes.
6. **Isolated MySQL 8 integration:** create two separate EPAPER publishers, store an archived DCN hostname, verify UUID decoding and parent relationships, confirm the DCNEP option is selectable, rebind the existing hostname, check unique-key preservation, and reject mismatched organisation/tenant joins.

**Result of first MySQL-backed PR check:** PASS, workflow run \`41297412300\` (all six gates). Later source changes require rerunning on the final head.

## 4. Known limits and remaining acceptance risks

- **Production database has not been inspected or modified.** In particular, DCN's existing product GUID, archive status and latest tenant mapping must be checked live before executing Restore or Reassign.
- **No Cloudways deployment or browser test has occurred.** Deployment from \`cw-platform\` must be staged and confirmed before updating the production application.
- ePaper independently caches domain-to-tenant mappings in Redis and a file cache. The Platform domain cache invalidation alone **cannot** immediately purge ePaper's caches; allow TTL/targeted purge and verify the two \`/health/platform\` diagnostics after a mapping change.
- The Platform resolver currently accepts \`ROUTING_VALID\` and \`SSL_PROVISIONING\` domains, rather than exclusively \`ACTIVE\`. Tightening this may interrupt existing working publisher sites; add a staged preview/go-live cutover after measuring current production usage.
- Manual Cloudways alias provisioning, DNS, certificates, media migration and final tenant isolation require external checks; this PR does not automate them.
- All management pages continue requiring a Platform administrator login. Publisher-facing role-limited administration is a separate permissions project.
- Automated tests cannot prove all forms in all browsers, all payment-provider webhooks, external API integrations or production database version drift.
- No hard-delete or production database reset is added. Staging reset remains opt-in, hostname-locked and limited to designated TEST_/DEMO_ domains.
- Restoring an archived parent never automatically restores all child records or publisher permissions. Those must be individually reviewed.

## 5. Cloudways staging acceptance checklist

1. Confirm recent restorable backups of **both Platform and ePaper** (database plus media/config). Use a protected Platform staging clone with an independent database and cache.
2. Deploy reviewed \`cw-platform\` commit to the clone; preserve private config and application document root.
3. Open \`/admin/domains\`, find an archived test domain and choose **Restore**. Verify it opens a visible confirmation (not \`/admin\`), rejects wrong confirmation, restores into \`AWAITING_DNS\`, and returns to **the same domain's Edit screen**.
4. Attempt Add Domain for an existing active hostname. Confirm it opens the existing onboarding record rather than creating another domain. Repeat for an archived hostname; confirm it opens Restore.
5. Select an alternative valid tenant; verify the application remains EPAPER, new owner organisation matches the tenant and the original domain GUID is preserved.
6. Confirm an incompatible product or inactive tenant cannot be selected or activated.
7. Verify ePaper tenant mapping after cache expiry or targeted purge, correct editions, publisher access, media permissions and no cross-tenant content.
8. Recheck DNS, HTTPS, login/redirect URI and documented go-live approval before promoting any new customer hostname.
9. Check permissions, CSRF, validation errors, browser/keyboard/mobile action controls and Archive/Restore for representative non-domain records.
10. Verify \`platform.digiti.in\` and the canonical ePaper domain still resolve and authenticate correctly. Do not modify \`cw-epaper\` or \`main\`.
11. Roll back using the previous \`cw-platform\` commit and a verified data snapshot as appropriate. **Reverting PHP alone never undoes a previous database record change.**

## 6. User-specific DCN recovery

The expected independent DCNEP tenant from previously supplied diagnostics/screenshot is \`01a195d7-3a9a-761d-bde8-3965e712ccd1\`. The default AMG tenant is \`01a05e76-d968-7e72-a4ef-aec784cf0547\`.

For the existing DCN hostname: Restore the *original* archived record if necessary, review its product/organisation compatibility, change its tenant to DCN/DCNEP while leaving application EPAPER, and reverify. **Do not delete or recreate the canonical ePaper installation or move newspaper content without a backed-up migration.**
