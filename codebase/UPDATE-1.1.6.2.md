# ePaper SaaS v1.1.6.2 — Account Actions + Admin CRUD UX Fix

## Account Library

Fixed `/account/bookmarks`, `/account/clips`, `/account/saved-articles`, `/account/history`, `/account/offline` and user-owned `/account/notifications` bulk-clear handling.

- Bulk audit no longer passes a route label where a GUID is expected.
- Every delete remains tenant + platform-user scoped.
- Clip rows are deleted first; file cleanup is best-effort and restricted to the application storage root.
- Clear All appears only when there is something removable.
- Successful bulk actions return to the library with an affected-record count.

## Master Data

`/admin/master-data` is now a single focused workspace with:

- Cities / Languages / Sections tabs
- per-category record counts
- quick search
- unclipped rows
- status at a glance
- + Add action
- Edit / Enable-Disable / Delete-if-unused action menu
- right-side editor drawer on desktop
- bottom editor sheet on mobile

Create/edit forms no longer live inside tiny scrollable popovers.

## Shared CRUD editor

The same progressive enhancement converts existing form-heavy record menus in Publications, Highlights, Editions, Saved Articles, Bookmarks and Clips into a simple **Edit** action that opens the shared editor drawer. Existing endpoints, CSRF, RBAC, validation and audit behavior remain authoritative.

## Protected history

Audit, Error Center and Processing history are intentionally not made deletable.

## Deployment

Required baseline: **v1.1.6.1**. No database migration and no RabbitMQ/Supervisor change. Overlay into the existing outer `public_html`, purge Cloudways/Varnish cache and hard-refresh.
