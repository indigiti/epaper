/* Publisher effects studio. Preview only: saving requires the existing CSRF form. */
(()=>{
  'use strict';
  const root=document.querySelector('[data-effect-studio]');
  if(!root||!document.body.classList.contains('admin-shell'))return;
  const form=root.querySelector('[data-effect-form]');
  const choice=root.querySelector('[data-effect-choice]');
  const layer=root.querySelector('[data-effect-preview]');
  const status=root.querySelector('[data-effect-status]');
  const badge=root.querySelector('[data-effect-unsaved]');
  const toggle=root.querySelector('[data-effect-preview-toggle]');
  const reset=root.querySelector('[data-effect-reset]');
  const map=window.EpaperVantaConfig;
  if(!form||!choice||!layer||!map)return;
  let assets;
  try{assets=JSON.parse(document.querySelector('#epdi-effect-assets')?.textContent||'{}');}
  catch(_){return;}
  const safe=url=>typeof url==='string'&&url.startsWith('/assets/vendor/')&&!url.includes('//')&&!url.includes('..');
  const media=window.matchMedia('(prefers-reduced-motion: reduce)');
  const narrow=window.matchMedia('(max-width: 899px)');
  const lowPower=()=>{
    const c=navigator.connection||navigator.mozConnection||navigator.webkitConnection;
    return Boolean(c?.saveData||
      (typeof navigator.deviceMemory==='number'&&navigator.deviceMemory<4)||
      (typeof navigator.hardwareConcurrency==='number'&&navigator.hardwareConcurrency<4));
  };
  const canAnimate=()=>!media.matches&&!narrow.matches&&!lowPower()&&!document.hidden;
  const links={
    background_color:'backgroundColor',base_color:'baseColor',
    secondary_color:'secondaryColor',opacity:'opacity',size:'size',
    speed:'speed',amplitude_factor:'amplitudeFactor',
    x_offset:'xOffset',y_offset:'yOffset',
    halo_ring:'ringFactor',halo_rotation:'rotationFactor',
    wave_height:'waveHeight',wave_speed:'waveSpeed',
    wave_shininess:'shininess',wave_zoom:'zoom',
    net_points:'points',net_distance:'maxDistance',net_spacing:'spacing',
    net_dots:'showDots',dots_spacing:'dotSpacing',dots_lines:'showLines',
    cloud_sky:'skyColor',cloud_color:'cloudColor',
    cloud_shadow:'cloudShadowColor',cloud_sun:'sunColor',
    fog_highlight:'fogHighlight',fog_midtone:'fogMidtone',
    fog_lowlight:'fogLowlight',fog_blur:'blurFactor'
  };
  const fields=[...form.querySelectorAll('[data-effect-input]')];
  const saved=[...form.querySelectorAll('input,select')].map(el=>[el,el.value]);
  const params=()=>{
    const result={};
    for(const input of fields){
      const match=(input.name||'').match(/^settings\[home_effect\.([a-z_]+)\]$/);
      if(!match||!links[match[1]])continue;
      const key=links[match[1]];
      result[key]=input.tagName==='SELECT'?input.value==='On':input.value;
    }
    return map.sanitize(result);
  };
  const format=value=>{
    if(/^#/.test(value))return value.toUpperCase();
    const n=Number(value);
    return Number.isFinite(n)?String(Number(n.toFixed(2))):value;
  };
  function display(){
    for(const input of fields){
      const out=input.closest('[data-effect-field]')?.querySelector('output');
      if(out)out.textContent=format(input.value);
    }
    for(const card of root.querySelectorAll('[data-effect-select]')){
      const active=card.dataset.effectSelect===choice.value;
      card.classList.toggle('is-selected',active);
      card.setAttribute('aria-pressed',String(active));
    }
    for(const field of root.querySelectorAll('[data-effect-applies]')){
      const supported=(field.dataset.effectApplies||'').split(',');
      field.hidden=choice.value==='Off'||!supported.includes(choice.value);
    }
    const previewLink=root.querySelector('[data-effect-public-preview]');
    if(previewLink)previewLink.href='/?effect_preview='+encodeURIComponent(choice.value.toLowerCase());
  }
  const scripts=new Map();
  function load(url){
    if(!safe(url))return Promise.reject(Error('Unsupported asset path'));
    if(scripts.has(url))return scripts.get(url);
    const promise=new Promise((resolve,reject)=>{
      const tag=document.createElement('script');
      tag.src=url;tag.async=true;
      tag.onload=resolve;
      tag.onerror=()=>{tag.remove();reject(Error('Effect asset failed'));};
      document.head.appendChild(tag);
    });
    scripts.set(url,promise);
    return promise;
  }
  function webgl(){
    try{
      const gl=document.createElement('canvas').getContext('webgl',{failIfMajorPerformanceCaveat:true});
      if(!gl)return false;
      gl.getExtension('WEBGL_lose_context')?.loseContext();
      return true;
    }catch(_){return false;}
  }
  let effect=null,serial=0,timer=null,paused=false,failed=false;
  function dispose(){
    serial++;
    if(effect){try{effect.destroy();}catch(_){ }effect=null;}
    layer.querySelectorAll('canvas').forEach(c=>c.remove());
    layer.style.removeProperty('background');
  }
  async function render(){
    dispose();
    const generation=serial;
    const selected=choice.value.toUpperCase();
    const current=params();
    layer.style.opacity=String(current.opacity);
    if(selected==='OFF'){
      status.textContent='Effect disabled. The normal homepage design will be used.';
      return;
    }
    if(paused){status.textContent='Preview paused. Changes remain unsaved.';return;}
    if(!canAnimate()){
      status.textContent='Static preview: animation is disabled on mobile, low-power or reduced-motion devices.';
      return;
    }
    if(!webgl()){
      status.textContent='WebGL is unavailable. A static fallback will be used.';
      return;
    }
    if(failed){status.textContent='The animation is unavailable. The static preview remains usable.';return;}
    const src=assets.effects?.[selected];
    if(!safe(src)||!safe(assets.three))return;
    status.textContent='Loading live preview…';
    try{
      if(!window.THREE)await load(assets.three);
      if(!window.VANTA||typeof window.VANTA[selected]!=='function')await load(src);
      if(generation!==serial||paused||!canAnimate())return;
      const opts=map.options(selected,current);
      if(!opts||typeof window.VANTA?.[selected]!=='function')throw Error('Renderer not ready');
      effect=window.VANTA[selected]({...opts,THREE:window.THREE,el:layer});
      status.textContent='Preview only. Save to publish these settings.';
    }catch(_){
      failed=true;
      status.textContent='Animation could not start. You can still save the static appearance.';
    }
  }
  function schedule(){
    if(timer!==null)clearTimeout(timer);
    timer=window.setTimeout(render,520);
  }
  function dirty(){badge.hidden=false;display();schedule();}
  choice.addEventListener('change',dirty);
  for(const card of root.querySelectorAll('[data-effect-select]')){
    card.addEventListener('click',()=>{
      choice.value=card.dataset.effectSelect;
      dirty();
    });
  }
  for(const input of fields)input.addEventListener('input',dirty);
  reset.addEventListener('click',()=>{
    for(const [el,value] of saved)el.value=value;
    badge.hidden=true;display();schedule();
  });
  toggle.addEventListener('click',()=>{
    paused=!paused;
    toggle.textContent=paused?'Resume animation':'Pause animation';
    toggle.setAttribute('aria-pressed',String(!paused));
    schedule();
  });
  media.addEventListener?.('change',schedule);
  narrow.addEventListener?.('change',schedule);
  document.addEventListener('visibilitychange',schedule);
  window.addEventListener('pagehide',()=>{
    if(timer!==null)clearTimeout(timer);
    dispose();
  },{once:true});
  display();
  // Do not load shader dependencies on the initial Off state.
  if(choice.value!=='Off'&&canAnimate())schedule();
})();
