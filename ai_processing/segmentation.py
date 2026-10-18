"""Conservative headline/body/caption grouping and candidate story links.

Unsupervised geometry is a proposal, NOT publication-ready story detection.
"""
from __future__ import annotations

import statistics
import uuid

from .hocr import OcrContractError
from .layout import propose_regions

SCHEMA = "epdi.segmentation.v1"


def _area(b):
    return max(0, b["x2"] - b["x1"]) * max(0, b["y2"] - b["y1"])


def _center(b):
    return (b["x1"] + b["x2"]) / 2


def _find_column(region_id, columns):
    for col in columns:
        if region_id in col["region_ids"]:
            return col["index"]
    return None


def segment_page(ocr: dict, image_regions: list[dict] | None = None) -> dict:
    """Detect reviewable text roles, image regions, and within-column story links."""
    layout = propose_regions(ocr)
    words = ocr["words"]
    page = layout["page"]
    page_id = uuid.UUID(page["page_guid"])
    pwidth, pheight = page["width_px"], page["height_px"]
    image_regions = image_regions or []
    if not isinstance(image_regions, list) or len(image_regions) > 128:
        raise OcrContractError("Invalid image detection output")
    word_by_id = {word["id"]: word for word in words}
    line_heights = [word["bbox_px"]["y2"] - word["bbox_px"]["y1"] for word in words]
    median_height = statistics.median(line_heights)
    enhanced = []

    for raw in layout["regions"]:
        region = dict(raw)
        heights = [word_by_id[w]["bbox_px"]["y2"] - word_by_id[w]["bbox_px"]["y1"]
                   for w in region["word_ids"]]
        typical = statistics.median(heights)
        b = region["bbox_px"]
        content = region["text"]
        # Both relative and absolute scale are necessary; a page in one font
        # cannot prove which region is a headline.
        headline = (typical >= max(17, median_height * 1.35)
                    and len(content) <= 260
                    and b["y1"] < pheight * .85)
        label = "HEADLINE_CANDIDATE" if headline else "BODY_CANDIDATE"
        region["suggested_kind"] = label
        region["role_provenance"] = "line-height-relative-v1"
        region["column_index"] = _find_column(region["id"], layout["columns"])
        region["requires_editor_review"] = True
        enhanced.append(region)

    detected_images = []
    for n, item in enumerate(image_regions):
        if not isinstance(item, dict) or item.get("kind") != "IMAGE_CANDIDATE":
            raise OcrContractError("Invalid image candidate type")
        box = item.get("bbox_px")
        if not isinstance(box, dict) or any(type(box.get(k)) is not int
                                            for k in ("x1", "y1", "x2", "y2")):
            raise OcrContractError("Invalid image candidate coordinates")
        x1, y1, x2, y2 = (box[k] for k in ("x1", "y1", "x2", "y2"))
        if not (0 <= x1 < x2 <= pwidth and 0 <= y1 < y2 <= pheight):
            raise OcrContractError("Image candidate out of page bounds")
        key = f"image-candidate:{n}:{x1}:{y1}:{x2}:{y2}"
        detected_images.append({
            "id": str(uuid.uuid5(page_id, key)),
            "kind": "IMAGE_CANDIDATE",
            "bbox_px": dict(box),
            "bbox_norm": {"x": round(x1 / pwidth, 6), "y": round(y1 / pheight, 6),
                          "width": round((x2 - x1) / pwidth, 6),
                          "height": round((y2 - y1) / pheight, 6)},
            "text": "",
            "word_ids": [],
            "suggested_kind": "IMAGE_CANDIDATE",
            "role_provenance": "opencv-texture-v1",
            "requires_editor_review": True,
            "column_index": None,
            "verified_article": False,
        })

    # A short text region directly under a photo-like region is a caption hint.
    # No other semantic entity classification is claimed.
    for region in enhanced:
        b = region["bbox_px"]
        if region["suggested_kind"] != "BODY_CANDIDATE" or len(region["text"]) > 220:
            continue
        for pic in detected_images:
            pb = pic["bbox_px"]
            overlap = max(0, min(b["x2"], pb["x2"]) - max(b["x1"], pb["x1"]))
            if (overlap >= .40 * min(b["x2"]-b["x1"], pb["x2"]-pb["x1"])
                    and 0 <= b["y1"] - pb["y2"] <= max(18, median_height * 2.5)):
                region["suggested_kind"] = "CAPTION_CANDIDATE"
                region["role_provenance"] = "image-proximity-v1"
                break

    # Conservative story candidates: only explicit headlines with subsequent
    # body regions in the SAME inferred column. No cross-column stitching.
    by_id = {r["id"]: r for r in enhanced}
    stories = []
    current = None
    for rid in layout["reading_order_proposal"]:
        region = by_id[rid]
        col = region["column_index"]
        if region["suggested_kind"] == "HEADLINE_CANDIDATE":
            current = {"id": str(uuid.uuid5(page_id, "story:" + rid)),
                       "headline_region_id": rid, "body_region_ids": [],
                       "column_index": col, "verified_article": False,
                       "review_status": "REVIEW_REQUIRED"}
            stories.append(current)
        elif (current is not None and col == current["column_index"]
              and region["suggested_kind"] == "BODY_CANDIDATE"):
            current["body_region_ids"].append(rid)
        elif current is not None and col != current["column_index"]:
            current = None

    return {
        "schema_version": SCHEMA,
        "page": page,
        "source": {"engine": "geometry-heuristic-v1", "ocr_schema": ocr["schema_version"],
                   "image_detection": bool(detected_images), "is_ml_classifier": False},
        "regions": enhanced + detected_images,
        "columns": layout["columns"],
        "reading_order_proposal": layout["reading_order_proposal"],
        "reading_order_strategy": layout["reading_order_strategy"],
        "story_candidates": stories,
        "status": "REVIEW_REQUIRED",
    }
