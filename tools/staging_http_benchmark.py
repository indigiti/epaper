#!/usr/bin/env python3
"""Read-only, bounded HTTP timing for an explicitly isolated staging hostname."""
from __future__ import annotations
import argparse
import concurrent.futures
import json
import math
import statistics
import time
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime, timezone


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, hdrs, newurl):
        return None


def valid_origin(url: str, expected_host: str) -> str:
    parsed = urllib.parse.urlsplit(url)
    host = (parsed.hostname or "").lower()
    if host != expected_host.lower() or parsed.username or parsed.password or parsed.path not in ("", "/") or parsed.query or parsed.fragment:
        raise ValueError("Benchmark origin must match --expected-host exactly, with no credentials/path/query")
    if parsed.scheme not in ("http", "https"):
        raise ValueError("Only HTTP(S) allowed")
    local = host in ("127.0.0.1", "localhost", "::1")
    staging_name = any(x in host for x in ("staging", "stage-", "preview")) or host.endswith((".test", ".invalid"))
    if not (local or staging_name):
        raise ValueError("Hostname must clearly identify an isolated staging/preview environment")
    if not local and parsed.scheme != "https":
        raise ValueError("Remote staging benchmark requires HTTPS")
    return parsed.scheme + "://" + (parsed.netloc or host)


def path_list(raw: str) -> list[str]:
    paths = [p.strip() for p in raw.split(",") if p.strip()]
    if not paths or len(paths) > 8:
        raise ValueError("Provide 1–8 read-only paths")
    for path in paths:
        if not path.startswith("/") or any(x in path for x in ("?", "#", "..", "//")):
            raise ValueError("Invalid read-only path")
        if path not in ("/", "/health/live", "/health/ready", "/health/queue", "/archive") and not path.startswith("/edition/"):
            raise ValueError("Path not approved for read-only benchmark")
    return paths


def percentile(values: list[float], fraction: float) -> float | None:
    if not values:
        return None
    ordered = sorted(values)
    return round(ordered[max(0, math.ceil(len(ordered) * fraction) - 1)], 2)


def measure(url: str, timeout: float) -> dict:
    start = time.perf_counter()
    opener = urllib.request.build_opener(NoRedirect())
    request = urllib.request.Request(url, headers={"Accept": "application/json,text/html;q=0.9", "Cache-Control": "no-cache", "User-Agent": "EPDI-Staging-Benchmark/1.0"}, method="GET")
    status, length, error = 0, 0, None
    try:
        with opener.open(request, timeout=timeout) as response:
            status = response.status
            length = len(response.read(512 * 1024))  # bounded payload read
    except urllib.error.HTTPError as exc:
        status, error = exc.code, f"HTTP {exc.code}"
    except (urllib.error.URLError, TimeoutError, OSError) as exc:
        error = type(exc).__name__
    return {"status": status, "ms": round((time.perf_counter() - start) * 1000, 2), "bytes_read": length, "ok": status == 200, "error": error}


def summarize(results: list[dict]) -> dict:
    durations = [item["ms"] for item in results]
    good = sum(1 for x in results if x["ok"])
    return {"requests": len(results), "successes": good, "failures": len(results) - good,
            "error_percent": round(100 * (len(results) - good) / max(len(results), 1), 2),
            "p50_ms": percentile(durations, .5), "p95_ms": percentile(durations, .95),
            "p99_ms": percentile(durations, .99), "max_ms": max(durations, default=None),
            "bytes_read": sum(x["bytes_read"] for x in results),
            "status_counts": {str(s): sum(1 for x in results if x["status"] == s) for s in sorted(set(x["status"] for x in results))}}


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--base-url", required=True)
    parser.add_argument("--expected-host", required=True)
    parser.add_argument("--confirm-isolated-staging", action="store_true", required=True)
    parser.add_argument("--paths", default="/health/live,/health/ready")
    parser.add_argument("--samples", type=int, default=30, help="Requests per path (1–300)")
    parser.add_argument("--concurrency", type=int, default=3, help="Concurrent GET requests (1–10)")
    parser.add_argument("--timeout", type=float, default=5, help="Per request seconds (1–15)")
    parser.add_argument("--max-p95-ms", type=float, default=1500, help="Explicit provisional staging threshold")
    parser.add_argument("--max-error-percent", type=float, default=1, help="Error budget (percent)")
    parser.add_argument("--output", help="Optional JSON report file path")
    args = parser.parse_args()
    origin = valid_origin(args.base_url, args.expected_host)
    paths = path_list(args.paths)
    if not 1 <= args.samples <= 300 or not 1 <= args.concurrency <= 10 or not 1 <= args.timeout <= 15:
        parser.error("Sample count, concurrency or timeout outside safe bound")
    if args.max_p95_ms <= 0 or not 0 <= args.max_error_percent <= 100:
        parser.error("Invalid benchmark threshold")
    report = {"kind": "isolated_staging_http_baseline", "timestamp_utc": datetime.now(timezone.utc).isoformat(),
              "origin": origin, "config": {"samples_per_path": args.samples, "concurrency": args.concurrency,
              "timeout_s": args.timeout, "max_p95_ms": args.max_p95_ms, "max_error_percent": args.max_error_percent},
              "paths": {}}
    all_pass = True
    for path in paths:
        with concurrent.futures.ThreadPoolExecutor(max_workers=args.concurrency) as pool:
            measures = list(pool.map(lambda _: measure(origin + path, args.timeout), range(args.samples)))
        values = summarize(measures)
        values["gate_pass"] = values["p95_ms"] is not None and values["p95_ms"] <= args.max_p95_ms and values["error_percent"] <= args.max_error_percent
        report["paths"][path] = values
        all_pass = all_pass and values["gate_pass"]
    report["overall_pass"] = all_pass
    encoded = json.dumps(report, indent=2, sort_keys=True)
    print(encoded)
    if args.output:
        with open(args.output, "w", encoding="utf-8") as handle:
            handle.write(encoded + "\n")
    return 0 if all_pass else 1


if __name__ == "__main__":
    raise SystemExit(main())
