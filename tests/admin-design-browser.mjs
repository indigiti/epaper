import { chromium } from 'playwright';
import { readFile, mkdir } from 'node:fs/promises';
import path from 'node:path';
import assert from 'node:assert/strict';

const root=path.resolve(import.meta.dirname,'..');
const asset=async name=>readFile(path.join(root,'public_html/assets',name),'utf8');
const [base,beta,ops,workspace,studio,side,types,design,navJs,readerBase,readerThemes,authBase,authThemes]=await Promise.all(
  ['app.css','admin-beta.css','admin-operations.css','admin-workspace.css','admin-studio.css',
   'admin-sidebar.css','admin-typography.css','admin-design-system.css','admin-sidebar.js',
   'reader.css','reader-themes.css','auth-clean-final-v2.css','auth-themes.css'].map(asset)
);
const css=[base,beta,ops,workspace,studio,side,types,design].join('\n');
const output=path.join(root,'tests/admin-redesign-previews');
await mkdir(output,{recursive:true});

const section=(name,items,opened=false,active=false)=>
  '<details class="nav-section'+(active?' is-active':'')+'" data-admin-nav-section="'+name+'"'+(opened?' open':'')+'><summary class="nav-section-title"><span class="nav-section-label"><span class="nav-section-mark">▣</span><span class="nav-section-heading">'+name+'</span></span><span class="nav-section-chevron">⌄</span></summary><div class="nav-group">'+
  items.map(item=>'<a class="app-nav-link'+(item[1]==='/admin'?' active':'')+'" href="'+item[1]+'" '+(item[1]==='/admin'?'aria-current="page"':'')+' data-admin-search-terms="'+name+' '+item[0]+'"><span class="admin-nav-icon">◈</span><span class="admin-nav-text">'+item[0]+'</span></a>').join('')+'</div></details>';
const nav='<nav id="admin-side-sections" class="app-nav grouped-nav grouped-nav-compact admin-sidebar-nav" data-admin-sidebar-nav data-admin-nav-role="OWNER">'+
  section('OVERVIEW',[['Publishing Desk','/admin']],true,true)+
  section('PUBLISHING',[['Editions','/admin/editions'],['Publications','/admin/publications']],true)+
  section('SETTINGS',[['Publisher Settings','/admin/settings']])+'</nav>';
const fixture=theme=>
  '<!doctype html><html lang="en"><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>'+css+'</style></head>'+
  '<body class="admin-shell theme-'+theme+'" data-tenant-guid="test-tenant" data-user-guid="test-user">'+
  '<header class="site-header"><div class="site-header-inner"><a class="brand" href="/">ePaper Publishing Studio</a><div class="header-actions"><a class="console-health-link" href="#">Diagnostics</a><a class="btn primary console-create" href="#">Add new</a></div></div></header>'+
  '<div class="app-workspace"><aside class="app-sidebar" data-app-sidebar>'+
  '<div class="sidebar-context admin-sidebar-identity"><span class="admin-sidebar-emblem">▣</span><span class="admin-sidebar-brand-copy"><strong>ePaper</strong><small>Publishing Studio</small></span><button type="button" class="admin-sidebar-collapse" data-admin-sidebar-collapse aria-expanded="true">◀</button></div>'+
  '<div class="admin-sidebar-search"><span class="admin-sidebar-search-icon">⌕</span><input type="search" data-admin-nav-search placeholder="Search modules"><kbd>/</kbd><button type="button" data-admin-nav-search-expand class="admin-sidebar-search-expand">⌕</button></div>'+
  nav+'<p data-admin-sidebar-empty hidden>No matching modules</p><a class="admin-sidebar-account" href="/admin/account"><span class="admin-sidebar-avatar">◉</span><span class="admin-sidebar-account-copy"><strong>Publisher user</strong><small>Admin</small></span></a></aside>'+
  '<main id="main" class="main-container workspace-main"><div class="publishing-desk studio-desk"><section class="studio-hero"><div class="studio-hero-copy"><span class="studio-eyebrow">PUBLISHING STUDIO</span><h1>Welcome back</h1><p>All your editions and publishing activity in one place.</p><div class="studio-hero-actions"><a class="studio-button studio-button-primary" href="#">New edition</a><a class="studio-button studio-button-soft" href="#">View website</a></div></div><div class="studio-hero-aside"><span class="studio-hero-kicker">TODAYS FOCUS</span><a href="#"><strong>5</strong><span>Ready to publish</span></a><a href="#"><strong>2</strong><span>Needs attention</span></a></div></section>'+
  '<nav class="desk-metrics studio-metrics">'+[42,3,8,5].map(n=>'<a class="desk-metric"><span class="studio-metric-label">Publishing status</span><strong>'+n+'</strong><small>View details</small></a>').join('')+'</nav>'+
  '<section class="panel studio-pipeline-card"><div class="section-head"><h2>Edition pipeline</h2></div><div class="studio-pipeline-bars"><div class="studio-pipeline-row"><span class="studio-pipeline-label">Published</span><span class="studio-pipeline-track"><span class="bar-published" style="width:100%"></span></span><strong>42</strong></div></div></section>'+
  '<div class="studio-content-grid"><section class="panel"><h2>Recent editions</h2><p class="muted">Latest published newspapers.</p></section><section class="panel"><h2>System alerts</h2><p class="muted">Everything is operating normally.</p></section></div></div></main></div></body></html>';

