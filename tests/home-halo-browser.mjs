import { chromium } from 'playwright';
import { readFile, mkdir } from 'node:fs/promises';
import assert from 'node:assert/strict';
import path from 'node:path';

const root=path.resolve(import.meta.dirname,'..');
const [base,haloCss,loader]=await Promise.all(
  ['app.css','home-halo.css','home-halo.js'].map(f=>readFile(path.join(root,'public_html/assets',f),'utf8'))
);
const output=path.join(root,'tests/home-halo-previews');
await mkdir(output,{recursive:true});
const layout=()=>[
'<!doctype html><html lang="en"><head><meta charset="utf-8">',
'<meta name="viewport" content="width=device-width,initial-scale=1">',
'<style>',base,'\n',haloCss,'</style></head>',
'<body class="public-shell theme-default">',
'<main class="main-container">',
'<section class="newsstand-hero has-vanta-halo">',
'<div class="epdi-halo-layer" aria-hidden="true" data-epaper-halo',
' data-three-src="/assets/vendor/three.r134.min.js?v=qa"',
' data-halo-src="/assets/vendor/vanta.halo.0.5.24.min.js?v=qa"></div>',
'<div class="newsstand-hero-copy"><span class="eyebrow">TODAYS EDITION</span>',
'<h1>Civic Mirror</h1>',
'<p class="newsstand-tagline">The City. The Stories. The Truth.</p>',
'<time class="newsstand-date">Saturday, 31 October 2026</time>',
'<div class="edition-meta-line">Pune · Marathi · 15 pages</div>',
'<div class="hero-actions"><a class="btn primary" href="/edition/demo/page/1">Read todays edition</a>',
'<a class="btn ghost" href="/archive">Browse archive</a></div></div>',
'<a class="newsstand-hero-cover" href="/edition/demo/page/1" aria-label="Read Civic Mirror">',
'<div class="paper-placeholder cover-missing"><span>Front page</span></div></a>',
'</section><section style="min-height:1300px;padding:40px 0"><h2>Recent editions</h2></section></main>',
'<script>',loader,'</script></body></html>'
].join('');
const threeStub='window.THREE={REVISION:"134"};window.__threeLoaded=(window.__threeLoaded||0)+1;';
const haloStub='window.VANTA={HALO:function(options){'+
  'window.__haloStarted=(window.__haloStarted||0)+1;'+
  'window.__haloOptions={mouseControls:options.mouseControls,touchControls:options.touchControls,gyroControls:options.gyroControls};'+
  'var c=document.createElement("canvas");options.el.appendChild(c);'+
  'return {destroy:function(){window.__haloDestroyed=(window.__haloDestroyed||0)+1;c.remove();}};'+
'}};';

const browser=await chromium.launch({headless:true});
try{
  for(const config of [
    {name:'desktop',width:1440,motion:'no-preference',shouldLoad:true},
    {name:'mobile',width:390,motion:'no-preference',shouldLoad:false},
    {name:'reduced-motion',width:1440,motion:'reduce',shouldLoad:false}
  ]){
    const context=await browser.newContext({
      viewport:{width:config.width,height:900},reducedMotion:config.motion,deviceScaleFactor:1
    });
    await context.addInitScript(()=>{
      window.__haloStarted=0;window.__haloDestroyed=0;window.__threeLoaded=0;
      window.WebGLRenderingContext=window.WebGLRenderingContext||function(){};
      const original=HTMLCanvasElement.prototype.getContext;
      HTMLCanvasElement.prototype.getContext=function(type,...args){
        if(type==='webgl')return {getExtension:()=>({loseContext(){}})};
        return original.call(this,type,...args);
      };
    });
    const requests=[];
    const page=await context.newPage();
    await page.route('https://epaper-halo.invalid/**',async route=>{
      const url=new URL(route.request().url());
      if(url.pathname.endsWith('/three.r134.min.js')){
        requests.push('three');
        return route.fulfill({status:200,contentType:'text/javascript',body:threeStub});
      }
      if(url.pathname.endsWith('/vanta.halo.0.5.24.min.js')){
        requests.push('halo');
        return route.fulfill({status:200,contentType:'text/javascript',body:haloStub});
      }
      return route.fulfill({status:200,contentType:'text/html',body:layout()});
    });
    await page.goto('https://epaper-halo.invalid/');
    await page.waitForTimeout(2200);
    const actual=await page.evaluate(()=>({
      started:window.__haloStarted,destroyed:window.__haloDestroyed,
      running:document.querySelector('.epdi-halo-layer').classList.contains('epdi-halo-running'),
      overflow:document.documentElement.scrollWidth-innerWidth,
      action:document.querySelector('.hero-actions .btn.primary').getAttribute('href'),
    }));
    assert(actual.overflow<=2,config.name+' horizontal overflow '+actual.overflow);
    assert(actual.action==='/edition/demo/page/1','Newspaper reader URLs must remain unchanged');
    assert(actual.started===(config.shouldLoad?1:0),config.name+' unexpected WebGL start');
    assert(requests.length===(config.shouldLoad?2:0),config.name+' unexpected vendor requests');
    if(config.shouldLoad){
      assert(requests.join(',')==='three,halo','Pinned assets must load in order');
      assert(actual.running,'Eligible desktop should start one halo');
      const controls=await page.evaluate(()=>window.__haloOptions);
      assert(!controls.mouseControls&&!controls.touchControls&&!controls.gyroControls,
        'Animation must never intercept interactions');
      await page.screenshot({path:path.join(output,'halo-desktop-static-fixture.png')});
      await page.evaluate(()=>window.scrollTo(0,1350));
      await page.waitForTimeout(400);
      assert((await page.evaluate(()=>window.__haloDestroyed))>=1,'Offscreen WebGL instance must be destroyed');
    }else{
      assert(!actual.running,config.name+' should retain static fallback');
      await page.screenshot({path:path.join(output,'halo-'+config.name+'-static.png')});
    }
    console.log('PASS',config.name,JSON.stringify({vendorRequests:requests.length,...actual}));
    await context.close();
  }

  const context=await browser.newContext({viewport:{width:1280,height:800}});
  const page=await context.newPage();
  await page.goto('about:blank');
  await page.setContent(layout().replace('class="public-shell theme-default"','class="reader-page theme-default"'));
  await page.waitForTimeout(1000);
  assert((await page.locator('.epdi-halo-layer.epdi-halo-running').count())===0,
    'HALO must not initialize on a Reader surface');
  await context.close();
  console.log('PASS HALO page isolation and static fallback');
}finally{
  await browser.close();
}
