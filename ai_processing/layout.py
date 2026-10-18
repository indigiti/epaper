"""Conservative OCR region proposals from epdi.ocr.v1.

This is NOT article segmentation. hOCR area/paragraph/line ancestry is used
only to propose reviewable text regions and a column-first reading order.
No publication, clip creation, media reads, or DB calls occur here.
"""
from __future__ import annotations

import math
import uuid
from collections import OrderedDict

from .hocr import MAX_DIMENSION, MAX_WORDS, OcrContractError, SCHEMA_VERSION as OCR_SCHEMA

LAYOUT_SCHEMA = "epdi.layout.v1"
MAX_REGIONS = 4096


def _guid(raw: object, label: str) -> str:
    try:
        if not isinstance(raw, str):
            raise ValueError("not a string")
        return str(uuid.UUID(raw))
    except ValueError as exc:
        raise OcrContractError("Invalid " + label) from exc


def _dimension(raw: object, label: str) -> int:
    if type(raw) is not int or not 1 <= raw <= MAX_DIMENSION:
        raise OcrContractError("Invalid " + label)
    return raw


def _rectangle(raw: object, width: int, height: int) -> tuple[int, int, int, int]:
    if not isinstance(raw, dict):
        raise OcrContractError("Invalid OCR word rectangle")
    coords = [raw.get(key) for key in ("x1", "y1", "x2", "y2")]
    if any(type(value) is not int for value in coords):
        raise OcrContractError("Non-integer OCR word rectangle")
    x1, y1, x2, y2 = coords
    if not (0 <= x1 < x2 <= width and 0 <= y1 < y2 <= height):
        raise OcrContractError("OCR word outside page boundaries")
    return x1, y1, x2, y2


def _normalized(rect: tuple[int, int, int, int], w: int, h: int) -> dict:
    x1, y1, x2, y2 = rect
    return {"x": round(x1 / w, 6), "y": round(y1 / h, 6),
            "width": round((x2 - x1) / w, 6),
            "height": round((y2 - y1) / h, 6)}


def _group(word: dict, ordinal: int) -> tuple[str, int]:
    # Never invent multi-column article joins. Prefer explicit hierarchy.
    for field, kind in (("ocr_area", "OCR_AREA"),
                        ("ocr_paragraph", "OCR_PARAGRAPH"),
                        ("ocr_line", "OCR_LINE")):
        val = word.get(field)
        if val is not None:
            if type(val) is not int or not 1 <= val <= MAX_WORDS:
                raise OcrContractError("Invalid hOCR ancestry index")
            return kind, val
    return "SINGLE_WORD_FALLBACK", ordinal + 1


def _column_order(regions: list[dict], page_width: int) -> tuple[list[str], list[dict], str]:
    if len(regions) > 1 and any(
        r["bbox_px"]["x2"] - r["bbox_px"]["x1"] >= page_width * .70
        for r in regions
    ):
        return ([r["id"] for r in regions], [],
                "UNRESOLVED_SPANNING_REGION_SOURCE_ORDER")

    columns: list[dict] = []
    for region in sorted(regions, key=lambda r: (
        r["bbox_px"]["x1"], r["bbox_px"]["y1"], r["id"]
    )):
        b = region["bbox_px"]
        left, right = b["x1"], b["x2"]
        size = right - left
        matches = []
        for column in columns:
            overlap = max(0, min(right, column["x2"]) - max(left, column["x1"]))
            if overlap >= min(size, column["x2"] - column["x1"]) * .5:
                matches.append((overlap, column))
        if matches:
            column = max(matches, key=lambda match: match[0])[1]
            column["x1"] = min(column["x1"], left)
            column["x2"] = max(column["x2"], right)
            column["regions"].append(region)
        else:
            columns.append({"x1": left, "x2": right, "regions": [region]})

    columns.sort(key=lambda c: (c["x1"], c["x2"]))
    proposal: list[str] = []
    visible_columns: list[dict] = []
    for index, column in enumerate(columns):
        ordered = sorted(column["regions"], key=lambda r: (
            r["bbox_px"]["y1"], r["bbox_px"]["x1"], r["id"]
        ))
        ids = [r["id"] for r in ordered]
        proposal.extend(ids)
        visible_columns.append({"index": index, "x1": column["x1"],
                                "x2": column["x2"], "region_ids": ids})
    return proposal, visible_columns, "COLUMN_FIRST_GEOMETRY_HEURISTIC"


