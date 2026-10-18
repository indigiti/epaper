(()=>{'use strict';
const $=(s,r=document)=>r.querySelector(s),$$=(s,r=document)=>[...r.querySelectorAll(s)];
const cfg=window.EpaperSaas||{};let csrfPromise=null;
function toast(message,type=''){const host=$('#toast-region');if(!host)return;const el=document.createElement('div');el.className='toast '+type;el.textContent=message;host.append(el);setTimeout(()=>el.remove(),2800)}window.epaperToast=toast;
const ensureCsrf=async()=>{if(cfg.csrf)return cfg.csrf;if(csrfPromise)return csrfPromise;csrfPromise=fetch('/api/v1/session/csrf',{credentials:'same-origin',headers:{Accept:'application/json','Cache-Control':'no-store'}}).then(async r=>{const d=await r.json();if(!r.ok||!d.csrf)throw new Error(d.code||'Unable to start secure session');cfg.csrf=d.csrf;window.EpaperSaas=cfg;return cfg.csrf}).finally(()=>{csrfPromise=null});return csrfPromise};
window.epaperApi=async(url,{method='POST',body=null,headers={}}={})=>{const verb=String(method||'POST').toUpperCase();const token=(verb==='GET'||verb==='HEAD')?(cfg.csrf||''):await ensureCsrf();const h={'Accept':'application/json','X-CSRF-Token':token,...headers};if(body!==null)h['Content-Type']='application/json';const r=await fetch(url,{method:verb,credentials:'same-origin',headers:h,body:body===null?null:JSON.stringify(body)});const d=await r.json().catch(()=>({ok:false,message:'Invalid server response'}));if(!r.ok)throw Object.assign(new Error(d.message||d.code||'Request failed'),{data:d,status:r.status});return d};
if('serviceWorker'in navigator)window.addEventListener('load',()=>{const v=encodeURIComponent(window.EpaperSaas?.version||'dev');navigator.serviceWorker.register('/service-worker.js?v='+v).catch(()=>{});});
const tenantThemeId=cfg.tenantGuid||document.body.dataset.tenantGuid||'tenant';const userThemeId=cfg.userGuid||document.body.dataset.userGuid||'guest';const themeKey='epaper.theme.'+tenantThemeId+'.'+userThemeId;const legacyUserThemeKey='epaper.theme.'+userThemeId;const isAdmin=document.body.classList.contains('admin-shell');const publicThemeMode=String(cfg.publicThemeMode||document.body.dataset.publicColorMode||'light').toLowerCase();const publicThemeSwitcher=String(cfg.publicThemeSwitcher||document.body.dataset.publicThemeSwitcher||'on').toLowerCase()!=='off';const systemTheme=matchMedia('(prefers-color-scheme:dark)');const resolveTheme=mode=>mode==='system'?(systemTheme.matches?'dark':'light'):(mode==='dark'?'dark':'light');const serverMode=isAdmin?(cfg.adminThemeMode||document.body.dataset.adminColorMode||''):'';const stored=(isAdmin||publicThemeSwitcher)?(localStorage.getItem(themeKey)||localStorage.getItem(legacyUserThemeKey)||localStorage.getItem('epaper.theme')||''):'';const initial=isAdmin?(serverMode||stored||resolveTheme('system')):(stored||resolveTheme(publicThemeMode));document.body.classList.toggle('dark',initial==='dark');
const updateThemeButton=()=>{$$('[data-theme-toggle]').forEach(b=>{const dark=document.body.classList.contains('dark');b.setAttribute('aria-label',dark?'Switch to light mode':'Switch to dark mode');b.title=dark?'Light mode':'Dark mode';b.setAttribute('aria-pressed',dark?'true':'false')})};updateThemeButton();
$$('[data-theme-toggle]').forEach(b=>b.addEventListener('click',async()=>{const before=document.body.classList.contains('dark')?'dark':'light';const mode=before==='dark'?'light':'dark';document.body.classList.toggle('dark',mode==='dark');localStorage.setItem(themeKey,mode);localStorage.setItem(legacyUserThemeKey,mode);updateThemeButton();if(!isAdmin){toast(mode==='dark'?'Dark mode enabled':'Light mode enabled');return;}try{const form=new URLSearchParams({_csrf:cfg.csrf||'',mode});const r=await fetch('/admin/preferences/theme',{method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:form.toString()});if(!r.ok)throw new Error('Theme preference could not be saved');const d=await r.json();if(!d.ok)throw new Error(d.code||'Theme preference could not be saved');toast(mode==='dark'?'Dark mode enabled':'Light mode enabled');}catch(e){document.body.classList.toggle('dark',before==='dark');localStorage.setItem(themeKey,before);updateThemeButton();toast('Appearance change could not be saved','error')}}));
if(!isAdmin&&publicThemeMode==='system'&&!(publicThemeSwitcher&&stored)){systemTheme.addEventListener?.('change',e=>{document.body.classList.toggle('dark',!!e.matches);updateThemeButton()})}
const centerRail=(rail,item)=>{if(!rail||!item)return;requestAnimationFrame(()=>{const left=item.offsetLeft-(rail.clientWidth-item.offsetWidth)/2;rail.scrollTo({left:Math.max(0,left),behavior:'auto'})})};$$('.archive-date-rail').forEach(rail=>centerRail(rail,rail.querySelector('[aria-current="date"]')));$$('[data-edition-date-rail]').forEach(rail=>centerRail(rail,rail.querySelector('[aria-current="date"]')));$$('[data-edition-strip-scroll]').forEach(btn=>btn.addEventListener('click',()=>{const host=btn.closest('[data-reader-edition-switcher]');const rail=host?.querySelector('[data-edition-date-rail]');if(!rail)return;const dir=Number(btn.dataset.editionStripScroll)||1;const reduce=matchMedia('(prefers-reduced-motion: reduce)').matches;rail.scrollBy({left:dir*Math.max(240,rail.clientWidth*.72),behavior:reduce?'auto':'smooth'})}));
// Privacy-minimal real-user performance sampling. No URL query, title, user ID,
 // content, cookie or request body is sent; only route path + Web Vitals numbers.
if(Math.random()<Number(cfg.rumSampleRate||0.10)){
  const vitals={route:location.pathname};let cls=0,lcp=0,inp=0;
  try{new PerformanceObserver(list=>{for(const e of list.getEntries())lcp=Math.max(lcp,e.startTime||0)}).observe({type:'largest-contentful-paint',buffered:true})}catch(_e){}
  try{new PerformanceObserver(list=>{for(const e of list.getEntries())if(!e.hadRecentInput)cls+=e.value||0}).observe({type:'layout-shift',buffered:true})}catch(_e){}
  try{new PerformanceObserver(list=>{for(const e of list.getEntries())inp=Math.max(inp,e.duration||0)}).observe({type:'event',buffered:true,durationThreshold:40})}catch(_e){}
  let sent=false;const send=()=>{if(sent)return;sent=true;vitals.lcp=Math.round(lcp*10)/10;vitals.cls=Math.round(cls*10000)/10000;vitals.inp=Math.round(inp*10)/10;const body=JSON.stringify(vitals);try{if(navigator.sendBeacon){navigator.sendBeacon('/api/v1/performance/vitals',new Blob([body],{type:'application/json'}));return}}catch(_e){}fetch('/api/v1/performance/vitals',{method:'POST',credentials:'omit',keepalive:true,headers:{'Content-Type':'application/json'},body}).catch(()=>{})};addEventListener('pagehide',send,{once:true});}
})();

