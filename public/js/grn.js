/* GRN entry page: item rows (PO no → its pending items, quantity max, PO rate and UOM; or the supplier's items for a
   credit / debit note), the amount match box while typing (supplier invoice amount against qty × rate + GST, ±tolerance),
   "Show all headings", the supplier's open POs and GST split, the customer's sales orders, and the lines sent as one
   JSON field. The server checks and works out everything again on save; this only helps while typing. */
(function(){
'use strict';
var $=function(s,r){return (r||document).querySelector(s)}, $$=function(s,r){return [].slice.call((r||document).querySelectorAll(s))};
function num(v){var n=parseFloat(String(v||'').replace(/[, ₹]/g,''));return isFinite(n)?n:0}
function r2(n){return Math.round((n+Number.EPSILON)*100)/100}
function money(n){return n.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})}
function qty(n){return n.toLocaleString('en-IN',{maximumFractionDigits:3})}
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
function getJson(url){return fetch(url,{headers:{'Accept':'application/json'},credentials:'same-origin'}).then(function(r){return r.ok?r.json():null}).catch(function(){return null})}

var form=$('#grnForm'),body=$('#grnLines'),party=$('#party_id'),amt=$('#invoice_amount'),box=$('#grnMatch');
if(!form||!body)return;
var orders=[];try{orders=JSON.parse(($('#grnOrders')||{}).textContent||'[]')||[]}catch(e){orders=[]}
var withoutPo=!!form.dataset.withoutPo,check=!!form.dataset.check,tolRaw=parseFloat(form.dataset.tolerance),tol=isFinite(tolRaw)?tolRaw:1;
/* The supplier's purchase items for credit / debit notes and lines without a PO: null until the supplier changes on this page. */
var items=null;
function itemOptions(sel){return '<option value="">Select</option>'+(items||[]).map(function(i){return '<option value="'+i.id+'" data-rate="'+i.rate+'" data-uom="'+esc(i.uom||'')+'" data-c="'+i.c+'" data-s="'+i.s+'" data-i="'+i.i+'"'+(String(i.id)===String(sel)?' selected':'')+'>'+esc(i.label)+'</option>'}).join('')}

/* "Show all headings": the short view shows the five supplier invoice fields; this shows the others. */
var hdBtn=$('#grnHeadings'),hd=$('#allHd');
if(hdBtn&&hd)hdBtn.addEventListener('click',function(){var open=hd.hidden;hd.hidden=!open;hdBtn.textContent=open?'Hide headings':'Show all headings';hdBtn.setAttribute('aria-expanded',String(open))});

/* Invoice type is a drop-down now, or a hidden field once matched (02/11/2026). */
function invType(){var r=$('[name=invoice_type]',form);return r&&r.value?r.value:'Purchase'}
function order(id){for(var i=0;i<orders.length;i++)if(String(orders[i].id)===String(id))return orders[i];return null}
function poOptions(sel){return '<option value="">'+(withoutPo?'No PO':'Select')+'</option>'+orders.map(function(o){return '<option value="'+o.id+'"'+(String(o.id)===String(sel)?' selected':'')+'>'+esc(o.no)+'</option>'}).join('')}
function lineOptions(po,sel){
  var o=order(po);if(!o)return '<option value="">Choose the PO no first</option>';
  return '<option value="">Select</option>'+o.lines.map(function(l){return '<option value="'+l.id+'" data-rate="'+l.rate+'" data-pending="'+l.pending+'" data-uom="'+esc(l.uom||'')+'" data-c="'+l.c+'" data-s="'+l.s+'" data-i="'+l.i+'"'+(String(l.id)===String(sel)?' selected':'')+'>'+esc(l.label)+'</option>'}).join('');
}
function show(el,on){if(!el)return;el.hidden=!on;el.disabled=!on}

