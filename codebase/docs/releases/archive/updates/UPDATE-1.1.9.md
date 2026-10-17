# ePaper SaaS v1.1.9 — Branded Login Experience

This hotfix upgrades the same-domain authentication experience without changing the underlying authentication protocol.

## What changes

- Full-viewport two-panel publisher-branded login shell on desktop.
- Responsive single-column mobile layout with the sign-in form first.
- Primary/Dark Logo support, Square Mark fallback, then generated brand monogram fallback.
- Product positioning copy for Today’s ePaper, Searchable Archive, and Clipping & Sharing Tools.
- Twelve short rotating quotes chosen on the server for each auth-page render.
- Existing `appearance.auth_theme` continues to control Split Editorial / Gradient Focus / Minimal Access treatment.
- Normal public site header/footer are hidden only for auth pages.
- Digiti Platform remains background-only; authentication/subscription navigation stays on the current ePaper domain.

## Not changed

- Authentication POST fields, callback flow, CSRF/session handling, MFA behavior or Platform verification.
- Reader, clipping, Source Page QR, account CRUD, Redis Reader cache, RabbitMQ or Supervisor.
- Database schema.
