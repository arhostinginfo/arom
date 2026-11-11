/* Stock transfer page: item rows, the stock at the source and UOM of each item, "Location from and to are the same" and
   "Quantity is more than the stock at the source" while typing. The server checks everything again on save and once
   more, under a lock, when the transfer is approved; this only helps while typing. */
(function(){
'use strict';
var $=function(s,r){return (r||document).querySelector(s)}, $$=function(s,r){return [].slice.call((r||document).querySelectorAll(s))};
function num(v){var n=parseFloat(String(v||'').replace(/[, ]/g,''));return isFinite(n)?n:0}
function qty(n){return n.toLocaleString('en-IN',{maximumFractionDigits:3})}
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}

var form=$('#transferForm'),body=$('#stLines'),from=$('#from_unit_id'),to=$('#to_unit_id'),same=$('#stSameUnit');
if(!form||!body)return;
var check=!!body.dataset.check;

/* One row: show the stock and UOM of the item picked, and flag a quantity above the stock. */
function paint(tr){
  var sel=tr.querySelector('select[name$="[item_id]"]'),o=sel&&sel.selectedOptions&&sel.selectedOptions[0];
  var has=o&&o.value,cell=tr.querySelector('[data-stock-cell]'),uom=tr.querySelector('[data-uom-cell]'),q=tr.querySelector('[data-qty]'),over=tr.querySelector('[data-over]');
  if(has&&o.dataset.stock!=null&&o.dataset.stock!==''){tr.dataset.stock=o.dataset.stock;delete tr.dataset.unknown}
  if(!has)delete tr.dataset.stock;
  var s=tr.dataset.stock!=null?num(tr.dataset.stock):null;
  if(cell)cell.textContent=has&&s!==null?qty(s):(has&&tr.dataset.unknown?'stock unknown':'');
  if(uom)uom.textContent=has?(o.dataset.uom||uom.textContent||''):'';
  if(q&&check)q.placeholder=has&&s!==null?'Max '+qty(s):'';
  var bad=check&&has&&s!==null&&String(q&&q.value).trim()!==''&&num(q.value)>s+0.0005;
  if(over)over.hidden=!bad;
  if(q)q.classList.toggle('err',bad);
}
function paintAll(){$$('tr',body).forEach(paint)}
function sameCheck(){if(!same||!from||!to)return;var b=from.value&&to.value&&from.value===to.value;same.hidden=!b;to.classList.toggle('err',!!b)}

function renumber(){$$('tr',body).forEach(function(tr,i){$$('[name^="lines["]',tr).forEach(function(inp){inp.name=inp.name.replace(/^lines\[[^\]]*\]/,'lines['+i+']')})})}
function addRow(){
  var tpl=$('#stLineTpl'),i=body.children.length;if(!tpl)return null;
  body.insertAdjacentHTML('beforeend',tpl.innerHTML.replace(/__i__/g,i).replace(/__n__/g,i+1).replace(/__URL__/g,esc(body.dataset.itemsUrl||'')));
  var tr=body.lastElementChild;$$('[data-picker]',tr).forEach(function(b){if(window.nPickerInit)window.nPickerInit(b)});
  return tr;
}
body.addEventListener('input',function(e){if(e.target.hasAttribute('data-qty'))paint(e.target.closest('tr'))});
body.addEventListener('change',function(e){if(e.target.matches('select[name$="[item_id]"]'))paint(e.target.closest('tr'))});
body.addEventListener('click',function(e){
  var b=e.target.closest('[data-del-line]');if(!b)return;
  if(body.children.length===1){$$('input:not([type=hidden]),select',b.closest('tr')).forEach(function(x){x.value='';x.dispatchEvent(new Event('change',{bubbles:true}))});return}
  b.closest('tr').remove();renumber();
});
$$('[data-add-line="#stLines"]').forEach(function(btn){btn.addEventListener('click',function(){var tr=addRow();if(tr){var f=tr.querySelector('button,input:not([type=hidden])');if(f)f.focus()}})});

/* Location from changes: the item pickers list the new source's stock, and the rows on screen are refreshed. */
if(from&&from.tagName==='SELECT'){
  from.addEventListener('change',function(){
    sameCheck();
    var url=body.dataset.itemsUrl.replace(/([?&])from=\d*/,'$1').replace(/[?&]$/,'');
    if(from.value)url+=(url.indexOf('?')<0?'?':'&')+'from='+encodeURIComponent(from.value);
    body.dataset.itemsUrl=url;$$('.picker',body).forEach(function(p){p.dataset.url=url});
    var ids=$$('select[name$="[item_id]"]',body).map(function(s){return s.value}).filter(Boolean);
    if(!from.value||!ids.length){$$('tr',body).forEach(function(tr){delete tr.dataset.stock;var o=tr.querySelector('select option:checked');if(o)o.dataset.stock=''});paintAll();return}
    fetch(form.dataset.stockUrl+'?unit='+encodeURIComponent(from.value)+'&items='+ids.join(','),{headers:{'Accept':'application/json'},credentials:'same-origin'})
      .then(function(r){return r.ok?r.json():null}).catch(function(){return null})
      .then(function(map){$$('tr',body).forEach(function(tr){var s=tr.querySelector('select[name$="[item_id]"]');if(!s||!s.value)return;var o=s.selectedOptions[0];
        /* The stock could not be read: say so and do not warn, rather than showing 0. */
        if(!map){delete tr.dataset.stock;tr.dataset.unknown='1';if(o)o.dataset.stock='';return}
        delete tr.dataset.unknown;var v=map[s.value]!=null?String(map[s.value]):'0';tr.dataset.stock=v;if(o)o.dataset.stock=v});paintAll()});
  });
}
if(to)to.addEventListener('change',sameCheck);

/* Items are sent as one JSON field, as on the Sales page. */
document.addEventListener('submit',function(e){
  if(e.target!==form||e.defaultPrevented)return;
  var rows=$$('tr',body).filter(function(tr){return tr.querySelector('[name^="lines["]')}).map(function(tr){var o={};$$('[name^="lines["]',tr).forEach(function(i){var m=i.name.match(/\]\[([a-z_]+)\]$/);if(m)o[m[1]]=i.value});return o});
  var h=form.querySelector('input[name=lines_json]');if(!h){h=document.createElement('input');h.type='hidden';h.name='lines_json';form.appendChild(h)}
  h.value=JSON.stringify(rows);
  $$('[name^="lines["]',body).forEach(function(i){i.disabled=true});
});
window.addEventListener('pageshow',function(){$$('[name^="lines["]',body).forEach(function(i){i.disabled=false});var h=form.querySelector('input[name=lines_json]');if(h)h.remove()});

sameCheck();paintAll();
})();
