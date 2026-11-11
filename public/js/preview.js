/* Sample screens ported from the clickable prototype (design/04). Modules not built yet keep the prototype's sample data; nothing here is saved. */

(function(){
'use strict';
var $=function(s,r){return (r||document).querySelector(s)}, $$=function(s,r){return [].slice.call((r||document).querySelectorAll(s))};
function ic(n,st){return '<svg class="ico"'+(st?' style="'+st+'"':'')+'><use href="#i-'+n+'"/></svg>'}
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function fmt(n){return Number(n||0).toLocaleString('en-IN')}
function money(n){return '₹ '+Number(n||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})}
function today(){return '03/10/2025'} /* fixed sample date inside FY 2025-26 */
var reduce=function(){return window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches};
var SAMPLE='<span class="sample">Sample</span>';
var COMPANY_STATE='27';

/* ---------------- Sample data (illustrative only) ---------------- */
var D={
  units:['Unit 1 · Chakan','Unit 2 · Bhosari'],
  users:[
    {name:'Priya S.',email:'priya@company.example',mobile:'+91 98220 00001',role:'Sales',units:'Unit 1, Unit 2',status:'Active'},
    {name:'Rahul K.',email:'rahul@company.example',mobile:'+91 98220 00002',role:'Purchase',units:'Unit 1',status:'Active'},
    {name:'Meena D.',email:'meena@company.example',mobile:'+91 98220 00003',role:'Accounts',units:'Unit 1, Unit 2',status:'Active'},
    {name:'Anil M.',email:'anil@company.example',mobile:'+91 98220 00004',role:'Admin',units:'All',status:'Active'}
  ],
  parties:[
    {name:'Apex Auto Components',gst:'27AAACA1234F1Z2',type:'Customer',bill:'Plot 14, MIDC Chakan, Pune 410501',ship:'Plot 14, MIDC Chakan, Pune 410501',terms:'45 days',ob:'50000.00',drcr:'DR',cur:'INR',status:'Active'},
    {name:'Kaveri Precision Engineering',gst:'29AABCK4521M1Z8',type:'Customer',bill:'Peenya Phase 2, Bengaluru 560058',ship:'Peenya Phase 2, Bengaluru 560058',terms:'30 days',ob:'0.00',drcr:'DR',cur:'INR',status:'Active'},
    {name:'Helix Motors GmbH',gst:'',type:'Customer',bill:'Stuttgart, Germany',ship:'Stuttgart, Germany',terms:'60 days',ob:'0.00',drcr:'DR',cur:'EUR',status:'Active'},
    {name:'Nova Fasteners',gst:'27AADCN7788Q1Z4',type:'Both',bill:'Bhosari MIDC, Pune 411026',ship:'Bhosari MIDC, Pune 411026',terms:'30 days',ob:'0.00',drcr:'DR',cur:'INR',status:'Active'},
    {name:'Shree Ganesh Castings',gst:'27AAAPS1234K1Z5',type:'Supplier',bill:'Shiroli MIDC, Kolhapur 416122',ship:'Shiroli MIDC, Kolhapur 416122',terms:'60 days',ob:'25000.00',drcr:'CR',cur:'INR',status:'Active'}
  ],
  items:[
    {no:'BRK-2210',ino:'10-4471',desc:'Brake bracket',party:'Apex Auto Components',cat:'Pressings',type:'Sales',hsn:'8708',uom:'NOS',tax:'GST 18%',price:'42.50',cur:'INR',min:'500',max:'3000',wpp:'0.42',pack:'100',status:'Active',stock:820,bom:[{item:'RM-CR-1.6',qty:'0.48',uom:'KG'}]},
    {no:'BRK-2214',ino:'10-4475',desc:'Mounting plate',party:'Apex Auto Components',cat:'Pressings',type:'Sales',hsn:'8708',uom:'NOS',tax:'GST 18%',price:'61.00',cur:'INR',min:'300',max:'2000',wpp:'0.65',pack:'50',status:'Active',stock:240,bom:[{item:'RM-CR-1.6',qty:'0.72',uom:'KG'}]},
    {no:'HNG-0907',ino:'22-1180',desc:'Hinge pin',party:'Kaveri Precision Engineering',cat:'Machined',type:'Sales',hsn:'7318',uom:'NOS',tax:'GST 18%',price:'8.20',cur:'INR',min:'2000',max:'10000',wpp:'0.05',pack:'500',status:'Active',stock:3150,bom:[]},
    {no:'HSG-5001',ino:'50-0001',desc:'Pump housing',party:'Helix Motors GmbH',cat:'Castings',type:'Sales',hsn:'8413',uom:'NOS',tax:'GST 18%',price:'4.80',cur:'EUR',min:'100',max:'600',wpp:'2.30',pack:'10',status:'Active',stock:180,bom:[{item:'CST-4410',qty:'1',uom:'NOS'}]},
    {no:'CLP-1102',ino:'31-0092',desc:'Spring clip',party:'Nova Fasteners',cat:'Pressings',type:'Both',hsn:'7326',uom:'NOS',tax:'GST 18%',price:'1.10',cur:'INR',min:'1000',max:'8000',wpp:'0.01',pack:'1000',status:'Active',stock:640,bom:[]},
    {no:'CST-4410',ino:'40-0012',desc:'Housing casting',party:'Shree Ganesh Castings',cat:'Castings',type:'Purchase',hsn:'7325',uom:'NOS',tax:'GST 18%',price:'210.00',cur:'INR',min:'300',max:'1500',wpp:'2.10',pack:'20',status:'Active',stock:410,bom:[]},
    {no:'CST-4412',ino:'40-0014',desc:'Cover casting',party:'Shree Ganesh Castings',cat:'Castings',type:'Purchase',hsn:'7325',uom:'NOS',tax:'GST 18%',price:'145.00',cur:'INR',min:'200',max:'1200',wpp:'1.40',pack:'20',status:'Active',stock:150,bom:[]},
    {no:'RM-CR-1.6',ino:'90-0016',desc:'CR sheet 1.6 mm',party:'Nova Fasteners',cat:'Raw material',type:'Purchase',hsn:'7209',uom:'KG',tax:'GST 18%',price:'68.00',cur:'INR',min:'800',max:'4000',wpp:'1',pack:'1',status:'Active',stock:1210,bom:[]}
  ],
  sos:[
    {no:'SO/25-26/0142',party:'Apex Auto Components',po:'4500123',start:'01/04/2025',end:'31/03/2026',rev:'2',revDate:'15/09/2025',file:'PO_4500123_rev2.pdf',status:'Approved',
      lines:[{item:'BRK-2210',qty:1200,rate:'42.50',del:'15/10/2025',bal:400},{item:'BRK-2214',qty:600,rate:'61.00',del:'15/10/2025',bal:250}],audit:['Priya S.','01/04/2025','Rahul K.','15/09/2025'],appr:['Anil M.','02/04/2025']},
    {no:'SO/25-26/0145',party:'Kaveri Precision Engineering',po:'KPE/PO/7781',start:'01/10/2025',end:'31/03/2026',rev:'0',revDate:'',file:'KPE_PO_7781.pdf',status:'Pending approval',
      lines:[{item:'HNG-0907',qty:4000,rate:'8.20',del:'20/11/2025',bal:4000}],audit:['Priya S.','01/10/2025','Priya S.','01/10/2025']}
  ],
  invoices:[
    {no:'INV/25-26/0815',party:'Apex Auto Components',type:'Sales',po:'4500123',mode:'Road',trn:'Sai Logistics',veh:'MH 14 AB 1234',dist:'42',pf:'250.00',lines:[{item:'BRK-2210',qty:400},{item:'BRK-2214',qty:250}],audit:['Priya S.','29/09/2025','Priya S.','29/09/2025']}
  ],
  pos:[
    {no:'PO/25-26/0077',party:'Shree Ganesh Castings',date:'18/09/2025',status:'Approved',lines:[{item:'CST-4410',qty:500,rate:'210.00',del:'01/10/2025'},{item:'CST-4412',qty:300,rate:'145.00',del:'01/10/2025'}],more:{del:'01/10/2025',terms:'60 days',cur:'INR',bill:'Plot 21, MIDC Chakan, Pune 410501',ship:'Plot 21, MIDC Chakan, Pune 410501',trn:'Sai Logistics',rem:'Pack in returnable crates'},audit:['Rahul K.','18/09/2025','Rahul K.','18/09/2025'],appr:['Anil M.','19/09/2025']},
    {no:'PO/25-26/0081',party:'Shree Ganesh Castings',date:'02/10/2025',status:'Pending approval',lines:[{item:'CST-4410',qty:600,rate:'210.00',del:'20/10/2025'}],more:{del:'20/10/2025',terms:'60 days',cur:'INR',bill:'Plot 21, MIDC Chakan, Pune 410501',ship:'Plot 21, MIDC Chakan, Pune 410501',trn:'',rem:''},audit:['Rahul K.','02/10/2025','Rahul K.','02/10/2025'],level:1}
  ],
  grns:[{no:'GRN/25-26/0232',party:'Shree Ganesh Castings',item:'CST-4410',qty:200}],
  quotes:[
    {enq:'ENQ/25-26/0044',qt:'QT/25-26/0031',rev:'2',customer:'',prospect:'Orion Agro Equipment',src:'Email',ref:'Trade fair, Pune',project:'Tractor hinge',sp:'Priya S.',date:'12/08/2025',cur:'INR',follow:'Negotiation',
      lines:[{no:'HNG-0907',desc:'Hinge pin',vol:'24000',rate:'8.20',sample:'15/09/2025',prod:'01/12/2025',tech:'EN8, zinc plated'}]}
  ],
  prospects:[
    {name:'Orion Agro Equipment',de:'Domestic',contact:'Sanjay Patil',pos:'Purchase Manager',ph1:'+91 98500 44521',ph2:'',email:'sanjay@orionagro.example',gst:'',ind:'Agriculture machinery',addr:'Hadapsar, Pune'}
  ],
  ledger:{
    'Apex Auto Components':[['01/04/2025','Opening balance','',50000,0,''],['29/09/2025','Tax invoice','INV/25-26/0815',37216,0,'Against PO 4500123'],['30/09/2025','Receivable','RCV/25-26/0091',0,50000,'NEFT · UTR 8812']],
    'Shree Ganesh Castings':[['01/04/2025','Opening balance','',0,25000,''],['01/10/2025','GRN','GRN/25-26/0232',0,49560,'SGC/2025/1102'],['02/10/2025','Payable','PAY/25-26/0040',25000,0,'NEFT']]
  },
  masters:{
    'Currency/Country':{f:['Country','Currency code','Symbol'],rows:[['India','INR','₹'],['Germany','EUR','€'],['United States','USD','$']]},
    'HSN/SAC':{f:['Code','Type','Description','Default tax structure'],rows:[['8708','HSN','Parts of motor vehicles','GST 18%'],['7325','HSN','Cast articles of iron or steel','GST 18%'],['7318','HSN','Screws, bolts, pins','GST 18%']]},
    'Tax master':{f:['Name','CGST %','SGST %','IGST %'],rows:[['GST 5%','2.5','2.5','5'],['GST 12%','6','6','12'],['GST 18%','9','9','18']]},
    'UOM':{f:['Code','Description'],rows:[['NOS','Numbers'],['KG','Kilogram'],['MTR','Metre']]},
    'Company':{f:['Name','GSTIN','PAN','Address','Phone','Email'],rows:[['Your company name','27AAAAA0000A1Z5','AAAAA0000A','Plot 21, MIDC Chakan, Pune 410501','+91 20 0000 0000','accounts@company.example']]},
    'Bank master':{f:['Bank name','Account no','IFSC','Branch','Account type'],rows:[['Sample Bank','001234567890','SMPL0000123','Chakan','Current']]},
    'Category':{f:['Name','Type','Item no prefix'],rows:[['Pressings','Sales','PRS'],['Castings','Both','CST'],['Machined','Sales','MCH'],['Raw material','Purchase','RM']]}
  },
  transporters:['Sai Logistics','VRL Logistics'],
  salesPeople:['Priya S.'],
  approvalCfg:{'Sales Order':{req:'Yes',levels:'1',l1:'Anil M. (Admin)',l2:'',above:''},'Purchase PO':{req:'Yes',levels:'2',l1:'Rahul K. (Purchase)',l2:'Anil M. (Admin)',above:'100000'},'Stock transfer':{req:'Yes',levels:'1',l1:'Anil M. (Admin)',l2:'',above:''}},
  dashTiles:{Sales:['Open quotations','Sales orders this month','Invoices this month','Items below minimum stock'],Purchase:['Open purchase orders','GRNs pending match','GRNs pending quality approval','Items below minimum stock'],Accounts:['Receivables outstanding','Payables outstanding','Invoices this month','Expenses this month']}
};
function party(n){return D.parties.filter(function(p){return p.name===n})[0]}
function item(no){return D.items.filter(function(i){return i.no===no})[0]||{no:no,desc:'',ino:'',price:'0',tax:'GST 18%'}}
function taxPct(t){var r=D.masters['Tax master'].rows.filter(function(x){return x[0]===t})[0];return r?+r[3]:18}
function interState(p){var pa=party(p);return pa&&pa.gst?pa.gst.slice(0,2)!==COMPANY_STATE:!!pa}
var nextGrn=233,nextInv={Sales:816,Credit:22,Debit:5},nextPo=82,nextSo=146,nextSt=13;

/* Reports exactly as listed on each sheet's REPORTS column, with decided options (S3) */
var REPORTS={
  CRM:[['Prospects',''],['Enquiries','party']],
  Sales:[['Sales order report','party'],['Sales report','party,sd'],['Export sales report','party'],['Credit/Debit report (customer)','party'],['Stock report and Minimum stock report','item']],
  Purchase:[['PO summary report','party'],['GRN report','party'],['Credit/Debit report (supplier)','party'],['Stock report and Minimum stock report','item'],['Stock transfer report','item'],['Stock ledger','item']],
  Accounts:[['Sales report','party,sd'],['Credit/Debit report (partywise)','party'],['Export/Import','party'],['GRN report','party'],['Party ledger report','party'],['Outstanding report','party'],['HSN report',''],['Transporter report','trn']]
};
var ROLE=NURSERY.role;
var ROLE_MODS={Sales:['reports','dashboard','crm','sales'],Purchase:['reports','dashboard','purchase'],Accounts:['reports','dashboard','accounts'],Admin:['reports','dashboard','crm','sales','purchase','accounts','admin']};
function me(){return {name:NURSERY.userName}}
function ini(n){return n.split(/[ .]+/).filter(Boolean).map(function(x){return x[0]}).join('').slice(0,2).toUpperCase()}
var ROLE_REPORTS={Sales:['CRM','Sales'],Purchase:['Purchase'],Accounts:['Accounts'],Admin:['CRM','Sales','Purchase','Accounts']};ROLE_REPORTS[ROLE]=NURSERY.reportGroups;ROLE_MODS[ROLE]=NURSERY.mods;
function repKey(g,i){return g+'-'+i}

/* Page capability table (OTHER POINTS A1:F8) */
var CAP={quotation:{setup:1,imp:1,csv:1,pdf:1},prospect:{setup:1,imp:1,csv:1,pdf:0},partymaster:{setup:1,imp:1,csv:1,pdf:0},itemmaster:{setup:1,imp:1,csv:1,pdf:0},salesorder:{setup:1,imp:1,csv:1,pdf:0},invoice:{setup:1,imp:0,csv:1,pdf:1},grn:{setup:1,imp:0,csv:0,pdf:0}};
var FIELDS={
  quotation:['Customer','Prospect','Source of enquiry','Reference','Project name','Sales person','Enquiry receipt date','Currency','Followup status'],
  prospect:['Prospect name','Domestic / Export','Contact person','Position','Contact no 1','Contact no 2','Email','GSTIN','Industry type','Address'],
  partymaster:['Name','GST no','Type','Billing address','Shipping address','Payment terms','Opening balance','DR / CR','Currency','Status'],
  itemmaster:['Party name','Category','Item description','Item no','Internal item no','Type','HSN/SAC','UOM','Tax structure','Price','Currency','Min stock','Max stock','Weight / piece','Packaging qty','Status'],
  salesorder:['Party name','PO number','Start date','End date','Rev no','Rev date','Upload PO'],
  invoice:['Party name','Invoice type','PO no','Shipping address','Mode of transport','Transporter','Vehicle no','Distance','P&F charges'],
  grn:['Party name','Invoice type','Invoice no','Invoice date','Invoice amount','Transporter','Vehicle no','E-way bill no','E-way bill date','Quality approval','Customer name','SO no']
};
var MANDATORY={
  quotation:['Customer','Prospect','Sales person','Enquiry receipt date'],prospect:['Prospect name','Domestic / Export','Contact no 1'],
  partymaster:['Name','Type','Billing address','Status'],itemmaster:['Item description','Item no','Type','HSN/SAC','UOM','Tax structure','Status'],
  salesorder:['Party name','PO number','Start date'],invoice:['Party name','Invoice type','Shipping address'],grn:['Party name','Invoice type','Invoice no','Invoice date','Invoice amount']
};

/* ---------------- Navigation (row 1 of each module sheet) ---------------- */
var NAV=[
  {k:'reports',i:'chart',t:'Reports'},
  {k:'dashboard',i:'dash',t:'Dashboard'},
  {k:'crm',i:'crm',t:'CRM',subs:[['quotation','Quotation'],['prospect','Prospect']]},
  {k:'sales',i:'receipt',t:'Sales',subs:[['sales','Tax Invoice'],['sales.so','Sales Order']]},
  {k:'purchase',i:'cart',t:'Purchase',subs:[['grn','GRN Entry'],['po','Purchase Order'],['transfer','Stock Transfer']]},
  {k:'accounts',i:'bank',t:'Accounts',subs:[['ledger','Party Ledger'],['entry','Receivables / Payables / Expenses / Bank entry']]},
  {k:'admin',i:'cog',t:'Admin',subs:[['users','Users list'],['access','Access rights'],['approval','Approval'],['dashcfg','Dashboard'],['config','Config'],['pagesetup','Page setup'],['masters','Masters']]}
];
var MOD={reports:'reports',dashboard:'dashboard',quotation:'crm',prospect:'crm',sales:'sales',grn:'purchase',po:'purchase',transfer:'purchase',ledger:'accounts',entry:'accounts',users:'admin',access:'admin',approval:'admin',dashcfg:'admin',config:'admin',pagesetup:'admin',masters:'admin',master:'admin'};

var NAV_GROUPS=[['Overview',['dashboard']],['Modules',['crm','sales','purchase','accounts']],['Settings',['admin']],['',['reports']]];
var REP_MOD={CRM:'crm',Sales:'sales',Purchase:'purchase',Accounts:'accounts'}, repCtx='';
function repButtons(groups,arg,ctx,labels){return groups.map(function(g){return (labels?'<div class="rgrp">'+g+'</div>':'')+REPORTS[g].map(function(r,i){var k=repKey(g,i);return '<button class="repl'+(arg===k?' on':'')+'" data-go="reports.'+k+'"'+(ctx?' data-ctx="'+ctx+'"':' data-ctx="reports"')+(arg===k?' aria-current="page"':'')+'>'+r[0]+'</button>'}).join('')}).join('')}
function renderSide(){}
function crumbs(list){
  // The top bar has no breadcrumbs since the menu bar (22/10/2026); the phone title below still follows the page.
  if($('#crumbs'))$('#crumbs').innerHTML=list.map(function(c,i){var last=i===list.length-1;return last?'<b>'+esc(c[0])+'</b>':'<a href="'+url(c[1])+'" style="color:inherit;text-decoration:none">'+esc(c[0])+'</a> '+ic('right','width:12px')}).join(' ');
  $('#mtitle').textContent=list[list.length-1][0];
  document.title=list[list.length-1][0]+' · Nursery';
}

/* ---------------- Router ---------------- */
var R={};
function url(h){var p=String(h).split('.'),r=p[0],a=p.slice(1).join('.');if(NURSERY.links[r])return NURSERY.links[r];return NURSERY.base+'/'+r+(a?'/'+encodeURIComponent(a):'')}
function go(h){location.href=url(h)}
function render(){
  var r=NURSERY.route,a=NURSERY.arg||'';if(!R[r])r='reports';
  closeLayer();R[r](a);
}
/* ---------------- Cinematic motion helpers ---------------- */
var booted=false, apPulse=false, lastMod='';
function cine(fn){
  if(!booted||reduce()||!document.startViewTransition){fn();stagger();return}
  try{var t=document.startViewTransition(function(){fn()});t.updateCallbackDone.then(stagger)}catch(e){fn();stagger()}
}
function stagger(){
  if(reduce())return;
  var els=$$('#view .pagehd, #view .entrybar, #view .kpi, #view > .sectcard, #view .sectcard > .sb > .fgrid > .field, #view .repcard > *, #view .fgrid > .sectcard');
  els.slice(0,14).forEach(function(e,i){e.classList.remove('cin');void e.offsetWidth;e.style.setProperty('--i',i);e.classList.add('cin')});
  $$('#view .kpi .v').forEach(countUp);
}
function countUp(el){
  var txt=el.textContent,m=txt.match(/[\d,]+(\.\d+)?/);if(!m)return;
  var target=parseFloat(m[0].replace(/,/g,'')),dec=m[1]?m[1].length-1:0,pre=txt.slice(0,m.index),post=txt.slice(m.index+m[0].length),t0=null,dur=700;
  function fmtN(v){return v.toLocaleString('en-IN',{minimumFractionDigits:dec,maximumFractionDigits:dec})}
  function step(t){if(t0==null)t0=t;var k=Math.min(1,(t-t0)/dur),e=1-Math.pow(1-k,4);el.textContent=pre+fmtN(target*e)+post;if(k<1)requestAnimationFrame(step);else el.textContent=txt}
  requestAnimationFrame(step);
}
function scramble(el,text){
  if(reduce()){el.textContent=text;return}
  var n=0,max=14,last=null;
  (function tick(){if(last!==null&&el.textContent!==last)return;n++;last=n<max?text.split('').map(function(c,i){return /\d/.test(c)&&i>=text.length*n/max?String(Math.floor(Math.random()*10)):c}).join(''):text;el.textContent=last;if(n<max)setTimeout(tick,38)})();
}
function labelTables(){
  $$('#view table.atable:not(.keep)').forEach(function(t){
    var hs=[].map.call(t.querySelectorAll('thead th'),function(th){return th.textContent.trim()});
    t.classList.add('rtable');
    [].forEach.call(t.querySelectorAll('tbody tr'),function(tr){[].forEach.call(tr.children,function(td,i){
      if(td.getAttribute('data-label')!==(hs[i]||''))td.setAttribute('data-label',hs[i]||'');
      if(i===0&&!td.classList.contains('wide'))td.classList.add('wide');
    })});
  });
}
var lblQ=false;new MutationObserver(function(){if(lblQ)return;lblQ=true;requestAnimationFrame(function(){lblQ=false;labelTables()})}).observe(document.getElementById('view'),{childList:true,subtree:true});
$('#content').addEventListener('scroll',function(){$('#top').classList.toggle('scrolled',this.scrollTop>8)},{passive:true});
document.addEventListener('pointermove',function(e){var c=e.target.closest&&e.target.closest('.kpi,.spot');if(!c)return;var r=c.getBoundingClientRect();c.style.setProperty('--mx',(e.clientX-r.left)+'px');c.style.setProperty('--my',(e.clientY-r.top)+'px')},{passive:true});
document.addEventListener('click',function(e){
  var g=e.target.closest('[data-go]');
  if(g&&!e.defaultPrevented){e.preventDefault();var d=g.getAttribute('data-go');repCtx=d.indexOf('reports.')===0?(g.getAttribute('data-ctx')||repCtx||''):'';go(d)}
});
function view(html){$('#view').innerHTML=html}

/* ---------------- Toasts & layers ---------------- */
function toast(msg,kind){
  var t=document.createElement('div');t.className='toast';
  t.innerHTML=ic(kind==='err'?'alert':'check-c','color:var('+(kind==='err'?'--error-600':'--success-600')+')')+'<span>'+msg+'</span>';
  $('#toasts').appendChild(t);while($('#toasts').children.length>3)$('#toasts').firstChild.remove();
  setTimeout(function(){if(reduce()){t.remove();return}t.classList.add('out');setTimeout(function(){t.remove()},260)},4300);
}
var lastFocus=null;
function layer(html){
  lastFocus=document.activeElement;
  $('#layer').innerHTML='<div class="overlay"><div class="scrim" data-x></div>'+html+'</div>';
  var f=$('#layer [autofocus]')||$('#layer input,#layer select,#layer textarea,#layer button:not([data-x])');if(f)f.focus();
  $$('#layer [data-x]').forEach(function(x){x.addEventListener('click',closeLayer)});
}
function closeLayer(){if($('#layer').innerHTML){if(!reduce()){var g=document.createElement('div');g.className='ghost';g.setAttribute('aria-hidden','true');g.innerHTML=$('#layer').innerHTML;g.querySelectorAll('[id]').forEach(function(x){x.removeAttribute('id')});document.body.appendChild(g);setTimeout(function(){g.remove()},240)}$('#layer').innerHTML='';if(lastFocus&&lastFocus.focus)lastFocus.focus()}$$('.pop').forEach(function(p){p.remove()})}
document.addEventListener('keydown',function(e){if(e.key==='Escape'){closeLayer();$('#shell').classList.remove('navopen')}});
function drawer(title,body,foot){
  layer('<div class="drawer" role="dialog" aria-modal="true" aria-labelledby="dwT"><div class="dh"><h3 id="dwT">'+title+'</h3><span style="flex:1"></span><button class="btn gh icon sm" data-x aria-label="Close">'+ic('x')+'</button></div><div class="db">'+body+'</div><div class="df">'+foot+'</div></div>');
}
function modal(title,body,foot){
  layer('<div class="modal" role="dialog" aria-modal="true" aria-labelledby="mdT"><div class="mh"><h3 id="mdT">'+title+'</h3></div><div class="mb">'+body+'</div><div class="mf">'+foot+'</div></div>');
}
function pop(anchor,html){
  $$('.pop').forEach(function(p){p.remove()});
  var p=document.createElement('div');p.className='pop';p.innerHTML=html;document.body.appendChild(p);
  var r=anchor.getBoundingClientRect(),w=p.offsetWidth;
  p.style.top=(r.bottom+6)+'px';p.style.left=Math.max(8,Math.min(window.innerWidth-w-8,r.right-w))+'px';
  setTimeout(function(){document.addEventListener('click',function off(e){if(!p.contains(e.target)){p.remove();document.removeEventListener('click',off)}})},0);
  var f=p.querySelector('button');if(f)f.focus();
  return p;
}

/* ---------------- Top bar ---------------- */
$('#fyChip').onclick=function(e){e.stopPropagation();pop(e.currentTarget,'<button class="mi on">'+ic('check')+'FY 2025-26</button><button class="mi" style="padding-left:36px">FY 2024-25</button><div class="sep"></div><div class="cap muted" style="padding:4px 10px;max-width:250px">Entries are financial-year wise, except masters (OTHER POINTS A22). FY starts 1 April</div>')};
/* ---------------- Form helpers ---------------- */
/* A choice drop-down made by seg() gets its label tied to it (02/11/2026). */
function fld(label,ctl,o){o=o||{};var sm=!o.for&&/^<select class="inp" data-seg id="([^"]+)"/.exec(ctl);if(sm)o.for=sm[1];return '<div class="field"'+(o.span?' style="grid-column:1/-1"':'')+'><label'+(o.for?' for="'+o.for+'"':'')+'>'+label+(o.req?'<span class="req">*</span>':'')+'</label>'+ctl+(o.hint?'<span class="hint">'+o.hint+'</span>':'')+(o.tag?'<span class="tagline">'+o.tag+'</span>':'')+'</div>'}
function inp(id,v,o){o=o||{};return '<input class="inp'+(o.num?' num':'')+'" id="'+id+'" value="'+esc(v)+'"'+(o.ph?' placeholder="'+esc(o.ph)+'"':'')+(o.ro?' readonly':'')+(o.mode?' inputmode="'+o.mode+'"':'')+'>'}
function dt(id,v){return inp(id,v,{num:1,ph:'dd/mm/yyyy'})}
function sel(id,opts,v,o){o=o||{};return '<select class="inp" id="'+id+'"'+(o.dis?' disabled':'')+'>'+(o.blank!==false?'<option value="">Select</option>':'')+opts.map(function(x){var val=Array.isArray(x)?x[0]:x,lab=Array.isArray(x)?x[1]:x;return '<option value="'+esc(val)+'"'+(val===v?' selected':'')+'>'+esc(lab)+'</option>'}).join('')+'</select>'}
/* Choice groups are drop-downs now instead of segmented buttons (02/11/2026): seg() makes a select, segVal() reads it
   and wireSeg() calls cb(id, value) when it changes, as the buttons did. lab = aria-label when there is no visible label. */