// ePaper SaaS v1.0.1.0 — RabbitMQ/Supervisor Processing Center live polling
(()=>{
  const center=document.querySelector('[data-processing-center]');
  if(!center)return;
  const body=center.querySelector('[data-processing-body]');
  const activeEl=document.querySelector('[data-processing-active]');
  const failedEl=document.querySelector('[data-processing-failed]');
  const stalledEl=document.querySelector('[data-processing-stalled]');
  const liveLabel=document.querySelector('[data-processing-live-label]');
  const liveWrap=liveLabel?.closest('.processing-live');
  const csrf=window.EpaperSaas?.csrf||'';
  let timer=null,busy=false,lastFingerprint='';
  const stageLabels={QUEUED:'Waiting for RabbitMQ worker',RETRY_WAIT:'Waiting for retry',STARTING:'Supervisor worker starting',PREPARING:'Preparing edition',RENDERING_PDF:'Rendering PDF',PDF_RENDERED:'PDF rendered',PAGE_SETUP:'Preparing page',THUMBNAIL:'Generating thumbnail',PREVIEW:'Generating preview',DEEP_ZOOM:'Generating Deep Zoom',OCR:'Running OCR',PAGE_COMPLETE:'Page complete',WAITING_FOR_LOCK:'Waiting for edition lock',COMPLETED:'Completed',FAILED:'Failed'};
  const node=(tag,cls,text)=>{const e=document.createElement(tag);if(cls)e.className=cls;if(text!==undefined)e.textContent=String(text);return e};
  const actionForm=(r)=>{
    if(!(r.status==='FAILED'||r.status==='NEEDS_ATTENTION'||r.stale)||!r.edition_guid)return null;
    const f=node('form','inline');f.method='post';f.action='/admin/editions/'+encodeURIComponent(r.edition_guid)+(r.stale?'/recover':'/reprocess');
    const i=document.createElement('input');i.type='hidden';i.name='_csrf';i.value=csrf;f.append(i);
    const label=r.stale?'Recover unfinished work':'Retry processing';const b=node('button','console-icon-action is-process');b.type='submit';b.title=label;b.setAttribute('aria-label',label);const svg=document.createElementNS('http://www.w3.org/2000/svg','svg');svg.setAttribute('viewBox','0 0 24 24');svg.setAttribute('class','ui-icon');svg.setAttribute('aria-hidden','true');svg.setAttribute('fill','none');svg.setAttribute('stroke','currentColor');svg.setAttribute('stroke-width','1.8');const path=document.createElementNS('http://www.w3.org/2000/svg','path');path.setAttribute('d','M20 6v5h-5 M19 11a7 7 0 1 0 1 5');svg.append(path);b.append(svg);f.append(b);return f;
  };
  const renderRow=(r)=>{
    const tr=document.createElement('tr');tr.dataset.jobGuid=r.job_guid||'';
    const editionTd=document.createElement('td');if(r.edition_guid){const a=node('a','text-link',r.edition||'');a.href='/admin/processing/'+encodeURIComponent(r.edition_guid);editionTd.append(a);}else editionTd.textContent=r.edition||'';tr.append(editionTd);
    const statusTd=document.createElement('td');statusTd.append(node('span','status '+String(r.status||'').toLowerCase(),r.status||''));if(r.stale){statusTd.append(document.createTextNode(' '));statusTd.append(node('span','pill warning','STALE'));}tr.append(statusTd);
    const progressTd=document.createElement('td');const pct=Math.max(0,Math.min(100,Number(r.progress)||0));const bar=node('div','processing-progress');bar.setAttribute('role','progressbar');bar.setAttribute('aria-valuemin','0');bar.setAttribute('aria-valuemax','100');bar.setAttribute('aria-valuenow',String(pct));const fill=node('span');fill.style.width=pct+'%';bar.append(fill);const meta=node('div','processing-progress-meta');meta.append(node('strong','',pct.toFixed(2)+'%'));meta.append(node('span','',`Attempt ${Math.max(1,Number(r.attempt_count)||1)}`));progressTd.append(bar,meta);tr.append(progressTd);
    let activity=r.message||stageLabels[r.stage]||r.stage||'Waiting';if(r.page&&r.total_pages)activity=`Page ${r.page}/${r.total_pages} · ${activity}`;tr.append(node('td','',activity));
    const last=node('td');const time=node('time','',r.updated_at||'');last.append(time);tr.append(last);
    const actionTd=document.createElement('td');if(r.error)actionTd.append(node('span','processing-error',r.error));const form=actionForm(r);if(form)actionTd.append(form);tr.append(actionTd);return tr;
  };
  const fingerprint=(data)=>JSON.stringify({active:Number(data.active)||0,rows:(Array.isArray(data.rows)?data.rows:[]).map(r=>[r.job_guid,r.status,r.stage,r.progress,r.updated_at,r.error,r.stale,r.attempt_count])});
  const render=(data)=>{
    if(!body)return;const rows=Array.isArray(data.rows)?data.rows:[];
    const nextFingerprint=fingerprint(data);
    if(nextFingerprint!==lastFingerprint){
      lastFingerprint=nextFingerprint;body.replaceChildren();
      if(!rows.length){const tr=document.createElement('tr');const td=node('td');td.colSpan=6;td.append(node('div','empty-inline','No processing jobs yet.'));tr.append(td);body.append(tr);}else rows.forEach(r=>body.append(renderRow(r)));
    }
    const active=Number(data.active)||0;const failed=rows.filter(r=>r.status==='FAILED'||r.status==='NEEDS_ATTENTION').length;if(activeEl)activeEl.textContent=String(active);if(failedEl)failedEl.textContent=String(failed);if(stalledEl){const stalled=rows.filter(r=>r.stale).length;stalledEl.textContent=String(stalled);stalledEl.className=stalled?'bad':'good';}if(liveLabel)liveLabel.textContent=active?'Live updates':'Idle';liveWrap?.classList.toggle('is-idle',!active);
    return active;
  };
  const schedule=(delay)=>{clearTimeout(timer);timer=setTimeout(poll,delay)};
  const poll=async()=>{
    if(busy){schedule(2000);return}
    if(document.hidden){schedule(10000);return}
    busy=true;let delay=10000;
    try{
      const r=await fetch('/admin/processing?ajax=1',{credentials:'same-origin',headers:{Accept:'application/json','Cache-Control':'no-store'}});
      if(!r.ok)throw new Error('processing poll failed');
      const data=await r.json();delay=render(data)>0?2000:10000;
    }catch(e){delay=15000}
    finally{busy=false;schedule(delay)}
  };
  document.addEventListener('visibilitychange',()=>{if(!document.hidden){clearTimeout(timer);poll()}});poll();
})();

