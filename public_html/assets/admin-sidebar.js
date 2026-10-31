/* Publisher Studio v4: native accessible details with one deterministic state controller.
 * Does not touch public, auth, reader, or the existing mobile drawer controller.
 */
(()=>{
  'use strict';
  const body=document.body;
  if(!body.classList.contains('admin-shell'))return;
  const root=document.querySelector('[data-app-sidebar]');
  const nav=root?.querySelector('[data-admin-sidebar-nav]');
  const collapse=root?.querySelector('[data-admin-sidebar-collapse]');
  const search=root?.querySelector('[data-admin-nav-search]');
  const searchExpand=root?.querySelector('[data-admin-nav-search-expand]');
  const empty=root?.querySelector('[data-admin-sidebar-empty]');
  if(!root||!nav||!collapse||!search)return;

  const groups=Array.from(nav.querySelectorAll('details[data-admin-nav-section]'));
  const scope=[body.dataset.tenantGuid||'tenant',body.dataset.userGuid||'user',nav.dataset.adminNavRole||'role'].join(':');
  const widthKey='epdi:admin:sidebar-width:v1:'+scope;
  const groupsKey='epdi:admin:sidebar-groups:v2:'+scope;
  const desktop=()=>window.matchMedia('(min-width:1001px)').matches;
  const read=key=>{
    try{return JSON.parse(window.localStorage.getItem(key)||'null');}catch{return null;}
  };
  const save=(key,value)=>{
    try{window.localStorage.setItem(key,JSON.stringify(value));}catch{}
  };

  const setCollapsed=(value,remember=true)=>{
    const collapsed=Boolean(value);
    body.classList.toggle('admin-nav-collapsed',collapsed);
    collapse.setAttribute('aria-expanded',String(!collapsed));
    collapse.setAttribute('aria-label',collapsed?'Expand navigation':'Collapse navigation');
    collapse.setAttribute('title',collapsed?'Expand sidebar':'Collapse sidebar');
    if(remember)save(widthKey,collapsed);
  };
  setCollapsed(read(widthKey)===true,false);

  // Initialize all details BEFORE registering change handlers. Otherwise the
  // asynchronous native toggle events can overwrite the saved accordion state.
  const savedGroups=read(groupsKey);
  if(Array.isArray(savedGroups)){
    const allowed=new Set(groups.map(g=>g.dataset.adminNavSection));
    const wanted=new Set(savedGroups.filter(x=>typeof x==='string'&&allowed.has(x)));
    for(const group of groups){
      group.open=wanted.has(group.dataset.adminNavSection)
        ||Boolean(group.querySelector('a[aria-current="page"]'));
    }
  }
  let hydrating=true;
  const currentActive=groups.find(g=>g.querySelector('a[aria-current="page"]'));
  if(currentActive)currentActive.open=true;

  const storeGroups=()=>{
    if(hydrating||search.value.trim()||body.classList.contains('admin-nav-collapsed'))return;
    save(groupsKey,groups.filter(g=>g.open).map(g=>g.dataset.adminNavSection));
  };
  for(const group of groups){
    group.addEventListener('toggle',storeGroups);
    const summary=group.querySelector(':scope > summary');
    summary?.addEventListener('click',event=>{
      // Take ownership of native disclosure state so click, Enter and Space
      // persist synchronously, including when navigation follows immediately.
      event.preventDefault();
      if(desktop()&&body.classList.contains('admin-nav-collapsed')){
        setCollapsed(false);
        group.open=true;
        summary.focus();
      }else{
        group.open=!group.open;
      }
      if(!search.value.trim()&&!body.classList.contains('admin-nav-collapsed')){
        save(groupsKey,groups.filter(section=>section.open).map(section=>section.dataset.adminNavSection));
      }
    });
  }
  // requestAnimationFrame lets queued initialization toggles settle. This is
  // a single setup barrier, not a polling loop or an ongoing animation.
  window.requestAnimationFrame(()=>{hydrating=false;});

  collapse.addEventListener('click',()=>{
    const next=!body.classList.contains('admin-nav-collapsed');
    setCollapsed(next);
    if(!next){
      const selected=groups.find(g=>g.querySelector('a[aria-current="page"]'));
      if(selected)selected.open=true;
    }
  });
  searchExpand?.addEventListener('click',()=>{
    setCollapsed(false);
    search.focus();
  });

  let previous='';
  let beforeSearch=null;
  const filter=()=>{
    const q=search.value.trim().toLocaleLowerCase();
    if(q&&!previous)beforeSearch=new Map(groups.map(g=>[g,g.open]));
    let found=0;
    for(const group of groups){
      let count=0;
      for(const link of group.querySelectorAll('a.app-nav-link')){
        const words=(link.dataset.adminSearchTerms||link.textContent||'').toLocaleLowerCase();
        const matches=!q||words.includes(q);
        link.hidden=!matches;
        if(matches)count++;
      }
      group.hidden=Boolean(q)&&count===0;
      if(q&&count>0)group.open=true;
      found+=count;
    }
    if(!q&&previous&&beforeSearch){
      for(const group of groups){
        group.hidden=false;
        group.open=beforeSearch.get(group)===true||Boolean(group.querySelector('a[aria-current="page"]'));
      }
      beforeSearch=null;
    }
    if(empty)empty.hidden=!q||found>0;
    previous=q;
  };
  search.addEventListener('input',filter);
  search.addEventListener('keydown',e=>{
    if(e.key==='Escape'){
      if(search.value){search.value='';filter();}
      else search.blur();
    }
  });
  document.addEventListener('keydown',e=>{
    if(e.key!=='/'||e.altKey||e.ctrlKey||e.metaKey||e.defaultPrevented)return;
    const target=e.target;
    if(target instanceof Element&&target.closest('input,textarea,select,[contenteditable="true"]'))return;
    if(!desktop()&&!body.classList.contains('app-nav-open'))return;
    e.preventDefault();
    if(desktop())setCollapsed(false);
    search.focus();
  });
  // app.js remains the sole owner of the mobile off-canvas drawer.
})();