function seg(id,opts,v,lab){return '<select class="inp" data-seg id="'+id+'"'+(lab?' aria-label="'+esc(lab)+'"':'')+'>'+opts.map(function(x){return '<option value="'+esc(x)+'"'+(x===v?' selected':'')+'>'+esc(x)+'</option>'}).join('')+'</select>'}
function segVal(id){var s=$('#'+id);return s?s.value:''}
function wireSeg(root,cb){$$('select[data-seg]',root).forEach(function(s){s.onchange=function(){if(cb)cb(s.id,s.value)}})}
function audit(a){return a?'<div class="audit"><span>Added by <b>'+a[0]+'</b> on <b class="num">'+a[1]+'</b></span><span>Updated by <b>'+a[2]+'</b> on <b class="num">'+a[3]+'</b></span><span class="cap">Audit (OTHER POINTS A24)</span></div>':'<div class="audit"><span>Added by and added on are filled when the entry is saved (OTHER POINTS A24)</span></div>'}
function rule(t){return '<div class="ruleh">'+ic('info')+'<span>'+t+'</span></div>'}
function need(ids){var bad=ids.filter(function(id){var e=$('#'+id);if(!e)return false;var v=e.value;var x=!String(v).trim();e.classList.toggle('err',x);e.setAttribute('aria-invalid',String(x));return x});if(bad.length){$('#'+bad[0]).focus();toast('Fill the required fields marked in red','err');return false}return true}
function capBar(key){var c=CAP[key];if(!c)return '';var b=[];
  if(c.setup)b.push('<button class="btn gh sm" data-cap="setup" data-key="'+key+'">'+ic('cols')+'Page setup</button>');
  if(c.imp)b.push('<button class="btn gh sm" data-cap="import">'+ic('upload')+'Import</button>');
  if(c.csv)b.push('<button class="btn gh sm" data-cap="csv">'+ic('download')+'Download CSV</button>');
  if(c.pdf)b.push('<button class="btn gh sm" data-cap="pdf">'+ic('file')+(key==='quotation'?'Generate PDF':'PDF')+'</button>');
  return '<div class="capbtns">'+b.join('')+'</div>'}
