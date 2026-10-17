# EP-DI v2.0.0-beta.2 — Reader OS + Library + Encrypted PWA Offline Vault

## Purpose

Beta.2 completes the first consumer-side v2 milestone on top of the verified beta.1.2 Publisher/Processing foundation. It introduces the Reader OS and Library experience and replaces the legacy plaintext offline-media compatibility path with a device-bound encrypted Offline Vault for eligible recent editions.

The design goal is deliberately split:

- public/read surfaces remain fast, server-rendered and progressively enhanced;
- protected offline newspaper media is never persisted as plaintext in Cache Storage;
- MariaDB remains durable canonical truth while the browser stores only encrypted page payloads plus the device-held non-exportable private key.

## Reader OS

`/account` is now the Reader OS home and includes:

- Continue Reading;
- Bookmarks, Saved Articles, Clippings, Offline Editions and Unread Alerts summaries;
- effective Reader capabilities;
- recent Reading History;
- install-PWA action when supported.

Account navigation now includes dedicated `Library` and `Devices` surfaces while retaining existing saved articles, bookmarks, clips, history, notifications and subscription views.

## Library

`/account/library` provides a consolidated reader library with item-specific sections for saved articles, bookmarks, clips, history, offline editions and notifications.

The offline account view now identifies:

- encrypted/offline state;
- device label;
- page count and downloaded pages;
- license expiry;
- account-side remove action.

## Encrypted Offline Vault

### Eligibility

The default policy is a rolling **7-day** edition window and up to **7 offline editions per device**. Both are Publisher Control Reader settings:

- `reader.offline_window_days`
- `reader.offline_edition_limit`

Existing entitlement values may further constrain those limits.

The expiry is based on the edition date plus the allowed rolling window. A six-day-old edition therefore has roughly one day of remaining offline eligibility; downloading it does not restart a fresh seven-day clock.

### Device identity

The browser generates an ECDH P-256 key pair using Web Crypto:

- the private key is non-exportable;
- the public SPKI key is registered with EP-DI;
- the device is bound to the signed-in Reader identity;
- a revoked device cannot silently reactivate itself.

### License/key flow

For each eligible edition download:

1. EP-DI creates a random 256-bit content-encryption key (CEK).
2. The CEK is wrapped to the device using **ECDH P-256 + HKDF-SHA256 + AES-256-GCM**.
3. The server stores only a server-sealed copy of the CEK needed to encrypt requested pages while the online provisioning license remains valid.
4. Individual newspaper page bytes are encrypted using AES-256-GCM before being returned to the browser.
5. The browser stores ciphertext, IV/tag and license metadata in IndexedDB.
6. The browser unwraps the CEK and decrypts a page only when the user reads it, creating an in-memory Blob URL that is discarded/revoked as pages change.

No original PDF is placed in the PWA offline store.

### Offline reader

The installable PWA includes:

- `/offline-library.html`
- `/offline-reader.html`
- `/offline.html`

These offline-only shells are explicitly `noindex,nofollow`. The offline reader supports previous/next page navigation and keyboard arrows without requiring a network request once an edition has been fully downloaded.

### Storage and lifecycle

The Offline Vault uses IndexedDB and requests persistent storage when browser support is available. Reader OS exposes storage usage/quota information where available.

Lifecycle controls include:

- hard license expiry;
- online expiry/revalidation;
- edition removal;
- device revocation;
- browser-vault wipe on logout;
- account clear-offline action;
- expired-local-license cleanup.

A fully disconnected browser cannot receive immediate server-side revocation. Hard expiry and revalidation-on-connect remain the security boundary. This is encrypted-at-rest browser DRM resistance, **not hardware-backed native DRM**.

## Service Worker hardening

The beta.2 service worker removes the old protected-reader-media Cache Storage path.

- `/media/*` is network/no-store.
- `/api/v2/offline/*` is network/no-store.
- protected page plaintext is never written to Cache Storage.
- activation deletes historical `epaper-reader-media-*` and legacy offline caches.
- public HTML remains network-first with offline fallback.
- `/account` and `/admin` remain private/non-persistent.
- immutable app shell/offline-reader assets are cached normally.

The public `.htaccess` now denies historical `.bak`, `.old` and `.orig` deployment artifacts. Such backup files are also removed from the full beta.2 package.

## Database migration

**Database migration is required.**

Canonical migration:

`database/upgrade-v2.0.0-beta.2.sql`

It adds:

- `ep_offline_devices`
- `ep_offline_licenses`
- device/license/expiry/progress columns on `ep_offline_items`
- device-aware offline-edition uniqueness
- offline expiry/license lookup indexes

The secured `/admin/system/upgrade` Schema Doctor understands the beta.2 schema and is the preferred migration path.

## Deployment from v2.0.0-beta.1.2

1. Take an on-demand Cloudways application + database backup.
2. Extract `EP-DI-v2.0.0-beta.2-READER-OS-OFFLINE-VAULT-HOTFIX.zip` over the application root.
3. Open `/admin/system/upgrade`.
4. Confirm the Offline Vault tables/columns/indexes appear as `FIXABLE`, then run the state-aware repair once.
5. Confirm the Upgrade Center reports the database aligned.
6. Hard-refresh once so the `epdi-v2.0.0-beta.2` service worker activates and removes legacy Reader media caches.
7. Verify `/account`, `/account/library`, `/account/offline` and `/account/devices`.
8. Open a published edition inside the rolling window and choose **Save Offline**.
9. Wait for the encrypted download to complete, disconnect the network, open `/offline-library.html`, and read multiple pages.
10. Reconnect, log out, and confirm the local browser vault is removed.

No Supervisor worker restart is required by beta.2.

## Recommended first verification

Use a non-sensitive recent edition and one test Reader account:

- install/open the PWA over HTTPS;
- Save Offline;
- confirm the browser receives `application/octet-stream` ciphertext from the offline page API rather than WebP plaintext;
- confirm protected pages are absent from Cache Storage;
- confirm ciphertext records exist in IndexedDB;
- switch offline and read at least first/middle/last pages;
- reload the PWA while offline;
- confirm expiry metadata is shown;
- remove the edition and confirm it disappears locally;
- download again, revoke the current device from `/account/devices`, reconnect and verify the license is rejected;
- test logout wipe.

## Security limitations

The browser must eventually decrypt content to display it. A sufficiently privileged user controlling the browser can capture displayed pixels or inspect runtime memory. Beta.2 therefore provides device-bound encrypted-at-rest offline storage, license/expiry controls and extraction resistance; it does not claim hardware-grade DRM.

## Release gates

Beta.2 is gated by:

- v2.0.0-beta.2 build guard;
- Offline Vault static/security guard;
- ECDH/HKDF/AES-GCM interoperability check;
- Schema Doctor guard;
- beta.1.2 Processing Control regression guard;
- beta.1.1 Editions Operations regression guard;
- Supervisor runtime guard;
- Audit UUID safety guard;
- tenant SQL isolation guard;
- Reader parity/regression guard;
- cumulative v2 architecture guard;
- PHP and JavaScript syntax validation;
- service-worker/build-version alignment;
- ZIP integrity and SHA-256 verification.
