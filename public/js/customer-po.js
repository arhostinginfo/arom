/* CRM › PO / Agreement page (items 29/10/2026): the item rows from the Item master (add, remove, an item picked fills
   description, UOM and the price as the rate), line amounts and the items total, the PO value (the sum of the items while
   there are items, typed otherwise), the item pickers following the quotation, and the items sent as one JSON field.
   The server checks and works out everything again on save; this only helps while typing.
   Separate PO menu removed (02/11/2026): no Customer field any more, so the items always follow the quotation. */
(function(){
'use strict';
var $=function(s,r){return (r||document).querySelector(s)}, $$=function(s,r){return [].slice.call((r||document).querySelectorAll(s))};
function num(v){var n=parseFloat(String(v||'').replace(/[, ]/g,''));return isFinite(n)?n:0}
function r2(n){return Math.round((n+Number.EPSILON)*100)/100}
function money(n){return n.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})}
function plain(n){return String(r2(n))}
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}

var form=$('#poForm'),body=$('#poLines'),box=$('#poItems');
if(!form||!body||!box)return;
var party=$('#quotation_id[data-items-url]'),value=$('#po_value'),valueHint=$('#poValueHint'),valueReq=$('#poValueReq'),total=$('#poLinesTotal');

/* The item pickers list the chosen quotation's customer's items. */
function itemsUrl(){
  if(!party||!party.dataset.itemsUrl)return body.dataset.itemsUrl||'';
  return party.dataset.itemsUrl.replace('__ID__',encodeURIComponent(party.value||'0'));
}
function setItemsUrl(){var u=itemsUrl();body.dataset.itemsUrl=u;$$('.picker',body).forEach(function(p){p.dataset.url=u})}

function rowRate(tr){var r=tr.querySelector('[name$="[rate]"]');if(r&&String(r.value).trim()!=='')return num(r.value);return tr.dataset.price!=null?num(tr.dataset.price):0}
/* With items the PO value is their sum (read-only); with none it is typed, and the typed value comes back. */
function hasItems(){return !box.hidden&&$$('select[name$="[item_id]"]',body).some(function(s){return s.value})}
function recalc(){
  var sum=0;
  $$('tr',body).forEach(function(tr){
    var q=tr.querySelector('[name$="[quantity]"]'),cell=tr.querySelector('[data-amount]');if(!q||!cell)return;
    var v=r2(num(q.value)*rowRate(tr));cell.textContent=v?money(v):'';sum+=v;
  });
  sum=r2(sum);if(total)total.textContent=money(sum);
  if(!value)return;
  var items=hasItems();
  if(items&&value.dataset.typed==null)value.dataset.typed=value.value;
  if(!items&&value.dataset.typed!=null){value.value=value.dataset.typed;delete value.dataset.typed}
  if(items)value.value=sum?plain(sum):'';
  value.readOnly=items;if(items)value.tabIndex=-1;else value.removeAttribute('tabindex');
  if(valueReq)valueReq.hidden=items;
  if(valueHint&&!box.hidden)valueHint.textContent=items?'Sum of the items, worked out again on save.':'Enter the value, or add items: with items it is their sum.';
}

/* Item picked: copy its price, UOM and description onto the row (the description only while not typed over). */
function takeItem(tr){
  var sel=tr.querySelector('select[name$="[item_id]"]');if(!sel)return;
  var o=sel.selectedOptions&&sel.selectedOptions[0];if(!o||!o.value)return;
  ['price','uom','desc'].forEach(function(k){if(o.dataset[k]!=null)tr.dataset[k]=o.dataset[k]});
  var u=tr.querySelector('select[name$="[uom_id]"]'),d=tr.querySelector('[name$="[description]"]'),r=tr.querySelector('[name$="[rate]"]');
  if(u&&tr.dataset.uom)u.value=tr.dataset.uom;
  if(d&&(!d.value||d.dataset.auto==='1')&&tr.dataset.desc!=null){d.value=tr.dataset.desc;d.dataset.auto='1'}
  /* The Item master price is the default rate; it stays editable. */
  if(r&&(!r.value||r.dataset.auto==='1')&&tr.dataset.price!=null){r.value=tr.dataset.price;r.dataset.auto='1'}
}


