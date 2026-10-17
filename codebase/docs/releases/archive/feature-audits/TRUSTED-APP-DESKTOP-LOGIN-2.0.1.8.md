# Trusted App & Desktop Login — v2.0.1.8

## Trust boundary
Only a verified `LOCAL_MOBILE` identity running the EP-DI Android client can register a trusted app. Temporary `LOCAL_DEVICE` readers cannot approve desktop access. Trusted-device registration requires the existing verified mobile session plus a random app-generated device credential.

The Android app stores:
- installation UUID in app-private storage
- random trusted-device secret encrypted with an Android Keystore-backed AES-GCM key
- trusted device GUID returned by EP-DI
- FCM registration token when Firebase is configured

EP-DI stores the installation identifier and device secret only as server HMACs. The FCM token is encrypted at rest with AES-256-GCM using key material derived from `app.key`.

## Desktop flow
1. Desktop reader enters a mobile number at `/desktop-login`.
2. EP-DI resolves the tenant-local mobile account and active trusted Android app.
3. EP-DI creates a three-minute browser-bound challenge and six-digit display code.
4. EP-DI attempts an FCM HTTP v1 data message to the registered app.
5. The Android app displays an OS notification and/or foreground Approve/Deny dialog.
6. The app authenticates its decision with the trusted-device GUID + random device secret.
7. Desktop polling sees `APPROVED`, creates a `DESKTOP_WEB` PHP session and marks the challenge `CONSUMED`.
8. Any older active desktop session for the same reader is marked `REPLACED`.

## Push is transport, not authentication
The FCM payload contains only the challenge identifier, display code and browser summary. Approve/Deny is accepted only after EP-DI verifies the trusted-device credential. If FCM is unavailable, the app can retrieve the pending challenge directly from EP-DI when opened.

## Firebase server setup
Create a Firebase project for application id `in.digiti.epdi`, enable Cloud Messaging and place the service-account JSON outside the web root. Set its absolute path in `auth.desktop.firebase.service_account_file`. The backend mints short-lived OAuth 2.0 access tokens and sends through the FCM HTTP v1 API.

## Android build setup
Supply the four `EPDI_FIREBASE_*` values to Gradle. They are compiled into `BuildConfig` and used to initialize Firebase programmatically. The Firebase service-account private key is **never** embedded in the Android application.

## Session policy
EP-DI enforces one active `MOBILE_APP` reader session and one active `DESKTOP_WEB` reader session per local mobile identity. A newly established session of the same kind marks the older session `REPLACED`. Registering another verified mobile app also replaces the previous trusted mobile authenticator.

The six-digit desktop value is a human matching code, not an OTP credential. The authoritative approval is the server-verified trusted-device GUID + secret.

Desktop browsers do not probe the operating system to discover an installed app. EP-DI determines app availability from the tenant-local trusted-device registry.

## Limits of source verification
The release source and static guards verify the FCM HTTP v1 integration, Android notification service, in-app fallback and session/challenge state machine. APK/AAB compilation, FCM delivery, and physical-device approval still require Android CI/device testing with the deployment Firebase project.
