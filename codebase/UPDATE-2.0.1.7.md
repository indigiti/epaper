# EP-DI v2.0.1.7 — Flexible Reader Identity

## What changed
- Publisher Admin can independently enable/disable SMS, WhatsApp and SNA under **Settings → Reader access**.
- Effective methods are always bounded by server-side provider capability; Admin cannot enable a provider channel that infrastructure has not permitted.
- When every effective verification method is disabled and **Temporary device account fallback** is ON, the Android app automatically creates/reuses an ePaper-local `LOCAL_DEVICE` identity.
- Android generates a random installation UUID in app-private preferences. EP-DI stores only an HMAC; no IMEI, SIM identifier, serial number or MAC address is collected.
- Temporary identities have rolling inactivity expiry, per-IP creation limits, Admin subscriber disable/enable support, scheduler expiry/pruning and fail-closed session revalidation.
- On later successful mobile verification, bookmarks, saved articles, clips, reading history, reader preferences, notifications, analytics attribution and offline ownership are merged into the permanent `LOCAL_MOBILE` account where possible.

## Database
Run `database/upgrade-v2.0.1.7-flexible-reader-identity.sql` once on an existing v2.0.1.6 installation.

## Default policy
- SMS: ON (only if allowed by server config)
- WhatsApp: OFF
- SNA: OFF
- Temporary device account fallback: ON
- Inactivity expiry: 30 days
- New device-account limit: 5/IP/day
- Merge after verification: ON
