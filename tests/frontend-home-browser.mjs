import { chromium } from 'playwright';
import { readFile, mkdir } from 'node:fs/promises';
import path from 'node:path';
import assert from 'node:assert/strict';

const root = path.resolve(import.meta.dirname, '..');
const inputDir = path.join(root,'tests/frontend-layout-fixtures');
const outDir = path.join(root,'tests/frontend-layout-screenshots');
await mkdir(outDir,{recursive:true});
const themes=['newsstand-modern','editorial-portal','classic-newspaper','digital-magazine'];
const browser=await chromium.launch({headless:true});
try {
  const page=await browser.newPage({viewport:{width:1440,height:900},deviceScaleFactor:1});
  for(const theme of themes){
    const html=await readFile(path.join(inputDir,theme+'.html'),'utf8');
    for(const [device,width,height] of [['desktop',1440,900],['mobile',375,812]]){
      await page.setViewportSize({width,height});
      await page.setContent(html,{waitUntil:'domcontentloaded'});
      const metrics=await page.evaluate(()=>{
        const home=document.querySelector('.fth-home');
        const rect=home?.getBoundingClientRect();
        return {
          overflow:document.documentElement.scrollWidth-window.innerWidth,
          width:rect?.width??0,
          height:rect?.height??0,
          links:[...document.querySelectorAll('.fth-home a')].filter(a=>!a.getAttribute('href')?.startsWith('/')).length,
          heading:document.querySelector('.fth-home h1,.fth-home h2')?.textContent?.trim()??''
        };
      });
      assert(metrics.width>200&&metrics.height>300,theme+' '+device+' has visible layout');
      assert(metrics.overflow<=2,theme+' '+device+' horizontal overflow '+metrics.overflow);
      assert(metrics.links===0,theme+' '+device+' contains invalid navigation link');
      assert(metrics.heading.length>4,theme+' '+device+' has no heading');
      await page.screenshot({path:path.join(outDir,theme+'-'+device+'.png'),fullPage:true});
      console.log('PASS',theme,device,JSON.stringify(metrics));
    }
  }
}finally{
  await browser.close();
}
console.log('PASS four frontend layout browser fixture tests; staging content QA remains necessary');
