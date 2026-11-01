# Five frontend themes — Cloudways pull acceptance

Source implementation: main commit 60a99ba34bf94d9f038fa10624eef4669c988955 (PR #54 and PR #55).

## Why this is a port, not a main merge

The Cloudways branch cw-epaper deploys at the repository root: app/ for PHP, public_html/ for web assets and tests/ for CI checks. The main branch uses nested codebase/ and differs substantially. This change ports only public frontend designs, retaining Cloudways-specific reader, clipping, admin and publishing changes.

## Included

- Five options in Appearance: Default, Newsstand Modern, Editorial Portal, Classic Newspaper and Digital Magazine.
- Four distinct, real-data HTML homepage layouts; existing Default is untouched.
- Private OWNER/ADMIN/PUBLISHER_ADMIN previews on /admin/settings/appearance with noindex and private/no-store.
- Scoped CSS only; no reader/clip JavaScript, database schema, service-worker, or runtime media modifications.
- Browser fixture screenshots and automated PHP acceptance checks.

## Before pressing Pull

1. Confirm EPaper application, git@github.com:indigiti/epaper.git, cw-epaper branch, deployment path public_html/.
2. Take and record a Cloudways application + MariaDB backup and verify rollback access.
3. Confirm CI passed at the exact cw-epaper commit being deployed.
4. Confirm Cloudways Git integration will not delete/overwrite live media, PDFs, clips, uploaded documents or private config.php. Do not perform destructive resets or branch switches.
5. Avoid simultaneous DigiOps deployment of the same application.
6. Record the currently deployed source SHA before promotion.

## Cloudways deployment

Cloudways > EPaper > Deployment via GIT > verify branch cw-epaper and path public_html/ > Pull. Review deployment logs and confirm complete. GitHub merge alone does not trigger the Cloudways pull.

## Browser acceptance on actual published data

- Default home and /archive should look as before.
- OWNER/ADMIN/PUBLISHER_ADMIN can visit /admin/settings/appearance and preview all five layouts.
- Confirm Preview changes do not persist, do not leak to anonymous users, return noindex and private/no-store.
- Test stories where real published articles exist; otherwise the page must not invent stories.
- Test actual publication covers, publication and edition links, archive/search and reader.
- Check reader page flip, clipping, zoom and article view remain unaffected.
- Test light/dark and 375px, 768px, 1440px layouts for horizontal overflow.
- Save a new public theme, check anonymous homepage, then restore Default.
- Confirm health/live, health/ready, scheduler, admin processing and no new PHP exceptions.

## Rollback

In Appearance, choose Default to restore the existing homepage without redeployment. For code-level failure, use the verified earlier Cloudways application release/backup. No DB migration occurs with this theme-only update. Never delete production uploads to force checkout.

Passing GitHub CI proves source and synthetic fixtures only, not actual Cloudways or real-publication acceptance.
