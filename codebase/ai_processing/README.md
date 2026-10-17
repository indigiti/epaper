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
