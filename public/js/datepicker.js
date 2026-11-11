/* Date picker for every date field (Sat 18/10/2026): any input with placeholder "dd/mm/yyyy" (or data-date) gets a
   calendar button. The calendar opens on click or focus of the field, typing still works and moves the calendar, and the
   value stays dd/mm/yyyy, the format the server checks. Keyboard: Alt+Down or the button opens it; in the calendar the
   arrows move a day or a week, PageUp / PageDown a month (with Shift a year), Home / End the week, Enter picks, Escape
   closes and returns to the field. One calendar floats at the top level of the page, so no card can cover it. */
(function(){
'use strict';
var SEL='input[placeholder="dd/mm/yyyy"],input[data-date]';
var MONTHS=['January','February','March','April','May','June','July','August','September','October','November','December'];
var DAYS=['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
var pop=null,grid=null,title=null,input=null,view=null,focusDay=null,quiet=false;

function pad(n){return (n<10?'0':'')+n}
function fmt(d){return pad(d.getDate())+'/'+pad(d.getMonth()+1)+'/'+d.getFullYear()}
function parse(v){
  var m=/^\s*(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})\s*$/.exec(v||'');if(!m)return null;
  var d=new Date(+m[3],+m[2]-1,+m[1]);return d.getDate()===+m[1]&&d.getMonth()===+m[2]-1?d:null;
}
function same(a,b){return a&&b&&a.getFullYear()===b.getFullYear()&&a.getMonth()===b.getMonth()&&a.getDate()===b.getDate()}
function addDays(d,n){var x=new Date(d);x.setDate(x.getDate()+n);return x}
function addMonths(d,n){var x=new Date(d.getFullYear(),d.getMonth()+n,1),last=new Date(x.getFullYear(),x.getMonth()+1,0).getDate();x.setDate(Math.min(d.getDate(),last));return x}
function el(tag,cls,text){var e=document.createElement(tag);if(cls)e.className=cls;if(text!=null)e.textContent=text;return e}

function build(){
  pop=el('div','dppop');pop.hidden=true;pop.setAttribute('role','dialog');pop.setAttribute('aria-label','Choose a date');
  var head=el('div','dphead');
  var nav=function(lbl,txt,months){var b=el('button','dpnav',txt);b.type='button';b.setAttribute('aria-label',lbl);
    b.addEventListener('click',function(){view=addMonths(view,months);focusDay=addMonths(focusDay,months);render(false)});return b};
  head.appendChild(nav('Previous year','«',-12));head.appendChild(nav('Previous month','‹',-1));
  title=el('div','dptitle');title.setAttribute('aria-live','polite');head.appendChild(title);
  head.appendChild(nav('Next month','›',1));head.appendChild(nav('Next year','»',12));
  grid=el('table','dpgrid');grid.setAttribute('role','grid');
  var thead=el('thead'),tr=el('tr');DAYS.forEach(function(d){var th=el('th',null,d.slice(0,2));th.setAttribute('abbr',d);th.setAttribute('scope','col');tr.appendChild(th)});
  thead.appendChild(tr);grid.appendChild(thead);grid.appendChild(el('tbody'));
  var foot=el('div','dpfoot');
  var today=el('button','btn gh sm','Today');today.type='button';today.addEventListener('click',function(){pick(new Date())});
  var clear=el('button','btn gh sm','Clear');clear.type='button';clear.addEventListener('click',function(){set('');close(true)});
  var done=el('button','btn sec sm','Close');done.type='button';done.addEventListener('click',function(){close(true)});
  foot.appendChild(today);foot.appendChild(clear);foot.appendChild(el('span','sp'));foot.appendChild(done);
  pop.appendChild(head);pop.appendChild(grid);pop.appendChild(foot);
  pop.addEventListener('keydown',keys);
  grid.addEventListener('click',function(e){var b=e.target.closest('button[data-d]');if(b)pick(new Date(+b.dataset.y,+b.dataset.m,+b.dataset.d))});
  document.body.appendChild(pop);
}

function render(moveFocus){
  title.textContent=MONTHS[view.getMonth()]+' '+view.getFullYear();
  var chosen=parse(input.value),now=new Date(),tb=grid.tBodies[0];tb.innerHTML='';
  var first=new Date(view.getFullYear(),view.getMonth(),1),start=addDays(first,-((first.getDay()+6)%7));
  for(var w=0;w<6;w++){
    var tr=el('tr');
    for(var i=0;i<7;i++){
      var d=addDays(start,w*7+i),td=el('td'),b=el('button','dpday',String(d.getDate()));
      b.type='button';b.dataset.y=d.getFullYear();b.dataset.m=d.getMonth();b.dataset.d=d.getDate();
      b.setAttribute('aria-label',DAYS[(d.getDay()+6)%7]+' '+d.getDate()+' '+MONTHS[d.getMonth()]+' '+d.getFullYear());
      b.tabIndex=same(d,focusDay)?0:-1;
      if(d.getMonth()!==view.getMonth())b.classList.add('out');
      if(same(d,now)){b.classList.add('today');b.setAttribute('aria-current','date')}
      if(same(d,chosen)){b.classList.add('on');td.setAttribute('aria-selected','true')}
      td.setAttribute('role','gridcell');td.appendChild(b);tr.appendChild(td);
    }
    tb.appendChild(tr);
  }
  if(moveFocus){var f=grid.querySelector('button[tabindex="0"]');if(f)f.focus()}
}

function place(){
  if(!pop||pop.hidden||!input)return;
  var r=input.getBoundingClientRect(),vw=document.documentElement.clientWidth,vh=window.innerHeight,w=Math.min(300,vw-16);
  var left=Math.min(Math.max(8,r.left),vw-w-8),h=pop.offsetHeight||340;
  var below=vh-r.bottom-8,up=below<h&&r.top-8>below;
  pop.style.width=w+'px';pop.style.left=left+'px';
  pop.style.top=(up?Math.max(8,r.top-h-4):Math.min(r.bottom+4,Math.max(8,vh-h-8)))+'px';
}

function open(inp,focusGrid){
  if(!pop)build();
  if(input&&input!==inp)close(false);
  input=inp;var v=parse(inp.value);focusDay=v||new Date();view=new Date(focusDay.getFullYear(),focusDay.getMonth(),1);
  pop.hidden=false;render(false);place();
  inp.setAttribute('aria-expanded','true');
  if(focusGrid)render(true);
}
function close(back){
  if(!pop||pop.hidden)return;pop.hidden=true;
  if(input){input.setAttribute('aria-expanded','false');if(back){quiet=true;input.focus();quiet=false}}
}
function set(v){if(!input)return;input.value=v;input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}))}
function pick(d){set(fmt(d));close(true)}

