# EP-DI v2.0.0-rc.1 — Semantic News SEO + AI Discovery + E-E-A-T Trust Infrastructure

## Purpose

RC.1 turns the image/PDF-first ePaper product into a crawlable semantic publishing platform without weakening Reader performance, entitlement boundaries or the encrypted PWA Offline Vault. OCR can seed an editorial draft, but only human-reviewed articles can be published to semantic URLs and discovery feeds.

## Semantic publishing workflow

`/admin/articles` provides Draft → review → Publish / Unpublish. A draft may use an OCR page as source material. Headline, body, publication, authors, topics, entities, sources, correction notes and AI disclosures remain editorially controlled. At least one active author/newsroom desk is required before publication.

Public articles use `/article/{slug}` and include visible bylines, published/updated metadata, source-edition linkage, topic/entity context, source notes, correction notes and AI-use disclosures when applicable.

## Author / Topic / Entity pages

- `/admin/authors` → `/author/{slug}` unique author profiles with role, beat, bio, image and verified-profile URLs.
- `/admin/topics` → `/topic/{slug}` curated topic landing pages.
- `/admin/entities` → `/entity/{slug}` reusable Person/Organization/Place/Event concepts for related reporting.

All relationship writes validate tenant ownership.

## Structured discovery

RC.1 emits server-rendered semantic HTML and JSON-LD for NewsArticle, Organization, ProfilePage/Person and collection/entity pages. Articles support canonical URLs, language metadata, article Open Graph fields and `hreflang` alternates through a shared translation key.

Search/discovery documents:
- `/sitemap.xml`
- `/sitemap-static.xml`
- `/sitemap-articles.xml`
- `/sitemap-authors.xml`
- `/sitemap-topics.xml`
- `/sitemap-entities.xml`
- `/sitemap-editions.xml`
- `/news-sitemap.xml`
- `/robots.txt`

The News sitemap contains only published semantic articles from the most recent two days, capped at 1,000 URLs.

## Editorial Trust Center

Publisher Control adds `Editorial Trust`, covering Masthead, Editorial Standards, Corrections, Fact-checking, Ethics, AI-use, Advertising, Ownership and Newsroom Contact. Only completed policies are indexable; empty policy routes are `noindex` until the publisher supplies approved wording.

## AI discovery policy

RC.1 adds no fictional "AI schema" and does not mass-publish raw/generated content. AI-assisted workflow can be disclosed at article level and governed by the publisher AI-use policy. Search/AI discovery relies on crawlable, indexed, useful semantic pages, clear entities, authorship, source transparency and normal technical SEO fundamentals.

## Cache integration

SEO/discovery state uses the beta.2.1 `seo` generation. Article, author, topic, entity, SEO-setting and Trust-setting changes invalidate that revision and queue asynchronous sitemap/discovery warming through the existing transactional outbox. `bin/cache-maintenance.php --warm` now also warms SEO documents.

## Database upgrade

RC.1 requires one additive migration. Deploy files first, then open:

`/admin/system/upgrade`

Schema Doctor detects and creates:
- `ep_authors`
- `ep_topics`
- `ep_entities`
- `ep_articles`
- `ep_article_authors`
- `ep_article_topics`
- `ep_article_entities`

Do not manually run SQL unless the secured Upgrade Center is unavailable.

## Recommended deployment from beta.2.1

1. Take Cloudways application + MariaDB backup.
2. Extract the rc.1 hotfix over the application root.
3. Open `/admin/system/upgrade` and apply the detected rc.1 semantic schema.
4. Restart EP-DI Supervisor workers.
5. Hard refresh once so `epdi-v2.0.0-rc.1` activates.
6. Configure Publisher Control → SEO & discovery.
7. Configure Publisher Control → Editorial Trust.
8. Create an author/newsroom desk.
9. Create one test article from newsroom copy or OCR, review it, publish it, and validate its public page.
10. Verify `/news-sitemap.xml`, `/sitemap.xml`, `/robots.txt`, Reader, Reader OS and an encrypted offline edition.

## Production-content note

The release provides the infrastructure, not legal/editorial policy wording. Ownership, newsroom contacts, editorial standards, corrections, advertising and AI-use policies must contain publisher-approved factual content before they are made indexable.
