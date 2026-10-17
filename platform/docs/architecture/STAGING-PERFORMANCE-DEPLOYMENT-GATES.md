# Digiti Platform + ePaper — staging performance and deployment gates
Date: 17 October 2026 | Status: **SOURCE / DISPOSABLE-CI VALIDATION ONLY — NO PRODUCTION DEPLOYMENT**

## Evidence classification

**Gate A — executable source/CI (automated):** PHP syntax, Platform policies and MariaDB transactions, ePaper tests, actual signed invalidation HTTP receiver with isolated fixture database, timestamp/signature/tamper/replay checks, safe load-harness unit tests, source-only SHA256 manifests. Evidence: GitHub Actions on Draft PR #23.

**Gate B — real isolated Cloudways staging (requires an operator):** two independent Cloudways applications, two MariaDB schemas, private runtime settings, separate persistent storage, restored Platform DB and ePaper DB/media, a staging-only HMAC key and staging hostname, PHP-FPM/CLI workers, WAF and real Redis. No connector to the user's Cloudways account is present in this chat; Gate B has not been exercised.

**Gate C — production readiness (must be approved separately):** credential/key rotation + historical compromise remediation; independent backup/restores; verified ePaper PDFs/clippings/Reader; billing provider sandboxes; DigiOps packaging and rollback; Gate B latency, worker crash and CDN/browser behavior; operator signoff. A passing Gate A never automatically satisfies B or C.

## Current high-risk repository findings

- Previous commits tracked ePaper `config/config.php` and `config/runtime.php`. Both were removed **only from the draft branch tip**. Prior Git history remains sensitive and credentials must be rotated/revoked, followed by coordinated all-ref history remediation.
- The draft has also untracked 257 generated ePaper cache files. However, other ePaper storage/media files, some uploaded branding assets, and a vendored dependency directory remain tracked. **Do not pull this draft into a live Cloudways checkout:** Git may delete formerly tracked configuration/cache paths and source cleanup can affect application data.
- `tools/release_source_preflight.py` excludes tracked runtime files and vendor content from candidate inventories and records the excluded-file counts. It produces SOURCE-ONLY manifests with SHA256 hashes, **not** tested/ready deployment ZIPs. Dependencies must be reproducibly installed from the pinned Composer lock in a separately tested packaging process.
- Both projects must have private settings mounted or installed outside Git replacement targets before any deployment. Never put Cloudways backup ZIPs, media archives or credentials into this repository.

## Gate A — reproducible CI

The separate `.github/workflows/staging-acceptance-ci.yml`:
1. Runs `python3 -m unittest discover -s tools -p 'test_staging_http_benchmark.py' -v` with no network.
2. Checks runtime private config and installation locks are absent in the *source checkout*.
3. Builds separate source-only SHA256 manifests, checking `platform/public_html` and `codebase/public_html` are different roots.
4. Starts a disposable MariaDB and a loopback ePaper PHP server; validates live HTTP signed receiver ACK, duplicate/replay/tamper/expired signature rejection, and one durable receipt.

The existing two workflows still run Platform PHP/MariaDB and ePaper archive/Reader regressions. Do not reuse CI DB passwords, fixture secrets or generated private files outside the disposable runner.

## Gate B — isolated Cloudways staging procedure (read-only first)

1. Restore separately: **Platform application + MariaDB** and **ePaper application + MariaDB + PDFs/clips/uploads** to *different* Cloudways staging app IDs and database names. Compare row counts, media file counts/checksums, and verify one Reader edition/PDF/clipping and user account. An ePaper backup is not a verified Platform restore.
2. Pin staging source to an reviewed immutable commit of Draft PR #23, but **do not directly git pull runtime deletions onto the existing live checkout**. Build a separate clean, source-only staging tree, install Composer dependencies in the staging release, and mount private configs/storage.
3. Configure staging-only private keys: Platform `integrations.epaper_invalidation_url` points to exactly the staging receiver `https://<EPAPER-STAGING-HOST>/internal/v1/platform/cache-invalidate`; Platform `integrations.epaper_invalidation_secret` and ePaper `platform.invalidation_secret` must be the **same unique 32+ byte staging secret**, unrelated to app/OAuth/payment keys. Do not log the secret.
4. Verify DNS and SSL, WAF allows correctly signed server-to-server POST but rejects unsigned, PHP-FPM and CLI/worker can read both private configs, cron/scheduler/Redis work with their own staging namespaces. Never disable global ModSecurity as a workaround.
5. Run read-only Platform preflight in isolated staging (not production):

