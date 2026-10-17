# Release history and source organization

## Modern navigation

Older version-specific documents previously filled the application root. The [complete old-to-new path index](LEGACY-ROOT-INDEX.md) records each move. Browse the [categorized archive](archive/README.md) or the untouched [original release-evidence directory](../../release/).

Some historical documents already existed in `release/` with exactly the same Git blob SHA; their redundant copies at the source root were removed. Unique older notes and backup copies of PHP/JS/CSS source are retained in `archive/` without being placed in executable directories.

The active, pinned documents needed by the existing source and build guards are deliberately kept in `codebase/`. Archived Git tags also retain the original historical file layout.

## Checksums and deployment scope

The unchanged `../../SHA256SUMS` lists paths and hashes for an **older full v2.0.2.1 release**. It is **not valid** for the proposed slim checkout; it was already incompatible when tracked runtime storage was removed in PR #17. Do not change historical checksum values to make them appear valid again.

A separate build tool, `../../bin/slim-release-zip.php`, creates an integrity-checked, source-only staging ZIP and intentionally excludes archived documentation, runtime storage and public uploads. It is **not** live Cloudways deployment approval.

Historical hardcoded links can point to former root filenames. Use the linked [legacy path index](LEGACY-ROOT-INDEX.md), the unmodified historical tags, or the old distribution package to reproduce a historical release.

## Before production

Do not merge or deploy this stacked cleanup until the existing security incident, credential rotation, history remediation, complete same-driver Cloudways staging rehearsal and rollback acceptance have been performed. The [staging promotion checklist](../operations/SLIM-RELEASE-CLOUDWAYS-PROMOTION-2026-10-17.md) is the operator runbook.
