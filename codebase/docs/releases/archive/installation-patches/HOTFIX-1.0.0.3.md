# ePaper SaaS v1.0.0.3 local-login hotfix

## Required Platform update
Install Digiti Platform v1.0.2 first.

Then edit the ePaper SaaS service account in Platform and add scope:

`auth.direct`

Keep the existing scopes such as `domains.resolve`, `tenant.read`, `identity.verify`, `auth.exchange`, `errors.write`, etc.

## Deployment
Overwrite the existing ePaper SaaS files with this patch. No database migration or reinstall is required.

Normal sign-in is now at `/login`; `/auth/callback` remains only for OAuth compatibility.
