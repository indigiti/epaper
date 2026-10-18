# ePaper multilayer controller extraction (staging)

The web entry point remains `public_html/index.php` and the existing HTTP dispatch
is byte-preserved inside `App::run()`. The first extraction moves action methods
into eight **controller action traits** under `app/Controllers/`. Traits are an
**intermediate compatibility seam**, not independently instantiated MVC controllers.
No routes, paths, response bodies, permissions, database schema or deployment roots
are intentionally changed.

| Action seam | Responsibilities |
|---|---|
| PublishingActions | newsstand, publication, SEO/content, edition management |
| ReaderActions | page reader, tiles, edition navigation |
| ClippingActions | clipping public pages, clipping operations, guest claims |
| AdministrationActions | dashboard, content and organisation administration |
| ApiActions | API endpoints, auth hooks, readiness |
| AccountActions | subscription and reader account |
| OperationsActions | queues, diagnostics, recovery, infrastructure |
| SupportActions | common application helpers |

## Repository boundary

`Contracts/PublishingMetricsRepository.php` and
`Repositories/SqlPublishingMetricsRepository.php` extract the tenant-scoped
publishing metrics query from `PublishingRuntime`. A repository can be injected
into `PublishingRuntime::snapshot(true, $repository)` for deterministic testing;
the default runtime behavior retains the same SQL and cache TTL.

## Release gate

1. Run `php -l` on every changed PHP file and
   `php tests/layered-controller-contracts.php`.
2. Run existing security, route, publishing, Reader, clipping and tenant guards.
3. Exercise PHP/RabbitMQ processing and signed media in isolated staging.
4. Check route method/response parity and admin CSRF and tenant isolation.
5. Port into `cw-epaper` using that branch's **root-level** app paths, not by
   merging the monorepo directory move; keep Cloudways deployment unchanged
   until staging and operational security gates pass.

Next step: replace compatibility traits with injectable controller instances,
split large admin action groups further, and move domain SQL behind typed
repositories; no claim that this step is the final clean architecture.
