// EP-DI v2.0.0-beta.1 — Publisher Console micro-interactions
(()=>{
  const $$=(s,r=document)=>[...r.querySelectorAll(s)];
  // Reader Navigation responsive preview. This is presentation-only; compiled data is unchanged.
  $$('[data-nav-preview-device]').forEach(group=>{
    const frame=group.closest('[data-nav-preview]')?.querySelector('[data-nav-preview-frame]');
    if(!frame)return;
    group.addEventListener('click',e=>{
      const btn=e.target.closest('button[data-device]');if(!btn)return;
      const device=btn.dataset.device||'desktop';
      frame.dataset.device=device;
      $$('button[data-device]',group).forEach(b=>{const on=b===btn;b.classList.toggle('active',on);b.setAttribute('aria-pressed',on?'true':'false')});
    });
  });
  // Show unsaved-draft signal after a navigation form field changes.
  const nav=document.querySelector('.navigation-editor');
  if(nav){
    let dirty=false;
    nav.addEventListener('input',e=>{
      if(dirty||!e.target.closest('form'))return;dirty=true;
      const flag=document.querySelector('[data-nav-dirty]');if(flag){flag.hidden=false;flag.textContent='Draft has unsaved changes';}
    });
  }
  // Density-aware table label for small screens.
  const updateNarrow=()=>document.body.classList.toggle('console-narrow',innerWidth<760);
  updateNarrow();addEventListener('resize',updateNarrow,{passive:true});
})();

// Keyboard containment for modal publisher surfaces.
(()=>{
  const selector='a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';
  document.addEventListener('keydown',e=>{
    if(e.key!=='Tab')return;
    const palette=document.querySelector('[data-command-palette]:not(.hidden)');
    const drawer=document.querySelector('.record-editor-layer:not(.hidden) .record-editor');
    const dialog=drawer||palette?.querySelector('.command-card');if(!dialog)return;
    const focusable=[...dialog.querySelectorAll(selector)].filter(el=>!el.hidden&&el.getClientRects().length);if(!focusable.length)return;
    const first=focusable[0],last=focusable[focusable.length-1];
    if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}
    else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}
  });
})();

// EP-DI v2.0.0-beta.1.1 — Editions operations interactions
(()=>{
  const $$=(s,r=document)=>[...r.querySelectorAll(s)];
  const updateCreatePublishing=(form)=>{
    const mode=form.querySelector('input[name="publish_mode"]:checked')?.value||'MANUAL';
    const fields=form.querySelector('[data-edition-schedule-fields]');
    if(fields){const on=mode==='SCHEDULED';fields.hidden=!on;const dt=fields.querySelector('input[name="scheduled_at"]');if(dt)dt.required=on;}
    const submit=form.querySelector('[data-edition-create-submit]');
    if(submit)submit.textContent=mode==='SCHEDULED'?'Add & schedule':(mode==='AUTO_READY'?'Add & auto-publish':'Create edition');
  };
  document.addEventListener('change',e=>{
    const form=e.target.closest('.edition-create-form');if(form&&e.target.name==='publish_mode')updateCreatePublishing(form);
    const reprocess=e.target.closest('.edition-reprocess-form');if(reprocess&&e.target.name==='reprocess_mode'){
      const custom=reprocess.querySelector('[data-custom-processing]');if(custom)custom.hidden=e.target.value!=='custom';
    }
    const publish=e.target.closest('.edition-publish-form');if(publish&&e.target.name==='publish_action'){
      const fields=publish.querySelector('[data-edition-publish-schedule]');const on=e.target.value==='schedule';if(fields){fields.hidden=!on;const dt=fields.querySelector('input[name="scheduled_at"]');if(dt)dt.required=on;}
    }
  });
  document.addEventListener('click',e=>{
    const trigger=e.target.closest('[data-open-editor-target]');if(!trigger)return;
    const form=document.getElementById(trigger.dataset.openEditorTarget||'');if(!form)return;
    if(form.classList.contains('edition-create-form'))setTimeout(()=>updateCreatePublishing(document.querySelector('.record-editor .edition-create-form')||form),0);
  });
  $$('.edition-create-form').forEach(updateCreatePublishing);
})();