/* Which picker a row uses: a PO line (Purchase, with a PO no or when lines need a PO) or an item (credit / debit note, or no PO). */
function mode(tr){
  var po=tr.querySelector('[data-po]'),pol=tr.querySelector('[data-pol]'),it=tr.querySelector('[data-item]'),np=tr.querySelector('[data-nopo]');
  var purchase=invType()==='Purchase',usePol=purchase&&(!withoutPo||(po&&po.value));
  show(po,purchase);if(np)np.hidden=purchase;
  show(pol,usePol);show(it,!usePol);
}
function picked(tr){
  var pol=tr.querySelector('[data-pol]'),it=tr.querySelector('[data-item]');
  var s=pol&&!pol.disabled?pol:it;var o=s&&s.selectedOptions&&s.selectedOptions[0];
  return o&&o.value?o:null;
}
function paint(tr){
  var o=picked(tr),rate=tr.querySelector('[name$="[rate]"]'),q=tr.querySelector('[name$="[quantity]"]'),uom=tr.querySelector('[data-uom-cell]'),mx=tr.querySelector('[data-max]');
  if(uom)uom.textContent=o?(o.dataset.uom||''):'';
  if(rate)rate.placeholder=o&&o.dataset.pending!=null?'PO rate '+qty(num(o.dataset.rate)):(o?'Item price '+qty(num(o.dataset.rate)):'Rate');
  var max=check&&o&&o.dataset.pending!=null?num(o.dataset.pending):null;
  if(mx){mx.hidden=max===null;var t=mx.querySelector('span');if(t)t.textContent=max===null?'':'Max '+qty(max)+' (PO qty pending)'}
  if(q){var bad=max!==null&&String(q.value).trim()!==''&&num(q.value)>max+0.0005;q.classList.toggle('err',bad)}
}

function recalc(){
  var taxable=0,gst=0,sup=form.dataset.supply||'';
  $$('tr',body).forEach(function(tr){
    var q=tr.querySelector('[name$="[quantity]"]'),r=tr.querySelector('[name$="[rate]"]'),cell=tr.querySelector('[data-amount]'),o=picked(tr);
    if(!q||!cell)return;
    var rate=r&&String(r.value).trim()!==''?num(r.value):(o?num(o.dataset.rate):0);
    var v=r2(num(q.value)*rate);cell.textContent=v?money(v):'';taxable+=v;
    if(!v||!o)return;
    if(sup==='Intra-state')gst+=r2(v*num(o.dataset.c)/100)+r2(v*num(o.dataset.s)/100);
    else if(sup==='Inter-state')gst+=r2(v*num(o.dataset.i)/100);
  });
  if(!box)return;
  taxable=r2(taxable);gst=r2(gst);
  var grand=r2(taxable+gst),inv=num(amt&&amt.value),diff=r2(inv-grand),ok=inv>0&&Math.abs(diff)<=tol+0.000001;
  var set=function(k,v){$$('[data-t="'+k+'"]',box).forEach(function(e){e.textContent=v})};
  set('invoice',money(inv));set('taxable',money(taxable));set('gst',money(gst));set('grand',money(grand));set('diff',money(diff));
  var d=$('[data-t="diff"]',box);if(d)d.style.color=ok?'var(--success-700)':'var(--error-700)';
  var b=$('[data-t="badge"]',box);if(b){b.className='badge '+(ok?'b-ok':'b-err');b.textContent=ok?'Amount matched':'Amount not matched'}
}

function renumber(){$$('tr',body).forEach(function(tr,i){$$('[name^="lines["]',tr).forEach(function(inp){inp.name=inp.name.replace(/^lines\[[^\]]*\]/,'lines['+i+']')})})}
function addRow(){
  var tpl=$('#grnLineTpl'),i=body.children.length;if(!tpl)return null;
  body.insertAdjacentHTML('beforeend',tpl.innerHTML.replace(/__i__/g,i).replace(/__n__/g,i+1));
  var tr=body.lastElementChild,po=tr.querySelector('[data-po]'),its=tr.querySelector('[data-item]');if(po)po.innerHTML=poOptions('');if(its&&items!==null)its.innerHTML=itemOptions('');
  mode(tr);paint(tr);return tr;
}
body.addEventListener('input',function(e){if(e.target.hasAttribute('data-calc')){paint(e.target.closest('tr'));recalc()}});
body.addEventListener('change',function(e){
  var tr=e.target.closest('tr');if(!tr)return;
  if(e.target.matches('[data-po]')){var pol=tr.querySelector('[data-pol]');if(pol)pol.innerHTML=lineOptions(e.target.value,'');mode(tr)}
  paint(tr);recalc();
});
body.addEventListener('click',function(e){
  var b=e.target.closest('[data-del-line]');if(!b)return;
  if(body.children.length===1){$$('input:not([type=hidden])',b.closest('tr')).forEach(function(x){x.value=''});$$('select',b.closest('tr')).forEach(function(x){x.value=''});paint(b.closest('tr'));recalc();return}
  b.closest('tr').remove();renumber();recalc();
});
$$('[data-add-line="#grnLines"]').forEach(function(btn){btn.addEventListener('click',function(){var tr=addRow();if(tr){var f=tr.querySelector('select:not([disabled])');if(f)f.focus()}})});
if(amt)amt.addEventListener('input',recalc);
$$('select[name=invoice_type]',form).forEach(function(r){r.addEventListener('change',function(){$$('tr',body).forEach(function(tr){mode(tr);paint(tr)});recalc()})});

