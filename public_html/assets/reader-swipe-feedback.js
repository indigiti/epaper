/* Reader Page-mode swipe feedback. Navigation remains owned by reader.js.
 * Unlike full-page WordPress gestures, this never changes location or captures touch.
 */
(()=>{
  'use strict';
  const root=document.querySelector('#reader');
  const stage=root?.querySelector('[data-page-stage]');
  if(!root||!stage)return;
  const motion=window.matchMedia?.('(prefers-reduced-motion: reduce)');
  const slow=()=>!!(navigator.connection?.saveData||motion?.matches);
  const colors={
    left:[
      [[139,92,246],[99,102,241],[56,189,248]],
      [[168,85,247],[79,70,229],[34,211,238]],
      [[109,40,217],[67,56,202],[14,165,233]],
      [[147,51,234],[59,130,246],[6,182,212]],
      [[192,132,252],[129,140,248],[103,232,249]]
    ],
    right:[
      [[249,115,22],[236,72,153],[251,191,36]],
      [[234,88,12],[219,39,119],[245,158,11]],
      [[251,146,60],[244,114,182],[253,224,71]],
      [[239,68,68],[217,70,239],[251,191,36]],
      [[249,115,22],[168,85,247],[236,72,153]]
    ]
  };
  const overlay=document.createElement('div');
  overlay.className='reader-swipe-fx';
  overlay.setAttribute('aria-hidden','true');
  const canvas=document.createElement('canvas');
  const arrow=document.createElement('span');
  arrow.className='reader-swipe-fx-arrow';
  overlay.append(canvas,arrow);
  stage.appendChild(overlay);
  const ctx=canvas.getContext('2d',{alpha:true});
  let active=false,direction='',palette=null,frame=0,lastFrame=0,started=0,exitTimer=0,width=0,height=0;
  function resize(){
    const box=stage.getBoundingClientRect();
    width=Math.max(1,Math.round(box.width));
    height=Math.max(1,Math.round(box.height));
    const ratio=Math.min(1.5,window.devicePixelRatio||1);
    canvas.width=Math.round(width*ratio);
    canvas.height=Math.round(height*ratio);
    ctx?.setTransform(ratio,0,0,ratio,0,0);
  }
  function draw(time){
    if(!active||!ctx||slow())return;
    frame=requestAnimationFrame(draw);
    if(time-lastFrame<33)return; // Cap the canvas effect around 30 fps on mobile.
    lastFrame=time;
    ctx.clearRect(0,0,width,height);
    const angle=(time-started)*.0011;
    const radius=Math.hypot(width,height)*.67;
    const cx=width/2,cy=height/2;
    palette.forEach((color,i)=>{
      const a=angle+i*2*Math.PI/3;
      const x=cx+Math.cos(a)*radius*.36;
      const y=cy+Math.sin(a)*radius*.36;
      const gradient=ctx.createRadialGradient(x,y,0,cx,cy,radius);
      gradient.addColorStop(0,'rgba('+color.join(',')+',.47)');
      gradient.addColorStop(1,'rgba('+color.join(',')+',0)');
      ctx.globalCompositeOperation=i===0?'source-over':'screen';
      ctx.fillStyle=gradient;
      ctx.fillRect(0,0,width,height);
    });
    ctx.globalCompositeOperation='source-over';
  }
  function stop(){
    if(frame)cancelAnimationFrame(frame);
    frame=0;
    ctx?.clearRect(0,0,width,height);
  }
  function hide(){
    clearTimeout(exitTimer);
    exitTimer=0;active=false;
    overlay.classList.remove('visible','committed');
    stop();direction='';palette=null;
  }
  function show(dir){
    if(!['left','right'].includes(dir))return;
    clearTimeout(exitTimer);
    exitTimer=0;
    if(dir!==direction){
      direction=dir;
      const options=colors[dir];
      palette=options[Math.floor(Math.random()*options.length)];
      arrow.textContent=dir==='left'?'←':'→';
      overlay.dataset.direction=dir;
    }
    overlay.classList.add('visible');
    overlay.classList.remove('committed');
    if(!active){
      active=true;
      if(!slow()&&ctx){
        resize();
        started=performance.now();lastFrame=0;
        frame=requestAnimationFrame(draw);
      }
    }
  }
  function commit(dir){
    show(dir);
    overlay.classList.add('committed');
    exitTimer=setTimeout(hide,390); // In-app page changes never wait for an animation.
  }
  window.EpdiSwipeFeedback={show,hide,commit};
  motion?.addEventListener?.('change',()=>{
    if(slow())stop();
    else if(active&&!frame&&ctx){started=performance.now();frame=requestAnimationFrame(draw)}
  });
  window.addEventListener('resize',()=>{if(active&&!slow())resize()},{passive:true});
  window.addEventListener('pagehide',hide);
})();
