# EP-DI v2.0.0-beta.1 Build Manifest

- Version: `2.0.0-beta.1`
- Base: `2.0.0-alpha.2.3`
- Database migration: **None**
- Runtime foundation: MariaDB + Redis + RabbitMQ + Supervisor retained
- New primary assets: `public_html/assets/admin-beta.css`, `public_html/assets/admin-beta.js`
- New console route: `/admin/plans`
- Updated experiences: Dashboard, Reader Navigation, Analytics, Error Center, Publisher Console shell
- Service worker build cache: `epdi-v2.0.0-beta.1`
- Rollback baseline: `2.0.0-alpha.2.3`

## Required release gates

- PHP syntax validation
- JavaScript syntax validation
- beta.1 build guard
- Supervisor runtime guard
- Schema Doctor static guard
- Audit UUID safety guard
- tenant SQL isolation guard
- Reader parity guard
- cumulative v2 guard
- ZIP integrity + SHA-256 manifest
