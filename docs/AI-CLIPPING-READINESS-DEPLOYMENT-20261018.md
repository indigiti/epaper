# AI Clipping Readiness — multilayer staging rollout (18 October 2026)

## Architecture
- HTTP: `App::runAdmin` dispatches GET `/admin/ai-clipping` to `AdminAiClippingController`, behind `Auth::requireAdmin()`.
- Authorization: OWNER/ADMIN/PUBLISHER_ADMIN/EDITOR only; OPERATOR and reader roles have no link or access.
- Domain service: `AiClippingReadiness` reads the effective private runtime flag, file prerequisites, web PHP capabilities and RabbitMQ configuration. No command is executed.
- Persistence: `SqlAiClippingReadinessRepository` checks the optional candidate table and tenant-scoped counts. Failed reads return UNAVAILABLE without exposing SQL internals.
- Presentation: `AiClippingReadinessView` escapes rendered values and links to AI Editorial Review only when the flag and schema permit it.
- Navigation: `AdminFeatureCatalog` exposes diagnostics even if AI processing is disabled; actual AI review stays feature-gated.

## Limitations
The dashboard is **read only**. It does not install Python, verify a running OCR worker, apply migrations, enable the private runtime flag, publish articles, generate clips, change media access, or deploy Cloudways by itself. A green static check is not live OCR or tenant-security certification. Existing manual clipping and the public reader stay unchanged.

## Deployment gates
1. Deploy source to isolated staging and confirm the private ePaper runtime file is preserved. Cloudways webroot remains nested `public_html/public_html` for the standalone `cw-epaper` branch. Do not deploy the repository root or platform folder to ePaper.
2. Run `php -l` on changed PHP modules and `php tests/ai-clipping-readiness-contract.php`, followed by admin workspace contracts, AI static tests and existing regression suite.
3. Log in as an allowed publisher and inspect `/admin/ai-clipping`. Verify 403 on OPERATOR, 404 or denial on reader sessions, no cached private HTML and no SQL disclosure when the optional candidate table is missing.
4. Do not turn AI on until an isolated backup/restore test, migration, private Python dependencies, worker process checks, real OCR test and tenant ACL validation succeed.
5. Confirm expected `cw-epaper` commit and real Cloudways release; a GitHub branch commit alone does not prove deployment was pulled. Retain rollback target.