function wireCap(root,name){$$('[data-cap]',root).forEach(function(b){b.onclick=function(){var k=b.dataset.cap;
  if(k==='setup')pageSetup(b.dataset.key,name);
  else if(k==='import')modal('Import · '+name,'<div style="border:1.5px dashed var(--border-strong);border-radius:8px;padding:22px;text-align:center;background:var(--surface-sunken)">'+ic('upload','width:22px;height:22px;color:var(--text-muted)')+'<p class="small" style="margin-top:6px"><b>Choose a CSV file</b> in the page’s column order</p></div><button class="btn lnk sm" style="justify-self:start">'+ic('download')+'Download the column template</button>','<button class="btn gh" data-x>Cancel</button><button class="btn pri" data-x id="impOk">Import sample file</button>'),$('#impOk').addEventListener('click',function(){toast(name+': sample rows imported (prototype)')});
  else if(k==='csv')toast(name+': CSV download would start here (sample)');
  else pdfPreview(name)}})}
function pdfPreview(name){modal(name+' · PDF','<div style="background:#fff;color:#111827;border:1px solid var(--border);border-radius:8px;padding:20px;font-size:12px;display:grid;gap:8px"><div style="display:flex;justify-content:space-between;gap:10px"><div><b style="font-size:14px">'+esc(name.toUpperCase())+'</b><div>Sample layout</div></div><div style="text-align:right"><b>Your company name</b><div>GSTIN 27AAAAA0000A1Z5</div></div></div><div style="border-top:1px solid #E3E6EB;padding-top:8px">Company logo and details come from the Company master; logo, bank details and terms are switched in Page setup.</div></div>'+rule('PDF layout'),'<button class="btn sec" data-x>Close</button><button class="btn pri" data-x id="pdfDl">'+ic('download')+'Download PDF</button>');$('#pdfDl').addEventListener('click',function(){toast('PDF download is disabled in the prototype')})}
function pageSetup(key,name){var f=FIELDS[key]||[],m=MANDATORY[key]||[];
  modal('Page setup · '+name,'<p class="small sec2">Choose which fields show on this page and which are mandatory. Admin only.</p><div class="tablecard" style="max-height:46vh;overflow:auto"><table class="atable"><thead><tr><th>Field</th><th class="r">Show</th><th class="r">Mandatory</th></tr></thead><tbody>'+f.map(function(x){var mm=m.indexOf(x)>=0;return '<tr style="cursor:default"><td>'+x+'</td><td class="r"><input type="checkbox" checked aria-label="Show '+x+'"></td><td class="r"><input type="checkbox"'+(mm?' checked':'')+' aria-label="'+x+' mandatory"></td></tr>'}).join('')+'</tbody></table></div>'+(CAP[key]&&CAP[key].pdf?'<div class="lbl">PDF layout</div><div style="display:grid;gap:6px" class="small"><label><input type="checkbox" checked> Show company logo</label><label><input type="checkbox" checked> Show bank details</label><label><input type="checkbox"> Show terms text</label></div>':''),'<button class="btn gh" data-x>Cancel</button><button class="btn pri" data-x id="psOk">Save page setup</button>');
  $('#psOk').addEventListener('click',function(){toast('Page setup saved for '+name+' (sample)')})}
function entryBar(label,list,cur,key){return '<div class="entrybar"><label for="entrySel">'+label+'</label><select class="inp" id="entrySel">'+list.map(function(x,i){return '<option value="'+i+'"'+(i===cur?' selected':'')+'>'+esc(x)+'</option>'}).join('')+'</select><button class="btn sec sm" id="newEntry">'+ic('plus')+'Add new</button><span class="sp"></span>'+capBar(key)+'</div>'}
function partyOpts(kind){return D.parties.filter(function(p){return p.status==='Active'&&(kind==='any'||p.type===kind||p.type==='Both')}).map(function(p){return p.name})}
var STB={'Draft':'b-neu','Pending approval':'b-warn','Approved':'b-ok','Rejected':'b-err','Pending match':'b-warn','Matched':'b-ok'};
function stBadge(s){return '<span class="badge '+(STB[s]||'b-neu')+'">'+esc(s)+'</span>'}
/* Approval bar on a document (D3) */
function approvalBar(doc,kind,rerender){
  var cfg=D.approvalCfg[kind],lv=+(cfg.levels||1),need2=kind==='Purchase PO'&&cfg.above&&docValue(doc)<+cfg.above?1:lv;
  var who=(doc.level||1)===1?cfg.l1:cfg.l2;var isAppr=ROLE==='Admin'||(ROLE==='Purchase'&&kind==='Purchase PO'&&(doc.level||1)===1);
  var h='<div class="apbar"><span class="cap muted">Status</span><span class="stwrap'+(apPulse?' pulse':'')+'">'+stBadge(doc.status)+'</span>'+(doc.status==='Pending approval'?'<span class="cap muted">Level '+(doc.level||1)+' of '+need2+' · approver '+esc(who)+'</span>':'')+(doc.appr?'<span class="cap muted">Approved by '+doc.appr[0]+' on '+doc.appr[1]+'</span>':'')+(doc.reason?'<span class="cap" style="color:var(--error-700)">Reason: '+esc(doc.reason)+'</span>':'')+'<span class="sp"></span>';
  if(doc._new)h+='<span class="cap muted">Save the entry, then submit it for approval</span>';
  else if(doc.status==='Draft'||doc.status==='Rejected')h+='<button class="btn sec sm" data-ap="submit">'+ic('check')+'Submit for approval</button>';
  if(doc.status==='Pending approval')h+=isAppr?'<button class="btn sec sm" data-ap="reject" style="color:var(--error-700)">'+ic('x')+'Reject</button><button class="btn pri sm" data-ap="approve">'+ic('check')+'Approve</button>':'<span class="cap muted">Log in as an approver to approve</span>';
  h+='</div>';
  apPulse=false;
  setTimeout(function(){$$('[data-ap]').forEach(function(b){b.onclick=function(){var a=b.dataset.ap;apPulse=true;
    if(a==='submit'){doc.status='Pending approval';doc.level=1;doc.reason='';rerender();toast(docNo(doc)+' sent for approval')}
    else if(a==='approve'){if((doc.level||1)<need2){doc.level=2;rerender();toast('Level 1 approved. Waiting for level 2.')}else{doc.status='Approved';doc.appr=[ROLE==='Admin'?'Anil M.':me().name,today()];rerender();toast(docNo(doc)+' approved')}}
    else{modal('Reject '+docNo(doc)+'?','<div class="field"><label for="rjR">Reason<span class="req">*</span></label><textarea class="inp" id="rjR" rows="3"></textarea></div>','<button class="btn gh" data-x>Cancel</button><button class="btn dan" id="rjOk">Reject</button>');
      $('#rjOk').onclick=function(){var r=$('#rjR');if(!r.value.trim()){r.classList.add('err');r.focus();return}doc.status='Rejected';doc.reason=r.value.trim();closeLayer();rerender();toast(docNo(doc)+' rejected')}}}})},0);
  return h;
}
function docNo(d){return d.no||'Entry'}
function docValue(d){return (d.lines||[]).reduce(function(a,l){return a+(+l.qty||0)*(+l.rate||0)},0)}

/* ---------------- Reports (default landing) ---------------- */
R.reports=function(k){
  crumbs([['Reports','reports']]);
  if(!k){view('<div class="blank">'+ic('chart')+'<p style="font-weight:600;color:var(--text-secondary)">Pick a report from the left menu</p><p class="small">Only reports for your role are listed ('+ROLE+'). Admin sees all reports.</p><p class="cap">Default page is blank with reports on the left menu (OTHER POINTS A17).</p><button class="btn sec sm menu-m" style="margin-top:6px" onclick="document.getElementById(\'shell\').classList.add(\'navopen\')">Show reports</button></div>');return}
  var p=k.split('-'),g=p[0],i=+p[1],rep=(REPORTS[g]||[])[i];
  if(!rep||ROLE_REPORTS[ROLE].indexOf(g)<0){go('reports');return}
  var name=rep[0],opt=rep[1].split(',');
  crumbs([['Reports','reports'],[g+' · '+name,'']]);
  var partyKind=/supplier|PO summary|GRN/.test(name)?'Supplier':/customer|Sales|Export sales|Enquir/.test(name)?'Customer':'any';
  view('<div class="repcard"><div class="pagehd"><h1>'+esc(name)+'</h1><span class="badge b-neu">'+g+'</span></div>'+
   '<div class="sectcard"><div class="sh"><h2>Download options</h2><span class="sp"></span></div><div class="sb">'+
    (opt.indexOf('sd')>=0?fld('Layout',seg('lay',['Summary','Detailed'],'Summary'),{hint:'Summary/detailed option to download (SALES A3)'}):'')+
    '<div class="fgrid">'+fld('From date',dt('rf','01/04/2025'),{})+fld('To date',dt('rt','31/03/2026'),{})+
     fld('Unit',sel('ru',['All units'].concat(D.units),'All units',{blank:false}),{})+
     (opt.indexOf('party')>=0?fld('Party',sel('rp',[['','All parties']].concat(partyOpts(partyKind)),'',{blank:false}),{}):'')+
     (opt.indexOf('item')>=0?fld('Item',sel('ri',[['','All items']].concat(D.items.map(function(x){return [x.no,x.no+' · '+x.desc]})),'',{blank:false}),{}):'')+
     (opt.indexOf('trn')>=0?fld('Transporter',sel('rtr',[['','All transporters']].concat(D.transporters),'',{blank:false}),{}):'')+
     fld('Format',seg('rfmt',['Excel (CSV)','PDF'],'Excel (CSV)'),{})+'</div>'+
    (/Export/.test(name)?rule('Export = sales to foreign-currency customers'+(name==='Export/Import'?' and purchases from foreign suppliers':'')+'. Not a data export.'):'')+
    '<div style="display:flex;gap:8px;justify-content:flex-end"><button class="btn pri" id="repdl">'+ic('download')+'Download</button></div>'+
   '</div></div>'+rule('The report downloads on this page, without opening a new page (OTHER POINTS A21). Reports are generated on the server (A20)')+'</div>');
  wireSeg($('#view'));
  $('#repdl').onclick=function(){var b=this;b.innerHTML='<span class="spin"></span>Preparing…';b.disabled=true;setTimeout(function(){b.innerHTML=ic('download')+'Download';b.disabled=false;toast(esc(name)+(opt.indexOf('sd')>=0?' ('+segVal('lay')+')':'')+' · '+segVal('rfmt')+' would download here (sample)')},reduce()?50:700)};
};

/* ---------------- Dashboard (D18) ---------------- */
function tileVal(t){
  var below=D.items.filter(function(i){return i.stock<+i.min}).length;
  return {'Open quotations':D.quotes.length,'Sales orders this month':D.sos.filter(function(s){return /\/10\/2025/.test(s.start)}).length,'Invoices this month':D.invoices.length,'Items below minimum stock':below,
   'Open purchase orders':D.pos.length,'GRNs pending match':1,'GRNs pending quality approval':1,'Receivables outstanding':money(37216),'Payables outstanding':money(49560),'Expenses this month':money(18400)}[t];
}
R.dashboard=function(){
  crumbs([['Dashboard','dashboard']]);
  var roles=ROLE==='Admin'?['Sales','Purchase','Accounts']:[ROLE];
  view('<div class="pagehd"><h1>Dashboard</h1>'+SAMPLE+'<span class="sp"></span><span class="cap muted">FY 2025-26 · '+esc($('#unitName').textContent)+' · role '+ROLE+'</span></div>'+
   roles.map(function(r){return (roles.length>1?'<div class="lbl" style="margin:6px 0 8px">'+r+'</div>':'')+'<div class="kpis" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:16px">'+D.dashTiles[r].map(function(t){return '<div class="card kpi"><span class="ovl">'+t+'</span><span class="v">'+tileVal(t)+'</span><span class="d">Sample figure</span></div>'}).join('')+'</div>'}).join('')+
   '<div class="sectcard"><div class="sh"><h2>Latest entries</h2></div><div class="sb" style="padding-top:6px"><div class="tablecard"><table class="atable"><thead><tr><th>Entry</th><th>Party</th><th>Date</th><th>Status</th></tr></thead><tbody>'+
    [['SO/25-26/0145','Kaveri Precision Engineering','01/10/2025','Pending approval','sales.so'],['PO/25-26/0081','Shree Ganesh Castings','02/10/2025','Pending approval','po'],['INV/25-26/0815','Apex Auto Components','29/09/2025','Approved','sales'],['QT/25-26/0031','Orion Agro Equipment','12/08/2025','Draft','quotation']].map(function(r){return '<tr data-go="'+r[4]+'"><td class="docno">'+r[0]+'</td><td>'+r[1]+'</td><td class="num">'+r[2]+'</td><td>'+stBadge(r[3])+'</td></tr>'}).join('')+
   '</tbody></table></div>'+rule('Admin › Dashboard chooses which tiles each role sees.')+'</div></div>');
};

