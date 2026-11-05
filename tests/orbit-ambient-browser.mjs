import { chromium } from 'playwright';
import { readFile, mkdir } from 'node:fs/promises';
import path from 'node:path';
import assert from 'node:assert/strict';

const root=path.resolve(import.meta.dirname,'..');
const [base,reader,readerThemes,orbit]=await Promise.all(
 ['app.css','reader.css','reader-themes.css','orbit-ambient.css']
 .map(name=>readFile(path.join(root,'public_html/assets',name),'utf8'))
);
const out=path.join(root,'tests/orbit-ambient-previews');
await mkdir(out,{recursive:true});
const html=(surface,dark=false)=>`<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>${base}\n${reader}\n${readerThemes}\n${orbit}</style></head>
<body class="${surface==='reader'?'reader-page':'public-shell'} theme-newsstand-replica epdi-orbit-enabled${dark?' dark':''}">
<div class="epdi-orbit-ambient" aria-hidden="true">
<span class="epdi-orbit-cloud epdi-orbit-cloud--pink"></span>
<span class="epdi-orbit-cloud epdi-orbit-cloud--peach"></span>
<span class="epdi-orbit-cloud epdi-orbit-cloud--teal"></span>
<span class="epdi-orbit-cloud epdi-orbit-cloud--lavender"></span>
</div>
<header class="site-header"><div class="site-header-inner"><a class="brand" href="/">ePaper</a>
<button id="action" type="button">Open menu</button></div></header>
${surface==='reader'?`<main class="reader-main" id="main"><div class="reader-shell" id="reader">
<aside class="reader-pages"><strong>Pages</strong></aside>
<div class="reader-stage-wrap"><header class="reader-header">Edition 1 / 12</header>
<div class="reader-stage" data-page-stage><div class="reader-document" data-document>
<img id="newspaper" alt="Newspaper" width="320" height="460" 
src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='320' height='460'%3E%3Crect width='320' height='460' fill='white'/%3E%3Ctext x='24' y='55' font-size='28'%3ENEWSPAPER%3C/text%3E%3C/svg%3E"></div></div>
<nav class="reader-pager"><button id="next-page" type="button">Next page</button></nav></div></div></main>`:
`<main id="main" class="main-container"><section class="newsstand-hero"><div class="newsstand-hero-copy">
<h1>Today's edition</h1><p>Read news without compromising clarity.</p>
<a class="btn primary" href="/edition/demo/page/1">Read newspaper</a>
</div><div class="newsstand-hero-cover"><div class="paper-placeholder">Edition cover</div></div></section></main>`}
<script>document.querySelector('#action').addEventListener('click',()=>document.querySelector('#action').dataset.clicked='yes');</script>
</body></html>`;

const browser=await chromium.launch({headless:true});
try{
 for(const scenario of [
   {name:'frontend-desktop',surface:'frontend',width:1440,motion:'no-preference',dark:false,animated:true},
   {name:'reader-desktop',surface:'reader',width:1440,motion:'no-preference',dark:false,animated:true},
   {name:'reader-mobile',surface:'reader',width:390,motion:'no-preference',dark:false,animated:false},
   {name:'reader-reduced-motion',surface:'reader',width:1440,motion:'reduce',dark:false,animated:false},
   {name:'reader-dark',surface:'reader',width:1440,motion:'no-preference',dark:true,animated:true},
 ]){
   const context=await browser.newContext({
     viewport:{width:scenario.width,height:900},reducedMotion:scenario.motion,deviceScaleFactor:1
   });
   const page=await context.newPage();
   await page.setContent(html(scenario.surface,scenario.dark));
   const actual=await page.evaluate(()=>{
      const bg=document.querySelector('.epdi-orbit-ambient');
      const cloud=document.querySelector('.epdi-orbit-cloud--pink');
      const rs=document.querySelector('.reader-shell');
      const stage=document.querySelector('.reader-stage');
      const doc=document.querySelector('[data-document]');
      return {
        count:document.querySelectorAll('.epdi-orbit-cloud').length,
        opacity:getComputedStyle(bg).opacity,
        display:getComputedStyle(bg).display,
        pointerEvents:getComputedStyle(bg).pointerEvents,
        animation:getComputedStyle(cloud).animationName,
        overflow:document.documentElement.scrollWidth-innerWidth,
        shellBackground:rs?getComputedStyle(rs).backgroundColor:null,
        stageBackground:stage?getComputedStyle(stage).backgroundColor:null,
        documentExists:!!doc,
        imageExists:!!document.querySelector('#newspaper'),
        readerMainVisible:!!document.querySelector('.reader-main')
      };
   });
   assert.equal(actual.count,4,scenario.name+' has exactly four color layers');
   assert.equal(actual.pointerEvents,'none',scenario.name+' decoration must not intercept touch or clicks');
   assert(actual.overflow<=2,scenario.name+' horizontal overflow '+actual.overflow);
   if(scenario.dark)assert.equal(actual.display,'none','Dark reader hides ambience');
   else{
     assert.equal(actual.display,'block','Enabled light theme displays ambience');
     assert.equal(actual.animation==='none',!scenario.animated,scenario.name+' animation gating');
   }
   if(scenario.surface==='reader'){
     assert(actual.documentExists&&actual.imageExists&&actual.readerMainVisible,'Reader document unchanged');
     if(!scenario.dark){
       assert.equal(actual.shellBackground,'rgba(0, 0, 0, 0)','Reader shell must show background');
       assert.equal(actual.stageBackground,'rgba(0, 0, 0, 0)','Reader page stage must show background');
     }
   }
   await page.locator('#action').click();
   assert.equal(await page.locator('#action').getAttribute('data-clicked'),'yes','Controls remain interactive');
   await page.screenshot({path:path.join(out,scenario.name+'.png')});
   console.log('PASS',scenario.name,JSON.stringify(actual));
   await context.close();
 }
}finally{await browser.close();}
console.log('PASS circular pastel background browser acceptance');
