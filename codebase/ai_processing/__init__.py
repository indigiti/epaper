"""Opt-in OCR and review-only region proposals for ePaper smart clipping."""
from .hocr import OcrContractError, normalize_hocr
from .layout import propose_regions
__all__ = ["OcrContractError", "normalize_hocr", "propose_regions"]
