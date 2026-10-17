// ePaper SaaS v1.1.7 — guest clipping conversion gate
(()=>{
  const root=document.querySelector('#reader[data-reader-guest],#reader[data-subscribe-url]');
  if(!root)return;
  const gate=document.querySelector('[data-guest-clip-gate]');
  if(!gate)return;
  const title=gate.querySelector('[data-guest-gate-title]');
  const copy=gate.querySelector('[data-guest-gate-copy]');
  const login=gate.querySelector('[data-guest-login]');
  const subscribe=gate.querySelector('[data-guest-subscribe]');
  const close=()=>{gate.classList.add('hidden');root.focus?.()};
  const open=(mode='guest',payload={})=>{
    if(mode==='subscribe'){
      if(title)title.textContent='Clipping needs an active subscription';
      if(copy)copy.textContent='Your account is signed in, but clipping is not included in the current subscription. Choose a subscription option to continue.';
      if(login)login.classList.add('hidden');
      if(subscribe){subscribe.classList.remove('hidden');subscribe.textContent='View subscription options';subscribe.href=payload.subscribe_url||root.dataset.subscribeUrl||'/subscribe';}
    }else{
      if(title)title.textContent='You’ve created your free clipping';
      if(copy)copy.textContent='Sign in to create more clippings, keep them in your Library and access them across devices.';
      if(login){login.classList.remove('hidden');login.href=payload.login_url||root.dataset.guestLoginUrl||'/login';login.textContent='Sign in / Create account';}
      if(subscribe){subscribe.classList.remove('hidden');subscribe.href=payload.subscribe_url||root.dataset.subscribeUrl||'/subscribe';}
    }
    gate.classList.remove('hidden');
    setTimeout(()=>gate.querySelector('a:not(.hidden),button:not(.hidden)')?.focus(),0);
  };
  gate.querySelector('[data-guest-gate-close]')?.addEventListener('click',close);
  gate.querySelector('[data-guest-gate-later]')?.addEventListener('click',close);
  gate.addEventListener('click',e=>{if(e.target===gate)close()});
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!gate.classList.contains('hidden')){e.preventDefault();close()}},true);
  const saveSelection=document.querySelector('[data-clip-save]');
  if(root.dataset.readerGuest==='1'&&root.dataset.guestClipUsed==='1'){
    saveSelection?.addEventListener('click',e=>{e.preventDefault();e.stopImmediatePropagation();open('guest')},true);
  }else if(root.dataset.readerGuest!=='1'&&root.dataset.clipSubscriptionRequired==='1'){
    saveSelection?.addEventListener('click',e=>{e.preventDefault();e.stopImmediatePropagation();open('subscribe')},true);
  }
  const original=window.epaperApi;
  if(typeof original==='function'){
    window.epaperApi=async function(path,opts){
      try{
        const data=await original.apply(this,arguments);
        if(path==='/api/v1/clips'&&data?.guest){root.dataset.guestClipUsed='1'}
        return data;
      }catch(err){
        const code=String(err?.code||err?.data?.code||'');
        const msg=String(err?.message||'');
        if(path==='/api/v1/clips'&&(code==='GUEST_CLIP_LIMIT'||code==='GUEST_CLIP_RATE_LIMIT'||msg.includes('free clipping')||msg.includes('Guest clipping'))){open('guest',err?.data||err||{});err.message='';}
        else if(path==='/api/v1/clips'&&(code==='ENTITLEMENT_REQUIRED'||msg.includes('current subscription'))){open('subscribe',err?.data||err||{});err.message='';}
        throw err;
      }
    };
  }
})();
