# -*- coding: utf-8 -*-
from pathlib import Path
from playwright.sync_api import sync_playwright
import sys, json

root = Path(__file__).resolve().parents[1]
css = (root / 'public_html/assets/app.css').read_text()
logo = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="520" height="100" viewBox="0 0 520 100"%3E%3Crect width="520" height="100" fill="white"/%3E%3Ctext x="8" y="67" font-size="54" font-family="Arial" font-weight="700" fill="black"%3EPuneTimesMirror%3C/text%3E%3C/svg%3E'
features = '''<div class="auth-feature-list"><article><span class="auth-feature-icon"></span><div><strong>Today’s ePaper</strong><p>Access the latest edition in a familiar, easy-to-read digital format.</p></div></article><article><span class="auth-feature-icon"></span><div><strong>Clipping &amp; Sharing Tools</strong><p>Save, organize, download, and share branded article clippings with ease.</p></div></article></div>'''
form = '''<section class="auth-panel"><span class="eyebrow">SECURE SIGN IN</span><h1>Welcome back</h1><p class="muted">Use your Digiti identity to access this ePaper.</p><form class="auth-form"><label>Email address<input type="email"></label><label>Password<input type="password"></label><label>Authenticator code <small>(if enabled)</small><input></label><button class="btn primary auth-submit" type="submit">Sign in</button></form><p class="auth-security">Authentication is verified securely in the background. Your password is never stored by ePaper SaaS.</p></section>'''

def doc(theme='theme-split-editorial'):
    return f'''<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style></head><body class="auth-shell {theme}" style="--brand-accent:#2f6fed"><main id="main" class="auth-main"><section class="auth-experience"><aside class="auth-story"><div class="auth-story-brand"><span class="auth-publisher-logo-stack"><img class="auth-publisher-logo auth-publisher-logo-dark" src="{logo}" alt="Pune Times Mirror"></span><span>The City. The Stories. The Truth.</span></div><span class="auth-product-pill">DIGITAL EPAPER</span><h1>Your newspaper. Your archive. Your reading library — all in one place.</h1><p class="auth-story-intro">Read today’s edition, explore past issues, save important clippings, and manage your subscription through one seamless digital experience.</p>{features}<figure class="auth-quote"><blockquote>“The press is the immune system of democracy.”</blockquote><figcaption>— Roy Greenslade</figcaption></figure></aside><div class="auth-form-stage"><div class="auth-form-stage-inner">{form}</div><p class="auth-control-plane-note">Secure identity and subscription verification runs in the background. You stay on this ePaper domain.</p></div></section></main></body></html>'''

fails = []
def check(ok, name):
    print(('PASS' if ok else 'FAIL') + ' — ' + name)
    if not ok: fails.append(name)

def inside(inner, outer, tol=2):
    return bool(inner and outer and inner['x'] >= outer['x'] - tol and inner['y'] >= outer['y'] - tol and inner['x'] + inner['width'] <= outer['x'] + outer['width'] + tol and inner['y'] + inner['height'] <= outer['y'] + outer['height'] + tol)

