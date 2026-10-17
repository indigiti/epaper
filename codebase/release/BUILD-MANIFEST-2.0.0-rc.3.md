# Build Manifest — EP-DI v2.0.0-rc.3

Release: **Final Acceptance & UX/Defect Polish**  
Baseline: **EP-DI v2.0.0-rc.2**

## Release intent
RC.3 is the final pre-stable acceptance release. It introduces no new product architecture or business workflow. It fixes verified presentation/interaction defects in the packaged RC.2 application and makes the final stable-promotion gate explicit.

## Changed runtime files
- `VERSION`
- `app/App.php`
- `app/Services/ProductionReadiness.php`
- `public_html/assets/admin-beta.css`
- `public_html/assets/admin-beta.js`
- `public_html/assets/crud.js`
- `public_html/assets/app.css`
- `public_html/service-worker.js`
- `public_html/offline.html`
- `public_html/offline-library.html`
- `public_html/offline-reader.html`

## Acceptance / interaction fixes
- Release Audit now requires automated Production Readiness to be fully `READY`; `DEGRADED` is no longer treated as launch approval.
- Added a browser-local manual acceptance matrix for backup/restore, publishing, Reader, encrypted offline, public Newsstand/Archive, Publisher Console, SEO/discovery and recovery/observability.
- Manual checklist state is convenience-only and is explicitly not a server-side approval record.
- Fixed reusable CRUD `••• More` action preservation: collapsed popovers are no longer mistaken for having no remaining actions because of zero layout rectangles.
- Form submissions expose `aria-busy`, disable the active submitter and prevent accidental duplicate operations during navigation.
- Raised minimum operational metadata/status legibility and mobile icon touch targets in the Publisher Console.
- Publication landing-page cover images now include descriptive alt text, async decoding and known thumbnail dimensions when available.
- Production Readiness now validates the build manifest matching the current VERSION dynamically instead of relying on a hard-coded RC.2 filename.

## Added release tests
- `tests/build-guard-v2.0.0-rc.3.php`
- `tests/final-acceptance-static.php`

## Documentation
- `FINAL-ACCEPTANCE-MATRIX-2.0.0-rc.3.md`
- `UPDATE-2.0.0-rc.3.md`
- `BUILD-MANIFEST-2.0.0-rc.3.md`
- `ROLLBACK-2.0.0-rc.3.md`
- `V2-BLUEPRINT.md`
- `CHANGELOG.md`

## Database
No schema change. No `database/upgrade-v2.0.0-rc.3.sql` exists.

## Worker topology
No RabbitMQ/Supervisor worker implementation or lane-count change. A Supervisor restart is not required solely for RC.3.

## Route contract
RC.3 adds no application route. Existing RC.2 routes are preserved.

## Stable promotion rule
Do not call v2.0.0 stable merely because the packaged release gate passes. Stable promotion requires:
1. automated Production Readiness = `READY`;
2. packaged release gate = PASS;
3. every manual acceptance item completed on the deployed environment;
4. any acceptance defect either resolved in RC.3 or explicitly accepted/documented.
