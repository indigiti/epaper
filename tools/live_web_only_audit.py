#!/usr/bin/env python3
"""Read-only HTTPS diagnostics on the two fixed Digiti production hosts."""
from __future__ import annotations
import json
import os
import socket
import ssl
import time
from datetime import datetime, timezone
from pathlib import Path
from urllib.error import HTTPError, URLError
from urllib.request import HTTPRedirectHandler, Request, build_opener

HOSTS = {
    "platform.digiti.in": ("/", "/login", "/health/live", "/health/ready"),
    "epaper.digiti.in": ("/", "/archive", "/health/live", "/health/ready"),
}
# Never use GET to inspect private files.
PRIVATE_PATHS = ("/config/config.php", "/app/Core/Config.php",
                 "/database/schema.sql", "/.git/config")
TIMEOUT = 7

class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, request, fp, code, msg, headers, newurl):
        return None

def probe(opener, host, path, method):
    request = Request(
        "https://" + host + path,
        method=method,
        headers={"User-Agent": "EPDI-WebOnly-ReadOnly-QA/1.0",
                 "Accept": "application/json,text/html;q=0.8,*/*;q=0.5",
                 "Cache-Control": "no-cache"},
    )
    status, error, reported_ok = 0, None, None
    start = time.monotonic()
    try:
        with opener.open(request, timeout=TIMEOUT) as response:
            status = response.status
            if method == "GET" and path.startswith("/health/"):
                # Only public health JSON, no raw bodies stored or printed.
                try:
                    obj = json.loads(response.read(8192))
                    if isinstance(obj, dict) and isinstance(obj.get("ok"), bool):
                        reported_ok = obj["ok"]
                except (ValueError, UnicodeError):
                    pass
    except HTTPError as exc:
        status = exc.code
        exc.close()
    except (URLError, OSError, TimeoutError, ValueError) as exc:
        error = type(exc).__name__
    row = {"host": host, "method": method, "path": path,
           "http_status": status,
           "duration_ms": round((time.monotonic()-start)*1000, 1),
           "transport_error_type": error}
    if reported_ok is not None:
        row["reported_ok"] = reported_ok
    if method == "HEAD":
        # A 200 might be a front-controller rewrite, not necessarily leakage.
        row["assessment"] = ("review_possible_exposure" if 200 <= status < 300 else
                             "not_served_2xx" if status else "transport_unverified")
    elif path in ("/", "/health/live", "/health/ready"):
        expected = (status == 200 if path.startswith("/health/") else 200 <= status < 400)
        row["assessment"] = "pass" if expected and reported_ok is not False else "fail"
    else:
        row["assessment"] = "info" if status and status < 500 else "review"
    return row

def check_tls(host):
    try:
        with socket.create_connection((host, 443), timeout=TIMEOUT) as raw:
            with ssl.create_default_context().wrap_socket(raw, server_hostname=host) as connection:
                cert = connection.getpeercert()
                expires = datetime.fromtimestamp(
                    ssl.cert_time_to_seconds(cert["notAfter"]), tz=timezone.utc)
                days = (expires-datetime.now(timezone.utc)).total_seconds()/86400
                return {"host": host, "trusted": True,
                        "days_until_expiration": round(days, 1),
                        "assessment": "pass" if days > 14 else "review"}
    except (OSError, ssl.SSLError, ValueError, KeyError) as exc:
        return {"host": host, "trusted": False, "error_type": type(exc).__name__,
                "assessment": "fail"}

def main():
    opener = build_opener(NoRedirect())
    tls, rows = [], []
    for host, paths in HOSTS.items():
        tls.append(check_tls(host))
        for path in paths:
            rows.append(probe(opener, host, path, "GET"))
            time.sleep(0.15)
        for path in PRIVATE_PATHS:
            rows.append(probe(opener, host, path, "HEAD"))
            time.sleep(0.15)
    failures = [r for r in rows if r["assessment"] == "fail"]
    failures += [r for r in tls if r["assessment"] == "fail"]
    report = {
        "kind": "digiti_live_web_only_read_only_audit",
        "checked_at_utc": datetime.now(timezone.utc).isoformat(),
        "no_credentials_or_write_operations": True,
        "not_a_database_worker_or_full_functional_acceptance": True,
        "http": rows, "tls": tls,
        "critical_failures": len(failures),
        "overall_http_health_pass": not bool(failures),
    }
    Path("web-only-qa.json").write_text(
        json.dumps(report, indent=2)+"\n", encoding="utf-8")
    summary = [
        "## Digiti web-only live QA", "",
        "Unauthenticated and read-only. No production writes, redirects followed or private response bodies retrieved.", "",
        "| Host | Method | Endpoint | HTTP | Time | Assessment |",
        "|---|---|---|---:|---:|---|",
    ]
    for row in rows:
        summary.append(
            f"| {row['host']} | {row['method']} | {row['path']} | {row['http_status'] or 'network error'} | {row['duration_ms']} ms | {row['assessment']} |")
    summary += ["", "| Host | TLS verified | Certificate days remaining | Assessment |",
                "|---|---|---:|---|"]
    for item in tls:
        summary.append(
            f"| {item['host']} | {item['trusted']} | {item.get('days_until_expiration','n/a')} | {item['assessment']} |")
    summary += ["", "HEAD 2xx on a private path requires manual review, not proof of leakage.",
                "No authenticated, database, Redis, queue or browser-flow tests are implied."]
    md = "\n".join(summary)+"\n"
    print(md)
    if os.getenv("GITHUB_STEP_SUMMARY"):
        with open(os.environ["GITHUB_STEP_SUMMARY"], "a", encoding="utf-8") as f:
            f.write(md)
    return 1 if failures else 0

if __name__ == "__main__":
    raise SystemExit(main())
