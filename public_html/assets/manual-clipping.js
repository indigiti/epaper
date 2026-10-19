/* Clip Marking mapping editor: adapt useful cropper UX without legacy dependencies.
 * Coordinates remain normalized and server-validated; saving a mark does not crop media.
 */
(()=>{
  'use strict';
  const root=document.querySelector('[data-manual-clipping]');
  if(!root)return;
  const stage=root.querySelector('[data-manual-stage]');
  const image=root.querySelector('[data-manual-image]');
  const selection=root.querySelector('[data-manual-selection]');
  const form=root.querySelector('[data-manual-form]');
  if(!stage||!image||!selection||!form)return;
  const fields=Object.fromEntries(['x','y','w','h'].map(k=>
    [k,form.querySelector('[data-manual-coord="'+k+'"]')]));
  const headline=form.querySelector('[name=title]');
  const feedback=form.querySelector('[data-manual-feedback]');
  const preview=form.querySelector('[data-manual-crop-preview]');
  const primary=form.querySelector('[data-manual-submit]');
  const privateButton=form.querySelector('[data-manual-save-private]');
  const quick=selection.querySelector('[data-manual-quick-save]');
  const heading=root.querySelector('[data-manual-form-heading]');
  const label=selection.querySelector('[data-manual-selection-label]');
  const defaultAction=form.dataset.manualPageUrl||form.action;
  const ready=form.dataset.markingReady==='1';
  const clamp=(n,lo=0,hi=1)=>Math.max(lo,Math.min(hi,n));
  const parse=()=>Object.fromEntries(Object.entries(fields).map(([k,input])=>
    [k,input.value.trim()===''?NaN:Number(input.value)]));
  const valid=b=>['x','y','w','h'].every(k=>Number.isFinite(b[k])&&b[k]>=0&&b[k]<=1)
    &&b.w>=.02&&b.h>=.02&&b.x+b.w<=1.0000001&&b.y+b.h<=1.0000001;
  let pointer=null;
  let drag=null;
  let editing=null;
  const setFields=b=>{
    for(const [k,n] of Object.entries(b))fields[k].value=clamp(n).toFixed(6);
  };
  function redraw(crop=true){
    const b=parse(),ok=valid(b);
    selection.hidden=!ok;
    if(ok){
      Object.assign(selection.style,{
        left:(b.x*100)+'%',top:(b.y*100)+'%',
        width:(b.w*100)+'%',height:(b.h*100)+'%'
      });
      if(crop&&preview&&image.complete&&image.naturalWidth&&image.naturalHeight){
        const sw=b.w*image.naturalWidth,sh=b.h*image.naturalHeight;
        preview.width=Math.max(1,Math.round(Math.min(460,sw)));
        preview.height=Math.max(1,Math.round(preview.width*sh/sw));
        const ctx=preview.getContext('2d');
        if(ctx){
          ctx.clearRect(0,0,preview.width,preview.height);
          ctx.drawImage(image,b.x*image.naturalWidth,b.y*image.naturalHeight,
            sw,sh,0,0,preview.width,preview.height);
          preview.hidden=false;
        }
      }
    }else if(preview)preview.hidden=true;
    if(feedback){
      feedback.textContent=ok
        ?(editing?'Adjust this saved outline and select Update.':'Outline ready. Enter a headline and select Save.')
        :'Draw or resize a rectangle at least 2% wide and tall, within the page.';
      feedback.classList.toggle('is-valid',ok);
    }
    if(primary)primary.disabled=!ok||!ready;
    if(privateButton)privateButton.disabled=!ok;
    if(quick)quick.disabled=!ok||!ready;
  }
  function resetMode(clear=false){
    editing=null;
    form.action=defaultAction;
    if(primary)primary.textContent='Save article outline';
    if(privateButton)privateButton.hidden=false;
    if(quick)quick.textContent='Save outline';
    if(label)label.textContent='New outline';
    if(heading)heading.textContent='New article outline';
    root.querySelectorAll('.manual-existing-mark.is-selected').forEach(el=>el.classList.remove('is-selected'));
    if(clear){
      for(const input of Object.values(fields))input.value='';
      if(headline)headline.value='';
    }
    redraw();
  }
  function editMark(el){
    if(!ready||!el?.dataset.markId)return;
    const id=el.dataset.markId;
    if(!/^[0-9a-f-]{36}$/i.test(id))return;
    const b=Object.fromEntries(['x','y','w','h'].map(k=>
      [k,Number(el.dataset['mark'+k.toUpperCase()])]));
    if(!valid(b))return;
    editing=id;
    form.action=defaultAction+'/marks/'+encodeURIComponent(id)+'/update';
    setFields(b);
    if(headline)headline.value=el.dataset.markTitle||'';
    if(primary)primary.textContent='Update article outline';
    if(privateButton)privateButton.hidden=true;
    if(quick)quick.textContent='Update outline';
    if(label)label.textContent='Editing existing outline';
    if(heading)heading.textContent='Edit marked article';
    root.querySelectorAll('.manual-existing-mark.is-selected').forEach(x=>x.classList.remove('is-selected'));
    root.querySelector('.manual-existing-mark[data-mark-id="'+id+'"]')?.classList.add('is-selected');
    redraw();
    stage.scrollIntoView({behavior:'smooth',block:'center'});
    stage.focus({preventScroll:true});
  }
  function newMark(){
    resetMode(true);
    setFields({x:.3,y:.3,w:.36,h:.2});
    redraw();
    stage.focus({preventScroll:true});
  }
  function point(event){
    const r=stage.getBoundingClientRect();
    return{x:clamp((event.clientX-r.left)/Math.max(1,r.width)),
      y:clamp((event.clientY-r.top)/Math.max(1,r.height))};
  }
  function dragBox(start,end){
    return{x:Math.min(start.x,end.x),y:Math.min(start.y,end.y),
      w:Math.abs(end.x-start.x),h:Math.abs(end.y-start.y)};
  }
  function resizeBox(start,dx,dy,handle){
    let left=start.x,top=start.y,right=start.x+start.w,bottom=start.y+start.h;
    if(handle.includes('w'))left=clamp(left+dx,0,right-.02);
    if(handle.includes('e'))right=clamp(right+dx,left+.02,1);
    if(handle.includes('n'))top=clamp(top+dy,0,bottom-.02);
    if(handle.includes('s'))bottom=clamp(bottom+dy,top+.02,1);
    return{x:left,y:top,w:right-left,h:bottom-top};
  }
  stage.addEventListener('pointerdown',e=>{
    if(e.button!==0||pointer!==null||e.target.closest('[data-manual-quick-save]'))return;
    const existing=e.target.closest('[data-manual-open-mark]');
    if(existing){
      editMark(existing);
      e.preventDefault();return;
    }
    const handle=e.target.closest('[data-manual-handle]')?.dataset.manualHandle;
    const moving=!handle&&e.target.closest('[data-manual-selection]');
    const p=point(e);
    if(handle&&valid(parse()))drag={mode:'resize',start:p,box:parse(),handle};
    else if(moving&&valid(parse()))drag={mode:'move',start:p,box:parse()};
    else{
      resetMode(true);
      drag={mode:'draw',start:p};
      setFields({x:p.x,y:p.y,w:0,h:0});
    }
    pointer=e.pointerId;
    stage.setPointerCapture(e.pointerId);
    stage.focus({preventScroll:true});
    e.preventDefault();
  });
  stage.addEventListener('pointermove',e=>{
    if(pointer!==e.pointerId||!drag)return;
    const p=point(e);
    if(drag.mode==='draw')setFields(dragBox(drag.start,p));
    if(drag.mode==='move'){
      const b=drag.box;
      setFields({...b,x:clamp(b.x+p.x-drag.start.x,0,1-b.w),
        y:clamp(b.y+p.y-drag.start.y,0,1-b.h)});
    }
    if(drag.mode==='resize')
      setFields(resizeBox(drag.box,p.x-drag.start.x,p.y-drag.start.y,drag.handle));
    redraw(false);
    e.preventDefault();
  });
  stage.addEventListener('pointerup',e=>{
    if(pointer!==e.pointerId)return;
    if(stage.hasPointerCapture(e.pointerId))stage.releasePointerCapture(e.pointerId);
    pointer=null;drag=null;redraw();
  });
  stage.addEventListener('pointercancel',()=>{
    pointer=null;drag=null;redraw();
  });
  root.querySelector('[data-manual-start]')?.addEventListener('click',newMark);
  root.querySelectorAll('[data-manual-open-mark]').forEach(el=>{
    if(!el.closest('[data-manual-stage]'))el.addEventListener('click',()=>editMark(el));
  });
  for(const input of Object.values(fields))input.addEventListener('input',()=>redraw());
  root.querySelector('[data-manual-reset]')?.addEventListener('click',()=>{
    resetMode(true);stage.focus({preventScroll:true});
  });
  if(quick)quick.addEventListener('click',e=>{
    e.stopPropagation();
    if(valid(parse())&&ready)form.requestSubmit(primary);
  });
  document.addEventListener('keydown',e=>{
    if(!root.contains(document.activeElement)||e.defaultPrevented)return;
    if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='s'){
      e.preventDefault();
      if(ready&&valid(parse()))form.requestSubmit(primary);
    }else if(e.key==='Escape'&&!e.ctrlKey&&!e.metaKey){
      resetMode(true);
    }else if(document.activeElement===stage&&['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(e.key)){
      const b=parse();if(!valid(b))return;
      const step=e.shiftKey ? 0.005 : 0.001;
      if(e.key==='ArrowLeft')b.x=clamp(b.x-step,0,1-b.w);
      if(e.key==='ArrowRight')b.x=clamp(b.x+step,0,1-b.w);
      if(e.key==='ArrowUp')b.y=clamp(b.y-step,0,1-b.h);
      if(e.key==='ArrowDown')b.y=clamp(b.y+step,0,1-b.h);
      setFields(b);redraw();e.preventDefault();
    }
  });
  image.addEventListener('load',()=>redraw());
  form.addEventListener('submit',e=>{
    if(!valid(parse())||!headline?.value.trim()){
      e.preventDefault();redraw();headline?.focus();return;
    }
    const requested=e.submitter?.value==='private'&&!editing?'private':'mark';
    let action=form.querySelector('[data-manual-final-action]');
    if(!action){
      action=document.createElement('input');
      action.type='hidden';action.name='action';action.dataset.manualFinalAction='';
      form.append(action);
    }
    action.value=requested;
    if(primary)primary.disabled=true;
    if(privateButton)privateButton.disabled=true;
    if(quick)quick.disabled=true;
  });
  redraw();
})();
