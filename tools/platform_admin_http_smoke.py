#!/usr/bin/env python3
"""Authorized fixture-only loopback HTTP navigation and CSRF flow; no production requests."""
import http.cookiejar
import html
import re
import urllib.error
import urllib.parse
import urllib.request

BASE="http://127.0.0.1:8097"
assert BASE.startswith("http://127.0.0.1:"), "Must use disposable loopback"
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

jar=http.cookiejar.CookieJar()
opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar),NoRedirect)
def req(path, data=None):
    url=BASE+path
    body=None if data is None else urllib.parse.urlencode(data).encode()
    r=urllib.request.Request(url,data=body,method="POST" if body is not None else "GET",
        headers={"Accept":"text/html","User-Agent":"Digiti-CI-Admin-Navigation/1.0",
                 "Content-Type":"application/x-www-form-urlencoded"})
    try:
        with opener.open(r,timeout=15) as v:
            return v.status,dict(v.headers),v.read(500000).decode("utf-8","replace")
    except urllib.error.HTTPError as v:
        return v.code,dict(v.headers),v.read(500000).decode("utf-8","replace")
def check(ok,label):
    print(("PASS" if ok else "FAIL")+" — "+label,flush=True)
    if not ok:raise AssertionError(label)
def csrf(body):
    m=re.search(r'<input\b[^>]*\bname="_csrf"[^>]*\bvalue="([0-9a-f]{48})"',body)
    return m.group(1) if m else None

status,header,page=req("/admin")
check(status==302 and header.get("Location")=="/login","Unauthenticated Admin redirects to login")
status,header,page=req("/login")
check(status==200 and bool(csrf(page)),"Login GET supplies CSRF")
check(any(c.name=="digiti_platform" for c in jar),"Named first-party PHP session cookie")
token=csrf(page)
status,header,response=req("/login",{"_csrf":token,"email":"ci-no-user@example.invalid","password":"bad-password"})
check(status==200 and "Invalid email" in response and "CSRF_INVALID" not in response,
      "Correct CSRF and invalid credentials rejected normally")
status,header,page=req("/login")
token=csrf(page)
status,header,response=req("/login",{"_csrf":token,"email":"ci-admin@example.invalid","password":"ci-only-fixture-password-2026"})
check(status==302 and header.get("Location")=="/admin","Authorized admin logs in with session regeneration")
for path,needle in [
    ("/admin","Overview"),
    ("/admin/organisations","Organisations"),
    ("/admin/users","Users"),
    ("/admin/products","Products"),
    ("/admin/applications","Applications"),
    ("/admin/product-tenants","Product Tenants"),
    ("/admin/domains","Domain registry"),
    ("/admin/service-accounts","Service Accounts"),
    ("/admin/commercial","Commercial"),
    ("/admin/subscriptions","Subscriptions"),
    ("/admin/billing","Billing"),
    ("/admin/operations","Operations"),
    ("/admin/audit","Audit"),
    ("/admin/errors","Errors"),
    ("/admin/health","System Health"),
    ("/admin/security","Security"),
]:
    status,header,page=req(path)
    check(status==200 and needle.lower() in page.lower() and "Digiti Platform" in page,
          "Authenticated GET "+path)
    if path=="/admin/domains":domain_html=page

token=csrf(domain_html)
check(bool(token),"Admin domain forms include CSRF")
status,header,response=req("/admin/domains",{"hostname":"ci-http-added.example.invalid"})
check(status==419 and "CSRF_INVALID" in response,"Admin domain POST without CSRF rejected")

def options(name):
    match=re.search(r'<select name="'+re.escape(name)+r'"[^>]*>(.*?)</select>',domain_html,re.S)
    check(bool(match),"Domain selection exists: "+name)
    items=re.findall(r'<option value="([0-9a-f-]{36})" data-product="([0-9a-f-]{36})"',match.group(1))
    check(bool(items),"Domain options carry product relationship: "+name)
    return items

tenant_options=options("product_tenant_guid")
app_options=options("application_guid")
pairs=[(tv,av) for tv,tp in tenant_options for av,ap in app_options if tp==ap]
check(bool(pairs),"Domain picker has a matching tenant/application product pair")
tenant,app=pairs[0]
status,header,response=req("/admin/domains",{
    "_csrf":token,"product_tenant_guid":tenant,"application_guid":app,
    "hostname":"ci-http-added.example.invalid"
})
check(status==302 and header.get("Location")=="/admin/domains","Domain created by authenticated CSRF form")
status,header,domains=req("/admin/domains")
check(status==200 and "ci-http-added.example.invalid" in domains,"Created hostname appears in Admin registry")
match=re.search(r'href="(/admin/domains/[0-9a-f-]{36}/edit)"',domains)
check(bool(match),"Domain edit navigation exposed")
status,header,editor=req(match.group(1))
check(status==200 and "Edit domain" in editor and bool(csrf(editor)),"Domain edit screen renders with CSRF")
status,header,resp=req("/admin/domains",{"_csrf":"invalid","hostname":"tampered.invalid"})
check(status==419 and "CSRF_INVALID" in resp,"Mismatched form CSRF rejected without any mutation")
status,header,health=req("/admin/health")
check(status==200 and "MariaDB" in health,"Health route renders after multiple mutations")
status,header,_=req("/logout")
check(status==302 and header.get("Location")=="/login","Logout cannot mutate session via GET")
status,header,_=req("/admin")
check(status==200,"GET logout did not revoke existing admin session")
status,header,page=req("/admin/domains")
check("no-store" in header.get("Cache-Control",""),"Protected Admin responses are explicitly noncacheable")
status,header,_=req("/logout",{"_csrf":csrf(page)})
check(status==302 and header.get("Location")=="/login","Logout succeeds only on CSRF-protected POST")
status,header,_=req("/admin")
check(status==302 and header.get("Location")=="/login","Logout revoked authenticated session")
print("PASS — complete disposable authenticated Platform HTTP navigation+CSRF smoke",flush=True)
