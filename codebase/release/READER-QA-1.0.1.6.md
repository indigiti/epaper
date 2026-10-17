# Reader QA — v1.0.1.6

Automated/static checks executed in build environment:

- `node --check reader.js` — PASS
- `node --check service-worker.js` — PASS
- `php -l app/Core/View.php` — PASS
- fit-relative minimum scale guard — PASS
- stable pinch anchor guard — PASS
- pinch stale drag/swipe cleanup guard — PASS
- two-axis wheel/trackpad pan guard — PASS
- exact centering / reachable edge range model — PASS
- resize manual-center preservation guard — PASS
- mobile geometry simulation (390×650, 2400×3400 source) — PASS
- desktop geometry simulation (1280×760, 2400×3400 source) — PASS
- pinch anchor invariance simulation — PASS

Live iOS/Android/desktop device verification remains required after deployment because the build environment cannot interact with the user's production Reader or physical multi-touch hardware.


## v1.1.0 re-audit

Headless Chromium smoke: 12/12 PASS for desktop fit/zoom/pan/top-edge recovery, keyboard seamless navigation, Clip activate/Esc cancel, and mobile fit-relative pinch in/out.
