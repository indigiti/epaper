# Dashboard-first acceptance for Cloudways ePaper

Primary UI: **/admin/system/testing**, accessible only after publisher
administrator authorization. From Publishing Desk select **Testing & QA**.

Click **Run safe checks**. The page records a tenant-scoped private JSON
snapshot under the application storage directory, showing:
- app-version and capture time; reports older than one hour or from another
  build are labelled **STALE**;
- MariaDB, Redis, Platform, RabbitMQ/processing and readiness checks;
- observed edition/page/clip/AI candidate record counts;
- explicit **NOT TESTED**, **DISABLED** and **UNAVAILABLE** distinctions for
  workflows that cannot be proven with non-mutating probes.

The page includes a link to its authenticated JSON export and to the relevant
admin modules. It **does not** execute Python, SSH commands, worker changes,
schema migrations, fake login, PDF uploads, editorial actions, tenant
suspension or backup restore. There is no change to the public API/schema.

## Project QA policy

Routine development should surface automated test results and actionable
failures in the admin dashboard. CI source tests still run automatically
on GitHub and are not replaced by health snapshots. Validated credentials,
browser role checks, destructive fixture tests and Cloudways restore
rehearsals are separate gated acceptance phases.

Use the admin dashboard first; request manual console diagnostics only when
the UI has identified an exceptional server-level problem that cannot be
safely diagnosed from the web. Never mark an unexercised workflow as passed.
