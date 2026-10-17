# EP-DI v2.0.1 — Code & Visual Audit / Implemented Decisions

## Header
- **Guest Sign in:** text removed from the compact header; accessible icon with `aria-label`/`title` links to `/login`.
- **Account:** existing authenticated user icon remains linked to `/account`.
- **Admin shortcut:** rendered only when the active membership role is in the existing `auth.admin_roles` policy. Server-side `/admin` authorization is unchanged.
- **Sign out:** remains available in Admin and was added to Account navigation so removing public-header text does not hide logout.

## Appearance
- Existing `body.dark`/Reader dark CSS is reused rather than introducing a second theme engine.
- New publisher defaults: Light (default), Follow device, Dark.
- Public switcher can be enabled/disabled. When disabled, stored browser overrides are ignored and publisher policy wins.
- Admin Console theme preference stays independent.
- Public toggle uses accessible pressed/label state and honors the system preference when publisher mode is `system`.

## Archive date rail
- Existing horizontally scrollable, cached date rail is retained.
- Repetitive “No edition” text is reduced to a muted dash for unavailable days.
- `aria-disabled` and an explicit no-edition accessible label remain.
- Current/selected date automatically centers on render.

## Reader edition rail
- New compact strip is 48px desktop, 46px tablet, 44px mobile and sits directly below the 62px global header.
- Reader main inset uses `--reader-edition-switcher-height`, preventing page overlap.
- Rail is horizontal, scroll-snap based, touch/trackpad friendly, keyboard focusable, dark-mode aware and reduced-motion aware.
- Only published editions matching publication + city + language are shown.
- Edition changes always open page 1 to avoid invalid page-number carryover.
- Publisher can disable the entire strip.

## Code-level guardrails
- No route dispatch changes.
- No v2.0.1 database migration.
- Tenant SQL guard covers the new Reader edition query.
- No media/Offline Vault path changes.
- No RabbitMQ/Supervisor worker change.
