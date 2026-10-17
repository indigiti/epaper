# Final Re-audit — ePaper SaaS v1.1.1

## Scope
Reader chrome persistence, Account Library visual parity, and Publisher Admin navigation/IA. This release is intentionally UI/interaction focused and does not modify the RabbitMQ/Supervisor processing engine or database schema.

## Reader
- Global header remains fixed/reachable on Reader pages.
- Reader header is never faded away by idle mode.
- Bottom previous/page/next pager remains visible.
- Only the secondary zoom/tool rail may soften after idle.
- Idle threshold increased from ~1.6s to ~4.6s.
- Existing Fit Page, Fit Width, pinch, pan, keyboard, swipe, Deep Zoom, Clip, Smart Article and seamless navigation code retained.

## Account
- Persistent Account shell with active navigation.
- Preview-based WebP cards for Continue Reading, Saved Articles, Bookmarks, History and Offline.
- Clips use their actual `/c/{slug}/image.webp` output.
- Continue Reading deduplicates by edition and keeps the most recent history entry.
- Notifications use a dedicated read/unread feed instead of generic article cards.
- Purpose-built empty states and direct Reader CTA included.

## Publisher Admin
- Persistent desktop sidebar and mobile drawer.
- Menu reorganized around publisher workflow: Workspace, Content, Publishing, Audience, Configuration, Access & Governance, Operations, Account.
- Settings consolidated to one sidebar destination; existing Appearance/Brand/SEO/Reader/Processing/Clipping routes remain accessible through internal tabs.
- Presentation navigation varies by role profile; server-side route authorization remains authoritative.
- Command palette is derived from the visible role-aware sidebar rather than a static global list.

## Release guards
- No route removed.
- No baseline capability removed.
- No baseline setting removed.
- No schema migration.
- Tenant-scoped Account queries retained.
- Service worker and asset cache-busters aligned to v1.1.1.

## Live verification required
Physical mobile pinch/Reader navigation and production Account/Admin rendering still require Cloudways/browser verification before promoting from TESTED to VERIFIED.
