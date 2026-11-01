"""Standard-library regression tests for the optional OCR contract."""
import sys
import unittest
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from ai_processing.hocr import MAX_HOCR_BYTES, OcrContractError, normalize_hocr
TENANT = "018f5e38-1f04-7cc3-8000-000000000011"
PAGE = "018f5e38-1f04-7cc3-8000-000000000022"
HOCR = """<html><body><div class="ocr_page" title="bbox 0 0 1000 2000">
<span class="ocr_line"><span class="ocrx_word" title="bbox 100 200 500 300; x_wconf 91">नमस्कार</span>
<span class="ocrx_word" title="bbox 510 200 800 300">Hello</span></span></div></body></html>"""


def convert(hocr=HOCR, **kwargs):
    params = {"tenant_guid": TENANT, "page_guid": PAGE, "width": 1000,
              "height": 2000, "languages": "mar+hin+eng"}
    params.update(kwargs)
    return normalize_hocr(hocr, **params)


class ContractTest(unittest.TestCase):
    def test_multilingual_and_normalized_geometry(self):
        result = convert()
        self.assertEqual(result["schema_version"], "epdi.ocr.v1")
        self.assertEqual([w["text"] for w in result["words"]], ["नमस्कार", "Hello"])
        self.assertEqual(result["words"][0]["bbox_norm"],
                         {"x": .1, "y": .1, "width": .4, "height": .05})
        self.assertEqual(result["words"][0]["confidence"], .91)
        self.assertIsNone(result["words"][1]["confidence"])
        self.assertEqual(result["status"], "REVIEW_REQUIRED")

    def test_stable_ids_same_input(self):
        self.assertEqual(convert(), convert())
        self.assertNotEqual(convert()["words"][0]["id"],
                            convert(HOCR.replace("नमस्कार", "बदलले"))["words"][0]["id"])

    def test_bad_geometry_is_reported_not_clamped(self):
        data = convert(HOCR.replace("bbox 510 200 800 300", "bbox 999 200 1500 300"))
        self.assertEqual(len(data["words"]), 1)
        self.assertEqual(data["warnings"]["rejected_word_count"], 1)

    def test_invalid_metadata_rejected(self):
        for kwargs in ({"tenant_guid": "bad"}, {"page_guid": "bad"},
                       {"width": 0}, {"height": True}, {"languages": "../../etc"}):
            with self.subTest(kwargs=kwargs), self.assertRaises(OcrContractError):
                convert(**kwargs)

    def test_no_valid_words_rejected(self):
        with self.assertRaises(OcrContractError):
            convert('<span class="ocrx_word" title="bbox 2000 10 3000 20">outside</span>')

    def test_multi_page_rejected(self):
        with self.assertRaises(OcrContractError):
            convert('<div class="ocr_page"></div><div class="ocr_page"></div>' + HOCR)

    def test_script_not_mixed_into_word(self):
        data = convert(HOCR.replace("Hello", "H<script>alert('x')</script>ello"))
        self.assertEqual(data["words"][1]["text"], "Hello")

    def test_reject_large_input(self):
        with self.assertRaises(OcrContractError):
            convert("x" * (MAX_HOCR_BYTES + 1))

    def test_bad_confidence_not_fabricated(self):
        data = convert(HOCR.replace("x_wconf 91", "x_wconf -1"))
        self.assertIsNone(data["words"][0]["confidence"])

    def test_nesting_bounded(self):
        with self.assertRaises(OcrContractError):
            convert("<div>" * 300 + HOCR)


if __name__ == "__main__":
    unittest.main()
