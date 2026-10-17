# EP-DI v2.0.0-rc.1.2 — Public Newsstand & Archive Experience

## Purpose
RC.1.2 is a public-facing UX/performance release on top of the verified RC.1.1 Publisher Operations Console baseline. It redesigns `/` and `/archive` using the supplied homepage/archive prototype as visual inspiration while preserving the existing SSR routes, live data model, Reader OS, Offline Vault, semantic SEO and publisher-admin behavior.

## What changed

### Homepage
- Rebuilt the live homepage as a calmer digital newsstand centered on the current published edition.
- Uses real publication/date/city/language/page-count data only; no prototype statistics or fabricated marketing claims were imported.
- Added responsive current-edition cover delivery with THUMB as the initial source and PREVIEW as the only larger responsive source.
- Added horizontally scrollable Recent Editions rail using strict THUMB assets.
- Added publication tiles, Archive date CTA and retained Highlights + signed-in Continue Reading.

### Archive
- Added a live, server-rendered month/date rail above the existing Archive filtering workflow.
- Added previous/next month controls, Today, Yesterday, Last 7 days and All Year shortcuts.
- Available dates show the count of published editions; unavailable dates are disabled rather than fake-linked.
- Date links preserve publication/city/language filter state.
- Existing `/archive`, `publication`, `city`, `language`, `year` and `date` behavior remains supported; `month` and `range=7` are additive query parameters.
- Archive result cards recompose responsively for desktop, tablet and mobile.

### Cover-image delivery
- Listing/newsstand `THUMB` mode is now strict: Archive, Recent Editions and Publication listing cards never fall back to PREVIEW or HIGHRES.
- Homepage hero PREVIEW mode may fall back to THUMB, but never to HIGHRES.
- Missing thumbnails show a lightweight placeholder rather than transferring a large newspaper page.
- THUMB/PREVIEW dimensions are returned with cover read models so image boxes can reserve layout space and reduce CLS.
- Listing images use lazy loading + asynchronous decoding; the homepage hero is prioritized.

### Cache integration
- Added `PublicReadCache::archiveCalendar()` using the existing beta.2.1 multi-layer cache.
- Calendar cache invalidates with the existing `public-editions` generation, so publication/reprocess/unpublish changes naturally invalidate date availability.
- No second cache architecture was introduced.

## Deployment
1. Back up the Cloudways application as usual.
2. Extract the RC.1.2 Public UX hotfix over the verified RC.1.1 application.
3. Do **not** run SQL; RC.1.2 has no database migration.
4. No Supervisor restart is required because long-running worker behavior is unchanged.
5. Hard-refresh once so service worker `epdi-v2.0.0-rc.1.2` and the new public CSS are active.
6. Verify `/`, `/archive`, one `/epaper/{slug}` publication page and a Reader edition.

## Suggested acceptance checks
- Homepage uses THUMB/PREVIEW only and does not request HIGHRES for the front page.
- Recent Editions and Archive cards request strict THUMB assets.
- An edition with a missing THUMB renders a placeholder rather than PREVIEW/HIGHRES.
- Archive date availability matches published editions for the active publication/city/language filters.
- Today, Yesterday, Last 7 days, month navigation and All Year behave correctly.
- Existing Archive query parameters remain bookmarkable.
- Mobile date rail is swipeable and Archive cards have no horizontal overflow.
- Reader, Offline Vault and semantic Article/SEO surfaces remain unchanged.

## Database
No database migration.

## Worker topology
Unchanged. No Supervisor restart required.
