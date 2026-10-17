// ePaper SaaS v1.1.8 — predictive Reader state/image prefetch.
(()=>{
  'use strict';
  const root=document.querySelector('#reader[data-state-endpoint]');
  if(!root||root.dataset.prefetchEnabled==='off')return;
  const endpoint=root.dataset.stateEndpoint;
  const maxPage=()=>Math.max(1,parseInt(root.dataset.pageCount||'1',10)||1);
  const currentPage=()=>Math.max(1,parseInt(root.dataset.pageNumber||'1',10)||1);
  const stateCache=new Map(),imageCache=new Map(),inflight=new Map();
  const stats={stateHits:0,stateMisses:0,decoded:0,errors:0,lastDirection:'none'};
  let directionLock=0,startX=null,startY=null;
  const connection=navigator.connection||navigator.mozConnection||navigator.webkitConnection;
  function mode(){const q=localStorage.getItem('reader.qualityMode')||'auto';if(q==='low'||q==='high')return q;const slow=!!(connection?.saveData||['slow-2g','2g','3g'].includes(connection?.effectiveType)||(Number.isFinite(connection?.downlink)&&connection.downlink>0&&connection.downlink<1));return slow?'low':'auto'}
  function url(n){return endpoint.replace('{page}',String(n))}
  function valid(n){return n>=1&&n<=maxPage()}
  async function state(n){
    if(!valid(n))return null;
    if(stateCache.has(n)){stats.stateHits++;return stateCache.get(n)}
    if(inflight.has(n))return inflight.get(n);
    stats.stateMisses++;
    const p=fetch(url(n),{credentials:'same-origin',headers:{'X-Reader-Prefetch':'1'}}).then(async r=>{if(!r.ok)throw new Error('reader-state-'+r.status);const d=await r.json();if(d?.ok)stateCache.set(n,d);return d}).catch(()=>{stats.errors++;return null}).finally(()=>inflight.delete(n));
    inflight.set(n,p);return p;
  }
  async function decode(src,key){
    if(!src)return false;
    if(imageCache.has(key))return true;
    const img=new Image();img.decoding='async';img.fetchPriority='low';
    const done=new Promise(resolve=>{img.onload=async()=>{try{await img.decode?.()}catch{}imageCache.set(key,img);stats.decoded++;resolve(true)};img.onerror=()=>{stats.errors++;resolve(false)}});
    img.src=src;return done;
  }
  async function warm(n,{decodePreview=true,highres=false}={}){
    const d=await state(n);if(!d?.ok)return null;
    if(decodePreview)await decode(d.preview||d.thumbnail||d.image,`p:${n}:${d.preview_asset_guid||d.preview||''}`);
    if(highres&&d.highres)decode(d.highres,`h:${n}:${d.highres_asset_guid||d.highres||''}`);
    return d;
  }
  function trim(){
    const c=currentPage();for(const k of [...stateCache.keys()])if(Math.abs(k-c)>3)stateCache.delete(k);
    for(const k of [...imageCache.keys()]){const m=k.match(/^[ph]:(\d+):/);if(m&&Math.abs(parseInt(m[1],10)-c)>2)imageCache.delete(k)}
  }
  function schedule(){
    const c=currentPage(),q=mode();
    // Adjacent PREVIEW pages are decoded before the user asks for them.
    warm(c+1,{decodePreview:true,highres:q==='high'});warm(c-1,{decodePreview:true,highres:false});
    // Keep the second forward/backward state hot without paying image bandwidth in Low Data.
    const idle=window.requestIdleCallback||((fn)=>setTimeout(fn,180));
    idle(()=>{state(c+2);state(c-2);if(q!=='low')warm(c+2,{decodePreview:true,highres:false});trim()},{timeout:800});
  }
  function predict(dx,dy){
    if(Math.abs(dx)<24||Math.abs(dx)<Math.abs(dy)*1.15)return;
    const now=performance.now();if(now-directionLock<160)return;directionLock=now;
    const target=dx<0?currentPage()+1:currentPage()-1;stats.lastDirection=dx<0?'next':'previous';warm(target,{decodePreview:true,highres:mode()==='high'});
  }
  const stage=document.querySelector('[data-page-stage]')||root;
  stage.addEventListener('pointerdown',e=>{if(e.pointerType==='mouse'&&e.button!==0)return;startX=e.clientX;startY=e.clientY},{passive:true});
  stage.addEventListener('pointermove',e=>{if(startX==null)return;predict(e.clientX-startX,e.clientY-startY)},{passive:true});
  const clear=()=>{startX=startY=null};stage.addEventListener('pointerup',clear,{passive:true});stage.addEventListener('pointercancel',clear,{passive:true});
  // Existing Reader navigation updates data-page-number; observe it and prepare the next window immediately.
  new MutationObserver(m=>{if(m.some(x=>x.attributeName==='data-page-number'))schedule()}).observe(root,{attributes:true,attributeFilter:['data-page-number']});
  connection?.addEventListener?.('change',schedule);
  window.addEventListener('pageshow',schedule,{once:true});
  window.EpaperReaderPrefetch={state,warm,stats,hasPreview:n=>[...imageCache.keys()].some(k=>k.startsWith(`p:${n}:`))};
  schedule();
})();
