#!/usr/bin/env python3
"""Opt-in region preview: validated epdi.ocr.v1 JSON stdin -> epdi.layout.v1 JSON stdout."""
from __future__ import annotations

import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from ai_processing.hocr import OcrContractError
from ai_processing.layout import propose_regions

MAX_JSON_BYTES = 24 * 1024 * 1024


def main() -> int:
    raw = sys.stdin.buffer.read(MAX_JSON_BYTES + 1)
    if len(raw) > MAX_JSON_BYTES:
        print("Layout input exceeds byte limit", file=sys.stderr)
        return 2
    try:
        data = json.loads(raw.decode("utf-8"))
        result = propose_regions(data)
    except (UnicodeError, ValueError, TypeError, OcrContractError):
        # Do not echo OCR contents, credentials, or tenant documents into logs.
        print("Invalid or unsupported OCR layout input", file=sys.stderr)
        return 2
    print(json.dumps(result, ensure_ascii=False, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    sys.exit(main())
