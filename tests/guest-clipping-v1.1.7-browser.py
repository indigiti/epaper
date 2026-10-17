from pathlib import Path
from playwright.sync_api import sync_playwright
import json, sys
root=Path(__file__).resolve().parents[1]
js=(root/'public_html/assets/guest-clipping.js').read_text()

def page_html(guest='1',used='0',sub_required='0'):
    return f'''<!doctype html><html><body>
<div id="reader" tabindex="0" data-reader-guest="{guest}" data-guest-clip-used="{used}" data-guest-login-url="/login?return=%2Fedition%2Ftest%2Fpage%2F1" data-subscribe-url="https://platform.digiti.in" data-clip-subscription-required="{sub_required}"></div>
<button data-clip-save>Save selection</button>
<div class="guest-clip-gate hidden" data-guest-clip-gate><div><button data-guest-gate-close>Close</button><h2 data-guest-gate-title></h2><p data-guest-gate-copy></p><a data-guest-login></a><a data-guest-subscribe></a><button data-guest-gate-later>Later</button></div></div>
<script>window.__bubble=0;document.querySelector('[data-clip-save]').addEventListener('click',()=>window.__bubble++);window.epaperApi=async(path,opts)=>({{ok:true}});</script>
</body></html>'''

def chk(page,name,expr,failures):
    ok=page.evaluate(expr); print(('PASS' if ok else 'FAIL')+' — '+name)
    if not ok: failures.append(name)

failures=[]
with sync_playwright() as pw:
    browser=pw.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    # Used guest quota: save is intercepted before reader's bubble handler.
    p=browser.new_page(viewport={'width':1440,'height':900});p.set_content(page_html('1','1'));p.add_script_tag(content=js);p.locator('[data-clip-save]').click();p.wait_for_timeout(40)
    chk(p,'used guest opens conversion gate',"() => !document.querySelector('[data-guest-clip-gate]').classList.contains('hidden')",failures)
    chk(p,'used guest prevents clipping save handler',"() => window.__bubble===0",failures)
    chk(p,'guest gate copy uses sign-in conversion',"() => document.querySelector('[data-guest-gate-title]').textContent.includes('free clipping')",failures)
    p.close()
    # Unused guest still reaches normal Reader handler.
    p=browser.new_page(viewport={'width':390,'height':844});p.set_content(page_html('1','0'));p.add_script_tag(content=js);p.locator('[data-clip-save]').click();p.wait_for_timeout(40)
    chk(p,'unused guest can open normal clipping creation',"() => window.__bubble===1",failures)
    chk(p,'unused guest gate stays closed',"() => document.querySelector('[data-guest-clip-gate]').classList.contains('hidden')",failures)
    # Guest API success marks quota immediately for same Reader session.
    p.evaluate("() => {window.epaperApi=async()=>({ok:true,guest:true,slug:'abc'});}")
    # re-run patch to wrap replacement stub
    p.add_script_tag(content=js);p.evaluate("() => window.epaperApi('/api/v1/clips',{body:{}})");p.wait_for_timeout(20)
    chk(p,'guest API success marks in-page quota used',"() => document.querySelector('#reader').dataset.guestClipUsed==='1'",failures)
    p.close()
    # Signed-in account known to lack entitlement is gated before the Reader save handler.
    p=browser.new_page(viewport={'width':1440,'height':900});p.set_content(page_html('0','0','1'));p.add_script_tag(content=js);p.locator('[data-clip-save]').click();p.wait_for_timeout(30)
    chk(p,'known subscription requirement gates before save',"() => window.__bubble===0 && !document.querySelector('[data-guest-clip-gate]').classList.contains('hidden')",failures)
    chk(p,'known subscription requirement shows subscribe copy',"() => document.querySelector('[data-guest-gate-title]').textContent.includes('subscription')",failures)
    p.close()
    # Signed-in entitlement error shows subscribe state.
    p=browser.new_page(viewport={'width':1440,'height':900});p.set_content(page_html('0','0'));p.evaluate("() => {window.epaperApi=async()=>{const e=new Error('Clipping is not included in your current subscription.');e.code='ENTITLEMENT_REQUIRED';e.data={subscribe_url:'https://platform.digiti.in'};throw e;}}")
    p.add_script_tag(content=js);p.evaluate("() => window.epaperApi('/api/v1/clips',{body:{}}).catch(()=>{})");p.wait_for_timeout(30)
    chk(p,'subscription error opens conversion gate',"() => !document.querySelector('[data-guest-clip-gate]').classList.contains('hidden')",failures)
    chk(p,'subscription mode hides login CTA',"() => document.querySelector('[data-guest-login]').classList.contains('hidden')",failures)
    chk(p,'subscription CTA points to Platform',"() => document.querySelector('[data-guest-subscribe]').href.startsWith('https://platform.digiti.in')",failures)
    p.close();browser.close()
if failures:
    print('\nFAILURES:',json.dumps(failures));sys.exit(1)
print('\nALL GUEST CLIPPING v1.1.7 BROWSER CHECKS PASSED')
