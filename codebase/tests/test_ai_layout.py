"""Synthetic contracts only, not accuracy benchmarks against real newspapers."""
import copy
import sys
import unittest
import uuid
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from ai_processing.hocr import OcrContractError, normalize_hocr
from ai_processing.layout import propose_regions

TENANT = "018f5e38-1f04-7cc3-8000-000000000011"
PAGE = "018f5e38-1f04-7cc3-8000-000000000022"
PAGE2 = "018f5e38-1f04-7cc3-8000-000000000023"
HOCR = """<html><body><div class="ocr_page" title="bbox 0 0 1000 1400">
<div class="ocr_carea"><p class="ocr_par"><span class="ocr_line"><span class="ocrx_word" title="bbox 50 100 250 130; x_wconf 92">पहिली</span><span class="ocrx_word" title="bbox 260 100 400 130; x_wconf 80">बातमी</span></span></p></div>
<div class="ocr_carea"><p class="ocr_par"><span class="ocr_line"><span class="ocrx_word" title="bbox 60 300 350 330; x_wconf 70">Left</span></span></p></div>
<div class="ocr_carea"><p class="ocr_par"><span class="ocr_line"><span class="ocrx_word" title="bbox 600 120 880 150; x_wconf 95">दूसरी</span></span></p></div>
<div class="ocr_carea"><p class="ocr_par"><span class="ocr_line"><span class="ocrx_word" title="bbox 620 330 860 360; x_wconf 97">Right</span></span></p></div>
</div></body></html>"""


def ocr(page=PAGE, hocr=HOCR):
    return normalize_hocr(hocr, tenant_guid=TENANT, page_guid=page,
                          width=1000, height=1400, languages="mar+hin+eng")


class LayoutContractTest(unittest.TestCase):
    def test_area_regions_and_column_first_order(self):
        words = ocr()["words"]
        self.assertEqual([w["ocr_area"] for w in words], [1, 1, 2, 3, 4])
        self.assertEqual([w["ocr_line"] for w in words], [1, 1, 2, 3, 4])
        result = propose_regions(ocr())
        self.assertEqual(result["schema_version"], "epdi.layout.v1")
        self.assertEqual([r["text"] for r in result["regions"]],
                         ["पहिली बातमी", "Left", "दूसरी", "Right"])
        self.assertEqual(len(result["columns"]), 2)
        regions = {r["id"]: r for r in result["regions"]}
        ordered_texts = [regions[r]["text"] for r in result["reading_order_proposal"]]
        self.assertEqual(ordered_texts, ["पहिली बातमी", "Left", "दूसरी", "Right"])
        self.assertEqual(result["reading_order_strategy"], "COLUMN_FIRST_GEOMETRY_HEURISTIC")
        self.assertEqual(result["status"], "REVIEW_REQUIRED")
        self.assertTrue(all(not r["verified_article"] for r in result["regions"]))

    def test_region_geometry_and_confidence(self):
        result = propose_regions(ocr())
        first = result["regions"][0]
        self.assertEqual(first["bbox_norm"], {
            "x": .05, "y": round(100 / 1400, 6),
            "width": .35, "height": round(30 / 1400, 6)
        })
        self.assertEqual(first["ocr_word_confidence_mean"], .86)

    def test_repeatable_and_page_scoped(self):
        first = propose_regions(ocr())
        second = propose_regions(ocr())
        self.assertEqual(first, second)
        changed = propose_regions(ocr(page=PAGE2))
        self.assertNotEqual(first["regions"][0]["id"], changed["regions"][0]["id"])
        self.assertEqual(changed["page"]["page_guid"], PAGE2)

    def test_reject_cross_page_id_mismatch(self):
        source = ocr()
        source["words"][0]["id"] = ocr(page=PAGE2)["words"][0]["id"]
        with self.assertRaises(OcrContractError):
            propose_regions(source)

    def test_duplicate_word_ids_rejected(self):
        source = ocr()
        source["words"][1]["id"] = source["words"][0]["id"]
        with self.assertRaises(OcrContractError):
            propose_regions(source)

    def test_bad_geometry_rejected(self):
        source = ocr()
        source["words"][0]["bbox_px"]["x2"] = 1500
        with self.assertRaises(OcrContractError):
            propose_regions(source)

    def test_no_unverified_published_input(self):
        source = ocr()
        source["status"] = "PUBLISHED"
        with self.assertRaises(OcrContractError):
            propose_regions(source)

    def test_missing_hierarchy_word_fallback_is_not_article(self):
        source = ocr(hocr='<span class="ocrx_word" title="bbox 3 3 50 30">One</span>'
                    '<span class="ocrx_word" title="bbox 70 3 100 30">Two</span>')
        result = propose_regions(source)
        self.assertEqual(len(result["regions"]), 2)
        self.assertEqual([r["source_group"]["kind"] for r in result["regions"]],
                         ["SINGLE_WORD_FALLBACK", "SINGLE_WORD_FALLBACK"])

    def test_spanning_header_requires_source_order(self):
        source = ocr(hocr="""<div class="ocr_carea"><span class="ocrx_word"
            title="bbox 10 10 950 50">HEADLINE</span></div>
            <div class="ocr_carea"><span class="ocrx_word"
            title="bbox 20 100 200 140">Left</span></div>
            <div class="ocr_carea"><span class="ocrx_word"
            title="bbox 700 100 900 140">Right</span></div>""")
        result = propose_regions(source)
        self.assertEqual(result["reading_order_strategy"],
                         "UNRESOLVED_SPANNING_REGION_SOURCE_ORDER")
        self.assertEqual(result["columns"], [])
        self.assertEqual(result["reading_order_proposal"],
                         [region["id"] for region in result["regions"]])

    def test_confidence_missing_propagates_null(self):
        source = ocr()
        source["words"][0]["confidence"] = None
        result = propose_regions(source)
        self.assertIsNone(result["regions"][0]["ocr_word_confidence_mean"])

    def test_reject_nan_confidence(self):
        source = ocr()
        source["words"][0]["confidence"] = float("nan")
        with self.assertRaises(OcrContractError):
            propose_regions(source)

    def test_hocr_nesting_continuity_for_multilingual(self):
        data = ocr()
        self.assertEqual(data["words"][0]["ocr_paragraph"], data["words"][1]["ocr_paragraph"])
        self.assertEqual(data["words"][2]["ocr_paragraph"], 2)


if __name__ == "__main__":
    unittest.main()
