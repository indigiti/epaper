"""Deterministic, bounded Tesseract hOCR -> epdi.ocr.v1 page-word normalization.

This opt-in module has no file, database, network, or model access.
A tenant-authenticated worker supplies the page metadata and source hOCR.
"""
from __future__ import annotations
import re
import uuid
from html.parser import HTMLParser

SCHEMA_VERSION = "epdi.ocr.v1"
MAX_HOCR_BYTES = 16 * 1024 * 1024
MAX_WORDS = 50_000
MAX_DIMENSION = 100_000
_BBOX = re.compile(r"(?:^|;)\s*bbox\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)(?=\s*(?:;|$))")
_CONF = re.compile(r"(?:^|;)\s*x_wconf\s+(-?\d+(?:\.\d+)?)(?=\s*(?:;|$))")
_LANGUAGES = re.compile(r"^[A-Za-z0-9_+,-]{1,80}$")


class OcrContractError(ValueError):
    """Rejected OCR input; messages never contain the source document."""


def _bbox(title: str) -> tuple[int, int, int, int] | None:
    match = _BBOX.search(title)
    return tuple(map(int, match.groups())) if match else None


def _confidence(title: str) -> float | None:
    match = _CONF.search(title)
    if match is None:
        return None
    number = float(match.group(1))
    return round(number / 100, 4) if 0 <= number <= 100 else None


class _Words(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.stack: list[dict] = []
        self.candidates: list[tuple[str, tuple[int, int, int, int] | None, float | None]] = []
        self.page_count = 0
        self.word_tags = 0
        self.suppressed = 0

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        a = dict(attrs)
        cls = set((a.get("class") or "").split())
        title = a.get("title") or ""
        if "ocr_page" in cls:
            self.page_count += 1
            if self.page_count > 1:
                raise OcrContractError("Only one OCR page is allowed per request")
        word = bool(cls.intersection({"ocrx_word", "ocr_word"}))
        if word:
            self.word_tags += 1
            if self.word_tags > MAX_WORDS:
                raise OcrContractError("OCR word limit exceeded")
        if tag in {"script", "style"}:
            self.suppressed += 1
        if len(self.stack) >= 256:
            raise OcrContractError("hOCR nesting limit exceeded")
        self.stack.append({"tag": tag, "word": word, "bbox": _bbox(title) if word else None,
                           "confidence": _confidence(title) if word else None, "text": []})

    def handle_startendtag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        self.handle_starttag(tag, attrs)
        self.handle_endtag(tag)

    def handle_data(self, data: str) -> None:
        if self.suppressed:
            return
        for frame in reversed(self.stack):
            if frame["word"]:
                frame["text"].append(data)
                break

    def handle_endtag(self, tag: str) -> None:
        pos = next((i for i in range(len(self.stack) - 1, -1, -1)
                    if self.stack[i]["tag"] == tag), None)
        if pos is None:
            return
        for frame in reversed(self.stack[pos:]):
            if frame["tag"] in {"script", "style"}:
                self.suppressed -= 1
            if frame["word"]:
                value = " ".join("".join(frame["text"]).split())
                self.candidates.append((value, frame["bbox"], frame["confidence"]))
        del self.stack[pos:]


def normalize_hocr(hocr: str, *, tenant_guid: str, page_guid: str,
                   width: int, height: int, languages: str = "eng") -> dict:
    """Normalize one page; IDs/geometry are stable and no article grouping is invented."""
    try:
        tenant_id = str(uuid.UUID(tenant_guid))
        page_id = str(uuid.UUID(page_guid))
    except (TypeError, ValueError, AttributeError) as exc:
        raise OcrContractError("Invalid tenant or page identifier") from exc
    if not isinstance(width, int) or isinstance(width, bool) or not 1 <= width <= MAX_DIMENSION:
        raise OcrContractError("Invalid page width")
    if not isinstance(height, int) or isinstance(height, bool) or not 1 <= height <= MAX_DIMENSION:
        raise OcrContractError("Invalid page height")
    if not isinstance(languages, str) or not _LANGUAGES.fullmatch(languages):
        raise OcrContractError("Invalid OCR language list")
    if not isinstance(hocr, str) or not hocr.strip():
        raise OcrContractError("Empty hOCR input")
    if len(hocr.encode("utf-8")) > MAX_HOCR_BYTES:
        raise OcrContractError("hOCR byte limit exceeded")

    parser = _Words()
    parser.feed(hocr)
    parser.close()
    if not parser.candidates:
        raise OcrContractError("No closed OCR word elements found")
    words = []
    invalid = 0
    for value, bbox, confidence in parser.candidates:
        if not value or bbox is None:
            invalid += 1
            continue
        x1, y1, x2, y2 = bbox
        if not (0 <= x1 < x2 <= width and 0 <= y1 < y2 <= height):
            invalid += 1
            continue
        index = len(words)
        word_id = str(uuid.uuid5(uuid.UUID(page_id), f"{index}:{bbox}:{value}"))
        words.append({
            "id": word_id,
            "text": value,
            "bbox_px": {"x1": x1, "y1": y1, "x2": x2, "y2": y2},
            "bbox_norm": {"x": round(x1 / width, 6), "y": round(y1 / height, 6),
                          "width": round((x2 - x1) / width, 6),
                          "height": round((y2 - y1) / height, 6)},
            "confidence": confidence,
        })
    if not words:
        raise OcrContractError("No valid OCR words within page bounds")
    return {
        "schema_version": SCHEMA_VERSION,
        "source": {"engine": "tesseract-hocr", "model": None,
                   "confidence_is_calibrated": False},
        "page": {"tenant_guid": tenant_id, "page_guid": page_id,
                 "width_px": width, "height_px": height, "languages": languages},
        "words": words,
        "warnings": {"rejected_word_count": invalid},
        "status": "REVIEW_REQUIRED",
    }
