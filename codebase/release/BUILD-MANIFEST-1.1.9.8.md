# v1.1.9.8 Build Manifest

Runtime changes:
- `app/Core/View.php`
- `public_html/assets/auth-v1.1.9.8.css`
- `public_html/service-worker.js`

Release/test changes:
- `VERSION`
- `manifest.json`
- `bin/release-check.php`
- `tests/build-guard-v1.1.9.8.php`
- `tests/auth-v1.1.9.8-browser.py`
- v1.1.9.8 release documentation/logs

Protected services are not part of the hotfix overlay:
- `app/Services/QrCode.php`
- `app/Services/ClipService.php`
