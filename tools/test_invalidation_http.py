#!/usr/bin/env python3
"""Isolated CI only: sends signed fixture events to a loopback ePaper PHP server."""
from __future__ import annotations
import hashlib
import hmac
import json
import os
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid


def request(url: str, raw: bytes | None, secret: bytes, *, timestamp: int | None = None, corrupt=False):
    if timestamp is None:
        timestamp = int(time.time())
    signature = hmac.new(secret, str(timestamp).encode() + b"\n" + (raw or b""), hashlib.sha256).hexdigest()
    if corrupt:
        signature = "0" * 64
    headers = {"Accept": "application/json", "Content-Type": "application/json",
               "X-Digiti-Timestamp": str(timestamp), "X-Digiti-Signature": "v1=" + signature}
    req = urllib.request.Request(url, data=raw, method="POST" if raw is not None else "GET", headers=headers)
    try:
        with urllib.request.urlopen(req, timeout=5) as resp:
            return resp.status, json.loads(resp.read())
    except urllib.error.HTTPError as err:
        return err.code, json.loads(err.read())


def main():
    base = os.environ.get("EPDI_RECEIVER_FIXTURE_BASE", "http://127.0.0.1:8095")
    parsed = urllib.parse.urlsplit(base)
    if os.environ.get("EPDI_RECEIVER_CI_ONLY") != "disposable-ci" or parsed.hostname not in ("127.0.0.1", "localhost"):
        raise SystemExit("Refusing non-CI or non-loopback HTTP test target")
    secret = os.environ.get("EPDI_RECEIVER_FIXTURE_SECRET", "").encode()
    if len(secret) < 32:
        raise SystemExit("Fixture secret is required")
    url = base + "/internal/v1/platform/cache-invalidate"
    event_id, tenant = str(uuid.uuid4()), str(uuid.uuid4())
    obj = {"kind": "control_plane.changed", "event_id": event_id,
           "tenant_guid": tenant, "hostnames": ["reader.staging.test"]}
    raw = json.dumps(obj, separators=(",", ":")).encode()
    # Server may be starting; poll bounded GET, which must not perform a mutation.
    for _ in range(30):
        try:
            code, _ = request(url, None, secret)
            if code == 405:
                break
        except (urllib.error.URLError, OSError):
            time.sleep(.2)
    else:
        raise SystemExit("Receiver did not become available")
    cases = [
        ("valid signed invalidation", raw, {}, 200, False),
        ("idempotent signed replay", raw, {}, 200, True),
        ("forged signature", raw, {"corrupt": True}, 400, None),
        ("expired timestamp", raw, {"timestamp": int(time.time()) - 400}, 400, None),
        ("altered event with same ID", json.dumps({**obj, "hostnames": ["different.staging.test"]}).encode(), {}, 400, None),
    ]
    for label, body, options, expected, duplicate in cases:
        status, answer = request(url, body, secret, **options)
        if status != expected or (duplicate is not None and answer.get("duplicate") is not duplicate):
            raise SystemExit(f"FAIL {label}: status={status}, response={answer}")
        if expected == 200 and answer.get("event_id") != event_id:
            raise SystemExit("FAIL mismatched ACK event ID")
        print(f"PASS {label} HTTP {status}")
    print("PASS authenticated ePaper HTTP receiver loopback acceptance")


if __name__ == "__main__":
    main()
