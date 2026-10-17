# -*- coding: utf-8 -*-
from pathlib import Path
from playwright.sync_api import sync_playwright
import sys, json

root = Path(__file__).resolve().parents[1]
css = (root / 'public_html/assets/app.css').read_text()
logo = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="520" height="100" viewBox="0 0 520 100"%3E%3Crect width="520" height="100" fill="white"/%3E%3Ctext x="8" y="67" font-size="54" font-family="Arial" font-weight="700" fill="black"%3EPuneTimesMirror%3C/text%3E%3C/svg%3E'
features = '''<div class="auth-feature-list"><article><span class="auth-feature-icon"></span><div><strong>Today’s ePaper</strong><p>Access the latest edition in a familiar, easy-to-read digital format.</p></div></article><article><span class="auth-feature-icon"></span><div><strong>Clipping &amp; Sharing Tools</strong><p>Save, organize, download, and share branded article clippings with ease.</p></div></article></div>'''
form = '''<section class="auth-panel"><span class="eyebrow">SECURE SIGN IN</span><h1>Welcome back</h1><p class="muted">Use your Digiti identity to access this ePaper.</p><form class="auth-form"><label>Email address<input type="email"></label><label>Password<input type="password"></label><label>Authenticator code <small>(if enabled)</small><input></label><button class="btn primary auth-submit" type="submit">Sign in</button></form><p class="auth-security">Authentication is verified securely in the background.</p></section>'''

def doc(theme='theme-split-editorial'):
    return f'''<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style></head><body class="auth-shell {theme}" style="--brand-accent:#2f6fed"><main id="main" class="auth-main"><section class="auth-experience"><aside class="auth-story"><div class="auth-story-brand"><span class="auth-publisher-logo-stack"><img class="auth-publisher-logo auth-publisher-logo-dark" src="{logo}" alt="Pune Times Mirror"></span><span>The City. The Stories. The Truth.</span></div><span class="auth-product-pill">DIGITAL EPAPER</span><h1>Your newspaper. Your archive. Your reading library — all in one place.</h1><p class="auth-story-intro">Read today’s edition, explore past issues, save important clippings, and manage your subscription through one seamless digital experience.</p>{features}<figure class="auth-quote"><blockquote>“The only security of all is in a free press.”</blockquote><figcaption>— Thomas Jefferson</figcaption></figure></aside><div class="auth-form-stage"><div class="auth-form-stage-inner">{form}</div><p class="auth-control-plane-note">Secure identity and subscription verification runs in the background. You stay on this ePaper domain.</p></div></section></main></body></html>'''

fails = []
def check(ok, name):
    print(('PASS' if ok else 'FAIL') + ' — ' + name)
    if not ok:
        fails.append(name)

def inside(inner, outer, tol=2):
    return bool(inner and outer and inner['x'] >= outer['x'] - tol and inner['y'] >= outer['y'] - tol and inner['x'] + inner['width'] <= outer['x'] + outer['width'] + tol and inner['y'] + inner['height'] <= outer['y'] + outer['height'] + tol)

with sync_playwright() as pw:
    browser = pw.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for vp in ({'width': 1440, 'height': 900}, {'width': 1366, 'height': 768}, {'width': 1280, 'height': 720}, {'width': 390, 'height': 844}):
        page = browser.new_page(viewport=vp)
        errors = []
        page.on('pageerror', lambda e: errors.append(str(e)))
        page.set_content(doc(), wait_until='load')
        page.wait_for_timeout(80)
        tag = f"{vp['width']}x{vp['height']}"
        check(not errors, tag + ' no runtime errors')
        check(page.locator('.auth-feature-list article').count() == 2, tag + ' exactly two value features rendered')
        check(page.locator('text=Searchable Archive').count() == 0, tag + ' Searchable Archive removed')
        check(page.locator('.auth-quote').count() == 1, tag + ' quote retained')
        exp = page.locator('.auth-experience').bounding_box()
        stage = page.locator('.auth-form-stage').bounding_box()
        story = page.locator('.auth-story').bounding_box()
        check(exp is not None and exp['width'] > 300, tag + ' auth card rendered')
        if vp['width'] > 900:
            check(story and stage and story['x'] < stage['x'], tag + ' split columns aligned')
            check(exp and exp['width'] <= 1042, tag + ' compact max width retained')
            check(exp and exp['height'] < 700, tag + ' compact height retained')
            check(exp and exp['width'] / exp['height'] > 1.45, tag + ' landscape proportion retained')
            storym = page.locator('.auth-story').evaluate('(e)=>({s:e.scrollHeight,c:e.clientHeight})')
            stagem = page.locator('.auth-form-stage').evaluate('(e)=>({s:e.scrollHeight,c:e.clientHeight})')
            check(storym['s'] <= storym['c'] + 1, tag + ' story not vertically clipped')
            check(stagem['s'] <= stagem['c'] + 1, tag + ' sign-in stage not vertically clipped')
            selectors = [
                ('.auth-form-stage .auth-panel', 'auth panel'),
                ('.auth-form-stage input[type=email]', 'email'),
                ('.auth-form-stage input[type=password]', 'password'),
                ('.auth-form-stage input:not([type])', 'authenticator'),
                ('.auth-submit', 'sign-in button'),
                ('.auth-control-plane-note', 'security footer'),
            ]
            for sel, name in selectors:
                box = page.locator(sel).first.bounding_box()
                check(inside(box, exp), tag + ' ' + name + ' fully inside auth card')
        else:
            check(stage and story and stage['y'] < story['y'], tag + ' mobile sign-in remains first')
            check(stage and stage['width'] <= vp['width'] + 1, tag + ' mobile form within viewport')
        page.close()
    browser.close()

if fails:
    print('\nFAILURES', json.dumps(fails))
    sys.exit(1)
print('\nALL v1.1.9.2 AUTH LAYOUT CHECKS PASSED')
