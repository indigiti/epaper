# ePaper SaaS v1.1.9.7 — Content-Fit Glass Login Fix

This hotfix fixes the live v1.1.9.6 symptom where the glass login card could stretch into a tall portrait panel on desktop.

## Root cause addressed

The login form body still carried legacy global classes such as `auth-panel` and `auth-form`. Earlier authentication CSS in the cumulative `app.css` could therefore continue to participate in layout depending on viewport, browser zoom, cached rules, and cascade ordering.

v1.1.9.7 isolates the authentication form markup from those legacy class names before rendering. The new card uses only `auth-fit-*` classes.

## Changes

- Renames legacy authentication form classes to a dedicated `auth-fit-*` namespace at render time.
- Hides the duplicate inner EPAPER/auth brand block; tenant/publisher logo remains the page brand.
- Replaces the 500px card with a compact 440px maximum content-fit card.
- Explicitly enforces `height:auto`, `min-height:0`, `max-height:none`, `flex:none` and content-fit form/panel geometry.
- Keeps all three existing authentication inputs and the same backend POST/authentication behavior.
- Moves the rotating quote inside the card so it cannot extend the page layout.
- Uses a mobile-specific fluid width and compact spacing; short desktop viewports scroll naturally rather than stretching the card.
- Adds zoom-stress regression checks at 67%, 80%, 100%, 125% and 150%.
- Loads `/assets/auth-v1.1.9.7.css?v=1.1.9.7` and bumps shell asset versions to prevent stale auth CSS.

## Preserved

No database migration. No RabbitMQ/Supervisor restart. Digiti Platform remains background-only. Source Page QR, ClipService, guest clipping, same-domain subscription flow, Reader Redis/prefetch and Admin/account fixes are unchanged.
