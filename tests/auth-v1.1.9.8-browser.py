# -*- coding: utf-8 -*-
from pathlib import Path
from playwright.sync_api import sync_playwright
import json, sys

root=Path(__file__).resolve().parents[1]
base=(root/'public_html/assets/app.css').read_text()
auth=(root/'public_html/assets/auth-v1.1.9.8.css').read_text()
logo='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI1MjAiIGhlaWdodD0iMTAwIiB2aWV3Qm94PSIwIDAgNTIwIDEwMCI+PHJlY3Qgd2lkdGg9IjUyMCIgaGVpZ2h0PSIxMDAiIGZpbGw9IndoaXRlIiIvPjx0ZXh0IHg9IjgiIHk9IjY3IiBmb250LXNpemU9IjU0IiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtd2VpZ2h0PSI3MDAiIGZpbGw9ImJsYWNrIj5QdW5lVGltZXM8L3RleHQ+PHRleHQgeD0iMjgyIiB5PSI2NyIgZm9udC1zaXplPSI1NCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXdlaWdodD0iNzAwIiBmaWxsPSIjZTMxZDJkIj5NaXJyb3I8L3RleHQ+PC9zdmc+'
icon='''<svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17 5-5-5-5"/><path d="M15 12H3"/></svg>'''
form='''<section class="auth-fit-panel"><span class="auth-fit-eyebrow">SECURE SIGN IN</span><div class="auth-fit-legacy-brand">EPAPER</div><h1>Welcome back</h1><p class="auth-fit-muted">Use your Digiti identity to access this ePaper.</p><form class="auth-fit-fields"><label>Email address<input type="email" autofocus></label><label>Password<input type="password"></label><label>Authenticator code <small>(if enabled)</small><input></label><button class="auth-fit-submit" type="submit">Sign in</button></form><p class="auth-fit-security">Authentication is verified securely in the background. Your password is never stored by ePaper SaaS.</p></section>'''
quote='''<figure class="auth-fit-quote-card" aria-label="Editorial quote"><span class="auth-fit-quote-mark" aria-hidden="true">“</span><blockquote>Comment is free, but facts are sacred.</blockquote><figcaption>— C. P. Scott</figcaption></figure>'''

def doc(extra=''):
    hostile='''.auth-panel,.auth-form{height:100vh!important;min-height:100vh!important}.auth-card{height:90vh!important}'''
    return f'''<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>{base}</style><style>{hostile}</style><style>{auth}</style><style>{extra}</style></head><body class="auth-shell theme-split-editorial" style="--brand-accent:#2563eb"><main class="auth-main"><section class="auth-fit auth-fit-v1198" data-auth-version="1.1.9.8"><a class="auth-fit-brand"><span class="auth-fit-logo-stack"><img class="auth-fit-logo auth-fit-logo-light" src="{logo}" alt="Pune Times Mirror"></span></a><div class="auth-fit-orbit auth-fit-orbit-one"></div><div class="auth-fit-orbit auth-fit-orbit-two"></div><div class="auth-fit-cloud auth-fit-cloud-one"></div><div class="auth-fit-cloud auth-fit-cloud-two"></div><div class="auth-fit-card"><div class="auth-fit-icon">{icon}</div><div class="auth-fit-form">{form}</div>{quote}</div></section></main></body></html>'''

fails=[]
def ck(ok,name,detail=''):
    print(('PASS' if ok else 'FAIL')+' — '+name+((' :: '+detail) if detail else ''))
    if not ok:fails.append(name)

def inside(inner,outer,tol=2):
    return inner and outer and inner['x']>=outer['x']-tol and inner['y']>=outer['y']-tol and inner['x']+inner['width']<=outer['x']+outer['width']+tol and inner['y']+inner['height']<=outer['y']+outer['height']+tol

with sync_playwright() as pw:
    browser=pw.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    sizes=[(1920,1080),(1600,900),(1440,900),(1366,768),(1280,720),(1024,768),(768,1024),(430,932),(390,844),(360,800),(320,720)]
    for w,h in sizes:
        p=browser.new_page(viewport={'width':w,'height':h}); errs=[];p.on('pageerror',lambda e:errs.append(str(e)))
        p.set_content(doc(),wait_until='load');p.wait_for_timeout(30)
        tag=f'{w}x{h}'
        card=p.locator('.auth-fit-card').bounding_box(); quote_box=p.locator('.auth-fit-quote-card').bounding_box(); mark=p.locator('.auth-fit-quote-mark').bounding_box(); submit=p.locator('.auth-fit-submit').bounding_box(); brand=p.locator('.auth-fit-brand').bounding_box()
        ck(not errs,tag+' no JS errors')
        ck(card and card['width']<=442,tag+' compact width',str(card))
        ck(card and card['height']<590,tag+' content-fit height',str(card))
        ck(inside(quote_box,card),tag+' editorial quote inside card')
        ck(inside(mark,quote_box),tag+' quote mark inside quote card')
        ck(inside(submit,card),tag+' submit inside card')
        ck(brand and brand['x']>=8 and brand['y']>=8,tag+' brand visible')
        ck(p.locator('.auth-fit-quote-card blockquote').inner_text().strip()!='',tag+' quote text visible')
        ck(p.locator('.auth-fit-quote-card figcaption').inner_text().strip().startswith('—'),tag+' quote author visible')
        if w<=640: ck(card and card['width']>=w-32,tag+' mobile width',str(card))
        p.close()
    for w,h,name in [(1440,900,'epaper_v1198_auth_desktop.png'),(390,844,'epaper_v1198_auth_mobile.png')]:
        p=browser.new_page(viewport={'width':w,'height':h});p.set_content(doc(),wait_until='load');p.screenshot(path=str(root.parent/name),full_page=True);p.close()
    browser.close()

if fails:
    print('\nFAILURES',json.dumps(fails,ensure_ascii=False));sys.exit(1)
print('\nALL v1.1.9.8 LUXURY QUOTE AUTH CHECKS PASSED')
