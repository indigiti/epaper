#!/usr/bin/env python3
"""One bounded synthetic-login CSRF diagnostic. No real account or credentials used."""
from __future__ import annotations
import http.cookiejar
import json
import os
import urllib.error
import urllib.parse
import urllib.request
import uuid
from datetime import datetime, timezone
from html.parser import HTMLParser
from pathlib import Path

URL = "https://platform.digiti.in/login"

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

class TokenParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.token = None
    def handle_starttag(self,tag,attrs):
        if tag != "input":return
        d = dict(attrs)
        if d.get("name") == "_csrf" and isinstance(d.get("value"),str):
            self.token = d["value"]

def fetch(opener, req):
    try:
        with opener.open(req,timeout=9) as response:
            return response.status, response.read(150*1024)
    except urllib.error.HTTPError as err:
        try:return err.code,err.read(150*1024)
        finally:err.close()

def main():
    cookies=http.cookiejar.CookieJar()
    opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookies),NoRedirect())
    headers={"User-Agent":"EPDI-Single-Synthetic-CSRF-Probe/1.0","Cache-Control":"no-store"}
    get_status, html = fetch(opener,urllib.request.Request(URL,headers=headers,method="GET"))
    parser=TokenParser()
    parser.feed(html.decode("utf-8",errors="replace"))
    token=parser.token
    session_cookie=any(c.name=="digiti_platform" for c in cookies)
    result={
      "utc":datetime.now(timezone.utc).isoformat(),
      "scope":"one GET and at most one POST to exact Platform login route",
      "real_credentials_submitted":False,
      "real_user_targeted":False,
      "changes_to_admin_database_requested":False,
      "get_http_status":get_status,
      "csrf_form_field_found":bool(token),
      "session_cookie_present":session_cookie,
      "synthetic_post_performed":False
    }
    if get_status == 200 and token and session_cookie:
        # Use non-routable example.invalid address. Never match or disclose a real account.
        form=urllib.parse.urlencode({
          "_csrf":token,
          "email":"csrf-probe-"+uuid.uuid4().hex+"@example.invalid",
          "password":"not-a-valid-login-"+uuid.uuid4().hex
        }).encode("utf-8")
        post_headers={**headers,"Content-Type":"application/x-www-form-urlencoded",
                       "Origin":"https://platform.digiti.in","Referer":URL}
        request=urllib.request.Request(URL,data=form,headers=post_headers,method="POST")
        status, response_body=fetch(opener,request)
        result["synthetic_post_performed"]=True
        result["post_http_status"]=status
        result["post_app_error_csrf_invalid"]=b"CSRF_INVALID" in response_body
        result["post_login_invalid_credentials_message"]=b"Invalid email, password or authenticator code" in response_body
        result["post_rate_limited"]=b"Too many sign-in attempts" in response_body
        result["cookie_remains_present"]=any(c.name=="digiti_platform" for c in cookies)
        result["csrf_validation_passed"]= bool(
            status == 200 and result["post_login_invalid_credentials_message"]
        )
        result["diagnostic"]="CSRF handshake ACCEPTED" if result["csrf_validation_passed"] else (
            "CSRF handshake REJECTED" if status==419 or result["post_app_error_csrf_invalid"] else
            "Unexpected POST result, see status"
        )
    else:
        result["diagnostic"]="No POST performed: GET failed to return both token and session cookie."
    Path("platform-login-synthetic-csrf.json").write_text(json.dumps(result,indent=2)+"\n")
    for key,v in result.items():
        print(key+": "+str(v))
    return 0 if result.get("csrf_validation_passed") else 1

if __name__ == "__main__":
    raise SystemExit(main())
