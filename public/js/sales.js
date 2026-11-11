/* Sales page (tax invoice and sales order on one page): item rows, PO parts, party details, line amounts and the
   invoice totals while typing. The server checks everything again on save (PO balance, credit limits, rates, GST);
   this only helps while typing. */
(function(){
'use strict';
var $=function(s,r){return (r||document).querySelector(s)}, $$=function(s,r){return [].slice.call((r||document).querySelectorAll(s))};
function num(v){var n=parseFloat(String(v||'').replace(/[, ]/g,''));return isFinite(n)?n:0}
function r2(n){return Math.round((n+Number.EPSILON)*100)/100}
function money(n){return n.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})}
function qty(n){return n.toLocaleString('en-IN',{maximumFractionDigits:3})}
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
function getJson(url){return fetch(url,{headers:{'Accept':'application/json'},credentials:'same-origin'}).then(function(r){return r.ok?r.json():null}).catch(function(){return null})}
function dataAttrs(d){if(!d)return '';return Object.keys(d).map(function(k){return ' data-'+k+'="'+esc(d[k])+'"'}).join('')}

/* The rate a row bills at: the typed rate, or (blank) the Item master price when it is in the party's currency. */
function rowRate(tr,cur){
  var r=tr.querySelector('[name$="[rate]"]');if(r&&String(r.value).trim()!=='')return num(r.value);
  if(tr.dataset.price!=null&&(!cur||(tr.dataset.cur||'INR')===cur))return num(tr.dataset.price);
  return 0;
}
/* Item picked in a free row: copy its price, currency and GST rates onto the row, and show the price as the hint. */
function takeItem(tr,cur){
  var sel=tr.querySelector('select[name$="[item_id]"]');if(!sel)return;
  var o=sel.selectedOptions&&sel.selectedOptions[0];if(!o||!o.value)return;
  ['price','cur','cgst','sgst','igst'].forEach(function(k){if(o.dataset[k]!=null)tr.dataset[k]=o.dataset[k]});
  var r=tr.querySelector('[name$="[rate]"]');if(!r||tr.dataset.price==null)return;
  r.placeholder=(tr.dataset.cur||'INR')===cur?'Item price '+qty(num(tr.dataset.price)):'Rate in '+cur;
}

/* Rows: amounts, renumbering, add and remove */
function amounts(body,cur){
  var total=0;
  $$('tr',body).forEach(function(tr){
    var q=tr.querySelector('[name$="[quantity]"]'),c=tr.querySelector('[data-amount]');
    if(!c)return;var a=r2(num(q&&q.value)*rowRate(tr,cur));c.textContent=a?money(a):'';total+=a;
  });
  return r2(total);
}
function renumber(body){$$('tr',body).forEach(function(tr,i){$$('[name^="lines["]',tr).forEach(function(inp){inp.name=inp.name.replace(/^lines\[[^\]]*\]/,'lines['+i+']')})})}
function addRow(body,tplId){
  var tpl=$(tplId),i=body.children.length;if(!tpl)return null;
  body.insertAdjacentHTML('beforeend',tpl.innerHTML.replace(/__i__/g,i).replace(/__n__/g,i+1).replace(/__URL__/g,esc(body.dataset.itemsUrl||'')));
  var tr=body.lastElementChild;$$('[data-picker]',tr).forEach(function(b){if(window.nPickerInit)window.nPickerInit(b)});
  return tr;
}
function wire(body,tplId,recalc){
  if(!body)return;
  body.addEventListener('input',function(e){if(e.target.hasAttribute('data-calc'))recalc()});
  body.addEventListener('change',function(e){if(e.target.matches('select[name$="[item_id]"]')){takeItem(e.target.closest('tr'),body.dataset.cur||'INR');recalc()}});
  body.addEventListener('click',function(e){
    var b=e.target.closest('[data-del-line]');if(!b)return;
    if(body.children.length===1){$$('input:not([type=hidden]),select',b.closest('tr')).forEach(function(x){x.value='';x.dispatchEvent(new Event('change'))});recalc();return}
    b.closest('tr').remove();renumber(body);recalc();
  });
  $$('[data-add-line="#'+body.id+'"]').forEach(function(btn){btn.addEventListener('click',function(){var tr=addRow(body,tplId);if(tr){var f=tr.querySelector('input:not([type=hidden])');if(f)f.focus()}})});
}

