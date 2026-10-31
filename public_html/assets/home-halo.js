/*
 * Vanta HALO (MIT) — progressive enhancement for the public homepage only.
 * Same-origin, pinned libraries; no dependency is requested on mobile,
 * reduced motion, Save-Data, low-memory or unsupported WebGL devices.
 */
(()=>{
  'use strict';
  const target=document.querySelector('[data-epaper-halo]');
  if(!target||!document.body.classList.contains('public-shell'))return;

  const motion=window.matchMedia('(prefers-reduced-motion: reduce)');
  const narrow=window.matchMedia('(max-width: 899px)');
  const connection=navigator.connection||navigator.mozConnection||navigator.webkitConnection;
  const isLowEnd=()=>Boolean(
    (connection&&connection.saveData)||
    (typeof navigator.deviceMemory==='number'&&navigator.deviceMemory<4)||
    (typeof navigator.hardwareConcurrency==='number'&&navigator.hardwareConcurrency<4)
  );
  const allowed=()=>!motion.matches&&!narrow.matches&&!isLowEnd()&&!document.hidden;
  let effect=null,visible=false,requested=false,disposed=false,requestSerial=0,loaded=null;
  let idleId=null;
  const safeAsset=(src)=>typeof src==='string'&&
    src.startsWith('/assets/vendor/')&&!src.includes('//')&&!src.includes('..');

  function supported(){
    if(!('WebGLRenderingContext' in window))return false;
    try{
      const canvas=document.createElement('canvas');
      const gl=canvas.getContext('webgl',{failIfMajorPerformanceCaveat:true});
      if(!gl)return false;
      gl.getExtension('WEBGL_lose_context')?.loseContext();
      return true;
    }catch(_){return false;}
  }

  function load(src){
    return new Promise((resolve,reject)=>{
      if(!safeAsset(src))return reject(new Error('Vanta assets must be local'));
      const script=document.createElement('script');
      script.src=src;
      script.async=true;
      script.referrerPolicy='same-origin';
      script.onload=()=>resolve();
      script.onerror=()=>{script.remove();reject(new Error('HALO asset could not load'));};
      document.head.appendChild(script);
    });
  }

  function dependencies(){
    if(loaded)return loaded;
    loaded=(async()=>{
      if(!window.THREE)await load(target.dataset.threeSrc);
      if(!window.VANTA||typeof window.VANTA.HALO!=='function'){
        await load(target.dataset.haloSrc);
      }
      if(!window.THREE||typeof window.VANTA?.HALO!=='function'){
        throw new Error('HALO dependency unavailable');
      }
    })();
    return loaded;
  }

  function destroy(){
    requestSerial++;
    if(effect){
      try{effect.destroy();}catch(_){}
      effect=null;
    }
    target.classList.remove('epdi-halo-running');
  }

  async function start(){
    if(disposed||!visible||!allowed()||effect||requested)return;
    if(!supported())return;
    requested=true;
    const generation=++requestSerial;
    try{
      await dependencies();
      if(disposed||generation!==requestSerial||!visible||!allowed())return;
      effect=window.VANTA.HALO({
        el:target,
        THREE:window.THREE,
        mouseControls:false,
        touchControls:false,
        gyroControls:false,
        minHeight:240,
        minWidth:320,
        scale:1.35,
        scaleMobile:1.8,
        backgroundColor:0x10233e,
        baseColor:0x3276af,
        color2:0x6de5d2,
        amplitudeFactor:0.75,
        ringFactor:1.15,
        rotationFactor:0.18,
        xOffset:0.12,
        yOffset:0,
        size:1.15,
        speed:0.30
      });
      if(!effect)return;
      target.classList.add('epdi-halo-running');
    }catch(_){
      // Static CSS is the permanent fallback; do not retry failed downloads.
      loaded=Promise.reject(new Error('Effect unavailable'));
      loaded.catch(()=>{});
    }finally{
      requested=false;
    }
  }

  function refresh(){
    if(disposed)return;
    if(!allowed()||!visible){destroy();return;}
    start();
  }

  const observer='IntersectionObserver' in window?
    new IntersectionObserver(entries=>{
      visible=Boolean(entries[0]?.isIntersecting);
      refresh();
    },{rootMargin:'90px 0px',threshold:0}):null;
  if(observer)observer.observe(target);
  else visible=true;

  function schedule(){
    if(!allowed())return;
    if('requestIdleCallback' in window){
      idleId=window.requestIdleCallback(refresh,{timeout:1600});
    }else{
      idleId=window.setTimeout(refresh,750);
    }
  }
  if(document.readyState==='complete')schedule();
  else window.addEventListener('load',schedule,{once:true});

  document.addEventListener('visibilitychange',refresh);
  motion.addEventListener?.('change',refresh);
  narrow.addEventListener?.('change',refresh);
  window.addEventListener('pagehide',()=>{
    disposed=true;
    if(observer)observer.disconnect();
    if(idleId!==null){
      if('cancelIdleCallback' in window)window.cancelIdleCallback(idleId);
      else clearTimeout(idleId);
    }
    destroy();
  },{once:true});
})();