// ePaper SaaS v1.0.1.2 — infrastructure end-to-end probe
(()=>{
  const root=document.querySelector('[data-infra-probe]');
  if(!root)return;
  const probe=root.dataset.infraProbe;
  const summary=root.querySelector('[data-probe-summary]');
  const started=Date.now();let done=false;
  const poll=async()=>{
    if(done)return;
    try{
      const r=await fetch('/admin/system/infrastructure/probe-status?probe='+encodeURIComponent(probe),{credentials:'same-origin',headers:{Accept:'application/json','Cache-Control':'no-store'}});
      const d=await r.json();
      for(const [lane,state] of Object.entries(d.lanes||{})){
        const el=root.querySelector('[data-probe-lane="'+lane+'"]');if(!el)continue;const strong=el.querySelector('strong');if(strong){strong.textContent=state.ok?'PASS':'WAITING';strong.className=state.ok?'good':'';}
      }
      if(d.complete){done=true;if(summary){summary.textContent='PASS · Outbox → RabbitMQ → all Supervisor workers';summary.className='good';}return;}
      if(summary)summary.textContent=(Number(d.outbox_pending)||0)+' probe messages pending · waiting for workers';
    }catch(e){if(summary)summary.textContent='Diagnostic request failed; retrying…';}
    if(Date.now()-started<45000)setTimeout(poll,1500);else if(summary){summary.textContent='Timed out after 45s — check outbox and worker heartbeats';summary.className='bad';}
  };
  poll();
})();

