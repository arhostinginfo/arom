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

/* AMC Employee master (22/11/2026): the chosen photo is shown in place of the current one before saving. Runs after
   app.js's size / type check, which clears a refused file; then the current photo comes back. */
$$('input[type=file][data-photo-preview]').forEach(function(f){
  var box=$(f.dataset.photoPreview);if(!box)return;
  var orig=box.innerHTML,url=null;
  f.addEventListener('change',function(){setTimeout(function(){
    if(url){URL.revokeObjectURL(url);url=null}
    var file=f.files&&f.files[0];
    if(!file||f.validationMessage||!/^image\/(jpeg|png)$/.test(file.type)){box.innerHTML=orig;return}
    url=URL.createObjectURL(file);
    var img=document.createElement('img');img.className='amcphoto lg';img.alt='Preview of the new photo';img.title='New photo: saved when you click Submit';
    img.onerror=function(){if(box.contains(img))box.innerHTML=orig};  // not a readable image (the server refuses it too)
    img.src=url;
    box.innerHTML='';box.appendChild(img);
  },0)});
});

/* AMC masters tabs (22/11/2026, test fix): on a phone the tabs scroll sideways; the current master's tab is scrolled
   into view, or it would sit off-screen to the right (AMC type, AMC Employee master). */
$$('.amcmtabs').forEach(function(t){var on=$('.on',t);if(on&&t.scrollWidth>t.clientWidth)t.scrollLeft+=on.getBoundingClientRect().left-t.getBoundingClientRect().left-(t.clientWidth-on.offsetWidth)/2});

/* AMC Client Inventory (22/11/2026): entries grouped by Location 1. Each block's Location 1 is copied into the hidden
   location_1 of its rows (the form saves one line per entry). New rows get a fresh key, so the keys never clash. */
var locs=$('#amcLocs'),rowTpl=$('#amcRowTpl'),locTpl=$('#amcLocTpl');
if(locs&&rowTpl&&locTpl){
  var next=+locs.dataset.next||1;
  var sync=function(block){var v=$('[data-loc1]',block).value;$$('[data-loc1-copy]',block).forEach(function(h){h.value=v})};
  var addRow=function(block){var k=next++;$('[data-rows]',block).insertAdjacentHTML('beforeend',rowTpl.innerHTML.replace(/__i__/g,k));sync(block);
    var f=$('[data-rows]',block).lastElementChild.querySelector('input:not([type=hidden])');if(f)f.focus()};
  var clearRow=function(tr){$$('input:not([data-loc1-copy]),select',tr).forEach(function(x){if(x.type==='hidden'&&/\[id\]$/.test(x.name))x.parentNode.removeChild(x);else x.value=''})};
  locs.addEventListener('input',function(e){if(e.target.matches('[data-loc1]'))sync(e.target.closest('[data-loc]'))});
  locs.addEventListener('click',function(e){
    var b=e.target.closest('button');if(!b)return;
    var block=b.closest('[data-loc]');
    if(b.matches('[data-add-row]'))addRow(block);
    else if(b.matches('[data-del-row]')){var tr=b.closest('tr'),rows=$('[data-rows]',block);
      if(rows.children.length===1)clearRow(tr);else rows.removeChild(tr)}
    else if(b.matches('[data-del-loc]')){
      if(locs.children.length===1){$('[data-loc1]',block).value='';var rs=$('[data-rows]',block);while(rs.children.length>1)rs.removeChild(rs.lastElementChild);clearRow(rs.firstElementChild);sync(block)}
      else locs.removeChild(block);
    }
  });
  $$('[data-add-loc]').forEach(function(b){b.addEventListener('click',function(){
    locs.insertAdjacentHTML('beforeend',locTpl.innerHTML);var block=locs.lastElementChild;addRow(block);$('[data-loc1]',block).focus();
  })});
  // The copies are set again before sending, in case a value was pasted or autofilled without an input event.
  var form=locs.closest('form');if(form)form.addEventListener('submit',function(){$$('[data-loc]',locs).forEach(sync)});
}

/* A client's details (segment, lines, Active contract) from amc.clients.info, cached per client. */
var infoCache={};
function clientInfo(url,id){
  if(!id)return Promise.resolve(null);
  if(infoCache[id])return Promise.resolve(infoCache[id]);
  return fetch(url.replace('__ID__',id),{headers:{'Accept':'application/json'},credentials:'same-origin'})
    .then(function(r){return r.ok?r.json():null}).then(function(d){if(d)infoCache[id]=d;return d}).catch(function(){return null});
}

