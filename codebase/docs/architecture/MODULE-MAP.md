# EP-DI application layers and ownership

This is a **source layout guide**. It does not claim production Cloudways, Platform, Redis or RabbitMQ availability.

```text
Browser / Android
  |
  +-- codebase/public_html/index.php
        |
        +-- app/bootstrap.php [EpaperSaas autoloader, config, security headers]
              |
              +-- app/App.php [routing and application controller]
                    |
                    +-- app/Core/Auth.php, Http.php, View.php
                    +-- app/Core/RequestContext.php [request / tenant]
                    +-- app/Services/DomainResolver.php
                    +-- app/Services/TenantData.php, ServiceAccessPolicy.php
                    +-- app/Services/{ClipService,OfflineVault,SemanticNews,ReaderNavigation}.php
                    +-- app/Services/{ProcessingPipeline,QueueService,PublishingScheduler}.php
                    +-- app/Core/{Store,Database,RedisStore,RateLimiter}.php
```

## Layer boundaries

| Layer | Current location | Contract |
|---|---|---|
| Public entry & assets | `public_html/` | Webroot, static files, service worker; never expose config/storage |
| Request/auth/HTTP | `app/Core/` | Role checks, CSRF, session cookies, cache headers, request validation |
| Tenant & publishing domain | `app/Services/` | Tenant-scoped DB access, editions, states, subscriptions, clips |
| Data/cache | `app/Core/Store.php`, `app/Services/MultiLayerCache.php` | Prepared SQL, Redis and fallback storage |
| Worker/outbox | `app/Services/QueueService.php`, `bin/` | Retry, jobs, supervisor, queue recovery |
| Processing | `app/Services/ProcessingPipeline.php` | PDF to WebP, OCR and deep zoom |
| Delivery | `app/Services/Media.php`, `public_html/assets/` | Signed media and dynamic Reader UI |
| Operations | `bin/`, `docs/operations/` | Diagnostics, cutovers and release gates |

## Refactoring constraint

The current bootstrap autoloader maps `EpaperSaas\Services\X` to `app/Services/X.php` directly. **Do not move PHP service files into nested directories without an explicit, separately tested namespace/autoload migration.** A source move can also change `__DIR__`-relative storage paths. This documentation reorganization deliberately leaves runtime paths untouched.

The 390 KB `app/App.php` is a separate future route-controller extraction project and should have a complete route inventory and regression coverage before it is split.
