/* Shell behaviour for every signed-in page: phone menu, top-bar menus, toasts, opening sequence. */
(function(){
'use strict';
var $=function(s,r){return (r||document).querySelector(s)}, $$=function(s,r){return [].slice.call((r||document).querySelectorAll(s))};
var reduce=function(){return window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches};
var shell=$('#shell');
$('#menuBtn').addEventListener('click',function(){shell.classList.add('navopen')});
$('#navscrim').addEventListener('click',function(){shell.classList.remove('navopen')});
document.addEventListener('keydown',function(e){if(e.key==='Escape'){shell.classList.remove('navopen');$$('.pop').forEach(function(p){p.remove()})}});
/* Menu fly-outs (Sat 18/10/2026): on a screen with a mouse and the menu at the side, pointing at or tabbing to a module
   shows its pages beside the menu after a short pause; it stays open while the pointer moves into it and closes on
   leaving, on Escape (focus goes back to the module) or when focus moves on. Touch screens and the phone menu keep tap. */
(function(){
  var mq=window.matchMedia&&matchMedia('(hover:hover) and (pointer:fine) and (min-width:1024px)');
  var open=null,tOpen=0,tClose=0;
  function ok(){return mq&&mq.matches&&!shell.classList.contains('navopen')}
  function show(a){
    clearTimeout(tClose);if(open&&open[0]===a)return;hide();
    var f=document.getElementById(a.getAttribute('aria-controls'));if(!f)return;
    var r=a.getBoundingClientRect(),side=$('#side').getBoundingClientRect();
    f.hidden=false;f.style.left=(side.right+6)+'px';
    var h=f.offsetHeight,top=Math.max(8,Math.min(r.top-8,window.innerHeight-h-8));f.style.top=top+'px';
    a.setAttribute('aria-expanded','true');a.classList.add('flyon');open=[a,f];
  }
  function hide(){clearTimeout(tOpen);if(!open)return;open[1].hidden=true;open[0].setAttribute('aria-expanded','false');open[0].classList.remove('flyon');open=null}
  function later(){clearTimeout(tOpen);clearTimeout(tClose);tClose=setTimeout(hide,280)}
  $$('#side a[data-fly]').forEach(function(a){
    var f=document.getElementById(a.getAttribute('aria-controls'));if(!f)return;
    a.addEventListener('pointerenter',function(e){if(e.pointerType!=='mouse'||!ok())return;clearTimeout(tClose);clearTimeout(tOpen);tOpen=setTimeout(function(){show(a)},open?0:140)});
    a.addEventListener('pointerleave',later);
    a.addEventListener('focus',function(){if(ok()&&a.matches(':focus-visible'))show(a)});
    f.addEventListener('pointerenter',function(){clearTimeout(tClose)});
    f.addEventListener('pointerleave',later);
    f.addEventListener('keydown',function(e){if(e.key==='Escape'){e.stopPropagation();hide();a.focus()}});
    a.addEventListener('keydown',function(e){if(e.key==='Escape'&&open){e.stopPropagation();hide()}});
  });
  document.addEventListener('focusin',function(e){if(open&&!open[0].contains(e.target)&&!open[1].contains(e.target))hide()});
  $('#side nav')&&$('#side nav').addEventListener('scroll',hide,{passive:true});
  window.addEventListener('resize',hide);
})();
/* Menu bar drop-downs (22/10/2026): pointing at a menu opens its pages after a short pause and keeps them open while
   the pointer moves into the list; tabbing to a menu opens them too, Tab walks through them, Arrow Down / Up move in
   the list, Arrow Left / Right move between menus, Escape closes (focus goes back to the menu). On touch the first tap
   opens the list and a second tap opens the menu's first page; a click with a mouse always opens the first page. */
(function(){
  var nav=$('#topnav'),top=$('#top');if(!nav)return;
  var items=$$('#topnav [data-tndd]'),more=$('#topnav .tnmore'),lis=$$('#topnav .tnw:not(.tnmore)');
  var open=null,tOpen=0,tClose=0,quiet=false;
  function all(){return $$('#topnav .tn').filter(function(x){return !x.closest('[hidden]')})}
  function dd(a){return document.getElementById(a.getAttribute('aria-controls'))}
  function links(a){return $$('a',dd(a)).filter(function(x){return !x.closest('.tnmg[hidden]')})}
  /* "More" (responsive menu bar): while the row does not fit beside the chips (no gap left before them, or the bar
     would scroll), the last menus move into More, so nothing is clipped and the page never scrolls sideways. */
  function fit(){
    if(!more||getComputedStyle(nav).display==='none')return;
    hide();more.hidden=true;
    lis.forEach(function(li){li.hidden=false});$$('.tnmg',more).forEach(function(g){g.hidden=true});
    var tsp=$('.tsp',top),shown=lis.slice();
    function tight(){return top.scrollWidth>top.clientWidth+1||(tsp&&tsp.getBoundingClientRect().width<12)}
    while(tight()&&shown.length>1){
      var li=shown.pop();li.hidden=true;more.hidden=false;
      var key=li.querySelector('.tn').id.replace('tn-','');var g=$('.tnmg[data-for="'+key+'"]',more);if(g)g.hidden=false;
    }
    var mb=$('#tn-more');mb.classList.toggle('on',!!$('.tnmg:not([hidden]) .tnmh.on',more));
  }
  var tFit=0;function refit(){clearTimeout(tFit);tFit=setTimeout(fit,60)}
  fit();window.addEventListener('resize',refit);document.fonts&&document.fonts.ready.then(refit);
  window.ResizeObserver&&new ResizeObserver(refit).observe(top);
  // byClick: the open list was opened by a click (or Enter). A list opened by pointing or tabbing stays open on the
  // first click; only a later click closes it (Escape, an outside click and moving away close it too).
  var byClick=false;
  function show(a,how){
    clearTimeout(tClose);clearTimeout(tOpen);if(open===a)return;hide();
    var d=dd(a),st=d.style;d.hidden=false;d.classList.remove('cols');st.columnCount=st.width=st.height=st.columnFill=st.left=st.right='';
    if(d.id!=='tndd-more')d.classList.remove('end');
    // Never a scroll bar: a list taller than the window flows into as many columns as it needs (filled top to bottom
    // to the window's height) and flips left when too wide.
    var r=d.getBoundingClientRect(),room=window.innerHeight-r.top-8;
    if(r.bottom>window.innerHeight-8&&room>60){
      var max=Math.max(1,Math.floor((window.innerWidth-16)/190)),n=Math.min(max,Math.ceil(r.height/room));
      d.classList.add('cols');st.height=room+'px';st.columnFill='auto';
      for(;;){st.columnCount=n;st.width=Math.min(n*200,window.innerWidth-16)+'px';if(d.scrollWidth<=d.clientWidth+1||n>=max)break;n++}
    }
    if(d.getBoundingClientRect().right>window.innerWidth-8)d.classList.add('end');
    // Still past the left edge (a wide list under a menu near the left): pin it 8px from the window's left edge.
    if(d.getBoundingClientRect().left<8){st.left=(8-a.parentNode.getBoundingClientRect().left)+'px';st.right='auto'}
    a.setAttribute('aria-expanded','true');open=a;byClick=how==='click';
  }
  function hide(){clearTimeout(tOpen);if(!open)return;dd(open).hidden=true;open.setAttribute('aria-expanded','false');open=null}
  function later(){clearTimeout(tOpen);clearTimeout(tClose);tClose=setTimeout(hide,250)}
  function back(a){hide();quiet=true;a.focus();quiet=false}
  function move(a,step){var t=all(),n=t[(t.indexOf(a)+step+t.length)%t.length];hide();n.focus()}
  $$('#topnav .tn').forEach(function(a){
    a.addEventListener('keydown',function(e){if(e.key==='ArrowRight'||e.key==='ArrowLeft'){e.preventDefault();move(a,e.key==='ArrowRight'?1:-1)}});
  });
  items.forEach(function(a){
    var li=a.parentNode,d=dd(a),touch=false;if(!d)return;
    li.addEventListener('pointerenter',function(e){if(e.pointerType!=='mouse')return;clearTimeout(tClose);clearTimeout(tOpen);tOpen=setTimeout(function(){show(a)},open?0:120)});
    li.addEventListener('pointerleave',function(e){if(e.pointerType==='mouse')later()});
    a.addEventListener('pointerdown',function(e){touch=e.pointerType!=='mouse'});
    a.addEventListener('click',function(e){
      if(a.tagName==='BUTTON'){if(open!==a)show(a,'click');else if(!byClick)byClick=true;else hide()}
      else if(touch&&open!==a){e.preventDefault();show(a,'click')}
      touch=false;
    });
    a.addEventListener('focus',function(){if(!quiet&&a.matches(':focus-visible'))show(a)});
    a.addEventListener('keydown',function(e){
      var l=links(a);
      if(e.key==='ArrowDown'||e.key===' '){e.preventDefault();show(a);(l.filter(function(x){return x.classList.contains('on')})[0]||l[0]).focus()}
      else if(e.key==='ArrowUp'){e.preventDefault();show(a);l[l.length-1].focus()}
      else if(e.key==='Escape'&&open){e.stopPropagation();hide()}
    });
    d.addEventListener('keydown',function(e){
      var l=links(a),i=l.indexOf(document.activeElement);
      if(e.key==='ArrowDown'){e.preventDefault();l[(i+1)%l.length].focus()}
      else if(e.key==='ArrowUp'){e.preventDefault();l[(i-1+l.length)%l.length].focus()}
      else if(e.key==='Home'){e.preventDefault();l[0].focus()}
      else if(e.key==='End'){e.preventDefault();l[l.length-1].focus()}
      else if(e.key==='Escape'){e.stopPropagation();back(a)}
      else if(e.key==='ArrowRight'||e.key==='ArrowLeft'){e.preventDefault();move(a,e.key==='ArrowRight'?1:-1)}
    });
  });
  document.addEventListener('focusin',function(e){if(open&&!open.parentNode.contains(e.target))hide()});
  document.addEventListener('pointerdown',function(e){if(open&&!open.parentNode.contains(e.target))hide()});
  window.addEventListener('resize',hide);
})();
/* Side menu report groups (22/10/2026): on a wide screen with Reports selected, groups that do not fit the window's
   height fold (the last ones first, never the group of the open report), so the side menu needs no scroll bar.
   Opening a folded group folds others again as needed. Phones and tablets (the drawer) keep every group open. */
(function(){
  var nav=$('#side nav.scoped');if(!nav)return;
  var groups=$$('.rg',nav);if(!groups.length)return;
  var mq=window.matchMedia&&matchMedia('(min-width:1024px)');
  function set(g,o){$('.rgt',g).setAttribute('aria-expanded',o?'true':'false');$('.rgl',g).hidden=!o}
  function over(){return nav.scrollHeight>nav.clientHeight+1}
  // mine: groups the user opened by hand, newest last. Refitting keeps them open and folds the others first.
  var mine=[];
  function drop(g){mine=mine.filter(function(x){return x!==g})}
  function shrink(keep){
    groups.filter(function(g){return keep.indexOf(g)<0&&!$('a.on',g)}).reverse().forEach(function(g){if(over()){set(g,false);drop(g)}});
    keep.slice(0,-1).forEach(function(g){if(over()&&!$('a.on',g)){set(g,false);drop(g)}});
  }
  function fit(){groups.forEach(function(g){set(g,true)});if(mq&&mq.matches)shrink(mine.slice())}
  groups.forEach(function(g){
    $('.rgt',g).addEventListener('click',function(){
      var o=$('.rgt',g).getAttribute('aria-expanded')!=='true';set(g,o);drop(g);
      if(o){mine.push(g);if(mq&&mq.matches)shrink(mine.slice())}
    });
  });
  var t=0;function refit(){clearTimeout(t);t=setTimeout(fit,120)}
  fit();window.addEventListener('resize',refit);
  // The side menu's own height changes with the window (and when it shows again after the phone width).
  window.ResizeObserver&&new ResizeObserver(refit).observe(nav);
})();
/* Phone lists: the Filters button opens and closes the panel with the views and sort (Sat 18/10/2026). */
$$('[data-fold]').forEach(function(b){var p=document.getElementById(b.getAttribute('aria-controls'));if(!p)return;
  b.addEventListener('click',function(){var o=!p.classList.contains('open');p.classList.toggle('open',o);b.setAttribute('aria-expanded',o?'true':'false');if(o){var f=p.querySelector('a.on,select');f&&f.focus()}});
});
/* Uploads: a file over the size limit or of a type the box does not take is refused before it is sent (CRM upload
   check 18/10/2026), so a large file never fails on the server's post limit. The server checks again. */
document.addEventListener('change',function(e){
  var f=e.target;if(!f.matches||!f.matches('input[type=file][data-max-kb]')||!f.files||!f.files[0])return;
  var ok=(f.accept||'').toLowerCase().split(',').map(function(x){return x.trim()}),many=f.files.length>1,msg='';
  // Every chosen file of a multiple input is checked, named in the message (new PO documents, Fri 23/10/2026).
  for(var i=0;i<f.files.length&&!msg;i++){
    var file=f.files[i],ext=(file.name.split('.').pop()||'').toLowerCase(),who=many?file.name+': ':'';
    // Same words as the server, in MB with at most one decimal: "1 MB", "0.5 MB" (Admin > Config upload size, 22/10/2026).
    if(file.size>(+f.dataset.maxKb)*1024)msg=who+'The file must not be larger than '+(Math.round(+f.dataset.maxKb/102.4)/10)+' MB.';
    else if(ok.length&&ok[0]&&ok.indexOf('.'+ext)<0&&!(ok.indexOf('image/*')>=0&&/^image\//.test(file.type)))msg=who+'This type of file cannot be uploaded here.';
  }
  f.setCustomValidity(msg);if(msg){f.reportValidity();f.value=''}
});
$('#content').addEventListener('scroll',function(){$('#top').classList.toggle('scrolled',this.scrollTop>8)},{passive:true});
document.addEventListener('pointermove',function(e){var c=e.target.closest&&e.target.closest('.kpi,.spot');if(!c)return;var r=c.getBoundingClientRect();c.style.setProperty('--mx',(e.clientX-r.left)+'px');c.style.setProperty('--my',(e.clientY-r.top)+'px')},{passive:true});

function pop(anchor,html){
  $$('.pop').forEach(function(p){p.remove()});
  var p=document.createElement('div');p.className='pop';p.setAttribute('role','menu');p.innerHTML=html;document.body.appendChild(p);
  var r=anchor.getBoundingClientRect(),w=p.offsetWidth;
  p.style.top=(r.bottom+6)+'px';p.style.left=Math.max(8,Math.min(window.innerWidth-w-8,r.right-w))+'px';
  setTimeout(function(){document.addEventListener('click',function off(e){if(!p.contains(e.target)){p.remove();document.removeEventListener('click',off)}})},0);
  /* Keyboard (UX review M3): items are menu items; Up and Down move, Escape or Tab closes and returns to the opener. */
  var items=$$('a,button',p);items.forEach(function(x){x.setAttribute('role','menuitem')});
  function shut(){p.remove();if(anchor.focus)anchor.focus()}
  p.addEventListener('keydown',function(e){
    var i=items.indexOf(document.activeElement);
    if(e.key==='ArrowDown'||e.key==='ArrowUp'){e.preventDefault();if(!items.length)return;i=e.key==='ArrowDown'?(i+1)%items.length:(i-1+items.length)%items.length;items[i].focus()}
    else if(e.key==='Escape'||e.key==='Tab'){e.preventDefault();e.stopPropagation();shut()}
  });
  if(items[0])items[0].focus();
}
$('#profile').addEventListener('click',function(e){e.stopPropagation();pop(e.currentTarget,$('#profileMenu').innerHTML)});
$('#unitChip').addEventListener('click',function(e){e.stopPropagation();pop(e.currentTarget,$('#unitMenu').innerHTML)});
var alertBtn=$('#alertBtn');
if(alertBtn)alertBtn.addEventListener('click',function(e){e.stopPropagation();pop(e.currentTarget,$('#alertMenu').innerHTML)});

/* Open dialogs keep keyboard focus inside (UX review M2): Tab and Shift+Tab wrap between the first and
   last controls, and focus that lands behind the dialog is brought back into it. */
function openLayer(){var l=$$('.overlay').filter(function(x){return !x.hidden});return l[l.length-1]||null}
function focusables(l){return $$('a[href],button:not([disabled]),input:not([type=hidden]):not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])',l).filter(function(x){return x.offsetParent!==null||x===document.activeElement})}
document.addEventListener('keydown',function(e){
  if(e.key!=='Tab')return;var l=openLayer();if(!l)return;
  var f=focusables(l);if(!f.length)return;
  var first=f[0],last=f[f.length-1];
  if(!l.contains(document.activeElement)){e.preventDefault();first.focus();return}
  if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus()}
  else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus()}
});
document.addEventListener('focusin',function(e){var l=openLayer();if(l&&!l.contains(e.target)){var f=focusables(l);if(f[0])f[0].focus()}});

/* Follow-up due date: red pop up (CRM changes). Once per browser session for the same list of due follow-ups. */
(function(){
  var p=$('#duePop');if(!p)return;
  var key='nursery-due-'+p.dataset.sig,seen=false;
  try{seen=sessionStorage.getItem(key)==='1'}catch(e){}
  if(seen)return;
  var back=document.activeElement;
  function hide(){p.hidden=true;try{sessionStorage.setItem(key,'1')}catch(e){}document.removeEventListener('keydown',esc);if(back&&back.focus)back.focus()}
  function esc(e){if(e.key==='Escape')hide()}
  $$('[data-x]',p).forEach(function(x){x.addEventListener('click',hide)});
  $$('a',p).forEach(function(a){a.addEventListener('click',function(){try{sessionStorage.setItem(key,'1')}catch(e){}})});
  document.addEventListener('keydown',esc);
  p.hidden=false;var b=p.querySelector('button[data-x]');if(b)b.focus();
})();
$('#fyChip').addEventListener('click',function(e){e.stopPropagation();pop(e.currentTarget,'<div class="cap muted" style="padding:6px 10px;max-width:250px">Entries are financial-year wise, except masters (OTHER POINTS A22). FY starts 1 April</div>')});

window.nToast=function(msg,kind){
  var t=document.createElement('div');t.className='toast';
  t.innerHTML='<svg class="ico" style="color:var('+(kind==='err'?'--error-600':'--success-600')+')"><use href="#i-'+(kind==='err'?'alert':'check-c')+'"/></svg><span></span>';
  t.querySelector('span').textContent=msg;
  $('#toasts').appendChild(t);
  setTimeout(function(){if(reduce()){t.remove();return}t.classList.add('out');setTimeout(function(){t.remove()},260)},4300);
};
(window.NURSERY_TOASTS||[]).forEach(function(m){nToast(m)});
(window.NURSERY_ERRORS||[]).forEach(function(m){nToast(m,'err')});

/* Confirm before a form with data-confirm submits (deletes, Submit / Send for approval): a pop-up with the message and
   Continue / Go back (Sat 18/10/2026), instead of the browser's own box. Escape or the backdrop goes back. */
window.nAsk=function(title,msg,ok){
  var back=document.activeElement,ov=document.createElement('div');ov.className='overlay';ov.setAttribute('data-ask','');
  ov.innerHTML='<div class="scrim" data-x></div><div class="modal" role="alertdialog" aria-modal="true" aria-labelledby="askT" aria-describedby="askM">'+
    '<div class="mh"><h3 id="askT"></h3></div><div class="mb"><p id="askM" style="margin:0"></p></div>'+
    '<div class="mf" style="display:flex;gap:8px;justify-content:flex-end;padding:12px 20px 18px"><button class="btn gh" type="button" data-x>Go back</button><button class="btn pri" type="button" data-ok>Continue</button></div></div>';
  ov.querySelector('#askT').textContent=title;ov.querySelector('#askM').textContent=msg;
  document.body.appendChild(ov);
  var done=function(go){ov.remove();if(go)ok();else if(back&&back.focus)back.focus()};
  ov.addEventListener('click',function(e){if(e.target.closest('[data-ok]'))done(true);else if(e.target.closest('[data-x]'))done(false)});
  ov.addEventListener('keydown',function(e){if(e.key==='Escape'){e.stopPropagation();done(false)}
    else if(e.key==='Tab'){var b=$$('button',ov),i=b.indexOf(document.activeElement);if(e.shiftKey&&i<=0){e.preventDefault();b[b.length-1].focus()}else if(!e.shiftKey&&i===b.length-1){e.preventDefault();b[0].focus()}}});
  ov.querySelector('[data-ok]').focus();
};
document.addEventListener('submit',function(e){
  var f=e.target;if(!f.dataset.confirm)return;
  if(f.dataset.confirmed){delete f.dataset.confirmed;return}
  e.preventDefault();var by=e.submitter;
  nAsk(f.dataset.confirmTitle||'Please confirm',f.dataset.confirm,function(){f.dataset.confirmed='1';if(f.requestSubmit)f.requestSubmit(by&&by.form===f?by:undefined);else f.submit()});
},true);

/* Unsaved changes (review M3): a form marked data-dirty-guard asks before the page is left. */
var dirtyForm=null,leaving=false;
$$('form[data-dirty-guard]').forEach(function(f){
  var mark=function(e){if(e.target.name&&e.target.name!=='_token')dirtyForm=f};
  f.addEventListener('input',mark);f.addEventListener('change',mark);
});
window.nDirtyOk=function(go){if(!dirtyForm){if(go)go();return true}
  nAsk('Unsaved changes','The changes you typed are not saved and will be lost. Leave without saving them?',function(){dirtyForm=null;leaving=true;if(go)go()});return false};
document.addEventListener('click',function(e){
  var a=e.target.closest&&e.target.closest('a[href]');
  if(!a||!dirtyForm||a.hasAttribute('download')||a.target==='_blank'||a.getAttribute('href').charAt(0)==='#'||e.defaultPrevented)return;
  if(!window.nDirtyOk(function(){location.href=a.href}))e.preventDefault();
});
window.addEventListener('beforeunload',function(e){if(dirtyForm&&!leaving){e.preventDefault();e.returnValue=''}});

/* Save buttons (review M8): on a form marked data-saving, disable the primary button and show "Saving…". */
document.addEventListener('submit',function(e){
  if(e.defaultPrevented)return;
  /* Another form on the page is being sent while this one has unsaved changes (UX review M1): ask first. */
  if(dirtyForm&&e.target!==dirtyForm){
    e.preventDefault();var f0=e.target,by0=e.submitter;
    nAsk('Unsaved changes','The changes you typed above are not saved and will be lost. Continue without saving them?',function(){
      dirtyForm=null;if(f0.requestSubmit)f0.requestSubmit(by0&&by0.form===f0?by0:undefined);else f0.submit()});
    return;
  }
  leaving=true;
  var f=e.target;if(!f.hasAttribute('data-saving'))return;
  var btns=$$('button[type=submit].pri',f).filter(function(b){var o=b.getAttribute('form');return !o||o===f.id});
  if(f.id)btns=btns.concat($$('button[type=submit].pri[form="'+f.id+'"]').filter(function(b){return !f.contains(b)}));
  btns.forEach(function(b){b.dataset.html=b.innerHTML;b.disabled=true;b.innerHTML='<span class="spin" aria-hidden="true"></span>Saving…'});
});
window.addEventListener('pageshow',function(e){
  if(!e.persisted)return;
  leaving=false;
  $$('button[data-html]').forEach(function(b){b.innerHTML=b.dataset.html;b.disabled=false;delete b.dataset.html});
});

/* Entrance cascade and count-up, as in the prototype */
function stagger(){
  if(reduce())return;
  var els=$$('#view .pagehd, #view .entrybar, #view .filterbar, #view .kpi, #view > .sectcard, #view > form > .sectcard, #view .rolecard, #view > .tablecard');
  els.slice(0,14).forEach(function(e,i){e.style.setProperty('--i',i);e.classList.add('cin')});
}
/* Opening sequence once per browser session (decision M1) */
(function(){
  var seen=false;try{seen=sessionStorage.getItem('nursery-intro')==='1';sessionStorage.setItem('nursery-intro','1')}catch(e){}
  if(seen||reduce()){if(!window.NURSERY_PREVIEW)stagger();return}
  var it=document.createElement('div');it.id='intro';it.setAttribute('aria-hidden','true');
  it.innerHTML='<div class="im"><div class="bm">N</div><div class="wm">Nursery</div><div class="ln"></div></div>';
  document.body.appendChild(it);shell.classList.add('booting');if(!window.NURSERY_PREVIEW)stagger();
  function done(){if(!it.parentNode)return;it.remove();shell.classList.remove('booting');document.removeEventListener('pointerdown',done);document.removeEventListener('keydown',done)}
  setTimeout(done,1700);document.addEventListener('pointerdown',done);document.addEventListener('keydown',done);
})();
})();
