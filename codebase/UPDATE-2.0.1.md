# EP-DI v2.0.1 — Reader Navigation & Appearance Polish

## Baseline
Built from the exact EP-DI v2.0.0 Production Stable package. v2.0.0 remains the rollback baseline.

## User-facing changes
- Guest header uses a Sign in icon linking `/login`.
- Authenticated header retains the Account icon linking `/account`.
- Users whose active tenant role is already allowed by `auth.admin_roles` receive a Publisher Administration icon linking `/admin`. Hiding/showing this icon is presentation only; `/admin` still enforces `Auth::requireAdmin()`.
- Public/Reader light-dark switcher can be shown or hidden from Publisher Control → Appearance.
- Public color default supports Light (default), Follow device, or Dark. Browser override is used only when the switcher is enabled.
- Publisher Console appearance remains separate from public/Reader appearance.
- Reader may show a compact edition-date swipe strip immediately below the global header. It is controlled by Publisher Control → Reader → Edition date switcher.
- Reader edition switching stays within the current publication, city and language, and opens page 1 of the chosen edition.
- Archive selected date is automatically centered; unavailable days use a muted dash rather than repeated “No edition” copy.

## Data/performance behavior
`PublicReadCache::readerEditionCalendar()` performs a tenant-scoped lightweight published-edition lookup only for edition date, slug, page count and creation order. It uses the existing `public-editions` generation and MultiLayerCache; it does not join page/media assets.

## Deployment from v2.0.0
1. Take a normal application/database backup.
2. Extract `EP-DI-v2.0.1-READER-NAV-APPEARANCE-HOTFIX.zip` over the v2.0.0 application.
3. Do **not** run SQL; there is no v2.0.1 migration.
4. Do **not** run Upgrade Center repair for this release.
5. Supervisor restart is not required; worker code/topology is unchanged.
6. Hard-refresh once so service worker `epdi-v2.0.1` activates.
7. Verify `/`, `/archive`, one `/epaper/{publication}`, `/login`, `/account`, `/admin` with authorized/unauthorized users, and one Reader edition.
8. In Publisher Control, verify the new Appearance and Reader controls before changing production policy.

## Live acceptance still required
Package/static gates cannot prove Cloudways runtime state or physical-browser behavior. After deployment, verify icon visibility by role, light/system/dark policy on target browsers/devices, Reader strip touch scrolling, and the Reader viewport at desktop/tablet/mobile breakpoints.
