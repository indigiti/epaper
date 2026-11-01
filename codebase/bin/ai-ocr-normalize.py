#!/usr/bin/env python3
"""Worker-only hOCR converter: stdin -> JSON stdout, no DB or filesystem reads."""
from __future__ import annotations
import argparse
import json
import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from ai_processing import OcrContractError, normalize_hocr
from ai_processing.hocr import MAX_HOCR_BYTES


def main() -> int:
    parser = argparse.ArgumentParser(description="Normalize a trusted single hOCR page")
    parser.add_argument("--tenant-guid", required=True)
    parser.add_argument("--page-guid", required=True)
    parser.add_argument("--width", required=True, type=int)
    parser.add_argument("--height", required=True, type=int)
    parser.add_argument("--languages", default="eng")
    options = parser.parse_args()
    raw = sys.stdin.buffer.read(MAX_HOCR_BYTES + 1)
    if len(raw) > MAX_HOCR_BYTES:
        print("hOCR byte limit exceeded", file=sys.stderr)
        return 2
    try:
        result = normalize_hocr(raw.decode("utf-8"), tenant_guid=options.tenant_guid,
                                page_guid=options.page_guid, width=options.width,
                                height=options.height, languages=options.languages)
    except (UnicodeError, OcrContractError) as exc:
        print("OCR normalization rejected: " + str(exc), file=sys.stderr)
        return 2
    print(json.dumps(result, ensure_ascii=False, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    sys.exit(main())
