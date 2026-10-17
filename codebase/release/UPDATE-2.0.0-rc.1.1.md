# EP-DI v2.0.0-rc.1.1 — Publisher Operations Console UX

## Scope

Admin-only presentation/interaction refinement on top of the verified v2.0.0-rc.1 release. No database migration, route rename, permission change, publishing logic change, queue change, Reader change or Offline Vault change.

## Implemented

- Reorganized admin navigation around Publishing, Discovery, Audience, Publisher, Operations and Governance.
- `/admin/` compatibility normalization to the existing `/admin` dashboard.
- Premium low-chrome Publisher Operations shell with tighter typography, spacing, radius and surface hierarchy.
- Dashboard converted to publishing-desk hierarchy: Processing, Failed jobs, Scheduled, Published, Attention Center and common publishing operations.
- Reusable CRUD action cluster with accessible icon-only View/Edit/Delete/Enable/Disable/Publish/Unpublish actions where unambiguous.
- Editions row actions now visibly include View, Edit, contextual Process/Reprocess/Publish, Archive and Restore.
- Archive uses a reversible destructive treatment; Restore uses a separate purple semantic action.
- Reader Navigation reorder/toggle/delete/restore actions use the same shared operational icon language.
- Editions recompose into stacked labeled rows on narrow screens; desktop table behavior is preserved.
- Consistent status system and WCAG-conscious focus/reduced-motion behavior.
- Existing shared record-editor drawer and all POST endpoints remain intact.

## Deployment

This release has **no SQL migration**. Deploy the RC.1.1 hotfix over an exact RC.1 installation. A Supervisor restart is not required because only request-time PHP/view code and static admin assets changed. Hard refresh once so the RC.1.1 service worker/build asset version is active.

Verify: `/admin/`, `/admin`, `/admin/editions`, `/admin/processing`, `/admin/articles`, `/admin/navigation`, `/admin/master-data`, `/admin/settings`, `/admin/errors`, `/admin/performance`, `/admin/system/health`.

## Rollback

Restore the RC.1 application files. No database rollback is needed.


## Audited action surfaces

The shared action system is applied to Editions, Publications, Highlights, Articles, Authors, Topics, Entities, Master Data and Reader Navigation where actions are unambiguous. Lower-frequency or potentially ambiguous operations stay in the existing overflow menu. Operational/status-heavy pages inherit the shell, table, form, status and focus tokens without having their workflows rewritten.
