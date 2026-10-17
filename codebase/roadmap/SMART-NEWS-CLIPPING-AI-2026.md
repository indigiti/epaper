# EP-DI: Smart News Clipping + Unlimited-OCR — roadmap, decisions & pending register

> Last reviewed: 2026-10-17. Planning baseline: main `0d00cb5b144db156d44094af9d39a3ecb20b3674`. **This document is a plan, not proof of implementation.**
> Master live tracker: [#3](https://github.com/indigiti/epaper/issues/3). Security gate: [#2](https://github.com/indigiti/epaper/issues/2), hardening [PR #1](https://github.com/indigiti/epaper/pull/1).

## North star / user journey

Upload multi-page PDF → private render source + browser assets → automatic *article layout* detection → OCR recognition + reading order → editable full-story regions overlaid on Page / Flip / Continuous views → tap story to **Read / Listen / Clip / Edit** → composited clipping & branded share formats. Works for Marathi, Hindi, English, mixed-language and varied newspaper layouts; preserve manual clipping, subscription controls and publisher-specific policies.

**Explicitly NOT the same thing:** OCR text recognition is not story segmentation; a text box at a click point is not a whole article; published article content is not a user's temporary clip.

## Source-inspected starting point (not live certified)

| Existing code | Verified observed behavior | Missing / future |
|---|---|---|
| `app/Services/ProcessingPipeline.php` | jobs: `EDITION_RENDER`, `PAGE_ASSETS`, `PAGE_OCR`, `EDITION_FINALIZE`; runs Tesseract, parses hOCR into `ep_page_ocr`, `ep_ocr_blocks` | selectable Python OCR providers, inference provenance, region candidates, queue resilience tests |
| `app/App.php::articleAt()` | uses boxes containing clicked x/y, returns box text | complete article grouping, region APIs, stable IDs |
| `app/Services/ClipService.php` | normalized rectangular crop, share/expiry policy | multi-region composition and polygon crop support |
| `public_html/assets/reader.js` | Smart Article click, TTS, manual clip rectangle, modal | overlay polygons, selection correction, visual QA on reader modes |
| `app/Services/SemanticNews.php` | published article/editing read models | approval queue; do not publish model guesses |
| MariaDB, RabbitMQ, Supervisor | job database, outbox, retry/recovery | secured Python worker bridge, limits, health/telemetry |

## Architecture / scope choices

1. PHP app remains source of truth. Python as isolated inference worker, preferably hosted separately for NVIDIA GPU needs; no GPU dependency on PHP web requests.
2. Use **official** [Baidu Unlimited-OCR](https://github.com/baidu/Unlimited-OCR) with pinned upstream version as a selectable engine; implement fallback providers (PaddleOCR, current Tesseract). Benchmark first; automatic promotion to default requires evidence, especially Devanagari layout accuracy.
3. Stage order: render → layout and article-region candidates → OCR recognition → grouping, confidence and review → final text/geometry → previews/clickable UI; permit independent retry and fallback of stages.
4. Versioned output contract: tenant_guid, edition_guid, page_guid, source image dimensions, detected region ids, normalized and pixel boxes/polygons, child regions, reading sequence, language, recognized text, confidence (nullable), OCR provider/version, segmentation provider/version, warnings, timings and provenance. Source coordinates must match web-reader zoom/tiles.
5. New tenant-scoped region/review tables; keep AI candidates separate from `ep_clips` and `ep_articles`. No inferred text auto-published, no automatic clip creation. Use authenticated tenant-safe APIs for read/approve/correct.
6. Support articles across multiple columns and pages, with image/caption associations; do not crop unintended advertisements.
7. Preserve existing reader/clip/export/entitlement/7-day policy and minimize new public asset exposure; sanitize OCR output, secure private file access, bind all requests to tenant and permitted users, no arbitrary paths.
8. Feature flag `ai_clipping.enabled`, provider controls, kill switch and migration rollback; web user workflows remain functional if AI is unavailable.

## Workstreams / issue index

| Priority | Issue | Deliverable | Status |
|---|---|---|---|
| P0 | [#4](https://github.com/indigiti/epaper/issues/4) | Functionwise security/reader baseline, test fixtures, visual screenshots | PLANNED |
| P1 | [#5](https://github.com/indigiti/epaper/issues/5) | Python provider interface, Unlimited-OCR/PaddleOCR/Tesseract, hOCR & structured output | PLANNED |
| P1 | [#6](https://github.com/indigiti/epaper/issues/6) | Layout segmentation, grouping, language-aware reading order, disjoint regions | PLANNED |
| P1 | [#7](https://github.com/indigiti/epaper/issues/7) | RabbitMQ integration, lifecycle, idempotency, cancel/retry, health | PLANNED |
| P1 | [#8](https://github.com/indigiti/epaper/issues/8) | Reader article overlays, hit targets, accessible editor, responsive QA | PLANNED |
| P1 | [#9](https://github.com/indigiti/epaper/issues/9) | Multi-region clipping, previews, exports and retention parity | PLANNED |
| P2 | [#10](https://github.com/indigiti/epaper/issues/10) | Publisher admin review, bulk jobs, editorial approval, settings | PLANNED |
| P2 | [#11](https://github.com/indigiti/epaper/issues/11) | 100-page Marathi/Hindi/English annotated benchmark and verified performance | PLANNED |
| P2 | [#12](https://github.com/indigiti/epaper/issues/12) | Security, Cloudways backup/test restore, deployment, production acceptance | BLOCKED (#2) |
| P3 | [#13](https://github.com/indigiti/epaper/issues/13) | Backlog: cross-page linking, duplicate detection, searchable archive, analytics etc | PLANNED |

## Recommended delivery gates

| Gate | Scope | Exit evidence |
|---|---|---|
| 0 — inventory | audit existing flows and model constraints; protect production | baseline review, test-case inventory, security dependence mapped |
| 1 — AI contract | Python worker + pinned vendor model + provider fallback and fixtures | reproducible CPU fallback; GPU benchmark where available; schema and tests |
| 2 — article intelligence | segmentation + OCR alignment + review candidates | benchmark outputs and quality/error distribution |
| 3 — interactive MVP | visual reader overlays and one-click clip with manual correction | real browser screenshots (desktop/mobile), successful clip and regression tests |
| 4 — operator UX | admin review, cost/progress/health, recoverability | roles/tenant checks, error handling and audit logs |
| 5 — final preparation | Cloudways app/DB/media backup and isolated restore; after successful restore, production credential remediation/history purge from #2 | private operator attestations, verified rollback and staging acceptance |
| 6 — production | authorized staged release and measured adoption | production health, release evidence, no regression |

**Important sequence:** User prefers to complete non-privileged project preparation first, leaving Cloudways backup and test restore as the final **pre-production preparation** stage. Nevertheless, per #2, no live credential revocation/rotation, Git history rewrite, production merge/release/deployment, or other irreversible server change before recovery is independently verified. The open security issue can require earlier restrictions on work; follow its blockers.

## Missing-feature prevention / continuous gap register

At each module audit, compare: **planned / code exists / UI wired / unit tests / browser tests / tenant-security tests / verified production**. These are separate statuses, not interchangeable.

| Gap/risk to explicitly check | Owner issue | Status |
|---|---|---|
| Existing Smart Article only returns OCR boxes rather than full story | #4 #6 #8 | OPEN |
| Current clipping model only supports one rectangle | #4 #9 | OPEN |
| No native pinned Unlimited-OCR integration or benchmark | #5 #11 | OPEN |
| OCR JSON/hOCR conversion can lose word coordinates | #5 #7 | OPEN |
| Wrong article grouping when columns continue/nonadjacent | #6 #11 | OPEN |
| Mixed-script Marathi/Hindi/English errors | #5 #11 | OPEN |
| PDF/scan orientation, skew, resolution, rotated content | #6 #11 | OPEN |
| Headlines and photos spanning several columns | #6 #9 | OPEN |
| Long multipart articles spanning multiple pages | #6 #13 | OPEN |
| Advertisements/classifieds accidentally detected as articles | #6 #11 #13 | OPEN |
| Reader coordinate drift on zoom, Deep Zoom, flip/continuous/mobile | #8 #9 | OPEN |
| Selection overlay tapping, keyboard, screen reader, gestures | #8 | OPEN |
| User manually corrects false positives and saves changes | #8 #10 | OPEN |
| Untrusted extracted text injection in web UI or API | #5 #8 #12 | OPEN |
| Duplicate jobs, crashes, GPU timeouts and failed finalization | #7 | OPEN |
| Privacy, entitlement, guest, expiry, audit, share/export parity | #9 #12 | OPEN |
| Storage quotas, clip/AI candidate retention and access controls | #7 #9 #12 | OPEN |
| False publication or SEO indexing of unapproved AI story | #10 #12 | OPEN |
| Cloudways GPU unavailability / GPU memory / external processing costs | #5 #11 #12 | OPEN |
| Security issue #2, historical secrets, staging evidence, recovery | #12 and #2 | BLOCKED |
| Live Cloudways backup and isolated test restore | #12 and #2 | BLOCKED |
| Original pages/media rights and externally hosted OCR data policy | #4 #5 #12 | OPEN |
| Unplanned minor defects discovered by functionwise/visual audit | #13 + new child issue | ALWAYS TRACK |

## Definition of done

No issue is **VERIFIED** without linking representative inputs/outputs, reproducible commands, tests that actually ran, observed results, screenshots where UI changed, and documented outstanding limitations. No release is **READY** until security incident #2 and pre-production recovery gates are resolved. Benchmarks use measured values, not vendor marketing or assumed targets. Treat ≥95% article recall / ≥90% grouping as **candidate targets only**, not results.

## Decision log (2026-10-17)

- Keep existing application stack; add Python rather than replacing PHP.
- Use official Baidu Unlimited-OCR as primary candidate, not only the concept/name.
- Keep manual clip, Tesseract fallback, and independent layout AI.
- Build **Smart News Clipping** as a single upgrade to existing Reader Smart Article / Clip Mode.
- Track every missing minor module and code/UI/backend gap as an issue.
- Preserve security #2 operator sequence, with Cloudways backup/test restore at final preparation but before production-risk operations.
- New requirement or discovery: update this document **and** add/link a GitHub issue; note pre/post behavior and verification.

## Verification / future updates

At every completed PR: edit this plan (status, issue, commit, evidence), update related issues, run a pre/post audit, and preserve unfinished work. If chats interrupt, resume from the issue index and last verified commit rather than relying on conversational claims.
