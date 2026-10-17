# EP-DI v2.0.1.7 — Flexible Reader Identity

## Publisher policy
Open **Publisher Control → Reader access**. SMS, WhatsApp and SNA are independently switchable per tenant. A method becomes effective only when both the tenant switch and server/provider capability permit it.

If the effective mobile-verification list is empty and **Temporary device account fallback** is enabled, the official Android app automatically creates or reuses an ePaper-local `LOCAL_DEVICE` account.

## Device privacy
The Android app creates a random installation UUID and stores it in app-private SharedPreferences. EP-DI HMACs the value with a server secret before persistence. EP-DI does not request or store IMEI, SIM identifiers, hardware serial numbers, MAC addresses or contacts/SMS permissions.

## Temporary account lifecycle
- Stored in `ep_local_device_accounts` inside the EP-DI database.
- Has `memberships=[]`; it cannot grant Publisher/Admin access.
- Uses rolling inactivity expiry (default 30 days).
- New-account creation is rate limited per IP/day.
- Publisher Subscriber disable/enable remains authoritative.
- LOCAL_DEVICE sessions fail closed if account/subscriber/identity validation cannot be completed.
- Scheduler marks abandoned accounts expired and prunes old merged mappings.

## Upgrade to verified mobile
When a temporary reader later verifies a phone number from the same Android installation, EP-DI promotes the session to `LOCAL_MOBILE` and merges the temporary reader's bookmarks, saved articles, clips, reading history, preferences and relevant reader ownership/analytics records where safe.

## Database upgrade
Existing v2.0.1.6 installations must run:

`database/upgrade-v2.0.1.7-flexible-reader-identity.sql`

The existing v2.0.1.5 mobile-auth schema remains a prerequisite for installations upgrading from an older release.
