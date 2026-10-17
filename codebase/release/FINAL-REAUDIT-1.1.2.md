# Final Re-audit — ePaper SaaS v1.1.2

## Scope

Admin appearance and operations-only release. Reader geometry, media delivery, RabbitMQ/Supervisor processing, Platform binding, account library and database schema are unchanged from v1.1.1.

## Implemented

- Per-user Publisher Admin Light/Dark mode using existing `ep_subscribers.preferences_json`.
- Server-rendered saved theme on the next request, with optimistic client toggle and rollback if persistence fails.
- Compact bottom-right request telemetry HUD: actual DB query count, DB query time, request-local Redis JSON cache hit rate and cache driver.
- Luxury System Health: live score, grouped Core / Processing / Queue & Workers cards, runtime metrics and safe operational links.
- Dark-mode polish for Publisher shell, status badges, panels and operations cards.

## Regression results

- PHP syntax: PASS (50 files).
- JavaScript syntax: PASS (`app.js`, `reader.js`, `service-worker.js`).
- v1.1.2 build guards: PASS.
- Tenant SQL guard: PASS — 178 tenant-owned SQL statements checked; 0 unscoped.
- Baseline route deletion guard: PASS.
- Baseline capability deletion guard: PASS.
- Baseline settings deletion guard: PASS.
- Reader parity guard: PASS.
- RabbitMQ/Supervisor/outbox protected files retained.
- Database schema: unchanged.
- Route manifest: 68 routes, including new `POST /admin/preferences/theme`.
- Capabilities: 124.
- Settings: 46, unchanged from v1.1.1.

## Live verification required

1. Toggle Light → Dark → Light as a Publisher Admin; refresh and sign out/in to confirm persistence.
2. Confirm a second admin user can keep an independent preference.
3. Open `/admin/system/health` in both appearances and confirm live score/cards match actual readiness.
4. Confirm the bottom-right HUD reports DB/cache metrics and never exposes credentials.
5. Confirm `/health/ready`, `/health/queue`, Reader, Account, Processing and Infrastructure remain unchanged operationally.

Release state: **TESTED** pending live Cloudways/browser verification.
