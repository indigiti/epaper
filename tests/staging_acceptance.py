#!/usr/bin/env python3
"""Non-destructive smoke checks for existing Cloudways staging domains.

Run from a network able to resolve both staging sites. Credentials are read
only from environment variables; no resource mutations are performed.
"""
import argparse
import http.cookiejar
import json
import os
import re
import sys
import urllib.error
import urllib.parse
import urllib.request

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

class Client:
    def __init__(self, base):
        parts = urllib.parse.urlsplit(base)
        if parts.scheme != "https" or not parts.netloc or parts.username or parts.password:
            raise ValueError("An HTTPS staging URL without embedded credentials is required")
        self.base = base.rstrip("/")
        self.client = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),
            NoRedirect()
        )
    def request(self, path, form=None, headers=None):
        data = None if form is None else urllib.parse.urlencode(form).encode()
        req = urllib.request.Request(self.base + path, data=data, headers=headers or {},
                                     method="POST" if form is not None else "GET")
        try:
            response = self.client.open(req, timeout=12)
        except urllib.error.HTTPError as e:
            response = e
        with response:
            return response.status, response.headers, response.read(262144).decode("utf-8", "replace")

def check(condition, message):
    if not condition:
        raise AssertionError(message)
    print("PASS — " + message, flush=True)

def as_json(response, name):
    try:
        data = json.loads(response[2])
    except (ValueError, TypeError):
        media = response[1].get("Content-Type", "unknown")
        raise AssertionError(f"{name} did not return JSON (HTTP {response[0]}, Content-Type {media})")
    check(isinstance(data, dict), name + " JSON response")
    return data

def find_csrf(html):
    match = re.search(r'<input\b[^>]*\bname=["\x27]_csrf["\x27][^>]*\bvalue=["\x27]([^"\x27]+)', html)
    return match.group(1) if match else None

def platform(client):
    result = client.request("/health/live")
    data = as_json(result, "Platform live")
    check(result[0] == 200 and data.get("ok") is True and data.get("status") == "LIVE",
          "Platform liveness")
    result = client.request("/admin")
    check(result[0] in (301, 302, 303) and result[1].get("Location", "").endswith("/login"),
          "Anonymous Platform admin access denied")
    login = client.request("/login")
    csrf = find_csrf(login[2])
    check(login[0] == 200 and bool(csrf), "Platform login form and CSRF token")
    rejected = client.request("/login", {"_csrf": "invalid-csrf", "email": "nobody@invalid.test",
                                         "password": "invalid"})
    check(rejected[0] == 419 and as_json(rejected, "CSRF denial").get("code") == "CSRF_INVALID",
          "Invalid CSRF rejected before login")
    denied = client.request("/internal/v1/domains/resolve?hostname=example.invalid")
    check(denied[0] == 401 and as_json(denied, "Service auth denial").get("code") == "SERVICE_AUTH_REQUIRED",
          "Internal domain resolver requires service token")
    email, password = os.getenv("STAGING_PLATFORM_EMAIL"), os.getenv("STAGING_PLATFORM_PASSWORD")
    if not email or not password:
        print("SKIP — authenticated Platform checks (no staging credentials configured)")
        return
    login = client.request("/login", {"_csrf": csrf, "email": email, "password": password,
                                      "totp": os.getenv("STAGING_PLATFORM_TOTP", "")})
    check(login[0] in (301, 302, 303) and login[1].get("Location", "").endswith("/admin"),
          "Platform admin login")
    for path in ("/admin", "/admin/domains", "/admin/product-tenants",
                 "/admin/organisations", "/admin/audit"):
        page = client.request(path)
        check(page[0] == 200 and "Digiti Platform" in page[2], "Platform authenticated GET " + path)
    no_csrf = client.request("/admin/domains", {"_csrf": "invalid-csrf"})
    check(no_csrf[0] == 419 and as_json(no_csrf, "Admin CSRF denial").get("code") == "CSRF_INVALID",
          "Authenticated domain mutations reject invalid CSRF")
    token, tenant = os.getenv("STAGING_SERVICE_TOKEN"), os.getenv("STAGING_TENANT_GUID")
    if token and tenant:
        check(bool(re.fullmatch(r"[0-9a-fA-F-]{36}", tenant)), "Tenant GUID format")
        headers = {"X-Digiti-Service-Token": token}
        for path in (f"/internal/v1/tenants/{tenant}",
                     f"/internal/v1/tenants/{tenant}/access",
                     f"/internal/v1/tenants/{tenant}/entitlements"):
            response = client.request(path, headers=headers)
            data = as_json(response, "Tenant scope " + path)
            check(response[0] == 200 and data.get("ok") is True,
                  "Valid service scope returns scoped tenant data " + path)
            if path.endswith("/access") and os.getenv("STAGING_EXPECT_ACCESS_STATE"):
                check(data.get("service_access_state") == os.getenv("STAGING_EXPECT_ACCESS_STATE"),
                      "Tenant access state reflects staging expectation")
    else:
        print("SKIP — tenant service scopes (set STAGING_SERVICE_TOKEN + STAGING_TENANT_GUID)")
    logout = client.request("/logout")
    check(logout[0] in (301, 302, 303), "Logout")
    after = client.request("/admin")
    check(after[0] in (301, 302, 303), "Logged-out session cannot view admin")

def epaper(client):
    result = client.request("/health/live")
    data = as_json(result, "ePaper live")
    check(result[0] == 200 and data.get("ok") is True and data.get("status") == "LIVE",
          "ePaper liveness")
    for path in ("/", "/archive"):
        page = client.request(path)
        check(page[0] == 200 and "<html" in page[2].lower(), "ePaper public GET " + path)
    edition = os.getenv("STAGING_EDITION_SLUG")
    if edition:
        reader = client.request("/edition/" + urllib.parse.quote(edition, safe="") + "/page/1")
        check(reader[0] == 200, "Reader first page responds")
    else:
        print("SKIP — Reader route (set STAGING_EDITION_SLUG)")
    clip = os.getenv("STAGING_PUBLIC_CLIP_SLUG")
    if clip:
        result = client.request("/c/" + urllib.parse.quote(clip, safe=""))
        check(result[0] == 200, "Existing public clipping is readable")
    else:
        print("SKIP — public clip (set STAGING_PUBLIC_CLIP_SLUG)")
    print("NOTE — PDF upload, clipping edits, AI review, page reordering,")
    print("       recovery and tenant suspension need separate disposable test fixtures.")

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--platform", default="https://platform.digiti.in")
    parser.add_argument("--epaper", default="https://epaper.digiti.in")
    parser.add_argument("--only", choices=("platform", "epaper", "both"), default="both")
    args = parser.parse_args()
    try:
        if args.only in ("platform", "both"):
            platform(Client(args.platform))
        if args.only in ("epaper", "both"):
            epaper(Client(args.epaper))
    except (AssertionError, ValueError, urllib.error.URLError, TimeoutError) as exc:
        print("FAIL/BLOCKED — " + str(exc), file=sys.stderr)
        return 1
    print("PASS — available acceptance checks completed; skipped tests are NOT validated")
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
