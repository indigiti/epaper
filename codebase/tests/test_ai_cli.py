"""End-to-end JSON/CLI smoke checks using fake hOCR only (no external models)."""
import json
import subprocess
import sys
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
TENANT = "018f5e38-1f04-7cc3-8000-000000000011"
PAGE = "018f5e38-1f04-7cc3-8000-000000000022"
HOCR = (b'<div class="ocr_carea"><span class="ocr_line">'
        + '<span class="ocrx_word" title="bbox 20 30 120 60; x_wconf 87">नमस्कार</span>'.encode()
        + b'</span></div>')


def invoke(name, payload, *args):
    return subprocess.run([sys.executable, str(ROOT / "bin" / name), *args],
                          input=payload, capture_output=True, timeout=10, check=False)


class CliSmoke(unittest.TestCase):
    def test_pipe_normalize_to_layout(self):
        normalized = invoke("ai-ocr-normalize.py", HOCR,
                            "--tenant-guid=" + TENANT, "--page-guid=" + PAGE,
                            "--width=1000", "--height=1200", "--languages=mar+hin+eng")
        self.assertEqual(normalized.returncode, 0, normalized.stderr.decode())
        ocr = json.loads(normalized.stdout)
        self.assertEqual(ocr["words"][0]["text"], "नमस्कार")
        rendered = invoke("ai-layout-preview.py", normalized.stdout)
        self.assertEqual(rendered.returncode, 0, rendered.stderr.decode())
        layout = json.loads(rendered.stdout)
        self.assertEqual(layout["page"]["tenant_guid"], TENANT)
        self.assertEqual(layout["page"]["page_guid"], PAGE)
        self.assertEqual(layout["regions"][0]["text"], "नमस्कार")
        self.assertEqual(layout["regions"][0]["source_group"]["kind"], "OCR_LINE")
        self.assertEqual(layout["status"], "REVIEW_REQUIRED")
        self.assertFalse(layout["regions"][0]["verified_article"])

    def test_invalid_preview_rejected_without_echo(self):
        data = b'{"schema_version":"wrong","private_document":"never-echo"}'
        bad = invoke("ai-layout-preview.py", data)
        self.assertEqual(bad.returncode, 2)
        self.assertEqual(bad.stdout, b"")
        self.assertNotIn(b"never-echo", bad.stderr)

    def test_invalid_ocr_metadata_rejected(self):
        bad = invoke("ai-ocr-normalize.py", HOCR, "--tenant-guid=invalid",
                     "--page-guid=" + PAGE, "--width=1000", "--height=1200")
        self.assertEqual(bad.returncode, 2)
        self.assertEqual(bad.stdout, b"")


if __name__ == "__main__":
    unittest.main()
