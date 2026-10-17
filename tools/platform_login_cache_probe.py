#!/usr/bin/env python3
"""Four GET-only probes: distinguish per-session CSRF from edge caching. No secrets saved."""
import http.cookiejar
import json
import re
import urllib.error
import urllib.request
from datetime import datetime, timezone
from html.parser import HTMLParser
from pathlib import Path

BASE="https://platform.digiti.in"
PATHS=("/login","/login?utm_source=chatgpt.com")

class TokenParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.token=None
    def handle_starttag(self,tag,attrs):
        d=dict(attrs)
        if tag=="input" and d.get("name")=="_csrf":
            self.token=d.get("value")
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):return None

def get(url):
    jar=http.cookiejar.CookieJar()
    opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar),NoRedirect())
    req=urllib.request.Request(url,method="GET",headers={"User-Agent":"Digiti-ReadOnly-Edge-Session-Diagnostic/1.0","Accept":"text/html","Cache-Control":"no-cache"})
    try:
        with opener.open(req,timeout=9) as resp:
            status=resp.status
            raw=resp.read(70*1024)
            hdr=resp.info()
    except urllib.error.HTTPError as ex:
        status=ex.code;hdr=ex.headers;raw=ex.read(2048);ex.close()
    parser=TokenParser()
    parser.feed(raw.decode("utf8","replace"))
    sid=next((c.value for c in jar if c.name=="digiti_platform"),None)
    record={"status":status,"token_present":bool(parser.token),"session_cookie_present":bool(sid),
            "cf_cache_status":hdr.get("CF-Cache-Status","missing"),
            "age":hdr.get("Age","missing"),
            "cache_control":hdr.get("Cache-Control","missing")[:180],
            "vary":hdr.get("Vary","missing")[:100],
            "set_cookie_present":bool(hdr.get_all("Set-Cookie"))}
    return record,parser.token,sid

def main():
    rows=[]
    flags=[]
    for path in PATHS:
        first,first_token,first_sid=get(BASE+path)
        second,second_token,second_sid=get(BASE+path)
        token_unique=bool(first_token and second_token and first_token!=second_token)
        cookie_unique=bool(first_sid and second_sid and first_sid!=second_sid)
        flags.append(token_unique and cookie_unique)
        rows.append({"path":path,"fresh_1":first,"fresh_2":second,
                     "fresh_tokens_differ":token_unique,
                     "fresh_cookies_differ":cookie_unique,
                     "potential_cached_login":bool(first_token and second_token and first_token==second_token)})
    report={"checked_at_utc":datetime.now(timezone.utc).isoformat(),
            "scope":"GET-only /login and /login?utm_source=chatgpt.com; two independent cookie jars each",
            "no_sensitive_cookies_or_csrf_values_stored":True,
            "rows":rows,"per_session_token_isolation_pass":all(flags)}
    Path("platform-login-cache-fingerprint.json").write_text(json.dumps(report,indent=2)+"\n")
    print("Login edge/cache and fresh session report:")
    for r in rows:
        print(r["path"],"different CSRF across 2 cookie jars:",r["fresh_tokens_differ"],
              "different cookies:",r["fresh_cookies_differ"])
        for z in ("fresh_1","fresh_2"):
            v=r[z];print(" ",z,"HTTP",v["status"],"CF-Cache-Status",v["cf_cache_status"],
                         "Age",v["age"],"Cache-Control",v["cache_control"],
                         "Set-Cookie:",v["set_cookie_present"])
    return 0 if report["per_session_token_isolation_pass"] else 1

if __name__=="__main__":
    raise SystemExit(main())
