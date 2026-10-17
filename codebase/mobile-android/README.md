# EP-DI Android — v2.0.2.0 Realistic Newspaper Reader

The Android app remains the in-app EP-DI Reader and mobile-number verification client, and now also acts as a trusted authenticator for desktop reader login.

## Reader + trusted-app flow

1. Verify the mobile number using the enabled SNA / WhatsApp / SMS method.
2. EP-DI creates/finds the tenant-local `LOCAL_MOBILE` reader account.
3. The app generates a random trusted-device secret and registers this installation with EP-DI.
4. When that mobile number is entered at `/desktop-login`, EP-DI generates a matching six-digit code and approval challenge.
5. FCM can wake the app with a high-priority data message. The app displays a login notification and an Approve/Deny dialog.
6. The app sends its decision using its trusted-device credential. The notification payload itself cannot authenticate a login.
7. A successful approval creates the single allowed `DESKTOP_WEB` session. A new desktop login replaces the older desktop session; a newly verified mobile app replaces the prior `MOBILE_APP` session/device.

If FCM is not configured, opening the app still checks EP-DI for a pending desktop login request.

## Android / Firebase build configuration

This project uses Android API 36, JDK 17, AGP 8.13.2, Gradle 8.13 and Firebase Android BoM 34.19.0 with `firebase-messaging`.

Supply these environment variables when building:

```text
EPDI_FIREBASE_APPLICATION_ID
EPDI_FIREBASE_API_KEY
EPDI_FIREBASE_PROJECT_ID
EPDI_FIREBASE_SENDER_ID
```

These are the Android Firebase project identifiers. The Firebase service-account JSON used to send FCM messages belongs only on the EP-DI server and must never be packaged in the APK/AAB.

The CI workflow also supports optional Play upload-signing secrets (`EPDI_UPLOAD_*`).

## Permissions

The app declares Internet/network permissions and `POST_NOTIFICATIONS` for Android 13+. It does not request SMS-reading, contacts, IMEI, phone number, serial, MAC address, storage, camera or microphone permissions.

## Server dependency

Deploy EP-DI v2.0.1.8 and run `database/upgrade-v2.0.1.8-trusted-app-desktop-login.sql` after the earlier mobile-auth/flexible-reader migrations.
