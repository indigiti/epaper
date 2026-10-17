// ePaper SaaS v1.1.6.2 — resilient bulk actions + shared record editor drawer
(()=>{
  const $=(s,r=document)=>r.querySelector(s),$$=(s,r=document)=>[...r.querySelectorAll(s)];

  // Delegated form UX also covers forms cloned into the editor drawer.
  document.addEventListener('submit',e=>{
    const form=e.target.closest('form');if(!form)return;
    const msg=form.dataset.confirm;
    if(msg&&!window.confirm(msg)){e.preventDefault();e.stopImmediatePropagation();return;}
    form.classList.add('form-submitting');
    form.setAttribute('aria-busy','true');
    const submitter=e.submitter||form.querySelector('button[type=submit],input[type=submit]');
    if(submitter){submitter.disabled=true;submitter.setAttribute('aria-disabled','true');}
  },true);

  document.addEventListener('click',e=>{if(!e.target.closest('.record-menu'))$$('.record-menu[open]').forEach(d=>d.removeAttribute('open'))});

  // Shared record-editor drawer. Form-heavy ••• menus become simple actions.
  const drawer=document.createElement('div');drawer.className='record-editor-layer hidden';drawer.innerHTML='<button class="record-editor-backdrop" type="button" data-close-record-editor aria-label="Close editor"></button><aside class="record-editor" role="dialog" aria-modal="true" aria-labelledby="record-editor-title"><header><div><span class="eyebrow">EDIT RECORD</span><h2 id="record-editor-title">Edit record</h2></div><button class="icon-btn" type="button" data-close-record-editor aria-label="Close editor">×</button></header><div class="record-editor-body" data-record-editor-body></div></aside>';document.body.append(drawer);
  const drawerBody=$('[data-record-editor-body]',drawer),drawerTitle=$('#record-editor-title',drawer);let returnFocus=null;
  function closeDrawer(){if(drawer.classList.contains('hidden'))return;drawer.classList.add('hidden');document.body.classList.remove('record-editor-open');drawerBody.replaceChildren();returnFocus?.focus?.();returnFocus=null;}
  function openForm(form,trigger,title){if(!form)return;returnFocus=trigger||document.activeElement;const clone=form.cloneNode(true);clone.classList.remove('editor-source');clone.style.display='';drawerBody.replaceChildren(clone);drawerTitle.textContent=title||form.dataset.editorTitle||'Edit record';drawer.classList.remove('hidden');document.body.classList.add('record-editor-open');$$('.record-menu[open]').forEach(d=>d.removeAttribute('open'));setTimeout(()=>clone.querySelector('input:not([type=hidden]),select,textarea,button')?.focus(),20);}
  drawer.addEventListener('click',e=>{if(e.target.closest('[data-close-record-editor]'))closeDrawer()});
  document.addEventListener('keydown',e=>{if(e.key==='Escape'){if(!drawer.classList.contains('hidden')){e.preventDefault();closeDrawer();return;}$$('.record-menu[open]').forEach(d=>d.removeAttribute('open'))}});

  $$('.record-menu').forEach(menu=>{
    const form=$('.record-form',menu);if(!form)return;form.classList.add('editor-source');
    if(menu.querySelector('[data-open-record-editor]'))return;
    const record=menu.closest('.record-row,tr,.library-card-wrap');const label=(record?.querySelector('.master-record-main strong,h3,td strong,strong')?.textContent||'record').trim();
    const btn=document.createElement('button');btn.type='button';btn.className='record-action record-edit-action';btn.dataset.openRecordEditor='1';btn.textContent='Edit';btn.addEventListener('click',()=>openForm(form,btn,form.dataset.editorTitle||`Edit ${label}`));
    form.before(btn);
  });

  // Generic drawer launcher used by operational actions (reprocess, schedule, create).
  document.addEventListener('click',e=>{
    const btn=e.target.closest('[data-open-editor-target]');if(!btn)return;
    const id=btn.dataset.openEditorTarget||'';const form=id?document.getElementById(id):null;if(!form)return;
    e.preventDefault();openForm(form,btn,form.dataset.editorTitle||btn.dataset.editorTitle||'Action');
  });

  $$('[data-open-master-create]').forEach(btn=>btn.addEventListener('click',()=>{
    const panel=btn.closest('[data-master-panel]'),form=$('.master-add-form',panel);openForm(form,btn,form?.dataset.editorTitle||'Add record');
  }));

  // Master-data tabs and client-side filtering.
  function activateMaster(type){$$('[data-master-tab]').forEach(b=>{const on=b.dataset.masterTab===type;b.classList.toggle('active',on);b.setAttribute('aria-selected',on?'true':'false')});$$('[data-master-panel]').forEach(p=>p.classList.toggle('active',p.dataset.masterPanel===type));try{localStorage.setItem('epaper.masterDataTab',type)}catch{} }
  $$('[data-master-tab]').forEach(btn=>btn.addEventListener('click',()=>activateMaster(btn.dataset.masterTab)));
  if($('[data-master-tab]')){const urlTab=new URL(location.href).searchParams.get('tab');let stored='';try{stored=localStorage.getItem('epaper.masterDataTab')||''}catch{}const wanted=urlTab||stored;if(wanted&&$(`[data-master-tab="${CSS.escape(wanted)}"]`))activateMaster(wanted)}
  $$('[data-master-search]').forEach(input=>input.addEventListener('input',()=>{const type=input.dataset.masterSearch,q=input.value.trim().toLocaleLowerCase();$$(`[data-master-list="${CSS.escape(type)}"] [data-master-search-text]`).forEach(row=>row.hidden=q!==''&&!row.dataset.masterSearchText.includes(q))}));

  // Brand controls retained from v1.1.5.x.
  const color=$('[data-brand-color]'),text=$('[data-brand-color-text]');if(color&&text){color.addEventListener('input',()=>text.value=color.value);text.addEventListener('input',()=>{if(/^#[0-9a-f]{6}$/i.test(text.value))color.value=text.value})}
  $$('[data-brand-file]').forEach(input=>input.addEventListener('change',()=>{const file=input.files?.[0],card=input.closest('.brand-asset-card'),preview=card?.querySelector('.brand-asset-preview');if(!file||!preview)return;if(!/^image\/(png|jpeg|webp)$/i.test(file.type)){input.value='';window.epaperToast?.('Use a PNG, JPEG or WebP image','error');return}if(file.size>8*1024*1024){input.value='';window.epaperToast?.('Brand asset must be under 8 MB','error');return}const url=URL.createObjectURL(file);preview.classList.remove('is-empty');preview.classList.add('has-image');preview.replaceChildren(Object.assign(document.createElement('img'),{src:url,alt:'Selected asset preview'}));const label=card.querySelector('.brand-upload-button');if(label?.childNodes?.[0])label.childNodes[0].textContent='Replace file'}));
  $$('[data-brand-remove]').forEach(btn=>btn.addEventListener('click',e=>{if(!window.confirm('Remove this brand asset?'))e.preventDefault()}));
})();


// EP-DI v2.0.0-rc.3 — reusable enterprise CRUD/action icon system + final interaction hardening.
(()=>{
  const $$=(s,r=document)=>[...r.querySelectorAll(s)];
  const icon={
    view:'<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/>',
    edit:'<path d="M4 20h4l11-11-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/>',
    delete:'<path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="m7 7 1 13h8l1-13"/><path d="M10 11v5M14 11v5"/>',
    restore:'<path d="M4 4v6h6"/><path d="M5.5 9A7.5 7.5 0 1 1 7 17.5"/>',
    archive:'<path d="M4 7h16v13H4z"/><path d="M3 4h18v3H3zM9 11h6"/>',
    power:'<path d="M12 2v10"/><path d="M6.2 5.8a8 8 0 1 0 11.6 0"/>',
    publish:'<path d="m22 2-7 20-4-9-9-4 20-7Z"/><path d="M22 2 11 13"/>',
    more:'<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>'
  };
  const meta=label=>{const t=label.trim().toLowerCase();
    if(t==='edit'||t.startsWith('edit '))return['edit','edit'];
    if(t.startsWith('view'))return['view','view'];
    if(t==='publish')return['publish','publish'];
    if(t==='unpublish')return['archive','archive'];
    if(t.startsWith('delete'))return['delete','delete'];
    if(t.startsWith('restore'))return['restore','restore'];
    if(t.startsWith('archive'))return['archive','archive'];
    if(t==='enable'||t==='activate')return['restore','power'];
    if(t==='disable'||t==='deactivate')return['neutral','power'];
    return null;
  };
  const decorate=(action,kind,iconName,label)=>{
    action.classList.add('console-icon-action',`is-${kind}`);action.classList.remove('record-action','danger');
    action.setAttribute('title',label);action.setAttribute('aria-label',label);
    action.innerHTML=`<svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">${icon[iconName]||icon.view}</svg><span class="sr-only">${label.replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]))}</span>`;
  };
  $$('.record-menu').forEach(menu=>{
    const summary=menu.querySelector(':scope > summary');
    if(summary){summary.setAttribute('title','More actions');summary.setAttribute('aria-label','More record actions');summary.innerHTML=`<svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">${icon.more}</svg><span class="sr-only">More actions</span>`;}
    const pop=menu.querySelector('.record-popover');if(!pop)return;
    const cluster=document.createElement('div');cluster.className='record-action-cluster console-action-cluster';cluster.setAttribute('role','group');cluster.setAttribute('aria-label','Record actions');menu.before(cluster);
    [...pop.children].forEach(child=>{
      if(child.classList.contains('record-form'))return;
      const action=child.matches?.('.record-action')?child:(child.matches?.('form')?child.querySelector(':scope > .record-action'):null);if(!action)return;
      const label=(action.textContent||'').trim();const m=meta(label);if(!m)return;
      decorate(action,m[0],m[1],label);const holder=child.matches?.('form')?child:action;holder.classList?.add?.('console-icon-form');cluster.append(holder);
    });
    const remaining=[...pop.children].filter(el=>!el.classList.contains('record-form'));
    if(!cluster.children.length)cluster.remove();
    if(!remaining.length)menu.hidden=true;
  });
})();
