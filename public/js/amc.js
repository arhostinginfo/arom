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

/* Return & Replace (22/11/2026, rows 23/11/2026): the customer fills its Location list and, from its Active contract
   (else the client), the AMC type and gardener. Picking a Location shows its entries from the AMC Client Inventory with
   what has been replaced so far; clicking an entry adds it to the return as a row. Location 2, planter, plant, plant size
   and rate are shown as text (from the entry and the Plant master); only the planter qty and plant qty (each at most the
   entry's own) and the quality are entered. Amount = rate × plant qty, with the total below. */
var rForm=$('#amcReturnForm');
if(rForm){
  var rc=$('#amc_client_id',rForm),loc=$('#amc_location_1',rForm),box=$('#amcLocEntries',rForm),rows=$('#amcLocRows',rForm),
      items=$('#amcItemRows',rForm),tpl=$('#amcItemTpl'),empty=$('#amcItemsEmpty',rForm),
      type=$('#amc_type_id',rForm),gard=$('#gardener_id',rForm),note=$('#amcContractNote',rForm);
  var ensure=function(sel,id,label){if(!sel||!id)return;id=String(id);if(![].some.call(sel.options,function(o){return o.value===id})){var o=document.createElement('option');o.value=id;o.textContent=label||id;sel.appendChild(o)}};
  var sumEl=$('#amcLocSummary',rForm),badgeCls={done:'b-ok',open:'b-warn'};
  var stCell=function(st){st=st||{state:'none',text:'Not replaced yet'};return '<td data-label="Replaced so far" data-state="'+esc(st.state)+'"><span class="badge '+(badgeCls[st.state]||'b-neu')+'">'+esc(st.text)+'</span></td>'};
  // For the chosen Location: how many entries, how many replaced, open or not replaced yet.
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
  var itemRows=function(){return $$('tr[data-item]',items)};
  var itemOf=function(id){return itemRows().filter(function(tr){return id&&tr.dataset.line===String(id)})[0]};
  // Entries already in the return are marked, and their button says Added.
  var mark=function(){
    $$('tr',rows).forEach(function(tr){var on=!!itemOf(tr.dataset.id);tr.classList.toggle('sel',on);var b=tr.querySelector('[data-add-entry]');if(b)b.textContent=on?'Added':'Add'});
    if(empty)empty.hidden=itemRows().length>0;
  };
  var showLoc=function(){
    var v=loc.value,vis=[];
    $$('tr',rows).forEach(function(tr){var on=v!==''&&tr.dataset.l1===v;tr.hidden=!on;if(on)vis.push(tr)});
    box.hidden=!vis.length;
    summary();mark();
  };
  var f=function(tr,k){return tr.querySelector('[data-f="'+k+'"]')};
  var fmt=function(n){return n.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})};
  var total=$('#amcItemTotal',rForm);
  // Amount of a row (rate × plant qty) and the total; a qty above the entry's own is marked at once.
  var calc=function(){
    var sum=0;
    itemRows().forEach(function(tr){
      var rate=parseFloat(tr.dataset.rate),q=f(tr,'qty'),n=q&&/^\d+$/.test(q.value.trim())?parseInt(q.value,10):NaN,a=f(tr,'amount');
      var amt=!isNaN(rate)&&!isNaN(n)?rate*n:null;if(amt!==null)sum+=amt;
      if(a)a.textContent=amt===null?'':fmt(amt);
      ['pqty','qty'].forEach(function(k){var i=f(tr,k);if(!i)return;var max=i.dataset.max,v=i.value.trim();var over=max!==undefined&&max!==''&&/^\d+$/.test(v)&&parseInt(v,10)>parseInt(max,10);
        // (24/11/2026, test fix) only undo our own mark: a server error on the box (e.g. "whole number from 1") stays red
        if(over){i.classList.add('err');i.dataset.over='1'}else if(i.dataset.over){i.classList.remove('err');delete i.dataset.over}
        i.title=over?'At most '+max+' (the qty in the AMC Client Inventory)':''});
    });
    if(total)total.textContent=fmt(sum);
  };
  var setQty=function(tr,k,has,max){
    var i=f(tr,k),m=f(tr,k==='qty'?'qmax':'pmax');
    i.disabled=!has;i.value='';
    if(has&&max!==''&&max!==undefined){i.dataset.max=max;m.textContent='of '+max}else{delete i.dataset.max;m.textContent=''}
  };
  // Clicking an entry adds it as a row filled from the AMC Client Inventory; clicking it again goes to its row.
  var addEntry=function(en){
    var have=itemOf(en.dataset.id);
    if(have){var x=f(have,en.dataset.plant?'qty':'pqty');if(x)x.focus();return}
    var n=items.dataset.next||'0';items.dataset.next=String(+n+1);
    var t=document.createElement('tbody');t.innerHTML=tpl.innerHTML.split('__N__').join(n);
    var tr=t.querySelector('tr');
    tr.dataset.line=en.dataset.id;f(tr,'line').value=en.dataset.id;
    f(tr,'l2').textContent=en.dataset.l2||('Entry '+(en.dataset.no||''));
    f(tr,'planter').textContent=en.dataset.planter?(en.dataset.planterName||''):'';
    f(tr,'plant').textContent=en.dataset.plant?(en.dataset.plantName||''):'';
    f(tr,'size').textContent=en.dataset.plant?(en.dataset.size||''):'';
    tr.dataset.rate=en.dataset.plant?(en.dataset.rate||''):'';
    f(tr,'rate').textContent=tr.dataset.rate!==''?fmt(parseFloat(tr.dataset.rate)):'';
    setQty(tr,'pqty',!!en.dataset.planter,en.dataset.pqty);
    setQty(tr,'qty',!!en.dataset.plant,en.dataset.qty);
    items.appendChild(tr);touched(tr);
    mark();calc();
    var q=f(tr,en.dataset.plant?'qty':'pqty');if(q)q.focus();
  };
  // (23/11/2026, retest) a row added or taken out is a change: tell the unsaved-changes guard (it listens for input on named fields)
  var touched=function(tr){var h=tr&&f(tr,'line');if(h)h.dispatchEvent(new Event('input',{bubbles:true}))};
  var dropRow=function(tr){if(tr&&tr.parentNode){touched(tr);tr.parentNode.removeChild(tr)}mark();calc()};
  rows.addEventListener('click',function(e){var tr=e.target.closest('tr');if(tr&&!tr.hidden)addEntry(tr)});
  items.addEventListener('click',function(e){var b=e.target.closest('[data-del-item]');if(b)dropRow(b.closest('tr'))});
  items.addEventListener('input',function(e){if(e.target.dataset.f==='qty'||e.target.dataset.f==='pqty')calc()});
  // A new Location: rows of the other Location go, after a yes.
  var lastLoc=loc.value;
  loc.addEventListener('change',function(){
    var other=itemRows().filter(function(tr){var en=$$('tr',rows).filter(function(x){return x.dataset.id===tr.dataset.line})[0];return en&&en.dataset.l1!==loc.value});
    if(other.length&&!window.confirm('Changing the Location takes out the '+other.length+' row(s) of "'+lastLoc+'". Continue?')){loc.value=lastLoc;return}
    other.forEach(dropRow);
    lastLoc=loc.value;
    showLoc();
  });
  rc.addEventListener('change',function(){
    loc.innerHTML='<option value="">Select a location</option>';rows.innerHTML='';box.hidden=true;
    itemRows().forEach(dropRow);
    if(note)note.textContent='';
    clientInfo(rForm.dataset.infoUrl,rc.value).then(function(d){
      if(!d)return;
      var seen={};
      d.lines.forEach(function(l){
        var k=l.location_1||'';
        if(k!==''&&!seen[k]){seen[k]=1;var o=document.createElement('option');o.textContent=k;loc.appendChild(o)}
        var tr=document.createElement('tr');tr.className='clk';tr.hidden=true;
        tr.dataset.id=l.id;tr.dataset.l1=k;tr.dataset.l2=l.location_2||'';tr.dataset.no=l.line_no||'';
        tr.dataset.planter=l.planter_id||'';tr.dataset.planterName=l.planter||'';tr.dataset.pqty=l.planter_qty==null?'':l.planter_qty;
        tr.dataset.plant=l.plant_id||'';tr.dataset.plantName=l.plant_name||'';tr.dataset.qty=l.plant_qty==null?'':l.plant_qty;
        tr.dataset.size=l.plant_size||'';tr.dataset.rate=l.plant_rate==null?'':l.plant_rate;
        tr.innerHTML='<td data-label="Location 2">'+esc(l.location_2)+
          '</td><td data-label="Planter">'+esc(l.planter)+'</td><td data-label="Planter qty" class="r num">'+esc(l.planter_qty)+'</td><td data-label="Plant">'+esc(l.plant)+
          '</td><td data-label="Plant qty" class="r num">'+esc(l.plant_qty)+'</td>'+stCell(l.replaced)+'<td><button class="btn sec sm" type="button" data-add-entry>Add</button></td>';
        rows.appendChild(tr);
      });
      box.dataset.locReturns=JSON.stringify(d.location_returns||{});
      var keys=Object.keys(seen);if(keys.length===1){loc.value=keys[0]}
      lastLoc=loc.value;
      showLoc();
      var gid=d.contract?(d.contract.gardener_id||d.gardener_id):d.gardener_id,glabel=d.contract&&d.contract.gardener_id?d.contract.gardener:d.gardener;
      if(d.contract&&type&&d.contract.amc_type_id){ensure(type,d.contract.amc_type_id,d.contract.type);type.value=String(d.contract.amc_type_id)}
      if(gard&&gid){ensure(gard,gid,glabel);gard.value=String(gid);gard.dispatchEvent(new Event('change',{bubbles:true}))}
      if(note)note.textContent=d.contract
        ?'AMC type'+(d.contract.gardener_id?' and gardener':'')+' taken from '+d.contract.number+(!d.contract.gardener_id&&d.gardener_id?', gardener from the AMC Client Inventory':'')+'; you can change them.'
        :'This customer has no Active contract: choose the AMC type'+(d.gardener_id?'; gardener taken from the AMC Client Inventory.':' and gardener.');
    });
  });
  summary();mark();calc();
}
})();