/* Lines go as one JSON field (only the pickers in use), and the button pressed as "intent", because the Save button is
   disabled while saving and a disabled button is not sent. */
document.addEventListener('submit',function(e){
  if(e.target!==form||e.defaultPrevented)return;
  var rows=$$('tr',body).map(function(tr){var o={};$$('[name^="lines["]',tr).forEach(function(i){if(i.disabled)return;var m=i.name.match(/\]\[([a-z_]+)\]$/);if(m)o[m[1]]=i.value});return o});
  var h=form.querySelector('input[name=lines_json]');if(!h){h=document.createElement('input');h.type='hidden';h.name='lines_json';form.appendChild(h)}
  h.value=JSON.stringify(rows);
  var it=form.querySelector('input[type=hidden][name=intent]');if(!it){it=document.createElement('input');it.type='hidden';it.name='intent';form.appendChild(it)}
  it.value=e.submitter&&e.submitter.name==='intent'?e.submitter.value:'pending';
  $$('[name^="lines["]',body).forEach(function(i){i.dataset.was=i.disabled?'1':'';i.disabled=true});
});
window.addEventListener('pageshow',function(){
  $$('[name^="lines["]',body).forEach(function(i){if(i.dataset.was!=null){i.disabled=i.dataset.was==='1';delete i.dataset.was}});
  ['lines_json'].forEach(function(n){var h=form.querySelector('input[name='+n+']');if(h)h.remove()});
  var it=form.querySelector('input[type=hidden][name=intent]');if(it)it.remove();
});

/* Supplier picked: its open POs (unit on the top bar) and GST split; rows start again because their POs belong to the old supplier. */
if(party)party.addEventListener('change',function(){
  if(!party.value)return;
  var u=form.dataset.ordersUrl;u+=(u.indexOf('?')<0?'?':'&')+'party='+encodeURIComponent(party.value);
  var iu=form.dataset.supplierItemsUrl+'?party='+encodeURIComponent(party.value);
  Promise.all([getJson(u),getJson(form.dataset.infoUrl.replace('__ID__',encodeURIComponent(party.value))),getJson(iu)]).then(function(res){
    orders=res[0]||[];var info=res[1];items=res[2]||[];
    $$('[data-item]',body).forEach(function(s){s.innerHTML=itemOptions(s.value)});
    if(info){form.dataset.supply=info.supply||'';form.dataset.currency=info.currency||'INR';
      $$('[data-t="cur"]').forEach(function(e){e.textContent=info.currency&&info.currency!=='INR'?info.currency+' ':'₹'})}
    $$('tr',body).forEach(function(tr){var po=tr.querySelector('[data-po]'),pol=tr.querySelector('[data-pol]');if(po)po.innerHTML=poOptions('');if(pol)pol.innerHTML=lineOptions('','');mode(tr);paint(tr)});
    recalc();
  });
});

/* Customer picked: its sales orders for SO no. */
var cust=$('#customer_id'),so=$('#sales_order_id');
if(cust&&so)cust.addEventListener('change',function(){
  if(!cust.value){so.innerHTML='<option value="">Select a customer first</option>';return}
  getJson(form.dataset.soUrl+'?customer='+encodeURIComponent(cust.value)).then(function(list){
    so.innerHTML='<option value="">Select</option>'+(list||[]).map(function(s){return '<option value="'+s.id+'">'+esc(s.label)+'</option>'}).join('');
  });
});

/* Quality buttons open the dialog with Approved or Rejected chosen; the per-item rejected quantities apply to Approved. */
$$('[data-open="#grnQualityLayer"][data-result]').forEach(function(b){b.addEventListener('click',function(){
  // Result is a drop-down now: the button sets its value (02/11/2026).
  var r=$('#grnQualityLayer select[name=result]');if(r){r.value=b.dataset.result;r.dispatchEvent(new Event('change',{bubbles:true}))}
})});
function qaLines(){var l=$('#grnQualityLayer [data-qa-lines]'),r=$('#grnQualityLayer select[name=result]');if(l)l.hidden=!!(r&&r.value==='Rejected')}
$$('#grnQualityLayer select[name=result]').forEach(function(r){r.addEventListener('change',qaLines)});qaLines();

$$('tr',body).forEach(function(tr){mode(tr);paint(tr)});
recalc();
})();
