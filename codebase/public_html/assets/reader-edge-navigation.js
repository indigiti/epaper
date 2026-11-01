/* Click-only reader edges: existing pager owns every navigation mode. */
(()=>{'use strict';
const root=document.querySelector('#reader');
if(!root)return;
const stage=root.querySelector('[data-page-stage]');
const pagerPrev=root.querySelector('.reader-pager [data-reader-prev]');
const pagerNext=root.querySelector('.reader-pager [data-reader-next]');
const label=root.querySelector('[data-page-label]');
if(!stage||!pagerPrev||!pagerNext)return;
const dots=[[12,7],[22,7],[22,17],[31,17],[31,27],[40,27],[40,37],[46,37],[40,47],[31,47],[31,57],[22,57],[22,67],[12,67]];
function glyph(dir,style){
  if(style==='chevron')return '<svg class="reader-edge-glyph reader-edge-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'+(dir==='prev'?'M16 4 8 12l8 8':'M8 4l8 8-8 8')+'"/></svg>';
  const circles=dots.map(([x,y])=>'<circle cx="'+(dir==='prev'?58-x:x)+'" cy="'+(y+5)+'" r="4"/>').join('');
  return '<svg class="reader-edge-glyph reader-edge-dots" viewBox="0 0 58 82" fill="currentColor" aria-hidden="true">'+circles+'</svg>';
}
function create(dir){
  const b=document.createElement('button'),title=dir==='prev'?'Previous page':'Next page';
  b.type='button';b.className='reader-edge-button reader-edge-'+dir;
  b.setAttribute('data-reader-edge-'+dir,'');b.setAttribute('aria-label',title);b.title=title;
  b.innerHTML=glyph(dir,'dots')+glyph(dir,'chevron');return b;
}
const nav=document.createElement('nav');
nav.className='reader-edge-nav';nav.setAttribute('aria-label','Page edge navigation');
const prev=create('prev'),next=create('next');nav.append(prev,next);stage.append(nav);
function current(){
  const mode=root.dataset.viewMode||root.dataset.defaultView||'page';
  const raw=mode==='continuous'?label?.textContent:root.dataset.pageNumber;
  const n=parseInt(String(raw||''),10);
  return Number.isInteger(n)&&n>0?n:(parseInt(root.dataset.pageNumber||'1',10)||1);
}
function blocked(){
  return root.classList.contains('is-transitioning')||root.classList.contains('clip-active')||
    root.classList.contains('pinching')||stage.classList.contains('article-pick');
}
function unavailable(a){return a.classList.contains('disabled')||a.getAttribute('aria-disabled')==='true'}
function sync(){
  const continuous=(root.dataset.viewMode||root.dataset.defaultView)==='continuous';
  const total=Number(root.dataset.pageCount)||1,page=current(),busy=blocked();
  prev.disabled=busy||(continuous?page<=1:unavailable(pagerPrev));
  next.disabled=busy||(continuous?page>=total:unavailable(pagerNext));
}
function wire(button,link){
  // Never start newspaper pan, pinch, swipe or selection on the edge control.
  for(const event of ['pointerdown','pointermove','pointerup','pointercancel'])
    button.addEventListener(event,e=>e.stopPropagation());
  for(const event of ['touchstart','touchmove','touchend','touchcancel'])
    button.addEventListener(event,e=>e.stopPropagation(),{passive:true});
  button.addEventListener('click',e=>{
    e.preventDefault();e.stopPropagation();sync();
    if(button.disabled||blocked())return;
    // Canonical pager already knows Page/Flip/Continuous, history and spreads.
    link.click();sync();
  });
}
wire(prev,pagerPrev);wire(next,pagerNext);
const observer=new MutationObserver(sync);
for(const link of [pagerPrev,pagerNext])
  observer.observe(link,{attributes:true,attributeFilter:['class','aria-disabled']});
observer.observe(root,{attributes:true,attributeFilter:['class','data-page-number','data-page-count','data-view-mode']});
if(label)observer.observe(label,{childList:true,characterData:true,subtree:true});
sync();
})();
