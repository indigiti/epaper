#!/usr/bin/env python3
"""Read-only Git-history purge input preparation for an OFFLINE mirror clone.

This tool never rewrites refs, changes a remote, or prints secret contents.
Review its output privately before invoking git-filter-repo.
"""
from __future__ import annotations
import argparse
import pathlib
import subprocess
import sys

parser = argparse.ArgumentParser()
parser.add_argument("--output", required=True, help="Private file for git-filter-repo --paths-from-file")
args = parser.parse_args()

repo = pathlib.Path(__file__).resolve().parents[2]
out = pathlib.Path(args.output).resolve()
if out.is_relative_to(repo):
    raise SystemExit("REFUSED: put the purge list outside the repository and webroot")
if out.exists():
    raise SystemExit("REFUSED: output already exists")
if subprocess.run(["git", "rev-parse", "--is-bare-repository"], cwd=repo, capture_output=True, text=True, check=True).stdout.strip() == "true":
    raise SystemExit("Run from a disposable working mirror checkout, not a live bare remote")
result = subprocess.run(["git", "log", "--all", "--name-only", "--format="], cwd=repo, capture_output=True, text=True, check=True)
paths = {line.strip() for line in result.stdout.splitlines() if line.strip()}
secret_files = {"codebase/config/config.php","codebase/config/runtime.php","platform/config/config.php","platform/config/runtime.php","codebase/storage/installed.lock","platform/storage/install.lock"}
exempt = {".keep",".gitkeep",".htaccess"}
purge = sorted(p for p in paths if (
    p in secret_files or
    (p.startswith(("codebase/storage/","platform/storage/")) and pathlib.PurePosixPath(p).name not in exempt) or
    p.startswith(("codebase/public_html/uploads/","platform/public_html/uploads/")) or
    (pathlib.PurePosixPath(p).name.startswith(".env") and not p.endswith((".example",".sample",".template")))
))
out.parent.mkdir(parents=True, exist_ok=True)
out.write_text("\n".join(purge)+"\n", encoding="utf-8")
out.chmod(0o600)
print(f"READ ONLY: prepared {len(purge)} historical paths for review.")
print("Do not run git-filter-repo or force-push until credential rotation, mirror backup, deploy freeze,")
print("branch/PR/fork inventory, storage preservation and approval are complete.")
print("After an authorized rewrite, validate every ref and coordinate fresh clones and cache removals.")