with sync_playwright() as pw:
    browser = pw.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    viewports = [
        {'width': 2048, 'height': 1664},
        {'width': 1440, 'height': 900},
        {'width': 1366, 'height': 768},
        {'width': 1280, 'height': 720},
        {'width': 390, 'height': 844},
    ]
    for vp in viewports:
        page = browser.new_page(viewport=vp)
        errors=[]
        page.on('pageerror', lambda e: errors.append(str(e)))
        page.set_content(doc(), wait_until='load')
        page.wait_for_timeout(100)
        tag=f"{vp['width']}x{vp['height']}"
        check(not errors, tag+' no runtime errors')
        check(page.locator('text=Searchable Archive').count()==0, tag+' Searchable Archive remains removed')
        check(page.locator('.auth-feature-list article').count()==2, tag+' exactly two feature rows')
        exp=page.locator('.auth-experience').bounding_box(); story=page.locator('.auth-story').bounding_box(); stage=page.locator('.auth-form-stage').bounding_box()
        check(exp is not None, tag+' auth card rendered')
        if vp['width']>900:
            check(exp and abs(exp['width']-1020)<=3, tag+' desktop card width locked near 1020px')
            target=540 if vp['height']<=680 else 590
            check(exp and abs(exp['height']-target)<=3, tag+f' desktop card height locked near {target}px')
            check(exp and exp['width']/exp['height']>=1.70, tag+' landscape proportion')
            check(story and stage and abs(story['width']-stage['width'])<=3, tag+' equal columns')
            # Typography sanity: avoid tiny UI text and serif quote mismatch.
            sizes={
                'hero': page.locator('.auth-story h1').evaluate("e=>parseFloat(getComputedStyle(e).fontSize)"),
                'intro': page.locator('.auth-story-intro').evaluate("e=>parseFloat(getComputedStyle(e).fontSize)"),
                'feature': page.locator('.auth-feature-list strong').first.evaluate("e=>parseFloat(getComputedStyle(e).fontSize)"),
                'feature_desc': page.locator('.auth-feature-list p').first.evaluate("e=>parseFloat(getComputedStyle(e).fontSize)"),
                'welcome': page.locator('.auth-form-stage .auth-panel h1').evaluate("e=>parseFloat(getComputedStyle(e).fontSize)"),
                'label': page.locator('.auth-form-stage .auth-form label').first.evaluate("e=>parseFloat(getComputedStyle(e).fontSize)"),
                'security': page.locator('.auth-security').evaluate("e=>parseFloat(getComputedStyle(e).fontSize)"),
            }
            check(sizes['hero']>=31, tag+' hero typography readable')
            check(sizes['intro']>=12, tag+' intro typography readable')
            check(sizes['feature']>=12, tag+' feature title readable')
            check(sizes['feature_desc']>=10.5, tag+' feature description readable')
            check(sizes['welcome']>=31, tag+' sign-in title readable')
            check(sizes['label']>=12, tag+' form labels readable')
            check(sizes['security']>=10.5, tag+' security copy readable')
            qfont=page.locator('.auth-quote blockquote').evaluate("e=>getComputedStyle(e).fontFamily")
            check('Georgia' not in qfont and 'Times New Roman' not in qfont, tag+' quote uses same system UI family')
            formbox=page.locator('.auth-form-stage-inner').bounding_box(); notebox=page.locator('.auth-control-plane-note').bounding_box()
            check(inside(formbox, exp), tag+' sign-in block fully inside card')
            check(inside(notebox, exp), tag+' bottom control-plane note fully inside card')
            check(notebox and exp and (exp['y']+exp['height']-(notebox['y']+notebox['height']))>=14, tag+' footer note has bottom clearance')
            # Center the auth form independently from pinned note.
            if formbox and stage:
                form_mid=formbox['y']+formbox['height']/2
                stage_mid=stage['y']+stage['height']/2
                check(abs(form_mid-stage_mid)<42, tag+' sign-in block vertically centered')
            storym=page.locator('.auth-story').evaluate('(e)=>({s:e.scrollHeight,c:e.clientHeight})')
            stagem=page.locator('.auth-form-stage').evaluate('(e)=>({s:e.scrollHeight,c:e.clientHeight})')
            check(storym['s']<=storym['c']+1, tag+' left story not clipped')
            check(stagem['s']<=stagem['c']+1, tag+' form stage not clipped')
        else:
            check(stage and story and stage['y']<story['y'], tag+' mobile sign-in remains first')
            check(exp and exp['height']>=vp['height'], tag+' mobile shell naturally stacks')
        if vp['width']==1440 and vp['height']==900:
            page.screenshot(path=str(root.parent/'epaper_v1193_auth_typography.png'), full_page=True)
        page.close()
    browser.close()

if fails:
    print('\nFAILURES', json.dumps(fails, ensure_ascii=False))
    sys.exit(1)
print('\nALL v1.1.9.3 AUTH TYPOGRAPHY/PROPORTION CHECKS PASSED')