// ePaper SaaS v1.1.1 — role-aware publisher command palette
(()=>{
  const palette=document.querySelector('[data-command-palette]');if(!palette)return;
  const input=palette.querySelector('[data-command-input]'),results=palette.querySelector('[data-command-results]');
  const commands=[...document.querySelectorAll('.app-sidebar .app-nav-link')].map(a=>[a.querySelector('span')?.textContent?.trim()||a.textContent.trim(),a.getAttribute('href')||'#',a.closest('.nav-section')?.querySelector('.nav-section-title span')?.textContent?.trim()||a.closest('.nav-group')?.querySelector('.nav-group-label')?.textContent?.trim()||'Navigate']).filter(c=>c[1]&&c[1]!=='#');
  if(commands.some(c=>c[1]==='/admin/editions'))commands.unshift(['New edition','/admin/editions','Quick action']);
  let active=0,filtered=[],previousFocus=null;
  const close=()=>{palette.classList.add('hidden');input.value='';document.body.style.overflow='';const target=previousFocus;previousFocus=null;if(target&&target.isConnected)setTimeout(()=>target.focus(),0)};
  const paintActive=()=>{[...results.querySelectorAll('.command-item')].forEach((el,i)=>el.classList.toggle('active',i===active))};
  const setActive=(index)=>{active=Math.max(0,Math.min(index,Math.max(0,filtered.length-1)));paintActive()};
  const render=()=>{const q=input.value.trim().toLowerCase();filtered=commands.filter(c=>!q||c.join(' ').toLowerCase().includes(q)).slice(0,14);active=Math.min(active,Math.max(0,filtered.length-1));results.replaceChildren(...filtered.map((c,i)=>{const a=document.createElement('a');a.href=c[1];a.className='command-item'+(i===active?' active':'');const label=document.createElement('span');label.textContent=c[0];const group=document.createElement('small');group.textContent=c[2];a.append(label,group);a.addEventListener('pointerenter',()=>setActive(i),{passive:true});return a}))};
  const open=()=>{previousFocus=document.activeElement;palette.classList.remove('hidden');document.body.style.overflow='hidden';render();setTimeout(()=>input.focus(),0)};
  document.querySelector('[data-command-trigger]')?.addEventListener('click',open);palette.addEventListener('click',e=>{if(e.target===palette)close()});
  document.addEventListener('keydown',e=>{if((e.metaKey||e.ctrlKey)&&e.key.toLowerCase()==='k'){e.preventDefault();palette.classList.contains('hidden')?open():close();return}if(palette.classList.contains('hidden'))return;if(e.key==='Escape'){e.preventDefault();close()}else if(e.key==='ArrowDown'){e.preventDefault();setActive(active+1)}else if(e.key==='ArrowUp'){e.preventDefault();setActive(active-1)}else if(e.key==='Enter'&&filtered[active]){e.preventDefault();location.href=filtered[active][1]}});input.addEventListener('input',()=>{active=0;render()});
})();

