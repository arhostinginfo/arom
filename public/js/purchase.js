/* Purchase order page: item rows (add, remove, item picked fills description, UOM, tax and the price hint), line
   amounts and the GST totals while typing, the supplier's details, "Show all headings", and the lines sent as one JSON
   field. The server checks and works out everything again on save; this only helps while typing. */
(function(){
'use strict';
var $=function(s,r){return (r||document).querySelector(s)}, $$=function(s,r){return [].slice.call((r||document).querySelectorAll(s))};
function num(v){var n=parseFloat(String(v||'').replace(/[, ]/g,''));return isFinite(n)?n:0}
function r2(n){return Math.round((n+Number.EPSILON)*100)/100}
function money(n){return n.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})}
function qty(n){return n.toLocaleString('en-IN',{maximumFractionDigits:3})}
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
function getJson(url){return fetch(url,{headers:{'Accept':'application/json'},credentials:'same-origin'}).then(function(r){return r.ok?r.json():null}).catch(function(){return null})}

var form=$('#poForm'),body=$('#poLines'),party=$('#party_id'),cur=$('#currency_id'),terms=$('#payment_terms'),tot=$('#poTotals');
if(!form||!body)return;

/* "Show all headings" (PURCHASE B11): the short view shows only the party; this shows the other headings. */
var hdBtn=$('#poHeadings'),hd=$('#allHd');
if(hdBtn&&hd)hdBtn.addEventListener('click',function(){var open=hd.hidden;hd.hidden=!open;hdBtn.textContent=open?'Hide headings':'Show all headings';hdBtn.setAttribute('aria-expanded',String(open))});

function poCur(){var o=cur&&cur.selectedOptions[0];return (o&&o.dataset.code)||form.dataset.currency||'INR'}
function supply(){return form.dataset.supply||''}

/* The rate a row is worked out at: the typed rate, or (blank) the item price when it is in the PO's currency. */
function rowRate(tr){
  var r=tr.querySelector('[name$="[rate]"]');if(r&&String(r.value).trim()!=='')return num(r.value);
  if(tr.dataset.price!=null&&(tr.dataset.cur||'INR')===poCur())return num(tr.dataset.price);
  return 0;
}
/* GST rates of the row: its Tax drop-down, or (blank) the item's tax structure. */
function rowTax(tr){
  var s=tr.querySelector('select[name$="[tax_structure_id]"]');if(!s)return null;
  var o=s.value?s.selectedOptions[0]:(tr.dataset.tax?s.querySelector('option[value="'+tr.dataset.tax+'"]'):null);
  return o&&o.value?{c:num(o.dataset.c),s:num(o.dataset.s),i:num(o.dataset.i)}:null;
}
function hint(tr){
  var r=tr.querySelector('[name$="[rate]"]');if(!r||tr.dataset.price==null)return;
  r.placeholder=(tr.dataset.cur||'INR')===poCur()?'Item price '+qty(num(tr.dataset.price)):'Rate in '+poCur();
}

function recalc(){
  var taxable=0,c=0,s=0,g=0,sup=supply();
  $$('tr',body).forEach(function(tr){
    var q=tr.querySelector('[name$="[quantity]"]'),d=tr.querySelector('[name$="[discount_percent]"]'),cell=tr.querySelector('[data-amount]');
    if(!q||!cell)return;
    var v=r2(num(q.value)*rowRate(tr)*(100-Math.min(100,num(d&&d.value)))/100);
    cell.textContent=v?money(v):'';taxable+=v;
    var t=rowTax(tr);if(!v||!t)return;
    if(sup==='Intra-state'){c+=r2(v*t.c/100);s+=r2(v*t.s/100)}else if(sup==='Inter-state'){g+=r2(v*t.i/100)}
  });
  if(!tot)return;
  taxable=r2(taxable);c=r2(c);s=r2(s);g=r2(g);
  var set=function(k,v){var e=tot.querySelector('[data-t="'+k+'"]');if(e)e.textContent=v};
  set('taxable',money(taxable));set('cgst',money(c));set('sgst',money(s));set('igst',money(g));set('grand',money(r2(taxable+c+s+g)));
  set('cur',poCur()!=='INR'?poCur()+' ':'₹');
  var kind=sup==='Intra-state'?'intra':sup==='Inter-state'?'inter':sup==='Import'?'import':'';
  $$('[data-row]',tot).forEach(function(e){e.hidden=e.dataset.row!==kind});
  set('note',(sup?'':'Choose the supplier to see GST. ')+'Worked out again on save.');
}