/* ---------------- CRM: Quotation ---------------- */
var qSel=0;
var FOLLOW=['New','Contacted','Quotation sent','Negotiation','Won','Lost'];
R.quotation=function(){
  crumbs([['CRM','quotation'],['Quotation','quotation']]);
  var isNew=qSel<0, q=isNew?{enq:'ENQ/25-26/0045',qt:'QT/25-26/0032',rev:'0',customer:'',prospect:'',src:'',ref:'',project:'',sp:'Priya S.',date:today(),cur:'INR',follow:'New',lines:[{no:'',desc:'',vol:'',rate:'',sample:'',prod:'',tech:''}]}:D.quotes[qSel];
  var list=D.quotes.map(function(x){return x.qt+' · Rev '+x.rev+' · '+(x.customer||x.prospect)});if(isNew)list.push('New quotation');
  view('<div class="pagehd"><h1>Quotation</h1>'+SAMPLE+'</div>'+entryBar('Entry',list,isNew?list.length-1:qSel,'quotation')+
   '<div class="sectcard"><div class="sh"><h2>Enquiry and quotation</h2><span class="sp"></span><div class="autono"><span>Enquiry no <b>'+q.enq+'</b></span><span>Quotation no <b>'+q.qt+'</b></span><span>Rev no <b>'+q.rev+'</b></span></div>'+(isNew?'':'<button class="btn sec sm" id="qRev">'+ic('refresh')+'Revise</button>')+'</div><div class="sb">'+
    rule('Enquiry no, Quotation no and Rev no are generated automatically (CRM A16). Revise adds 1 to Rev no and keeps the earlier revision read-only')+
    '<div class="fgrid">'+
     fld('Customer',sel('qCust',partyOpts('Customer'),q.customer),{for:'qCust',hint:'Customer or prospect is required'})+
     fld('Prospect','<div style="display:flex;gap:6px">'+sel('qPros',D.prospects.map(function(p){return p.name}),q.prospect)+'<button class="btn sec" id="addPros" type="button">'+ic('plus')+'Add</button></div>',{hint:'Add opens the Prospect form in this window (CRM H9).'})+
     fld('Source of enquiry',inp('qSrc',q.src),{for:'qSrc'})+fld('Reference',inp('qRef',q.ref),{for:'qRef'})+fld('Project name',inp('qProj',q.project),{for:'qProj'})+
     fld('Sales person',sel('qSp',D.users.filter(function(u){return u.role==='Sales'}).map(function(u){return u.name}),q.sp),{req:1,for:'qSp',hint:'Users with the Sales role'})+fld('Enquiry receipt date',dt('qDate',q.date),{req:1,for:'qDate'})+
     fld('Currency',sel('qCur',D.masters['Currency/Country'].rows.map(function(r){return r[1]}),q.cur),{for:'qCur'})+fld('Followup status',sel('qFol',FOLLOW,q.follow),{for:'qFol'})+
    '</div>'+
    '<div class="lbl">Items</div><div class="tablecard" style="overflow-x:auto"><table class="atable lines"><thead><tr><th>Item no</th><th>Item description</th><th class="r">Annual volume</th><th class="r">Rate</th><th>Sample target date</th><th>Production target date</th><th>Technical details</th><th></th></tr></thead><tbody id="qLines"></tbody></table></div>'+
    '<div><button class="btn gh sm" id="qAdd">'+ic('plus')+'Add item</button></div>'+rule('Rate defaults to the Item master price and can be edited')+
    '<div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap"><button class="btn sec" data-cap="pdf">'+ic('file')+'Generate PDF</button><button class="btn pri" id="qSave">Save</button></div>'+
   '</div></div>');
  var lines=q.lines.map(function(l){return Object.assign({},l)});
  function drawLines(){$('#qLines').innerHTML=lines.map(function(l,i){return '<tr style="cursor:default"><td>'+sel('qn'+i,D.items.filter(function(x){return x.type!=='Purchase'}).map(function(x){return x.no}),l.no)+'</td><td><input class="inp" data-f="desc" data-i="'+i+'" value="'+esc(l.desc)+'"></td><td><input class="inp num" data-f="vol" data-i="'+i+'" value="'+esc(l.vol)+'" inputmode="numeric"></td><td><input class="inp num" data-f="rate" data-i="'+i+'" value="'+esc(l.rate)+'" inputmode="decimal"></td><td><input class="inp num" data-f="sample" data-i="'+i+'" value="'+esc(l.sample)+'" placeholder="dd/mm/yyyy"></td><td><input class="inp num" data-f="prod" data-i="'+i+'" value="'+esc(l.prod)+'" placeholder="dd/mm/yyyy"></td><td><input class="inp" data-f="tech" data-i="'+i+'" value="'+esc(l.tech)+'"></td><td class="del"><button class="btn gh icon sm" data-del="'+i+'" aria-label="Remove item">'+ic('x')+'</button></td></tr>'}).join('');
    lines.forEach(function(l,i){$('#qn'+i).onchange=function(){var it=item(this.value);l.no=this.value;l.desc=it.desc||'';l.rate=it.price||'';drawLines()}});
    $$('#qLines [data-f]').forEach(function(e){e.oninput=function(){lines[+e.dataset.i][e.dataset.f]=e.value}});
    $$('#qLines [data-del]').forEach(function(b){b.onclick=function(){lines.splice(+b.dataset.del,1);drawLines()}});}
  drawLines();
  $('#qAdd').onclick=function(){lines.push({no:'',desc:'',vol:'',rate:'',sample:'',prod:'',tech:''});drawLines()};
  $('#entrySel').onchange=function(){qSel=+this.value;if(qSel>=D.quotes.length)qSel=-1;R.quotation()};
  $('#newEntry').onclick=function(){qSel=-1;R.quotation()};
  var rv=$('#qRev');if(rv)rv.onclick=function(){q.rev=String(+q.rev+1);R.quotation();toast(q.qt+' revised to Rev '+q.rev+'. Rev '+(q.rev-1)+' is kept read-only.')};
  $('#addPros').onclick=function(){prospectDrawer(function(n){R.quotation();$('#qPros').value=n})};
  $('#qSave').onclick=function(){
    if(!$('#qCust').value&&!$('#qPros').value){$('#qCust').classList.add('err');toast('Select a customer or a prospect','err');$('#qCust').focus();return}
    if(!need(['qSp','qDate']))return;
    if(!lines.some(function(l){return l.no})){toast('Add at least one item','err');return}
    if(isNew){D.quotes.push({enq:q.enq,qt:q.qt,rev:'0',customer:$('#qCust').value,prospect:$('#qPros').value,src:$('#qSrc').value,ref:$('#qRef').value,project:$('#qProj').value,sp:$('#qSp').value,date:$('#qDate').value,cur:$('#qCur').value,follow:$('#qFol').value,lines:lines});qSel=D.quotes.length-1;R.quotation()}
    else{q.follow=$('#qFol').value;q.lines=lines}
    toast(q.qt+' saved (sample)')};
  wireCap($('#view'),'Quotation');
};

/* ---------------- CRM: Prospect ---------------- */
function prospectForm(p){p=p||{};return '<div class="fgrid">'+
  fld('Prospect name',inp('pName',p.name),{req:1,for:'pName'})+fld('Domestic / Export',seg('pDE',['Domestic','Export'],p.de||'Domestic'),{req:1})+
  fld('Contact person',inp('pCon',p.contact),{for:'pCon'})+fld('Position',inp('pPos',p.pos),{for:'pPos'})+
  fld('Contact no 1',inp('pPh1',p.ph1,{mode:'tel'}),{req:1,for:'pPh1'})+fld('Contact no 2',inp('pPh2',p.ph2,{mode:'tel'}),{for:'pPh2'})+
  fld('Email',inp('pEm',p.email,{mode:'email'}),{for:'pEm'})+fld('GSTIN',inp('pGst',p.gst),{for:'pGst'})+fld('Industry type',inp('pInd',p.ind),{for:'pInd'})+
  fld('Address','<textarea class="inp" id="pAddr" rows="2">'+esc(p.addr)+'</textarea>',{span:1,for:'pAddr'})+'</div>'+rule('Mandatory: Prospect name, Domestic/Export, Contact no 1')}
function readProspect(){return {name:$('#pName').value.trim(),de:segVal('pDE'),contact:$('#pCon').value,pos:$('#pPos').value,ph1:$('#pPh1').value,ph2:$('#pPh2').value,email:$('#pEm').value,gst:$('#pGst').value,ind:$('#pInd').value,addr:$('#pAddr').value}}
function prospectDrawer(done){
  drawer('Add prospect',prospectForm(),'<button class="btn gh" data-x>Cancel</button><button class="btn pri" id="pdSave">Save prospect</button>');
  wireSeg($('#layer'));
  $('#pdSave').onclick=function(){if(!need(['pName','pPh1']))return;var p=readProspect();D.prospects.push(p);closeLayer();done(p.name);toast(esc(p.name)+' added and selected')};
}
var pSel=0;
R.prospect=function(){
  crumbs([['CRM','quotation'],['Prospect','prospect']]);
  var isNew=pSel<0,p=isNew?{}:D.prospects[pSel];
  var list=D.prospects.map(function(x){return x.name});if(isNew)list.push('New prospect');
  view('<div class="pagehd"><h1>Prospect</h1>'+SAMPLE+'</div>'+entryBar('Entry',list,isNew?list.length-1:pSel,'prospect')+
   '<div class="sectcard"><div class="sh"><h2>Prospect details</h2></div><div class="sb">'+prospectForm(p)+
   '<div style="display:flex;justify-content:flex-end"><button class="btn pri" id="pSave">Save</button></div></div></div>');
  wireSeg($('#view'));
  $('#entrySel').onchange=function(){pSel=+this.value;if(pSel>=D.prospects.length)pSel=-1;R.prospect()};
  $('#newEntry').onclick=function(){pSel=-1;R.prospect()};
  $('#pSave').onclick=function(){if(!need(['pName','pPh1']))return;var np=readProspect();if(isNew){D.prospects.push(np);pSel=D.prospects.length-1}else D.prospects[pSel]=np;R.prospect();toast(esc(np.name)+' saved (sample)')};
  wireCap($('#view'),'Prospect');
};