// ePaper SaaS v1.1.1 — responsive Admin/Account navigation drawer
(()=>{const b=document.querySelector('[data-app-nav-toggle]'),sidebar=document.querySelector('[data-app-sidebar]'),scrim=document.querySelector('[data-sidebar-scrim]');if(!b||!sidebar)return;const close=()=>document.body.classList.remove('app-nav-open');const open=()=>document.body.classList.add('app-nav-open');b.addEventListener('click',()=>document.body.classList.contains('app-nav-open')?close():open());scrim?.addEventListener('click',close);document.addEventListener('keydown',e=>{if(e.key==='Escape')close()});sidebar.addEventListener('click',e=>{if(e.target.closest('a'))close()})})();

// ePaper SaaS v1.1.4 — premium clipping page, real format preparation and QR
(()=>{
  const root=document.querySelector('[data-clip-page]');if(!root)return;
  const slug=root.dataset.clipSlug,url=root.dataset.clipUrl,canManage=root.dataset.canManage==='1';
  let formats={};try{formats=JSON.parse(root.dataset.clipFormats||'{}')}catch{}
  const supported=()=>Object.values(formats).filter(f=>f.supported).length;
  const ready=()=>Object.values(formats).filter(f=>f.supported&&f.ready).length;
  const pct=()=>supported()?Math.round(ready()*100/supported()):100;
  const summary=document.querySelector('[data-format-summary]'),percent=document.querySelector('[data-format-percent]'),bar=document.querySelector('[data-format-bar]'),message=document.querySelector('[data-format-message]');
  function refreshProgress(label=''){
    const p=pct();if(summary)summary.textContent=`${ready()} of ${supported()} formats ready`;if(percent)percent.textContent=p+'%';if(bar)bar.style.width=p+'%';if(message)message.textContent=label||(p>=100?'Ready to share':'Preparing optimized formats…');
  }
  function markFormat(key,state){formats[key]={...(formats[key]||{}),...state};const card=document.querySelector(`[data-clip-format="${CSS.escape(key)}"]`);if(!card)return;card.classList.remove('ready','preparing','unsupported');card.classList.add(state.supported===false?'unsupported':state.ready?'ready':'preparing');const s=card.querySelector('[data-format-state]');if(s)s.textContent=state.supported===false?'Unavailable':state.ready?'Ready':'Preparing…';if(state.ready){card.href=formats[key].download||`/c/${encodeURIComponent(slug)}/download/${encodeURIComponent(key)}`;card.removeAttribute('aria-disabled');card.setAttribute('download','')}else{card.href='#';card.setAttribute('aria-disabled','true')}}
  async function prepareFormats(){
    if(!canManage)return refreshProgress();
    const queue=Object.entries(formats).filter(([,f])=>f.supported&&!f.ready);if(!queue.length)return refreshProgress();
    for(const [key,f] of queue){
      refreshProgress(`Preparing ${f.label}…`);
      try{const d=await epaperApi(`/api/v1/clips/${encodeURIComponent(slug)}/formats/${encodeURIComponent(key)}`,{body:{}});const one=d.format||{};markFormat(key,{ready:!!one.ready,supported:one.supported!==false,download:`/c/${slug}/download/${key}`});if(d.formats)for(const [k,v] of Object.entries(d.formats)){formats[k]=v;markFormat(k,v)}}catch(e){markFormat(key,{ready:false,supported:false});}
      refreshProgress();
    }
    refreshProgress(pct()>=100?'Ready to share':'Available formats are ready');
  }
  const shares=document.querySelector('[data-clip-shares]'),downloads=document.querySelector('[data-clip-downloads]');
  const updateStats=d=>{if(d?.stats){if(shares)shares.textContent=String(d.stats.shares??shares.textContent);if(downloads)downloads.textContent=String(d.stats.downloads??downloads.textContent)}};
  document.querySelectorAll('[data-clip-share]').forEach(a=>a.addEventListener('click',()=>{epaperApi(`/api/v1/clips/${encodeURIComponent(slug)}/share`,{body:{platform:a.dataset.clipShare||'share'}}).then(updateStats).catch(()=>{})}));
  document.querySelector('[data-clip-copy]')?.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(url);epaperToast?.('Clipping link copied');const d=await epaperApi(`/api/v1/clips/${encodeURIComponent(slug)}/share`,{body:{platform:'copy'}});updateStats(d)}catch(e){epaperToast?.('Could not copy clipping link','error')}});
  document.querySelectorAll('.clip-format-card.ready').forEach(a=>a.addEventListener('click',()=>{if(downloads)downloads.textContent=String((parseInt(downloads.textContent||'0',10)||0)+1)}));
  function qrMatrixV5L(text){
    const bytes=new TextEncoder().encode(text);if(bytes.length>106)throw new Error('URL too long for embedded QR');
    const bits=[],append=(val,len)=>{for(let i=len-1;i>=0;i--)bits.push((val>>>i)&1)};append(4,4);append(bytes.length,8);bytes.forEach(b=>append(b,8));const capacity=108*8;for(let i=0;i<Math.min(4,capacity-bits.length);i++)bits.push(0);while(bits.length%8)bits.push(0);const data=[];for(let i=0;i<bits.length;i+=8){let b=0;for(let j=0;j<8;j++)b=(b<<1)|bits[i+j];data.push(b)}for(let pad=0;data.length<108;pad++)data.push(pad%2?0x11:0xEC);
    const exp=new Array(512),log=new Array(256);let gx=1;for(let i=0;i<255;i++){exp[i]=gx;log[gx]=i;gx<<=1;if(gx&0x100)gx^=0x11d}for(let i=255;i<512;i++)exp[i]=exp[i-255];const mul=(a,b)=>a&&b?exp[log[a]+log[b]]:0;const degree=26,div=new Array(degree).fill(0);div[degree-1]=1;let rsRoot=1;for(let i=0;i<degree;i++){for(let j=0;j<degree;j++){div[j]=mul(div[j],rsRoot);if(j+1<degree)div[j]^=div[j+1]}rsRoot=mul(rsRoot,2)}const rem=new Array(degree).fill(0);for(const b of data){const factor=b^rem[0];rem.shift();rem.push(0);for(let j=0;j<degree;j++)rem[j]^=mul(div[j],factor)}const code=data.concat(rem),size=37,m=Array.from({length:size},()=>Array(size).fill(false)),fn=Array.from({length:size},()=>Array(size).fill(false));const setF=(x,y,d)=>{if(x>=0&&y>=0&&x<size&&y<size){m[y][x]=!!d;fn[y][x]=true}};
    for(let i=0;i<size;i++){setF(6,i,i%2===0);setF(i,6,i%2===0)}const finder=(cx,cy)=>{for(let dy=-4;dy<=4;dy++)for(let dx=-4;dx<=4;dx++){const dist=Math.max(Math.abs(dx),Math.abs(dy));setF(cx+dx,cy+dy,dist!==2&&dist!==4)}};finder(3,3);finder(size-4,3);finder(3,size-4);const align=(cx,cy)=>{for(let dy=-2;dy<=2;dy++)for(let dx=-2;dx<=2;dx++)setF(cx+dx,cy+dy,Math.max(Math.abs(dx),Math.abs(dy))!==1)};align(30,30);
    const formatBits=()=>{const dataBits=8;let r=dataBits;for(let i=0;i<10;i++)r=(r<<1)^(((r>>>9)&1)*0x537);return((dataBits<<10)|r)^0x5412};const drawFormat=()=>{const b=formatBits();for(let i=0;i<=5;i++)setF(8,i,(b>>>i)&1);setF(8,7,(b>>>6)&1);setF(8,8,(b>>>7)&1);setF(7,8,(b>>>8)&1);for(let i=9;i<15;i++)setF(14-i,8,(b>>>i)&1);for(let i=0;i<8;i++)setF(size-1-i,8,(b>>>i)&1);for(let i=8;i<15;i++)setF(8,size-15+i,(b>>>i)&1);setF(8,size-8,true)};drawFormat();const all=[];for(const b of code)for(let i=7;i>=0;i--)all.push((b>>>i)&1);let k=0;for(let right=size-1;right>=1;right-=2){if(right===6)right--;for(let vert=0;vert<size;vert++){const upward=((right+1)&2)===0,y=upward?size-1-vert:vert;for(let j=0;j<2;j++){const x=right-j;if(fn[y][x])continue;let bit=k<all.length?all[k]:0;k++;if(((x+y)&1)===0)bit^=1;m[y][x]=!!bit}}}drawFormat();return m;
  }
  function renderQr(host,value){try{const m=qrMatrixV5L(value),quiet=4,size=m.length+quiet*2,ns='http://www.w3.org/2000/svg',svg=document.createElementNS(ns,'svg');svg.setAttribute('viewBox',`0 0 ${size} ${size}`);svg.setAttribute('shape-rendering','crispEdges');const bg=document.createElementNS(ns,'rect');bg.setAttribute('width','100%');bg.setAttribute('height','100%');bg.setAttribute('fill','#fff');svg.append(bg);let d='';for(let y=0;y<m.length;y++)for(let x=0;x<m.length;x++)if(m[y][x])d+=`M${x+quiet} ${y+quiet}h1v1h-1z`;const path=document.createElementNS(ns,'path');path.setAttribute('d',d);path.setAttribute('fill','#000');svg.append(path);host.replaceChildren(svg)}catch(e){host.textContent='QR unavailable'}}
  document.querySelectorAll('[data-clip-qr]').forEach(el=>renderQr(el,el.dataset.url||url));refreshProgress();prepareFormats();
})();

