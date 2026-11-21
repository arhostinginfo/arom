/* AMC pages (21/11/2026): dialogs, client location lines, the customer's details on the contract page and the
   auto-fill of Return & Replace. Used only by resources/views/amc. */
(function(){
'use strict';
var $=function(s,r){return (r||document).querySelector(s)}, $$=function(s,r){return [].slice.call((r||document).querySelectorAll(s))};
function esc(v){var d=document.createElement('div');d.textContent=v==null?'':String(v);return d.innerHTML}

/* Dialogs: [data-open="#id"] shows the overlay, [data-x] or Escape hides it; [data-autoopen] opens on load. */
var lastFocus=null;
function open(layer){lastFocus=document.activeElement;layer.hidden=false;var f=layer.querySelector('button[data-x],input:not([type=hidden]),select');if(f)f.focus()}
function close(layer){layer.hidden=true;if(lastFocus&&lastFocus.focus)lastFocus.focus()}
$$('[data-open]').forEach(function(b){b.addEventListener('click',function(){var l=$(b.dataset.open);if(l)open(l)})});
$$('.overlay').forEach(function(l){$$('[data-x]',l).forEach(function(x){x.addEventListener('click',function(){close(l)})})});
document.addEventListener('keydown',function(e){if(e.key==='Escape')$$('.overlay').forEach(function(l){if(!l.hidden&&l.id!=='duePop')close(l)})});
$$('.overlay[data-autoopen]').forEach(open);

/* Uploads (21/11/2026): the files chosen together may be at most data-max-total-kb in all (the server's post limit) and
   data-max-files in number, checked before sending; the per-file size and type check is app.js's. The server checks again. */
function totalCheck(f){
  if(!f.files||!f.files.length||f.validationMessage)return true;
  var total=0,msg='';
  for(var i=0;i<f.files.length;i++)total+=f.files[i].size;
  if(f.files.length>(+f.dataset.maxFiles))msg='Choose at most '+f.dataset.maxFiles+' files at a time.';
  else if(total>(+f.dataset.maxTotalKb)*1024)msg=f.dataset.totalMsg;
  if(msg){f.setCustomValidity(msg);f.reportValidity();f.value='';return false}
  return true;
}
document.addEventListener('change',function(e){var f=e.target;if(f.matches&&f.matches('input[type=file][data-max-total-kb]'))totalCheck(f)});
$$('form.amcup').forEach(function(form){form.addEventListener('submit',function(e){var f=form.querySelector('input[type=file][data-max-total-kb]');if(f&&!totalCheck(f))e.preventDefault()})});

/* AMC Client Master: add and remove location lines. */
var body=$('#amcLines'),tpl=$('#amcLineTpl');
function renumber(){$$('tr',body).forEach(function(tr,i){$$('[name^="lines["]',tr).forEach(function(inp){inp.name=inp.name.replace(/^lines\[[^\]]*\]/,'lines['+i+']')})})}
if(body&&tpl){
  $$('[data-add-line]').forEach(function(b){b.addEventListener('click',function(){
    var i=body.children.length;body.insertAdjacentHTML('beforeend',tpl.innerHTML.replace(/__i__/g,i).replace(/__n__/g,i+1));
    var f=body.lastElementChild.querySelector('input');if(f)f.focus();
  })});
  body.addEventListener('click',function(e){
    var b=e.target.closest('[data-del-line]');if(!b)return;
    var tr=b.closest('tr');
    if(body.children.length===1){$$('input,select',tr).forEach(function(x){x.value=''});return}
    tr.remove();renumber();
  });
}

/* A client's details (segment, lines, Active contract) from amc.clients.info, cached per client. */
var infoCache={};
function clientInfo(url,id){
  if(!id)return Promise.resolve(null);
  if(infoCache[id])return Promise.resolve(infoCache[id]);
  return fetch(url.replace('__ID__',id),{headers:{'Accept':'application/json'},credentials:'same-origin'})
    .then(function(r){return r.ok?r.json():null}).then(function(d){if(d)infoCache[id]=d;return d}).catch(function(){return null});
}

/* Customer PO / AMC: picking the customer shows its segment and location / planter / plant table. */
var cSel=$('#amc_client_id[data-info-url]'),cBox=$('#amcClientInfo');
if(cSel&&cBox){
  cSel.addEventListener('change',function(){
    if(!cSel.value){cBox.innerHTML='<div class="cap muted">Choose a customer to see its locations, planters and plants.</div>';return}
    cBox.innerHTML='<div class="cap muted">Loading…</div>';
    clientInfo(cSel.dataset.infoUrl,cSel.value).then(function(d){
      if(!d){cBox.innerHTML='<div class="cap muted">The customer\'s details could not be loaded.</div>';return}
      var h='<div class="kv"><span>Business segment: <b>'+esc(d.segment||'–')+'</b></span>'+(d.contract?'<span>Active contract: <b>'+esc(d.contract.number)+'</b></span>':'')+'</div>';
      if(!d.lines.length)h+='<div class="cap muted">No location lines in the AMC Client Master.</div>';
      else{
        h+='<div class="tablecard"><table class="atable rtable"><thead><tr><th>Location 1</th><th>Location 2</th><th>Planter</th><th class="r">Planter qty</th><th>Plant</th><th class="r">Plant qty</th></tr></thead><tbody>';
        d.lines.forEach(function(l){h+='<tr><td data-label="Location 1">'+esc(l.location_1)+'</td><td data-label="Location 2">'+esc(l.location_2)+'</td><td data-label="Planter">'+esc(l.planter)+
          '</td><td data-label="Planter qty" class="r num">'+esc(l.planter_qty)+'</td><td data-label="Plant">'+esc(l.plant)+'</td><td data-label="Plant qty" class="r num">'+esc(l.plant_qty)+'</td></tr>'});
        h+='</tbody></table></div>';
      }
      cBox.innerHTML=h;
    });
  });
}

/* Return & Replace: the customer fills its location lines and, from its Active contract, the AMC type and gardener;
   the plant fills its size. Values the user already chose are kept when the customer is picked again. */
var rForm=$('#amcReturnForm');
if(rForm){
  var rc=$('#amc_client_id',rForm),line=$('#amc_client_line_id',rForm),l1=$('#location_1_show',rForm),l2=$('#location_2_show',rForm),
      type=$('#amc_type_id',rForm),gard=$('#gardener_id',rForm),plant=$('#amc_plant_id',rForm),size=$('#plant_size_show',rForm),note=$('#amcContractNote',rForm);
  var showLine=function(){var o=line.options[line.selectedIndex];l1.value=o&&o.dataset.l1||'';l2.value=o&&o.dataset.l2||''};
  var ensure=function(sel,id,label){if(!sel||!id)return;id=String(id);if(![].some.call(sel.options,function(o){return o.value===id})){var o=document.createElement('option');o.value=id;o.textContent=label||id;sel.appendChild(o)}};
  rc.addEventListener('change',function(){
    line.innerHTML='<option value="">Select a location</option>';showLine();
    if(note)note.textContent='';
    clientInfo(rForm.dataset.infoUrl,rc.value).then(function(d){
      if(!d)return;
      d.lines.forEach(function(l){var o=document.createElement('option');o.value=l.id;o.textContent=l.label;o.dataset.l1=l.location_1||'';o.dataset.l2=l.location_2||'';line.appendChild(o)});
      if(d.lines.length===1){line.value=String(d.lines[0].id)}
      showLine();
      if(d.contract){
        if(type&&d.contract.amc_type_id){ensure(type,d.contract.amc_type_id,d.contract.type);type.value=String(d.contract.amc_type_id)}
        if(gard&&d.contract.gardener_id){ensure(gard,d.contract.gardener_id,d.contract.gardener);gard.value=String(d.contract.gardener_id);gard.dispatchEvent(new Event('change',{bubbles:true}))}
        if(note)note.textContent='AMC type and gardener taken from '+d.contract.number+'; you can change them.';
      }else if(note)note.textContent='This customer has no Active contract: choose the AMC type and gardener.';
    });
  });
  line.addEventListener('change',showLine);
  if(plant&&size)plant.addEventListener('change',function(){var o=plant.options[plant.selectedIndex];size.value=o&&o.dataset.size||''});
}
})();
