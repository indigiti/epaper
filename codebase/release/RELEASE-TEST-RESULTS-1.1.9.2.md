# Release Test Results — v1.1.9.2

State: **TESTED**

- Searchable Archive feature removed from branded login: PASS
- Desktop auth card uses content-driven height (no fixed 620px clipping): PASS
- Full Email / Password / Authenticator / Sign in / security footer remain inside card: PASS
- 1440×900: PASS
- 1366×768: PASS
- 1280×720: PASS
- 390×844 mobile sign-in-first layout: PASS
- PHP syntax: PASS
- Service Worker JavaScript syntax: PASS
- v1.1.9.2 cumulative build guard: PASS
- Settings POST normalization regression: PASS
- v1.1.4.1 Source Page QR exact protected hashes: PASS
- Same-domain subscription / Platform-background-only guards: PASS
- Reader Redis/prefetch guards: PASS
- Guest clipping guards: PASS

No database migration. No RabbitMQ/Supervisor restart required.
