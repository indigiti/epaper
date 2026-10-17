from pathlib import Path
from playwright.sync_api import sync_playwright
import sys, json
root=Path(__file__).resolve().parents[1]
js=(root/'public_html/assets/reader-prefetch.js').read_text()
gif='data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=='
html='''<!doctype html><html><body><div id="reader" data-state-endpoint="/api/v1/reader/test/page/{page}/state" data-page-number="2" data-page-count="6" data-prefetch-enabled="on"><div data-page-stage style="width:800px;height:600px"></div></div></body></html>'''
fail=[]
def chk(page,name,expr):
    ok=page.evaluate(expr);print(('PASS' if ok else 'FAIL')+' — '+name)
    if not ok: fail.append(name)
with sync_playwright() as pw:
    b=pw.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    p=b.new_page(viewport={'width':1000,'height':800});p.set_content(html)
    p.evaluate("""g=>{window.__calls=[];const mem=new Map();Object.defineProperty(window,'localStorage',{value:{getItem:k=>mem.get(k)||null,setItem:(k,v)=>mem.set(k,String(v))},configurable:true});window.fetch=async u=>{window.__calls.push(String(u));const m=String(u).match(/page\/(\d+)\/state/),n=Number(m?.[1]||1);return {ok:true,json:async()=>({ok:true,page_number:n,page_count:6,preview:g,highres:g,preview_asset_guid:'p'+n,highres_asset_guid:'h'+n})}}}""",gif)
    errors=[];p.on('pageerror',lambda e: errors.append(str(e)))
    p.add_script_tag(content=js);p.wait_for_timeout(700)
    chk(p,'no JS errors',"() => true" if not errors else "() => false")
    chk(p,'next state prefetched',"() => __calls.some(x=>x.includes('/page/3/state'))")
    chk(p,'previous state prefetched',"() => __calls.some(x=>x.includes('/page/1/state'))")
    chk(p,'second-forward state warmed',"() => __calls.some(x=>x.includes('/page/4/state'))")
    chk(p,'adjacent preview decoded',"() => EpaperReaderPrefetch.stats.decoded>=2")
    p.evaluate("() => document.querySelector('#reader').dataset.pageNumber='3'");p.wait_for_timeout(500)
    chk(p,'page change schedules new next page',"() => __calls.some(x=>x.includes('/page/5/state'))")
    stage=p.locator('[data-page-stage]').bounding_box();x=stage['x']+400;y=stage['y']+300
    p.mouse.move(x,y);p.mouse.down();p.mouse.move(x-120,y);p.mouse.up();p.wait_for_timeout(100)
    chk(p,'swipe direction predicts next',"() => EpaperReaderPrefetch.stats.lastDirection==='next'")
    b.close()
if fail:
    print('FAILURES',json.dumps(fail));sys.exit(1)
print('ALL v1.1.8 READER PREFETCH BROWSER CHECKS PASSED')
