# Historical release and checksum preservation

The application root currently contains versioned `UPDATE-*`, `BUILD-MANIFEST-*`, `RELEASE-CHECK-*`, `ROLLBACK-*`, and `INSTALL-*` files, often mirrored under `release/`.

These documents are **historical release evidence**, not new active developer documentation. **Do not bulk-move or delete them yet.**

Why: `SHA256SUMS` at application root lists their exact relative file paths and hashes; older upgrade instructions and release artifacts refer to those filenames. Moving them without a new release artifact and integrity migration would invalidate `sha256sum -c SHA256SUMS` path checks and potentially a deployment process.

## Follow-on archive migration (requires a new verified release)

1. Inventory which files and scripts reference each pinned document and historical release path.
2. Generate an immutable baseline copy of the old manifest and a separate versioned new checksum manifest for the NEW release; do not relabel an old historical checksum manifest as current.
3. Migrate historic notes to `docs/releases/archive/<version>/` only after updating all hard-coded references and validating the packaging toolchain.
4. Exercise a clean checkout, Composer dependencies, all release tests, Android packaging, worker invocations and full staging deployment before publishing the new artifact.
5. Preserve old release evidence in Git tags and release archives rather than mutating prior release history.

The current reorganization moves only **active, post-release security documentation not present in the historical SHA256SUMS**. It does not move PHP runtime modules, migration scripts, published PDFs, media, stored clipping files or pinned historical documents.
