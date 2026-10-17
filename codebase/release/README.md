# EP-DI historical release evidence

This directory contains **426 original release-evidence files** from earlier product versions: manifests, QA logs, route snapshots, audit reports, rollback instructions and compatibility matrices. These files are deliberately retained at their original paths because some versioned guards and archival references rely on their filenames.

## Find files by prefix

| Prefix | What it records |
|---|---|
| `BUILD-MANIFEST-*` | Included files and historical package manifests |
| `RELEASE-CHECK-*`, `RELEASE-TEST-RESULTS-*`, `FINAL-TEST-LOG-*` | Release regression and test evidence |
| `PHP-LINT-*`, `SW-SYNTAX-*`, `JS-CHECK-*` | Historic syntax-validation outputs |
| `FINAL-REAUDIT-*`, `BUILD-GUARD-*` | Version acceptance and audits |
| `UPDATE-*`, `ROLLBACK-*`, `RELEASE-CHECKLIST-*` | Upgrade, rollback and deployment checklists |
| `CAPABILITIES-*`, `ROUTES-*`, `SETTINGS-MANIFEST-*` | Functional parity and routes |
| `READER-*`, `AUTH-BROWSER-*`, `CITY-EPAPER-COMPATIBILITY-*` | Reader, authentication and parity reference |

For the separately organized former root documents, use the [categorized archive](../docs/releases/archive/README.md) and [old-to-new path index](../docs/releases/LEGACY-ROOT-INDEX.md).

**Why this folder is flat:** moving these historical files requires a follow-up compatibility pass across legacy build guards, relative links and old release archives. The default application release ZIP does not include this history. This archive is not a current production verification record, and its old manifests are not a replacement for the new signed/hashed ZIP artifact verification.
