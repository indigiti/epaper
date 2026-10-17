# Final Re-audit — ePaper SaaS v1.1.9.5

## Root cause
The production auth UI had accumulated multiple generations of auth layout CSS (v1.1.9.1 through v1.1.9.4) inside the shared application stylesheet. The later selectors usually won, but viewport-height, browser zoom, inherited theme rules and stale static caching could still produce conflicting geometry in the live page.

## Corrective design
v1.1.9.5 stops modifying the cumulative auth block and introduces an auth-only stylesheet loaded after `app.css`. The stylesheet explicitly resets geometry, typography, overflow and panel layout for the auth route. Desktop follows the original City ePaper/Cloudways split-login pattern; tablet and mobile have separate layouts.

## Regression boundary
No routes, database schema, auth POST logic, tenant resolution, Platform background authentication, clipping business logic, QR generation, Reader cache/prefetch, RabbitMQ or processing workers were changed.

## Protected baseline
`QrCode.php`: `2ca83d8f5cd585d0a1d983a540acd1a01027349362d4c293f427f11cd7eb1716`

`ClipService.php`: `d57ece49dc21bc45f7cf4c261c3390d6f7538a0d79c98f4ff9a983dd209c06f6`

Both remain byte-for-byte unchanged and are excluded from the hotfix ZIP.
