# EP-DI v2.0.0-rc.3.1.1 — Publication Newsstand Rail Fix

## Scope
Presentation-only correction for publication landing pages such as `/epaper/civic-mirror` and `/epaper/pune-mirror`.

## What changed
- Publication edition lists now render as a horizontal swipe/trackpad rail.
- Desktop cards use a stable ~190–230px newspaper width rather than auto-fill stretching.
- Tablet/mobile cards use responsive rail widths while preserving native newspaper geometry.
- Publication cover images now use `object-fit: contain` and `object-position: center top`.
- Horizontal rail uses mandatory scroll snap, native touch panning, momentum scrolling and thin scrollbars.
- Keyboard focus is visible; reduced-motion users do not receive smooth/hover motion.
- Homepage Today’s Edition, Recent Editions, Archive grid/date rail, Reader, Offline Vault and Admin Console are unchanged.

## Delivery behavior
The existing publication renderer already requests `THUMB`, so this release does not change media/DB logic. It corrects only the CSS presentation path that was still using the legacy `.newsstand` grid and `object-fit: cover` rule.

## Deployment from v2.0.0-rc.3.1
1. Take the normal application backup.
2. Extract the rc.3.1.1 hotfix over rc.3.1.
3. Do **not** run SQL.
4. Do **not** run Upgrade Center repair.
5. No Supervisor restart is required.
6. Hard-refresh once so `epdi-v2.0.0-rc.3.1.1` and the new CSS cache key activate.
7. Verify `/epaper/civic-mirror`, one other publication page, `/`, `/archive`, and one Reader edition.

## Rollback
Restore the rc.3.1 files listed in `ROLLBACK-2.0.0-rc.3.1.1.md` or redeploy the exact rc.3.1 package. No database rollback is required.