/* EPAPER_AUTH_COMPACT_RUNTIME_V3 — live auth card content-height repair */
(()=>{
  if(window.__EPAPER_AUTH_COMPACT_RUNTIME_V3)return;
  window.__EPAPER_AUTH_COMPACT_RUNTIME_V3=true;
  const imp=(el,prop,val)=>{if(el)el.style.setProperty(prop,val,'important')};
  const clearBox=(el)=>{
    if(!el)return;
    imp(el,'height','auto');imp(el,'min-height','0');imp(el,'max-height','none');
    imp(el,'flex','0 0 auto');imp(el,'flex-grow','0');imp(el,'align-self','auto');
    imp(el,'margin-bottom','0');imp(el,'overflow','visible');
  };
  function repair(){
    if(!document.body || (!document.body.classList.contains('auth-shell') && location.pathname!='/login'))return;
    const card=document.querySelector('.auth-fit-card,.auth-card');
    if(!card)return;
    const wrap=card.querySelector('.auth-fit-form')||card.querySelector('.auth-form');
    const panel=card.querySelector('.auth-fit-panel')||card.querySelector('.auth-panel')||wrap;
    const fields=card.querySelector('.auth-fit-fields')||card.querySelector('form');
    const security=card.querySelector('.auth-fit-security,.auth-security');
    const quote=document.querySelector('.auth-fit-quote-card,.auth-fit-quote,.ep-login-quote');
    // Keep quote in the same document flow immediately after the trust/security note.
    if(quote&&security&&quote.previousElementSibling!==security)security.insertAdjacentElement('afterend',quote);
    // Destroy all known stretch mechanisms, regardless of the legacy auth stylesheet.
    imp(card,'box-sizing','border-box');imp(card,'display','block');imp(card,'height','auto');imp(card,'min-height','0');imp(card,'max-height','none');
    imp(card,'align-content','start');imp(card,'justify-content','flex-start');imp(card,'overflow','visible');
    imp(card,'padding-top','40px');imp(card,'padding-bottom','18px');
    [wrap,panel,fields].forEach(el=>{clearBox(el);imp(el,'margin-top',el===fields?'16px':'0');imp(el,'padding-top','0');imp(el,'padding-bottom','0');});
    if(wrap){imp(wrap,'display','block');imp(wrap,'justify-content','flex-start');imp(wrap,'align-items','stretch')}
    if(panel){imp(panel,'display','block');imp(panel,'justify-content','flex-start');imp(panel,'align-items','stretch')}
    if(fields){imp(fields,'display','grid');imp(fields,'gap','9px')}
    if(security){clearBox(security);imp(security,'margin-top','9px')}
    if(quote){clearBox(quote);imp(quote,'position','relative');imp(quote,'margin-top','9px');imp(quote,'margin-bottom','0');imp(quote,'float','none')}
    // Empty layout-only children must never reserve vertical room.
    [...card.children].forEach(el=>{if(el.matches('.auth-fit-orbit,.auth-fit-cloud,.auth-spacer,[data-auth-spacer]'))imp(el,'display','none')});
    requestAnimationFrame(()=>{
      const last=quote||security||fields||panel;
      if(!last)return;
      const cr=card.getBoundingClientRect(), lr=last.getBoundingClientRect();
      let desired=Math.ceil(lr.bottom-cr.top+18);
      // Guardrails only; normal login should resolve around 500–590px depending on quote wrapping.
      desired=Math.max(460,Math.min(desired,640));
      imp(card,'height',desired+'px');imp(card,'min-height',desired+'px');imp(card,'max-height',desired+'px');
      card.dataset.compactAuthHeight=String(desired);
    });
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',repair,{once:true}); else repair();
  window.addEventListener('load',repair,{once:true});
  setTimeout(repair,80);setTimeout(repair,450);setTimeout(repair,1200);
})();