/* ---------------- Sales: Tax invoice + Sales order on one page ---------------- */
var iSel=0, sSel=0, soOpen=false;
R.sales=function(a){
  if(a==='so')soOpen=true;
  crumbs([['Sales','sales'],[soOpen?'Tax Invoice & Sales Order':'Tax Invoice','sales']]);
  var invNew=iSel<0, inv=invNew?{no:'',party:'',type:'Sales',po:'',mode:'Road',trn:'',veh:'',dist:'',pf:'0.00',lines:[],audit:null}:D.invoices[iSel];
  var soNew=sSel<0, s=soNew?{no:'SO/25-26/'+String(nextSo).padStart(4,'0'),party:'',po:'',start:today(),end:'',rev:'0',revDate:'',file:'',status:'Draft',_new:1,lines:[{item:'',qty:'',rate:'',del:''}],audit:null}:D.sos[sSel];
  var iList=D.invoices.map(function(x){return x.no+' · '+x.party});if(invNew)iList.push('New tax invoice');
  var sList=D.sos.map(function(x){return x.no+' · '+x.party});if(soNew)sList.push('New sales order');
  var nextNo=function(t){return (t==='Sales'?'INV':t==='Credit'?'CN':'DN')+'/25-26/'+String(nextInv[t]).padStart(4,'0')};
  view('<div class="pagehd"><h1>Sales</h1>'+SAMPLE+'<span class="sp"></span><span class="cap muted">Sales order and sales invoice are on this single page (SALES A24)</span></div>'+
   /* Tax invoice */
   '<div class="sectcard" id="invCard"><div class="sh"><h2>Tax invoice</h2><div class="autono"><span>Invoice no <b id="invNo">'+(inv.no||nextNo(inv.type)+' (on save)')+'</b></span></div><span class="sp"></span>'+capBar('invoice')+'</div><div class="sb">'+
    '<div class="entrybar" style="margin:0"><label for="invSel">Entry</label><select class="inp" id="invSel">'+iList.map(function(x,i){return '<option value="'+i+'"'+(i===(invNew?iList.length-1:iSel)?' selected':'')+'>'+esc(x)+'</option>'}).join('')+'</select><button class="btn sec sm" id="invNew">'+ic('plus')+'Add new</button></div>'+
    '<div class="fgrid">'+
     fld('Party name',sel('iParty',partyOpts('Customer'),inv.party),{req:1,for:'iParty'})+
     fld('Invoice type',seg('iType',['Sales','Credit','Debit'],inv.type),{req:1})+
     fld('PO no','<select class="inp" id="iPo"></select>',{for:'iPo',hint:'Lists the selected party’s approved sales order POs.'})+
     fld('Shipping address','<select class="inp" id="iShip"></select>',{req:1,for:'iShip'})+
     fld('Mode of transport',sel('iMode',['Road','Rail','Air','Ship'],inv.mode),{for:'iMode'})+
     fld('Transporter',sel('iTrn',D.transporters,inv.trn),{for:'iTrn'})+
     fld('Vehicle no',inp('iVeh',inv.veh),{for:'iVeh'})+
     fld('Distance (km)',inp('iDist',inv.dist,{num:1,mode:'decimal'}),{for:'iDist'})+
     fld('P&amp;F charges (₹)',inp('iPf',inv.pf,{num:1,mode:'decimal'}),{for:'iPf'})+
    '</div>'+
    '<div class="lbl">Items</div><div id="iLinesWrap"></div><div id="iTotals"></div>'+
    audit(inv.audit)+
    '<div style="display:flex;gap:8px;justify-content:flex-end"><button class="btn pri" id="iSave">Save tax invoice</button></div>'+
   '</div></div>'+
   /* Sales order */
   '<div class="sectcard'+(soOpen?'':' collapsed')+'" id="soCard"><div class="sh"><h2>Sales order</h2><span class="cap muted">Hidden by default (SALES B10)</span><span class="sp"></span>'+(soOpen?capBar('salesorder'):'')+'<button class="btn sec sm" id="soToggle" aria-expanded="'+soOpen+'">'+(soOpen?'Hide sales order':'Show sales order')+'</button></div><div class="sb">'+
    '<div class="entrybar" style="margin:0"><label for="soSel">Entry</label><select class="inp" id="soSel">'+sList.map(function(x,i){return '<option value="'+i+'"'+(i===(soNew?sList.length-1:sSel)?' selected':'')+'>'+esc(x)+'</option>'}).join('')+'</select><button class="btn sec sm" id="soNew">'+ic('plus')+'Add new</button><div class="autono"><span>SO no <b>'+s.no+'</b></span></div></div>'+
    (soOpen?approvalBar(s,'Sales Order',function(){R.sales('so')}):'')+
    '<div class="fgrid">'+
     fld('Party name',sel('sParty',partyOpts('Customer'),s.party),{req:1,for:'sParty'})+
     fld('PO number',inp('sPo',s.po),{req:1,for:'sPo'})+
     fld('Start date',dt('sStart',s.start),{req:1,for:'sStart'})+fld('End date',dt('sEnd',s.end),{for:'sEnd'})+
     fld('Rev no',inp('sRev',s.rev,{num:1}),{for:'sRev'})+fld('Rev date',dt('sRevD',s.revDate),{for:'sRevD'})+
     fld('Upload PO','<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><button class="btn sec sm" id="sUp" type="button">'+ic('upload')+'Choose file</button><span class="small sec2" id="sFile">'+(s.file?esc(s.file):'No file chosen')+'</span></div>',{})+
    '</div>'+
    '<div class="lbl">Items</div><div class="tablecard" style="overflow-x:auto"><table class="atable lines"><thead><tr><th>Item no / Description / IPN</th><th class="r" style="width:120px">Quantity</th><th class="r" style="width:120px">Rate</th><th style="width:150px">Delivery date</th><th></th></tr></thead><tbody id="sLines"></tbody></table></div>'+
    '<div><button class="btn gh sm" id="sAdd">'+ic('plus')+'Add item</button></div>'+
    audit(s.audit)+
    '<div style="display:flex;justify-content:flex-end"><button class="btn pri" id="sSave">Save sales order</button></div>'+
   '</div></div>');
  wireSeg($('#view'),function(id,v){if(id==='iType'&&invNew)$('#invNo').textContent=nextNo(v)+' (on save)';if(id==='iType')drawInvLines()});wireCap($('#invCard'),'Sales invoice');wireCap($('#soCard'),'Sales order');

  /* invoice behaviour */
  var lines=inv.lines.map(function(l){return Object.assign({},l)});
  function custSOs(p){return D.sos.filter(function(x){return x.party===p&&x.status==='Approved'})}
  function fillPo(keep){var p=$('#iParty').value,list=custSOs(p);
    $('#iPo').innerHTML='<option value="">No PO selected</option>'+list.map(function(x){return '<option value="'+esc(x.po)+'"'+(x.po===keep?' selected':'')+'>'+esc(x.po)+' · '+x.no+'</option>'}).join('');
    var pa=party(p);$('#iShip').innerHTML=pa?'<option>'+esc(pa.ship)+'</option>':'<option value="">Select a party first</option>'}
  function poSO(){var po=$('#iPo').value;return D.sos.filter(function(x){return x.po===po})[0]}
  function drawInvLines(){
    var po=$('#iPo').value,p=$('#iParty').value,h='';
    if(!p){$('#iLinesWrap').innerHTML='<p class="small muted">Select a party to list its parts.</p>';$('#iTotals').innerHTML='';return}
    if(po){
      var so=poSO();
      if(!lines.length||lines.po!==po){lines=so.lines.map(function(l){return {item:l.item,qty:l.bal,max:l.bal,rate:l.rate}});lines.po=po}
      h='<div class="tablecard" style="overflow-x:auto"><table class="atable lines"><thead><tr><th>Item no / Description</th><th class="r" style="width:170px">Quantity</th><th class="r">Rate</th><th class="r">Amount</th></tr></thead><tbody>'+lines.map(function(l,i){var bad=+l.qty>l.max||!(+l.qty>0);return '<tr style="cursor:default"><td>'+l.item+'<span class="sub">'+esc(item(l.item).desc)+'</span></td><td class="r"><input class="inp num'+(bad?' err':'')+'" data-iq="'+i+'" value="'+esc(l.qty)+'" inputmode="numeric" aria-label="Quantity for '+l.item+'"><span class="qlock">'+ic('lock','width:11px')+'Max '+fmt(l.max)+' (PO balance)</span></td><td class="r num">'+(+l.rate).toFixed(2)+'<span class="qlock">'+ic('lock','width:11px')+'From SO</span></td><td class="r num">'+money((+l.qty||0)*(+l.rate))+'</td></tr>'}).join('')+'</tbody></table></div>'+
       rule('Only parts against the selected PO are shown (SALES A21). For PO parts the quantity cannot exceed the PO balance but can be lower, and the rate comes from the SO (SALES A23)');
    } else {
      if(lines.po){lines=[];}
      if(!lines.length)lines=[{item:'',qty:'',rate:''}];
      var parts=D.items.filter(function(x){return x.party===p&&x.type!=='Purchase'}).map(function(x){return [x.no,x.no+' · '+x.desc]});
      h='<div class="tablecard" style="overflow-x:auto"><table class="atable lines"><thead><tr><th>Item no / Description</th><th class="r" style="width:140px">Quantity</th><th class="r" style="width:130px">Rate</th><th class="r">Amount</th><th></th></tr></thead><tbody>'+lines.map(function(l,i){return '<tr style="cursor:default"><td>'+sel('ii'+i,parts,l.item)+'</td><td class="r"><input class="inp num" data-iq="'+i+'" value="'+esc(l.qty)+'" inputmode="numeric" aria-label="Quantity"></td><td class="r"><input class="inp num" data-ir="'+i+'" value="'+esc(l.rate)+'" inputmode="decimal" aria-label="Rate"></td><td class="r num">'+money((+l.qty||0)*(+l.rate||0))+'</td><td class="del"><button class="btn gh icon sm" data-idel="'+i+'" aria-label="Remove item">'+ic('x')+'</button></td></tr>'}).join('')+'</tbody></table></div><div><button class="btn gh sm" id="iAdd">'+ic('plus')+'Add item</button></div>'+
       rule('No PO no selected, so all parts of this customer are in the drop down (SALES A22). Quantity is free for non-PO parts; rate defaults to the Item master price');
    }
    $('#iLinesWrap').innerHTML=h;
    $$('[data-iq]').forEach(function(e){e.onchange=function(){lines[+e.dataset.iq].qty=e.value.replace(/,/g,'');drawInvLines()}});
    $$('[data-ir]').forEach(function(e){e.onchange=function(){lines[+e.dataset.ir].rate=e.value;drawInvLines()}});
    lines.forEach(function(l,i){var s=$('#ii'+i);if(s)s.onchange=function(){l.item=s.value;l.rate=item(s.value).price;drawInvLines()}});
    $$('[data-idel]').forEach(function(b){b.onclick=function(){lines.splice(+b.dataset.idel,1);drawInvLines()}});
    var ad=$('#iAdd');if(ad)ad.onclick=function(){lines.push({item:'',qty:'',rate:''});drawInvLines()};
    drawTotals();
  }
  function drawTotals(){var p=$('#iParty').value;if(!p){$('#iTotals').innerHTML='';return}
    var taxable=lines.reduce(function(a,l){return a+(+l.qty||0)*(+l.rate||0)},0),pf=+$('#iPf').value||0,base=taxable+pf,pct=lines[0]&&lines[0].item?taxPct(item(lines[0].item).tax):18,gst=base*pct/100,inter=interState(p);
    $('#iTotals').innerHTML='<div class="card" style="background:var(--surface-sunken);max-width:420px;margin-left:auto"><div class="cb2" style="padding-top:12px"><dl class="kv num" style="grid-template-columns:1fr auto"><dt>Taxable value</dt><dd style="text-align:right">'+money(taxable)+'</dd><dt>P&amp;F charges</dt><dd style="text-align:right">'+money(pf)+'</dd>'+(inter?'<dt>IGST '+pct+'%</dt><dd style="text-align:right">'+money(gst)+'</dd>':'<dt>CGST '+pct/2+'%</dt><dd style="text-align:right">'+money(gst/2)+'</dd><dt>SGST '+pct/2+'%</dt><dd style="text-align:right">'+money(gst/2)+'</dd>')+'<dt style="font-weight:600;color:var(--text-primary)">Grand total</dt><dd style="text-align:right;font-size:16px;font-weight:600">'+money(base+gst)+'</dd></dl><p class="cap muted" style="margin-top:6px">'+(inter?'Out-of-state customer: IGST':'Same state as the company: CGST + SGST')+' (by GSTIN state code)</p></div></div>'}
  fillPo(inv.po);if(inv.po){var so0=poSO();lines=so0.lines.map(function(l){var m=inv.lines.filter(function(x){return x.item===l.item})[0];return {item:l.item,qty:m?m.qty:l.bal,max:l.bal,rate:l.rate}});lines.po=inv.po}
  drawInvLines();
  $('#iPf').oninput=drawTotals;
  $('#iParty').onchange=function(){lines=[];fillPo('');drawInvLines()};
  $('#iPo').onchange=function(){lines=[];drawInvLines()};
  $('#invSel').onchange=function(){iSel=+this.value;if(iSel>=D.invoices.length)iSel=-1;R.sales(soOpen?'so':'')};
  $('#invNew').onclick=function(){iSel=-1;R.sales(soOpen?'so':'')};
  $('#iSave').onclick=function(){
    if(!need(['iParty','iShip']))return;
    if(!lines.some(function(l){return l.item&&+l.qty>0})){toast('Add at least one item with a quantity','err');return}
    if(lines.po&&lines.some(function(l){return +l.qty>l.max||!(+l.qty>0)})){toast('Quantity is above the PO balance for a PO part','err');var b=$('#iLinesWrap .err');if(b)b.focus();return}
    var t=segVal('iType'),no=inv.no||nextNo(t);if(!inv.no)nextInv[t]++;
    var rec={no:no,party:$('#iParty').value,type:t,po:$('#iPo').value,mode:$('#iMode').value,trn:$('#iTrn').value,veh:$('#iVeh').value,dist:$('#iDist').value,pf:$('#iPf').value,lines:lines.map(function(l){return {item:l.item,qty:l.qty}}),audit:invNew?[me().name,today(),me().name,today()]:[inv.audit[0],inv.audit[1],me().name,today()]};
    if(invNew){D.invoices.push(rec);iSel=D.invoices.length-1}else D.invoices[iSel]=rec;
    R.sales(soOpen?'so':'');toast(no+' saved (sample)')};

  /* sales order behaviour */
  $('#soToggle').onclick=function(){soOpen=!soOpen;R.sales(soOpen?'so':'')};
  var sl=s.lines.map(function(l){return Object.assign({},l)});
  function drawSo(){var p=$('#sParty').value,parts=D.items.filter(function(x){return (!p||x.party===p)&&x.type!=='Purchase'}).map(function(x){return [x.no,x.no+' · '+x.desc+' · IPN '+x.ino]});
    $('#sLines').innerHTML=sl.map(function(l,i){return '<tr style="cursor:default"><td>'+sel('si'+i,parts,l.item)+'</td><td class="r"><input class="inp num" data-sq="'+i+'" value="'+esc(l.qty)+'" inputmode="numeric" aria-label="Quantity"></td><td class="r"><input class="inp num" data-sr="'+i+'" value="'+esc(l.rate)+'" inputmode="decimal" aria-label="Rate"></td><td><input class="inp num" data-sd="'+i+'" value="'+esc(l.del)+'" placeholder="dd/mm/yyyy" aria-label="Delivery date"></td><td class="del"><button class="btn gh icon sm" data-sdel="'+i+'" aria-label="Remove item">'+ic('x')+'</button></td></tr>'}).join('');
    sl.forEach(function(l,i){$('#si'+i).onchange=function(){l.item=this.value;l.rate=item(this.value).price;drawSo()}});
    $$('[data-sq]').forEach(function(e){e.oninput=function(){sl[+e.dataset.sq].qty=e.value}});
    $$('[data-sr]').forEach(function(e){e.oninput=function(){sl[+e.dataset.sr].rate=e.value}});
    $$('[data-sd]').forEach(function(e){e.oninput=function(){sl[+e.dataset.sd].del=e.value}});
    $$('[data-sdel]').forEach(function(b){b.onclick=function(){sl.splice(+b.dataset.sdel,1);drawSo()}});}
  drawSo();
  $('#sParty').onchange=drawSo;
  $('#sAdd').onclick=function(){sl.push({item:'',qty:'',rate:'',del:''});drawSo()};
  $('#sUp').onclick=function(){$('#sFile').textContent='Customer_PO.pdf (sample)';toast('A file picker opens here in the real app')};
  $('#soSel').onchange=function(){sSel=+this.value;if(sSel>=D.sos.length)sSel=-1;R.sales('so')};
  $('#soNew').onclick=function(){sSel=-1;R.sales('so')};
  $('#sSave').onclick=function(){if(!need(['sParty','sPo','sStart']))return;
    if(!sl.some(function(l){return l.item&&+l.qty>0&&l.del})){toast('Add at least one item with quantity and delivery date','err');return}
    var rec={no:s.no,party:$('#sParty').value,po:$('#sPo').value,start:$('#sStart').value,end:$('#sEnd').value,rev:$('#sRev').value,revDate:$('#sRevD').value,file:$('#sFile').textContent==='No file chosen'?'':$('#sFile').textContent,status:s.status==='Approved'?'Approved':'Draft',appr:s.appr,lines:sl.map(function(l){return {item:l.item,qty:+l.qty||0,rate:l.rate,del:l.del,bal:+l.qty||0}}),audit:soNew?[me().name,today(),me().name,today()]:[s.audit[0],s.audit[1],me().name,today()]};
    if(soNew){D.sos.push(rec);sSel=D.sos.length-1;nextSo++}else D.sos[sSel]=rec;
    R.sales('so');toast(rec.no+' saved as '+rec.status+' (sample)')};
};

