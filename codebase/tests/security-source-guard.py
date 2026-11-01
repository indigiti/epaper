#!/usr/bin/env python3
"""Fail closed if secrets or customer newspaper data are present in the current Git tree.

This deliberately reports paths/counts only; it never prints secret values.
Git history must be checked and rewritten separately after coordinated rotation.
"""
from __future__ import annotations
import pathlib
import subprocess
import sys

root = pathlib.Path(__file__).resolve().parents[2]
tracked = subprocess.check_output(["git", "ls-files", "-z"], cwd=root).decode("utf-8", "replace").split("\0")
tracked = [p.replace("\\", "/") for p in tracked if p]

secrets = {
    "codebase/config/config.php",
    "codebase/config/runtime.php",
    "codebase/storage/installed.lock",
}
exceptions = {".keep", ".gitkeep", ".htaccess"}
violations = []
for path in tracked:
    name = pathlib.PurePosixPath(path).name.lower()
    if path in secrets or (name.startswith(".env") and not name.endswith((".example", ".sample", ".template"))):
        violations.append(("secret/runtime config", path))
    elif path.startswith("codebase/storage/") and name not in exceptions:
        violations.append(("customer/generated content", path))

# Include filenames only in a deliberately bounded count; don't print Git blob content.
if violations:
    from collections import Counter
    counts = Counter(kind for kind, _ in violations)
    for kind, count in sorted(counts.items()):
        print(f"SECURITY BLOCK: {count} tracked {kind} file(s)", file=sys.stderr)
    print("Rotate secrets, preserve server storage, untrack files, and coordinate history rewrite.", file=sys.stderr)
    print("Deploying an unsafe source cleanup may DELETE live newspaper content.", file=sys.stderr)
    sys.exit(1)
print("Current Git tree contains no tracked runtime secrets or stored newspaper assets.")
