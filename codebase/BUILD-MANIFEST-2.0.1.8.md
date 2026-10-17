# EP-DI v2.0.1.8 — Build Manifest

## Release scope
Trusted Android app registration and passwordless desktop reader login with app Approve/Deny, a matching six-digit display code, one active mobile-app session and one active desktop-web session per verified local reader.

## New runtime services
- `app/Services/TrustedAppAuth.php` — trusted Android device registry and revocable device credentials.
- `app/Services/DesktopAuth.php` — desktop login challenge lifecycle and app decision flow.
- `app/Services/ReaderSession.php` — one-session-per-kind enforcement and server-side revocation.
- `app/Services/FcmPush.php` — Firebase Cloud Messaging HTTP v1 delivery using a server-side service account.

## New database objects
- `ep_reader_devices`
- `ep_reader_sessions`
- `ep_desktop_login_challenges`

Apply `database/upgrade-v2.0.1.8-trusted-app-desktop-login.sql` once when upgrading from v2.0.1.7.

## Android additions
- Firebase Messaging dependency via Firebase Android BoM 34.19.0.
- Programmatic Firebase initialization from build environment variables.
- FCM background service and Android notification channel.
- In-app desktop login approval dialog.
- Trusted-device secret generated locally with `SecureRandom` and protected at rest using an Android Keystore-backed AES-GCM key; raw secret is never stored by EP-DI.
- `POST_NOTIFICATIONS` permission for Android 13+.

## Session policy
A verified `LOCAL_MOBILE` reader may hold at most:
- 1 active `MOBILE_APP` reader session.
- 1 active `DESKTOP_WEB` reader session.

Creating a new session of the same kind marks the previous one `REPLACED`.

`LOCAL_DEVICE` temporary readers are not allowed to become trusted desktop authenticators.
