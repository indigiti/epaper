from pathlib import Path
from playwright.sync_api import sync_playwright
import sys
root=Path(__file__).resolve().parents[1]
css=(root/'public_html/assets/app.css').read_text()
logo='data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="420" height="80" viewBox="0 0 420 80"%3E%3Crect width="420" height="80" fill="white"/%3E%3Ctext x="4" y="56" font-size="50" font-family="Arial" font-weight="700"%3EPuneTimesMirror%3C/text%3E%3C/svg%3E'
def doc(with_logo: bool):
    brand = (f'<a class="brand brand-with-logo" href="/" aria-label="Pune Times Mirror home"><span class="brand-logo-stack"><img class="brand-logo brand-logo-light" src="{logo}" alt="Pune Times Mirror" decoding="async"></span></a>' if with_logo else '<a class="brand" href="/" aria-label="Pune Times Mirror home"><span class="brand-name brand-name-fallback">Pune Times Mirror</span></a>')
    return f'''<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style></head><body><header class="site-header"><div class="site-header-inner">{brand}<nav class="site-nav-links"><a>Today</a><a>Archive</a><a>Search</a></nav><div class="header-actions"></div></div></header></body></html>'''
fails=[]
def check(cond,name):
    print(('PASS' if cond else 'FAIL')+' — '+name)
    if not cond:fails.append(name)
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for vp in ({'width':1440,'height':900},{'width':390,'height':844}):
        tag=f"{vp['width']}x{vp['height']}"
        # Logo configured: logo only, no repeated visible site-name text.
        page=browser.new_page(viewport=vp);page.set_content(doc(True),wait_until='load');page.wait_for_timeout(80)
        check(page.locator('.brand-logo-light').count()==1,f'{tag} configured logo is rendered')
        check(page.locator('.brand-name').count()==0,f'{tag} configured logo does not repeat visible brand name')
        check(page.locator('.brand-logo-light').get_attribute('alt')=='Pune Times Mirror',f'{tag} configured logo has meaningful alt text')
        box=page.locator('.brand-logo-light').bounding_box();check(box is not None and box['width']>40,f'{tag} wide masthead stays legible')
        if vp['width']<=540:check(box is not None and box['width']<=130.5 and box['height']<=28.5,f'{tag} mobile logo respects responsive masthead size')
        else:check(box is not None and box['width']<=190.5 and box['height']<=36.5,f'{tag} desktop logo respects responsive masthead size')
        page.close()
        # No logo: text-only fallback.
        page=browser.new_page(viewport=vp);page.set_content(doc(False),wait_until='load');page.wait_for_timeout(50)
        check(page.locator('.brand-logo').count()==0,f'{tag} no empty logo placeholder without asset')
        check(page.locator('.brand-name-fallback').inner_text()=='Pune Times Mirror',f'{tag} site name is visible when logo is absent')
        check(page.locator('.brand').get_attribute('aria-label')=='Pune Times Mirror home',f'{tag} brand link keeps accessible home label')
        page.close()
    browser.close()
if fails:
    print('FAILURES:',fails);sys.exit(1)
print('ALL v1.1.6.1 HEADER BRAND CHECKS PASSED')
