from pathlib import Path
from playwright.sync_api import sync_playwright
import sys, json
root=Path(__file__).resolve().parents[1]
css=(root/'public_html/assets/app.css').read_text()
js=(root/'public_html/assets/crud.js').read_text()
HTML=f'''<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>:root{{--surface:#fff;--surface-2:#f7f7f8;--surface-3:#ececf0;--border:#ddd;--text:#1d1d1f;--text-2:#6e6e73;--accent:#2563eb;--brand-accent:#2563eb;--danger:#b42318}}body{{margin:0;background:#f5f5f7;font-family:Arial,sans-serif}}main{{padding:30px}}</style><style>{css}</style></head><body><main>
<nav class="master-tabs" role="tablist"><button class="master-tab active" data-master-tab="city" aria-selected="true">Cities <span>1</span></button><button class="master-tab" data-master-tab="language" aria-selected="false">Languages <span>2</span></button><button class="master-tab" data-master-tab="section" aria-selected="false">Sections <span>1</span></button></nav>
<div class="master-data-panels">
<section class="panel master-data-panel active" data-master-panel="city"><div class="master-panel-head"><div><h2>Cities</h2></div><button class="btn primary" type="button" data-open-master-create="city">+ Add City</button></div><div class="record-stack master-record-list" data-master-list="city"><article class="record-row master-record" data-master-search-text="pune pune"><div class="master-record-main"><strong>Pune</strong><span>pune</span></div><div class="master-record-side"><small class="status active">ACTIVE</small><details class="record-menu"><summary>•••</summary><div class="record-popover"><form class="record-form" data-editor-title="Edit Pune"><label>Name<input name="name" value="Pune"></label><div class="drawer-actions"><button type="button" data-close-record-editor>Cancel</button><button>Save</button></div></form><form><button class="record-action">Disable</button></form></div></details></div></article></div><form class="master-add-form editor-source" data-editor-title="Add City"><label>Name<input name="name"></label><div class="drawer-actions"><button type="button" data-close-record-editor>Cancel</button><button>Create</button></div></form></section>
<section class="panel master-data-panel" data-master-panel="language"><div class="master-panel-head"><div><h2>Languages</h2></div><button class="btn primary" type="button" data-open-master-create="language">+ Add Language</button></div><div class="master-list-toolbar"><label class="master-search"><input data-master-search="language"></label></div><div class="record-stack master-record-list" data-master-list="language"><article class="record-row master-record" data-master-search-text="english en en-in"><div class="master-record-main"><strong>English</strong><span>en · en-IN</span></div><div class="master-record-side"><small class="status active">ACTIVE</small><details class="record-menu"><summary>•••</summary><div class="record-popover"><form class="record-form" data-editor-title="Edit English"><label>Name<input name="name" value="English"></label><label>Code<input name="code" value="en"></label><label>Locale<input name="locale" value="en-IN"></label><div class="drawer-actions"><button type="button" data-close-record-editor>Cancel</button><button>Save changes</button></div></form><form><button class="record-action">Disable</button></form></div></details></div></article><article class="record-row master-record" data-master-search-text="marathi ma mr-in"><div class="master-record-main"><strong>Marathi</strong><span>ma · mr-IN</span></div></article></div><form class="master-add-form editor-source" data-editor-title="Add Language"><label>Name<input name="name"></label><label>Code<input name="code"></label><label>Locale<input name="locale"></label><div class="drawer-actions"><button type="button" data-close-record-editor>Cancel</button><button>Create</button></div></form></section>
<section class="panel master-data-panel" data-master-panel="section"><h2>Sections</h2></section>
</div>
</main><script>window.epaperToast=()=>{{}}</script></body></html>'''

def check(page,name,expr,failures):
    ok=page.evaluate(expr); print(('PASS' if ok else 'FAIL')+' — '+name)
    if not ok: failures.append(name)

fails=[]
with sync_playwright() as pw:
    browser=pw.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for vp in ({'width':1440,'height':900},{'width':390,'height':844}):
        page=browser.new_page(viewport=vp)
        errors=[];page.on('pageerror',lambda e: errors.append(str(e)))
        page.set_content(HTML,wait_until='domcontentloaded')
        page.evaluate("() => {const m=new Map();Object.defineProperty(window,'localStorage',{value:{getItem:k=>m.has(k)?m.get(k):null,setItem:(k,v)=>m.set(k,String(v))},configurable:true})}")
        page.add_script_tag(content=js);page.wait_for_timeout(100)
        tag=f"{vp['width']}x{vp['height']}"
        if errors: fails.append(tag+' JS errors'); print('FAIL — '+tag+' JS errors '+str(errors))
        else: print('PASS — '+tag+' no JS errors')
        check(page,tag+' only active master panel visible',"() => getComputedStyle(document.querySelector('[data-master-panel=city]')).display!=='none' && getComputedStyle(document.querySelector('[data-master-panel=language]')).display==='none'",fails)
        page.locator('[data-master-tab=language]').click();page.wait_for_timeout(30)
        check(page,tag+' tab switches cleanly',"() => getComputedStyle(document.querySelector('[data-master-panel=language]')).display!=='none' && getComputedStyle(document.querySelector('[data-master-panel=city]')).display==='none'",fails)
        check(page,tag+' inline edit form hidden',"() => getComputedStyle(document.querySelector('[data-master-panel=language] .record-form')).display==='none'",fails)
        page.locator('[data-master-panel=language] .record-menu summary').click();page.wait_for_timeout(20)
        check(page,tag+' menu has Edit action',"() => !!document.querySelector('[data-master-panel=language] [data-open-record-editor]')",fails)
        page.locator('[data-master-panel=language] [data-open-record-editor]').click();page.wait_for_timeout(300)
        check(page,tag+' edit drawer opens',"() => !document.querySelector('.record-editor-layer').classList.contains('hidden') && document.querySelector('#record-editor-title').textContent.includes('English')",fails)
        check(page,tag+' editor stays within viewport',"() => {const r=document.querySelector('.record-editor').getBoundingClientRect();return r.left>=-1&&r.right<=innerWidth+1&&r.top>=-1&&r.bottom<=innerHeight+1}",fails)
        page.locator('.record-editor [data-close-record-editor]').first.click();page.wait_for_timeout(30)
        page.locator('[data-open-master-create=language]').click();page.wait_for_timeout(50)
        check(page,tag+' add uses shared editor',"() => document.querySelector('#record-editor-title').textContent==='Add Language'",fails)
        page.locator('.record-editor [data-close-record-editor]').first.click()
        page.locator('[data-master-search=language]').fill('marathi');page.wait_for_timeout(20)
        check(page,tag+' search filters records',"() => {const rows=[...document.querySelectorAll('[data-master-list=language] [data-master-search-text]')];return rows[0].hidden===true&&rows[1].hidden===false}",fails)
        page.close()
    browser.close()
if fails:
    print('\nFAILURES',json.dumps(fails));sys.exit(1)
print('\nALL v1.1.6.2 CRUD UI BROWSER CHECKS PASSED')
