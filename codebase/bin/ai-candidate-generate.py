#!/usr/bin/env python3
"""Worker-only staging tool: trusted PHP writes private input and receives review JSON.

No public HTTP endpoint. Tenant and page authorization is enforced in PHP.
"""
from __future__ import annotations
import argparse
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from ai_processing.hocr import OcrContractError, normalize_hocr
from ai_processing.image_regions import detect_images
from ai_processing.segmentation import segment_page

MAX_INPUT = 18 * 1024 * 1024
MAX_OUTPUT = 8 * 1024 * 1024


def main() -> int:
    p = argparse.ArgumentParser()
    p.add_argument("--input-json", required=True)
    p.add_argument("--output-json", required=True)
    p.add_argument("--image", default="")
    args = p.parse_args()
    try:
        source = Path(args.input_json)
        target = Path(args.output_json)
        if source.stat().st_size > MAX_INPUT:
            raise OcrContractError("OCR worker input too large")
        data = json.loads(source.read_text(encoding="utf-8"))
        doc = normalize_hocr(data["hocr"], tenant_guid=data["tenant_guid"],
                             page_guid=data["page_guid"], width=data["width"],
                             height=data["height"], languages=data.get("languages", "eng"))
        images, warnings = [], []
        if args.image:
            try:
                images = detect_images(args.image, doc)
            except OcrContractError:
                warnings.append("IMAGE_DETECTOR_UNAVAILABLE_OR_INVALID")
        result = segment_page(doc, images)
        result["warnings"] = warnings
        result["source"]["image_detector_attempted"] = bool(args.image)
        output = json.dumps(result, ensure_ascii=False, separators=(",", ":"))
        if len(output.encode("utf-8")) > MAX_OUTPUT:
            raise OcrContractError("Generated candidate JSON too large")
        target.write_text(output, encoding="utf-8")
        target.chmod(0o600)
    except (OSError, UnicodeError, ValueError, TypeError, KeyError, OcrContractError):
        # Do not log input documents, file paths or private tenant metadata.
        print("AI candidate generation failed (see staging worker metrics)", file=sys.stderr)
        return 2
    return 0


if __name__ == "__main__":
    sys.exit(main())