/* ---------------- Purchase: GRN entry ---------------- */
var QA=['Pending','Approved','Rejected'];
R.grn=function(){
  crumbs([['Purchase','grn'],['GRN Entry','grn']]);
  var lines=[{po:'PO/25-26/0077',item:'CST-4410',qty:'500'},{po:'PO/25-26/0077',item:'CST-4412',qty:'300'}];
  view('<div class="pagehd"><h1>GRN entry</h1>'+SAMPLE+'</div>'+
   '<div class="entrybar"><label>Entry</label><select class="inp" id="entrySel"><option>New GRN entry</option><option>GRN/25-26/0232 · Shree Ganesh Castings</option></select><span class="sp"></span>'+capBar('grn')+'</div>'+
   '<div class="sectcard"><div class="sh"><h2>Supplier invoice</h2></div><div class="sb"><div class="fgrid">'+
    fld('Party name',sel('gParty',partyOpts('Supplier'),'Shree Ganesh Castings'),{req:1,for:'gParty',hint:'Customers are not listed: a customer party can’t be used on purchase (ADMIN A23).'})+
    fld('Invoice type',seg('gType',['Purchase','Credit','Debit'],'Purchase'),{req:1})+
    fld('Invoice no',inp('gInv','SGC/2025/1187'),{req:1,for:'gInv'})+fld('Invoice date',dt('gDate','01/10/2025'),{req:1,for:'gDate'})+
    fld('Invoice amount incl. GST (₹)',inp('gAmt','175230.00',{num:1,mode:'decimal'}),{req:1,for:'gAmt'})+
    fld('Transporter',sel('gTrn',D.transporters,'Sai Logistics'),{for:'gTrn'})+fld('Vehicle no',inp('gVeh','MH 09 CD 4410'),{for:'gVeh'})+
    fld('E-way bill no',inp('gEw','281009876543',{num:1}),{for:'gEw'})+fld('E-way bill date',dt('gEwd','01/10/2025'),{for:'gEwd'})+
    fld('Quality approval',sel('gQa',QA,'Pending',{blank:false}),{for:'gQa',hint:'Stock is usable only after Approved.'})+
    fld('Customer name',sel('gCust',partyOpts('Customer'),''),{for:'gCust',hint:'Optional: material bought against a customer order.'})+
    fld('SO no','<select class="inp" id="gSo"><option value="">Select a customer first</option></select>',{for:'gSo'})+
   '</div></div></div>'+
   '<div class="sectcard"><div class="sh"><h2>Items</h2></div><div class="sb"><div id="gLines"></div><div><button class="btn gh sm" id="gAdd">'+ic('plus')+'Add item</button></div>'+
    rule('With a PO no, only that PO’s parts are listed and quantity cannot exceed the PO quantity (PURCHASE A21). Without a PO no, all parts of the supplier are listed (PURCHASE A20).')+
   '</div></div>'+
   '<div class="sectcard"><div class="sh"><h2>Amount match, GRN no and GRN date</h2></div><div class="sb">'+
    '<div id="gMatch"></div>'+
    '<div class="grnno" id="grnBox"><span class="ovl">GRN no · GRN date</span><span class="num" id="grnVal" style="font-size:16px;font-weight:600;color:var(--text-disabled)">Generated when the amount matches</span></div>'+
    rule('If amount is matched, generate GRN no and show it with GRN date (PURCHASE A22). Match = supplier invoice amount equals Σ(qty × PO rate) + GST within ±₹1. On a mismatch the entry can be saved as "Pending match"')+
    audit(null)+
    '<div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap"><button class="btn sec" id="gPend">Save as pending match</button><button class="btn pri" id="gSave">Save and generate GRN no</button></div>'+
   '</div></div>');
  wireSeg($('#view'));wireCap($('#view'),'GRN entry');
  $('#gCust').onchange=function(){var c=this.value;$('#gSo').innerHTML=c?'<option value="">Select</option>'+D.sos.filter(function(s){return s.party===c}).map(function(s){return '<option>'+s.no+'</option>'}).join(''):'<option value="">Select a customer first</option>'};
  function poLine(po,it){var p=D.pos.filter(function(x){return x.no===po})[0];if(!p)return null;return p.lines.filter(function(x){return x.item===it})[0]||null}
  function calc(){var t=0;lines.forEach(function(l){var pl=l.po?poLine(l.po,l.item):null,rate=pl?+pl.rate:+item(l.item).price||0;t+=(+l.qty||0)*rate*(1+taxPct(item(l.item).tax)/100)});return t}
  function drawMatch(){var c=calc(),a=+$('#gAmt').value||0,ok=Math.abs(c-a)<=1;
    $('#gMatch').innerHTML='<dl class="kv num" style="grid-template-columns:1fr auto;max-width:420px"><dt>Supplier invoice amount</dt><dd style="text-align:right">'+money(a)+'</dd><dt>Calculated (qty × PO rate + GST)</dt><dd style="text-align:right">'+money(c)+'</dd><dt>Difference</dt><dd style="text-align:right;color:'+(ok?'var(--success-700)':'var(--error-700)')+'">'+money(a-c)+'</dd></dl>'+(ok?'<span class="badge b-ok">'+ic('check')+'Amount matched</span>':'<span class="badge b-err">'+ic('x')+'Amount not matched</span>');return ok}
  function draw(){var sup=$('#gParty').value,pos=D.pos.filter(function(p){return p.party===sup&&p.status==='Approved'}).map(function(p){return p.no});
    $('#gLines').innerHTML='<div class="tablecard" style="overflow-x:auto"><table class="atable lines"><thead><tr><th style="width:170px">PO no</th><th>Item no / Description</th><th class="r" style="width:170px">Invoice qty</th><th class="r">Rate</th><th></th></tr></thead><tbody>'+
     lines.map(function(l,i){var parts=l.po?(D.pos.filter(function(p){return p.no===l.po})[0]||{lines:[]}).lines.map(function(x){return [x.item,x.item+' · '+item(x.item).desc]}):D.items.filter(function(x){return x.party===sup&&x.type!=='Sales'}).map(function(x){return [x.no,x.no+' · '+x.desc]});
       var pl=l.po?poLine(l.po,l.item):null,max=pl?pl.qty:null,bad=max!=null&&+l.qty>max,rate=pl?pl.rate:item(l.item).price;
       return '<tr style="cursor:default"><td>'+sel('gp'+i,pos,l.po,{})+'</td><td>'+sel('gi'+i,parts,l.item)+'</td><td class="r"><input class="inp num'+(bad?' err':'')+'" data-gq="'+i+'" value="'+esc(l.qty)+'" inputmode="numeric" aria-label="Invoice quantity">'+(max!=null?'<span class="qlock">'+ic('lock','width:11px')+'Max '+fmt(max)+' (PO qty)</span>':'')+'</td><td class="r num">'+(l.item?(+rate).toFixed(2):'')+'</td><td class="del"><button class="btn gh icon sm" data-gdel="'+i+'" aria-label="Remove item">'+ic('x')+'</button></td></tr>'}).join('')+'</tbody></table></div>';
    lines.forEach(function(l,i){$('#gp'+i).onchange=function(){l.po=this.value;l.item='';draw()};$('#gi'+i).onchange=function(){l.item=this.value;draw()}});
    $$('[data-gq]').forEach(function(e){e.onchange=function(){lines[+e.dataset.gq].qty=e.value.replace(/,/g,'');draw()}});
    $$('[data-gdel]').forEach(function(b){b.onclick=function(){lines.splice(+b.dataset.gdel,1);draw()}});drawMatch()}
  draw();
  $('#gAmt').oninput=drawMatch;
  $('#gParty').onchange=function(){lines=[{po:'',item:'',qty:''}];draw()};
  $('#gAdd').onclick=function(){lines.push({po:'',item:'',qty:''});draw()};
  function valid(){if(!need(['gParty','gInv','gDate','gAmt']))return false;var bad=lines.some(function(l){var pl=l.po?poLine(l.po,l.item):null;return !l.item||!(+l.qty>0)||(pl&&+l.qty>pl.qty)});if(bad){toast('Fix the highlighted item lines','err');return false}return true}
  $('#gPend').onclick=function(){if(!valid())return;var v=$('#grnVal');$('#grnBox').classList.remove('ok');v.style.color='var(--warning-700)';v.textContent='Pending match · no GRN no yet';toast('Saved as pending match (sample)')};
  $('#gSave').onclick=function(){if(!valid())return;
    var box=$('#grnBox'),v=$('#grnVal');
    if(!drawMatch()){box.classList.remove('ok');v.style.color='var(--error-700)';v.textContent='Not matched · no GRN no generated';toast('Amount not matched. Fix it or save as pending match.','err');return}
    var b=this;b.innerHTML='<span class="spin"></span>Saving…';b.disabled=true;
    setTimeout(function(){var no='GRN/25-26/'+String(nextGrn++).padStart(4,'0');box.classList.remove('sweep');void box.offsetWidth;box.classList.add('ok','reveal','sweep');v.style.color='var(--success-700)';scramble(v,no+' · '+today());b.innerHTML='Save and generate GRN no';b.disabled=false;
      if($('#gQa').value!=='Approved')toast(no+' generated. Stock is usable after quality approval.');else toast(no+' generated (sample)')},reduce()?50:700)};
};

/* ---------------- Purchase: Purchase order (D5) ---------------- */
var poSel=0, poAll=false;
R.po=function(){
  crumbs([['Purchase','grn'],['Purchase Order','po']]);
  var isNew=poSel<0,p=isNew?{no:'PO/25-26/'+String(nextPo).padStart(4,'0'),party:'',date:today(),status:'Draft',_new:1,lines:[{item:'',qty:'',rate:'',del:''}],more:{del:'',terms:'',cur:'INR',bill:'',ship:'',trn:'',rem:''},audit:null}:D.pos[poSel];
  var list=D.pos.map(function(x){return x.no+' · '+x.party});if(isNew)list.push('New purchase order');
  view('<div class="pagehd"><h1>Purchase order</h1>'+SAMPLE+'</div>'+
   '<div class="entrybar"><label for="entrySel">Entry</label><select class="inp" id="entrySel">'+list.map(function(x,i){return '<option value="'+i+'"'+(i===(isNew?list.length-1:poSel)?' selected':'')+'>'+esc(x)+'</option>'}).join('')+'</select><button class="btn sec sm" id="newEntry">'+ic('plus')+'Add new</button></div>'+
   '<div class="sectcard"><div class="sh"><h2>Purchase order</h2><div class="autono"><span>PO no <b>'+p.no+'</b></span><span>PO date <b>'+p.date+'</b></span></div><span class="sp"></span><button class="btn sec sm" id="hd" aria-expanded="'+poAll+'">'+(poAll?'Hide headings':'Show all headings')+'</button></div><div class="sb">'+
    approvalBar(p,'Purchase PO',R.po)+
    '<div class="fgrid">'+fld('Party name',sel('poParty',partyOpts('Supplier'),p.party),{req:1,for:'poParty'})+'</div>'+
    '<div id="allHd"'+(poAll?'':' hidden')+'><div class="fgrid">'+
      fld('Delivery date',dt('poDel',p.more.del),{for:'poDel'})+fld('Payment terms',inp('poTerms',p.more.terms),{for:'poTerms'})+
      fld('Currency',sel('poCur',D.masters['Currency/Country'].rows.map(function(r){return r[1]}),p.more.cur),{for:'poCur'})+
      fld('Transporter',sel('poTrn',D.transporters,p.more.trn),{for:'poTrn'})+
      fld('Billing address','<textarea class="inp" id="poBill" rows="2">'+esc(p.more.bill)+'</textarea>',{for:'poBill'})+
      fld('Shipping address','<textarea class="inp" id="poShip" rows="2">'+esc(p.more.ship)+'</textarea>',{for:'poShip'})+
      fld('Remarks',inp('poRem',p.more.rem),{span:1,for:'poRem'})+
    '</div></div>'+
    rule('Short view by default (PURCHASE C11); "Show all headings" (B11) shows delivery date, payment terms, currency, transporter, addresses and remarks')+
    '<div class="lbl">Items</div><div class="tablecard" style="overflow-x:auto"><table class="atable lines"><thead><tr><th>Item no / Description</th><th class="r" style="width:120px">Quantity</th><th class="r" style="width:120px">Rate</th><th style="width:150px">Delivery date</th><th class="r">Amount</th><th></th></tr></thead><tbody id="poLines"></tbody></table></div>'+
    '<div><button class="btn gh sm" id="poAdd">'+ic('plus')+'Add item</button></div>'+
    audit(p.audit)+
    '<div style="display:flex;justify-content:flex-end"><button class="btn pri" id="poSave">Save purchase order</button></div>'+
   '</div></div>');
  var pl=p.lines.map(function(l){return Object.assign({},l)});
  function draw(){var sup=$('#poParty').value,parts=D.items.filter(function(x){return (!sup||x.party===sup)&&x.type!=='Sales'}).map(function(x){return [x.no,x.no+' · '+x.desc]});
    $('#poLines').innerHTML=pl.map(function(l,i){return '<tr style="cursor:default"><td>'+sel('pi'+i,parts,l.item)+'</td><td class="r"><input class="inp num" data-pq="'+i+'" value="'+esc(l.qty)+'" inputmode="numeric" aria-label="Quantity"></td><td class="r"><input class="inp num" data-pr="'+i+'" value="'+esc(l.rate)+'" inputmode="decimal" aria-label="Rate"></td><td><input class="inp num" data-pd="'+i+'" value="'+esc(l.del)+'" placeholder="dd/mm/yyyy" aria-label="Delivery date"></td><td class="r num">'+money((+l.qty||0)*(+l.rate||0))+'</td><td class="del"><button class="btn gh icon sm" data-pdel="'+i+'" aria-label="Remove item">'+ic('x')+'</button></td></tr>'}).join('');
    pl.forEach(function(l,i){$('#pi'+i).onchange=function(){l.item=this.value;l.rate=item(this.value).price;draw()}});
    $$('[data-pq]').forEach(function(e){e.onchange=function(){pl[+e.dataset.pq].qty=e.value;draw()}});
    $$('[data-pr]').forEach(function(e){e.onchange=function(){pl[+e.dataset.pr].rate=e.value;draw()}});
    $$('[data-pd]').forEach(function(e){e.oninput=function(){pl[+e.dataset.pd].del=e.value}});
    $$('[data-pdel]').forEach(function(b){b.onclick=function(){pl.splice(+b.dataset.pdel,1);draw()}});}
  draw();
  $('#poParty').onchange=function(){var pa=party(this.value);if(pa){$('#poTerms').value=pa.terms;$('#poBill').value=D.masters.Company.rows[0][3];$('#poShip').value=D.masters.Company.rows[0][3]}draw()};
  $('#hd').onclick=function(){poAll=!poAll;var h=$('#allHd');h.hidden=!poAll;this.textContent=poAll?'Hide headings':'Show all headings';this.setAttribute('aria-expanded',String(poAll))};
  $('#poAdd').onclick=function(){pl.push({item:'',qty:'',rate:'',del:''});draw()};
  $('#entrySel').onchange=function(){poSel=+this.value;if(poSel>=D.pos.length)poSel=-1;R.po()};
  $('#newEntry').onclick=function(){poSel=-1;R.po()};
  $('#poSave').onclick=function(){if(!need(['poParty']))return;if(!pl.some(function(l){return l.item&&+l.qty>0})){toast('Add at least one item','err');return}
    var rec={no:p.no,party:$('#poParty').value,date:p.date,status:p.status==='Approved'?'Approved':'Draft',appr:p.appr,lines:pl,more:{del:$('#poDel').value,terms:$('#poTerms').value,cur:$('#poCur').value,bill:$('#poBill').value,ship:$('#poShip').value,trn:$('#poTrn').value,rem:$('#poRem').value},audit:isNew?[me().name,today(),me().name,today()]:[p.audit[0],p.audit[1],me().name,today()]};
    if(isNew){D.pos.push(rec);poSel=D.pos.length-1;nextPo++}else D.pos[poSel]=rec;R.po();toast(rec.no+' saved as '+rec.status+' (sample)')};
};

/* ---------------- Purchase: Stock transfer (D9) ---------------- */
var stDoc={no:'ST/25-26/0013',status:'Draft',lines:[]};
R.transfer=function(){
  crumbs([['Purchase','grn'],['Stock Transfer','transfer']]);
  view('<div class="pagehd"><h1>Stock transfer</h1>'+SAMPLE+'</div>'+
   '<div class="sectcard"><div class="sh"><h2>Stock transfer</h2><div class="autono"><span>Transfer no <b>'+stDoc.no+'</b></span></div><span class="sp"></span></div><div class="sb">'+
   approvalBar(stDoc,'Stock transfer',R.transfer)+
   '<div class="fgrid">'+
    fld('Item no',sel('tItem',D.items.map(function(x){return x.no}),stDoc.item||''),{req:1,for:'tItem'})+
    fld('Item description',inp('tDesc',stDoc.item?item(stDoc.item).desc:'',{ro:1}),{for:'tDesc'})+
    fld('Location from',sel('tFrom',D.units,stDoc.from||''),{req:1,for:'tFrom'})+fld('Location to',sel('tTo',D.units,stDoc.to||''),{req:1,for:'tTo'})+
    fld('Quantity',inp('tQty',stDoc.qty||'',{num:1,mode:'numeric'}),{req:1,for:'tQty',hint:'<span id="tAvail">Pick an item to see stock at the source location.</span>'})+
    fld('Source type',seg('tType',['Purchase','Credit','Debit'],stDoc.type||'Purchase'),{hint:'The kind of inward document the stock came from.'})+
    fld('Purchase GRN','<select class="inp" id="tGrn"><option value="">Select an item first</option></select>',{for:'tGrn'})+
   '</div>'+rule('Moves one item between plants (units). Location from and to must differ; quantity cannot exceed the stock at the source. Marked "need to discuss" in the workbook (PURCHASE E25)')+
   '<div style="display:flex;justify-content:flex-end"><button class="btn pri" id="tSave">Save stock transfer</button></div></div></div>');
  wireSeg($('#view'));
  function fillItem(){var it=item($('#tItem').value);$('#tDesc').value=it.desc||'';$('#tAvail').textContent=$('#tItem').value?'Stock at source: '+fmt(it.stock||0)+' '+(it.uom||'')+' (sample)':'Pick an item to see stock at the source location.';
    var g=D.grns.filter(function(x){return x.item===$('#tItem').value});$('#tGrn').innerHTML=$('#tItem').value?'<option value="">Select</option>'+g.map(function(x){return '<option>'+x.no+' · qty '+fmt(x.qty)+'</option>'}).join('')+(g.length?'':'<option value="" disabled>No GRN for this item</option>'):'<option value="">Select an item first</option>'}
  fillItem();
  $('#tItem').onchange=fillItem;
  $('#tSave').onclick=function(){if(!need(['tItem','tFrom','tTo','tQty']))return;
    if($('#tFrom').value===$('#tTo').value){$('#tTo').classList.add('err');toast('Location from and to are the same','err');return}
    if(+$('#tQty').value>(item($('#tItem').value).stock||0)){$('#tQty').classList.add('err');toast('Quantity is more than the stock at the source','err');return}
    stDoc.item=$('#tItem').value;stDoc.from=$('#tFrom').value;stDoc.to=$('#tTo').value;stDoc.qty=$('#tQty').value;stDoc.type=segVal('tType');if(stDoc.status!=='Approved')stDoc.status='Draft';R.transfer();toast(stDoc.no+' saved (sample). Submit it for approval.')};
};

