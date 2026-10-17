#!/usr/bin/env python3
"""Inspect two isolated source trees; emit manifests, NOT deployable ZIPs.

This intentionally does not use production credentials or remove runtime data.
"""
from __future__ import annotations
import argparse
import hashlib
import json
import pathlib
import subprocess
import sys
from datetime import datetime, timezone


APPS = {
    "platform": {
        "directories": ("app/", "bin/", "public_html/", "database/"),
        "files": ("VERSION", "manifest.json", "config/config.example.php"),
    },
    "epaper": {
        "root": "codebase",
        "directories": ("app/", "bin/", "public_html/", "database/"),
        "files": ("VERSION", "config/config.example.php", "composer.json", "composer.lock"),
    },
}


def tracked_files(root: pathlib.Path) -> list[str]:
    raw = subprocess.check_output(["git", "-C", str(root), "ls-files", "-z"])
    return [p.decode("utf-8") for p in raw.split(b"\0") if p]


def inspect(root: pathlib.Path, paths: list[str]) -> tuple[dict, list[str]]:
    errors = []
    source: dict = {}
    for name, config in APPS.items():
        prefix = config.get("root", name) + "/"
        chosen = {}
        for tracked in paths:
            if not tracked.startswith(prefix):
                continue
            inner = tracked[len(prefix):]
            # Runtime data is never a deployable source, even if tracked by mistake.
            unsafe = (inner in ("config/config.php", "config/runtime.php", "storage/install.lock")
                      or inner.startswith(("storage/", "public_html/uploads/", "vendor/"))
                      or (inner.startswith("config/") and inner not in config["files"]))
            if unsafe:
                errors.append(f"{name}: forbidden tracked runtime file: {inner}")
            if not (any(inner.startswith(p) for p in config["directories"]) or inner in config["files"]):
                continue
            if unsafe:
                continue
            full = root / tracked
            if not full.is_file() or full.is_symlink():
                errors.append(f"{name}: missing or symlinked source file: {inner}")
                continue
            data = full.read_bytes()
            chosen[inner] = {"bytes": len(data), "sha256": hashlib.sha256(data).hexdigest()}
        for must_exist in ("app/bootstrap.php", "public_html/index.php", "config/config.example.php"):
            if must_exist not in chosen:
                errors.append(f"{name}: missing required source: {must_exist}")
        source[name] = {
            "source_root": prefix.rstrip("/"),
            "document_root": f"{prefix}public_html",
            "source_only_do_not_deploy": True,
            "file_count": len(chosen),
            "total_bytes": sum(row["bytes"] for row in chosen.values()),
            "files": dict(sorted(chosen.items())),
        }
    return source, errors


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--repo-root", default=".")
    parser.add_argument("--output-dir", required=True)
    args = parser.parse_args()
    root = pathlib.Path(args.repo_root).resolve()
    output = pathlib.Path(args.output_dir).resolve()
    try:
        inventory, errors = inspect(root, tracked_files(root))
    except (subprocess.CalledProcessError, OSError) as exc:
        print(f"FAIL unable to read repository source: {type(exc).__name__}", file=sys.stderr)
        return 2
    result = {"type": "isolated_source_candidate_inventory",
              "generated_at_utc": datetime.now(timezone.utc).isoformat(),
              "production_deployment_approved": False,
              "requires_private_config_and_restore": True,
              "components": {name: {k: v for k, v in content.items() if k != "files"} for name, content in inventory.items()},
              "errors": errors}
    if not errors:
        output.mkdir(parents=True, exist_ok=True)
        for name, content in inventory.items():
            (output / f"{name}-source-manifest.json").write_text(json.dumps(content, indent=2, sort_keys=True) + "\n")
        (output / "readiness-summary.json").write_text(json.dumps(result, indent=2, sort_keys=True) + "\n")
    print(json.dumps(result, indent=2, sort_keys=True))
    return 1 if errors else 0


if __name__ == "__main__":
    raise SystemExit(main())