/* AMC Tracker (was Customer PO / AMC): picking the customer shows its segment and location / planter / plant table. */
var cSel=$('#amc_client_id[data-info-url]'),cBox=$('#amcClientInfo');
if(cSel&&cBox){
  cSel.addEventListener('change',function(){
    if(!cSel.value){cBox.innerHTML='<div class="cap muted">Choose a customer to see its locations, planters and plants.</div>';return}
    cBox.innerHTML='<div class="cap muted">Loading…</div>';
    clientInfo(cSel.dataset.infoUrl,cSel.value).then(function(d){
      if(!d){cBox.innerHTML='<div class="cap muted">The customer\'s details could not be loaded.</div>';return}
      var h='<div class="kv"><span>Business segment: <b>'+esc(d.segment||'–')+'</b></span>'+(d.contract?'<span>Active contract: <b>'+esc(d.contract.number)+'</b></span>':'')+'</div>';
      if(!d.lines.length)h+='<div class="cap muted">No location lines in the AMC Client Inventory.</div>';
      else{
        h+='<div class="tablecard"><table class="atable rtable"><thead><tr><th>Location 1</th><th>Location 2</th><th>Planter</th><th class="r">Planter qty</th><th>Plant</th><th class="r">Plant qty</th></tr></thead><tbody>';
        d.lines.forEach(function(l){h+='<tr><td data-label="Location 1">'+esc(l.location_1)+'</td><td data-label="Location 2">'+esc(l.location_2)+'</td><td data-label="Planter">'+esc(l.planter)+
          '</td><td data-label="Planter qty" class="r num">'+esc(l.planter_qty)+'</td><td data-label="Plant">'+esc(l.plant)+'</td><td data-label="Plant qty" class="r num">'+esc(l.plant_qty)+'</td></tr>'});
        h+='</tbody></table></div>';
      }
      cBox.innerHTML=h;
      // (22/11/2026) the client's gardener, when the box is empty or still holds the previous customer's filled-in one
      var g=$('#gardener_id');
      if(g&&(!g.value||g.value===cSel.dataset.autoGardener)){
        var gid=d.gardener_id?String(d.gardener_id):'';
        if(gid&&![].some.call(g.options,function(o){return o.value===gid})){var o=document.createElement('option');o.value=gid;o.textContent=d.gardener||gid;g.appendChild(o)}
        if(g.value!==gid){g.value=gid;g.dispatchEvent(new Event('change',{bubbles:true}))}
        cSel.dataset.autoGardener=gid;
      }
    });
  });
}

/* Return & Replace (22/11/2026): the customer fills its Location 1 list and, from its Active contract (else the client),
   the AMC type and gardener. Picking a Location 1 shows its entries (Location 2, planter, plant and quantities) from the
   AMC Client Inventory; picking an entry fills Location 2 and, when empty, the plant. Picking a plant shows its size. */
