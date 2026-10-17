# ePaper SaaS v1.1.9.7 — Final Re-audit

## Finding

The live v1.1.9.6 screenshot showed the new glass theme but a card stretched into a tall desktop portrait panel. This ruled out a simple stale-theme problem. The remaining structural risk was that the server-generated login body still used global legacy class names (`auth-panel`, `auth-form`, etc.) from the cumulative `app.css` authentication history.

## Correction

v1.1.9.7 renames those form-body class tokens into an isolated `auth-fit-*` namespace before rendering. The login card is now explicitly content-fit with `height:auto`, `min-height:0`, `max-height:none`, `flex:none`, and a 440px maximum width. The rotating quote is inside the card and the duplicate inner EPAPER brand is hidden.

The card no longer depends on any global `.auth-panel`, `.auth-form`, `.auth-story`, or `.auth-form-stage` layout rule.

## Regression status

Cumulative guards retain v1.1.4.1 Source Page QR, v1.1.5.1 Settings normalization, Smart Social Sharing, Header dedup, Account/Admin CRUD, Guest Clipping, same-domain subscription behavior, and Reader Redis/predictive prefetch. No production credentials are packaged.

Release state: **TESTED**. Live Cloudways verification is required before marking VERIFIED.