/* Items are sent as one JSON field, so a 200-item order stays within PHP's max_input_vars (as on the quotation). */
$$('form[data-lines-form]').forEach(function(form){
  var body=$(form.dataset.linesForm);if(!body)return;
  document.addEventListener('submit',function(e){
    if(e.target!==form||e.defaultPrevented)return;
    var rows=$$('tr',body).filter(function(tr){return tr.querySelector('[name^="lines["]')}).map(function(tr){var o={};$$('[name^="lines["]',tr).forEach(function(i){var m=i.name.match(/\]\[([a-z_]+)\]$/);if(m)o[m[1]]=i.value});return o});
    var h=form.querySelector('input[name=lines_json]');if(!h){h=document.createElement('input');h.type='hidden';h.name='lines_json';form.appendChild(h)}
    h.value=JSON.stringify(rows);
    $$('[name^="lines["]',body).forEach(function(i){i.disabled=true});
  });
  window.addEventListener('pageshow',function(){$$('[name^="lines["]',body).forEach(function(i){i.disabled=false});var h=form.querySelector('input[name=lines_json]');if(h)h.remove()});
});

/* Asking before typed rows are replaced (UX review M5). */
function typedRows(body){return $$('[name$="[quantity]"]',body).filter(function(i){return String(i.value).trim()!==''}).length}
function keepOrRevert(sel,body,what){
  var n=typedRows(body);
  if(n&&!window.confirm('Replace the '+n+' item'+(n===1?'':'s')+' you typed with '+what+'?')){
    sel.dataset.reverting='1';sel.value=sel.dataset.prev||'';sel.dispatchEvent(new Event('change',{bubbles:true}));delete sel.dataset.reverting;
    return false;
  }
  return true;
}
function remember(sel){if(sel)sel.dataset.prev=sel.value}

/* ---- Sales order: the item pickers follow the party; amounts and the order total while typing ---- */
var soForm=$('#orderForm'),soBody=$('#soLines'),soParty=$('#so_party_id'),soTotal=$('#soTotal');
function soCur(){var o=soParty&&soParty.selectedOptions[0];return (o&&o.dataset.cur)||(soForm&&soForm.dataset.currency)||'INR'}
function soRecalc(){if(!soBody)return;soBody.dataset.cur=soCur();var t=amounts(soBody,soCur());if(soTotal)soTotal.textContent=money(t)}
wire(soBody,'#soLineTpl',soRecalc);
if(soParty&&soBody){
  soParty.addEventListener('change',function(){
    if(soParty.dataset.reverting)return;
    var url=soBody.dataset.itemsUrl.replace(/([?&])party=\d*/,'$1').replace(/[?&]$/,'');
    url+=(url.indexOf('?')<0?'?':'&')+'party='+encodeURIComponent(soParty.value);
    soBody.dataset.itemsUrl=url;$$('.picker',soBody).forEach(function(p){p.dataset.url=url});
    if(soForm)soForm.dataset.currency=soCur();
    $$('tr',soBody).forEach(function(tr){takeItem(tr,soCur())});soRecalc();
  });
}
if(soBody){$$('tr',soBody).forEach(function(tr){var r=tr.querySelector('[name$="[rate]"]');if(r&&tr.dataset.price!=null)r.placeholder=(tr.dataset.cur||'INR')===soCur()?'Item price '+qty(num(tr.dataset.price)):'Rate in '+soCur()});soRecalc()}

/* ---- Tax invoice ---- */
var invForm=$('#invoiceForm'),invBody=$('#invLines'),invParty=$('#inv_party_id'),invPo=$('#inv_sales_order_id'),ship=$('#shipping_address'),invAdd=$('#invAdd'),invFill=$('#invFill'),pf=$('#pf_charges');
var lastPo=null,kept={};
/* 02/11/2026: the type is a dropdown (select#invoice_type) on a new invoice; a saved one keeps data-type on the form. */
var invTypeSel=$('select[name=invoice_type]');
function invType(){return invTypeSel&&invTypeSel.value?invTypeSel.value:((invForm&&invForm.dataset.type)||'Sales')}
function invCur(){return (invForm&&invForm.dataset.currency)||'INR'}

/* Totals while typing (UX review M1): the same rules as the server, each line's GST rounded to paise, P&F at the
   highest GST rate of the parts. */
