"""Image and newspaper-role prototype tests; accuracy awaits annotated corpus."""
import json
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT))
from ai_processing.hocr import normalize_hocr,OcrContractError
from ai_processing.segmentation import segment_page

TENANT="018f5e38-1f04-7cc3-8000-000000000011"
PAGE="018f5e38-1f04-7cc3-8000-000000000022"
HOCR="""<div class="ocr_page">
<div class="ocr_carea"><span class="ocrx_word" title="bbox 50 50 330 92; x_wconf 95">Breaking</span></div>
<div class="ocr_carea"><span class="ocrx_word" title="bbox 50 115 330 130; x_wconf 90">पहिलीबातमी</span></div>
<div class="ocr_carea"><span class="ocrx_word" title="bbox 50 145 330 162; x_wconf 88">storytext</span></div>
<div class="ocr_carea"><span class="ocrx_word" title="bbox 560 80 910 120; x_wconf 90">Second</span></div>
<div class="ocr_carea"><span class="ocrx_word" title="bbox 570 150 905 165; x_wconf 83">otherbody</span></div>
<div class="ocr_carea"><span class="ocrx_word" title="bbox 570 520 700 533; x_wconf 86">Photo</span></div>
</div>"""


def ocr():
    return normalize_hocr(HOCR,tenant_guid=TENANT,page_guid=PAGE,
                          width=1000,height=1200,languages="mar+hin+eng")


class SegmentationTest(unittest.TestCase):
    def test_headline_body_and_multicolumn(self):
        result=segment_page(ocr())
        kinds={r["text"]:r["suggested_kind"] for r in result["regions"]}
        self.assertEqual(kinds["Breaking"],"HEADLINE_CANDIDATE")
        self.assertEqual(kinds["Second"],"HEADLINE_CANDIDATE")
        self.assertEqual(kinds["पहिलीबातमी"],"BODY_CANDIDATE")
        self.assertEqual(kinds["otherbody"],"BODY_CANDIDATE")
        self.assertEqual(len(result["columns"]),2)
        self.assertEqual(len(result["story_candidates"]),2)
        self.assertEqual([len(x["body_region_ids"]) for x in result["story_candidates"]],[2,2])
        self.assertEqual(result["status"],"REVIEW_REQUIRED")
        self.assertTrue(all(not r["verified_article"] for r in result["regions"]))

    def test_image_and_caption_proposals_not_published(self):
        image={"kind":"IMAGE_CANDIDATE","bbox_px":{"x1":540,"y1":200,"x2":925,"y2":500}}
        result=segment_page(ocr(),[image])
        kinds={r["text"]:r["suggested_kind"] for r in result["regions"]}
        self.assertEqual(kinds["Photo"],"CAPTION_CANDIDATE")
        self.assertEqual(result["regions"][-1]["kind"],"IMAGE_CANDIDATE")
        self.assertFalse(result["regions"][-1]["verified_article"])
        self.assertTrue(all(x["review_status"]=="REVIEW_REQUIRED" for x in result["story_candidates"]))

    def test_reject_image_outside_page(self):
        with self.assertRaises(OcrContractError):
            segment_page(ocr(),[{"kind":"IMAGE_CANDIDATE",
                                 "bbox_px":{"x1":0,"y1":0,"x2":1800,"y2":50}}])

    def test_repeatable_segmentation(self):
        self.assertEqual(segment_page(ocr()),segment_page(ocr()))

    def test_trusted_file_cli_hocr_to_candidates(self):
        with tempfile.TemporaryDirectory() as tmp:
            inp=Path(tmp)/"in.json";out=Path(tmp)/"out.json"
            inp.write_text(json.dumps({"hocr":HOCR,"tenant_guid":TENANT,"page_guid":PAGE,
                                       "width":1000,"height":1200,"languages":"mar+eng"}))
            cmd=[sys.executable,str(ROOT/"bin"/"ai-candidate-generate.py"),
                 "--input-json",str(inp),"--output-json",str(out)]
            p=subprocess.run(cmd,capture_output=True,timeout=10)
            self.assertEqual(p.returncode,0,p.stderr.decode())
            result=json.loads(out.read_text())
            self.assertEqual(result["schema_version"],"epdi.segmentation.v1")
            self.assertEqual(result["page"]["tenant_guid"],TENANT)
            self.assertEqual(result["status"],"REVIEW_REQUIRED")
            self.assertTrue(all(not r["verified_article"] for r in result["regions"]))


class ComputerVisionTest(unittest.TestCase):
    def test_text_mask_and_photo_candidate(self):
        try:
            import cv2
            import numpy as np
        except ImportError:
            self.skipTest("OpenCV optional outside dedicated AI CI")
        from ai_processing.image_regions import detect_images
        rng=np.random.default_rng(443)
        image=np.full((1200,1000),245,dtype=np.uint8)
        image[250:650,110:475]=rng.integers(10,235,size=(400,365),dtype=np.uint8)
        image[300:590,610:930]=rng.integers(20,235,size=(290,320),dtype=np.uint8)
        with tempfile.TemporaryDirectory() as tmp:
            path=str(Path(tmp)/"page.png")
            self.assertTrue(cv2.imwrite(path,image))
            source=ocr()
            detections=detect_images(path,source)
            self.assertGreaterEqual(len(detections),2)
            self.assertTrue(all(d["kind"]=="IMAGE_CANDIDATE" for d in detections))
            self.assertTrue(all(d["bbox_px"]["x2"]<=1000 and
                                d["bbox_px"]["y2"]<=1200 for d in detections))


if __name__=="__main__":
    unittest.main()
