#!/usr/bin/env python3
"""Non-mutating public Cloudways route diagnostics; never prints response bodies or secrets."""
import json
import sys
import urllib.error
import urllib.request

BASE = "https://epaper.digiti.in"
PATHS = ("/health/live", "/health/ready", "/", "/archive")

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

def check(path):
    request = urllib.request.Request(
        BASE + path,
        headers={"User-Agent": "EPDI-read-only-acceptance/1.0", "Accept": "text/html,application/json"},
        method="GET",
    )
    opener = urllib.request.build_opener(NoRedirect())
    try:
        response = opener.open(request, timeout=12)
    except urllib.error.HTTPError as exc:
        response = exc
    except Exception as exc:
        print(f"BLOCKED {path}: {type(exc).__name__} (no response)", flush=True)
        return False

    with response:
        status = response.status
        kind = response.headers.get("Content-Type", "missing").split(";", 1)[0].lower()
        body = response.read(8192)
        target = response.headers.get("Location", "")
    expected_json = path.startswith("/health/")
    valid = False
    if expected_json:
        try:
            payload = json.loads(body.decode("utf-8", "replace"))
            valid = isinstance(payload, dict) and isinstance(payload.get("ok"), bool)
        except (ValueError, TypeError):
            pass
    else:
        valid = b"<html" in body.lower() or b"<!doctype html" in body.lower()
    passed = status == 200 and valid
    # No body content, auth cookie, credentials, or internal service detail in logs.
    note = f"; redirect={target}" if 300 <= status < 400 and target.startswith("/") else ""
    print(f"{'PASS' if passed else 'BLOCKED'} {path}: HTTP {status}, type={kind}, expected={'json' if expected_json else 'html'}, valid={valid}{note}", flush=True)
    return passed

if __name__ == "__main__":
    success = all([check(path) for path in PATHS])
    sys.exit(0 if success else 1)
