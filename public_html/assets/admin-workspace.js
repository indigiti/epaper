/* Scoped, role-aware admin launcher; never fetches remote data. */
(()=>{
  'use strict';
  const root=document.querySelector('[data-admin-launchpad]');
  if(!root)return;
  const input=root.querySelector('[data-admin-feature-search]');
  const directory=root.querySelector('[data-feature-directory]');
  const all=[...root.querySelectorAll('[data-feature-item]')];
  const groups=[...root.querySelectorAll('[data-feature-group]')];
  const empty=root.querySelector('[data-feature-empty]');
  const area=root.querySelector('[data-admin-pinned-area]');
  const links=root.querySelector('[data-admin-pinned-links]');
  const available=new Map();
  for(const row of all){
    const anchor=row.querySelector('a[href]');
    if(anchor){
      const path=anchor.getAttribute('href');
      if(path&&path.startsWith('/admin/')&&!path.includes('//'))available.set(path,{label:row.querySelector('strong')?.textContent?.trim()||path,row});
    }
  }
  const key='epdi:admin:pins:v1:'+root.dataset.storageScope;
  const read=()=>{
    try{
      const stored=JSON.parse(window.localStorage.getItem(key)||'[]');
      return Array.isArray(stored)?[...new Set(stored.filter(s=>typeof s==='string'&&available.has(s)))].slice(0,8):[];
    }catch{return []}
  };
  let pins=read();
  const save=()=>{try{window.localStorage.setItem(key,JSON.stringify(pins))}catch{}};
  const render=()=>{
    if(!area||!links)return;
    links.replaceChildren();
    for(const path of pins){
      const item=available.get(path);if(!item)continue;
      const a=document.createElement('a');
      a.href=path;a.textContent=item.label;
      links.append(a);
    }
    area.hidden=links.childElementCount===0;
    root.querySelectorAll('[data-feature-pin]').forEach(button=>{
      const active=pins.includes(button.dataset.featurePin);
      button.setAttribute('aria-pressed',String(active));
      button.textContent=active?'★':'☆';
      const feature=available.get(button.dataset.featurePin);
      button.setAttribute('aria-label',(active?'Unpin ':'Pin ')+(feature?.label||'tool'));
      button.title=active?'Remove from pinned tools':'Pin to quick access';
    });
  };
  root.addEventListener('click',event=>{
    const button=event.target.closest('[data-feature-pin]');
    if(!button||!root.contains(button))return;
    const path=button.dataset.featurePin;
    if(!available.has(path))return;
    pins=pins.includes(path)?pins.filter(p=>p!==path):[...pins,path].slice(-8);
    save();render();
  });
  const filter=()=>{
    const q=(input?.value||'').trim().toLocaleLowerCase();
    if(q)directory.open=true;
    let total=0;
    for(const group of groups){
      let visible=0;
      for(const row of group.querySelectorAll('[data-feature-item]')){
        const match=!q||(row.dataset.searchText||'').toLocaleLowerCase().includes(q);
        row.hidden=!match;if(match){total++;visible++}
      }
      group.hidden=visible===0;
    }
    if(empty)empty.hidden=total!==0;
  };
  input?.addEventListener('input',filter);
  render();
})();
