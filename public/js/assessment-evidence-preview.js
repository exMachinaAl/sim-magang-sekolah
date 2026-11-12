(()=>{
 const dialog=document.getElementById('assessment-evidence-lightbox');
 if(!dialog)return;
 const image=dialog.querySelector('[data-evidence-full-image]');
 const title=dialog.querySelector('#assessment-evidence-lightbox-title');
 const download=dialog.querySelector('[data-evidence-download]');
 const open=dialog.querySelector('[data-evidence-open]');
 const close=()=>{if(dialog.open)dialog.close();};
 document.addEventListener('click',event=>{
  const trigger=event.target.closest('[data-evidence-preview]');
  if(trigger){
   image.src=trigger.dataset.previewUrl; image.alt='Preview '+trigger.dataset.previewTitle;
   title.textContent=trigger.dataset.previewTitle; download.href=trigger.dataset.downloadUrl; open.href=trigger.dataset.openUrl;
   if(typeof dialog.showModal==='function')dialog.showModal();else dialog.setAttribute('open','');
   return;
  }
  if(event.target.closest('[data-evidence-close]'))close();
 });
 dialog.addEventListener('click',event=>{if(event.target===dialog)close();});
 dialog.addEventListener('close',()=>{image.removeAttribute('src');});
})();
