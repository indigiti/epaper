# Cloudways Deployment

## Application

Create a **Custom PHP** application for `platform.digiti.in` and upload this package into the application root.

Expected Cloudways structure:

```text
applications/<app>/
├── public_html/
├── app/
├── config/
├── database/
├── bin/
└── storage/
```

Do not place `config/`, `database/`, or `storage/` inside the public web root.

## MariaDB

Create/use a dedicated database for the Platform. Do not point Platform migrations at the City ePaper business database.

## Redis

Enable Redis at server level. If ACL credentials are used, enter both Redis username and password during install. The platform remains database-authoritative; Redis is used for cache/locks only.

## Domains

Attach:

```text
platform.digiti.in
auth.digiti.in
```

to this application initially if you want the control plane and auth surface to use the same runtime.

`domains.digiti.in` is an edge/CNAME target and can be routed through Cloudflare to the appropriate origin architecture.

## Cron

Cloudways Cron Job:

```text
* * * * * php /home/master/applications/<APP_ID>/bin/scheduler.php >/dev/null 2>&1
```

Use the actual Cloudways application path.

## Supervisor

Run:

```text
php /home/master/applications/<APP_ID>/bin/worker.php --loop
```

If Supervisor is not available in the application's management surface, run the worker through the supported Cloudways worker mechanism. Do not run multiple unmanaged workers without reviewing queue locking and resource capacity.

## Cache purge after releases

After an upgrade:

1. PHP OPcache/application cache purge as appropriate.
2. Varnish purge if enabled for this app.
3. Redis cache invalidation only for keys impacted by the release; do not flush the whole server if other applications share Redis.

## File permissions

`storage/` and `config/` must be writable during initial installation. After `config/config.php` is generated, restrict it to the application user and remove unnecessary write permissions where Cloudways permits.
