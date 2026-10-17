"""Candidate photograph/illustration regions using classical computer vision.

Optional OpenCV dependency, no external service or pretrained model. Newspaper
textures/advertisements may be mistaken for photos: results require review.
"""
from __future__ import annotations

from .hocr import OcrContractError

MAX_FILE_BYTES = 32 * 1024 * 1024
MAX_PIXELS = 40_000_000


def detect_images(image_path: str, ocr: dict) -> list[dict]:
    try:
        import cv2
        import numpy as np
    except ImportError as exc:
        raise OcrContractError("OpenCV image detector not installed") from exc
    from pathlib import Path
    file = Path(image_path)
    if not file.is_file() or file.stat().st_size > MAX_FILE_BYTES:
        raise OcrContractError("Invalid image file size")
    # IMAGE path must be authorized/tenant-scoped by the upstream PHP worker.
    img = cv2.imread(str(file), cv2.IMREAD_GRAYSCALE)
    if img is None:
        raise OcrContractError("Unable to decode page image")
    original_h, original_w = img.shape[:2]
    if original_w * original_h > MAX_PIXELS:
        raise OcrContractError("Image pixels exceed processing limit")
    meta = ocr.get("page", {})
    width, height = meta.get("width_px"), meta.get("height_px")
    if type(width) is not int or type(height) is not int or min(width, height) <= 0:
        raise OcrContractError("Invalid page dimensions")
    # Page renderer may write alternate WebP resolution; scale all detections
    # back to native OCR image dimensions without assuming same pixel grid.
    scale = min(1.0, 1400 / max(original_w, original_h))
    if scale < 1.0:
        img = cv2.resize(img, None, fx=scale, fy=scale, interpolation=cv2.INTER_AREA)
    h, w = img.shape
    # Texture detector operates at the displayed-page resolution.
    m = cv2.blur(img.astype("float32"), (19, 19))
    m2 = cv2.blur(img.astype("float32") ** 2, (19, 19))
    deviation = cv2.sqrt(cv2.max(m2 - m * m, 0))
    mask = (deviation > 17.0).astype("uint8") * 255
    ratio_x, ratio_y = w / width, h / height
    for word in ocr.get("words", []):
        b = word.get("bbox_px", {})
        if not all(k in b for k in ("x1", "y1", "x2", "y2")):
            continue
        x1, y1 = int(b["x1"] * ratio_x), int(b["y1"] * ratio_y)
        x2, y2 = int(b["x2"] * ratio_x), int(b["y2"] * ratio_y)
        margin = max(3, int(min(w, h) * .004))
        cv2.rectangle(mask, (max(0, x1-margin), max(0, y1-margin)),
                      (min(w-1, x2+margin), min(h-1, y2+margin)), 0, -1)
    kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (19, 19))
    mask = cv2.morphologyEx(mask, cv2.MORPH_CLOSE, kernel)
    mask = cv2.morphologyEx(mask, cv2.MORPH_OPEN,
                            cv2.getStructuringElement(cv2.MORPH_RECT, (13, 13)))
    contours, _ = cv2.findContours(mask, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    detected = []
    for contour in contours:
        x, y, cw, ch = cv2.boundingRect(contour)
        if cw * ch < .013 * w * h or cw < .07 * w or ch < .06 * h:
            continue
        if cw * ch > .80 * w * h:
            continue
        x1, y1 = max(0, int(x / ratio_x)), max(0, int(y / ratio_y))
        x2, y2 = min(width, int((x + cw) / ratio_x)), min(height, int((y + ch) / ratio_y))
        if x2 <= x1 or y2 <= y1:
            continue
        detected.append({"kind": "IMAGE_CANDIDATE",
                         "bbox_px": {"x1": x1, "y1": y1, "x2": x2, "y2": y2}})
    return sorted(detected, key=lambda c: (c["bbox_px"]["y1"], c["bbox_px"]["x1"]))[:128]
