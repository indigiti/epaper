import { chromium } from 'playwright';
import { readFile, mkdir } from 'node:fs/promises';
import assert from 'node:assert/strict';
import path from 'node:path';

const root=path.resolve(import.meta.dirname,'..');
const files=[
  'app.css','home-halo.css','home-halo.js',
  'vendor/three.r134.min.js','vendor/vanta.halo.0.5.24.min.js'
];
const [base,css,loader,three,halo]=await Promise.all(
  files.map(p=>readFile(path.join(root,'public_html/assets',p),'utf8'))
);
const out=path.join(root,'tests/home-halo-previews');
await mkdir(out,{recursive:true});
const html=[
'<!doctype html><html lang="en"><head><meta charset="utf-8">',
'<meta name="viewport" content="width=device-width,initial-scale=1">',
'<style>',base,css,'</style></head><body class="public-shell theme-default">',
'<main class="main-container"><section class="newsstand-hero has-vanta-halo">',
'<div class="epdi-halo-layer" data-epaper-halo aria-hidden="true" ',
'data-three-src="/assets/vendor/three.r134.min.js?v=real-qa" ',
'data-halo-src="/assets/vendor/vanta.halo.0.5.24.min.js?v=real-qa"></div>',
'<div class="newsstand-hero-copy"><span class="eyebrow">TODAY’S EDITION</span>',
'<h1>Civic Mirror</h1><p class="newsstand-tagline">The City. The Stories. The Truth.</p>',
'<time class="newsstand-date">Saturday, 31 October 2026</time>',
'<div class="hero-actions"><a class="btn primary" href="/archive">Read today’s edition</a>',
'<a class="btn ghost" href="/archive">Browse archive</a></div></div>',
'<a class="newsstand-hero-cover" href="/archive">',
'<div class="paper-placeholder cover-missing"><span>Front page preview</span></div></a>',
'</section></main><script>',loader,'</script></body></html>'
].join('');

const browser=await chromium.launch({
  headless:true,
  args:['--enable-webgl','--use-gl=angle','--use-angle=swiftshader','--enable-unsafe-swiftshader']
});
try{
  const page=await browser.newPage({viewport:{width:1280,height:760},deviceScaleFactor:1});
  const errors=[];
  page.on('pageerror',error=>errors.push(error.message));
  await page.route('https://epaper-halo.invalid/**',async route=>{
    const p=new URL(route.request().url()).pathname;
    if(p.endsWith('/three.r134.min.js'))return route.fulfill({status:200,contentType:'text/javascript',body:three});
    if(p.endsWith('/vanta.halo.0.5.24.min.js'))return route.fulfill({status:200,contentType:'text/javascript',body:halo});
    return route.fulfill({status:200,contentType:'text/html',body:html});
  });
  await page.goto('https://epaper-halo.invalid/');
  const supported=await page.evaluate(()=>{
    try{const c=document.createElement('canvas');return Boolean(c.getContext('webgl'));}catch(_){return false;}
  });
  if(!supported){
    console.log('SKIP real WebGL screenshot: headless renderer does not support WebGL');
  }else{
    await page.waitForTimeout(3500);
    const value=await page.evaluate(()=>({
      started:document.querySelector('.epdi-halo-layer').classList.contains('epdi-halo-running'),
      canvas:!!document.querySelector('.epdi-halo-layer canvas'),
      three:!!window.THREE,
      vanta:typeof window.VANTA?.HALO==='function',
      overflow:document.documentElement.scrollWidth-innerWidth,
    }));
    // If WebGL is supported, the actual vendored renderer must start,
    // rather than only succeeding against a fake constructor.
    assert(value.started&&value.canvas&&value.three&&value.vanta,
      'Real Vanta HALO failed to initialize '+JSON.stringify(value)+
      ' errors='+errors.join('; '));
    assert(value.overflow<=2,'Real HALO caused horizontal overflow');
    await page.screenshot({path:path.join(out,'halo-real-webgl-desktop.png')});
    console.log('PASS real vendored Three.js + HALO integration',JSON.stringify(value));
  }
}finally{
  await browser.close();
}
