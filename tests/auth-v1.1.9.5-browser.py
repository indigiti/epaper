# -*- coding: utf-8 -*-
from pathlib import Path
from playwright.sync_api import sync_playwright
import json, sys

root=Path(__file__).resolve().parents[1]
base=(root/'public_html/assets/app.css').read_text()
auth=(root/'public_html/assets/auth-v1.1.9.5.css').read_text()
logo='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI1MjAiIGhlaWdodD0iMTAwIiB2aWV3Qm94PSIwIDAgNTIwIDEwMCI+PHJlY3Qgd2lkdGg9IjUyMCIgaGVpZ2h0PSIxMDAiIHJ4PSIzIiBmaWxsPSJ3aGl0ZSIvPjx0ZXh0IHg9IjgiIHk9IjY3IiBmb250LXNpemU9IjU0IiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtd2VpZ2h0PSI3MDAiIGZpbGw9ImJsYWNrIj5QdW5lVGltZXM8L3RleHQ+PHRleHQgeD0iMjgyIiB5PSI2NyIgZm9udC1zaXplPSI1NCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXdlaWdodD0iNzAwIiBmaWxsPSIjZTMxZDJkIj5NaXJyb3I8L3RleHQ+PC9zdmc+'
brand=f'''<div class="auth-story-brand"><span class="auth-publisher-logo-stack"><img class="auth-publisher-logo auth-publisher-logo-light" src="{logo}" alt="Pune Times Mirror"><img class="auth-publisher-logo auth-publisher-logo-dark" src="{logo}" alt="Pune Times Mirror"></span><span class="auth-brand-tagline">The City. The Stories. The Truth.</span></div>'''
features='''<div class="auth-feature-list"><article><span class="auth-feature-icon"><svg class="ui-icon" viewBox="0 0 24 24"></svg></span><div><strong>Today’s ePaper</strong><p>Access the latest edition in a familiar, easy-to-read digital format.</p></div></article><article><span class="auth-feature-icon"><svg class="ui-icon" viewBox="0 0 24 24"></svg></span><div><strong>Clipping &amp; Sharing Tools</strong><p>Save, organize, download, and share branded article clippings with ease.</p></div></article></div>'''
form='''<section class="auth-panel"><span class="eyebrow">SECURE SIGN IN</span><h1>Welcome back</h1><p class="muted">Use your Digiti identity to access this ePaper.</p><form class="auth-form"><label>Email address<input type="email" autofocus></label><label>Password<input type="password"></label><label>Authenticator code <small>(if enabled)</small><input></label><button class="btn primary auth-submit" type="submit">Sign in</button></form><p class="auth-security">Authentication is verified securely by Digiti Platform. Your password is never stored by ePaper SaaS.</p></section>'''

def doc(quote='The press is the immune system of democracy.'):
    story_top=brand+'''<span class="auth-product-pill">DIGITAL EPAPER</span><h1>Your newspaper. Your archive. Your reading library — all in one place.</h1><p class="auth-story-intro">Read today’s edition, explore past issues, save important clippings, and manage your subscription through one seamless digital experience.</p>'''+features
    mobile='<div class="auth-mobile-brand">'+brand+'</div>'
    return f'''<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>{base}</style><style>{auth}</style></head><body class="auth-shell theme-split-editorial" style="--brand-accent:#2f6fed"><main class="auth-main"><section class="auth-experience auth-experience-v1195"><aside class="auth-story"><div class="auth-story-top">{story_top}</div><figure class="auth-quote"><blockquote>“{quote}”</blockquote><figcaption>— Roy Greenslade</figcaption></figure></aside><div class="auth-form-stage">{mobile}<div class="auth-form-stage-inner">{form}</div></div></section></main></body></html>'''

fails=[]
def check(ok,name,detail=''):
    print(('PASS' if ok else 'FAIL')+' — '+name+((' :: '+detail) if detail else ''))
    if not ok:fails.append(name)
def inside(inner,outer,tol=2):
    return inner and outer and inner['x']>=outer['x']-tol and inner['y']>=outer['y']-tol and inner['x']+inner['width']<=outer['x']+outer['width']+tol and inner['y']+inner['height']<=outer['y']+outer['height']+tol

with sync_playwright() as pw:
    browser=pw.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for w,h in [(2560,1440),(2048,1202),(1920,1080),(1600,900),(1440,900),(1366,768),(1280,720),(1024,768),(900,900),(768,1024),(430,932),(390,844),(360,800)]:
        p=browser.new_page(viewport={'width':w,'height':h})
        errs=[];p.on('pageerror',lambda e:errs.append(str(e)))
        p.set_content(doc(),wait_until='load');p.wait_for_timeout(50)
        tag=f'{w}x{h}'
        exp=p.locator('.auth-experience').bounding_box();story=p.locator('.auth-story').bounding_box();stage=p.locator('.auth-form-stage').bounding_box();formbox=p.locator('.auth-form-stage-inner').bounding_box();quote=p.locator('.auth-quote').bounding_box();top=p.locator('.auth-story-top').bounding_box();submit=p.locator('.auth-submit').bounding_box();security=p.locator('.auth-security').bounding_box()
        check(not errs,tag+' no JS errors')
        check(p.locator('text=Searchable Archive').count()==0,tag+' Searchable Archive absent')
        check(p.locator('.auth-feature-list article').count()==2,tag+' exactly two feature rows')
        if w>900:
            check(exp and exp['width']<=1082 and exp['width']>=900,tag+' original-layout desktop width',str(exp))
            check(exp and exp['height']>=550 and exp['height']<=700,tag+' compact landscape height',str(exp))
            check(exp and exp['width']/exp['height']>1.5,tag+' landscape aspect')
            check(inside(top,story),tag+' complete story header/content visible')
            check(inside(quote,story),tag+' quote visible')
            check(inside(formbox,stage),tag+' complete form block visible')
            check(inside(submit,stage),tag+' submit visible')
            check(inside(security,stage),tag+' security copy visible')
            st=p.locator('.auth-story').evaluate('(e)=>({scrollTop:e.scrollTop,sh:e.scrollHeight,ch:e.clientHeight,transform:getComputedStyle(e).transform,overflow:getComputedStyle(e).overflow})')
            check(st['scrollTop']==0 and st['transform']=='none',tag+' story has no retained transform/scroll offset',str(st))
            check(st['sh']<=st['ch']+2,tag+' story content does not clip',str(st))
        else:
            mobile=p.locator('.auth-mobile-brand').bounding_box()
            check(stage and story and stage['y']<story['y'],tag+' mobile/tablet sign-in first')
            check(mobile and inside(mobile,stage),tag+' mobile publisher brand visible')
            check(inside(formbox,stage),tag+' mobile form visible')
        p.close()

    # Fresh screenshot previews.
    for w,h,name in [(1440,900,'epaper_v1195_auth_desktop.png'),(390,844,'epaper_v1195_auth_mobile.png')]:
        p=browser.new_page(viewport={'width':w,'height':h});p.set_content(doc('A good newspaper, I suppose, is a nation talking to itself.'),wait_until='load');p.screenshot(path=str(root.parent/name),full_page=True);p.close()
    browser.close()

if fails:
    print('\nFAILURES',json.dumps(fails,ensure_ascii=False));sys.exit(1)
print('\nALL v1.1.9.5 RESPONSIVE AUTH CHECKS PASSED')
