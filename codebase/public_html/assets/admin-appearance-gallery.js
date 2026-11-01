/* Appearance: scoped progressive enhancement, no network or persistence before Save. */
(()=>{
  'use strict';
  const gallery=document.querySelector('[data-appearance-gallery]');
  if(!gallery||!document.body.classList.contains('admin-shell'))return;
  const allowed=new Set(['frontend','admin','reader','auth']);
  const instruction=gallery.querySelector('[data-theme-selection-note]');
  gallery.addEventListener('click',event=>{
    const button=event.target.closest('[data-theme-choose]');
    if(!button||!gallery.contains(button))return;
    const surface=button.dataset.themeSurface||'';
    const theme=button.dataset.themeName||'';
    if(!allowed.has(surface)||!theme)return;
    const select=document.querySelector('select[name="settings[appearance.'+surface+'_theme]"]');
    if(!select||![...select.options].some(opt=>opt.value===theme))return;
    select.value=theme;
    select.dispatchEvent(new Event('change',{bubbles:true}));
    for(const item of gallery.querySelectorAll('[data-theme-choose]')){
      if(item.dataset.themeSurface!==surface)continue;
      const pending=item.dataset.themeName===theme;
      item.closest('.appearance-demo-card')?.classList.toggle('is-pending',pending);
      item.textContent=pending?'Selected — save to publish':'Select this theme';
      item.setAttribute('aria-pressed',String(pending));
    }
    if(instruction)instruction.textContent=theme+' selected for '+surface+'. Click Save Appearance above to publish.';
  });
})();