const browser=await chromium.launch({headless:true});
try{
  const context=await browser.newContext({viewport:{width:1440,height:900},deviceScaleFactor:1});
  const page=await context.newPage();
  await page.route('https://studio.example.invalid/**',async route=>{
    const theme=new URL(route.request().url()).searchParams.get('theme')||'publisher-pro';
    await route.fulfill({status:200,contentType:'text/html',body:fixture(theme)});
  });
  const adminPalettes=new Set();
  for(const theme of ['publisher-pro','executive-slate','midnight-desk','default']){
    await page.goto('https://studio.example.invalid/fixture?theme='+theme);
    await page.addScriptTag({content:navJs});
    const result=await page.evaluate(()=>{
      const css=getComputedStyle(document.body);
      return {
        palette:[css.getPropertyValue('--studio-primary').trim(),css.getPropertyValue('--studio-bg').trim()].join('|'),
        width:document.querySelector('.app-sidebar').getBoundingClientRect().width,
        overflow:document.documentElement.scrollWidth-innerWidth
      };
    });
    assert(result.width>=220&&result.width<=300,theme+' nav width invalid');
    assert(result.overflow<=2,theme+' desktop overflow');
    assert(!adminPalettes.has(result.palette),theme+' duplicates another admin palette');
    adminPalettes.add(result.palette);
    await page.screenshot({path:path.join(output,'admin-'+theme+'-desktop.png'),fullPage:false});
    console.log('PASS admin theme',theme,result.palette);
  }
  await page.goto('https://studio.example.invalid/fixture?theme=publisher-pro');
  await page.addScriptTag({content:navJs});
  const settings=page.locator('details[data-admin-nav-section="SETTINGS"]');
  assert(!(await settings.evaluate(el=>el.open)),'Settings should begin collapsed');
  await settings.locator('summary').click();
  assert(await settings.evaluate(el=>el.open),'Native disclosure must open');
  await page.reload();
  await page.addScriptTag({content:navJs});
  assert(await settings.evaluate(el=>el.open),'Disclosure must persist across page reload');
  const search=page.locator('[data-admin-nav-search]');
  await search.fill('editions');
  assert(!(await page.locator('a[href="/admin/editions"]').isHidden()),'Search should show editions');
  assert(await page.locator('a[href="/admin/settings"]').isHidden(),'Search should filter settings');
  await search.fill('');
  assert(!(await settings.isHidden()),'Search reset should restore sections');
  const toggle=page.locator('[data-admin-sidebar-collapse]');
  await toggle.click();
  assert(await page.locator('body').evaluate(el=>el.classList.contains('admin-nav-collapsed')),'Sidebar should collapse');
  assert((await toggle.getAttribute('aria-expanded'))==='false','ARIA collapse state');
  await page.locator('details[data-admin-nav-section="PUBLISHING"] summary').click();
  assert(!(await page.locator('body').evaluate(el=>el.classList.contains('admin-nav-collapsed'))),'Rail selection should expand sidebar');
  await page.setViewportSize({width:390,height:844});
  await page.screenshot({path:path.join(output,'admin-publisher-pro-mobile.png'),fullPage:false});
  const over=await page.evaluate(()=>document.documentElement.scrollWidth-innerWidth);
  assert(over<=2,'Mobile dashboard horizontal overflow '+over);

  const auth=await context.newPage();
  const authStyles=new Set();
  for(const theme of ['default','split-editorial','gradient-focus','minimal-access']){
    const markup='<!doctype html><html><head><style>'+authBase+'\n'+authThemes+
      '</style><style>#epx-login-v5{position:fixed;inset:0;background:linear-gradient(#a8def4,#f8fbfc);padding:72px 20px}#epx-login-v5 .epx-card{width:min(100%,430px);background:#fff;padding:40px}</style></head>'+
      '<body class="auth-shell theme-'+theme+'"><section id="epx-login-v5"><a class="epx-brand">ePaper</a><div class="epx-card"><h1>Sign in</h1><form class="epx-form"><label>Email<input></label><button class="epx-submit">Continue</button></form></div></section></body></html>';
    await auth.setContent(markup);
    const computed=await auth.locator('#epx-login-v5').evaluate(el=>getComputedStyle(el).backgroundImage+'|'+getComputedStyle(el).backgroundColor);
    assert(!authStyles.has(computed),'Auth theme '+theme+' duplicates another');
    authStyles.add(computed);
    console.log('PASS authentication',theme);
  }
  await auth.close();

  const reader=await context.newPage();
  const chrome=new Set();
  for(const theme of ['default','newsstand-replica','editorial-luxe','metro-pulse','heritage-press','apple-editorial']){
    const markup='<!doctype html><html><head><style>'+base+'\n'+readerBase+'\n'+readerThemes+
      '</style></head><body class="reader-page theme-'+theme+'"><div class="reader-shell"><aside class="reader-pages"></aside><main class="reader-stage-wrap"><header class="reader-header">Sample newspaper</header><div class="reader-stage"></div></main><aside class="reader-rail"></aside></div></body></html>';
    await reader.setContent(markup);
    const value=await reader.evaluate(()=>{
      const bg=selector=>getComputedStyle(document.querySelector(selector)).backgroundColor;
      return [bg('.reader-header'),bg('.reader-pages'),bg('.reader-stage-wrap')].join('|');
    });
    assert(!chrome.has(value),'Reader '+theme+' same chrome as another');
    chrome.add(value);
    console.log('PASS reader',theme);
  }
  await reader.close();
  console.log('PASS admin navigation / theme differentiation / mobile smoke; live role acceptance still needed');
}finally{
  await browser.close();
}
