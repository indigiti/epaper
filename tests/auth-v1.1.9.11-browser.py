# -*- coding: utf-8 -*-
from pathlib import Path
from playwright.sync_api import sync_playwright
import json,sys
root=Path(__file__).resolve().parents[1]
auth=(root/'public_html/assets/auth-v1.1.9.11.css').read_text()
# Simulate aggressive legacy/theme geometry rules loaded before the dedicated auth stylesheet.
hostile='''
.auth-fit-card{height:1300px!important;min-height:1300px!important;display:flex!important;flex-direction:column!important;justify-content:space-between!important}
.auth-fit-content{height:1100px!important;min-height:1100px!important;display:flex!important;flex-direction:column!important;justify-content:space-between!important}
.auth-fit-fields{min-height:500px!important;justify-content:space-between!important}
.auth-fit-quote-card{margin-top:auto!important;margin-bottom:auto!important}
'''
icon='''<svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>'''
content='''<h1>Welcome back</h1><p class="auth-fit-muted">Use your Digiti identity to access this ePaper.</p><form class="auth-fit-fields"><label>Email address<input type="email"></label><label>Password<input type="password"></label><label>Authenticator code <small>(if enabled)</small><input></label><button class="auth-fit-submit" type="submit">Sign in</button></form><p class="auth-fit-security">Authentication is verified securely by Digiti Platform. Your password is never stored by ePaper SaaS.</p><figure class="auth-fit-quote-card"><span class="auth-fit-quote-mark">“</span><blockquote>Freedom of the press is not just important to democracy, it is democracy.</blockquote><figcaption>— Walter Cronkite</figcaption></figure>'''
def doc():
    return f'''<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>{hostile}</style><style>{auth}</style></head><body class="auth-shell theme-split-editorial" style="--brand-accent:#2563eb"><main class="auth-main"><section class="auth-fit auth-fit-v11911" data-auth-version="1.1.9.11"><a class="auth-fit-brand"><span class="auth-fit-identity"><span class="auth-fit-monogram">E</span><strong>EPAPER</strong></span></a><div class="auth-fit-orbit auth-fit-orbit-one"></div><div class="auth-fit-orbit auth-fit-orbit-two"></div><div class="auth-fit-card"><div class="auth-fit-icon">{icon}</div><div class="auth-fit-content">{content}</div></div></section></main></body></html>'''
fails=[]
def ck(ok,name,detail=''):
    print(('PASS' if ok else 'FAIL')+' — '+name+((' :: '+detail) if detail else ''))
    if not ok:fails.append(name)
with sync_playwright() as pw:
    browser=pw.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for w,h in [(1920,1080),(1440,900),(1024,768),(768,1024),(430,932),(390,844),(360,800),(320,720)]:
        p=browser.new_page(viewport={'width':w,'height':h});p.set_content(doc(),wait_until='load')
        tag=f'{w}x{h}'
        card=p.locator('.auth-fit-card').bounding_box(); h1=p.locator('.auth-fit-content>h1').bounding_box(); sec=p.locator('.auth-fit-security').bounding_box(); q=p.locator('.auth-fit-quote-card').bounding_box(); cont=p.locator('.auth-fit-content').bounding_box()
        style=p.locator('.auth-fit-content').evaluate("el=>({display:getComputedStyle(el).display,height:getComputedStyle(el).height,minHeight:getComputedStyle(el).minHeight})")
        top=h1['y']-card['y']; mid=q['y']-(sec['y']+sec['height']); bottom=(card['y']+card['height'])-(q['y']+q['height'])
        ck(card['height']<590,tag+' compact card',f"{card['height']:.1f}px")
        ck(cont['height']<520,tag+' intrinsic content stack',json.dumps(style))
        ck(top<70,tag+' upper marked space removed',f'{top:.1f}px')
        ck(mid<16,tag+' middle marked space removed',f'{mid:.1f}px')
        ck(bottom<24,tag+' lower marked space removed',f'{bottom:.1f}px')
        p.close()
    for w,h,name in [(1440,900,'epaper_v11911_auth_desktop.png'),(390,844,'epaper_v11911_auth_mobile.png')]:
        p=browser.new_page(viewport={'width':w,'height':h});p.set_content(doc(),wait_until='load');p.screenshot(path=str(root.parent/name),full_page=True);p.close()
    browser.close()
if fails:
    print('FAILURES',json.dumps(fails));sys.exit(1)
print('ALL v1.1.9.11 DIRECT-FLOW AUTH CHECKS PASSED')
