import { chromium } from 'playwright';
import { readFile, mkdir } from 'node:fs/promises';
import path from 'node:path';
import assert from 'node:assert/strict';

const root = path.resolve(import.meta.dirname, '..');
const baseCSS = await readFile(path.join(root, 'public_html/assets/app.css'), 'utf8');
const frontCSS = await readFile(path.join(root, 'public_html/assets/frontend-themes.css'), 'utf8');
const readerCSS = await readFile(path.join(root, 'public_html/assets/reader.css'), 'utf8');
const readerThemeCSS = await readFile(path.join(root, 'public_html/assets/reader-themes.css'), 'utf8');
const output = path.join(root, 'tests/theme-preview-artifacts');
await mkdir(output, { recursive: true });

const htmlFront = `<!doctype html><html lang="en"><head><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body class="public-shell">
<header class="site-header"><div class="site-header-inner"><a class="brand" href="#">City News</a><nav class="site-nav-links"><a href="#">Today</a> <a href="#">Archive</a></nav></div></header>
<main class="main-container">
<section class="newsstand-hero"><div class="newsstand-hero-copy"><span class="eyebrow">TODAY'S EDITION</span><h1>City News</h1><p class="newsstand-tagline">Today's journalism, designed to be read.</p><p class="newsstand-date">Saturday, 31 October 2026</p><div class="hero-actions"><a class="btn primary" href="#">Read edition</a></div></div>
<div class="newsstand-hero-cover"><div style="width:185px;max-width:100%;height:245px;background:#fafafa;color:#232323;border:1px solid #ddd;padding:18px">NEWSPAPER<br><strong>Front page</strong></div></div></section>
<section class="section public-section"><div class="section-head"><h2>Recent editions</h2></div><div class="newsstand"><a class="edition-tile" href="#">31 October</a><a class="edition-tile" href="#">30 October</a></div></section>
</main></body></html>`;

const htmlReader = `<!doctype html><html lang="en"><head><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body class="reader-page"><div class="reader-shell">
<aside class="reader-pages"><strong>Pages</strong></aside>
<div class="reader-stage-wrap"><header class="reader-header"><div class="reader-title"><strong>City News</strong><span>Page 1 / 12</span></div><div class="reader-header-actions"><button class="reader-header-tool">Pages</button><button class="reader-header-tool">Search</button></div></header>
<div class="reader-stage"><div class="reader-document"><div style="width:270px;max-width:100%;height:390px;background:white;color:#232323;padding:25px;box-shadow:0 2px 10px #0002">City News<br>Newspaper page</div></div></div></div>
<aside class="reader-rail"><button>+</button><button>-</button></aside>
</div></body></html>`;

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 });
try {
  for (const [surface, html, base, skins, variants, sel] of [
    ['frontend',htmlFront,baseCSS,frontCSS,['newsstand-modern','editorial-portal','classic-newspaper','digital-magazine'],'.newsstand-hero'],
    ['reader',htmlReader,baseCSS + '\n' + readerCSS,readerThemeCSS,['newsstand-replica','editorial-luxe','metro-pulse','heritage-press','apple-editorial'],'.reader-header']
  ]) {
    await page.setContent(html);
    await page.addStyleTag({ content: base + '\n' + skins });
    const appearances = new Map();
    for (const variant of variants) {
      await page.evaluate(({surface,variant}) => { document.body.className = (surface==='reader'?'reader-page':'public-shell') + ' theme-' + variant; }, {surface,variant});
      const styles = await page.locator(sel).evaluate(el => {
        const s = getComputedStyle(el);
        return {color:s.color,background:s.backgroundColor,image:s.backgroundImage,border:s.borderBottomColor,font:s.fontFamily};
      });
      const key = JSON.stringify(styles);
      assert(!appearances.has(key), `${surface} ${variant} is visually identical in primary region to ${appearances.get(key)}`);
      appearances.set(key,variant);
      await page.screenshot({ path: path.join(output,`${surface}-${variant}-desktop.png`), fullPage:false });
      await page.setViewportSize({ width:390,height:844 });
      await page.screenshot({ path: path.join(output,`${surface}-${variant}-mobile.png`), fullPage:false });
      const visible = await page.locator(sel).evaluate(el => {
        const r=el.getBoundingClientRect();return r.width>0 && r.height>0 && r.left<window.innerWidth && r.right>0;
      });
      assert(visible, `${surface} ${variant} primary region invisible at mobile width`);
      await page.setViewportSize({width:1440,height:900});
      console.log('PASS',surface,variant,'desktop/mobile computed styles and screenshots');
    }
  }
} finally {
  await browser.close();
}
console.log('PASS synthetic browser-theme smoke; live staging reader interactions still require separate acceptance');