var tot=$('#invTotals');
function invRecalc(){
  if(!invBody)return;
  invBody.dataset.cur=invCur();amounts(invBody,invCur());
  if(!tot)return;
  var supply=(invForm&&invForm.dataset.supply)||'',taxable=0,c=0,s=0,g=0,hi=null;
  $$('tr',invBody).forEach(function(tr){
    var q=tr.querySelector('[name$="[quantity]"]');if(!q)return;
    var v=r2(num(q.value)*rowRate(tr,invCur()));if(!v)return;taxable+=v;
    var rc=num(tr.dataset.cgst),rs=num(tr.dataset.sgst),ri=num(tr.dataset.igst);
    if(supply==='Intra-state'){c+=r2(v*rc/100);s+=r2(v*rs/100)}else if(supply==='Inter-state'){g+=r2(v*ri/100)}
    if(tr.dataset.igst!=null&&(!hi||ri>hi.i))hi={c:rc,s:rs,i:ri};
  });
  var p=r2(num(pf&&pf.value));
  if(p>0&&hi){if(supply==='Intra-state'){c+=r2(p*hi.c/100);s+=r2(p*hi.s/100)}else if(supply==='Inter-state'){g+=r2(p*hi.i/100)}}
  taxable=r2(taxable+p);c=r2(c);s=r2(s);g=r2(g);
  var set=function(k,v){var e=tot.querySelector('[data-t="'+k+'"]');if(e)e.textContent=v};
  set('taxable',money(taxable));set('cgst',money(c));set('sgst',money(s));set('igst',money(g));set('grand',money(r2(taxable+c+s+g)));
  set('cur',invCur()!=='INR'?invCur()+' ':'₹');
  var pfNote=tot.querySelector('[data-pf-note]');if(pfNote)pfNote.hidden=!(p>0);
  var kind=supply==='Intra-state'?'intra':supply==='Inter-state'?'inter':supply==='Export'?'export':'';
  $$('[data-row]',tot).forEach(function(e){e.hidden=e.dataset.row!==kind});
  set('note',(supply?'':'Choose the party to see GST. ')+'Worked out again on save.');
}
wire(invBody,'#invLineTpl',invRecalc);
if(pf)pf.addEventListener('input',invRecalc);

function setItemsUrl(url){if(!invBody)return;invBody.dataset.itemsUrl=url;$$('.picker',invBody).forEach(function(p){p.dataset.url=url})}
function freeRows(){
  if(!invBody)return;kept={};invBody.dataset.mode='free';invBody.innerHTML='';addRow(invBody,'#invLineTpl');if(invAdd)invAdd.hidden=false;if(invFill)invFill.hidden=true;invRecalc();
}
/* PO parts (A21). Quantity starts empty with the most allowed as the hint (UX review M2); a part with nothing left
   for this document shows as text with no box (M3). keep: quantities typed before, by PO line. */