function renumber(){$$('tr',body).forEach(function(tr,i){$$('[name^="lines["]',tr).forEach(function(inp){inp.name=inp.name.replace(/^lines\[[^\]]*\]/,'lines['+i+']')})})}
/* Tester review (29/10/2026): ids of new rows never repeat after a row is removed (the name index is renumbered). */
var seq=body.children.length;
function addRow(){
  var tpl=$('#poLineTpl'),i=body.children.length;if(!tpl)return null;
  body.insertAdjacentHTML('beforeend',tpl.innerHTML.replace(/cpo_item___i__/g,'cpo_item_n'+(seq++)).replace(/__i__/g,i).replace(/__n__/g,i+1).replace(/__URL__/g,esc(body.dataset.itemsUrl||'')));
  var tr=body.lastElementChild;$$('[data-picker]',tr).forEach(function(b){if(window.nPickerInit)window.nPickerInit(b)});
  return tr;
}
body.addEventListener('input',function(e){
  if(e.target.hasAttribute('data-calc'))recalc();
  if(e.target.matches('[name$="[description]"],[name$="[rate]"]'))e.target.dataset.auto='0';
});
body.addEventListener('change',function(e){
  if(e.target.matches('select[name$="[item_id]"]')){takeItem(e.target.closest('tr'));recalc()}
  else if(e.target.hasAttribute('data-calc'))recalc();
});
body.addEventListener('click',function(e){
  var b=e.target.closest('[data-del-line]');if(!b)return;
  if(body.children.length===1){
    var tr=b.closest('tr');$$('input:not([type=hidden]),select',tr).forEach(function(x){x.value='';x.dispatchEvent(new Event('change'))});
    $$('input[type=hidden]',tr).forEach(function(x){x.remove()});['price','uom','desc'].forEach(function(k){delete tr.dataset[k]});recalc();return;
  }
  b.closest('tr').remove();renumber();recalc();
});
$$('[data-add-line="#poLines"]').forEach(function(btn){btn.addEventListener('click',function(){var tr=addRow();if(tr){var f=tr.querySelector('button,input:not([type=hidden])');if(f)f.focus()}})});

if(party)party.addEventListener('change',setItemsUrl);

/* Items are sent as one JSON field, so a 200-item PO stays within PHP's max_input_vars (as on the Purchase order page).
   Items hidden in Page setup send none. */
document.addEventListener('submit',function(e){
  if(e.target!==form||e.defaultPrevented)return;
  $$('[name^="lines["]',body).forEach(function(i){i.disabled=true});
  if(box.hidden)return;
  var rows=$$('tr',body).map(function(tr){var o={};$$('[name^="lines["]',tr).forEach(function(i){var m=i.name.match(/\]\[([a-z_]+)\]$/);if(m)o[m[1]]=i.value});return o});
  var h=form.querySelector('input[name=lines_json]');if(!h){h=document.createElement('input');h.type='hidden';h.name='lines_json';form.appendChild(h)}
  h.value=JSON.stringify(rows);
});
window.addEventListener('pageshow',function(){$$('[name^="lines["]',body).forEach(function(i){i.disabled=false});var h=form.querySelector('input[name=lines_json]');if(h)h.remove()});

/* Tester review (29/10/2026): on rows shown from the server (a saved PO, or after an error), a description or rate that is
   still the item's own follows when another item is picked, as on a new row; one typed over stays. */
$$('tr',body).forEach(function(tr){
  var d=tr.querySelector('[name$="[description]"]'),r=tr.querySelector('[name$="[rate]"]');
  if(d&&tr.dataset.desc!=null&&d.value===tr.dataset.desc)d.dataset.auto='1';
  if(r&&tr.dataset.price!=null&&String(r.value).trim()!==''&&num(r.value)===num(tr.dataset.price))r.dataset.auto='1';
});

setItemsUrl();recalc();
})();
