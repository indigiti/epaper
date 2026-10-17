# v1.1.9.7 Build Manifest

Runtime files changed by this hotfix:

- `app/Core/View.php`
- `public_html/assets/auth-v1.1.9.7.css`
- `public_html/service-worker.js`

No database migration. No worker restart. No production config/secrets included. `app/Services/QrCode.php` and `app/Services/ClipService.php` are protected and not packaged.
