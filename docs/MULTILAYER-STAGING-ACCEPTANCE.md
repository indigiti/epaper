# ePaper Cloudways multilayer — staging acceptance

Candidate: PR #31, based on cw-epaper. This is NOT production authorization.

## Automated evidence
- PHP 8.3 syntax, reflection method contracts and injected-controller unit tests.
- AI editorial source guards, security-hardening static assertions and tenant
  SQL guard extended to ep_ai_page_candidates.
- Ephemeral PHP server /health/live JSON smoke with example-only CI config.
- These do **not** validate Cloudways, authenticated publishing or RabbitMQ.

## Prerequisites (isolated Cloudways STAGING only)
- Isolated staging app and hostname, MariaDB clone, Redis namespace,
  RabbitMQ vhost/queues, protected uploads and media storage.
- Staging Platform mapping and a disposable publisher-admin/editor account.
- Backup, restore evidence, secrets in Cloudways private config, AI processing opt-in.
- Verify the deployed Git SHA against this PR's reviewed commit.

## Must-pass authenticated checks
- Login/logout and CSRF rejection, OWNER/ADMIN/EDITOR/viewer role matrix.
- Publish new PDF; check per-page render, WebP tiles, thumbnails, OCR and queue
  retries; stop/restart RabbitMQ, confirm retry/recovery and no duplicate pages.
- Reorder draft page sequence, publish, archive/restore and guarded remove;
  confirm audit trail and that other tenants cannot affect the edition.
- Reader navigation, zoom, archive, date selection, mobile/offline and signed
  media URLs. Test expired token denial and revalidation.
- Private/public clipping, share/download, crop bounds, guest claim and
  tenant-scoped clip permission checks.
- AI candidates from hOCR/image regions; editorial edit/approve/reject;
  verify disabling AI does not affect primary PDF publishing.
- Suspend tenant in Platform and confirm cache invalidation + reader/access denial.
- Compare edition/page/clip row counts, storage hash/media integrity and visual
  screenshots against pre-refactor baseline.
- Test rollback from Cloudways backup on isolated staging.

## Promotion rule
Do not merge PR #31 or pull cw-epaper to Cloudways until ALL checks above
are evidenced, operational security/credential rotation is completed, and a
named reviewer records approval. Never run destructive test data operations on
production. CI green is necessary but NOT sufficient.
