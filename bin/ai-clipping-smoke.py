#!/usr/bin/env python3
"""Private, no-database, no-network candidate CLI and OpenCV staging smoke test.

Run with the identical Python interpreter configured for the RabbitMQ OCR worker.
All images and JSON payloads are synthetic and held in disposable private files.
"""
from __future__ import annotations

import json
import subprocess
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
TENANT = "018f5e38-1f04-7cc3-8000-000000000011"
PAGE = "018f5e38-1f04-7cc3-8000-000000000022"

HOCR = """<div class="ocr_page">
<div class="ocr_carea"><span class="ocrx_word" title="bbox 50 50 330 92; x_wconf 95">Breaking</span></div>
<div class="ocr_carea"><span class="ocrx_word" title="bbox 50 115 330 130; x_wconf 90">पहिलीबातमी</span></div>
<div class="ocr_carea"><span class="ocrx_word" title="bbox 570 150 905 165; x_wconf 83">storytext</span></div>
</div>"""


def run() -> int:
    stage = "DEPENDENCY_IMPORT_FAILED"
    try:
        import cv2
        import numpy as np

        stage = "TEMP_WORKSPACE_FAILED"
        with tempfile.TemporaryDirectory(prefix="epdi-ai-smoke-") as tmp:
            path = Path(tmp)
            source = path / "input.json"
            target = path / "result.json"
            image = path / "synthetic.png"

            rng = np.random.default_rng(443)
            pixels = np.full((1200, 1000), 245, dtype=np.uint8)
            pixels[240:630, 105:470] = rng.integers(
                10, 230, size=(390, 365), dtype=np.uint8
            )
            stage = "IMAGE_GENERATION_FAILED"
            if not cv2.imwrite(str(image), pixels):
                raise RuntimeError("Synthetic page image could not be created")

            stage = "SOURCE_JSON_FAILED"
            source.write_text(json.dumps({
                "hocr": HOCR,
                "tenant_guid": TENANT,
                "page_guid": PAGE,
                "width": 1000,
                "height": 1200,
                "languages": "mar+hin+eng",
            }, ensure_ascii=False), encoding="utf-8")

            command = [
                sys.executable, str(ROOT / "bin" / "ai-candidate-generate.py"),
                "--input-json", str(source),
                "--output-json", str(target),
                "--image", str(image),
            ]
            stage = "CANDIDATE_CLI_FAILED"
            worker = subprocess.run(command, capture_output=True, timeout=12, check=False)
            if worker.returncode:
                raise RuntimeError("AI candidate CLI failed")
            stage = "OUTPUT_CONTRACT_FAILED"
            doc = json.loads(target.read_text(encoding="utf-8"))
            if (doc.get("schema_version") != "epdi.segmentation.v1"
                    or doc.get("status") != "REVIEW_REQUIRED"
                    or doc.get("page", {}).get("tenant_guid") != TENANT
                    or doc.get("page", {}).get("page_guid") != PAGE
                    or not doc.get("regions")
                    or not doc.get("source", {}).get("image_detector_attempted")
                    or doc.get("warnings")):
                raise RuntimeError("Candidate schema or image detector contract failed")
            if any(r.get("verified_article") is not False for r in doc["regions"]):
                raise RuntimeError("Unsafe automatically verified region detected")

        print("PASS: private Python / NumPy / OpenCV, candidate JSON and manual-review contract")
        print("NOTE: synthetic smoke only; RabbitMQ, real OCR and tenant ACL need staging acceptance")
        return 0
    except (ImportError, OSError, ValueError, KeyError, RuntimeError,
            subprocess.TimeoutExpired) as exc:
        print("EPDI_AI_SMOKE_CODE:" + stage, file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(run())
