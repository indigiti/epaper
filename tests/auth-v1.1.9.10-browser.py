# -*- coding: utf-8 -*-
from pathlib import Path
from playwright.sync_api import sync_playwright
import json, sys

root=Path(__file__).resolve().parents[1]
base=(root/'public_html/assets/app.css').read_text()
auth=(root/'public_html/assets/auth-v1.1.9.10.css').read_text()
logo='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI1MjAiIGhlaWdodD0iMTAwIiB2aWV3Qm94PSIwIDAgNTIwIDEwMCI+PHJlY3Qgd2lkdGg9IjUyMCIgaGVpZ2h0PSIxMDAiIGZpbGw9IndoaXRlIiIvPjx0ZXh0IHg9IjgiIHk9IjY3IiBmb250LXNpemU9IjU0IiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtd2VpZ2h0PSI3MDAiIGZpbGw9ImJsYWNrIj5QdW5lVGltZXM8L3RleHQ+PHRleHQgeD0iMjgyIiB5PSI2NyIgZm9udC1zaXplPSI1NCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXdlaWdodD0iNzAwIiBmaWxsPSIjZTMxZDJkIj5NaXJyb3I8L3RleHQ+PC9zdmc+'
icon='''<svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>'''
quote='''<figure class="auth-fit-quote-card" aria-label="Editorial quote"><span class="auth-fit-quote-mark" aria-hidden="true">“</span><blockquote>Freedom of the press is not just important to democracy, it is democracy.</blockquote><figcaption>— Walter Cronkite</figcaption></figure>'''
panel=f'''<section class="auth-fit-panel"><span class="auth-fit-eyebrow">SECURE SIGN IN</span><div class="auth-fit-legacy-brand">EPAPER</div><h1>Welcome back</h1><p class="auth-fit-muted">Use your Digiti identity to access this ePaper.</p><form class="auth-fit-fields"><label>Email address<input type="email" autofocus></label><label>Password<input type="password"></label><label>Authenticator code <small>(if enabled)</small><input></label><button class="auth-fit-submit" type="submit">Sign in</button></form><p class="auth-fit-security">Authentication is verified securely by Digiti Platform. Your password is never stored by ePaper SaaS.</p>{quote}</section>'''

def doc():
    # Reproduce the live failure aggressively: wrapper/form/card are given huge heights by stale/global CSS.
    hostile='''.auth-fit-form{height:1100px!important;min-height:1100px!important;display:flex!important;flex-direction:column!important}.auth-fit-card{height:1300px!important;min-height:1300px!important}.auth-fit-panel{min-height:900px!important}.auth-fit-quote-card{margin-top:auto!important}'''
    return f'''<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>{base}</style><style>{hostile}</style><style>{auth}</style></head><body class="auth-shell theme-split-editorial" style="--brand-accent:#2563eb"><main class="auth-main"><section class="auth-fit auth-fit-v11910" data-auth-version="1.1.9.10"><a class="auth-fit-brand"><span class="auth-fit-logo-stack"><img class="auth-fit-logo auth-fit-logo-light" src="{logo}" alt="Pune Times Mirror"></span></a><div class="auth-fit-orbit auth-fit-orbit-one"></div><div class="auth-fit-orbit auth-fit-orbit-two"></div><div class="auth-fit-cloud auth-fit-cloud-one"></div><div class="auth-fit-cloud auth-fit-cloud-two"></div><div class="auth-fit-card"><div class="auth-fit-icon">{icon}</div><div class="auth-fit-form">{panel}</div></div></section></main></body></html>'''

fails=[]
def ck(ok,name,detail=''):
    print(('PASS' if ok else 'FAIL')+' — '+name+((' :: '+detail) if detail else ''))
    if not ok:fails.append(name)

def inside(inner,outer,tol=2):
    return inner and outer and inner['x']>=outer['x']-tol and inner['y']>=outer['y']-tol and inner['x']+inner['width']<=outer['x']+outer['width']+tol and inner['y']+inner['height']<=outer['y']+outer['height']+tol

with sync_playwright() as pw:
    browser=pw.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    sizes=[(1920,1080),(1440,900),(1024,768),(768,1024),(430,932),(390,844),(360,800),(320,720)]
    for w,h in sizes:
        p=browser.new_page(viewport={'width':w,'height':h}); errs=[]; p.on('pageerror',lambda e:errs.append(str(e)))
        p.set_content(doc(),wait_until='load'); p.wait_for_timeout(30)
        tag=f'{w}x{h}'
        card=p.locator('.auth-fit-card').bounding_box(); quote_box=p.locator('.auth-fit-quote-card').bounding_box(); sec=p.locator('.auth-fit-security').bounding_box(); h1=p.locator('.auth-fit-panel h1').bounding_box()
        form_display=p.locator('.auth-fit-form').evaluate("el=>getComputedStyle(el).display")
        sec_quote_gap=(quote_box['y']-(sec['y']+sec['height'])) if sec and quote_box else 999
        bottom_gap=((card['y']+card['height'])-(quote_box['y']+quote_box['height'])) if card and quote_box else 999
        top_gap=(h1['y']-card['y']) if card and h1 else 999
        ck(not errs,tag+' no JS errors')
        ck(form_display=='contents',tag+' wrapper cannot reserve height',form_display)
        ck(card and card['height']<620,tag+' card shrink-wraps content',str(card))
        ck(inside(quote_box,card),tag+' quote inside card')
        ck(sec_quote_gap<16,tag+' quote directly follows security note',f'{sec_quote_gap:.1f}px')
        ck(bottom_gap<24,tag+' no lower blank zone',f'{bottom_gap:.1f}px')
        ck(top_gap<82,tag+' no upper blank zone',f'{top_gap:.1f}px')
        p.close()
    for w,h,name in [(1440,900,'epaper_v11910_auth_desktop.png'),(390,844,'epaper_v11910_auth_mobile.png')]:
        p=browser.new_page(viewport={'width':w,'height':h});p.set_content(doc(),wait_until='load');p.screenshot(path=str(root.parent/name),full_page=True);p.close()
    browser.close()

if fails:
    print('\nFAILURES',json.dumps(fails,ensure_ascii=False));sys.exit(1)
print('\nALL v1.1.9.10 SINGLE-FLOW AUTH CHECKS PASSED')
