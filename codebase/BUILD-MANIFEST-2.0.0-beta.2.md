# EP-DI v2.0.0-beta.2 Build Manifest

- Version: `2.0.0-beta.2`
- Base / required version: `2.0.0-beta.1.2`
- Release type: cumulative beta feature release
- Feature: Reader OS + Library + Encrypted PWA Offline Vault
- Database migration: **required** — `database/upgrade-v2.0.0-beta.2.sql`
- Upgrade path: `/admin/system/upgrade`
- Worker restart: **not required**
- Service-worker cache: `epdi-v2.0.0-beta.2`
- Default offline policy: rolling 7 days / 7 editions per device, publisher/entitlement constrained

## New runtime files

- `app/Services/OfflineVault.php`
- `database/upgrade-v2.0.0-beta.2.sql`
- `public_html/assets/offline-vault.js`
- `public_html/assets/offline-vault.css`
- `public_html/assets/account-beta.css`
- `public_html/offline-library.html`
- `public_html/offline-reader.html`
- `tests/offline-vault-static.php`
- `tests/build-guard-v2.0.0-beta.2.php`

## Updated runtime files

- `app/App.php`
- `app/Core/View.php`
- `app/Services/SchemaDoctor.php`
- `app/Services/SettingsCatalog.php`
- `database/schema.sql`
- `public_html/.htaccess`
- `public_html/assets/reader.js`
- `public_html/manifest.webmanifest`
- `public_html/offline.html`
- `public_html/service-worker.js`
- `bin/release-check.php`
- `VERSION`

## Functional guarantees

- legacy `/api/v1/offline` no longer returns plaintext offline media URLs;
- protected media and Offline Vault ciphertext endpoints are `no-store` in the service worker;
- browser persistent offline records contain ciphertext, not original page WebP/PDF payloads;
- device private key is generated non-exportable with Web Crypto;
- Offline Vault key wrapping uses ECDH P-256 + HKDF-SHA256 + AES-256-GCM;
- page payloads use AES-256-GCM authenticated encryption;
- rolling eligibility/expiry is edition-date based;
- device/edition licenses are revocable and hard-expiring;
- Reader OS, Library and Devices are account-scoped and private;
- public offline-only shells are `noindex,nofollow`;
- historical public `.bak/.old/.orig` artifacts are denied by `.htaccess` and omitted from the full build;
- beta.1.2 Publisher Console, Editions Operations and Processing Recovery behavior remains intact.

## Release gates

- beta.2 build guard
- Offline Vault static/security guard
- crypto interoperability test
- Schema Doctor guard
- Processing Control regression guard
- Editions Operations regression guard
- Supervisor runtime guard
- Audit UUID safety guard
- tenant SQL isolation guard
- Reader parity guard
- cumulative v2 guard
- PHP / JavaScript syntax validation
- service-worker alignment
- ZIP integrity / SHA-256 verification