```bash
export EPDI_STAGING_READINESS=allow
export EPDI_EXPECTED_PLATFORM_STAGING_HOST=<platform-staging.example.org>
export EPDI_EXPECTED_EPAPER_STAGING_HOST=<epaper-staging.example.org>
export EPDI_EXPECTED_PLATFORM_STAGING_DB=<platform_staging_schema>
export EPDI_EXPECTED_EPAPER_STAGING_DB=<epaper_staging_schema>
php platform/bin/staging-readiness.php
```

The staging readiness command is strictly opt-in and verifies HTTPS hostnames, separate DB names, required Platform tables, Redis connectivity and secret presence. It never prints HMAC secret material and never claims backup, restore or deployment approval.

6. Run the signed ACK probe against a **staging EPAPER tenant** and confirm every delivery is acknowledged after the worker processes it:

```bash
EPDI_STAGING_INVALIDATION=allow php platform/bin/staging-invalidation-probe.php \
  --tenant=<STAGING_EPAPER_TENANT_UUID> --samples=10 --timeout=30
```

7. Run read-only HTTP performance baselines **from a trusted build runner/workstation** for each staging host (the repo-level `tools/` files are not part of the app source release):

```bash
python3 tools/staging_http_benchmark.py \
  --base-url https://platform-staging.example.org \
  --expected-host platform-staging.example.org \
  --confirm-isolated-staging \
  --paths /health/live,/health/ready \
  --samples 100 --concurrency 5 --output platform-staging-perf.json

python3 tools/staging_http_benchmark.py \
  --base-url https://epaper-staging.example.org \
  --expected-host epaper-staging.example.org \
  --confirm-isolated-staging \
  --paths /health/live,/health/ready,/,/archive \
  --samples 100 --concurrency 5 --output epaper-staging-perf.json
```

A provisional acceptance gate is **HTTP errors ≤1% and p95 ≤1500 ms per read-only endpoint** under these *small* synthetic loads; final targets require production-like dataset and hardware. Record p50/p95/p99, bytes and error rate for cold/warm cache, before/after equivalent builds. Never report unmeasured speed gains.

8. Test two tenants: create isolated staging records and exercise ACTIVE→SUSPENDED, subscription expired, removed domain and recovered tenant. Verify tenant B remains accessible, and that ePaper caches are invalidated when the worker ACKs. Check user login/session/Reader behavior separately.
9. Inject failures **only into isolated staging**: ePaper network unavailable, Redis unavailable, worker killed mid-event, DB down; verify retry/dead-letter handling, no false successful ACK, signed replay rejection, no cross-tenant access. Review app logs without sensitive tokens.
10. Inspect service worker/browser/CDN caching and signed-media expiry after suspension. Origin invalidation is not a CDN purge; this gate is not automatically met by 10s/30s TTL settings.

## Gate C — release and rollback

Only after Gate B evidence is complete:
- Capture immutable source commit, separate source SHA256 manifests, migration versions, Composer lock/vendor build hash, runner/PHP extensions, application IDs, schema names, backup timestamps/references and two independent isolated restore proofs.
- Operator signs off security rotations/history remediation and secret placement. Real payment providers and webhooks must be separately exercised in sandbox.
- Build **independent** Platform/ePaper release artifacts with private config, databases, media and uploads explicitly excluded. Stage a full deploy + rollback with file hashes, queue drain/restart and worker Supervisor commands. Do not use direct Git source deletions on live state.
- Recheck login, admin, PDF upload/processing, clipping, Reader, authenticated tenant suspension and CDN/service worker behavior post-rollback. Verify no divergence between MariaDB rows and media hashes.
- No auto-merge, auto-deploy, production DB writes or Cloudways commands are included in these checks.

## Completion ledger (update only with observed evidence)

| Acceptance | Current |
|---|---|
| Source/CI PHP, MariaDB, signed receiver | GitHub workflow results, see PR #23 |
| Read-only benchmark harness | Implemented; no live Cloudways measurements |
| Platform staging config/DB/Redis preflight | NOT RUN — isolated staging connection required |
| Platform separate database backup + isolated restore | NOT VERIFIED |
| ePaper database/media restore | NOT VERIFIED in this milestone |
| Real worker HMAC/WAF/Redis delivery | NOT VERIFIED |
| CDN/browser/session/signed media revocation | NOT VERIFIED |
| Private configuration, credential rotation, Git history cleanup | NOT VERIFIED |
| Two-app DigiOps deploy + rollback acceptance | NOT VERIFIED |
| Production promotion | BLOCKED |

See issues #2, #21 and #22. Preserve staging evidence outside public GitHub issues if it contains private domain identifiers, user information or backup paths.
