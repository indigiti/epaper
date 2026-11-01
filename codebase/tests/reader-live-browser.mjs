// Staging-only browser acceptance. Set EPDI_READER_TEST_URL to run; missing URL is NOT a pass.
import assert from 'node:assert/strict';
import {mkdir,writeFile} from 'node:fs/promises';
import {chromium} from 'playwright';
import AxeBuilder from '@axe-core/playwright';

const url=process.env.EPDI_READER_TEST_URL||'';
if(!url){console.log('SKIPPED: EPDI_READER_TEST_URL is absent. Live e2e acceptance is NOT verified.');process.exit(0)}
const target=new URL(url);
if(!['https:','http:'].includes(target.protocol))throw new Error('Invalid test URL protocol');
if(target.hostname==='localhost'&&process.env.CI)throw new Error('CI requires a real deployed staging reader');
const origin=target.origin,dir=new URL('./reader-live-screenshots/',import.meta.url);
await mkdir(dir,{recursive:true});
const browser=await chromium.launch({headless:true});
const evidence=[];
try{
  const profiles=[
    {name:'desktop-1440',width:1440,height:900,mobile:false},
    {name:'tablet-768',width:768,height:1024,mobile:true},
    {name:'mobile-390',width:390,height:844,mobile:true},
    {name:'mobile-320',width:320,height:720,mobile:true}
  ];
  for(const device of profiles){
    const context=await browser.newContext({viewport:{width:device.width,height:device.height},deviceScaleFactor:1,isMobile:device.mobile,hasTouch:device.mobile,reducedMotion:'reduce'});
    const page=await context.newPage(),errors=[];
    await page.addInitScript(()=>{window.__webVitals={lcp:0,cls:0};try{new PerformanceObserver(list=>{for(const e of list.getEntries())window.__webVitals.lcp=e.startTime}).observe({type:'largest-contentful-paint',buffered:true});new PerformanceObserver(list=>{for(const e of list.getEntries())if(!e.hadRecentInput)window.__webVitals.cls+=e.value}).observe({type:'layout-shift',buffered:true})}catch{}});
    page.on('pageerror',error=>errors.push(String(error.message).slice(0,250)));
    await page.goto(url,{waitUntil:'domcontentloaded',timeout:30000});await page.locator('#reader').waitFor({timeout:12000});
    await page.waitForTimeout(750);
    const frame=page.locator('#reader'),count=+(await frame.getAttribute('data-page-count')||'1');
    const dimensions=await page.evaluate(()=>({width:document.documentElement.scrollWidth,viewport:window.innerWidth}));
    assert.ok(dimensions.width<=dimensions.viewport+8,`${device.name}: horizontal document overflow ${JSON.stringify(dimensions)}`);
    if(device.width<=720){
      const more=page.locator('[data-reader-more-toggle]');assert.ok(await more.isVisible(),device.name+': overflow menu missing');await more.click();assert.ok(await page.locator('[data-reader-more-menu]').isVisible());await page.keyboard.press('Escape');assert.ok(!(await page.locator('[data-reader-more-menu]').isVisible()),'Escape must close overflow menu');
    }
    await page.locator('[data-reader-text-mode]').click();
    await page.getByRole('dialog',{name:'Read page text'}).waitFor();
    await page.locator('[data-reader-text-status]').waitFor();
    await page.keyboard.press('Escape');assert.ok(!(await page.locator('[data-reader-text-modal]').isVisible()),'Escape must close OCR view');
    if(count>1&&device.name==='desktop-1440'){
      const next=page.locator('[data-reader-next]');await next.click();await page.waitForFunction(()=>document.querySelector('#reader')?.dataset.pageNumber==='2',null,{timeout:12000});assert.equal(await frame.getAttribute('data-page-number'),'2','Next-page navigation must work');
    }
    const axe=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21a','wcag21aa']).analyze();
    const violations=axe.violations.map(v=>({id:v.id,impact:v.impact,nodes:v.nodes.length}));
    const metrics=await page.evaluate(()=>({navigationMs:Math.round(performance.getEntriesByType('navigation')[0]?.duration||0),lcpMs:Math.round(window.__webVitals?.lcp||0),cls:Number((window.__webVitals?.cls||0).toFixed(4)),resourceCount:performance.getEntriesByType('resource').length,transferBytes:performance.getEntriesByType('resource').reduce((sum,r)=>sum+(r.transferSize||0),0)}));
    const maxLcp=Number(process.env.EPDI_MAX_LCP_MS||4000),maxCls=Number(process.env.EPDI_MAX_CLS||0.1);
    assert.ok(metrics.lcpMs>0,device.name+': no measurable LCP; performance acceptance incomplete');
    assert.ok(metrics.lcpMs<=maxLcp,device.name+': LCP exceeds '+maxLcp+'ms');
    assert.ok(metrics.cls<=maxCls,device.name+': CLS exceeds '+maxCls);
    await page.screenshot({path:new URL(`${device.name}.png`,dir).pathname,fullPage:true,animations:'disabled'});
    evidence.push({profile:device.name,screen:{width:device.width,height:device.height},metrics,violations,pageErrors:errors});
    await context.close();
    assert.deepEqual(errors,[],device.name+': JavaScript errors detected');
    assert.deepEqual(violations,[],device.name+': WCAG A/AA violations detected');
  }
  const ctx=await browser.newContext();const bad=await ctx.request.get(origin+'/media/not-a-valid-signature');
  assert.notEqual(bad.status(),200,'Unsigned media must not be accessible');
  const random='00000000-0000-4000-8000-000000000000';
  const unpublished=process.env.EPDI_TEST_UNPUBLISHED_PAGE_GUID||random;
  const text=await ctx.request.get(origin+'/api/v1/pages/'+unpublished+'/text');
  assert.notEqual(text.status(),200,'Non-public or missing OCR page must not be readable');
  const tile=await ctx.request.get(origin+'/api/v1/pages/'+unpublished+'/tiles/0/0/0.webp');
  assert.notEqual(tile.status(),200,'Non-public or missing raster tiles must not be readable');
  await ctx.close();
}finally{await browser.close();await writeFile(new URL('report.json',dir),JSON.stringify({url:target.origin+target.pathname,evidence},null,2))}
console.log('Live browser acceptance completed; screenshots and report saved.');
