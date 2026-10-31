#!/usr/bin/env python3
"""Read-only public ePaper browser check with PR CSS/JS; never authenticates or writes data."""
import json
from pathlib import Path
from urllib.parse import urlsplit
from playwright.sync_api import sync_playwright, Error as BrowserError

ROOT=Path(__file__).resolve().parents[1]
OUT=ROOT/'qa-artifacts'
BASE='https://epaper.digiti.in'
WIDTHS=(320,375,390,768,1440)
ASSETS={'reader-editorial-clips.js':'application/javascript',
        'reader-editorial-clips.css':'text/css','clip-ui-compact.css':'text/css'}

def serve_pr_asset(route):
    path=urlsplit(route.request.url).path
    name=path.rsplit('/',1)[-1]
    if path.startswith('/assets/') and name in ASSETS:
        route.fulfill(status=200,body=(ROOT/'public_html'/'assets'/name).read_bytes(),content_type=ASSETS[name])
    else: route.continue_()

def main():
    OUT.mkdir(exist_ok=True)
    results=[]; failures=[]; blocked=[]; edition=None
    with sync_playwright() as p:
        browser=p.chromium.launch(args=['--no-sandbox'],headless=True)
        try:
            for width in WIDTHS:
                ctx=browser.new_context(viewport={'width':width,'height':844 if width<600 else 900},reduced_motion='reduce',user_agent='EPDI-read-only-acceptance/1.0',extra_http_headers={'Accept':'text/html,application/json'})
                page=ctx.new_page()
                page.route('**/assets/**',serve_pr_asset)
                for path in ('/','/archive','/login'):
                    label=path.strip('/') or 'home'
                    result={'path':path,'width':width}
                    try:
                        response=page.goto(BASE+path,wait_until='domcontentloaded',timeout=25000)
                        page.wait_for_timeout(400)
                        result['http']=response.status if response else 0
                        result['title']=page.title()[:90]
                        if result['http']==403 and 'just a moment' in result['title'].lower():
                            blocked.append(f'{path} {width}px challenged (HTTP 403)')
                        elif result['http']!=200:
                            failures.append(f'{path} {width}px HTTP {result["http"]}')
                        else:
                            overflow=page.evaluate('() => Math.max(document.body.scrollWidth,document.documentElement.scrollWidth)-innerWidth')
                            result['overflow_px']=overflow
                            if overflow>8:failures.append(f'{path} {width}px overflow {overflow}px')
                            page.screenshot(path=str(OUT/f'{label}-{width}.png'))
                            if path=='/':
                                result['broken_logo_paths']=page.locator('.site-header .brand-logo').evaluate_all(
                                    '(imgs)=>imgs.filter(i=>i.complete && i.naturalWidth===0).map(i=>new URL(i.currentSrc||i.src).pathname)')
                            if not edition and path in ('/','/archive'):
                                links=page.locator('a[href^="/edition/"][href*="/page/"]').evaluate_all(
                                    '(elements)=>elements.map(a=>a.getAttribute("href")).filter(Boolean)')
                                if links:edition=links[0]
                    except (BrowserError,OSError) as e:failures.append(f'{path} {width}px {type(e).__name__}')
                    results.append(result)
                ctx.close()
            reader={'status':'NOT_TESTED','reason':'No public edition found'}
            if edition:
                ctx=browser.new_context(viewport={'width':390,'height':844},user_agent='EPDI-read-only-acceptance/1.0',extra_http_headers={'Accept':'text/html,application/json'})
                page=ctx.new_page();page.route('**/assets/**',serve_pr_asset)
                try:
                    response=page.goto(BASE+edition,wait_until='domcontentloaded',timeout=25000)
                    page.wait_for_timeout(600)
                    reader={'path':edition,'http':response.status if response else 0,'shell':page.locator('#reader').count()>0}
                    if reader['http']==200 and reader['shell']:page.screenshot(path=str(OUT/'reader-390.png'))
                    else: failures.append('Reader page unavailable or #reader missing')
                except BrowserError as e:failures.append('Reader '+type(e).__name__)
                ctx.close()
        finally:browser.close()
    report={'pages':results,'reader':reader,'failures':failures,'blocked':blocked}
    (OUT/'report.json').write_text(json.dumps(report,indent=2),encoding='utf-8')
    for item in results:print('PAGE',item['path'],item['width'],item.get('http'),item.get('overflow_px'),flush=True)
    print('READER',json.dumps(reader),flush=True)
    if failures:print('FAILURES',json.dumps(failures),flush=True);raise SystemExit(1)
    if blocked:
        print('PARTIAL — browser-challenged routes are NOT_TESTED:',json.dumps(blocked),flush=True)
    else:
        print('PASS public responsive browser smoke',flush=True)

if __name__=='__main__':main()
