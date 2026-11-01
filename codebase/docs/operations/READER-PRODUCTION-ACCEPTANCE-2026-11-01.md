# EP-DI Reader: production acceptance matrix

This is an **evidence collection checklist**, not a passing test report. Automated mobile emulation is not a substitute for Safari/Android devices. No production deployment is authorized by this document.

## Preflight and release security (M1)
- [ ] Cloudways DB, application config, storage PDFs/images, clips and queues independently backed up AND successfully restored in isolation
- [ ] DB, Redis, RabbitMQ, application signing key and Platform/API service credentials rotated and old tokens revoked; no values in Git/logs
- [ ] Staff/deploy key access audit complete; unauthorized sessions revoked; 2FA and least privilege reviewed
- [ ] Direct `/storage/`, `/config/`, `/vendor/`, `.env` URLs return 403/404 through both CDN and origin
- [ ] No secrets, PDFs, image outputs or clips in current Git refs; history rewrite / cache follow-up attested
- [ ] Valid published signed-media reads succeed; forged, expired, wrong-tenant and unpublished reads fail
- [ ] Offline encrypted licence still works after app key transition, or is deliberately revoked with user messaging

## Browser and assistive-technology matrix (M2/M3)

| Browser / device | Keyboard | Flip/Page/Continuous | Clip/Article/OCR | Offline/Quality | Zoom/Pinch | Status/evidence |
|---|---|---|---|---|---|---|
| Windows Chrome 1440×900 | required | required | required | required | required | **NOT TESTED** |
| Windows/Ubuntu Firefox 1280×800 | required | required | required | required | required | **NOT TESTED** |
| macOS Safari 1440×900 | required | required | required | required | required | **NOT TESTED** |
| iPhone Safari 390×844 physical | VoiceOver | required | required | required | required | **NOT TESTED** |
| iPhone SE/320px physical | VoiceOver | required | required | required | required | **NOT TESTED** |
| Android Chrome 360×800 physical | TalkBack | required | required | required | required | **NOT TESTED** |
| iPad 768×1024 physical | keyboard | required | required | required | required | **NOT TESTED** |

## Concrete regressions
1. Type *a*, *c*, *f*, arrow keys, and spaces into clipping title and page-jump fields. Reader shortcuts must not steal those keystrokes.
2. Change Flip/Page/Continuous; scroll through a tall image on a narrow viewport. Page number, URL and persisted reading position follow the center-visible page.
3. Rapidly click Next/Previous ten times under Slow 3G throttling. Latest requested page must be displayed, with browser Back/Forward preserving order.
4. In Low Data, initiate swipes, zoom and page moves; image/tile waterfall must show no speculative adjacent high-resolution fetches. Restore Auto and High Quality.
5. Drag a clipping partly across a page edge, resize all four handles, save, and confirm server bounds validation and correct output.
6. Open the full-page OCR text reader; verify screen-reader order, paragraph wrapping, copy/select, Escape, focus restoration and empty-OCR messaging.
7. Validate fullscreen and unsupported fullscreen fallback; no trapped overlay or permanently hidden controls.
8. Test the Save Offline button from desktop/mobile, encrypted cache persistence, expired licences, logout wipe and device revocation.
9. Verify text, controls, overflow menu and page navigation at 200% zoom; target sizes at least 44 CSS px on mobile; no document-level horizontal scroll.
10. Check contrast, keyboard traps, labeled dialogs, ARIA states and page-image alternatives with axe + manual screen reader.

## Performance/release parity (M4)
- Automated staging run: `EPDI_READER_TEST_URL=https://STAGING/edition/SLUG/page/1 node codebase/tests/reader-live-browser.mjs` with screenshots, axe findings, media 403/404 negative tests and LCP/CLS thresholds.
- Compare sanitized staging/production runtime signatures with `php codebase/bin/deployment-parity.php --webroot=/absolute/path/to/public_html --strict` and `--compare=staging-parity.json`.
- Record p50/p95 page API latency, p95 image TTFB, LCP ≤ 4 s on test environment, CLS ≤ 0.1, total transferred bytes per mode and concurrent user test at 20/50/100 sessions (do not invent figures).
- Record backup and restore completion, rollback target commit and sign-off. Require separate signatures from engineering, security and Cloudways operator.

**Release decision: BLOCKED until each required row contains actual reproducible evidence.**
