# EP-DI v2.0.0-rc.1 Build Manifest

- Version: `2.0.0-rc.1`
- Required base: `2.0.0-beta.2.1`
- Release type: semantic publishing / discovery release candidate
- Database migration: `database/upgrade-v2.0.0-rc.1.sql`
- Upgrade path: secured `/admin/system/upgrade` Schema Doctor
- Supervisor restart: recommended after deployment so Outbox SEO cache-warm handling is loaded
- Service Worker cache: `epdi-v2.0.0-rc.1`
- Offline model: beta.2 encrypted IndexedDB Offline Vault preserved

## New semantic infrastructure
- `app/Services/SemanticNews.php`
- `app/Services/SeoDiscovery.php`
- `database/upgrade-v2.0.0-rc.1.sql`
- `tests/build-guard-v2.0.0-rc.1.php`

## New durable semantic data
- Authors / newsroom desks
- Topics
- Entities (Person / Organization / Place / Event / Other)
- Human-reviewed Articles
- Article ↔ Author / Topic / Entity relationships

## Public discovery surfaces
- `/article/{slug}`
- `/author/{slug}`
- `/topic/{slug}`
- `/entity/{slug}`
- `/trust` and publisher-authored trust policies
- `/sitemap.xml` + static/article/author/topic/entity/edition sitemaps
- `/news-sitemap.xml`
- `/robots.txt`

## SEO / AI discovery controls
- SSR semantic article HTML
- `NewsArticle`, `Organization`, author `ProfilePage` and collection/entity JSON-LD
- Canonical URLs
- article Open Graph metadata
- language + `hreflang` translation alternates
- two-day Google News sitemap window
- human review boundary: OCR is source material, never auto-indexed as an article
- active author/byline required before Article publish
- AI-assistance disclosure per article plus publisher AI-use policy

## Trust infrastructure
- Masthead
- Editorial Standards
- Corrections Policy
- Fact-checking Policy
- Ethics Policy
- AI-use Policy
- Advertising / sponsored-content policy
- Ownership disclosure
- Newsroom contact

## Performance invariants
- Semantic pages/sitemaps use the beta.2.1 multi-layer cache and SEO revision generation.
- Article/trust changes invalidate SEO generation and queue asynchronous SEO cache warm.
- MariaDB remains source of truth.
- Redis + filesystem remain read accelerators.
- Encrypted Offline Vault and Reader runtime are unchanged.

## Release gates
- rc.1 build guard
- Offline Vault guard
- Supervisor runtime guard
- Schema Doctor + additive rc.1 migration guard
- Editions Operations / Processing Control regression
- Audit UUID safety
- tenant SQL isolation
- Reader parity
- cumulative v2 architecture guard
- PHP and JavaScript syntax validation
- package overlay verification against exact beta.2.1
- ZIP integrity and SHA-256 verification
