from pathlib import Path
from playwright.sync_api import sync_playwright
import sys
root=Path(__file__).resolve().parents[1]
css=(root/'public_html/assets/app.css').read_text()
logo='data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="300" height="70" viewBox="0 0 300 70"%3E%3Crect width="300" height="70" fill="white"/%3E%3Ctext x="2" y="48" font-size="42" font-family="Arial" font-weight="700"%3EPuneMirror%3C/text%3E%3C/svg%3E'
html=f'''<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style></head><body><header class="site-header"><div class="site-header-inner"><a class="brand brand-with-logo" href="/" aria-label="Pune Times Mirror home"><span class="brand-logo-stack"><img class="brand-logo brand-logo-light" src='{logo}' alt="Pune Times Mirror" decoding="async"></span><span class="brand-name" aria-hidden="true">Pune Times Mirror</span></a><nav class="site-nav-links"><a>Today</a><a>Archive</a><a>Search</a></nav><div class="header-actions"><a class="icon-link">U</a></div></div></header></body></html>'''
fails=[]
def check(cond,name):
    print(('PASS' if cond else 'FAIL')+' — '+name)
    if not cond:fails.append(name)
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for vp in ({'width':1440,'height':900},{'width':390,'height':844}):
        page=browser.new_page(viewport=vp);page.set_content(html,wait_until='load');page.wait_for_timeout(100)
        box=page.locator('.brand-logo-light').bounding_box();brand=page.locator('.brand').bounding_box();tag=f"{vp['width']}x{vp['height']}"
        check(box is not None and box['width']>30,f'{tag} uploaded wide logo is not forced to 30px')
        check(box is not None and box['height']<=35,f'{tag} logo height stays inside header')
        check(brand is not None and brand['height']<=48,f'{tag} brand block stays compact')
        check(page.locator('.brand-logo-light').get_attribute('alt')=='Pune Times Mirror',f'{tag} logo alt text uses site name')
        check(page.locator('.brand-name').get_attribute('aria-hidden')=='true',f'{tag} visible brand name avoids duplicate screen-reader name')
        if vp['width']<=540: check(box['width']<=82.5,f'{tag} mobile logo respects compact width')
        else: check(box['width']<=140.5,f'{tag} desktop logo respects masthead width')
        page.close()
    browser.close()
if fails:
    print('FAILURES:',fails);sys.exit(1)
print('ALL v1.1.6 HEADER BROWSER CHECKS PASSED')
