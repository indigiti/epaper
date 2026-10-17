# Release Test Results — ePaper SaaS v1.1.7

State: **TESTED**

## Passed
- PHP syntax: App/View/build guard/release check
- guest clipping JS syntax
- service worker syntax
- exact v1.1.4.1 QrCode.php hash retained
- exact v1.1.4.1 ClipService.php hash retained
- v1.1.5.1 Settings normalization regression
- v1.1.6 Smart Social Sharing regression
- v1.1.6.1 Header dedup regression
- v1.1.6.2 Account bulk-clear/Admin CRUD regression
- first guest clipping remains allowed
- used guest quota intercepts save before Reader creation handler
- guest clipping API success marks the in-page quota used
- known subscription requirement gates before Reader save
- entitlement failure opens subscription conversion state
- guest clipping page uses noindex/nofollow
- post-login claim SQL is tenant scoped and only claims ownerless clips
- secondary guest network rate limiter present
- guest clipping module included in service worker

## Browser test
Headless Chromium verified guest conversion behavior at desktop 1440×900 and mobile 390×844.

## Live checks still required
- Actual anonymous clipping creation against live MariaDB/media stack
- Login redirect/session preservation and automatic clip claim
- Live Digiti Platform entitlement state
- Tenant-specific subscription destination
- Mobile QR/share on a guest-created clipping
