# Smart News Clipping — phase 2 staging deployment and acceptance (18 October 2026)

**Status: DRAFT / STAGING ONLY.** Source lives on [PR #29](https://github.com/indigiti/epaper/pull/29), stacked on [PR #28](https://github.com/indigiti/epaper/pull/28). No production deployment or merge is authorized by passing CI.

## Implemented boundaries

- **Tesseract hOCR** (existing) → validated `epdi.ocr.v1` words and hOCR hierarchy → `epdi.layout.v1` text region grouping → `epdi.segmentation.v1` headline, body and photo/caption proposals; column-first local story links. The image detector uses pinned OpenCV classical texture segmentation, not a trained newspaper AI model.
- **RabbitMQ**: the existing `PAGE_OCR` stage commits authoritative Tesseract OCR first; then, only with a private runtime `ai_clipping.enabled=true`, invokes `AiCandidatePipeline::stage()`. It independently verifies request-tenant page/edition ownership and the resolved source image under the tenant+edition media directory, calls bounded Python CLI with private 0600 temp files, verifies returned tenant/page/schema and inserts only a new candidate. Errors are isolated from legacy OCR completion; no new RabbitMQ lane.
- **MariaDB**: isolated `ep_ai_page_candidates` table per tenant and page (candidate JSON, source hash, correction JSON, optimistic revision, editor and status). The migration is **NOT** run automatically; actual `ep_articles` and `ep_clips` remain untouched. OCR retries do not overwrite reviewed corrections.
- **Editor**: `/admin/ai-review` uses the established authenticated `runAdmin()` route, CSRF-validated POST and additional OWNER/ADMIN/PUBLISHER_ADMIN/EDITOR role restriction. Tenant-scoped queries, no-store response, private preview with realpath media containment, bounded editable type/text/box fields, compare-and-swap revision and audit log. Image overlays are click-to-edit, drag-to-move and Shift-drag-to-resize. “Mark reviewed” only changes candidate status; it does **not** publish, create a clipping or grant access to readers.
- **Feature flag**: `ai_clipping.enabled` defaults **false**. No admin toggle is exposed; private config/staging operator controls it.

## Required isolated staging operator sequence — do NOT use on live Cloudways

1. Verify independent ePaper app+MariaDB+media backup and isolated successful restore; do not touch `cw-platform` or `cw-epaper` production branches.
2. Use a staging checkout of stacked PRs #28 and #29. Ensure `codebase/` remains the application root and `public_html/` the document root. Keep runtime private config and media persistent and outside atomic deletion.
3. Apply `codebase/database/upgrade-ai-page-candidates-20261018.sql` to the **staging** ePaper DB, after inspecting schema and ensuring a rollback DB snapshot.
4. Use isolated Python 3.12 runtime, then install `codebase/ai_processing/requirements-image.txt` (pinned NumPy/OpenCV; CPU only). Ensure Python interpreter is accessible to the queue worker, not public request users.
5. In **private staging config only**, set `'ai_clipping'=>['enabled'=>true,'python_binary'=>'/absolute/private/path/to/python3','timeout_seconds'=>30]`. Do not commit runtime secrets, staging paths or operator credentials.
6. With a disposable test tenant and edition, run existing RabbitMQ `PAGE_OCR`. Verify original `ep_page_ocr` and `ep_ocr_blocks` still populate, one `PENDING` candidate appears, and image/column/text regions are private.
7. As OWNER/ADMIN/PUBLISHER_ADMIN/EDITOR, test correction and review at `/admin/ai-review`, preview access, drag/Shift-resize, CSRF denial, stale version 409, submit replay, logout denial and OPERATOR/reader denial. Test tenant A vs tenant B pages and images for direct object reference denial.
8. Force Python absent, image detector absent, corrupt hOCR, timeout, Redis unavailable, duplicate RabbitMQ delivery, two simultaneous editors and OCR reprocess. Legacy OCR must continue unaffected and reviewed edits must not be silently overwritten.
9. Verify Reader, publishing, manual clipping, QR, watermark, expiry, media signatures, mobile UI, archive and recovery continue working with feature **off**, then on. Check latency, queue lease, private media memory and restore evidence before requesting rollout approval.
10. For rollback, **disable the private flag** and restart staging PHP workers. Preserve candidates for forensics; do not drop customer DB/media. Do not promote without #2 security remediation and #12 real staging acceptance.

## Verification in GitHub Actions

- Python unittests (OCR + layout + synthetic image/headline/body/caption/multi-column + CLI).
- PHP 8.3 lint for modified PHP modules, reader dispatch and View.
- Static checks for admin role/CSRF/tenant path/revision and no publishing.
- Disposable MariaDB 10.11: migration twice; two-tenant row isolation; optimistic concurrency, reviewed correction preservation, identical reprocess denial.
- Source-only CI **does not** exercise real Platform auth, application PHP-FPM, live tenant ACL, auth cookies, UI interaction, real OCR accuracy or full Cloudways.

## Remaining gaps

- Tesseract structure and relative word-height thresholds are heuristic. **No** trained model for headline, body, advertisement, image type or disconnected multi-column articles; no reliable cross-page continuation.
- OCR image detection classifies texture; ads, maps, artwork and photos may be confused.
- 100+ annotated Marathi/Hindi/English real-edition benchmark, human polygon editing, fine region split/merge, partial review, mobile keyboard/accessibility, user-facing article/clip overlays and model comparison are still outstanding.
- Existing legacy synchronous `Processing` does not stage AI candidates; only RabbitMQ `ProcessingPipeline::pageOcr` is integrated. Off-by-default is intentional.
- No live authenticated Cloudways/Redis/RabbitMQ staging measurements, model GPU deployment, production certification or irreversible Git history cleanup performed.
