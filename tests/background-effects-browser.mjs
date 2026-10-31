import { chromium } from 'playwright';
import { readFile, mkdir } from 'node:fs/promises';
import { resolve, join } from 'node:path';
import assert from 'node:assert/strict';

const root=resolve(import.meta.dirname,'..');
const fixture=await readFile(join(root,'tests/effects-studio-fixtures/effects-studio.html'),'utf8');
const [mapping,interaction]=await Promise.all([
  readFile(join(root,'public_html/assets/vanta-config.js'),'utf8'),
  readFile(join(root,'public_html/assets/admin-effects.js'),'utf8')
]);
const screenshots=join(root,'tests/effects-studio-screenshots');
await mkdir(screenshots,{recursive:true});
const browser=await chromium.launch({headless:true});
const modes=[['desktop',1440,900],['tablet',768,1024],['mobile',390,844]];
try{
  for(const [mode,width,height] of modes){
    const context=await browser.newContext({viewport:{width,height},deviceScaleFactor:1});
    await context.addInitScript(()=>{
      window.__started=[];window.__destroyed=0;window.__posted=0;
      window.WebGLRenderingContext=window.WebGLRenderingContext||function(){};
      const old=HTMLCanvasElement.prototype.getContext;
      HTMLCanvasElement.prototype.getContext=function(type,...args){
        if(type==='webgl')return {getExtension:()=>({loseContext(){}})};
        return old.call(this,type,...args);
      };
    });
    const assets=[];
    const page=await context.newPage();
    await page.route('https://studio.epaper.test/**',async route=>{
      const url=new URL(route.request().url());
      if(url.pathname.endsWith('/three.r134.min.js')){
        assets.push('THREE');
        return route.fulfill({status:200,contentType:'text/javascript',body:'window.THREE={REVISION:"134"};'});
      }
      const match=url.pathname.match(/vanta\.(halo|waves|net|dots|rings|clouds|cells|fog)\.0\.5\.24\.min\.js/);
      if(match){
        const effect=match[1].toUpperCase();
        assets.push(effect);
        const source='window.VANTA=window.VANTA||{};window.VANTA.'+effect+'=function(o){'+
          'window.__started.push({effect:"'+effect+'",options:o});'+
          'const c=document.createElement("canvas");o.el.appendChild(c);'+
          'return {destroy(){window.__destroyed++;c.remove();}};};';
        return route.fulfill({status:200,contentType:'text/javascript',body:source});
      }
      return route.fulfill({status:200,contentType:'text/html',body:fixture});
    });
    await page.goto('https://studio.epaper.test/admin/settings/effects');
    await page.addScriptTag({content:mapping});
    await page.addScriptTag({content:interaction});
    const cards=page.locator('[data-effect-select]');
    assert(await cards.count()===9,mode+' should have eight effects plus Off');
    assert(await page.locator('[data-effect-choice]').inputValue()==='Off','Off must be default');
    await page.waitForTimeout(650);
    assert(assets.length===0,'Off must not download any vendor graphics');
    const getMetrics=()=>page.evaluate(()=>({
      overflow:document.documentElement.scrollWidth-innerWidth,
      active:[...document.querySelectorAll('[data-effect-select][aria-pressed="true"]')].map(el=>el.dataset.effectSelect),
      visible:document.querySelectorAll('.effect-field:not([hidden])').length,
      unsaved:!document.querySelector('[data-effect-unsaved]').hidden,
    }));
    for(const name of ['Halo','Waves','Net','Dots','Rings','Clouds','Cells','Fog']){
      await page.locator('[data-effect-select="'+name+'"]').click();
      await page.waitForTimeout(mode==='desktop'?670:80);
      const selected=await page.locator('[data-effect-choice]').inputValue();
      assert(selected===name,name+' visual card must synchronize actual POST form select');
      const m=await getMetrics();
      assert(m.active.length===1&&m.active[0]===name,name+' only one pressed choice');
      assert(m.visible>=2,name+' should expose suitable effect controls');
      assert(m.unsaved,name+' should show unsaved state, never publish automatically');
      const preview=await page.locator('[data-effect-public-preview]').getAttribute('href');
      assert(preview.endsWith('='+name.toLowerCase()),name+' live link should reference selected type');
      if(name==='Clouds'){
        assert(await page.locator('[data-effect-field="home_effect.cloud_color"]').isVisible(),
          'Clouds needs its own cloud-color picker');
        assert(await page.locator('[data-effect-field="home_effect.halo_ring"]').isHidden(),
          'Halo-only settings must be hidden for Clouds');
      }
      if(name==='Fog'){
        assert(await page.locator('[data-effect-field="home_effect.fog_blur"]').isVisible(),
          'Fog needs its own softness slider');
      }
      if(mode==='desktop'){
        await page.waitForFunction(key=>
          window.__started.some(x=>x.effect===key),name,{timeout:2500});
        const started=await page.evaluate(key=>window.__started.find(x=>x.effect===key),name);
        assert(started.options.mouseControls===false&&started.options.touchControls===false&&started.options.gyroControls===false,
          name+' must not intercept pointer/reader interactions');
      }
    }
    if(mode==='desktop'){
      assert(assets.filter(x=>x==='THREE').length===1,'All eight effects must share one Three.js download');
      assert(assets.length===9,'Exactly one asset per selected effect plus one Three.js');
    }else{
      assert(assets.length===0,mode+' must not fetch any shaders or Three.js');
    }
    const info=await getMetrics();
    assert(info.overflow<=2,mode+' horizontal overflow '+info.overflow);
    await page.screenshot({path:join(screenshots,'effects-studio-'+mode+'.png'),fullPage:mode==='desktop'});
    if(mode==='desktop'){
      await page.locator('[data-effect-preview-toggle]').click();
      assert((await page.locator('[data-effect-preview-toggle]').getAttribute('aria-pressed'))==='false','Pause needs ARIA state');
      await page.locator('[data-effect-reset]').click();
      assert((await page.locator('[data-effect-choice]').inputValue())==='Off','Reset restores saved selection');
      assert(await page.locator('[data-effect-unsaved]').isHidden(),'Reset clears dirty badge');
    }
    assert(assets.every(x=>['THREE','HALO','WAVES','NET','DOTS','RINGS','CLOUDS','CELLS','FOG'].includes(x)),
      'Only allowlisted shaders may be downloaded');
    console.log('PASS',mode,JSON.stringify({loaded:assets,overflow:info.overflow,controls:info.visible}));
    await context.close();
  }
  console.log('PASS eight Vanta effects customizer, publisher-only preview and mobile/desktop parity');
}finally{
  await browser.close();
}