/* ---------------- Accounts (D12) ---------------- */
R.ledger=function(a){
  crumbs([['Accounts','ledger'],['Party Ledger','ledger']]);
  var p=a?decodeURIComponent(a):'Apex Auto Components';
  var rows=D.ledger[p]||[],bal=0;
  view('<div class="pagehd"><h1>Party ledger</h1>'+SAMPLE+'<span class="sp"></span></div><div class="entrybar"><label for="lParty">Party</label><select class="inp" id="lParty">'+partyOpts('any').map(function(x){return '<option'+(x===p?' selected':'')+'>'+esc(x)+'</option>'}).join('')+'</select><label for="lf">From</label>'+inp('lf','01/04/2025',{num:1}).replace('class="inp','style="max-width:120px;height:34px" class="inp')+'<label for="lt">To</label>'+inp('lt','31/03/2026',{num:1}).replace('class="inp','style="max-width:120px;height:34px" class="inp')+'<span class="sp"></span><button class="btn gh sm" id="lDl">'+ic('download')+'Download</button></div>'+
   (rows.length?'<div class="tablecard" style="overflow-x:auto"><table class="atable"><thead><tr><th>Date</th><th>Document type</th><th>Document no</th><th class="r">Debit</th><th class="r">Credit</th><th class="r">Balance</th><th>Narration</th></tr></thead><tbody>'+
    rows.map(function(r){bal+=r[3]-r[4];return '<tr style="cursor:default"><td class="num">'+r[0]+'</td><td>'+r[1]+'</td><td class="docno">'+(r[2]||'—')+'</td><td class="r num">'+(r[3]?money(r[3]):'')+'</td><td class="r num">'+(r[4]?money(r[4]):'')+'</td><td class="r num"><b>'+money(Math.abs(bal))+' '+(bal>=0?'DR':'CR')+'</b></td><td class="small sec2">'+esc(r[5])+'</td></tr>'}).join('')+'</tbody></table></div>':'<div class="sectcard"><div class="sb"><div class="blank" style="min-height:20vh">'+ic('bank')+'<p class="small">No entries for this party in FY 2025-26 (sample).</p></div></div></div>')+
   '<div style="margin-top:10px">'+rule('Opening balance and DR/CR come from Party master. Invoices, credit/debit notes, GRNs and receivable/payable entries post to the ledger.')+'</div>');
  $('#lParty').onchange=function(){go('ledger.'+encodeURIComponent(this.value))};
  $('#lDl').onclick=function(){toast('Party ledger would download here (sample)')};
};
var eType='Receivables';
R.entry=function(){
  crumbs([['Accounts','ledger'],['Add entry','entry']]);
  var f='';
  var modes=['Cash','Cheque','NEFT','RTGS','UPI'],banks=D.masters['Bank master'].rows.map(function(r){return r[0]+' · '+r[1]});
  if(eType==='Receivables')f=fld('Date',dt('eDate',today()),{req:1})+fld('Party (customer)',sel('eParty',partyOpts('Customer'),''),{req:1,for:'eParty'})+fld('Against invoice',sel('eAg',D.invoices.map(function(i){return i.no}),''),{})+fld('Amount (₹)',inp('eAmt','',{num:1,mode:'decimal'}),{req:1,for:'eAmt'})+fld('Mode',sel('eMode',modes,'NEFT'),{})+fld('Bank',sel('eBank',banks,''),{})+fld('Reference no',inp('eRef',''),{})+fld('Narration',inp('eNar',''),{span:1});
  else if(eType==='Payables')f=fld('Date',dt('eDate',today()),{req:1})+fld('Party (supplier)',sel('eParty',partyOpts('Supplier'),''),{req:1,for:'eParty'})+fld('Against GRN',sel('eAg',D.grns.map(function(g){return g.no}),''),{})+fld('Amount (₹)',inp('eAmt','',{num:1,mode:'decimal'}),{req:1,for:'eAmt'})+fld('Mode',sel('eMode',modes,'NEFT'),{})+fld('Bank',sel('eBank',banks,''),{})+fld('Reference no',inp('eRef',''),{})+fld('Narration',inp('eNar',''),{span:1});
  else if(eType==='Expenses')f=fld('Date',dt('eDate',today()),{req:1})+fld('Expense head',sel('eParty',['Freight','Rent','Power','Salary','Repairs','Other'],''),{req:1,for:'eParty'})+fld('Amount (₹)',inp('eAmt','',{num:1,mode:'decimal'}),{req:1,for:'eAmt'})+fld('GST amount (₹)',inp('eGst','',{num:1,mode:'decimal'}),{})+fld('Paid from',seg('ePaid',['Cash','Bank'],'Bank'),{})+fld('Bank',sel('eBank',banks,''),{})+fld('Reference no',inp('eRef',''),{})+fld('Narration',inp('eNar',''),{span:1});
  else f=fld('Date',dt('eDate',today()),{req:1})+fld('Bank',sel('eParty',banks,''),{req:1,for:'eParty'})+fld('Type',seg('eBt',['Deposit','Withdrawal','Transfer'],'Deposit'),{})+fld('To bank (for transfer)',sel('eTo',banks,''),{})+fld('Amount (₹)',inp('eAmt','',{num:1,mode:'decimal'}),{req:1,for:'eAmt'})+fld('Reference no',inp('eRef',''),{})+fld('Narration',inp('eNar',''),{span:1});
  view('<div class="pagehd"><h1>Add receivables / payables / expenses / bank entry</h1>'+SAMPLE+'</div><div class="sectcard"><div class="sh">'+seg('eType',['Receivables','Payables','Expenses','Bank entry'],eType,'Entry type')+'<span class="sp"></span></div><div class="sb"><div class="fgrid">'+f+'</div>'+
   '<div style="display:flex;justify-content:flex-end"><button class="btn pri" id="eSave">Save entry</button></div></div></div>');
  wireSeg($('#view'),function(id,v){if(id==='eType'){eType=v;R.entry()}});
  $('#eSave').onclick=function(){if(!need(['eParty','eAmt']))return;toast(eType+' entry saved and posted to the ledger (sample)')};
};

