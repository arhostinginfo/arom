/*
 Server-fed pickers (decisions A11, UX2; Sat 18/10/2026: "should load like select with search").
 Markup: <div class="picker" data-picker="field|nav" data-url="…"> with a <select> inside for "field".
 field: a select with search. The field is a button showing the chosen option; clicking it (or Enter, Space, Down)
        opens a list at once with a search box on top. The first page comes from a short-lived cache (filled when the
        pointer or focus reaches the field), otherwise "Loading…" shows while it is fetched. Typing searches on the
        server (debounced, older requests cancelled); scrolling to the end loads the next page. The hidden select keeps
        the value that is submitted, so forms and validation work as before.
 nav:   the entry picker; picking an entry opens it. Arrow keys only move the highlight (review M7).
 The server returns 20 matches a page as [{id, label, sub}] (?q=…&page=n).
*/
(function(){
'use strict';
var uid=0, PAGE=20, TTL=60000, cache={};
function cached(key){var c=cache[key];return c&&Date.now()-c.t<TTL?c.d:null}
function fetchPage(url,q,page,signal){
  var key=url+'|'+q+'|'+page,hit=cached(key);
  if(hit)return Promise.resolve(hit);
  return fetch(url+(url.indexOf('?')<0?'?':'&')+'q='+encodeURIComponent(q)+'&page='+page,{headers:{'Accept':'application/json'},credentials:'same-origin',signal:signal})
    .then(function(r){return r.ok?r.json():[]})
    .then(function(d){d=Array.isArray(d)?d:[];cache[key]={t:Date.now(),d:d};return d});
}
function el(tag,cls,text){var e=document.createElement(tag);if(cls)e.className=cls;if(text!=null)e.textContent=text;return e}
/* Lists float at the top level of the page (Sat 18/10/2026: the Prospect search list went behind the form): fixed
   position next to their field, flipped above it near the bottom of the screen, kept inside the screen; on a phone
   the CSS turns them into a sheet at the bottom. */
var floating=[];
function place(anchor,pop){
  if(pop.hidden)return;
  if(window.matchMedia('(max-width:640px)').matches){pop.style.cssText='';return}
  var r=anchor.getBoundingClientRect(),vw=document.documentElement.clientWidth,vh=window.innerHeight;
  var w=Math.min(Math.max(r.width,240),vw-16),left=Math.min(Math.max(8,r.left),vw-w-8);
  var below=vh-r.bottom-12,above=r.top-12,up=below<240&&above>below,room=Math.max(160,up?above:below);
  pop.style.position='fixed';pop.style.left=left+'px';pop.style.width=w+'px';pop.style.right='auto';pop.style.maxHeight=Math.min(room,420)+'px';
  if(up){pop.style.top='auto';pop.style.bottom=(vh-r.top+4)+'px'}else{pop.style.bottom='auto';pop.style.top=(r.bottom+4)+'px'}
}
function float(anchor,pop){document.body.appendChild(pop);pop.classList.add('pkfloat');floating.push([anchor,pop])}
function replaceAll(){floating.forEach(function(f){place(f[0],f[1])})}
window.addEventListener('resize',replaceAll);window.addEventListener('scroll',replaceAll,true);

/* ---------- field: select with search ---------- */
function initField(box){
  var sel=box.querySelector('select');if(!sel||box.dataset.pkReady)return;box.dataset.pkReady='1';
  var n=++uid,lbl=document.querySelector('label[for="'+sel.id+'"]');
  var btn=el('button','inp pkbtn');btn.type='button';btn.id=(sel.id||'pk'+n)+'_pick';
  btn.setAttribute('aria-haspopup','listbox');btn.setAttribute('aria-expanded','false');btn.setAttribute('aria-controls','pkp'+n);
  var val=el('span','pkval');btn.appendChild(val);
  var chev=document.createElementNS('http://www.w3.org/2000/svg','svg');chev.setAttribute('class','ico pkchev');chev.setAttribute('aria-hidden','true');
  var use=document.createElementNS('http://www.w3.org/2000/svg','use');use.setAttribute('href','#i-down');chev.appendChild(use);btn.appendChild(chev);
  if(lbl){lbl.htmlFor=btn.id;if(!lbl.id)lbl.id='pkl'+n;btn.setAttribute('aria-labelledby',lbl.id+' '+btn.id)}
  if(sel.classList.contains('err'))btn.classList.add('err');
  if(sel.getAttribute('aria-required'))btn.setAttribute('aria-required','true');
  var desc=sel.getAttribute('aria-describedby');if(desc)btn.setAttribute('aria-describedby',desc);
  if(!lbl&&sel.getAttribute('aria-label'))btn.setAttribute('aria-label',sel.getAttribute('aria-label'));
  sel.style.display='none';sel.tabIndex=-1;sel.setAttribute('aria-hidden','true');
  box.insertBefore(btn,sel);box.style.position='relative';

  var pop=el('div','pkpop');pop.id='pkp'+n;pop.hidden=true;
  var search=el('input','inp pksearch');search.type='search';search.autocomplete='off';search.placeholder=box.dataset.placeholder||'Type to search';
  search.setAttribute('role','combobox');search.setAttribute('aria-expanded','true');search.setAttribute('aria-autocomplete','list');search.setAttribute('aria-controls','pk'+n);
  search.setAttribute('aria-label','Search '+(lbl?lbl.textContent.replace('*','').trim():'options'));
  var list=el('ul','pklist pkinner');list.id='pk'+n;list.setAttribute('role','listbox');if(lbl)list.setAttribute('aria-labelledby',lbl.id);
  var stat=el('div','pkstat');stat.setAttribute('role','status');stat.setAttribute('aria-live','polite');
  pop.appendChild(search);pop.appendChild(list);pop.appendChild(stat);float(btn,pop);

  var items=[],active=-1,q='',page=1,more=false,busy=false,ctl=null,timer=null,emptyOpt=[].filter.call(sel.options,function(o){return o.value===''})[0];
  function sync(){var o=sel.options[sel.selectedIndex];var has=o&&o.value!=='';val.textContent=has?o.textContent:(emptyOpt?emptyOpt.textContent:'Select');val.classList.toggle('ph',!has);
    btn.disabled=sel.disabled||!!sel.closest('fieldset[disabled]');}
  sel.addEventListener('change',sync);sync();
  function setStatus(t,loading){stat.textContent=t;stat.classList.toggle('loading',!!loading)}
  function render(append){
    if(!append){list.innerHTML='';active=-1}
    var start=append?list.querySelectorAll('[role=option]').length:0;
    if(!append&&sel.value!==''&&emptyOpt&&q===''){
      var c=el('li','pkclear');c.id=list.id+'-c';c.setAttribute('role','option');c.setAttribute('aria-selected','false');c.dataset.clear='1';c.textContent='Clear: '+emptyOpt.textContent;list.appendChild(c);
    }
    items.slice(start).forEach(function(it,j){
      var li=el('li');li.id=list.id+'-'+(start+j);li.setAttribute('role','option');li.dataset.i=start+j;
      li.setAttribute('aria-selected',String(String(it.id)===sel.value));if(String(it.id)===sel.value)li.classList.add('cur');
      li.appendChild(el('span',null,it.label));if(it.sub)li.appendChild(el('small',null,it.sub));list.appendChild(li);
    });
    setStatus(items.length?(more?'Scroll for more':items.length+' shown'):'No matches',false);
  }
  function load(reset){
    if(ctl)ctl.abort();ctl=window.AbortController?new AbortController():null;
    if(reset){page=1;items=[];more=false}
    busy=true;setStatus('Loading…',true);
    var url=box.dataset.url,myQ=q,myPage=page;
    fetchPage(url,myQ,myPage,ctl&&ctl.signal).then(function(d){
      if(myQ!==q||pop.hidden&&reset===false)return;
      busy=false;items=items.concat(d);more=d.length>=PAGE;render(myPage>1);
    }).catch(function(e){if(e&&e.name==='AbortError')return;busy=false;setStatus('Could not load. Type again to retry.',false)});
  }
  function open(){
    if(btn.disabled||!pop.hidden)return;
    pop.hidden=false;place(btn,pop);btn.setAttribute('aria-expanded','true');box.classList.add('open');
    search.value='';q='';
    var hit=cached(box.dataset.url+'||1');
    if(hit){items=hit.slice();page=1;more=hit.length>=PAGE;render(false)}else{list.innerHTML='';load(true)}
    search.focus();
  }
  function close(focusBtn){
    if(pop.hidden)return;pop.hidden=true;btn.setAttribute('aria-expanded','false');box.classList.remove('open');
    search.removeAttribute('aria-activedescendant');if(ctl)ctl.abort();if(focusBtn)btn.focus();
  }
  function options(){return [].slice.call(list.querySelectorAll('[role=option]'))}
  function highlight(){options().forEach(function(li,i){var on=i===active;li.classList.toggle('on',on);if(on){li.scrollIntoView({block:'nearest'});search.setAttribute('aria-activedescendant',li.id)}})}
  function move(d){var o=options();if(!o.length)return;active=(active+d+o.length)%o.length;highlight()}
  function choose(li){
    if(!li)return;
    if(li.dataset.clear){sel.value='';sel.dispatchEvent(new Event('change',{bubbles:true}));close(true);return}
    var it=items[+li.dataset.i];if(!it)return;
    var opt=[].filter.call(sel.options,function(o){return o.value===String(it.id)})[0];
    if(!opt){opt=new Option(it.label,it.id);sel.add(opt)}
    if(it.data)for(var k in it.data)if(Object.prototype.hasOwnProperty.call(it.data,k))opt.dataset[k]=it.data[k];
    sel.value=String(it.id);sel.dispatchEvent(new Event('change',{bubbles:true}));
    if(box.dataset.clears){var other=document.querySelector(box.dataset.clears);if(other&&other.value){other.value='';other.dispatchEvent(new Event('change',{bubbles:true}))}}
    close(true);
  }
  /* Warm the first page before the click, so the list opens with options already there. */
  function warm(){if(!btn.disabled&&!cached(box.dataset.url+'||1'))fetchPage(box.dataset.url,'',1).catch(function(){})}
  btn.addEventListener('pointerenter',warm);btn.addEventListener('focus',warm);
  btn.addEventListener('click',function(){pop.hidden?open():close(true)});
  btn.addEventListener('keydown',function(e){
    if(e.key==='ArrowDown'||e.key==='ArrowUp'||e.key==='Enter'||e.key===' '){e.preventDefault();open()}
    else if(e.key.length===1&&!e.ctrlKey&&!e.metaKey&&!e.altKey){e.preventDefault();open();search.value=e.key;q=e.key;load(true)}
  });
  search.addEventListener('input',function(){clearTimeout(timer);timer=setTimeout(function(){q=search.value.trim();load(true)},250)});
  search.addEventListener('keydown',function(e){
    if(e.key==='ArrowDown'){e.preventDefault();move(1)}
    else if(e.key==='ArrowUp'){e.preventDefault();move(-1)}
    else if(e.key==='Enter'){e.preventDefault();var o=options();choose(o[active>=0?active:(o[0]&&o[0].dataset.clear&&o.length>1?1:0)])}
    else if(e.key==='Escape'){e.preventDefault();e.stopPropagation();close(true)}
    else if(e.key==='Tab'){close(false)}
  });
  list.addEventListener('mousedown',function(e){e.preventDefault()});
  list.addEventListener('click',function(e){choose(e.target.closest('[role=option]'))});
  list.addEventListener('scroll',function(){if(more&&!busy&&list.scrollTop+list.clientHeight>=list.scrollHeight-40){page++;load(false)}});
  document.addEventListener('pointerdown',function(e){if(!pop.hidden&&!box.contains(e.target)&&!pop.contains(e.target))close(false)});
  /* A fieldset turned on or off later (e.g. a form unlocked) updates the button. */
  new MutationObserver(sync).observe(sel,{attributes:true,attributeFilter:['disabled']});
}

/* ---------- nav: the entry picker ---------- */
function initNav(box){
  if(box.dataset.pkReady)return;box.dataset.pkReady='1';
  var input=box.querySelector('input');box.style.display='';box.style.position='relative';
  var list=el('ul','pklist');list.id='pk'+(++uid);list.setAttribute('role','listbox');list.hidden=true;float(input,list);
  input.setAttribute('role','combobox');input.setAttribute('aria-autocomplete','list');input.setAttribute('aria-expanded','false');input.setAttribute('aria-controls',list.id);
  var items=[],active=-1,req=0,timer=null,ctl=null;
  function term(){return input.value===input.dataset.current?'':input.value.trim()}
  function load(q){
    var n=++req;if(ctl)ctl.abort();ctl=window.AbortController?new AbortController():null;
    list.innerHTML='';var w=el('li','none loading','Loading…');list.appendChild(w);list.hidden=false;place(input,list);input.setAttribute('aria-expanded','true');
    fetchPage(box.dataset.url,q,1,ctl&&ctl.signal).then(function(d){if(n!==req||document.activeElement!==input)return;items=d;active=-1;render()}).catch(function(){});
  }
  function render(){
    list.innerHTML='';
    if(!items.length)list.appendChild(el('li','none','No matches'));
    items.forEach(function(it,i){
      var li=el('li');li.id=list.id+'-'+i;li.setAttribute('role','option');li.setAttribute('aria-selected','false');
      li.appendChild(el('span',null,it.label));if(it.sub)li.appendChild(el('small',null,it.sub));
      li.addEventListener('mousedown',function(ev){ev.preventDefault()});li.addEventListener('click',function(){pick(i)});list.appendChild(li);
    });
    list.hidden=false;place(input,list);input.setAttribute('aria-expanded','true');
  }
  function close(){list.hidden=true;input.setAttribute('aria-expanded','false');input.removeAttribute('aria-activedescendant');active=-1}
  function highlight(){[].forEach.call(list.children,function(li,i){var on=i===active;li.classList.toggle('on',on);li.setAttribute('aria-selected',String(on));if(on){li.scrollIntoView({block:'nearest'});input.setAttribute('aria-activedescendant',li.id)}})}
  function move(d){if(list.hidden){load(term());return}if(!items.length)return;active=(active+d+items.length)%items.length;highlight()}
  function pick(i){
    var it=items[i];if(!it)return;close();
    var go=function(){input.value=it.label;location.href=it.url};
    if(window.nDirtyOk&&!window.nDirtyOk(go)){input.value=input.dataset.current||'';return}
  }
  input.addEventListener('pointerenter',function(){fetchPage(box.dataset.url,'',1).catch(function(){})});
  input.addEventListener('focus',function(){load(term())});
  input.addEventListener('input',function(){clearTimeout(timer);timer=setTimeout(function(){load(input.value.trim())},250)});
  input.addEventListener('keydown',function(e){
    if(e.key==='ArrowDown'){e.preventDefault();move(1)}
    else if(e.key==='ArrowUp'){e.preventDefault();move(-1)}
    else if(e.key==='Enter'){if(!list.hidden&&items.length){e.preventDefault();pick(active>=0?active:0)}}
    else if(e.key==='Escape'){if(!list.hidden){e.stopPropagation();close();input.value=input.dataset.current||''}}
  });
  input.addEventListener('blur',function(){setTimeout(function(){close();if(input.value!==(input.dataset.current||''))input.value=input.dataset.current||''},150)});
}

function init(box){(box.dataset.picker==='nav'?initNav:initField)(box)}
[].forEach.call(document.querySelectorAll('[data-picker]'),init);
/* Rows added later (sales order and invoice items) start their own pickers. */
window.nPickerInit=init;
})();