function poRows(lines,keep){
  invBody.dataset.mode='po';if(invAdd)invAdd.hidden=true;keep=keep||{};
  var type=invType(),open=0;
  invBody.innerHTML=lines.map(function(l){
    var max=type==='Sales'?l.balance:type==='Credit'?l.credit_room:l.invoiced;
    if(!(max>0)){
      var why=type==='Sales'?'fully billed':type==='Credit'?'nothing left to credit':'nothing invoiced yet';
      return '<tr class="podone"><td data-label="Item" class="wide"><span>'+esc(l.label)+'</span></td><td data-label="Quantity" colspan="5" class="cap muted">'+why+'</td></tr>';
    }
    var i=open++,v=keep[l.sales_order_line_id]!=null?keep[l.sales_order_line_id]:'';
    return '<tr data-max="'+max+'"'+dataAttrs(l.data)+'><td data-label="Item" class="wide"><input type="hidden" name="lines['+i+'][sales_order_line_id]" value="'+l.sales_order_line_id+'"><input type="hidden" name="lines['+i+'][item_id]" value="'+l.item_id+'"><span>'+esc(l.label)+'</span></td>'+
      '<td data-label="Quantity"><input class="inp num" name="lines['+i+'][quantity]" value="'+esc(v)+'" placeholder="Max '+qty(max)+'" inputmode="decimal" data-calc aria-label="Quantity, item '+(i+1)+', at most '+qty(max)+'"></td>'+
      '<td data-label="Rate"><input class="inp num ro" name="lines['+i+'][rate]" value="'+l.rate+'" readonly aria-label="Rate from the sales order, item '+(i+1)+'"><span class="hint txt">From the sales order</span></td>'+
      '<td class="r num" data-label="Amount" data-amount></td>'+
      '<td data-label="PO balance" class="cap muted">Max '+qty(max)+' '+esc(l.uom||'')+(type==='Credit'?' (invoiced less credited)':type==='Debit'?' (invoiced)':'')+'</td>'+
      '<td class="del"></td></tr>';
  }).join('');
  if(invFill)invFill.hidden=!(type==='Sales'&&open>0);
  invRecalc();
}
/* Quantities typed per PO line, kept across type changes even while a line is shown without a box. */
function typedByLine(){$$('tr',invBody).forEach(function(tr){var id=tr.querySelector('[name$="[sales_order_line_id]"]'),q=tr.querySelector('[name$="[quantity]"]');if(id&&q)kept[id.value]=q.value});return kept}
function loadPo(keep){
  if(!invPo||!invBody)return;
  if(!invPo.value){lastPo=null;freeRows();return}
  getJson(invPo.dataset.linesUrl.replace('__ID__',encodeURIComponent(invPo.value))).then(function(d){if(d){lastPo=d;poRows(d,keep)}});
}
if(invFill)invFill.addEventListener('click',function(){$$('tr[data-max]',invBody).forEach(function(tr){var q=tr.querySelector('[name$="[quantity]"]');if(q&&!q.readOnly)q.value=tr.dataset.max});invRecalc()});
remember(invParty);remember(invPo);
/* 02/11/2026: PO no lists the chosen party's approved orders; with no party it is emptied, with none it says so. */
function setPoOptions(orders,party){
  if(!invPo)return;
  var blank=party&&!orders.length?'No approved sales order for this party.':'No PO selected';
  invPo.innerHTML='<option value="">'+esc(blank)+'</option>'+orders.map(function(o){return '<option value="'+o.id+'">'+esc(o.label)+'</option>'}).join('');
  invPo.dataset.prev='';
  var none=$('#invPoNone');if(none)none.hidden=!(party&&!orders.length);
}
if(invParty)invParty.addEventListener('change',function(){
  if(invParty.dataset.reverting)return;
  if(!invParty.value){var hadPo=invPo&&invPo.value;setPoOptions([],false);invParty.dataset.prev='';if(hadPo){lastPo=null;freeRows()}return}
  if(!keepOrRevert(invParty,invBody,'the parts of the new party'))return;
  invParty.dataset.prev=invParty.value;
  var o=invParty.selectedOptions[0];
  if(o&&o.dataset.supply!=null&&invForm){invForm.dataset.supply=o.dataset.supply;invForm.dataset.currency=o.dataset.cur||'INR'}
  getJson(invParty.dataset.infoUrl.replace('__ID__',encodeURIComponent(invParty.value))).then(function(d){
    if(!d)return;
    if(invForm){invForm.dataset.supply=d.supply||'';invForm.dataset.currency=d.currency||'INR'}
    if(ship&&(!ship.value||ship.dataset.auto==='1')){ship.value=d.shipping_address||'';ship.dataset.auto='1'}
    setPoOptions(d.orders||[],true);
    setItemsUrl(d.items_url);freeRows();
  });
});
if(ship)ship.addEventListener('input',function(){ship.dataset.auto='0'});
if(invPo)invPo.addEventListener('change',function(){
  if(invPo.dataset.reverting)return;
  if(!keepOrRevert(invPo,invBody,invPo.value?'the parts of this PO':'an empty row'))return;
  invPo.dataset.prev=invPo.value;
  kept={};loadPo({});
});
/* Changing only the type keeps what was typed; the limits and hints follow the type (UX review M5). */
if(invTypeSel)invTypeSel.addEventListener('change',function(){
  if(invPo&&invPo.value){var keep=typedByLine();if(lastPo)poRows(lastPo,keep);else loadPo(keep)}else invRecalc();
});
/* Opened from "Make tax invoice": the PO is chosen but its parts are not loaded yet. After a failed save the typed rows
   are already on the page, so they stay and only the PO's data is fetched for a type change. */
if(invPo&&invPo.value&&invBody){
  if(!$('[name$="[sales_order_line_id]"]',invBody))loadPo({});
  else getJson(invPo.dataset.linesUrl.replace('__ID__',encodeURIComponent(invPo.value))).then(function(d){if(d)lastPo=d});
}
invRecalc();

/* ---- Sales order card: opened from the menu, the page header link or after a sales order action ---- */
var soCard=$('#soCard');
function openSo(focus){if(!soCard)return;soCard.open=true;soCard.scrollIntoView({block:'start'});if(focus){var s=soCard.querySelector('summary');if(s)s.focus({preventScroll:true})}}
if(soCard&&location.hash==='#soCard')soCard.open=true;
if(soCard&&soCard.hasAttribute('data-focus'))openSo(true);
$$('[data-go-so]').forEach(function(a){a.addEventListener('click',function(e){e.preventDefault();openSo(true);if(history.replaceState)history.replaceState(null,'','#soCard')})});
})();
