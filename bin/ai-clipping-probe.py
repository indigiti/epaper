#!/usr/bin/env python3
"""Bounded, privacy-safe Cloudways interpreter diagnosis for AI Clipping.

No network, installation, account data, database, or external commands.
Only known fixed error codes are written to stdout. Invoked by a privileged
web action using the server-selected Python interpreter.
"""
from __future__ import annotations

import os
import re
import subprocess
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PREFIX = "EPDI_AI_PROBE:"
SMOKE_PREFIX = "EPDI_AI_SMOKE_CODE:"
ALLOWED_SMOKE = {
    "TEMP_WORKSPACE_FAILED": "TEMP_STORAGE_UNAVAILABLE",
    "IMAGE_GENERATION_FAILED": "IMAGE_FIXTURE_FAILED",
    "SOURCE_JSON_FAILED": "TEMP_STORAGE_UNAVAILABLE",
    "CANDIDATE_CLI_FAILED": "CANDIDATE_GENERATOR_FAILED",
    "OUTPUT_CONTRACT_FAILED": "CANDIDATE_CONTRACT_FAILED",
}


def result(code: str) -> int:
    print(PREFIX + code)
    return 0 if code == "PASS" else 2


def main() -> int:
    if not ((3, 9) <= sys.version_info[:2] <= (3, 12)):
        return result("PYTHON_VERSION_UNSUPPORTED")

    try:
        import numpy as np
    except ModuleNotFoundError as exc:
        return result("NUMPY_MISSING" if exc.name == "numpy" else "NUMPY_IMPORT_FAILED")
    except Exception:
        return result("NUMPY_IMPORT_FAILED")

    try:
        import cv2
    except ModuleNotFoundError as exc:
        return result("OPENCV_MISSING" if exc.name == "cv2" else "OPENCV_IMPORT_FAILED")
    except Exception:
        return result("OPENCV_IMPORT_FAILED")

    try:
        with tempfile.TemporaryDirectory(prefix="epdi-ai-probe-") as temporary:
            f = Path(temporary) / "probe"
            f.write_bytes(b"ok")
            if f.read_bytes() != b"ok":
                return result("TEMP_STORAGE_UNAVAILABLE")
    except OSError:
        return result("TEMP_STORAGE_UNAVAILABLE")

    smoke = ROOT / "bin" / "ai-clipping-smoke.py"
    if not smoke.is_file():
        return result("SMOKE_TEST_UNAVAILABLE")

    try:
        completed = subprocess.run(
            [sys.executable, str(smoke)],
            cwd=str(ROOT),
            stdin=subprocess.DEVNULL,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            timeout=12,
            check=False,
            env={**os.environ, "PYTHONDONTWRITEBYTECODE": "1"},
        )
    except subprocess.TimeoutExpired:
        return result("CANDIDATE_TEST_TIMED_OUT")
    except OSError:
        return result("CANDIDATE_PROCESS_UNAVAILABLE")

    if completed.returncode == 0:
        return result("PASS")

    # Never relay arbitrary stdout/stderr, tracebacks, document content or paths.
    stderr = completed.stderr[-512:].decode("utf-8", "replace")
    found = re.search(r"EPDI_AI_SMOKE_CODE:([A-Z_]+)", stderr)
    if found:
        return result(ALLOWED_SMOKE.get(found.group(1), "CANDIDATE_GENERATOR_FAILED"))
    return result("CANDIDATE_GENERATOR_FAILED")


if __name__ == "__main__":
    raise SystemExit(main())
