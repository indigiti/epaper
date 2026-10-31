import { chromium } from 'playwright';
import { readFile, mkdir } from 'node:fs/promises';
import path from 'node:path';
import assert from 'node:assert/strict';

const root=path.resolve(import.meta.dirname,'..');
const source=path.join(root,'tests/reader-hub-fixtures');
const output=path.join(root,'tests/reader-hub-screenshots');
await mkdir(output,{recursive:true});
const browser=await chromium.launch({headless:true});
try{
  const page=await browser.newPage({viewport:{width:1440,height:900},deviceScaleFactor:1});
  const themes=[
    {name:'reading-light',file:'reading-light.html'},
    {name:'reading-dark',file:'reading-dark.html'},
    {name:'new-reader',file:'new-reader.html'}
  ];
  const colors=new Map();
  for(const mode of themes){
    const markup=await readFile(path.join(source,mode.file),'utf8');
    for(const [device,width,height] of [['desktop',1440,900],['tablet',768,1024],['mobile',390,844],['small-mobile',320,700]]){
      await page.setViewportSize({width,height});
      await page.setContent(markup,{waitUntil:'domcontentloaded'});
      const metrics=await page.evaluate(()=>{
        const el=document.querySelector('.reader-hub');
        const rect=el.getBoundingClientRect();
        const hero=document.querySelector('.rh-continue').getBoundingClientRect();
        const resume=document.querySelector('.rh-primary').getBoundingClientRect();
        const doc=document.documentElement;
        const dock=document.querySelector('.reader-mobile-dock');
        return {
          horizontalOverflow:doc.scrollWidth-innerWidth,
          width:rect.width,
          height:rect.height,
          heroWidth:hero.width,
          resumeWidth:resume.width,
          dockVisible:getComputedStyle(dock).display!=='none',
          colors:[getComputedStyle(document.body).backgroundColor,
            getComputedStyle(document.querySelector('.rh-panel')).backgroundColor],
          hasResume:!!document.querySelector('.rh-progress[aria-valuenow]')
        };
      });
      assert(metrics.width>=Math.min(width-55,200),mode.name+' '+device+' has narrow content');
      assert(metrics.heroWidth>=Math.min(width-55,200),mode.name+' '+device+' reading hero too narrow');
      assert(metrics.resumeWidth>=85,mode.name+' '+device+' reading CTA too narrow');
      assert(metrics.horizontalOverflow<=2,mode.name+' '+device+' horizontal overflow '+metrics.horizontalOverflow);
      assert(metrics.dockVisible===(width<=600),mode.name+' '+device+' mobile dock visibility incorrect');
      if(mode.name==='new-reader')assert(!metrics.hasResume,'First visit must not show fake reading progress');
      if(device==='desktop')colors.set(mode.name,metrics.colors.join('|'));
      await page.screenshot({path:path.join(output,mode.name+'-'+device+'.png'),fullPage:device==='desktop'});
      console.log('PASS',mode.name,device,JSON.stringify(metrics));
    }
  }
  assert(colors.get('reading-light')!==colors.get('reading-dark'),'Dark reader hub does not differ');
  await page.setViewportSize({width:390,height:844});
  await page.setContent(await readFile(path.join(source,'reading-light.html'),'utf8'));
  const more=page.locator('.reader-account-more');
  await more.locator('summary').click();
  assert(await more.evaluate(el=>el.open),'More reader navigation should expand');
  const dropdown=await page.locator('.reader-more-menu').boundingBox();
  assert(dropdown && dropdown.x>=-1 && dropdown.x+dropdown.width<=391,'More dropdown clipped by mobile nav');
  assert((await page.locator('.reader-more-menu a[href="/account/notifications"]').count())===1,'Notifications must remain reachable');
  assert((await page.locator('.reader-more-menu a[href="/logout"]').count())===1,'Sign out must remain reachable');
  const current=page.locator('.reader-mobile-dock a[aria-current="page"]');
  assert((await current.count())===1,'Exactly one mobile tab should be active');
  assert((await current.getAttribute('href'))==='/account','Reader Home should be active');
  const details=page.locator('.rh-tool-details');
  await details.locator('summary').click();
  assert(await details.evaluate(el=>el.open),'Tools details must be keyboard/mobile accessible');
  const titles=await page.locator('.rh-shelf-card strong').allTextContents();
  assert(titles.includes('Clippings')&&titles.includes('Bookmarks')&&titles.includes('Offline editions'),'Library shelves missing');
  console.log('PASS mobile navigation, accessible tools, dark mode and actual reader routes');
}finally{
  await browser.close();
}
