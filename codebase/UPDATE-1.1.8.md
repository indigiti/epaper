# ePaper SaaS v1.1.8 — Instant Page Navigation + Reader Cache

## Reader performance
- Reader state metadata is cached in Redis for 300 seconds using a tenant-scoped key.
- Adjacent page PREVIEW images are fetched and decoded before navigation.
- N+2/N-2 state is warmed in the background; N+2 preview is skipped in Low Data mode.
- Swipe direction raises the likely next/previous page to immediate prefetch priority.
- High Quality mode may warm adjacent HIGHRES after PREVIEW is ready.
- Service Worker media fallback is bounded to 18 media objects and remains network/HTTP-cache first so token/authorization semantics stay authoritative.
- Redis never stores page WebP binaries.

## Subscription privacy / control-plane boundary
`platform.digiti.in` is not a user-facing subscription destination. Reader conversion CTAs use `/subscribe`, and authenticated users view `/account/subscription` on the current ePaper domain. Digiti Platform remains a server-to-server control plane for access and entitlement synchronization.

No database migration or RabbitMQ/Supervisor reconfiguration is required.