/* Item picked: copy its price, currency and defaults onto the row; fill UOM, tax and description when not chosen. */
function takeItem(tr){
  var sel=tr.querySelector('select[name$="[item_id]"]');if(!sel)return;
  var o=sel.selectedOptions&&sel.selectedOptions[0];if(!o||!o.value)return;
  ['price','cur','uom','tax','desc'].forEach(function(k){if(o.dataset[k]!=null)tr.dataset[k]=o.dataset[k]});
  var u=tr.querySelector('select[name$="[uom_id]"]'),t=tr.querySelector('select[name$="[tax_structure_id]"]'),d=tr.querySelector('[name$="[description]"]');
  if(u&&tr.dataset.uom)u.value=tr.dataset.uom;
  if(t&&tr.dataset.tax)t.value=tr.dataset.tax;
  if(d&&(!d.value||d.dataset.auto==='1')&&tr.dataset.desc!=null){d.value=tr.dataset.desc;d.dataset.auto='1'}
  hint(tr);
}

function renumber(){$$('tr',body).forEach(function(tr,i){$$('[name^="lines["]',tr).forEach(function(inp){inp.name=inp.name.replace(/^lines\[[^\]]*\]/,'lines['+i+']')})})}
function addRow(){
  var tpl=$('#poLineTpl'),i=body.children.length;if(!tpl)return null;
  body.insertAdjacentHTML('beforeend',tpl.innerHTML.replace(/__i__/g,i).replace(/__n__/g,i+1).replace(/__URL__/g,esc(body.dataset.itemsUrl||'')));
  var tr=body.lastElementChild;$$('[data-picker]',tr).forEach(function(b){if(window.nPickerInit)window.nPickerInit(b)});
  return tr;
}
body.addEventListener('input',function(e){if(e.target.hasAttribute('data-calc'))recalc();if(e.target.matches('[name$="[description]"]'))e.target.dataset.auto='0'});
body.addEventListener('change',function(e){
  if(e.target.matches('select[name$="[item_id]"]')){takeItem(e.target.closest('tr'));recalc()}
  else if(e.target.hasAttribute('data-calc'))recalc();
});
body.addEventListener('click',function(e){
  var b=e.target.closest('[data-del-line]');if(!b)return;
  if(body.children.length===1){$$('input:not([type=hidden]),select',b.closest('tr')).forEach(function(x){x.value='';x.dispatchEvent(new Event('change'))});recalc();return}
  b.closest('tr').remove();renumber();recalc();
});
$$('[data-add-line="#poLines"]').forEach(function(btn){btn.addEventListener('click',function(){var tr=addRow();if(tr){var f=tr.querySelector('input:not([type=hidden])');if(f)f.focus()}})});

/* Items are sent as one JSON field, so a 200-item PO stays within PHP's max_input_vars (as on the Sales page). */
document.addEventListener('submit',function(e){
  if(e.target!==form||e.defaultPrevented)return;
  var rows=$$('tr',body).filter(function(tr){return tr.querySelector('[name^="lines["]')}).map(function(tr){var o={};$$('[name^="lines["]',tr).forEach(function(i){var m=i.name.match(/\]\[([a-z_]+)\]$/);if(m)o[m[1]]=i.value});return o});
  var h=form.querySelector('input[name=lines_json]');if(!h){h=document.createElement('input');h.type='hidden';h.name='lines_json';form.appendChild(h)}
  h.value=JSON.stringify(rows);
  $$('[name^="lines["]',body).forEach(function(i){i.disabled=true});
});
window.addEventListener('pageshow',function(){$$('[name^="lines["]',body).forEach(function(i){i.disabled=false});var h=form.querySelector('input[name=lines_json]');if(h)h.remove()});

/* Supplier picked: GST split, currency and payment terms from the Party master; the item pickers list its items. */
if(party)party.addEventListener('change',function(){
  if(!party.value||!party.dataset.infoUrl)return;
  getJson(party.dataset.infoUrl.replace('__ID__',encodeURIComponent(party.value))).then(function(d){
    if(!d)return;
    form.dataset.supply=d.supply||'';form.dataset.currency=d.currency||'INR';
    if(cur&&d.currency_id)cur.value=String(d.currency_id);
    if(terms&&(!terms.value||terms.dataset.auto==='1')&&d.payment_terms!=null){terms.value=d.payment_terms||'';terms.dataset.auto='1'}
    body.dataset.itemsUrl=d.items_url;$$('.picker',body).forEach(function(p){p.dataset.url=d.items_url});
    $$('tr',body).forEach(hint);recalc();
  });
});
if(terms)terms.addEventListener('input',function(){terms.dataset.auto='0'});
if(cur)cur.addEventListener('change',function(){$$('tr',body).forEach(hint);recalc()});

$$('tr',body).forEach(hint);
recalc();
})();