var rForm=$('#amcReturnForm');
if(rForm){
  var rc=$('#amc_client_id',rForm),loc=$('#amc_location_1',rForm),box=$('#amcLocEntries',rForm),rows=$('#amcLocRows',rForm),entry=$('#amc_client_line_id',rForm),
      type=$('#amc_type_id',rForm),gard=$('#gardener_id',rForm),plant=$('#amc_plant_id',rForm),size=$('#plant_size_show',rForm),note=$('#amcContractNote',rForm);
  var ensure=function(sel,id,label){if(!sel||!id)return;id=String(id);if(![].some.call(sel.options,function(o){return o.value===id})){var o=document.createElement('option');o.value=id;o.textContent=label||id;sel.appendChild(o)}};
  var sumEl=$('#amcLocSummary',rForm),badgeCls={done:'b-ok',open:'b-warn'};
  var stCell=function(st){st=st||{state:'none',text:'Not replaced yet'};return '<td data-label="Replaced so far" data-state="'+esc(st.state)+'"><span class="badge '+(badgeCls[st.state]||'b-neu')+'">'+esc(st.text)+'</span></td>'};
  // (23/11/2026) For the chosen Location: how many entries, how many replaced, open or not replaced yet.
  var summary=function(){
    if(!sumEl)return;
    var v=loc.value,vis=$$('tr',rows).filter(function(tr){return v!==''&&tr.dataset.l1===v}),n={done:0,open:0,none:0};
    vis.forEach(function(tr){var c=tr.querySelector('[data-state]');n[c?c.dataset.state:'none']++});
    if(!v){sumEl.textContent='';return}
    var t=v+': '+vis.length+' entr'+(vis.length===1?'y':'ies')+' · '+n.done+' replaced · '+n.open+' with an open request · '+n.none+' not replaced yet';
    var lr={};try{lr=JSON.parse(box.dataset.locReturns||'{}')}catch(e){}
    if(Object.prototype.hasOwnProperty.call(lr,v)&&lr[v])t+='. Returns for the Location without an entry: '+lr[v].text;
    sumEl.textContent=t;
  };
  var rowOf=function(id){return $$('tr',rows).filter(function(tr){return tr.dataset.id===String(id)})[0]};
  // The Location's entries become the options of Location 2 (entry); its table rows show below.
  var showLoc=function(){
    var v=loc.value,keep=entry.value,vis=[];
    $$('tr',rows).forEach(function(tr){var on=v!==''&&tr.dataset.l1===v;tr.hidden=!on;if(on)vis.push(tr)});
    box.hidden=!vis.length;
    entry.innerHTML='<option value="">No entry</option>';
    vis.forEach(function(tr){var o=document.createElement('option');o.value=tr.dataset.id;o.textContent=tr.dataset.l2||('Entry '+tr.dataset.no);entry.appendChild(o)});
    entry.value=vis.some(function(tr){return tr.dataset.id===keep})?keep:(vis.length===1?vis[0].dataset.id:'');
    summary();
    showPick();
  };
  var showPick=function(){
    var tr=entry.value?rowOf(entry.value):null;
    $$('tr',rows).forEach(function(x){x.classList.toggle('sel',x===tr)});
    if(tr&&plant&&!plant.value&&tr.dataset.plant){plant.value=tr.dataset.plant;plant.dispatchEvent(new Event('change',{bubbles:true}))}
  };
  rc.addEventListener('change',function(){
    loc.innerHTML='<option value="">Select a location</option>';rows.innerHTML='';box.hidden=true;entry.innerHTML='<option value="">No entry</option>';
    if(note)note.textContent='';
    clientInfo(rForm.dataset.infoUrl,rc.value).then(function(d){
      if(!d)return;
      var seen={};
      d.lines.forEach(function(l){
        var k=l.location_1||'';
        if(k!==''&&!seen[k]){seen[k]=1;var o=document.createElement('option');o.textContent=k;loc.appendChild(o)}
        var tr=document.createElement('tr');tr.dataset.id=l.id;tr.dataset.l1=k;tr.dataset.l2=l.location_2||'';tr.dataset.no=l.line_no||'';tr.dataset.plant=l.plant_id||'';tr.hidden=true;
        tr.innerHTML='<td data-label="Location 2">'+esc(l.location_2)+
          '</td><td data-label="Planter">'+esc(l.planter)+'</td><td data-label="Planter qty" class="r num">'+esc(l.planter_qty)+'</td><td data-label="Plant">'+esc(l.plant)+
          '</td><td data-label="Plant qty" class="r num">'+esc(l.plant_qty)+'</td>'+stCell(l.replaced);
        rows.appendChild(tr);
      });
      box.dataset.locReturns=JSON.stringify(d.location_returns||{});
      var keys=Object.keys(seen);if(keys.length===1){loc.value=keys[0]}
      showLoc();
      var gid=d.contract?(d.contract.gardener_id||d.gardener_id):d.gardener_id,glabel=d.contract&&d.contract.gardener_id?d.contract.gardener:d.gardener;
      if(d.contract&&type&&d.contract.amc_type_id){ensure(type,d.contract.amc_type_id,d.contract.type);type.value=String(d.contract.amc_type_id)}
      if(gard&&gid){ensure(gard,gid,glabel);gard.value=String(gid);gard.dispatchEvent(new Event('change',{bubbles:true}))}
      if(note)note.textContent=d.contract
        ?'AMC type'+(d.contract.gardener_id?' and gardener':'')+' taken from '+d.contract.number+(!d.contract.gardener_id&&d.gardener_id?', gardener from the AMC Client Inventory':'')+'; you can change them.'
        :'This customer has no Active contract: choose the AMC type'+(d.gardener_id?'; gardener taken from the AMC Client Inventory.':' and gardener.');
    });
  });
  loc.addEventListener('change',showLoc);
  summary();
  entry.addEventListener('change',showPick);
  if(plant&&size)plant.addEventListener('change',function(){var o=plant.options[plant.selectedIndex];size.value=o&&o.dataset.size||''});
}
})();
