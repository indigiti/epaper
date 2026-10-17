# EP-DI v2.0.0-rc.1.1 — Admin UI/UX Audit

## Purpose

This release treats the deployed RC.1 application as the functional baseline. The audit is limited to the Publisher Operations Console presentation and interaction layer. Routes, database schema, queue topology, processing/publishing logic, permissions, semantic News/SEO logic, Reader OS and the encrypted Offline Vault remain functionally unchanged.

## Preserve

- Existing `/admin` routes, POST action endpoints and CSRF behavior.
- Existing role gates for Owner/Admin/Publisher Admin/Editor/Operator.
- Edition states, processing queue, reprocess recovery, scheduling and archive/restore behavior.
- Record-editor drawer and existing POST forms rather than replacing CRUD with JavaScript APIs.
- Publisher/tenant identity, light/dark preference, Diagnostics, command palette and request performance HUD.
- RC.1 semantic article/author/topic/entity workflows and Editorial Trust configuration.
- beta.2.1 cache/delivery architecture and beta.2 encrypted Offline Vault.

## Refine

- Reduce sidebar width/chrome and increase usable operational workspace.
- Tighten typography scale, spacing rhythm, borders, surface radius and elevation.
- Replace generic text action menus with a reusable icon-action pattern where the meaning is unambiguous.
- Make status pills semantically consistent: green success, blue active/info, amber wait/warning, red failure, grey neutral.
- Improve hover, focus, pressed and reduced-motion behavior.
- Recompose Editions into a mobile operational card/table hybrid rather than only shrinking the desktop table.

## Consolidate

- Group navigation by operator intent rather than implementation category.
- Consolidate record edit actions into the existing shared drawer and keep only uncommon actions in overflow.
- Consolidate dashboard primary metrics around processing, failures, schedule and published state.

## Reposition

- `Processing` moves into the primary **Publishing** navigation group.
- `Articles` and `Highlights` remain close to Editions in Publishing.
- `Authors`, `Topics`, `Entities` and `Reader Navigation` form **Discovery**.
- `Publications`, `Master data` and `Publisher Control` form **Publisher** configuration.
- Health, Error Center, Performance/Cache, Storage, Upgrade and Infrastructure form **Operations**.
- Roles, Audit and Release audit form **Governance**.

## Visually redesign

### `/admin`
Convert the dashboard from equal-weight widgets into a publishing desk. Primary numbers are Processing, Failed jobs, Scheduled and Published. Attention Center remains the highest-priority work surface.

### `/admin/editions`
Keep the current columns and workflows, but make the row task-oriented. Visible icon actions are View, Edit, context action (Process/Reprocess/Publish), and Archive/Restore. Cancel Schedule and lower-frequency actions remain in overflow. Archive remains reversible; Restore uses a distinct purple semantic action.

### CRUD-heavy admin pages
`Publications`, `Highlights`, `Articles`, `Authors`, `Topics`, `Entities` and `Master data` use the shared icon action cluster. Edit continues to open the existing record drawer. View, publish/status and delete actions remain the same POST/link targets as RC.1.

### Reader Navigation
Move up/down, enable/disable, delete and version restore become compact icon actions with accessible labels/tooltips. Draft/publish semantics are unchanged.

### Data/operations pages
`Processing`, `Subscribers`, `Plans`, `Error Center`, `Performance & Cache`, `Storage`, `System Health`, `Infrastructure`, `Audit`, `Access`, `Settings` retain their current data and controls. They inherit the quieter shell, table, status, form and surface tokens rather than receiving page-specific decorative treatments.

## Compatibility correction

`/admin/` now normalizes to the existing `/admin` dashboard handler. This is a compatibility correction for the trailing slash; it does not rename or remove `/admin`.

## Reusable action language

| Action | Treatment | Meaning |
|---|---|---|
| Create | primary / plus | create a new record or edition |
| View | blue eye | inspect/open |
| Edit | amber pencil | modify metadata/content |
| Publish/Process | blue/green contextual | active system operation |
| Delete | red trash | irreversible removal |
| Archive | red archive box | reversible removal from active publishing |
| Restore | purple history arrow | return archived/versioned content |
| More | neutral ellipsis | uncommon/contextual actions |

Every icon-only action retains `title` plus an accessible label, visible focus state and practical touch target.
