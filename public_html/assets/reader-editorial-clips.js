/* Clip Marking: approved newspaper outlines are cropped only when readers choose to save.
 * No private editor drafts are fetched or rendered by this client.
 */
(()=>{
 'use strict';
 const root=document.getElementById('reader');
 if(!root)return;
 const doc=root.querySelector('[data-document]');
 if(!doc)return;
 const cache=new Map();
 // Approved Clip Marking outlines appear automatically, without an Articles toolbar control.
 // Retain the programmatic setter for other Reader components, not for toolbar UI.
 let enabled=true;
 function setEnabled(value){
   enabled=!!value;
   root.querySelectorAll('.reader-editorial-layer').forEach(x=>{x.hidden=!enabled});
 }
 const validGuid=s=>typeof s==='string'&&/^[0-9a-f-]{36}$/i.test(s);
 const validClip=c=>c&&typeof c.title==='string'&&validGuid(c.mark_guid)&&
   ['x','y','w','h'].every(k=>Number.isFinite(Number(c[k])))&&
   Number(c.x)>=0&&Number(c.y)>=0&&Number(c.w)>=.02&&Number(c.h)>=.02&&
   Number(c.x)+Number(c.w)<=1.00001&&Number(c.y)+Number(c.h)<=1.00001;
 async function clips(guid){
   if(!validGuid(guid))return [];
   if(!cache.has(guid)){
     const p=fetch('/api/v1/pages/'+encodeURIComponent(guid)+'/clip-marks',{
       credentials:'same-origin',headers:{Accept:'application/json'}
     }).then(async r=>{if(!r.ok)return [];const data=await r.json();
       return data?.ok&&data.page_guid?.toLowerCase()===guid.toLowerCase()&&Array.isArray(data.marks)
         ?data.marks.filter(validClip).slice(0,80):[];
     }).catch(()=>[]);
     cache.set(guid,p);
   }
   return cache.get(guid);
 }
 async function attach(surface,guid,pageNumber=0){
   if(!surface||!validGuid(guid))return;
   if(surface.dataset.editorialBoundGuid===guid)return;
   surface.dataset.editorialBoundGuid=guid;
   surface.querySelectorAll(':scope > .reader-editorial-layer').forEach(el=>el.remove());
   const list=await clips(guid);
   if(surface.dataset.editorialBoundGuid!==guid||!surface.isConnected||list.length===0)return;
   const layer=document.createElement('div');
   layer.className='reader-editorial-layer';
   layer.hidden=!enabled;
   layer.setAttribute('aria-label','Published newspaper article regions');
   for(const c of list){
      const button=document.createElement('button');button.type='button';
      button.className='reader-editorial-marker';
      const title=String(c.title||'Open newspaper clipping');
      button.dataset.clipLabel=title.slice(0,100);
      const official=typeof c.official_url==='string'&&/^\/c\/[a-f0-9]{18}$/i.test(c.official_url)
        ?c.official_url:null;
      if(official){
        button.dataset.officialClip='1';
        button.dataset.clipLabel=('Official clipping · '+title).slice(0,100);
        button.title='Open publisher-approved clipping';
      }
      button.setAttribute('aria-label',(official?'Open official clipping: ':'Create clipping: ')+title);
      Object.assign(button.style,{
        left:(100*Number(c.x))+'%',top:(100*Number(c.y))+'%',
        width:(100*Number(c.w))+'%',height:(100*Number(c.h))+'%'
      });
      button.addEventListener('pointerdown',e=>e.stopPropagation());
      button.addEventListener('pointerup',e=>e.stopPropagation());
      button.addEventListener('click',e=>{
        e.preventDefault();e.stopPropagation();
        if(official){window.location.assign(official);return;}
        window.EpdiReader?.createFromEditorialMarker?.(c,guid,pageNumber||Number(root.dataset.pageNumber));
      });
      layer.appendChild(button);
   }
   surface.appendChild(layer);
 }
 function syncDocument(){
   const guid=root.dataset.pageGuid;
   if(validGuid(guid))attach(doc,guid,Number(root.dataset.pageNumber));
 }
 function scanOtherViews(){
   const state=window.EpdiReader;
   root.querySelectorAll('.reader-flip-page').forEach(page=>{
     const label=page.querySelector('.reader-flip-page-number');
     const n=Number(label?.textContent||0);
     if(!n||!page.querySelector('img')){page.removeAttribute('data-editorial-page-guid');page.querySelectorAll(':scope > .reader-editorial-layer').forEach(x=>x.remove());return;}
     if(page.dataset.editorialNumber===String(n))return;
     page.dataset.editorialNumber=String(n);
     state?.state?.(n)?.then(d=>{if(page.dataset.editorialNumber===String(n)&&d?.page_guid)attach(page,d.page_guid,n)}).catch(()=>{});
   });
   root.querySelectorAll('.reader-continuous-page').forEach(item=>{
     const paper=item.querySelector('.reader-continuous-paper');
     if(!paper||!paper.querySelector('img'))return;
     const n=Number(item.dataset.continuousPage||0);
     if(!n||paper.dataset.editorialNumber===String(n))return;
     paper.dataset.editorialNumber=String(n);
     state?.state?.(n)?.then(d=>{if(paper.dataset.editorialNumber===String(n)&&d?.page_guid)attach(paper,d.page_guid,n)}).catch(()=>{});
   });
 }
 const watcher=new MutationObserver(()=>{
   syncDocument();scanOtherViews();
 });
 watcher.observe(root,{attributes:true,attributeFilter:['data-page-guid']});
 const book=root.querySelector('.reader-flip-book');
 if(book)book.querySelectorAll('.reader-flip-page').forEach(p=>watcher.observe(p,{childList:true}));
 const continuous=root.querySelector('.reader-continuous-layer');
 if(continuous)watcher.observe(continuous,{childList:true,subtree:true});
 // Own layers trigger child mutations; the bound GUID guard avoids extra fetches.
 window.EpdiEditorialClips={attach,showPage:syncDocument,scan:scanOtherViews,setEnabled};
 syncDocument();scanOtherViews();
})();
