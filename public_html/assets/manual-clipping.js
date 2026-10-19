/* Drag a normalized rectangle on a private publisher page preview. */
(()=>{
  'use strict';
  const root=document.querySelector('[data-manual-clipping]');if(!root)return;
  const stage=root.querySelector('[data-manual-stage]');
  const img=root.querySelector('[data-manual-image]');
  const box=root.querySelector('[data-manual-selection]');
  const form=root.querySelector('[data-manual-form]');
  if(!stage||!img||!box||!form)return;
  const inputs=Object.fromEntries(['x','y','w','h'].map(k=>[k,form.querySelector('[data-manual-coord="'+k+'"]')]));
  const feedback=form.querySelector('[data-manual-feedback]');
  const submit=form.querySelector('[data-manual-submit]');
  const privateButton=form.querySelector('[data-manual-save-private]');
  const markingReady=form.dataset.markingReady==='1';
  const canvas=form.querySelector('[data-manual-crop-preview]');
  const clamp=n=>Math.max(0,Math.min(1,n));
  const values=()=>Object.fromEntries(Object.entries(inputs).map(([k,el])=>[k,el.value.trim()===''?NaN:Number(el.value)]));
  const valid=b=>['x','y','w','h'].every(k=>Number.isFinite(b[k])&&b[k]>=0&&b[k]<=1)
    &&b.w>=.02&&b.h>=.02&&b.x+b.w<=1.0000001&&b.y+b.h<=1.0000001;
  function repaint(crop=true){
    const b=values(),ok=valid(b);
    box.hidden=!ok;
    if(ok){
      Object.assign(box.style,{left:(100*b.x)+'%',top:(100*b.y)+'%',width:(100*b.w)+'%',height:(100*b.h)+'%'});
      if(crop&&img.complete&&img.naturalWidth&&img.naturalHeight&&canvas){
        const sw=b.w*img.naturalWidth,sh=b.h*img.naturalHeight;
        canvas.width=Math.max(1,Math.round(Math.min(450,sw)));
        canvas.height=Math.max(1,Math.round(canvas.width*sh/sw));
        const ctx=canvas.getContext('2d');
        if(ctx){ctx.clearRect(0,0,canvas.width,canvas.height);
          ctx.drawImage(img,b.x*img.naturalWidth,b.y*img.naturalHeight,sw,sh,0,0,canvas.width,canvas.height);
          canvas.hidden=false;}
      }
    }else if(canvas)canvas.hidden=true;
    if(feedback){feedback.textContent=ok?'Area selected. Review the preview and save.':'Select an area at least 2% wide and tall, entirely inside the page.';feedback.classList.toggle('is-valid',ok);}
    if(submit)submit.disabled=!ok||!markingReady;
    if(privateButton)privateButton.disabled=!ok;
  }
  const point=e=>{
    const r=stage.getBoundingClientRect();
    return{x:clamp((e.clientX-r.left)/Math.max(1,r.width)),y:clamp((e.clientY-r.top)/Math.max(1,r.height))};
  };
  const set=(start,end,crop)=>{
    const b={x:Math.min(start.x,end.x),y:Math.min(start.y,end.y),w:Math.abs(start.x-end.x),h:Math.abs(start.y-end.y)};
    Object.keys(b).forEach(k=>{inputs[k].value=b[k].toFixed(6)});
    repaint(crop);
  };
  let pointer=null,start=null;
  stage.addEventListener('pointerdown',e=>{
    if(e.button!==0||pointer!==null)return;
    pointer=e.pointerId;start=point(e);stage.setPointerCapture(e.pointerId);set(start,start,false);e.preventDefault();
  });
  stage.addEventListener('pointermove',e=>{if(e.pointerId===pointer&&start){set(start,point(e),false);e.preventDefault()}});
  stage.addEventListener('pointerup',e=>{
    if(e.pointerId!==pointer||!start)return;
    set(start,point(e),true);
    if(stage.hasPointerCapture(e.pointerId))stage.releasePointerCapture(e.pointerId);
    pointer=null;start=null;
  });
  stage.addEventListener('pointercancel',()=>{pointer=null;start=null;repaint()});
  Object.values(inputs).forEach(input=>input.addEventListener('input',()=>repaint()));
  form.querySelector('[data-manual-reset]')?.addEventListener('click',()=>{
    Object.values(inputs).forEach(input=>{input.value=''});repaint();
  });
  img.addEventListener('load',()=>repaint());
  form.addEventListener('submit',e=>{
    if(!valid(values())){e.preventDefault();repaint();return}
    if(submit)submit.disabled=true;
    if(privateButton)privateButton.disabled=true;
  });
  repaint();
})();
