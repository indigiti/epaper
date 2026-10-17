# ePaper SaaS v1.1.9.8 — Luxury Editorial Quote Login

Requires v1.1.9.7.

## Changes

- Preserves the v1.1.9.7 content-fit glass authentication card and shared reference-style sky/orbit background.
- Promotes the rotating editorial quote from tiny footer text into a dedicated luxury glass quote card.
- Adds a restrained editorial quote mark, stronger quote typography, quieter author treatment, and tenant accent integration.
- Keeps the existing 12-quote server-side pool and selects a new random quote on each auth render while avoiding an immediate repeat within the active session.
- Quote area remains inside the login card and is responsive at 320px through desktop widths.
- Authentication, MFA, same-domain login/subscription behavior, guest-clip claiming, and Digiti Platform background-only boundary are unchanged.
- No database migration and no worker restart.
