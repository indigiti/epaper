#!/usr/bin/env python3
"""Unauthenticated GET-only production login CSRF/session/cache diagnostic."""
from __future__ import annotations
import http.cookiejar
import json
import os
import re
import urllib.error
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

ORIGIN = "https://platform.digiti.in"
CSRF = re.compile(rb'<input\b[^>]*\bname=["\x27]_csrf["\x27][^>]*\bvalue=["\x27]([a-f0-9]{48,96})["\x27]', re.I)
CSRF_ALT = re.compile(rb'<input\b[^>]*\bvalue=["\x27]([a-f0-9]{48,96})["\x27][^>]*\bname=["\x27]_csrf["\x27]', re.I)

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

def session():
    cookies = http.cookiejar.CookieJar()
    opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookies), NoRedirect())
    return cookies,opener

def get_login(opener, cookies):
    req=urllib.request.Request(ORIGIN+"/login",method="GET",headers={"Cache-Control":"no-cache","User-Agent":"EPDI-Login-CSRF-Diagnostic/1.0"})
    try:
        with opener.open(req,timeout=8) as resp:
            status=resp.status
            headers=resp.info()
            raw=resp.read(96*1024)
    except urllib.error.HTTPError as ex:
        status=ex.code
        headers=ex.headers
        raw=ex.read(4096)
    except (urllib.error.URLError, TimeoutError, OSError) as ex:
        return {"http_status":0,"error_type":type(ex).__name__},None
    match=CSRF.search(raw) or CSRF_ALT.search(raw)
    # Token and raw cookies deliberately never stored or emitted.
    token=match.group(1) if match else None
    sess=[c for c in cookies if c.name=="digiti_platform"]
    cc=headers.get("Cache-Control","")
    return {
       "http_status":status,
       "content_type_html":"text/html" in headers.get("Content-Type","").lower(),
       "form_csrf_present":bool(token),
       "platform_session_cookie_present":bool(sess),
       "session_cookie_secure":bool(sess and sess[0].secure),
       "session_cookie_http_only":bool(sess and sess[0].has_nonstandard_attr("HttpOnly")),
       "session_cookie_path_root":bool(sess and sess[0].path=="/"),
       "cache_control_has_no_store":"no-store" in cc.lower(),
       "cache_control_has_private":"private" in cc.lower(),
       "cache_control_value":cc[:160],  # header only; no sensitive data
       "response_has_set_cookie":bool(headers.get_all("Set-Cookie",[]))
    },token

def main():
    jar1,client1=session()
    first,first_token=get_login(client1,jar1)
    repeat,repeat_token=get_login(client1,jar1)
    jar2,client2=session()
    distinct,distinct_token=get_login(client2,jar2)
    same_session_csrf_stable=bool(first_token and repeat_token and first_token==repeat_token)
    fresh_session_csrf_unique=bool(first_token and distinct_token and first_token!=distinct_token)
    status_ok=all(row["http_status"]==200 for row in (first,repeat,distinct))
    cookie_ok=all(row["platform_session_cookie_present"] and row["session_cookie_secure"] and row["session_cookie_http_only"] for row in (first,repeat,distinct))
    no_store=all(row["cache_control_has_no_store"] for row in (first,repeat,distinct))
    report={
      "kind":"platform_login_get_only_cookie_csrf_diagnostic",
      "at_utc":datetime.now(timezone.utc).isoformat(),
      "host":"platform.digiti.in",
      "no_post_no_login_credentials_no_tokens_logged":True,
      "http_status_ok":status_ok,
      "cookie_ok":cookie_ok,
      "same_session_csrf_stable":same_session_csrf_stable,
      "fresh_session_csrf_unique":fresh_session_csrf_unique,
      "cache_headers_no_store":no_store,
      "runs":{"fresh_first":first,"same_session_repeat":repeat,"fresh_second":distinct},
      "overall_diagnostic_pass":all((status_ok,cookie_ok,same_session_csrf_stable,fresh_session_csrf_unique,no_store))
    }
    Path("platform-login-session-diagnostic.json").write_text(json.dumps(report,indent=2)+"\n")
    print("Platform GET-only login/session diagnostics:")
    for key in ("http_status_ok","cookie_ok","same_session_csrf_stable","fresh_session_csrf_unique","cache_headers_no_store","overall_diagnostic_pass"):
       print(f"  {key}: {report[key]}")
    for name,row in report["runs"].items():
       print(f"  {name}: HTTP={row['http_status']}, html={row.get('content_type_html')}, csrf_present={row.get('form_csrf_present')}, cookie_present={row.get('platform_session_cookie_present')}, cache_control={row.get('cache_control_value','')}")
    # Nonzero triggers an explicit review rather than claiming login is fixed.
    return 0 if report["overall_diagnostic_pass"] else 1

if __name__=="__main__":
    raise SystemExit(main())
