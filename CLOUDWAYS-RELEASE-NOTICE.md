# Cloudways Platform release snapshot — not deployed

This branch is a **staging candidate** derived from `platform/` on
`main` at commit `37c0710ebf90f35668dba5a133e671d1d03e860e`.

- Branch layout is intentionally rooted at application root, not repo root.
- Runtime configuration (config.php, generated runtime.php), install locks,
  caches, logs, PDFs, uploads and clipping/media state are NOT included.
- Git ancestry still includes historic tracked secrets; these branches
  are **not a replacement for credential rotation or Git history repair**.
- Before switching a live Cloudways app from `main`, migrate protected
  storage/config outside replaceable Git-managed paths and run an isolated
  staging checkout/rollback test. Git switching can delete tracked files.
- Verify nested webroot, cron, Supervisor and PHP bootstrap paths.
- Cloudways deployment was not triggered by creating this branch.
- Do not merge this deployment-layout branch back into `main`.

See root repo documentation and the deployment runbooks for app-specific
staging checks.
