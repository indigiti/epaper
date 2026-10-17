# ePaper SaaS v1.1.9.4 — Stable Auth Shell

Base: v1.1.9.3.

## Fixed
- Removed fixed desktop auth height that could clip content at some viewport/zoom combinations.
- Replaced hidden overflow scroll containers with `overflow: clip`.
- Removed the duplicate bottom control-plane note.
- Rebalanced desktop auth to a compact 980px landscape shell with content-sized height.
- Normalized the login typography and form geometry.
- Preserved the mobile sign-in-first stack.
- `Searchable Archive` remains removed.

## Cache bust
All first-party asset version query strings and the service-worker cache version are bumped to `1.1.9.4` so v1.1.9.3 auth CSS cannot remain active after deployment.

No database migration. No RabbitMQ/Supervisor restart.
