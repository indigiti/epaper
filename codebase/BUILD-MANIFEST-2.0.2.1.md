# EP-DI v2.0.2.1 — Build Manifest

Base: **EP-DI v2.0.2.0 — Realistic Newspaper Reader**

Release type: Reader UX patch. No database migration. No native Android binary change.

## Primary changes

- Toolbar back-edition dropdown placed between Download and Share.
- Responsive trigger: full date → short date → calendar icon only.
- Exact publication + city + language variant filtering.
- Admin Reader controls for Dropdown / Strip / Off.
- Cached back-edition history list, default 365 editions and runtime bounded to 30–1000.
- Existing date strip retained as an optional compatibility mode.
- Historical regression guards made release-version tolerant while preserving their original feature checks.

## Runtime files

- `app/App.php`
- `app/Core/View.php`
- `app/Services/PublicReadCache.php`
- `app/Services/SettingsCatalog.php`
- `public_html/assets/reader.js`
- `public_html/assets/reader.css`
- `public_html/service-worker.js`

## Validation

Run:

```bash
php bin/release-check.php
sha256sum -c SHA256SUMS
```
