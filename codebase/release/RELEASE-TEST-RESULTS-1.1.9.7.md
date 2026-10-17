# ePaper SaaS v1.1.9.7 — Release Test Results

State: **TESTED** (live Cloudways verification pending).

- PHP lint: PASS.
- v1.1.9.7 cumulative build guard: PASS.
- Settings POST normalization regression: PASS.
- Chromium content-fit auth regression: PASS at 2560×1440, 2048×1202, 1920×1080, 1600×900, 1440×900, 1366×768, 1280×720, 1024×768, 768×1024, 430×932, 390×844, 360×800, 320×720.
- Browser zoom stress: PASS at 67%, 80%, 100%, 125%, 150%.
- Hostile legacy `.auth-panel`/`.auth-form` 100vh CSS injection: PASS; v1.1.9.7 markup contains neither legacy class.
- Card desktop geometry: 440px wide × ~515px high in the representative test form; no portrait stretch.
- Mobile form/card remains fully inside viewport and uses fluid width.
- Service Worker JavaScript syntax: PASS.
- v1.1.4.1 Source Page QR exact file hashes retained: PASS.
- Digiti Platform UI exposure scan: PASS; same-domain subscription flow retained.
- Reader Redis/prefetch and guest clipping build guards: PASS.
