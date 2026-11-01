# EP-DI AI clipping — OCR contract foundation

**Status: isolated, opt-in source prototype; not connected to production processing.** First incremental implementation toward existing issue #5 / master roadmap #3. The referenced separate ChatGPT conversation cannot be reliably read through this GitHub connector, so this is **not claimed to match that conversation's exact scope**.

## Present functionality

- Pure standard-library Tesseract hOCR parser to versioned `epdi.ocr.v1` JSON.
- Page and tenant IDs (supplied by trusted caller), pixel and normalized word geometry, deterministic word IDs, optional hOCR confidence, language metadata, rejected-word counts, and explicit `REVIEW_REQUIRED` status.
- Bounded 16 MiB input, 50,000 words, 256 HTML nesting levels, UUIDs and page dimensions. Invalid boxes rejected, never silently clamped.
- CLI reads hOCR only from stdin; no database, network, model, or stored file access. Run exclusively from an authenticated, tenant-scoped trusted worker.

## Local validation

```bash
cd codebase
python3 -m unittest discover -s tests -p 'test_ai_hocr.py' -v
python3 bin/ai-ocr-normalize.py --tenant-guid=018f5e38-1f04-7cc3-8000-000000000011 --page-guid=018f5e38-1f04-7cc3-8000-000000000022 --width=1000 --height=2000 --languages=mar+hin+eng < sample.hocr
```

## Not yet implemented

The live PHP OCR worker remains unchanged. No switch to Python, no PaddleOCR / Unlimited-OCR inference, no document-region grouping, auto-clipping, article approval UI, DB migration or publication, RabbitMQ integration, deployment or live performance certification.

## Safe next integration steps

1. Compare representative Marathi/Hindi/English hOCR fixtures and source image page transforms against existing `ep_page_ocr` and `ep_ocr_blocks`; extend normalization contract for layout regions.
2. Add version-pinned, separately executed OCR inference adapters, keeping Tesseract as fallback. Verify third-party dependencies, GPU requirements and licenses.
3. Add tenant-scoped article *candidates* and review/approval workflow; preserve original manual clipping, entitlements, watermarks, expiry and share features.
4. Test both `ProcessingPipeline::pageOcr()` (RabbitMQ) and legacy synchronous `Processing` under isolated MariaDB/Redis staging and revoked-access/tenant-isolation cases.
5. Keep Cloudways untouched until issues #2 and #12 recovery/security and acceptance gates complete.

## OCR text region preview (incremental issue #6)

The conservative layout proposal implementation takes `epdi.ocr.v1` JSON and returns `epdi.layout.v1` JSON, **with no direct changes to the live PHP worker**:

```bash
# Convert tenant-authorized hOCR stdin to normalized word boxes:
python3 bin/ai-ocr-normalize.py --tenant-guid=<tenant-uuid> --page-guid=<page-uuid> --width=1000 --height=1400 < page.hocr > page.ocr.json
# Preview OCR-derived area/paragraph/line regions, without saving or publishing:
python3 bin/ai-layout-preview.py < page.ocr.json > page.layout.json
# Run both sets of synthetic contract tests:
python3 -m unittest discover -s tests -p 'test_ai_*.py' -v
```

- hOCR `ocr_carea`, `ocr_par` and `ocr_line` ancestry produces grouping hints; all `regions[].verified_article` fields are `false`.
- The output contains pixel/normalized coordinates, region text/word IDs, actual OCR word confidence averages when supplied, stable region IDs, and column-first proposed region order.
- Pages with spanning areas **revert to source order** with an explicit unresolved strategy. In other layouts, column grouping is only a heuristic. No cross-column story association is inferred.
- Revalidate every OCR word UUID against the page UUID, text and pixel rectangle before grouping; mismatches and duplicate identifiers are rejected. Validate page and tenant IDs, geometry, confidence, word counts and region caps.
- The CLI accepts bounded JSON through stdin; it cannot load files from a tenant path and does not contact any external model.
- **Do not render these boxes as tenant-visible public clickable articles yet.** No protected-media access policy, editorial approval/persistence, candidate workflow, or operational article detection benchmark has been implemented.
- Existing PHP `ep_page_ocr`, `ep_ocr_blocks`, `articleAt()` and manual ClipService remain unchanged.

### Acceptance evidence still outstanding

These small synthetic tests establish contract behavior only. They are **not** OCR precision/recall tests and do not certify Marathi, Hindi, English, newspaper-image angle robustness, article boundaries, mobile overlay geometry, cross-tenant authorization, production stability, model/GPU readiness, nor Cloudways deployment. Those remain the acceptance gates on issues #6 through #12.

## Stacked phase 2: headline/image candidates, editor, RabbitMQ

Draft [PR #29](https://github.com/indigiti/epaper/pull/29) adds `epdi.segmentation.v1` (headline/body/caption suggestions, OpenCV image textures, column-first story links), optional post-`PAGE_OCR` PHP Python staging bridge and an editor-only /admin/ai-review workspace. The feature is **off by default** and never publishes articles/clips automatically.

- Optional image detector dependencies: `python3 -m pip install -r ai_processing/requirements-image.txt` (install in a private isolated staging worker venv only).
- Staging-only migration: `database/upgrade-ai-page-candidates-20261018.sql`; never apply to a live DB without documented backup and restore acceptance.
- Set `ai_clipping.enabled=true` in a **private staging config** only after migration, Python package audit and tenant isolation acceptance; `ai_clipping.python_binary` is the absolute interpreter executable used by the RabbitMQ worker.
- Editorial labels, bbox, text, manual story group and order are revisions stored separately as `editor_json`; candidate auto-reprocessing never overwrites a previous review. `REVIEWED` is not `PUBLISHED`.
- Staging rollout, browser/privacy acceptance and rollback instructions: [AI-EDITORIAL-STAGING-20261018.md](../docs/AI-EDITORIAL-STAGING-20261018.md). Existing Reader/manual ClipService remain untouched.
- Source and disposable MariaDB CI are **not real newspaper accuracy or live Cloudways acceptance**. Hindi/Marathi/English OCR can be preserved through the text contract but quality has not been benchmarked.
