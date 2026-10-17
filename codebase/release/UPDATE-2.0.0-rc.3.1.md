# EP-DI v2.0.0-rc.3.1 — Public Newsstand Polish

Baseline: **EP-DI v2.0.0-rc.3**

This is a presentation-only public UX patch. It does not change application routes, database schema, permissions, processing, publishing, Reader OS, Offline Vault, semantic SEO, admin workflows, RabbitMQ or Supervisor topology.

## Homepage
- Today’s Edition cover receives a 3 px white paper edge.
- Hover/focus applies a restrained 3° swing, 4 px lift and 1.01 scale over 190 ms.
- `prefers-reduced-motion` disables the transform.
- A low-opacity blurred editorial glow sits behind the Today’s Edition copy without introducing a card/glass surface.
- Hero cover remains responsive THUMB/PREVIEW only; no HIGHRES fallback is introduced.

## Recent editions
- Horizontal Newsstand rail remains native-scroll/swipe and now uses mandatory snap, smooth scrolling and touch pan-x.
- Card widths are normalized so covers no longer look compressed.
- Newspaper images use `object-fit: contain` inside a white framed surface instead of stretching/cropping.

## Archive
- Desktop: 3-column operational browse grid.
- Narrow desktop/tablet: 2 columns.
- Mobile: 1 column.
- Cover frames use `object-fit: contain` and stable newspaper aspect ratio.
- Existing date rail, month controls and publication/city/language/year filters are unchanged.

## Deployment
1. Take the normal application backup.
2. Extract the rc.3.1 hotfix over **v2.0.0-rc.3**.
3. Do **not** run SQL.
4. Do **not** run Upgrade Center repair.
5. No Supervisor restart is required.
6. Hard-refresh once so the `epdi-v2.0.0-rc.3.1` Service Worker activates.
7. Verify `/`, `/archive`, one publication landing page, one Reader edition and one previously-downloaded encrypted offline edition.
