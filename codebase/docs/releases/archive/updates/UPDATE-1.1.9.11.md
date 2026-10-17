# ePaper SaaS v1.1.9.11 — Direct-Flow Login Geometry Fix

This hotfix removes the remaining large blank zones visible on the live branded login page.

## Root cause addressed

The previous layout still allowed the legacy authentication panel/form wrapper to participate in layout. A tenant theme, inline rule, browser zoom interaction, or cached global auth rule could therefore force that wrapper to a tall block and distribute empty space above the heading, between the trust note and editorial quote, and below the quote.

## Fix

- The legacy auth panel wrapper is stripped from the rendered geometry.
- Heading, intro, fields, trust note, and rotating quote are direct children of one intrinsic-height `.auth-fit-content` stack.
- Legacy inline geometry on the form is sanitized before render.
- Card/content/form use intrinsic height only; no flex growth, `min-height`, spacer, `margin-top:auto`, or distributed vertical alignment is allowed.
- Auth assets are cache-busted to v1.1.9.11.
- Service worker shell cache is bumped to v1.1.9.11.

No database migration or worker restart is required.
