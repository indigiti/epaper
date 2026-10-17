# Browser-only Platform and ePaper QA

Updated: 18 October 2026. This runbook does **not** authorize production writes.

## Run the automated web checks

1. Open indigiti/epaper → **Actions** → **Web-only Production QA (read-only)**.
2. Open the existing workflow run (for example, https://github.com/indigiti/epaper/actions/runs/39062822155), choose **Re-run jobs → Re-run all jobs**. GitHub's Run workflow button normally requires the workflow to exist on the default branch, so it may not be shown for this draft-only workflow.
3. Inspect the job summary and download the digiti-web-only-diagnostics JSON artifact.
4. A failed workflow is intentional when a homepage or core health endpoint returns HTTP 500. Do not interpret a green ePaper health endpoint as proof that Reader/archive is functional.

The workflow accesses only platform.digiti.in and epaper.digiti.in, runs one GET per approved public route and one HEAD per fixed private-path check, validates TLS expiry, never follows redirects, and never submits credentials, installation, mutation or destructive requests. It saves no private response body.

## Read-only browser acceptance

| Scope | How to test from browser | Live vs staging |
| --- | --- | --- |
| HTTPS/TLS, redirects, basic reachability | Automated GitHub workflow and opening the URLs | Live-safe |
| Readiness/Platform dependency | /health/live, /health/ready, plus actual public pages | Live-safe; readiness is not end-to-end certification |
| Public home/archive/search/Reader | Browse currently published public resources (no uploads) | Live-safe |
| Admin login | Open /login and verify correct login page | Live page check; actual login requires own credentials |
| Admin domain CRUD, tenant suspend/reactivate, renewal, invoices | Browser UI using dedicated fixture tenant | **Isolated staging only**, not live |
| Platform to ePaper invalidation, cache and sessions | Authenticated staging admin; suspend test tenant and verify ePaper denial/recovery | **Isolated staging only** |
| PDF upload/render/OCR/clipping | Dedicated synthetic edition in staging Publisher and Reader | **Isolated staging only** |
| Queue/Redis/worker/crash/failover | Authenticated staging operations plus server instrumentation | Cannot be proven by public HTTP alone |
| DB/media restore and checksum | Controlled backup/restore and private integrity tools | Cannot be proven by public HTTP alone |

## Platform HTTP 500 from missing runtime configuration

The app/Core/Config.php loader requires the existing private file at the outer application/source root config/config.php. Git intentionally excludes this file. A successful Git pull does not create it. **Do not** click /install.php, reinitialize a database, remove locks or copy config/config.example.php as a substitute for the original credentials and encryption key.

A browser-only recovery path exists **if** there is a verified Cloudways Web Files backup containing the original configuration:

1. Cloudways Flexible → Server → Application → **Backup and Restore**.
2. Inspect backup timestamps (restore points are UTC) and select a verified point before the missing-file event.
3. Recover the original single config file through an authorized private file-management method if available. Cloudways **Web Files** partial restore is an alternative available in the Cloudways web dashboard, but it replaces application web files, not just config.php. Verify other deployment changes before proceeding; do not restore the Database for this missing-file symptom.
4. Recheck Platform /health/live, /health/ready, /login, then ePaper /archive and authenticated tenant/Reader flows.

Cloudways official documentation: https://support.cloudways.com/en/articles/5123320-how-to-do-a-point-in-time-restore-of-your-application

If no backup contains the original config, public HTTP testing cannot recreate the old app key or database credentials. Use authorized Cloudways support or private file recovery. Never commit or paste private configuration.

## Reporting rules

- Report exact hostname, endpoint, UTC timestamp, HTTP status and workflow/commit. Source CI green is not deployed-version proof.
- HEAD 2xx on private paths needs manual review, not automatic proof of disclosure; no GET secret reads.
- Do not mark ePaper fully healthy when its /archive or Reader fails despite 200 health.
- Keep main and existing Cloudways-connected cw-platform and cw-epaper unchanged until recovery and staging approval.
