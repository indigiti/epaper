# Cloudways AI Clipping — isolated staging activation procedure

This is a **server-operator procedure**, not a statement of completed Cloudways activation.
The `cw-epaper` source contains the AI candidate workflow, but GitHub cannot install
Cloudways Python packages, update the private runtime, or migrate the hosting database.

## Isolation and multilayer paths

- Clone/create an **isolated staging ePaper application** with its own MariaDB database and application domain. See Cloudways Staging Management.
- Keep `platform.digiti.in` and `cw-platform` unchanged.
- On the standalone `cw-epaper` branch, the **application root** contains `app/`, `bin/`, `database/`, `storage/`, `config/`, `public_html/`. The webroot is the nested `public_html/`, not the outer directory.
- Do **not** use the canonical `epaper.digiti.in` hostname for staging. Update private `app.url` and `app.environment` to `staging` and ensure the staging database connection points to its own database.
- Cloudways' app cloning may copy original URLs and credentials. Recheck both before applying any migration. Keep staging workers, cron and RabbitMQ routing separate from live queues. **Do not** run staging OCR workers against live broker queues.
- **Digiti Platform tenant boundary:** `DomainResolver` permits a canonical-host fallback through `platform.default_tenant_guid`. On staging, clear both `platform.default_tenant_guid` and `platform.default_organisation_guid` in the private effective configuration. Register the distinct staging hostname with a dedicated test EPAPER product tenant, or use a separate staging Platform. Ensure that the staged service token and OAuth redirect URI are scoped to the staging identity. Do not use a live production tenant or allow staging workers to report usage to it. No source-code change to `cw-platform` is needed.
- If the staging hostname has not been registered with Platform and approved for OAuth, stop before authenticated web testing: the HTTP dispatcher may reject domain resolution. Do not bypass this by copying production tenant fallback values.

## 1. Safe preflight (read-only)

From the **staging ePaper application root** (the outer public_html folder):

```sh
php bin/ai-clipping-staging.php --check
php bin/supervisor-preflight.php --json
```

Both commands may report warnings. The AI CLI preflight does not print database credentials and does not enable AI or mutate schema. Inspect environment, staging_guard, platform_fallback_disabled, database_selected, candidate_schema, source_modules and python_binary.
A Git pull into Cloudways updates source only; it does not enable AI.

## 2. Stage Python privately

Use an available **Python 3.12** binary under your Cloudways SSH user; do not assume it exists. Install the virtualenv under the private application `storage/` tree so it is outside the nested public webroot. If Python 3.12 is unavailable, request provisioning on the staging server rather than switching production packages blindly.

```sh
python3.12 -m venv storage/private/ai-venv
storage/private/ai-venv/bin/python -m pip install -r ai_processing/requirements-image.txt
storage/private/ai-venv/bin/python bin/ai-clipping-smoke.py
```

Only proceed if the synthetic OpenCV/candidate smoke passes. This does not prove real newspaper image precision.

## 3. Apply the additive schema only to isolated staging

Obtain a **staging database and media backup** and verify its restore plan first.
Export confirmations interactively, using the exact staging URL hostname and
**independent staging database name** from the private `config/config.php`:

```sh
export EPDI_AI_STAGING_HOST='YOUR-STAGING-HOST.example'
export EPDI_AI_STAGING_DB_NAME='YOUR_STAGING_DATABASE_NAME'
export EPDI_AI_BACKUP_VERIFIED='yes'
php bin/ai-clipping-staging.php --apply-migration
unset EPDI_AI_STAGING_HOST EPDI_AI_STAGING_DB_NAME EPDI_AI_BACKUP_VERIFIED
```

The script denies schema changes if `app.environment` is not `staging`,
if the canonical live host or any default Platform tenant fallback is configured, or if host/database/backup
confirmations do not match. It permits only the existing add-only
`CREATE TABLE IF NOT EXISTS ep_ai_page_candidates` SQL. It never drops or edits existing candidate rows.

## 4. Configure the private runtime and staging workers

After completing the previous gates, edit the **private** staging `config/runtime.php`:
merge this value into its existing returned array, preserving other runtime settings:

```php
'ai_clipping' => [
    'enabled' => true,
    'python_binary' => '/ABSOLUTE/STAGING/APP/ROOT/storage/private/ai-venv/bin/python',
    'timeout_seconds' => 30,
],
```

If `runtime.php` is absent, create it with `<?php return [ ... ];` and appropriate owner-only permissions. Do **not** commit this runtime file.
The environment flag must remain staging. Restart **staging** Supervisor OCR workers so CLI PHP loads the new config, without touching the production worker programs.

Use the existing staging infrastructure admin `/admin/system/infrastructure/supervisor` to verify worker health; never share or reuse live queue credentials.

## 5. End-to-end staging acceptance

- With a disposable staging tenant and new test edition, process a PDF via the RabbitMQ OCR lane. Existing Tesseract `ep_page_ocr` must populate and the new `ep_ai_page_candidates` table must receive a `PENDING` page candidate.
- Check `/admin/ai-clipping` reports enabled/schema available and `/admin/ai-review` displays editable region boxes.
- Verify CSRF, role denial, cross-tenant candidate/image denial, duplicate OCR delivery, reprocess preservation, and the approved/rejected states. A `REVIEWED` item must **not** publish an article or create a public clipping.
- Recheck the public Reader, manual clipping, publishing, Redis fallback and all original queue workers. Compare performance before/after with disposable editions.

## Rollback

Disable `ai_clipping.enabled` in the staging private config; restart the **staging** OCR workers. Keep the new additive table/data for inspection. Never push a complete staging database over the live ePaper database.

## Limits

Neither `--check` nor the synthetic Python smoke validates authenticated Cloudways sessions, real OCR accuracy, real worker credentials or live deployment. Staging and production must have separate DBs, queue names/vhosts or brokers, Redis namespaces and media before running worker acceptance.
