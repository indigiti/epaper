## Preferred path: existing staging ePaper, browser only

1. Open the existing authenticated ePaper **Admin → AI Clipping** page.
2. OWNER/ADMIN selects **Confirm existing staging installation**, enters the displayed current hostname and ePaper database name, and acknowledges current backup and staging-only use. No SQL, worker jobs or AI processing run in this step.
3. Refresh the same page, then use **Create candidate table** if missing. This is an additive schema change with a separate database-name and backup confirmation.
4. Use **Test Python and image detection**. It checks the configured interpreter using synthetic data, without installing packages or running against customer editions. If the Python interpreter is not provisioned, use Cloudways web management; the application cannot independently install OS packages.
5. Continue via **Worker Doctor**, **Processing Center** and **AI Editorial Review** once the worker and AI flag prerequisites are satisfied. Confirm that AI remains off until the actual RabbitMQ OCR/tenant security test passes.

# Cloudways AI Clipping — isolated staging activation procedure

This is a **server-operator procedure**, not a statement of completed Cloudways activation.
The `cw-epaper` source contains the AI candidate workflow, but GitHub cannot install
Cloudways Python packages, update the private runtime, or migrate the hosting database.

## Isolation and multilayer paths

- **Use the existing ePaper installation when it is the designated staging deployment.** No second Cloudways application is required. The owner confirms staging and the current hostname/database through the AI Clipping dashboard. Confirm that real production customer traffic/data are not on this installation; a staging declaration does not make shared production resources isolated.
- Keep `platform.digiti.in` and `cw-platform` unchanged.
- On the standalone `cw-epaper` branch, the **application root** contains `app/`, `bin/`, `database/`, `storage/`, `config/`, `public_html/`. The webroot is the nested `public_html/`, not the outer directory.
- An ePaper hostname such as `epaper.digiti.in` does **not** determine environment status. The dashboard permits this hostname only after explicit OWNER/ADMIN staging and backup confirmation, stored in private runtime config. Do not change `app.url`, application environment, database or Platform membership just to satisfy the wizard.
- Cloudways' app cloning may copy original URLs and credentials. Recheck both before applying any migration. Keep staging workers, cron and RabbitMQ routing separate from live queues. **Do not** run staging OCR workers against live broker queues.
- **Digiti Platform tenant boundary:** Keep the existing Platform connection and domain mapping unchanged for the designated staging deployment. Confirm that the mapped EPAPER tenant is used for testing; do not route test OCR activity into real customer operations. No change to `cw-platform` is needed. Platform fallback values by themselves do not prove whether the deployment is production or staging.
- If your existing staging domain already logs in and resolves tenants correctly, do not reconfigure OAuth or create a new domain. Preserve the working Platform integration.

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
