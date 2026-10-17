# EP-DI v2.0.1.3 Build Manifest

Baseline: supplied v2.0.1.2 cumulative package. Release: 2.0.1.3.
No schema migration. Existing vendor PHP dependencies and service topology carried forward unchanged. Restart all workers after installing.

Changed runtime files:

- `VERSION`
- `public_html/service-worker.js`
- `app/App.php`
- `bin/rabbit-worker.php`
- `bin/worker.php`
- `public_html/assets/admin-operations.css`
- `public_html/assets/admin-beta.js`
- `public_html/assets/app.js`
- `app/Core/View.php`
- `app/Services/QueueService.php`
- `app/Services/Processing.php`
- `app/Services/RuntimeCache.php`
- `app/Services/EditionOperations.php`
- `app/Services/PublishingRuntime.php`
- `app/Services/CancelledJobException.php`
- `app/Services/PublishingScheduler.php`
- `app/Services/TenantData.php`
- `app/Services/ProcessingPipeline.php`

Validation limits are recorded in RELEASE-CHECK-2.0.1.3.txt. This package has not been deployed to the live site.
