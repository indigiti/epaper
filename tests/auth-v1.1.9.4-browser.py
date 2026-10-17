# -*- coding: utf-8 -*-
from pathlib import Path
from playwright.sync_api import sync_playwright
import json, sys

root=Path(__file__).resolve().parents[1]
css=(root/'public_html/assets/app.css').read_text()
logo='data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="520" height="100" viewBox="0 0 520 100"%3E%3Crect width="520" height="100" fill="white"/%3E%3Ctext x="8" y="67" font-size="54" font-family="Arial" font-weight="700" fill="black"%3EPuneTimesMirror%3C/text%3E%3C/svg%3E'
features='''<div class="auth-feature-list"><article><span class="auth-feature-icon"></span><div><strong>Today’s ePaper</strong><p>Access the latest edition in a familiar, easy-to-read digital format.</p></div></article><article><span class="auth-feature-icon"></span><div><strong>Clipping &amp; Sharing Tools</strong><p>Save, organize, download, and share branded article clippings with ease.</p></div></article></div>'''
form='''<section class="auth-panel"><span class="eyebrow">SECURE SIGN IN</span><h1>Welcome back</h1><p class="muted">Use your Digiti identity to access this ePaper.</p><form class="auth-form"><label>Email address<input type="email" autofocus></label><label>Password<input type="password"></label><label>Authenticator code <small>(if enabled)</small><input></label><button class="btn primary auth-submit" type="submit">Sign in</button></form><p class="auth-security">Authentication is verified securely by Digiti Platform. Your password is never stored by ePaper SaaS.</p></section>'''

def doc(quote='A critical, independent and investigative press is the lifeblood of any democracy.'):
    return f'''<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style></head><body class="auth-shell theme-split-editorial" style="--brand-accent:#2f6fed"><main id="main" class="auth-main"><section class="auth-experience"><aside class="auth-story"><div class="auth-story-brand"><span class="auth-publisher-logo-stack"><img class="auth-publisher-logo auth-publisher-logo-dark" src="{logo}" alt="Pune Times Mirror"></span><span>The City. The Stories. The Truth.</span></div><span class="auth-product-pill">DIGITAL EPAPER</span><h1>Your newspaper. Your archive. Your reading library — all in one place.</h1><p class="auth-story-intro">Read today’s edition, explore past issues, save important clippings, and manage your subscription through one seamless digital experience.</p>{features}<figure class="auth-quote"><blockquote>“{quote}”</blockquote><figcaption>— Nelson Mandela</figcaption></figure></aside><div class="auth-form-stage"><div class="auth-form-stage-inner">{form}</div></div></section></main></body></html>'''

fails=[]
def check(ok,name):
    print(('PASS' if ok else 'FAIL')+' — '+name)
    if not ok:fails.append(name)

def inside(inner,outer,tol=2):
    return inner and outer and inner['x']>=outer['x']-tol and inner['y']>=outer['y']-tol and inner['x']+inner['width']<=outer['x']+outer['width']+tol and inner['y']+inner['height']<=outer['y']+outer['height']+tol

viewports=[(2048,1202),(2047,1050),(1920,1080),(1440,900),(1366,768),(1280,720),(1024,768),(390,844)]
with sync_playwright() as pw:
    browser=pw.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for w,h in viewports:
        p=browser.new_page(viewport={'width':w,'height':h})
        errs=[];p.on('pageerror',lambda e:errs.append(str(e)))
        p.set_content(doc(),wait_until='load');p.wait_for_timeout(80)
        tag=f'{w}x{h}'
        exp=p.locator('.auth-experience').bounding_box();story=p.locator('.auth-story').bounding_box();stage=p.locator('.auth-form-stage').bounding_box()
        brand=p.locator('.auth-story-brand').bounding_box();quote=p.locator('.auth-quote').bounding_box();formbox=p.locator('.auth-form-stage-inner').bounding_box();security=p.locator('.auth-security').bounding_box()
        check(not errs,tag+' no runtime errors')
        check(p.locator('text=Searchable Archive').count()==0,tag+' Searchable Archive absent')
        check(p.locator('.auth-feature-list article').count()==2,tag+' two feature rows')
        check(p.locator('.auth-control-plane-note').count()==0,tag+' duplicate footer note removed')
        if w>900:
            check(exp and exp['width']<=982 and exp['width']>=900,tag+' compact desktop width')
            check(exp and exp['height']>=500 and exp['height']<=600,tag+' content-sized compact height')
            check(exp and exp['width']/exp['height']>=1.62,tag+' landscape proportion')
            check(inside(brand,story),tag+' brand fully visible')
            check(inside(quote,story),tag+' quote fully visible')
            check(inside(formbox,stage),tag+' form fully visible')
            check(inside(security,stage),tag+' security copy fully visible')
            sm=p.locator('.auth-story').evaluate('(e)=>({top:e.scrollTop,sh:e.scrollHeight,ch:e.clientHeight,ov:getComputedStyle(e).overflow})')
            p.locator('.auth-story').evaluate('(e)=>{e.scrollTop=250}')
            sm2=p.locator('.auth-story').evaluate('(e)=>({top:e.scrollTop,sh:e.scrollHeight,ch:e.clientHeight,ov:getComputedStyle(e).overflow})')
            check(sm['ov']=='clip' and sm2['top']==0,tag+' story cannot retain hidden scroll offset')
            check(sm['sh']<=sm['ch']+1,tag+' story content does not overflow')
            body=p.evaluate('()=>({sh:document.documentElement.scrollHeight,ih:innerHeight})')
            check(body['sh']<=body['ih']+2,tag+' normal desktop page has no vertical document scroll')
        else:
            check(stage and story and stage['y']<story['y'],tag+' mobile sign-in first')
        p.close()

    # Simulate browser-level scale variation by applying page zoom after layout.
    for z in [0.8,1.0,1.25,1.5]:
        p=browser.new_page(viewport={'width':1600,'height':1000})
        p.set_content(doc(),wait_until='load')
        p.evaluate(f"document.documentElement.style.zoom='{z}'")
        p.wait_for_timeout(60)
        exp=p.locator('.auth-experience').bounding_box();story=p.locator('.auth-story').bounding_box();stage=p.locator('.auth-form-stage').bounding_box();brand=p.locator('.auth-story-brand').bounding_box();quote=p.locator('.auth-quote').bounding_box();formbox=p.locator('.auth-form-stage-inner').bounding_box()
        check(inside(brand,story),f'zoom {z} brand visible')
        check(inside(quote,story),f'zoom {z} quote visible')
        check(inside(formbox,stage),f'zoom {z} form visible')
        p.close()

    # Screenshot exact user-like viewport.
    p=browser.new_page(viewport={'width':2048,'height':1202})
    p.set_content(doc('Freedom of the press is not just important to democracy, it is democracy.'),wait_until='load')
    p.screenshot(path=str(root.parent/'epaper_v1194_auth_stable.png'),full_page=True)
    p.close();browser.close()

if fails:
    print('\nFAILURES',json.dumps(fails,ensure_ascii=False));sys.exit(1)
print('\nALL v1.1.9.4 STABLE AUTH CHECKS PASSED')