def propose_regions(document: dict) -> dict:
    """Propose hOCR-derived text regions, preserving source and tenant identity.

    The trusted worker MUST authorize the tenant/page before invoking this
    function; echoed IDs are not authentication. Results require editor review.
    """
    if not isinstance(document, dict) or document.get("schema_version") != OCR_SCHEMA:
        raise OcrContractError("Expected epdi.ocr.v1 input")
    page = document.get("page")
    if not isinstance(page, dict):
        raise OcrContractError("Missing OCR page metadata")
    tenant = _guid(page.get("tenant_guid"), "tenant identifier")
    page_guid = _guid(page.get("page_guid"), "page identifier")
    width = _dimension(page.get("width_px"), "page width")
    height = _dimension(page.get("height_px"), "page height")
    raw_words = document.get("words")
    if not isinstance(raw_words, list) or not 1 <= len(raw_words) <= MAX_WORDS:
        raise OcrContractError("Invalid OCR word count")
    if document.get("status") != "REVIEW_REQUIRED":
        raise OcrContractError("OCR input must be review-only")

    groups: dict[tuple[str, int], list[dict]] = OrderedDict()
    seen = set()
    for index, word in enumerate(raw_words):
        if not isinstance(word, dict):
            raise OcrContractError("Invalid OCR word record")
        guid = _guid(word.get("id"), "OCR word identifier")
        if guid in seen:
            raise OcrContractError("Duplicate OCR word identifier")
        seen.add(guid)
        value = word.get("text")
        if not isinstance(value, str) or not value.strip() or len(value) > 2048:
            raise OcrContractError("Invalid OCR word content")
        rect = _rectangle(word.get("bbox_px"), width, height)
        expected_id = str(uuid.uuid5(uuid.UUID(page_guid), f"{index}:{rect}:{value.strip()}"))
        if guid != expected_id:
            raise OcrContractError("OCR word identifier does not match page and content")
        confidence = word.get("confidence")
        if confidence is not None:
            if type(confidence) not in (int, float) or not math.isfinite(confidence) or not 0 <= confidence <= 1:
                raise OcrContractError("Invalid OCR word confidence")
        group = _group(word, index)
        groups.setdefault(group, []).append({
            "id": guid, "text": value.strip(), "bbox": rect,
            "confidence": confidence,
        })
        if len(groups) > MAX_REGIONS:
            raise OcrContractError("Too many OCR layout regions")

    regions = []
    for (kind, group_index), words in groups.items():
        x1 = min(w["bbox"][0] for w in words)
        y1 = min(w["bbox"][1] for w in words)
        x2 = max(w["bbox"][2] for w in words)
        y2 = max(w["bbox"][3] for w in words)
        rect = (x1, y1, x2, y2)
        confident = [w["confidence"] for w in words if w["confidence"] is not None]
        score = round(sum(confident) / len(confident), 4) if len(confident) == len(words) else None
        uid = str(uuid.uuid5(uuid.UUID(page_guid), f"epdi.layout.v1:{kind}:{group_index}"))
        regions.append({
            "id": uid,
            "kind": "OCR_TEXT_REGION",
            "verified_article": False,
            "source_group": {"kind": kind, "index": group_index},
            "bbox_px": {"x1": x1, "y1": y1, "x2": x2, "y2": y2},
            "bbox_norm": _normalized(rect, width, height),
            "word_ids": [word["id"] for word in words],
            "text": " ".join(word["text"] for word in words),
            "ocr_word_confidence_mean": score,
            "requires_editor_review": True,
        })
    order, columns, strategy = _column_order(regions, width)
    return {
        "schema_version": LAYOUT_SCHEMA,
        "page": {"tenant_guid": tenant, "page_guid": page_guid,
                 "width_px": width, "height_px": height},
        "source": {"engine": "hocr-structure-v1", "ocr_schema": OCR_SCHEMA,
                   "article_detection": False},
        "regions": regions,
        "reading_order_proposal": order,
        "reading_order_strategy": strategy,
        "columns": columns,
        "status": "REVIEW_REQUIRED",
    }
