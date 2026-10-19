/* ePaper Studio navigation: only operates on server-rendered role-authorized links. */
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
  const groups=[...nav.querySelectorAll('[data-admin-nav-section]')];
  const scope=[body.dataset.tenantGuid||'tenant',body.dataset.userGuid||'user',nav.dataset.adminNavRole||'role'].join(':');
  const widthKey='epdi:admin:sidebar-width:v1:'+scope;
  const groupsKey='epdi:admin:sidebar-groups:v1:'+scope;
  const desktop=()=>window.matchMedia('(min-width:1001px)').matches;
  const read=(key)=>{
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
    collapse.title=collapsed?'Expand sidebar':'Collapse sidebar';
    if(remember)save(widthKey,collapsed);
  };
  setCollapsed(read(widthKey)===true,false);
  const savedGroups=read(groupsKey);
  if(Array.isArray(savedGroups)){
    const allowed=new Set(groups.map(g=>g.dataset.adminNavSection));
    const wanted=new Set(savedGroups.filter(x=>typeof x==='string'&&allowed.has(x)));
    groups.forEach(group=>{
      group.open=wanted.has(group.dataset.adminNavSection) || Boolean(group.querySelector('.app-nav-link.active'));
    });
  }
  const storeGroups=()=>{
    if(search.value.trim()||body.classList.contains('admin-nav-collapsed'))return;
    save(groupsKey,groups.filter(g=>g.open).map(g=>g.dataset.adminNavSection));
  };
  groups.forEach(group=>{
    group.addEventListener('toggle',storeGroups);
    const summary=group.querySelector('summary');
    summary?.addEventListener('click',event=>{
      if(!desktop()||!body.classList.contains('admin-nav-collapsed'))return;
      event.preventDefault();
      setCollapsed(false);
      group.open=true;
      summary.focus();
      storeGroups();
    });
  });
  collapse.addEventListener('click',()=>setCollapsed(!body.classList.contains('admin-nav-collapsed')));
  searchExpand?.addEventListener('click',()=>{
    setCollapsed(false);
    search.focus();
  });
  let previous='';
  let beforeSearch=null;
  const filter=()=>{
    const q=search.value.trim().toLocaleLowerCase();
    if(q&&!previous)beforeSearch=new Map(groups.map(g=>[g,g.open]));
    if(!q&&previous&&beforeSearch){
      groups.forEach(group=>{group.open=beforeSearch.get(group)===true;});
      beforeSearch=null;
    }
    let found=0;
    for(const group of groups){
      const links=[...group.querySelectorAll('.app-nav-link')];
      let count=0;
      for(const link of links){
        const matches=!q||link.textContent.toLocaleLowerCase().includes(q);
        link.hidden=!matches;
        if(matches)count++;
      }
      group.hidden=q.length>0&&count===0;
      if(q&&count>0)group.open=true;
      found+=count;
    }
    if(empty)empty.hidden=!q||found>0;
    previous=q;
  };
  search.addEventListener('input',filter);
  search.addEventListener('keydown',e=>{
    if(e.key==='Escape'){
      if(search.value){search.value='';filter();}else search.blur();
    }
  });
  document.addEventListener('keydown',e=>{
    if(e.key!=='/'||e.altKey||e.ctrlKey||e.metaKey||e.defaultPrevented)return;
    const target=e.target;
    if(target instanceof Element && target.closest('input,textarea,select,[contenteditable="true"]'))return;
    if(!desktop()&& !body.classList.contains('app-nav-open'))return;
    e.preventDefault();
    if(desktop())setCollapsed(false);
    search.focus();
  });
  /* Native mobile drawer handling stays in app.js; desktop rail never changes it. */
})();
