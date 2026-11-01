# ePaper Publisher Studio — UI audit, theme parity and release acceptance

Baseline: cw-epaper at 8adef5bb4b4821a3849792b37f4ba0803fc2c77c. Target: PR #57 (feature branch feat/cw-dashboard-system-theme-parity). Deployment root on Cloudways is public_html/ and source PHP app/ at repository root.

## Code-audited causes

1. Admin: old app.css contained superficial theme variables, but admin-beta.css, admin-studio.css, admin-sidebar.css, and admin-typography.css later set most surface/spacing tokens. Selecting Executive Slate or Midnight Desk did not reliably change the console. Repeated and conflicting sidebar widths (~238/248/254/268px) and competing fonts made the navigation look unaligned.
2. Reader: cw-epaper reader.css contained no individual theme selectors for Newsstand Replica, Editorial Luxe, Metro Pulse, Heritage Press or Apple Editorial.
3. Authentication: View::authExperience injects #epx-login-v5 inline styles, dominating earlier low-specificity auth themes. The fallback in the Appearance catalog differed from View's own fallback.
4. Sidebar: admin-sidebar.js independently restored rail state, group open state and search; browser test coverage was missing for state persistence across reload, collapsing and search. CSS layers independently altered group padding and fonts.
5. Icons: View::icon returned a generic circle for unknown names; module icon names had no catalogue-level validation. The UI also rendered star glyphs for pin actions.
6. Publisher logo: a missing brand-logo image displayed an unprofessional broken-image glyph.
7. Dashboard: one-off markup/styles produced inconsistent cards, sections and redundant links. Existing counts already available on the dashboard can support a useful current-workflow chart without a new query.

## Implementation and boundaries

- New admin-design-system.css is a final, admin-only design layer; global reader/auth/account/public classes remain unchanged.
- Admin styles: Publisher Pro teal, Executive Slate blue, Midnight Desk dark, Default neutral. Unconfigured Admin keeps existing Publisher Pro selection.
- Reader: Newsstand Replica, Editorial Luxe, Metro Pulse, Heritage Press, Apple Editorial plus Default. Reader chrome only; page canvas, clipping offsets, page rotation, zoom engine and reader scripts untouched.
- Authentication: unchanged Default, plus Split Editorial, Gradient Focus and Minimal Access. Existing login forms, CSRF and identity validation untouched. Saved theme values are respected; unconfigured login uses Default to preserve its previous appearance.
- AppearanceGallery renders 19 CSS-only mockup tiles: five frontend, four admin, six reader, four auth. Selecting a tile changes the existing settings form input and still requires explicit Save. Frontend, Reader and Admin managers can preview on their respective screens. Authentication is checked through the sign-in page in a private browser session after saving, not an impersonating public preview endpoint.
- AdminFeatureCatalog continues to determine role-visible routes, AdminRoutePolicy remains the authorization boundary, and SVG icons are locally rendered.
- The sidebar remains native accessible details; desktop narrow icon rail is separate from app.js mobile drawer.
- Dashboard pipeline chart uses existing status counts, is explicitly labeled as a current distribution, and exposes edition links only for authorized editor roles.
- CSS and JS assets are static, served via Build::asset; the gallery stylesheet/script are loaded only on Appearance.
- Existing DB queries, multi-layer caching, Redis, CDN, publishing services, Cloudways paths, configuration, PDF/image storage, clipping workers and queue systems are not modified. No claim of measured response-time improvements without before-and-after production telemetry.

## Gates before Cloudways Pull

- GitHub Actions Cloudways ePaper AI and Publishing Staging: source-contract, existing public-browser-smoke and Five frontend themes (Cloudways layout), all successful on the exact cw-epaper merge SHA.
- PHP syntax, all role icon/rendering/source tests and legacy admin/reader/source contracts pass.
- Chromium fixtures: desktop 1440px and mobile 390px, four distinct admin skins, navigation open/search/collapse persistence, no horizontal overflow, auth and reader visual differences, Appearance card selection without automatic publishing.
- Visual fixture screenshots are NOT actual screenshots of the protected production Publishing Desk.

## Live acceptance after Cloudways Fetch / Pull

1. Back up application and database and verify rollback, record deployed Git SHA and check no active DigiOps deployment.
2. Confirm Cloudways EPaper repository, cw-epaper branch, public_html/ deployment path. Do not clean tracked runtime uploads or private config.
3. Test /admin in desktop (1440px), tablet (~768px) and mobile (390px), sidebar group expand/collapse/search, keyboard focus, accessible icons, no horizontal scrolling.
4. Validate /admin/settings/appearance: 19 demo cards are visible and correct, selecting a card updates the form without publishing; saved Admin themes change real logged-in UI after refresh.
5. Validate Reader themes with a REAL published newspaper: page 1 loads, page flip, scrolling, spread/single, zoom, clipping rectangles, OCR article mode, clipping share and downloads remain in place. Test desktop and mobile; no geometry displacement.
6. Validate Authentication default plus saved split/gradient/minimal appearances using signed-out private windows. Verify login and recovery, no theme-specific hidden fields or form truncation.
7. Verify OWNER, ADMIN, PUBLISHER_ADMIN, EDITOR and OPERATOR have only allowed routes; operators do not receive editor controls, and hidden menu items cannot be reached by direct link.
8. Confirm missing publisher logo renders text fallback; verify site header, publisher branding and sign-in contrast.
9. Test both Admin and Reader dark/light color choices and all responsive widths.
10. Validate /health/live and /health/ready, processing queues, scheduler and page publish; compare PHP timings, DB query count, peak memory, Core Web Vitals, and browser console/network failures against the previous baseline.
11. Restore previous frontend/admin theme if visual acceptance fails. If release rollback is necessary, restore prior Cloudways code with protected storage unchanged.

## Remaining architectural debt / limits

- Older admin base styles remain because specialist modules rely on them; the final scoped design layer consolidates presentation but does not delete every old selector.
- CSS-only preview illustrations are representative, not live screenshots.
- Synthetic browser tests do not prove tenant-specific production performance or real PDF clipping geometry.
- Before claiming "superfast", collect reproducible production metrics for cold/hot HTML, Redis cache hit ratio, database slow queries, reader image latency and device performance. Set service objectives and use staging load tests.
- A future phase may extract the very large App.php / View.php renderers into dedicated view components, if necessary, only with route and authorization parity tests.
