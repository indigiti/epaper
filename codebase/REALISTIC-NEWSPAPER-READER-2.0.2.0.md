# EP-DI v2.0.2.0 — Realistic Newspaper Reader

## Scope

This release adds a newspaper-oriented presentation layer without replacing the existing EP-DI reader state, media, zoom/tile, clipping, Smart Article, offline, history or analytics systems.

### Reader views

- **Flip** — realistic page turning. Desktop uses a two-page spread when enabled; mobile portrait remains single-page.
- **Page** — existing high-resolution single-page reader with zoom, pan, tiles, clipping and Smart Article.
- **Continuous** — vertically scrollable, lazy-loaded preview pages.

The reader remembers the end-user view choice in local storage. The publisher can choose the default and spread/fallback behavior in Admin → Settings → Reader.

## Flip interaction

At normal fit scale the Flip adapter owns horizontal drag/navigation. It renders only the current spread and warmed adjacent spread states. It does not load all HIGHRES newspaper pages into memory.

When the user zooms above fit scale, enables clipping, or enters Smart Article selection, the flip surface is suspended and the existing EP-DI single-page document/tile surface becomes authoritative. Returning to Fit Page restores the configured Flip view.

### Navigation

- mouse/touch drag turns a page with perspective and moving fold shadow;
- arrows, keyboard arrows and Page Up/Page Down animate the same turn;
- thumbnails and page-number jumps keep the canonical `/edition/{slug}/page/{n}` state;
- desktop spreads follow cover → 2–3 → 4–5 … semantics;
- mobile/small layouts navigate one page at a time.

## Performance policy

The Flip view uses PREVIEW assets for the physical paper surface. HIGHRES and deep tiles remain on-demand in Page/zoom mode. Adjacent states reuse `reader-prefetch.js`/the existing state endpoint. Continuous mode uses IntersectionObserver and lazy preview loading instead of eagerly decoding every edition page.

`prefers-reduced-motion`, Data Saver, very low device memory and very low CPU concurrency disable expensive interactive turn animation while retaining navigation.

## Publisher settings

- `reader.default_view`: `flip | page | continuous`
- `reader.flip_desktop_spread`: `on | off`
- `reader.flip_tablet_spread`: `on | off`
- `reader.flip_shadow`: `on | off`
- `reader.flip_duration_ms`: bounded to 240–700 ms
- `reader.flip_low_power_fallback`: `on | off`

No database migration is required; these use the existing settings store.

## Engine choice

The production build uses a self-hosted EP-DI native flip adapter rather than a CDN or third-party runtime. This keeps the canonical Reader in control of state and avoids an additional library dependency. The adapter includes odd-final-spread handling, low-power swipe fallback and page-aware double-tap zoom.
