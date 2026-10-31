/*
 * Vanta HALO (MIT) — progressive enhancement for the public homepage only.
 * Same-origin, pinned libraries; no dependency is requested on mobile,
 * reduced motion, Save-Data, low-memory or unsupported WebGL devices.
 */
(()=>{
  'use strict';
  const target=document.querySelector('[data-epaper-halo]');
  if(!target||!document.body.classList.contains('public-shell'))return;
  const selectedEffect=String(target.dataset.effectName||'HALO').toUpperCase();
  const supportedNames=['HALO','WAVES','NET','DOTS','RINGS','CLOUDS','CELLS','FOG'];
  if(!supportedNames.includes(selectedEffect))return;
  let parameters={};
  try{parameters=JSON.parse(target.dataset.effectConfig||'{}');}catch(_){}
  const mapping=window.EpaperVantaConfig;
  const safeConfig=mapping?.sanitize?.(parameters)||parameters;

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
  let idleReady=false,permanentFailure=false;
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
      if(!window.VANTA||typeof window.VANTA[selectedEffect]!=='function'){
        await load(target.dataset.haloSrc);
      }
      if(!window.THREE||typeof window.VANTA?.[selectedEffect]!=='function'){
        throw new Error('Vanta effect dependency unavailable');
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
    if(disposed||!idleReady||permanentFailure||!visible||!allowed()||effect||requested)return;
    if(!supported())return;
    requested=true;
    const generation=++requestSerial;
    try{
      await dependencies();
      if(disposed||generation!==requestSerial||!visible||!allowed())return;
      const common={el:target,THREE:window.THREE,
        mouseControls:false,touchControls:false,gyroControls:false,
        minHeight:240,minWidth:320,
        scale:Math.max(1.45,Math.min(2.6,(window.devicePixelRatio||1)*1.2)),
        scaleMobile:2
      };
      const fallback={
        ...common,backgroundColor:0xf4f8ff,baseColor:0x3877bd,
        color2:0x67c9c3,amplitudeFactor:.75,ringFactor:1.15,
        rotationFactor:.18,xOffset:.12,yOffset:0,size:1.15,speed:.30
      };
      const options=mapping?.options?.(selectedEffect,safeConfig)||fallback;
      if(!options)throw new Error('Unsupported Vanta settings');
      target.style.opacity=String(Math.min(.45,Math.max(.08,Number(safeConfig.opacity)||.28)));
      effect=window.VANTA[selectedEffect]({...options,el:target,THREE:window.THREE});
      if(!effect)return;
      target.classList.add('epdi-halo-running');
    }catch(_){
      // Fail closed: keep the transparent static glow, no repeated script requests.
      permanentFailure=true;
    }finally{
      requested=false;
      // Visibility can change during loading; recheck after invalidated runs.
      if(!permanentFailure&&generation!==requestSerial&&visible&&allowed()&&!disposed){
        window.requestAnimationFrame(refresh);
      }
    }
  }

  function refresh(){
    if(disposed)return;
    if(!allowed()||!visible){destroy();return;}
    if(idleReady)start();
  }

  const observer='IntersectionObserver' in window?
    new IntersectionObserver(entries=>{
      visible=Boolean(entries[0]?.isIntersecting);
      refresh();
    },{rootMargin:'90px 0px',threshold:0}):null;
  if(observer)observer.observe(target);
  else visible=true;

  function schedule(){
    const ready=()=>{idleReady=true;refresh();};
    if('requestIdleCallback' in window){
      idleId=window.requestIdleCallback(ready,{timeout:1600});
    }else{
      idleId=window.setTimeout(ready,750);
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
