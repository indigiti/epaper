# EP-DI v2.0.1.6 — Local Mobile Reader Authentication

EP-DI authenticates tenant-local readers with verified mobile numbers while keeping Publisher/Admin identities on Digiti Platform. Local mobile runtime identities always use `memberships=[]`, so they cannot satisfy Publisher Control RBAC.

## Access enforcement

Every authenticated request is revalidated after tenant resolution. For `LOCAL_MOBILE`, EP-DI requires all three records to remain ACTIVE:

- `ep_local_mobile_accounts.status`
- `ep_subscribers.status`
- `ep_identities.status`

A missing record, inactive state, or local subscriber-store exception invalidates the local session. This fail-closed rule is intentionally scoped to local mobile users so a local database fault does not unnecessarily lock out Platform publisher/admin identities.

## Verification channels

Configured channels can include `sms`, `whatsapp`, and `sna`. Browser `/mobile-login` supports SMS/WhatsApp. SNA is Android-only because its returned URL must be invoked from the handset's cellular network.

The Android client invokes the SNA URL over `TRANSPORT_CELLULAR`, then performs the server Verification Check. A transient Twilio `60519` result is returned as `MOBILE_AUTH_PENDING`; Android retries it for a bounded period and then presents SMS/WhatsApp fallback actions.

## Challenge lifecycle

Challenges remain valid for 10 minutes and use tenant, phone and IP rate controls. Raw IP addresses are not stored in challenge history; a keyed HMAC is stored instead. The recurring scheduler now:

- marks expired PENDING challenges as `EXPIRED`;
- deletes `APPROVED`, `EXPIRED`, and `LOCKED` rows older than `auth.mobile.challenge_retention_days` (default 14 days).

## Phone normalization

The default country code remains configurable. A number such as `9876543210` with default `+91` normalizes to `+919876543210`; `919876543210` is also accepted without adding a second `91` prefix.

## Provider diagnostics

Provider transport/config/HTTP details are sent to Error Center. Reader-facing API responses use generic verification errors, preventing provider internals from leaking into the app or browser.

## Database

v2.0.1.6 adds no schema migration. Installations that have not yet applied mobile authentication must run:

```bash
mysql -u YOUR_DB_USER -p YOUR_DB_NAME < database/upgrade-v2.0.1.5-mobile-auth.sql
```

## Configuration

Merge the mobile block into live config rather than replacing the live file. New optional setting:

```php
'challenge_retention_days' => 14,
```

Keep SNA disabled until the verification provider confirms coverage for your intended carriers. SMS remains the safest initial production channel for India.
