from pathlib import Path
from playwright.sync_api import sync_playwright
import sys,json
root=Path(__file__).resolve().parents[1]
css=(root/'public_html/assets/app.css').read_text()
logo='data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="520" height="100" viewBox="0 0 520 100"%3E%3Crect width="520" height="100" fill="white"/%3E%3Ctext x="8" y="67" font-size="54" font-family="Arial" font-weight="700" fill="black"%3EPuneTimesMirror%3C/text%3E%3C/svg%3E'
features='''<div class="auth-feature-list"><article><span class="auth-feature-icon"></span><div><strong>Today’s ePaper</strong><p>Access the latest edition in a familiar, easy-to-read digital format.</p></div></article><article><span class="auth-feature-icon"></span><div><strong>Searchable Archive</strong><p>Quickly find past editions, articles, pages, and stories from your publication archive.</p></div></article><article><span class="auth-feature-icon"></span><div><strong>Clipping &amp; Sharing Tools</strong><p>Save, organize, download, and share branded article clippings with ease.</p></div></article></div>'''
form='''<section class="auth-panel"><span class="eyebrow">SECURE SIGN IN</span><h1>Welcome back</h1><p class="muted">Use your Digiti identity to access this ePaper.</p><form class="auth-form"><label>Email address<input type="email"></label><label>Password<input type="password"></label><label>Authenticator code <small>(if enabled)</small><input></label><button class="btn primary auth-submit" type="submit">Sign in</button></form><p class="auth-security">Authentication is verified securely in the background.</p></section>'''
def doc(theme='theme-split-editorial'):
    return f'''<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style></head><body class="auth-shell {theme}" style="--brand-accent:#7655ee"><main id="main" class="auth-main"><section class="auth-experience"><aside class="auth-story"><div class="auth-story-brand"><span class="auth-publisher-logo-stack"><img class="auth-publisher-logo auth-publisher-logo-dark" src="{logo}" alt="Pune Times Mirror"></span></div><span class="auth-product-pill">DIGITAL EPAPER</span><h1>Your newspaper. Your archive. Your reading library — all in one place.</h1><p class="auth-story-intro">Read today’s edition, explore past issues, save important clippings, and manage your subscription through one seamless digital experience.</p>{features}<figure class="auth-quote"><blockquote>“Comment is free, but facts are sacred.”</blockquote><figcaption>— C. P. Scott</figcaption></figure></aside><div class="auth-form-stage"><div class="auth-form-stage-inner">{form}</div><p class="auth-control-plane-note">Secure identity and subscription verification runs in the background. You stay on this ePaper domain.</p></div></section></main></body></html>'''
fails=[]
def check(ok,name):
    print(('PASS' if ok else 'FAIL')+' — '+name)
    if not ok:fails.append(name)
with sync_playwright() as pw:
    browser=pw.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for vp in ({'width':1440,'height':900},{'width':390,'height':844}):
        page=browser.new_page(viewport=vp)
        errors=[];page.on('pageerror',lambda e: errors.append(str(e)))
        page.set_content(doc(),wait_until='load');page.wait_for_timeout(80)
        tag=f"{vp['width']}x{vp['height']}"
        check(not errors,tag+' no JavaScript/runtime errors')
        check(page.locator('.auth-story h1').inner_text().startswith('Your newspaper.'),tag+' branded hero copy visible')
        check(page.locator('.auth-feature-list article').count()==3,tag+' three value features rendered')
        check(page.locator('.auth-quote blockquote').count()==1,tag+' rotating quote slot rendered')
        check(page.locator('.auth-publisher-logo').get_attribute('alt')=='Pune Times Mirror',tag+' publisher logo has meaningful alt text')
        check(page.locator('.auth-form-stage .auth-panel').count()==1,tag+' existing auth form remains embedded')
        exp=page.locator('.auth-experience').bounding_box();check(exp is not None and exp['width']>300 and exp['height']>500,tag+' auth experience rendered')
        if vp['width']>900:
            story=page.locator('.auth-story').bounding_box();stage=page.locator('.auth-form-stage').bounding_box()
            check(story is not None and stage is not None and story['x'] < stage['x'],tag+' desktop uses split two-panel layout')
            check(exp is not None and exp['width']<=1121,tag+' desktop shell uses bounded premium width')
        else:
            story=page.locator('.auth-story').bounding_box();stage=page.locator('.auth-form-stage').bounding_box()
            check(story is not None and stage is not None and stage['y'] < story['y'],tag+' mobile puts sign-in form before brand story')
            check(stage is not None and stage['width']<=vp['width']+1,tag+' mobile form stays within viewport')
        page.close()
    page=browser.new_page(viewport={'width':1280,'height':800});page.set_content(doc('theme-minimal-access'),wait_until='load');page.wait_for_timeout(40)
    bg=page.evaluate("getComputedStyle(document.querySelector('.auth-form-stage')).backgroundColor")
    check(bg in ('rgb(255, 255, 255)','rgba(255, 255, 255, 1)'), 'Minimal Access keeps branded split structure with light form surface')
    page.close();browser.close()
if fails:
    print('\nFAILURES',json.dumps(fails));sys.exit(1)
print('\nALL v1.1.9 AUTH BROWSER CHECKS PASSED')
