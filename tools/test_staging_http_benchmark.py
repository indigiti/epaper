#!/usr/bin/env python3
"""Offline tests: no real HTTP connections."""
import importlib.util
import pathlib
import unittest


spec = importlib.util.spec_from_file_location("benchmark", pathlib.Path(__file__).with_name("staging_http_benchmark.py"))
bench = importlib.util.module_from_spec(spec)
spec.loader.exec_module(bench)


class Safety(unittest.TestCase):
    def test_isolated_preview(self):
        self.assertEqual(bench.valid_origin("https://epaper-staging.example.org", "epaper-staging.example.org"),
                         "https://epaper-staging.example.org")
        self.assertEqual(bench.valid_origin("http://127.0.0.1:8000", "127.0.0.1"), "http://127.0.0.1:8000")

    def test_production_and_ssrf_guard(self):
        for url, host in [
            ("https://epaper.digiti.in", "epaper.digiti.in"),
            ("http://platform-staging.example.org", "platform-staging.example.org"),
            ("https://preview.example.org@evil.org", "evil.org"),
            ("https://preview.example.org/admin", "preview.example.org"),
            ("https://preview.example.org", "different-staging.example.org"),
        ]:
            with self.subTest(url=url), self.assertRaises(ValueError):
                bench.valid_origin(url, host)

    def test_only_read_paths(self):
        self.assertEqual(bench.path_list("/,/health/live,/edition/demo/page/1"),
                         ["/", "/health/live", "/edition/demo/page/1"])
        for raw in ["/admin", "/internal/v1/usage", "/?x=1", "/x/../admin", "http://evil",
                    "/health/live,/webhooks/stripe"]:
            with self.subTest(raw=raw), self.assertRaises(ValueError):
                bench.path_list(raw)

    def test_statistics_and_gate_inputs(self):
        results = [{"ms": float(i), "ok": i < 100, "status": 200 if i < 100 else 503, "bytes_read": 100}
                   for i in range(1, 101)]
        summary = bench.summarize(results)
        self.assertEqual(summary["p50_ms"], 50.0)
        self.assertEqual(summary["p95_ms"], 95.0)
        self.assertEqual(summary["p99_ms"], 99.0)
        self.assertEqual(summary["failures"], 1)
        self.assertEqual(summary["bytes_read"], 10000)


if __name__ == "__main__":
    unittest.main()