function keys(e){
  if(e.key==='Escape'){e.preventDefault();e.stopPropagation();close(true);return}
  if(e.key==='Tab'){ // keep Tab inside the calendar while it is open from the keyboard
    var f=[].filter.call(pop.querySelectorAll('button'),function(b){return b.tabIndex!==-1&&!b.disabled});
    if(!f.length)return;var i=f.indexOf(document.activeElement);
    if(e.shiftKey&&i<=0){e.preventDefault();f[f.length-1].focus()}else if(!e.shiftKey&&i===f.length-1){e.preventDefault();f[0].focus()}
    return;
  }
  if(!e.target.classList.contains('dpday'))return;
  var d=focusDay,n=null;
  switch(e.key){
    case 'ArrowLeft':n=addDays(d,-1);break;case 'ArrowRight':n=addDays(d,1);break;
    case 'ArrowUp':n=addDays(d,-7);break;case 'ArrowDown':n=addDays(d,7);break;
    case 'Home':n=addDays(d,-((d.getDay()+6)%7));break;case 'End':n=addDays(d,6-((d.getDay()+6)%7));break;
    case 'PageUp':n=addMonths(d,e.shiftKey?-12:-1);break;case 'PageDown':n=addMonths(d,e.shiftKey?12:1);break;
    case 'Enter':case ' ':e.preventDefault();pick(d);return;
    default:return;
  }
  e.preventDefault();focusDay=n;view=new Date(n.getFullYear(),n.getMonth(),1);render(true);
}

function enhance(inp){
  if(inp.dataset.dpReady||inp.type==='hidden')return;inp.dataset.dpReady='1';
  var wrap=el('span','dpwrap');inp.parentNode.insertBefore(wrap,inp);wrap.appendChild(inp);
  var b=el('button','dpbtn');b.type='button';b.tabIndex=-1;
  b.setAttribute('aria-label','Open calendar'+(inp.labels&&inp.labels[0]?' for '+inp.labels[0].textContent.replace(/\*/g,'').trim():''));
  b.innerHTML='<svg class="ico" aria-hidden="true"><use href="#i-cal"/></svg>';
  wrap.appendChild(b);
  inp.setAttribute('aria-haspopup','dialog');inp.setAttribute('aria-expanded','false');inp.autocomplete='off';
  var sync=function(){b.disabled=inp.disabled||inp.readOnly};sync();
  new MutationObserver(sync).observe(inp,{attributes:true,attributeFilter:['disabled','readonly']});
  b.addEventListener('click',function(){if(b.disabled)return;if(pop&&!pop.hidden&&input===inp){close(true)}else{open(inp,true)}});
  inp.addEventListener('click',function(){if(!inp.disabled&&!inp.readOnly&&(!pop||pop.hidden||input!==inp))open(inp,false)});
  inp.addEventListener('focus',function(){if(!quiet&&!inp.disabled&&!inp.readOnly)open(inp,false)});
  inp.addEventListener('input',function(){if(input!==inp||!pop||pop.hidden)return;var v=parse(inp.value);if(v){focusDay=v;view=new Date(v.getFullYear(),v.getMonth(),1)}render(false)});
  inp.addEventListener('keydown',function(e){
    if((e.key==='ArrowDown'&&(e.altKey||(pop&&!pop.hidden&&input===inp)))){e.preventDefault();open(inp,true)}
    else if(e.key==='Escape'&&pop&&!pop.hidden&&input===inp){e.stopPropagation();close(false)}
    else if(e.key==='Tab'&&pop&&!pop.hidden&&input===inp)close(false);
  });
}

function scan(root){(root.matches&&root.matches(SEL)?[root]:[]).concat([].slice.call((root.querySelectorAll?root:document).querySelectorAll(SEL))).forEach(enhance)}
scan(document);
new MutationObserver(function(ms){ms.forEach(function(m){[].forEach.call(m.addedNodes,function(n){if(n.nodeType===1)scan(n)})})}).observe(document.body,{childList:true,subtree:true});
document.addEventListener('pointerdown',function(e){if(pop&&!pop.hidden&&!pop.contains(e.target)&&!(input&&input.parentNode.contains(e.target)))close(false)});
document.addEventListener('focusin',function(e){if(pop&&!pop.hidden&&!pop.contains(e.target)&&e.target!==input)close(false)});
window.addEventListener('resize',function(){place()});window.addEventListener('scroll',function(){place()},true);
window.nDateInit=scan;
})();
