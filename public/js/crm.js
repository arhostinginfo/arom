/* CRM entry pages: entry selector, import dialog, item rows and the Add prospect drawer. */
(function(){
'use strict';
var $=function(s,r){return (r||document).querySelector(s)}, $$=function(s,r){return [].slice.call((r||document).querySelectorAll(s))};

/* Dialogs and drawers: [data-open="#id"] shows the overlay, [data-x] or Escape hides it */
var lastFocus=null;
function open(layer){lastFocus=document.activeElement;layer.hidden=false;var f=layer.querySelector('input:not([type=hidden]),select,textarea');if(f)f.focus()}
function close(layer){layer.hidden=true;if(lastFocus)lastFocus.focus()}
$$('[data-open]').forEach(function(b){b.addEventListener('click',function(){var l=$(b.dataset.open);if(l)open(l)})});
$$('.overlay').forEach(function(l){$$('[data-x]',l).forEach(function(x){x.addEventListener('click',function(){close(l)})})});
document.addEventListener('keydown',function(e){if(e.key==='Escape')$$('.overlay').forEach(function(l){if(!l.hidden)close(l)})});
/* A dialog whose form came back with errors opens again */
$$('.overlay[data-autoopen]').forEach(open);

/* Quotation item rows */
var body=$('#qLines'),tpl=$('#qLineTpl'),add=$('#qAdd'),qTax=$('#tax_structure_id');
/* GST / tax of the quotation (23/10/2026): the Tax of items that have none, and of new items */
function taxSel(tr){return tr.querySelector('[name$="[tax_structure_id]"]')}
function defaultTax(tr){var s=taxSel(tr);if(qTax&&qTax.value&&s&&!s.value&&!s.disabled)s.value=qTax.value}
function renumber(){$$('tr',body).forEach(function(tr,i){$$('[name^="lines["]',tr).forEach(function(inp){inp.name=inp.name.replace(/^lines\[[^\]]*\]/,'lines['+i+']')})})}
if(body&&tpl){
  if(add)add.addEventListener('click',function(){
    var i=body.children.length;body.insertAdjacentHTML('beforeend',tpl.innerHTML.replace(/__i__/g,i));
    defaultTax(body.lastElementChild);totals();
    var f=body.lastElementChild.querySelector('input');if(f)f.focus();
  });
  body.addEventListener('click',function(e){
    var b=e.target.closest('[data-del-line]');if(!b)return;
    if(body.children.length===1){$$('input,select',b.closest('tr')).forEach(function(x){x.value=''});defaultTax(b.closest('tr'));return}
    b.closest('tr').remove();renumber();
  });
}

/* Items are sent as one JSON field, so a quotation of up to 200 items stays within PHP's max_input_vars
   (full test B1). The item inputs are disabled just for the send, and enabled again on Back. */
var qForm=$('#quotationForm');
/* On document, after app.js's own submit checks, so nothing is disabled when the send is stopped. */
if(qForm&&body)document.addEventListener('submit',function(e){
  if(e.target!==qForm||e.defaultPrevented)return;
  var rows=$$('tr',body).map(function(tr){var o={};$$('[name^="lines["]',tr).forEach(function(i){var m=i.name.match(/\]\[([a-z_]+)\]$/);if(m)o[m[1]]=i.value});return o});
  var h=$('#linesJson');if(!h){h=document.createElement('input');h.type='hidden';h.name='lines_json';h.id='linesJson';qForm.appendChild(h)}
  h.value=JSON.stringify(rows);
  $$('[name^="lines["]',body).forEach(function(i){i.disabled=true});
});
window.addEventListener('pageshow',function(){if(body)$$('[name^="lines["]',body).forEach(function(i){i.disabled=false});var h=$('#linesJson');if(h)h.remove()});

/* Quotation totals (CRM changes): Qty × Rate, less Discount %, plus Tax %, then less the Discount amount.
   Shown while typing only; the server works them out again on save. */
function num(v){v=String(v||'').replace(/[, ]/g,'');var n=parseFloat(v);return isFinite(n)?n:0}
function money(n){return n.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})}
function totals(){
  if(!body)return;var sub=0;
  $$('tr',body).forEach(function(tr){
    var g=function(f){var i=tr.querySelector('[name$="['+f+']"]');return i?num(i.value):0};
    /* Tax: the rate of the Tax master row picked (data-rate on the option, 23/10/2026) */
    var ts=taxSel(tr),o=ts&&ts.selectedIndex>=0?ts.options[ts.selectedIndex]:null,tax=o?num(o.getAttribute('data-rate')):0;
    var t=Math.round(g('qty')*g('rate')*(1-g('discount_percent')/100)*(1+tax/100)*100)/100;
    sub+=t;var c=tr.querySelector('[data-line-total]');if(c)c.textContent=t?money(t):'';
  });
  var d=$('#discount_amount'),s=$('#qSubtotal'),tt=$('#qTotal');
  if(s)s.textContent=money(sub);if(tt)tt.textContent=money(Math.max(0,sub-(d?num(d.value):0)));
}
if(body){body.addEventListener('input',function(e){if(e.target.hasAttribute('data-calc'))totals()});body.addEventListener('change',function(e){if(e.target.hasAttribute('data-calc'))totals()});body.addEventListener('click',function(){setTimeout(totals,0)});totals()}
if(qTax&&body)qTax.addEventListener('change',function(){$$('tr',body).forEach(defaultTax);totals()});
var disc=$('#discount_amount');if(disc)disc.addEventListener('input',totals);

