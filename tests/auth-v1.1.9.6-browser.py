# -*- coding: utf-8 -*-
from pathlib import Path
from playwright.sync_api import sync_playwright
import json, sys

root=Path(__file__).resolve().parents[1]
base=(root/'public_html/assets/app.css').read_text()
auth=(root/'public_html/assets/auth-v1.1.9.6.css').read_text()
logo='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI1MjAiIGhlaWdodD0iMTAwIiB2aWV3Qm94PSIwIDAgNTIwIDEwMCI+PHJlY3Qgd2lkdGg9IjUyMCIgaGVpZ2h0PSIxMDAiIHJ4PSIzIiBmaWxsPSJ3aGl0ZSIvPjx0ZXh0IHg9IjgiIHk9IjY3IiBmb250LXNpemU9IjU0IiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtd2VpZ2h0PSI3MDAiIGZpbGw9ImJsYWNrIj5QdW5lVGltZXM8L3RleHQ+PHRleHQgeD0iMjgyIiB5PSI2NyIgZm9udC1zaXplPSI1NCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXdlaWdodD0iNzAwIiBmaWxsPSIjZTMxZDJkIj5NaXJyb3I8L3RleHQ+PC9zdmc+'
form='''<section class="auth-panel"><span class="eyebrow">SECURE SIGN IN</span><h1>Welcome back</h1><p class="muted">Use your Digiti identity to access this ePaper.</p><form class="auth-form"><label>Email address<input type="email" autofocus></label><label>Password<input type="password"></label><label>Authenticator code <small>(if enabled)</small><input></label><button class="btn primary auth-submit" type="submit">Sign in</button></form><p class="auth-security">Authentication is verified securely by Digiti Platform. Your password is never stored by ePaper SaaS.</p></section>'''
icon='''<svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-5 14.5-5 16 0"/></svg>'''

def doc():
    return f'''<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>{base}</style><style>{auth}</style></head><body class="auth-shell theme-split-editorial" style="--brand-accent:#2563eb"><main class="auth-main"><section class="auth-simple auth-simple-v1196"><a class="auth-simple-brand" href="/"><span class="auth-simple-logo-stack"><img class="auth-simple-logo auth-simple-logo-light" src="{logo}" alt="Pune Times Mirror"><img class="auth-simple-logo auth-simple-logo-dark" src="{logo}" alt="Pune Times Mirror"></span></a><div class="auth-simple-orbit auth-simple-orbit-one"></div><div class="auth-simple-orbit auth-simple-orbit-two"></div><div class="auth-simple-cloud auth-simple-cloud-one"></div><div class="auth-simple-cloud auth-simple-cloud-two"></div><div class="auth-simple-card"><div class="auth-simple-icon">{icon}</div><div class="auth-simple-form">{form}</div></div><p class="auth-simple-quote">“The press is the immune system of democracy.” <span>— Roy Greenslade</span></p></section></main></body></html>'''

fails=[]
def ck(ok,name,detail=''):
    print(('PASS' if ok else 'FAIL')+' — '+name+((' :: '+detail) if detail else ''))
    if not ok:fails.append(name)

def inside(inner,outer,tol=2):
    return inner and outer and inner['x']>=outer['x']-tol and inner['y']>=outer['y']-tol and inner['x']+inner['width']<=outer['x']+outer['width']+tol and inner['y']+inner['height']<=outer['y']+outer['height']+tol

with sync_playwright() as pw:
    browser=pw.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for w,h in [(2560,1440),(2048,1202),(1920,1080),(1600,900),(1440,900),(1366,768),(1280,720),(1024,768),(768,1024),(430,932),(390,844),(360,800),(320,720)]:
        p=browser.new_page(viewport={'width':w,'height':h})
        errs=[];p.on('pageerror',lambda e:errs.append(str(e)))
        p.set_content(doc(),wait_until='load');p.wait_for_timeout(40)
        tag=f'{w}x{h}'
        card=p.locator('.auth-simple-card').bounding_box(); brand=p.locator('.auth-simple-brand').bounding_box(); formbox=p.locator('.auth-simple-form').bounding_box(); submit=p.locator('.auth-submit').bounding_box(); security=p.locator('.auth-security').bounding_box(); quote=p.locator('.auth-simple-quote').bounding_box(); iconbox=p.locator('.auth-simple-icon').bounding_box()
        ck(not errs,tag+' no JS errors')
        ck(card and card['width']<=502,tag+' card max width',str(card))
        ck(card and card['x']>=8 and card['x']+card['width']<=w-8,tag+' card inside viewport',str(card))
        ck(inside(formbox,card),tag+' full form inside card')
        ck(inside(submit,card),tag+' submit inside card')
        ck(inside(security,card),tag+' security copy inside card')
        ck(iconbox and iconbox['x']>=card['x'] and iconbox['x']+iconbox['width']<=card['x']+card['width'],tag+' icon centered over card')
        ck(brand and brand['x']>=12 and brand['y']>=12 and brand['x']+brand['width']<=w-12,tag+' brand visible')
        ck(p.locator('.auth-story').count()==0 and p.locator('.auth-form-stage').count()==0,tag+' legacy split auth removed')
        if h>=768:
            ck(quote and quote['y']>=card['y']+card['height']-2,tag+' quote below card')
        if w<=720:
            ck(card and card['width']>=w-40,tag+' mobile card uses width',str(card))
        p.close()

    for w,h,name in [(1440,900,'epaper_v1196_auth_desktop.png'),(390,844,'epaper_v1196_auth_mobile.png')]:
        p=browser.new_page(viewport={'width':w,'height':h});p.set_content(doc(),wait_until='load');p.screenshot(path=str(root.parent/name),full_page=True);p.close()
    browser.close()

if fails:
    print('\nFAILURES',json.dumps(fails,ensure_ascii=False));sys.exit(1)
print('\nALL v1.1.9.6 SIMPLE AUTH CHECKS PASSED')