// EP-DI v2.0.0-beta.1.2 — Processing Control presets
(()=>{
  const form=document.querySelector('[data-processing-settings]');if(!form)return;
  const presets={
    recommended:{
      'processing.pdf_dpi':'180','processing.preview_width':'1280','processing.thumbnail_width':'360','processing.tile_size':'512',
      'processing.highres_quality':'90','processing.preview_quality':'82','processing.thumbnail_quality':'78','processing.tile_quality':'82',
      'processing.ocr_languages':'auto','processing.ocr_timeout_seconds':'300','processing.auto_language':'on',
      'processing.recovery_manual_smart':'on','processing.recovery_auto_rescue':'off','processing.recovery_max_rescues':'1',
      'processing.recovery_dpi_ceiling':'160','processing.recovery_tile_size':'384','processing.recovery_ocr_timeout_seconds':'450'
    },
    quality:{
      'processing.pdf_dpi':'220','processing.preview_width':'1600','processing.thumbnail_width':'420','processing.tile_size':'512',
      'processing.highres_quality':'94','processing.preview_quality':'88','processing.thumbnail_quality':'82','processing.tile_quality':'86',
      'processing.ocr_languages':'auto','processing.ocr_timeout_seconds':'420','processing.auto_language':'on',
      'processing.recovery_manual_smart':'on','processing.recovery_auto_rescue':'off','processing.recovery_max_rescues':'1',
      'processing.recovery_dpi_ceiling':'170','processing.recovery_tile_size':'384','processing.recovery_ocr_timeout_seconds':'480'
    },
    recovery:{
      'processing.pdf_dpi':'150','processing.preview_width':'1100','processing.thumbnail_width':'320','processing.tile_size':'384',
      'processing.highres_quality':'86','processing.preview_quality':'80','processing.thumbnail_quality':'74','processing.tile_quality':'78',
      'processing.ocr_languages':'auto','processing.ocr_timeout_seconds':'450','processing.auto_language':'on',
      'processing.recovery_manual_smart':'on','processing.recovery_auto_rescue':'on','processing.recovery_max_rescues':'1',
      'processing.recovery_dpi_ceiling':'150','processing.recovery_tile_size':'384','processing.recovery_ocr_timeout_seconds':'540'
    }
  };
  const state=form.querySelector('[data-processing-preset-state]');
  form.addEventListener('click',e=>{
    const btn=e.target.closest('[data-processing-preset]');if(!btn)return;
    const preset=presets[btn.dataset.processingPreset];if(!preset)return;
    for(const [key,value] of Object.entries(preset)){
      const field=form.querySelector(`[name="settings[${key}]"]`);if(!field)continue;field.value=value;field.dispatchEvent(new Event('change',{bubbles:true}));
    }
    form.querySelectorAll('[data-processing-preset]').forEach(b=>b.classList.toggle('active',b===btn));
    if(state){state.hidden=false;state.textContent=`${btn.textContent.trim()} preset applied · unsaved`;}
  });
  form.addEventListener('input',()=>{if(state&&!state.hidden&&!state.textContent.includes('customized'))state.textContent='Preset customized · unsaved';});
})();

// EP-DI v2.0.0-rc.3 — browser-local final acceptance checklist.
(()=>{
  const root=document.querySelector('[data-final-acceptance]');if(!root)return;
  const version=root.dataset.version||'rc3',checks=[...root.querySelectorAll('[data-acceptance-check]')];
  const progress=root.querySelector('[data-acceptance-progress]');
  const key=`epdi.finalAcceptance.${version}`;
  let saved={};try{saved=JSON.parse(localStorage.getItem(key)||'{}')||{}}catch{}
  checks.forEach(box=>{box.checked=!!saved[box.dataset.acceptanceCheck]});
  const sync=()=>{
    const state={};checks.forEach(box=>state[box.dataset.acceptanceCheck]=box.checked);
    try{localStorage.setItem(key,JSON.stringify(state))}catch{}
    const done=checks.filter(box=>box.checked).length;
    if(progress){progress.textContent=`${done} / ${checks.length} completed`;progress.classList.toggle('is-complete',done===checks.length);}
    root.classList.toggle('acceptance-complete',done===checks.length);
  };
  root.addEventListener('change',e=>{if(e.target.matches('[data-acceptance-check]'))sync()});
  root.querySelector('[data-acceptance-reset]')?.addEventListener('click',()=>{
    if(!window.confirm('Reset the browser-local final acceptance checklist?'))return;
    checks.forEach(box=>box.checked=false);sync();
  });
  root.querySelector('[data-acceptance-print]')?.addEventListener('click',()=>window.print());
  sync();
})();

// Deep-link the single Add edition action into the existing accessible drawer.
(()=>{if(new URLSearchParams(location.search).get('create')==='1'){document.querySelector('[data-open-editor-target="edition-create-form"]')?.click();}})();