/* ---------------- Admin ---------------- */
R.users=function(){
  crumbs([['Admin','users'],['Users list','users']]);
  view('<div class="pagehd"><h1>Users list</h1>'+SAMPLE+'<span class="sp"></span><button class="btn pri" id="uAdd">'+ic('plus')+'Add user</button></div><div class="tablecard" style="overflow-x:auto"><table class="atable"><thead><tr><th>Name</th><th>Login email</th><th>Mobile</th><th>Role</th><th>Units allowed</th><th>Status</th></tr></thead><tbody>'+
   D.users.map(function(u,i){return '<tr data-u="'+i+'"><td><b>'+u.name+'</b></td><td>'+u.email+'</td><td class="num">'+u.mobile+'</td><td>'+u.role+'</td><td>'+u.units+'</td><td><span class="badge '+(u.status==='Active'?'b-ok':'b-neu')+'">'+u.status+'</span></td></tr>'}).join('')+'</tbody></table></div>');
  function form(u){u=u||{role:'Sales',status:'Active'};drawer(u.name?'Edit user':'Add user','<div class="fgrid">'+fld('Name',inp('uN',u.name),{req:1,for:'uN'})+fld('Login email',inp('uE',u.email,{mode:'email'}),{req:1,for:'uE'})+fld('Mobile',inp('uM',u.mobile,{mode:'tel'}),{})+fld('Role',sel('uR',['Admin','Sales','Purchase','Accounts'],u.role,{blank:false}),{})+fld('Units allowed',sel('uU',['All'].concat(D.units),'All',{blank:false}),{})+fld('Status',seg('uS',['Active','Inactive'],u.status),{})+'</div>','<button class="btn gh" data-x>Cancel</button><button class="btn pri" id="uOk">Save user</button>');wireSeg($('#layer'));
    $('#uOk').onclick=function(){if(!need(['uN','uE']))return;closeLayer();toast('User saved (sample)')}}
  $('#uAdd').onclick=function(){form()};
  $$('[data-u]').forEach(function(tr){tr.onclick=function(){form(D.users[+tr.dataset.u])}});
};
R.access=function(){
  crumbs([['Admin','users'],['Access rights','access']]);
  var pages=['Quotation','Prospect','Sales order','Tax invoice','Purchase order','GRN entry','Stock transfer','Party ledger','Accounts entries','Masters','Admin pages'];
  var def={Sales:['Quotation','Prospect','Sales order','Tax invoice'],Purchase:['Purchase order','GRN entry','Stock transfer'],Accounts:['Party ledger','Accounts entries','Tax invoice']};
  var role='Sales';
  function draw(){var allow=def[role]||[];
    $('#acc').innerHTML='<div class="tablecard" style="overflow-x:auto"><table class="atable keep"><thead><tr><th>Page</th><th class="r">View</th><th class="r">Add</th><th class="r">Edit</th><th class="r">Delete</th></tr></thead><tbody>'+pages.map(function(p){var on=role==='Admin'||allow.indexOf(p)>=0;return '<tr style="cursor:default"><td>'+p+'</td>'+[1,1,1,0].map(function(d){return '<td class="r"><input type="checkbox"'+(on&&(d||role==='Admin')?' checked':'')+' aria-label="'+p+'"></td>'}).join('')+'</tr>'}).join('')+'</tbody></table></div>'+
     '<div class="sectcard" style="margin-top:14px"><div class="sh"><h2>Report groups shown in the left menu</h2></div><div class="sb small">'+['CRM','Sales','Purchase','Accounts'].map(function(g){return '<label><input type="checkbox"'+(ROLE_REPORTS[role].indexOf(g)>=0?' checked':'')+'> '+g+' reports</label>'}).join('')+'</div></div>'}
  view('<div class="pagehd"><h1>Access rights</h1><span class="sp"></span></div><div class="entrybar"><label for="accRole">Role</label>'+seg('accRole',['Admin','Sales','Purchase','Accounts'],role)+'<span class="sp"></span><button class="btn pri sm" id="accSave">Save</button></div><div id="acc"></div>'+
   '<div style="margin-top:10px">'+rule('Report visibility by role comes from OTHER POINTS A17: users see only their role’s reports; Admin sees all.')+'</div>');
  wireSeg($('#view'),function(id,v){role=v;draw()});draw();
  $('#accSave').onclick=function(){toast('Access rights saved for '+role+' (sample)')};
};
R.approval=function(){
  crumbs([['Admin','users'],['Approval','approval']]);
  var approvers=D.users.filter(function(u){return u.role==='Admin'||u.role==='Purchase'||u.role==='Sales'}).map(function(u){return u.name+' ('+u.role+')'});
  view('<div class="pagehd"><h1>Approval</h1><span class="sp"></span></div>'+
   ['Purchase PO','Sales Order','Stock transfer'].map(function(d,i){var c=D.approvalCfg[d];return '<div class="sectcard"><div class="sh"><h2>'+d+'</h2><span class="cap muted">ADMIN I'+(3+[0,1,2][i])+':J'+(3+i)+' · config</span></div><div class="sb"><div class="fgrid">'+
    fld('Approval required',seg('ar'+i,['Yes','No'],c.req),{})+fld('Levels',seg('al'+i,['1','2'],c.levels),{})+
    fld('Level 1 approver',sel('a1'+i,approvers,c.l1,{blank:false}),{})+fld('Level 2 approver',sel('a2'+i,approvers,c.l2||approvers[0]),{})+
    (d!=='Stock transfer'?fld('Approve only above (₹)',inp('aa'+i,c.above,{num:1,mode:'decimal',ph:'Blank = all'}),{hint:'Below this value, level 1 only.'}):'')+
   '</div></div></div>'}).join('')+
   '<div style="display:flex;justify-content:flex-end"><button class="btn pri" id="apSave">Save approval config</button></div>'+
   '<div style="margin-top:10px">'+rule('Documents go Draft → Pending approval → Approved or Rejected (reason required). Approve and Reject appear on the document for the approver.')+'</div>');
  wireSeg($('#view'));
  $('#apSave').onclick=function(){toast('Approval config saved (sample)')};
};
R.dashcfg=function(){
  crumbs([['Admin','users'],['Dashboard','dashcfg']]);
  var all=['Open quotations','Sales orders this month','Invoices this month','Items below minimum stock','Open purchase orders','GRNs pending match','GRNs pending quality approval','Receivables outstanding','Payables outstanding','Expenses this month'];
  view('<div class="pagehd"><h1>Dashboard</h1><span class="sp"></span></div><p class="small sec2" style="margin-bottom:12px">Choose which tiles each role sees on its Dashboard. Admin sees all roles’ tiles.</p><div class="tablecard" style="overflow-x:auto"><table class="atable"><thead><tr><th>Tile</th><th class="r">Sales</th><th class="r">Purchase</th><th class="r">Accounts</th></tr></thead><tbody>'+
   all.map(function(t){return '<tr style="cursor:default"><td>'+t+'</td>'+['Sales','Purchase','Accounts'].map(function(r){return '<td class="r"><input type="checkbox"'+(D.dashTiles[r].indexOf(t)>=0?' checked':'')+' aria-label="'+t+' for '+r+'"></td>'}).join('')+'</tr>'}).join('')+'</tbody></table></div><div style="display:flex;justify-content:flex-end;margin-top:12px"><button class="btn pri" id="dcSave">Save</button></div>');
  $('#dcSave').onclick=function(){toast('Dashboard tiles saved (sample)')};
};
var CFG_AUTO=false;
R.config=function(){
  crumbs([['Admin','users'],['Config','config']]);
  view('<div class="pagehd"><h1>Config</h1><span class="sp"></span></div>'+
   '<div class="sectcard"><div class="sh"><h2>Item no</h2></div><div class="sb">'+
   fld('Item no generation',seg('cfgItem',['Manual','Auto'],CFG_AUTO?'Auto':'Manual'),{hint:'Auto: Category prefix + running number, e.g. PRS-0001 (ADMIN A24).'})+'</div></div>'+
   '<div class="sectcard"><div class="sh"><h2>Document numbers</h2><span class="sp"></span></div><div class="sb"><div class="fgrid">'+
    [['Enquiry','ENQ'],['Quotation','QT'],['Sales order','SO'],['Tax invoice','INV'],['Credit note','CN'],['Debit note','DN'],['Purchase order','PO'],['GRN','GRN'],['Stock transfer','ST']].map(function(x){return fld(x[0]+' prefix',inp('pf'+x[1],x[1]),{hint:x[1]+'/25-26/0001 · resets each FY, per unit'})}).join('')+'</div></div></div>'+
   '<div class="sectcard"><div class="sh"><h2>General</h2></div><div class="sb"><div class="fgrid">'+fld('Financial year starts',inp('cfgFy','1 April',{ro:1}),{})+fld('Date format',inp('cfgDf','dd/mm/yyyy',{ro:1}),{hint:'Fixed (OTHER POINTS A18)'})+fld('Default currency',sel('cfgCur',['INR','USD','EUR'],'INR',{blank:false}),{})+'</div></div></div>'+
   '<div style="display:flex;justify-content:flex-end"><button class="btn pri" id="cfgSave">Save config</button></div>');
  wireSeg($('#view'),function(id,v){if(id==='cfgItem'){CFG_AUTO=v==='Auto'}});
  $('#cfgSave').onclick=function(){toast('Config saved (sample)')};
};
R.pagesetup=function(){
  crumbs([['Admin','users'],['Page setup','pagesetup']]);
  var rows=[['Enquiry/Quotation','quotation','Required','Required','Required','Required','Required'],['Prospect','prospect','Required','Required','Required','Required','No'],['Party master','partymaster','Required','Required','Required','Required','No'],['Item master','itemmaster','Required','Required','Required','Required','No'],['Sales order','salesorder','Required','Required','Required','Required','No'],['Sales invoice','invoice','Required','Required','No','Required','Required'],['GRN entry','grn','Required','Required','No','No','No']];
  view('<div class="pagehd"><h1>Page setup</h1><span class="sp"></span></div><div class="tablecard" style="overflow-x:auto"><table class="atable"><thead><tr><th>Page name</th><th>Page setup</th><th>Add/update</th><th>Import</th><th>Download CSV</th><th>PDF</th><th></th></tr></thead><tbody>'+
   rows.map(function(r){return '<tr style="cursor:default"><td><b>'+r[0]+'</b></td>'+r.slice(2).map(function(c){return '<td>'+(c==='Required'?'<span class="badge b-ok">'+ic('check')+'Required</span>':'<span class="badge b-neu">No</span>')+'</td>'}).join('')+'<td class="r"><button class="btn sec sm" data-ps="'+r[1]+'" data-n="'+r[0]+'">Configure</button></td></tr>'}).join('')+'</tbody></table></div>'+
   '<div style="margin-top:10px">'+rule('Capabilities as listed on OTHER POINTS A1:F8. Configure sets which fields show and which are mandatory, and the PDF layout for pages with PDF.')+'</div>');
  $$('[data-ps]').forEach(function(b){b.onclick=function(){pageSetup(b.dataset.ps,b.dataset.n)}});
};
var MASTERS=['Party master','Item master','Currency/Country','HSN/SAC','Tax master','UOM','Company','Bank master','Category'];
R.masters=function(){
  crumbs([['Admin','users'],['Masters','masters']]);
  view('<div class="pagehd"><h1>Masters</h1></div><div class="fgrid">'+MASTERS.map(function(m,i){return '<button class="sectcard spot" style="text-align:left;font:inherit;color:inherit;cursor:pointer;margin:0;padding:14px 16px;display:flex;gap:10px;align-items:center" data-go="master.'+i+'">'+ic('db','color:var(--primary-600)')+'<b>'+m+'</b><span style="flex:1"></span>'+ic('right','color:var(--text-muted)')+'</button>'}).join('')+'</div>'+
   '<div style="margin-top:12px">'+rule('Each master supports add / update / show / search (ADMIN F5:F11). Masters are not financial-year wise (OTHER POINTS A22).')+'</div>');
};
var mSel={};
R.master=function(a){
  var i=+a,name=MASTERS[i];if(!name){go('masters');return}
  crumbs([['Admin','users'],['Masters','masters'],[name,'']]);
  if(i===0)return partyMaster();
  if(i===1)return itemMaster();
  var M=D.masters[name],j=mSel[name]||0,isNew=j<0,row=isNew?M.f.map(function(){return ''}):M.rows[j];
  view('<div class="pagehd"><h1>'+name+'</h1>'+SAMPLE+'<span class="sp"></span></div><div class="entrybar"><label for="mSearch">Search</label><input class="inp" id="mSearch" style="max-width:220px;height:34px" placeholder="Search '+name+'"><select class="inp" id="entrySel">'+M.rows.map(function(x,k){return '<option value="'+k+'"'+(k===j?' selected':'')+'>'+esc(x.slice(0,2).join(' · '))+'</option>'}).join('')+(isNew?'<option selected value="-1">New entry</option>':'')+'</select><button class="btn sec sm" id="newEntry">'+ic('plus')+'Add new</button></div>'+
   '<div class="sectcard"><div class="sb"><div class="fgrid">'+M.f.map(function(f,k){return fld(f,inp('mf'+k,row[k]),{req:k===0,for:'mf'+k})}).join('')+'</div><div style="display:flex;justify-content:flex-end"><button class="btn pri" id="mSave">Save</button></div></div></div>');
  $('#mSearch').oninput=function(){var q=this.value.toLowerCase(),hit=-1;M.rows.forEach(function(r,k){if(hit<0&&r.join(' ').toLowerCase().indexOf(q)>=0)hit=k});if(hit>=0&&q){mSel[name]=hit;$('#entrySel').value=String(hit);M.f.forEach(function(f,k){$('#mf'+k).value=M.rows[hit][k]})}};
  $('#entrySel').onchange=function(){mSel[name]=+this.value;R.master(a)};
  $('#newEntry').onclick=function(){mSel[name]=-1;R.master(a)};
  $('#mSave').onclick=function(){if(!need(['mf0']))return;var v=M.f.map(function(f,k){return $('#mf'+k).value});if(isNew){M.rows.push(v);mSel[name]=M.rows.length-1}else M.rows[j]=v;R.master(a);toast(esc(v[0])+' saved (sample)')};
};
function partyMaster(){
  var j=mSel.party||0,isNew=j<0,p=isNew?{type:'Customer',drcr:'DR',cur:'INR',status:'Active'}:D.parties[j];
  var list=D.parties.map(function(x){return x.name});if(isNew)list.push('New party');
  view('<div class="pagehd"><h1>Party master</h1>'+SAMPLE+'</div>'+entryBar('Entry',list,isNew?list.length-1:j,'partymaster')+
   '<div class="sectcard"><div class="sb"><div class="fgrid">'+
    fld('Name',inp('pmName',p.name),{req:1,for:'pmName'})+
    fld('GST no','<div style="display:flex;gap:6px"><input class="inp num" id="pmGst" style="text-align:left;text-transform:uppercase" maxlength="15" value="'+esc(p.gst||'')+'"><button class="btn sec" id="pmFetch" type="button">'+ic('refresh')+'Fetch</button></div>',{for:'pmGst',hint:'<span id="pmGstH">Fetch party details against GST no (ADMIN A22). Try the sample 27AABCK4521M1Z8.</span>'})+
    fld('Type',seg('pmType',['Customer','Supplier','Both'],p.type),{req:1,hint:'If the party is a customer, purchase type is not allowed, and vice versa (ADMIN A23).'})+
    fld('Billing address','<textarea class="inp" id="pmBill" rows="2">'+esc(p.bill)+'</textarea>',{req:1,for:'pmBill'})+
    fld('Shipping address','<textarea class="inp" id="pmShip" rows="2">'+esc(p.ship)+'</textarea>',{for:'pmShip'})+
    fld('Payment terms',inp('pmTerms',p.terms),{for:'pmTerms'})+
    fld('Opening balance (₹)',inp('pmOb',p.ob,{num:1,mode:'decimal'}),{for:'pmOb'})+
    fld('DR / CR',seg('pmDrcr',['DR','CR'],p.drcr),{})+
    fld('Currency',sel('pmCur',D.masters['Currency/Country'].rows.map(function(r){return r[1]}),p.cur),{for:'pmCur'})+
    fld('Status',seg('pmStatus',['Active','Inactive'],p.status),{req:1})+
   '</div>'+rule('Mandatory: Name, Type, Billing address, Status')+'<div style="display:flex;justify-content:flex-end"><button class="btn pri" id="pmSave">Save</button></div></div></div>');
  wireSeg($('#view'));wireCap($('#view'),'Party master');
  $('#entrySel').onchange=function(){mSel.party=+this.value;if(mSel.party>=D.parties.length)mSel.party=-1;partyMaster()};
  $('#newEntry').onclick=function(){mSel.party=-1;partyMaster()};
  $('#pmFetch').onclick=function(){var g=$('#pmGst'),v=g.value.trim().toUpperCase(),b=this,hn=$('#pmGstH');
    if(!/^[0-9]{2}[A-Z0-9]{13}$/.test(v)){g.classList.add('err');hn.parentNode.className='hint e';hn.textContent='A GST no has 15 characters and starts with a 2-digit state code.';g.focus();return}
    g.classList.remove('err');b.innerHTML='<span class="spin"></span>Fetching…';b.disabled=true;
    setTimeout(function(){b.innerHTML=ic('refresh')+'Fetch';b.disabled=false;
      if(v==='27AABCK4521M1Z8'){$('#pmName').value='Kaveri Precision Engineering Pvt Ltd';$('#pmBill').value='Gat 221, Sanaswadi, Shirur, Pune, Maharashtra 412208';$('#pmShip').value=$('#pmBill').value;hn.parentNode.className='hint s';hn.textContent='Filled from GST records (sample). Edit if needed.'}
      else{hn.parentNode.className='hint w';hn.textContent='Couldn’t fetch details for this GST no. Enter them manually.'}},reduce()?50:900)};
  $('#pmSave').onclick=function(){if(!need(['pmName','pmBill']))return;
    var rec={name:$('#pmName').value.trim(),gst:$('#pmGst').value.toUpperCase(),type:segVal('pmType'),bill:$('#pmBill').value,ship:$('#pmShip').value||$('#pmBill').value,terms:$('#pmTerms').value,ob:$('#pmOb').value,drcr:segVal('pmDrcr'),cur:$('#pmCur').value,status:segVal('pmStatus')};
    if(isNew){D.parties.push(rec);mSel.party=D.parties.length-1}else D.parties[j]=rec;partyMaster();toast(esc(rec.name)+' saved (sample)')};
}
function itemMaster(){
  var j=mSel.item||0,isNew=j<0,it=isNew?{type:'Sales',status:'Active',cur:'INR',bom:[]}:D.items[j];
  var list=D.items.map(function(x){return x.no+' · '+x.desc});if(isNew)list.push('New item');
  var bom=(it.bom||[]).map(function(b){return Object.assign({},b)});
  view('<div class="pagehd"><h1>Item master</h1>'+SAMPLE+'</div>'+entryBar('Entry',list,isNew?list.length-1:j,'itemmaster')+
   '<div class="sectcard"><div class="sb"><div class="fgrid">'+
    fld('Party name',sel('imParty',partyOpts('any'),it.party),{for:'imParty'})+
    fld('Category',sel('imCat',D.masters.Category.rows.map(function(r){return r[0]}),it.cat),{for:'imCat'})+
    fld('Item description',inp('imDesc',it.desc),{req:1,for:'imDesc'})+
    fld('Item no',inp('imNo',isNew&&CFG_AUTO?'Generated on save':it.no,{ro:isNew&&CFG_AUTO}),{req:1,for:'imNo',hint:CFG_AUTO?'Auto: Category prefix + number (ADMIN A24)':'Manual entry. Switch to auto in Admin › Config (ADMIN A24).'})+
    fld('Internal item no',inp('imIno',it.ino),{for:'imIno'})+
    fld('Type',seg('imType',['Sales','Purchase','Both'],it.type),{req:1})+
    fld('HSN/SAC',sel('imHsn',D.masters['HSN/SAC'].rows.map(function(r){return r[0]}),it.hsn),{req:1,for:'imHsn'})+
    fld('UOM',sel('imUom',D.masters.UOM.rows.map(function(r){return r[0]}),it.uom),{req:1,for:'imUom'})+
    fld('Tax structure',sel('imTax',D.masters['Tax master'].rows.map(function(r){return r[0]}),it.tax),{req:1,for:'imTax'})+
    fld('Price',inp('imPrice',it.price,{num:1,mode:'decimal'}),{for:'imPrice',hint:'Default rate on quotations, orders and invoices'})+
    fld('Currency',sel('imCur',D.masters['Currency/Country'].rows.map(function(r){return r[1]}),it.cur),{for:'imCur'})+
    fld('Min stock',inp('imMin',it.min,{num:1,mode:'numeric'}),{for:'imMin'})+
    fld('Max stock',inp('imMax',it.max,{num:1,mode:'numeric'}),{for:'imMax'})+
    fld('Weight / piece',inp('imWpp',it.wpp,{num:1,mode:'decimal'}),{for:'imWpp'})+
    fld('Packaging qty',inp('imPack',it.pack,{num:1,mode:'numeric'}),{for:'imPack'})+
    fld('Status',seg('imStatus',['Active','Inactive'],it.status),{req:1})+
   '</div></div></div>'+
   '<div class="sectcard" id="bomCard"><div class="sh"><h2>RM BOM</h2><span class="cap muted">Raw material per piece (OTHER POINTS A25)</span><span class="sp"></span></div><div class="sb"><div id="bomWrap"></div>'+
   '<div style="display:flex;justify-content:flex-end"><button class="btn pri" id="imSave">Save</button></div></div></div>');
  wireSeg($('#view'),function(id){if(id==='imType')drawBom()});wireCap($('#view'),'Item master');
  function drawBom(){var t=segVal('imType');if(t==='Purchase'){$('#bomWrap').innerHTML='<p class="small muted">RM BOM applies to Sales and Both items.</p>';return}
    var rms=D.items.filter(function(x){return x.type!=='Sales'}).map(function(x){return [x.no,x.no+' · '+x.desc]});
    $('#bomWrap').innerHTML='<div class="tablecard" style="overflow-x:auto"><table class="atable lines"><thead><tr><th>Raw material item</th><th class="r" style="width:140px">Qty per piece</th><th style="width:100px">UOM</th><th></th></tr></thead><tbody>'+(bom.length?bom.map(function(b,k){return '<tr style="cursor:default"><td>'+sel('bi'+k,rms,b.item)+'</td><td class="r"><input class="inp num" data-bq="'+k+'" value="'+esc(b.qty)+'" inputmode="decimal" aria-label="Qty per piece"></td><td class="small">'+esc(item(b.item).uom||'')+'</td><td class="del"><button class="btn gh icon sm" data-bdel="'+k+'" aria-label="Remove">'+ic('x')+'</button></td></tr>'}).join(''):'<tr style="cursor:default"><td colspan="4" class="small muted">No raw material added.</td></tr>')+'</tbody></table></div><div><button class="btn gh sm" id="bAdd">'+ic('plus')+'Add raw material</button></div>';
    bom.forEach(function(b,k){$('#bi'+k).onchange=function(){b.item=this.value;drawBom()}});
    $$('[data-bq]').forEach(function(e){e.oninput=function(){bom[+e.dataset.bq].qty=e.value}});
    $$('[data-bdel]').forEach(function(x){x.onclick=function(){bom.splice(+x.dataset.bdel,1);drawBom()}});
    $('#bAdd').onclick=function(){bom.push({item:'',qty:''});drawBom()}}
  drawBom();
  $('#entrySel').onchange=function(){mSel.item=+this.value;if(mSel.item>=D.items.length)mSel.item=-1;itemMaster()};
  $('#newEntry').onclick=function(){mSel.item=-1;itemMaster()};
  $('#imSave').onclick=function(){if(!need(['imDesc'].concat(isNew&&CFG_AUTO?[]:['imNo']).concat(['imHsn','imUom','imTax'])))return;
    var cat=$('#imCat').value,pre=(D.masters.Category.rows.filter(function(r){return r[0]===cat})[0]||['','','ITM'])[2];
    var no=isNew&&CFG_AUTO?pre+'-'+String(D.items.length+1).padStart(4,'0'):$('#imNo').value.trim();
    var rec={no:no,ino:$('#imIno').value,desc:$('#imDesc').value,party:$('#imParty').value,cat:cat,type:segVal('imType'),hsn:$('#imHsn').value,uom:$('#imUom').value,tax:$('#imTax').value,price:$('#imPrice').value,cur:$('#imCur').value,min:$('#imMin').value,max:$('#imMax').value,wpp:$('#imWpp').value,pack:$('#imPack').value,status:segVal('imStatus'),stock:isNew?0:it.stock,bom:bom};
    if(isNew){D.items.push(rec);mSel.item=D.items.length-1}else D.items[j]=rec;itemMaster();toast(no+' saved (sample)')};
}

/* ---------------- Boot ---------------- */
render();stagger();booted=true;
})();
