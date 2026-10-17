# EP-DI v2.0.1.4 Build Manifest

Baseline: delivered v2.0.1.3. No database or vendor dependency changes. New optional direct worker; all existing workers must restart. Runtime files changed:

- `app/App.php`
- `app/Core/View.php`
- `app/Services/DirectRecovery.php`
- `app/Services/EditionOperations.php`
- `app/Services/JobExecutor.php`
- `app/Services/ProcessingPipeline.php`
- `app/Services/ProcessingSummary.php`
- `app/Services/PublishingRuntime.php`
- `app/Services/PublishingScheduler.php`
- `app/Services/QueueService.php`
- `app/Services/RuntimeCache.php`
- `app/Services/TenantData.php`
- `bin/direct-recovery-worker.php`
- `bin/rabbit-worker.php`
- `bin/worker.php`
- `public_html/assets/admin-operations.css`
- `public_html/assets/app.js`
- `public_html/service-worker.js`

See PROCESSING-AUDIT-2.0.1.4.md and RELEASE-CHECK-2.0.1.4.txt.
