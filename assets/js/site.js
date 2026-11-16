/* JC Síndico Profissional — interações do site */
(function(){
'use strict';
function boot(){
  function $(s,c){return (c||document).querySelector(s)}
  function $$(s,c){return Array.prototype.slice.call((c||document).querySelectorAll(s))}
  function el(tag,cls,txt){var e=document.createElement(tag);if(cls)e.className=cls;if(txt!=null)e.textContent=txt;return e}
  var toastT;function toast(m){var t=$('#toast');if(!t)return;t.textContent=m;t.hidden=false;clearTimeout(toastT);toastT=setTimeout(function(){t.hidden=true},2600)}
  var root=document.documentElement,body=document.body;
  var API=body.dataset.api||'/api/',WA=body.dataset.wa||'';
  if(WA&&WA.indexOf('55')!==0)WA='55'+WA;
  function waLink(txt){return 'https://wa.me/'+WA+(txt?'?text='+encodeURIComponent(txt):'')}
  function reduced(){return window.matchMedia('(prefers-reduced-motion: reduce)').matches}

  /* chamada à API: JSON de ida e volta, erro amigável */
  function api(path,data){
    var opt={method:data?'POST':'GET',headers:{'Accept':'application/json'},credentials:'same-origin'};
    if(data){opt.headers['Content-Type']='application/json';opt.body=JSON.stringify(data)}
    return fetch(API+path,opt).then(function(r){return r.json().catch(function(){return {ok:false,erro:'Não foi possível enviar agora. Tente de novo ou chame no WhatsApp.'}})},
      function(){return {ok:false,erro:'Sem conexão. Verifique a internet e tente de novo.'}});
  }

  var top=$('#topbar'),burger=$('#burger');
  var hero=$('.sh');
  function onScroll(){var y=window.scrollY;top.classList.toggle('scrolled',y>8);if(hero)body.classList.toggle('past-hero',y>hero.offsetHeight*.6)}
  window.addEventListener('scroll',onScroll,{passive:true});onScroll();

  /* ---------- elevador: painel de andares, portas e display ---------- */
  var liftPanel=$('#lift-panel'),fNum=$('#f-num'),fLbl=$('#f-lbl'),fDir=$('#f-dir');
  var floors=$$('main [data-floor]'),order=floors.map(function(s){return s.id}),current='inicio',riding=false,jumping=false;
  function openLift(o,silent){if(!liftPanel)return;liftPanel.hidden=!o;burger.setAttribute('aria-expanded',String(o));
    if(o){$$('.lift-plate [data-ride]').forEach(function(b){b.classList.toggle('here',b.dataset.ride===current)});var f=$('.lift-plate button.here')||$('.lift-plate button');f.focus()}
    else if(!silent)burger.focus()}
  if(liftPanel){
    liftPanel.addEventListener('keydown',function(e){if(e.key!=='Tab')return;var f=$$('button,a[href]',liftPanel);if(!f.length)return;var a=f[0],z=f[f.length-1];
      if(e.shiftKey&&document.activeElement===a){e.preventDefault();z.focus()}else if(!e.shiftKey&&document.activeElement===z){e.preventDefault();a.focus()}});
    burger.addEventListener('click',function(){openLift(true)});
    $('#lift-x').addEventListener('click',function(){openLift(false)});
    liftPanel.addEventListener('click',function(e){if(e.target===liftPanel)openLift(false)});
    document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!liftPanel.hidden)openLift(false)});
  }
  function focusSec(id){var s=document.getElementById(id),h=s&&s.querySelector('h1,h2');if(h){h.setAttribute('tabindex','-1');h.focus({preventScroll:true})}}
  function setFloor(id){var s=document.getElementById(id);if(!s||!s.dataset.floor)return;
    fDir.textContent=order.indexOf(id)>=order.indexOf(current)?'▲':'▼';fNum.textContent=s.dataset.floor;fLbl.textContent=s.dataset.label;current=id}
  function jumpTo(id){var t=document.getElementById(id);if(!t)return;
    var y=id==='inicio'?0:t.getBoundingClientRect().top+window.scrollY,h=root,prev=h.style.scrollBehavior;
    h.style.scrollBehavior='auto';window.scrollTo(0,y);h.style.scrollBehavior=prev;
    if(window.ScrollTrigger)ScrollTrigger.update()}
  function ride(id,then){
    var s=document.getElementById(id);if(!s)return;
    if(history.replaceState)history.replaceState(null,'',id==='inicio'?location.pathname:'#'+id);
    if(!window.gsap||reduced()){jumpTo(id);setFloor(id);focusSec(id);if(then)then();return}
    if(riding)return;riding=true;jumping=true;
    var doors=$('#doors'),dl=doors.querySelector('.l'),dr=doors.querySelector('.r'),disp=$('#d-display'),seam=$('#seam');
    disp.textContent=s.dataset.floor==='T'?'Térreo':s.dataset.floor+'º andar';disp.appendChild(el('small',null,s.dataset.label));
    gsap.timeline({onComplete:function(){riding=false;setTimeout(function(){jumping=false},150);if(then)then()}})
      .set(doors,{visibility:'visible'}).set(dl,{xPercent:-101}).set(dr,{xPercent:101})
      .to(dl,{xPercent:0,duration:.55,ease:'power3.inOut'}).to(dr,{xPercent:0,duration:.55,ease:'power3.inOut'},'<')
      .fromTo(disp,{opacity:0,y:6},{opacity:1,y:0,duration:.3},'-=.15')
      .add(function(){jumpTo(id);setFloor(id);focusSec(id)})
      .to({},{duration:.35})
      .to(disp,{opacity:0,duration:.2})
      .fromTo(seam,{scaleY:0,opacity:1},{scaleY:1,duration:.25,ease:'power2.out'},'<')
      .to(dl,{xPercent:-101,duration:.75,ease:'power3.inOut'}).to(dr,{xPercent:101,duration:.75,ease:'power3.inOut'},'<')
      .to(seam,{opacity:0,duration:.4},'<')
      .set(doors,{visibility:'hidden'});
  }
  document.addEventListener('click',function(e){
    var b=e.target.closest('[data-ride]');
    var a=!b&&e.target.closest('#nav a[href^="#"],.top-cta a[href^="#"]');
    if(!b&&!a)return;
    var id=b?b.dataset.ride:a.getAttribute('href').slice(1);
    var t=document.getElementById(id);if(!t||!t.dataset.floor)return;
    e.preventDefault();
    if(b&&b.closest('.lift-plate')){b.classList.add('lit');setTimeout(function(){b.classList.remove('lit')},900)}
    var inPanel=b&&b.closest('.lift-plate');
    setTimeout(function(){openLift(false,true);ride(id)},inPanel?240:0);
  });

  /* ---------- diagnóstico: uma pergunta por vez ---------- */
  var QX=[];try{QX=JSON.parse($('#diag-data').textContent)}catch(x){}
  var qz=$('#qz');
  if(qz&&QX.length){
    var N=QX.length,ans=QX.map(function(){return null}),cur=0,stage=$('#qz-stage'),floorsBar=$('#qz-floors'),qzBusy=false,diagId=0,diagSent=false;
    stage.textContent=''; /* remove o aviso <noscript> para a primeira pergunta entrar sem espera */
    QX.forEach(function(q,i){var b=el('button','qf');b.type='button';b.tabIndex=-1;b.setAttribute('aria-label','Ir para a pergunta '+(i+1));b.addEventListener('click',function(){go(i)});floorsBar.appendChild(b)});
    var paintFloors=function(){Array.prototype.forEach.call(floorsBar.children,function(f,i){f.className='qf'+(ans[i]?' '+ans[i]:'')+(i===cur?' cur':'')})};
    var OPTS=[['ok','Sim, está em dia','✓'],['no','Não','✕'],['ns','Não sei','?']];
    var viewQuestion=function(i){
      var q=QX[i],w=el('div','qz-q');
      w.appendChild(el('span','qz-cat',q[0]));
      var h=el('h3',null,q[1]);h.id='qz-title';w.appendChild(h);
      var opts=el('div','qz-opts');opts.setAttribute('role','group');opts.setAttribute('aria-labelledby','qz-title');
      OPTS.forEach(function(o,k){var b=el('button','qz-opt '+o[0]);b.type='button';b.dataset.v=o[0];b.setAttribute('aria-pressed',String(ans[i]===o[0]));
        b.appendChild(el('i',null,o[2]));b.appendChild(el('span',null,o[1]));b.appendChild(el('kbd',null,String(k+1)));
        b.addEventListener('click',function(){answer(o[0])});opts.appendChild(b)});
      w.appendChild(opts);return w;
    };
    var diagMsg=function(ok){
      var pend=QX.filter(function(q,i){return ans[i]==='no'}).map(function(q){return q[1].replace(/\?$/,'')});
      var nsi=QX.filter(function(q,i){return ans[i]==='ns'}).length;
      return 'Olá Junior, fiz o diagnóstico no site: '+ok+' de '+N+' itens em dia.'+(pend.length?' Pendentes: '+pend.join('; ')+'.':'')+(nsi?' Sem informação: '+nsi+'.':'');
    };
    var saveDiag=function(){if(diagSent)return;diagSent=true;api('diagnostico.php',{respostas:ans}).then(function(r){if(r&&r.ok)diagId=r.id})};
    var viewResult=function(){
      var ok=0,no=0,ns=0;ans.forEach(function(a){if(a==='ok')ok++;else if(a==='no')no++;else if(a==='ns')ns++});
      saveDiag();
      var w=el('div','qz-res');
      var bld=el('div','qz-build');bld.setAttribute('aria-hidden','true');bld.appendChild(el('span','roof'));
      for(var i=N-1;i>=0;i--){var f=el('span','fl '+(ans[i]||'skip'));f.style.setProperty('--n',N-1-i);bld.appendChild(f)}
      bld.appendChild(el('span','base'));w.appendChild(bld);
      var t=el('div','qz-rtext');
      t.appendChild(el('span','qz-cat','Resultado do seu prédio'));
      var h=el('h3');h.appendChild(el('b',null,String(ok)));h.appendChild(document.createTextNode(' de '+N+' itens em dia'));t.appendChild(h);
      var v=no===0&&ns===0&&ok===N?'Tudo em dia. Seu condomínio está muito bem cuidado.':(no>=4?'Há pontos importantes em aberto. Vale uma revisão completa da gestão.':(no?'Poucos pontos em aberto, fáceis de resolver com um plano simples.':'Nenhuma pendência confirmada, mas há itens sem informação.'));
      t.appendChild(el('p','qz-verdict',v));
      var pend=[];ans.forEach(function(a,i){if(a==='no'||a==='ns')pend.push(i)});
      if(pend.length){var ul=el('ul','qz-pend');pend.forEach(function(i){var li=el('li',ans[i]);li.appendChild(el('b',null,QX[i][1].replace(/\?$/,'')));li.appendChild(el('small',null,ans[i]==='ns'?'Sem informação: vale conferir. '+QX[i][2]:QX[i][2]));ul.appendChild(li)});t.appendChild(ul)}
      var act=el('div','qz-act');
      var s=el('a','btn btn-sun','Enviar pelo WhatsApp');s.href=waLink(diagMsg(ok));s.target='_blank';s.rel='noopener';
      s.addEventListener('click',function(){if(diagId)api('diagnostico.php',{id:diagId,canal:'whatsapp'})});
      var f2=el('button','btn btn-line','Usar no formulário');f2.type='button';f2.addEventListener('click',function(){$('#f-msg').value=diagMsg(ok);leadDiag=diagId;setTab('prop');ride('contato',function(){$('#f-nome').focus({preventScroll:true})})});
      var r=el('button','btn btn-line','Refazer');r.type='button';r.addEventListener('click',function(){ans=ans.map(function(){return null});diagSent=false;diagId=0;go(0,-1)});
      act.appendChild(s);act.appendChild(f2);act.appendChild(r);t.appendChild(act);w.appendChild(t);return w;
    };
    var render=function(view,dir){
      var old=stage.firstElementChild,anim=!reduced()&&old&&old.animate,hadFocus=qz.contains(document.activeElement);
      function put(){stage.textContent='';stage.appendChild(view);
        if(hadFocus){var hf=view.querySelector('h3');if(hf){hf.tabIndex=-1;hf.focus({preventScroll:true})}}
        if(anim)view.animate([{opacity:0,transform:'translateX('+(40*dir)+'px)'},{opacity:1,transform:'none'}],{duration:340,easing:'cubic-bezier(.16,1,.3,1)'});qzBusy=false}
      qzBusy=true;
      if(anim){old.animate([{opacity:1,transform:'none'},{opacity:0,transform:'translateX('+(-30*dir)+'px)'}],{duration:180,easing:'ease-in',fill:'forwards'});setTimeout(put,170)}else put();
    };
    var advT;
    var go=function(i,dir){
      clearTimeout(advT);if(qzBusy)return;dir=dir||(i>=cur?1:-1);cur=i;
      if(i>=N){$('#qz-step').textContent='Diagnóstico concluído';qz.classList.add('done');render(viewResult(),1)}
      else{qz.classList.remove('done');$('#qz-step').textContent='Pergunta '+(i+1)+' de '+N;render(viewQuestion(i),dir)}
      $('#qz-back').disabled=i===0;paintFloors();
    };
    var answer=function(v){
      if(qzBusy)return;ans[cur]=v;paintFloors();
      $$('.qz-opt',stage).forEach(function(b){b.setAttribute('aria-pressed',String(b.dataset.v===v))});
      var nxt=cur+1;while(nxt<N&&ans[nxt])nxt++;
      advT=setTimeout(function(){go(nxt,1)},reduced()?0:260);
    };
    $('#qz-back').addEventListener('click',function(){if(cur>0)go(cur-1,-1)});
    $('#qz-skip').addEventListener('click',function(){go(Math.min(N,cur+1),1)});
    qz.addEventListener('keydown',function(e){
      if(e.target.closest('input,textarea,select'))return;
      if(cur<N&&(e.key==='1'||e.key==='2'||e.key==='3')){answer(OPTS[+e.key-1][0]);e.preventDefault()}
      else if(e.key==='ArrowLeft'&&cur>0){go(cur-1,-1)}
      else if(e.key==='ArrowRight'&&cur<N){go(cur+1,1)}
    });
    go(0,1);
  }

  /* ---------- carrossel de condomínios: livre, sem prender a rolagem ---------- */
  var cx=$('#condos-x'),cSec=$('#condominios');
  if(cx){
    var autoT=null,touched=false,secVisible=false;
    var cItems=function(){return $$('.condo',cx)};
    var step=function(){var it=cItems();return it.length>1?it[1].offsetLeft-it[0].offsetLeft:cx.clientWidth};
    var cGo=function(dir){var max=cx.scrollWidth-cx.clientWidth;var tgt=cx.scrollLeft+dir*step();
      if(dir>0&&cx.scrollLeft>=max-4)tgt=0;cx.scrollTo({left:tgt,behavior:reduced()?'auto':'smooth'})};
    var pauseUI=function(){var b=$('#h-pause');b.setAttribute('aria-pressed',String(touched));b.textContent=touched?'▶':'❚❚';b.setAttribute('aria-label',touched?'Retomar passagem automática':'Pausar passagem automática')};
    var cUpdate=function(){var it=cItems(),max=Math.max(1,cx.scrollWidth-cx.clientWidth),p=Math.min(1,cx.scrollLeft/max);
      $('#h-bar').style.transform='scaleX('+Math.max(.04,p).toFixed(3)+')';
      var idx=Math.min(it.length,Math.round(p*(it.length-1))+1);
      $('#h-count').textContent=idx+' de '+it.length+(touched?'':' · deslize ou use as setas');
      $('#h-prev').disabled=cx.scrollLeft<4;pauseUI()};
    var startAuto=function(){if(touched||autoT||reduced())return;autoT=setInterval(function(){cGo(1)},4200)};
    var stopAuto=function(){clearInterval(autoT);autoT=null};
    var userTook=function(){touched=true;stopAuto();cUpdate()};
    /* desenha o cartão quando a seção está na tela e o cartão está visível no carrossel */
    var checkCondos=function(){
      var vh=window.innerHeight||800,sr=cSec.getBoundingClientRect();
      var vis=Math.min(vh,sr.bottom)-Math.max(0,sr.top);secVisible=vis>Math.min(vh,sr.height)*.4;
      if(secVisible){var xr=cx.getBoundingClientRect();cItems().forEach(function(c){var r=c.getBoundingClientRect(),w=Math.min(xr.right,r.right)-Math.max(xr.left,r.left);if(w>r.width*.55)c.classList.add('drawn')});startAuto()}else stopAuto();
    };
    var cRaf=0,cQueue=function(){if(!cRaf)cRaf=requestAnimationFrame(function(){cRaf=0;checkCondos()})};
    if(!reduced())cSec.classList.add('js-anim');
    window.addEventListener('scroll',cQueue,{passive:true});window.addEventListener('resize',cQueue);cx.addEventListener('scroll',cQueue,{passive:true});
    cx.addEventListener('scroll',cUpdate,{passive:true});
    ['pointerdown','keydown','touchstart'].forEach(function(t){cx.addEventListener(t,userTook,{passive:true})});
    cx.addEventListener('wheel',function(e){if(Math.abs(e.deltaX)>Math.abs(e.deltaY))userTook()},{passive:true});
    cx.addEventListener('mouseenter',stopAuto);cx.addEventListener('mouseleave',function(){if(secVisible)startAuto()});cx.addEventListener('focusin',stopAuto);
    $('#h-pause').addEventListener('click',function(){if(touched){touched=false;startAuto()}else userTook();cUpdate()});
    $('#h-prev').addEventListener('click',function(){userTook();cGo(-1)});
    $('#h-next').addEventListener('click',function(){userTook();cGo(1)});
    cx.addEventListener('keydown',function(e){if(e.key==='ArrowRight'){e.preventDefault();cGo(1)}else if(e.key==='ArrowLeft'){e.preventDefault();cGo(-1)}});
    /* arrastar com o mouse no desktop */
    (function(){var down=false,sx=0,sl=0;
      cx.addEventListener('dragstart',function(e){e.preventDefault()});
      cx.addEventListener('pointerdown',function(e){if(e.pointerType!=='mouse'||e.button!==0)return;down=true;cx.__dragged=false;sx=e.clientX;sl=cx.scrollLeft;cx.classList.add('dragging');try{cx.setPointerCapture(e.pointerId)}catch(x){}});
      function end(){if(!down)return;down=false;cx.classList.remove('dragging');var s=step();if(s)cx.scrollTo({left:Math.round(cx.scrollLeft/s)*s,behavior:'smooth'});setTimeout(function(){cx.__dragged=false},50)}
      cx.addEventListener('pointermove',function(e){if(!down)return;if(!e.buttons){end();return}var dx=e.clientX-sx;if(Math.abs(dx)>5)cx.__dragged=true;cx.scrollLeft=sl-dx});
      ['pointerup','pointercancel','lostpointercapture'].forEach(function(t){cx.addEventListener(t,end)});
    })();
    cUpdate();checkCondos();
  }

  /* ---------- formulários: proposta e agenda ---------- */
  var leadDiag=0;
  function setTab(t){
    var p=t==='prop';$('#t-prop').setAttribute('aria-selected',String(p));$('#t-agen').setAttribute('aria-selected',String(!p));
    $('#lead-form').hidden=!p;$('#sched-form').hidden=p;
    if(!p)loadAgenda();
  }
  if($('#lead-form')){
    $('#t-prop').addEventListener('click',function(){setTab('prop')});
    $('#t-agen').addEventListener('click',function(){setTab('agen')});
    $$('[data-open-tab]').forEach(function(a){a.addEventListener('click',function(){setTab('agen')})});
    $$('.ftabs [role=tab]').forEach(function(tb){tb.addEventListener('keydown',function(e){if(e.key==='ArrowRight'||e.key==='ArrowLeft'){e.preventDefault();var o=tb.id==='t-prop'?$('#t-agen'):$('#t-prop');o.click();o.focus()}})});

    var showErr=function(box,msg,field){box.textContent=msg;box.hidden=false;if(field){field.setAttribute('aria-invalid','true');field.focus()}};
    var busy=function(form,on){var b=form.querySelector('[type=submit]');b.disabled=on;b.classList.toggle('loading',on)};

    $('#lead-form').addEventListener('submit',function(e){
      e.preventDefault();var form=e.target,er=$('#lead-err');
      var nome=$('#f-nome').value.trim(),wa=$('#f-wa').value.replace(/\D/g,''),okc=$('#f-ok').checked;
      $$('[aria-invalid]',form).forEach(function(x){x.removeAttribute('aria-invalid')});er.hidden=true;
      if(nome.length<2)return showErr(er,'Informe seu nome.',$('#f-nome'));
      if(wa.length<10)return showErr(er,'Informe o WhatsApp com DDD.',$('#f-wa'));
      if(!okc)return showErr(er,'Para enviar, marque a concordância com o uso dos dados.',$('#f-ok'));
      var papel=form.querySelector('[name=papel]:checked');
      busy(form,true);
      api('lead.php',{papel:papel?papel.value:'',nome:nome,whatsapp:$('#f-wa').value,condominio:$('#f-cond').value,unidades:$('#f-un').value,
        mensagem:$('#f-msg').value,consentimento:okc?1:0,diagnostico_id:leadDiag||0,site_url:$('#f-site').value}).then(function(r){
        busy(form,false);
        if(!r.ok){var map={nome:'#f-nome',whatsapp:'#f-wa',consentimento:'#f-ok'};return showErr(er,r.erro||'Não foi possível enviar.',r.campo&&map[r.campo]?$(map[r.campo]):null)}
        form.reset();leadDiag=0;var ok=$('#lead-ok');ok.hidden=false;ok.focus();
      });
    });

    /* agenda: dias e horários vêm do servidor */
    var DAYS=[],selDay=0,selTime=null,agendaLoaded=false;
    var renderDays=function(){
      var d=$('#days'),s=$('#slots');d.textContent='';s.textContent='';
      if(!DAYS.length){d.appendChild(el('span','slots-msg','Sem horários livres nos próximos dias. Chame no WhatsApp que eu encaixo você.'));return}
      DAYS.forEach(function(x,i){var b=el('button');b.type='button';b.appendChild(el('small',null,x.dia));b.appendChild(document.createTextNode(x.rotulo));b.setAttribute('aria-pressed',String(i===selDay));b.addEventListener('click',function(){selDay=i;selTime=null;renderDays()});d.appendChild(b)});
      var day=DAYS[selDay];
      day.slots.forEach(function(t){var b=el('button',null,t.h);b.type='button';b.disabled=!t.livre;b.setAttribute('aria-pressed',String(t.h===selTime));if(!t.livre)b.setAttribute('aria-label',t.h+' ocupado');b.addEventListener('click',function(){selTime=t.h;renderDays()});s.appendChild(b)});
    };
    var setDays=function(d){DAYS=d||[];if(selDay>=DAYS.length)selDay=0;renderDays()};
    var loadAgenda=function(force){
      if(agendaLoaded&&!force)return;agendaLoaded=true;
      api('agenda.php').then(function(r){if(r.ok)setDays(r.dias);else{agendaLoaded=false;$('#days').textContent=r.erro||'Não foi possível carregar os horários.'}});
    };
    $('#sched-form').addEventListener('submit',function(e){
      e.preventDefault();var form=e.target,er=$('#sched-err'),ok=$('#sched-ok');
      $$('[aria-invalid]',form).forEach(function(x){x.removeAttribute('aria-invalid')});er.hidden=true;ok.hidden=true;
      var nome=$('#s-nome').value.trim(),wa=$('#s-wa').value.replace(/\D/g,'');
      if(!selTime||!DAYS[selDay])return showErr(er,'Escolha um dia e um horário.',null);
      if(nome.length<2)return showErr(er,'Informe seu nome.',$('#s-nome'));
      if(wa.length<10)return showErr(er,'Informe o WhatsApp com DDD.',$('#s-wa'));
      if(!$('#s-ok').checked)return showErr(er,'Para enviar, marque a concordância com o uso dos dados.',$('#s-ok'));
      var modo=form.querySelector('[name=modo]:checked');
      busy(form,true);
      api('agenda.php',{nome:nome,whatsapp:$('#s-wa').value,modo:modo?modo.value:'video',data:DAYS[selDay].data,hora:selTime,consentimento:1,site_url:$('#s-site').value}).then(function(r){
        busy(form,false);
        if(r.dias)setDays(r.dias);
        if(!r.ok){selTime=null;renderDays();var map={nome:'#s-nome',whatsapp:'#s-wa',consentimento:'#s-ok'};return showErr(er,r.erro||'Não foi possível marcar.',r.campo&&map[r.campo]?$(map[r.campo]):null)}
        selTime=null;renderDays();$('#s-nome').value='';$('#s-wa').value='';$('#s-ok').checked=false;
        ok.textContent='Pedido de horário enviado: '+r.quando+'. Confirmo pelo WhatsApp.';ok.hidden=false;ok.focus();
      });
    });
    /* se a pessoa chegar com #contato e já for para a agenda, os horários carregam quando a aba abre */
  }

  /* máscara simples de telefone */
  $$('input[type=tel]').forEach(function(i){i.addEventListener('input',function(){
    var d=i.value.replace(/\D/g,'').slice(0,11),o=d;
    if(d.length>2)o='('+d.slice(0,2)+') '+d.slice(2);
    if(d.length>7)o='('+d.slice(0,2)+') '+d.slice(2,d.length===11?7:6)+'-'+d.slice(d.length===11?7:6);
    i.value=o;
  })});

  $$('[data-copy]').forEach(function(b){b.addEventListener('click',function(){
    function done(){b.textContent='copiado';setTimeout(function(){b.textContent='copiar'},1600)}
    function sel(){var r=document.createRange();r.selectNodeContents($('#wa-num'));var s=getSelection();s.removeAllRanges();s.addRange(r)}
    try{navigator.clipboard.writeText(b.dataset.copy).then(done,sel)}catch(err){sel()}
  })});

  /* ---------- títulos: palavras que sobem ao entrar na tela ---------- */
  function splitWords(elm){var i=0;(function walk(n){
    Array.prototype.slice.call(n.childNodes).forEach(function(c){
      if(c.nodeType===3){var frag=document.createDocumentFragment();
        c.nodeValue.split(/(\s+)/).forEach(function(p){if(!p)return;
          if(/^\s+$/.test(p)){frag.appendChild(document.createTextNode(p));return}
          var w=el('span','w'),s=el('span',null,p);s.style.setProperty('--i',i++);w.appendChild(s);frag.appendChild(w)});
        n.replaceChild(frag,c)}
      else if(c.nodeType===1)walk(c)})})(elm)}
  function setupReveal(){
    if(!('IntersectionObserver' in window))return;
    var groups=['main .block .head','.steps li','.quote','.portrait','.about-text','.timeline li','.compare tr','.post','.faq details','.formcard','.cinfo'];
    groups.forEach(function(sel){$$(sel).forEach(function(e,i){e.classList.add('reveal');e.style.setProperty('--d',Math.min(i,6))})});
    var io=new IntersectionObserver(function(es){es.forEach(function(en){
      if(!en.isIntersecting)return;var t=en.target;io.unobserve(t);t.classList.add('in');
    })},{threshold:.15,rootMargin:'0px 0px -6% 0px'});
    $$('.reveal').forEach(function(e){io.observe(e)});
  }

  /* ---------- GSAP: Junior em cena, cartões empilhados ---------- */
  var NS='http://www.w3.org/2000/svg',tk=$('#ticks');
  if(tk)for(var a=0;a<72;a++){var ang=a*5*Math.PI/180,lng=a%6===0,r1=lng?88:92,ln=document.createElementNS(NS,'line');
    ln.setAttribute('x1',(Math.cos(ang)*r1).toFixed(2));ln.setAttribute('y1',(Math.sin(ang)*r1).toFixed(2));ln.setAttribute('x2',(Math.cos(ang)*97).toFixed(2));ln.setAttribute('y2',(Math.sin(ang)*97).toFixed(2));
    ln.setAttribute('stroke',lng?'rgba(255,194,26,.6)':'rgba(156,201,255,.28)');ln.setAttribute('stroke-width',lng?'.7':'.35');tk.appendChild(ln)}
  var gsapReady=!!(window.gsap&&window.ScrollTrigger);
  function initMotion(){
    if(!gsapReady)return;
    gsap.registerPlugin(ScrollTrigger);ScrollTrigger.config({ignoreMobileResize:true});
    /* display do andar acompanha a rolagem (sem portas) */
    floors.forEach(function(s){ScrollTrigger.create({trigger:s,refreshPriority:-1,start:'top 55%',end:'bottom 55%',
      onToggle:function(st){if(st.isActive&&!jumping&&s.id!==current){var up=order.indexOf(s.id)>=order.indexOf(current);setFloor(s.id);
        gsap.fromTo(fNum,{yPercent:up?60:-60,opacity:0},{yPercent:0,opacity:1,duration:.35,ease:'power2.out'})}}})});
    var mm=gsap.matchMedia();
    mm.add({mobile:'(max-width: 899px)',desktop:'(min-width: 900px)'},function(ctx){
      var mobile=ctx.conditions.mobile;
      gsap.set('#dial',{xPercent:-50,yPercent:-50,x:0,y:0});
      gsap.set('.sh-copy',{yPercent:mobile?0:-30,y:0});
      gsap.timeline({defaults:{ease:'expo.out'}})
        .from('.sh-bg',{opacity:0,duration:.9,ease:'power2.out'})
        .from('.sh-dial',{scale:.9,opacity:0,duration:1,transformOrigin:'50% 50%'},.1)
        .from('.sh-back',{yPercent:20,opacity:0,duration:.9},.1)
        .from('.sh-photo2',{y:80,opacity:0,duration:1},.15)
        .from('.sh-photo',{y:70,duration:.9},.05)
        .from('.sh-front',{yPercent:50,opacity:0,duration:.8},.35)
        .from('.sh-chips li',{y:16,opacity:0,stagger:.08,duration:.6},.5)
        .from('.sh-copy > *',{y:16,stagger:.05,duration:.6},.1);
      /* desktop: abertura presa enquanto a cena anima; celular: rola normalmente, só com movimento leve */
      var heroTl=gsap.timeline({scrollTrigger:mobile?{trigger:'.sh',start:'top top',end:'bottom top',scrub:.5}:{trigger:'.sh',start:'top top',end:'+=60%',scrub:.7,pin:true}})
        .to('.sh-back',{xPercent:-38,ease:'none'},0)
        .to('.sh-front',{xPercent:mobile?-95:-60,ease:'none'},0)
        .to('#dial',{rotate:120,ease:'none'},0)
        .to('.sh-photo',{scale:1.05,ease:'none',transformOrigin:'50% 100%'},0)
        .to('.sh-photo2',{xPercent:-30,scale:.97,ease:'none',transformOrigin:'50% 100%'},0);
      if(!mobile)heroTl
        .to('.sh-chips li:nth-child(1)',{y:-110,ease:'none'},0)
        .to('.sh-chips li:nth-child(2)',{y:-170,ease:'none'},0)
        .to('.sh-chips li:nth-child(3)',{y:-60,ease:'none'},0)
        .to('.sh-copy',{opacity:0,y:-30,ease:'none'},.5)
        .to('.sh-chips',{opacity:0,ease:'none'},.7);
      gsap.fromTo('.svc-marquee span',{xPercent:0},{xPercent:-50,ease:'none',scrollTrigger:{trigger:'.svc-sec',start:'top bottom',end:'bottom top',scrub:.6}});
      var cards=gsap.utils.toArray('.scard');
      cards.forEach(function(c,i){
        var tl=gsap.timeline({scrollTrigger:{trigger:c,start:'top 80%'}});
        tl.fromTo(c.querySelectorAll('.ico [pathLength]'),{strokeDasharray:1,strokeDashoffset:1},{strokeDashoffset:0,duration:.8,stagger:.12,ease:'power2.inOut'},0)
          .from(c.querySelector('.led'),{opacity:0,duration:.4},.1)
          .from(c.querySelectorAll('h3,.k,:scope > p,li'),{y:18,opacity:0,stagger:.04,duration:.5,ease:'power2.out'},.1);
        if(i===cards.length-1)return;
        var st={trigger:cards[i+1],start:'top bottom',end:'top '+(84+(i+1)*16)+'px',scrub:true};
        gsap.to(c,{scale:mobile?.9:.93,ease:'none',scrollTrigger:st});
        gsap.to(c.querySelector('.dim'),{opacity:.5,ease:'none',scrollTrigger:{trigger:st.trigger,start:st.start,end:st.end,scrub:true}});
      });
      return function(){};
    });
  }

  /* ---------- vídeos: prévia muda ao aparecer; vídeo completo com som só quando a pessoa pede ---------- */
  var reels=$$('.reel');
  var con=navigator.connection||{},lowData=!!(con.saveData||/(^|-)(2g|3g)$/.test(con.effectiveType||''));
  function loadReel(r,full){var v=r.querySelector('video'),want=full?r.dataset.src:r.dataset.preview;if(v.getAttribute('src')!==want){v.src=want;r.__full=!!full}return v}
  function ppUI(r){var v=r.querySelector('video'),b=r.querySelector('.pp'),playing=!v.paused;b.textContent=playing?'❚❚':'▶';b.setAttribute('aria-label',playing?'Pausar vídeo':'Reproduzir vídeo')}
  function playV(v){var p=v.play();if(p&&p.catch)p.catch(function(){})}
  reels.forEach(function(r){
    var v=r.querySelector('video'),b=r.querySelector('.snd'),pp=r.querySelector('.pp');b.setAttribute('aria-pressed','false');
    v.addEventListener('play',function(){ppUI(r)});v.addEventListener('pause',function(){ppUI(r)});ppUI(r);
    b.addEventListener('click',function(){
      var on=b.getAttribute('aria-pressed')!=='true';
      reels.forEach(function(o){if(o!==r){o.querySelector('video').muted=true;o.querySelector('.snd').setAttribute('aria-pressed','false');o.querySelector('.snd').textContent='Som'}});
      if(on&&!r.__full){loadReel(r,true)}
      v.muted=!on;b.setAttribute('aria-pressed',String(on));b.textContent=on?'Som ligado':'Som';
      if(on){r.__userPaused=false;playV(v)}
    });
    pp.addEventListener('click',function(){if(v.paused){r.__userPaused=false;if(!v.getAttribute('src'))loadReel(r,lowData);playV(v)}else{r.__userPaused=true;v.pause()}});
  });
  if('IntersectionObserver' in window){
    var pio=new IntersectionObserver(function(es){es.forEach(function(en){if(en.isIntersecting){var v=en.target.querySelector('video');if(!v.poster)v.poster=en.target.dataset.poster;pio.unobserve(en.target)}})},{rootMargin:'600px 0px'});
    var rio=new IntersectionObserver(function(es){es.forEach(function(en){
      var r=en.target,v=r.querySelector('video');
      if(en.isIntersecting&&en.intersectionRatio>=.6&&!reduced()&&!lowData&&!r.__userPaused){loadReel(r,r.__full);playV(v)}
      else if(!en.isIntersecting||en.intersectionRatio<.6){if(!v.paused)v.pause()}
    })},{threshold:[0,.6,1]});
    reels.forEach(function(r){pio.observe(r);rio.observe(r)});
  }else reels.forEach(function(r){r.querySelector('video').poster=r.dataset.poster});

  /* ---------- início ---------- */
  if(!reduced()){initMotion();setupReveal()}
  else if(gsapReady){gsap.registerPlugin(ScrollTrigger)}
  /* chegou com #secao no endereço: posiciona depois que a abertura fixa foi montada */
  var h=(location.hash||'').slice(1);
  if(h&&document.getElementById(h)){
    window.addEventListener('load',function(){if(window.ScrollTrigger)ScrollTrigger.refresh();jumpTo(h);setFloor(h)});
  }
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
})();
