# Final Re-audit — ePaper SaaS v1.1.7

Release state: **TESTED**

v1.1.7 adds a conversion funnel without changing the clipping table or the protected Source Page QR implementation.

## Behavior contract
1. Anonymous reader may create one guest clip.
2. Guest clip is forced to a shareable PUBLIC link, remains unindexed, and expires after the existing fixed seven days.
3. The second guest clipping attempt displays the conversion gate and cannot reach the normal Reader save handler.
4. The API independently enforces the same guest quota.
5. Guest creation uses a secondary per-network rate limit as anti-abuse protection.
6. The server-side session remembers the guest clip slug; after successful authentication the next request attaches that ownerless tenant clip to the authenticated platform user.
7. Signed-in users remain subject to `EPAPER.CLIPPING`; missing entitlement produces a subscription conversion state.
8. Guest clipping pages invite sign-in and do not expose private-account semantics.

## Privacy/security
No canvas/font/hardware fingerprinting is introduced. The anonymous quota is browser/session based with coarse network abuse protection. Claiming requires the same server-side PHP session and updates only an ownerless clip within the active tenant.

## Protected regressions
- Source Page QR exact implementation: retained
- Premium clipping + fixed 7-day expiry: retained
- Settings normalization: retained
- Smart Social Sharing: retained
- Responsive/header dedup: retained
- Account Clear All fix: retained
- Admin Master Data/shared CRUD drawer: retained
- RabbitMQ/Supervisor/processing: untouched

Promote to VERIFIED only after an actual guest → second-attempt gate → login → claimed Library clip flow succeeds on `epaper.digiti.in`.
