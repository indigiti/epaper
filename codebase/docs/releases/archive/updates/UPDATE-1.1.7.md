# ePaper SaaS v1.1.7 — Guest Clipping + Conversion Gate

## Goal
Allow an anonymous reader to experience clipping once before requiring an account or subscription.

## Guest policy
- First anonymous clipping: allowed.
- Guest clipping visibility: shareable public link, but the clipping page is `noindex,nofollow`.
- Guest clipping expiry: existing fixed 7-day clipping policy.
- Second anonymous clipping attempt: blocked before another clip is created and replaced with the sign-in/subscription conversion sheet.
- Guest state: PHP session plus an HttpOnly 7-day positive quota marker cookie.
- Abuse mitigation: a secondary tenant/IP-hash rate limiter permits only a small number of guest creation attempts from one network per day.

Because an anonymous visitor has no durable account identity, the one-free-clip rule is necessarily a browser/guest-session rule rather than a cryptographic person identity. Clearing browser state can create a new guest identity; the network rate limiter reduces simple repeated abuse without invasive device fingerprinting.

## Claim after login
The first guest clip slug is retained in the server-side PHP session. After authentication succeeds and the user returns in the same browser/session, ePaper SaaS attaches the previously ownerless clip to that platform user. No database schema change is needed.

## Subscription behavior
Authenticated users continue to use `EPAPER.CLIPPING`. When the entitlement is unavailable, the Reader shows a conversion sheet instead of a raw 403/error toast. `clipping.subscribe_url` may be configured internally; otherwise the CTA falls back to `https://platform.digiti.in`.

## Guest clipping page
The guest clipping page displays a Sign in & save banner, keeps QR/share behavior, and uses `noindex,nofollow`. Full prepared download formats become available after the clip is claimed by a signed-in owner.

## Deployment
This is an overwrite hotfix for v1.1.6.2. No MariaDB migration and no RabbitMQ/Supervisor reconfiguration are required.
