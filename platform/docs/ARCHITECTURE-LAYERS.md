# Platform multilayer extraction

The original `public_html/index.php` and `App::run()` routing remain
unchanged. Existing action methods are composed via three autoloaded controller
traits (`AdminActions`, `InternalApiActions`, `SupportActions`) as the first
compatibility-preserving separation. They are **not yet standalone MVC controllers**.

An `OrganisationReader` interface with the identical MariaDB SELECT adapter is
introduced and used by existing Admin CRUD organisation edit workflow.

No schema, HTTP route, authentication, service-scope, CSRF or role changes are
intended. Test with `php -l`, `php tests/layered-controller-contracts.php`,
the existing Platform guards and login/domain/tenant staging scenarios.
Only port source changes to `cw-platform` using its root-level layout after
verification. Continue splitting individual admin controllers and typed
repositories before declaring full layering complete.