/* Cost details: Total cost, Margin amount = Total × Margin %, Proposed selling price = Total + Margin */
var costBox=$('#costBox');
function costs(){
  var tot=0;$$('[data-cost]',costBox).forEach(function(i){if(i.id!=='cost_margin_percent')tot+=num(i.value)});
  var pct=num(($('#cost_margin_percent')||{}).value),m=Math.round(tot*pct)/100;
  $('#costTotal').textContent=money(tot);$('#costMargin').textContent=money(m);$('#costPrice').textContent=money(tot+m);
}
if(costBox){costBox.addEventListener('input',function(e){if(e.target.hasAttribute('data-cost'))costs()});costs()}

/* Customer status: the reason box is needed for Rejected, Expired and Hold */
var cs=$('#cs_status'),csr=$('#csReason');
function reason(){var need=cs.dataset.reasonFor.split(',').indexOf(cs.value)>=0;csr.querySelector('input').required=need;csr.classList.toggle('need',need)}
if(cs&&csr){cs.addEventListener('change',reason);reason()}

/* Add prospect drawer (CRM H9): saves without leaving the quotation and selects the new prospect */
var quick=$('#prospectQuick');
if(quick)quick.addEventListener('submit',function(e){
  e.preventDefault();
  var err=$('#pdErr'),btn=quick.querySelector('button[type=submit]');btn.disabled=true;
  fetch(quick.action,{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':$('meta[name=csrf-token]').content},body:new FormData(quick),credentials:'same-origin'})
    .then(function(r){return r.json().then(function(d){return {ok:r.ok,d:d}})})
    .then(function(res){
      btn.disabled=false;
      $$('.inp.err',quick).forEach(function(x){x.classList.remove('err')});
      if(!res.ok){
        var ul=err.querySelector('ul');ul.innerHTML='';
        Object.keys(res.d.errors||{}).forEach(function(k){
          var f=$('#pd_'+k);if(f)f.classList.add('err');
          res.d.errors[k].forEach(function(m){var li=document.createElement('li');li.textContent=m;ul.appendChild(li)});
        });
        if(!res.d.errors){var li=document.createElement('li');li.textContent=res.d.message||'Could not save the prospect.';ul.appendChild(li)}
        err.hidden=false;return;
      }
      var ps=$('#prospect_id'),o=document.createElement('option');o.value=res.d.id;o.textContent=res.d.name;ps.appendChild(o);ps.value=String(res.d.id);
      ps.dispatchEvent(new Event('change',{bubbles:true}));
      var cu=$('#party_id');if(cu&&cu.value){cu.value='';cu.dispatchEvent(new Event('change',{bubbles:true}))}
      quick.reset();err.hidden=true;close($('#prospectLayer'));
      if(window.nToast)nToast(res.d.message);
    })
    .catch(function(){btn.disabled=false;if(window.nToast)nToast('Could not reach the server. Try again.','err')});
});
/* Enquiry: picking an existing customer fills its known details (Sat 18/10/2026). The boxes stay editable; picking
   another customer replaces what the last one filled. Picking a Prospect fills nothing (22/10/2026). */
(function(){
  var ps=$('#prospect_id'),cu=$('#party_id');if(!ps||!ps.dataset.clientInfo)return;
  var url=ps.dataset.clientInfo,req=0;
  function setVal(id,v){var e=document.getElementById(id);if(!e||e.disabled||e.readOnly)return;e.value=v==null?'':v;e.dispatchEvent(new Event('input',{bubbles:true}))}
  function setSel(id,v,label){var e=document.getElementById(id);if(!e||e.disabled)return;v=v==null?'':String(v);
    if(v&&![].some.call(e.options,function(o){return o.value===v})){var o=document.createElement('option');o.value=v;o.textContent=label||v;e.appendChild(o)}
    e.value=v;e.dispatchEvent(new Event('change',{bubbles:true}))}
  function fill(kind,id){
    if(!id)return;var n=++req;
    fetch(url+'?'+kind+'='+encodeURIComponent(id),{headers:{'Accept':'application/json'},credentials:'same-origin'}).then(function(r){return r.ok?r.json():null}).then(function(d){
      if(!d||n!==req)return;
      ['contact_name','contact_no','address','industry','website'].forEach(function(k){setVal(k,d[k])});
      setSel('industry_type_id',d.industry_type_id);
      setSel('city_id',d.city?d.city.id:'',d.city?d.city.label:'');
    }).catch(function(){});
  }
  /* Contact name / Contact no are hidden and not sent while a Prospect is picked; they come from the Prospect (22/10/2026). */
  var pair=document.getElementById('contactPair');
  function syncPair(){if(!pair)return;var off=!!ps.value;pair.hidden=off;$$('input',pair).forEach(function(i){i.disabled=off})}
  ps.addEventListener('change',syncPair);
  if(cu)cu.addEventListener('change',syncPair);
  ps.addEventListener('change',function(){req++});/* a pending customer fill no longer lands after a Prospect is picked */
  if(cu)cu.addEventListener('change',function(){fill('party',cu.value)});
})();
})();
