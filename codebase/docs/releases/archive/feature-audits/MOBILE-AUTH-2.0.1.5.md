# EP-DI v2.0.1.5 — Local Mobile Reader Authentication

## What this release adds

EP-DI can now create and authenticate **tenant-local reader accounts** from a verified mobile number without asking Digiti Platform to verify reader credentials. Publisher/admin authentication is unchanged and remains Digiti Platform controlled.

The Android project adds an in-app WebView Reader plus a native fast-signup screen. It supports provider-configured SMS, WhatsApp, and Silent Network Authentication (`sna`). New verified numbers create a local reader automatically; returning numbers resolve to the same local `user_guid`.

## Security boundary

Local mobile identities are saved with `identity_source=LOCAL_MOBILE` and their runtime identity has `memberships=[]`. Therefore they cannot satisfy `Auth::requireAdmin()`. Existing publisher/admin identities continue to have `identity_source=PLATFORM`.

Subscriber disable is now enforced during authenticated access. If `ep_subscribers.status` is not `ACTIVE`, the local session is destroyed and protected reader/account routes are denied.

OTP/challenge protection includes a ten-minute challenge lifetime, per-phone and per-IP start limits, per-challenge attempt limits, a request-IP HMAC (not the raw IP) in challenge history, session binding of public challenge IDs, CSRF validation, and no-store responses.

## Database migration

Run:

```bash
mysql -u YOUR_DB_USER -p YOUR_DB_NAME < database/upgrade-v2.0.1.5-mobile-auth.sql
```

The migration adds `ep_identities.identity_source`, `ep_local_mobile_accounts`, and `ep_mobile_auth_challenges`. It does not alter existing reader `user_guid` references.

## Configuration

Merge the new `auth.mobile` block from `config/config.example.php` into the live `config/config.php`. Do not replace the live config file.

Example:

```php
'auth' => [
  // retain the existing admin/infrastructure role arrays
  'mobile' => [
    'enabled' => true,
    'provider' => 'twilio_verify',
    'default_country_code' => '+91',
    'channels' => ['sms'],
    'twilio' => [
      'service_sid' => 'VAxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
      'api_key_sid' => 'SKxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
      'api_key_secret' => 'SECRET_FROM_ENV_OR_SECURE_CONFIG',
      'account_sid' => '',
      'auth_token' => '',
    ],
  ],
],
```

Prefer a restricted Twilio API Key SID/secret rather than the Account Auth Token. Keep credentials outside source control and preserve them during upgrades.

### India channel note

For Indian readers, start production acceptance with **SMS**. Enable WhatsApp only after confirming the verification provider's current India delivery behavior and approved WhatsApp sender/template setup. Enable `sna` only after your provider confirms network coverage for the target Indian carriers; the Android app will otherwise fall back to an offered WhatsApp/SMS route.

## New endpoints

- `GET /api/v1/auth/mobile/bootstrap` — creates/returns CSRF/session state and configured channels.
- `POST /api/v1/auth/mobile/start` — normalizes the E.164 phone number and starts provider verification.
- `POST /api/v1/auth/mobile/verify` — checks the code/network verification, creates/finds the local reader, and establishes the PHP session.
- `GET|POST /mobile-login` — browser fallback for SMS/WhatsApp mobile login.

## Deployment order

1. Back up application files and MariaDB.
2. Run `database/upgrade-v2.0.1.5-mobile-auth.sql`.
3. Deploy the changed v2.0.1.5 server files while preserving live config/secrets/storage.
4. Merge `auth.mobile` configuration and keep `enabled=false` until credentials are present.
5. Reload PHP/OPcache, then test `/api/v1/auth/mobile/bootstrap` and browser `/mobile-login` with a test tenant/number.
6. Set `enabled=true`; test new-reader creation, repeat login, subscriber disable, and an admin login to confirm the boundaries.
7. Build/install the Android project with the official Android SDK and test in-app WebView session transfer plus each enabled verification channel.

## Rollback

Set `auth.mobile.enabled=false` first. Restore v2.0.1.4 application files if required. The added tables/column are backward-compatible and may be left in place during rollback; do not drop them until you have confirmed no local-reader records are needed.
