# ePaper dual-branch source synchronization

**Source layouts:** The `main` branch historically kept the PHP ePaper application below `codebase/`. The Cloudways deploy branch `cw-epaper` kept the deployable PHP app at the repository root. Replacing one branch with the other's tree is **not** a valid merge.

## Required invariant
In each synchronized release, every tracked executable/application file inside the following directories MUST have an identical counterpart (same relative path and SHA-256 content) under both `/` and `/codebase/`:

`app/`, `public_html/`, `config/`, `bin/`, `database/`, `ai_processing/`.

The codebase suite and the Cloudways suite both invoke:
`python3 codebase/tests/branch-layout-parity.py`.

The same commit object must also be the head of **both** `main` and `cw-epaper`. Matching runtime files are necessary, but not sufficient: branch commits can still diverge.

## Safe release procedure

1. Freeze pushes to both branches while preparing the release and record their GitHub commit IDs.
2. Make a reconciliation branch containing the history of both prior heads (a **two-parent merge commit**). Merge Cloudways-only modules into `codebase/` and codebase-only runtime improvements into root; manually reconcile overlapping App/View/Reader code.
3. Run and pass BOTH codebase and Cloudways CI workflows against the **identical integration commit**. Resolve failures without muting security/authentication checks. Run the read-only layout parity and secret/source guards.
4. Verify production Cloudways deployment mode, persistent `storage/` and `public_html/uploads/`, server-only configuration, off-repo credentials, and staging backups. Compare before/after SHA-256 media inventory, test the ePaper reader, admin pages, jobs, OCR and manual clipping. Require separate confirmation for ePaper and Platform.
5. Only after real acceptance, fast-forward both branches with compare-and-swap expected SHA; **never use force**. Use the tested integration commit, not separate PR merge commits, because that would result in different SHAs. If either ref moves unexpectedly, stop and re-integrate.
6. Re-read both remote refs and assert they equal the approved commit. Verify Cloudways deployed artifact/commit and all smoke tests.
7. Coordinate credential rotation, Git-history purge and CDN/cache cleanup as a separate security incident workstream with its own approval and rollback. Current-tree Git hygiene does not sanitize historical blobs.

### Example branch equality check

```bash
git fetch --no-tags origin main cw-epaper
main=$(git rev-parse refs/remotes/origin/main)
cloudways=$(git rev-parse refs/remotes/origin/cw-epaper)
test "$main" = "$cloudways" || { echo "SYNC FAILURE: branch heads differ"; exit 1; }
python3 codebase/tests/branch-layout-parity.py
python3 codebase/tests/security-source-guard.py
```

Never publish runtime config or historic exposed tokens in this checklist. Current staged PRs are **not** a substitute for actual Cloudways acceptance.
