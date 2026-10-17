from pathlib import Path
from playwright.sync_api import sync_playwright
import base64,sys,json
root=Path(__file__).resolve().parents[1]
css=(root/'public_html/assets/app.css').read_text()
js=(root/'public_html/assets/crud.js').read_text()
html=f'''<!doctype html><html><head><meta charset="utf-8"><style>{css}</style></head><body class="admin-shell">
<header><span class="brand-logo-stack"><img class="brand-logo brand-logo-light" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=="><img class="brand-logo brand-logo-dark" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=="></span></header>
<form id="brand"><div class="color-field"><input type="color" data-brand-color value="#2563eb"><input data-brand-color-text value="#2563eb"></div><article class="brand-asset-card"><div class="brand-asset-preview is-empty"><span>No asset uploaded</span></div><label class="brand-upload-button">Choose file<input type="file" accept="image/png,image/jpeg,image/webp" data-brand-file></label><button type="button" data-brand-remove data-slot="logo">Remove</button></article></form>
<details class="record-menu" open><summary>•••</summary><div class="record-popover">Actions</div></details><button id="outside">Outside</button>
<form id="danger" data-confirm="Delete record?"><button type="submit">Delete</button></form>
</body></html>'''
fail=[]
def check(name,ok):
    print(('PASS' if ok else 'FAIL')+' — '+name)
    if not ok: fail.append(name)
with sync_playwright() as pw:
    b=pw.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    p=b.new_page(viewport={'width':1280,'height':800})
    p.set_content(html);p.add_script_tag(content=js);p.wait_for_timeout(80)
    p.locator('[data-brand-color]').evaluate("e=>{e.value='#ff00aa';e.dispatchEvent(new Event('input',{bubbles:true}))}")
    check('color picker syncs hex field',p.locator('[data-brand-color-text]').input_value()=='#ff00aa')
    # File preview.
    png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9WlKkqAAAAAASUVORK5CYII=')
    p.locator('[data-brand-file]').set_input_files({'name':'logo.png','mimeType':'image/png','buffer':png});p.wait_for_timeout(60)
    check('brand file shows local preview',p.locator('.brand-asset-preview img').count()==1)
    p.locator('#outside').click();check('record action menu closes on outside click',not p.locator('.record-menu').get_attribute('open'))
    # Dark logo swap.
    p.evaluate("document.body.classList.add('dark')")
    light=p.locator('.brand-logo-light').evaluate("e=>getComputedStyle(e).display");dark=p.locator('.brand-logo-dark').evaluate("e=>getComputedStyle(e).display")
    check('dark mode swaps to dark logo',light=='none' and dark!='none')
    # Cancelled destructive confirmation prevents submission.
    p.evaluate("window.confirm=()=>false;window.__submitted=false;document.querySelector('#danger').addEventListener('submit',()=>window.__submitted=true)")
    p.locator('#danger button').click();p.wait_for_timeout(20);check('destructive action cancellation blocks submit',not p.evaluate('window.__submitted'))
    b.close()
if fail:
    print('FAILURES',json.dumps(fail));sys.exit(1)
print('ALL v1.1.5 UI BROWSER CHECKS PASSED')
