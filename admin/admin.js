/* JC Síndico — painel administrativo */
(function(){
'use strict';
var API=document.body.dataset.api,SITE=document.body.dataset.site||'/';
var CSRF=(document.querySelector('meta[name=csrf]')||{}).content||'';
var pane=document.getElementById('pane');
if(!pane)return;

/* ---------- utilidades ---------- */
function $(s,c){return (c||document).querySelector(s)}
function $$(s,c){return Array.prototype.slice.call((c||document).querySelectorAll(s))}
/* h('tag.classe', {atributos}, filhos...) — sempre textContent, nunca HTML vindo do banco */
function h(sel,attrs){
  var idm=sel.split('#'),p=idm[0].split('.'),e=document.createElement(p[0]||'div');if(p.length>1)e.className=p.slice(1).join(' ');if(idm[1])e.id=idm[1];
  var kids=Array.prototype.slice.call(arguments,2);
  if(attrs&&(typeof attrs!=='object'||attrs.nodeType||Array.isArray(attrs))){kids.unshift(attrs);attrs=null}
  if(attrs)Object.keys(attrs).forEach(function(k){var v=attrs[k];if(v==null||v===false)return;
    if(k.slice(0,2)==='on')e.addEventListener(k.slice(2),v);else if(k==='text')e.textContent=v;else if(k in e&&k!=='list'&&k!=='form')e[k]=v;else e.setAttribute(k,v===true?'':v)});
  (function add(list){list.forEach(function(c){if(c==null||c===false)return;if(Array.isArray(c))add(c);else e.appendChild(c.nodeType?c:document.createTextNode(String(c)))})})(kids);
  return e;
}
var toastT;function toast(m,bad){var t=$('#toast');t.textContent=m;t.classList.toggle('bad',!!bad);t.hidden=false;clearTimeout(toastT);toastT=setTimeout(function(){t.hidden=true},2800)}
function api(acao,data,isForm){
  var url=API+'?acao='+encodeURIComponent(acao),opt={credentials:'same-origin',headers:{'Accept':'application/json'}};
  if(data!==undefined){opt.method='POST';opt.headers['X-CSRF']=CSRF;
    if(isForm)opt.body=data;else{opt.headers['Content-Type']='application/json';opt.body=JSON.stringify(data)}}
  return fetch(url,opt).then(function(r){
    if(r.status===401){location.reload();return {ok:false}}
    return r.json().catch(function(){return {ok:false,erro:'Resposta inválida do servidor.'}})
  },function(){return {ok:false,erro:'Sem conexão com o servidor.'}}).then(function(r){if(!r.ok&&r.erro)toast(r.erro,true);return r});
}
function get(acao,params){var q='';if(params)Object.keys(params).forEach(function(k){if(params[k]!=null&&params[k]!=='')q+='&'+k+'='+encodeURIComponent(params[k])});
  return fetch(API+'?acao='+acao+q,{credentials:'same-origin',headers:{'Accept':'application/json'}}).then(function(r){if(r.status===401){location.reload();return {ok:false}}return r.json()}).catch(function(){toast('Sem conexão com o servidor.',true);return {ok:false}})}
function title(t,sub,right){return h('div.ptitle',h('div',h('h2',t),sub?h('p.sub',sub):null),right||null)}
function field(label,input,full,hint){var id=input.id||('f'+Math.random().toString(36).slice(2,8));input.id=id;return h('div.field'+(full?'.full':''),h('label',{htmlFor:id},label,hint?h('span',' '+hint):null),input)}
function toggle(checked,label,on){var i=h('input',{type:'checkbox',checked:!!checked,'aria-label':label});i.addEventListener('change',function(){on(i.checked,i)});return h('label.toggle',i,h('span'))}
function pill(txt,cls){return h('span.pill.'+cls,txt)}
function confirmar(msg){return window.confirm(msg)}
var STATUS={novo:['Novo','p-info'],conversa:['Em conversa','p-warn'],proposta:['Proposta enviada','p-warn'],fechado:['Fechado','p-ok'],perdido:['Perdido','p-mute']};
var ORIGEM={proposta:'Formulário',agenda:'Agenda',diagnostico:'Diagnóstico'};
var MODO={video:'Videochamada',presencial:'No condomínio',escritorio:'No escritório'};
function waUrl(num,txt){return 'https://wa.me/55'+String(num).replace(/\D/g,'')+(txt?'?text='+encodeURIComponent(txt):'')}
function badge(id,n){var b=$('#'+id);if(!b)return;b.textContent=n;b.hidden=!n}

/* ---------- navegação ---------- */
var tabs=$$('.side [data-tab]'),current='';
function show(t,arg){
  current=t;tabs.forEach(function(b){if(b.dataset.tab===t)b.setAttribute('aria-current','page');else b.removeAttribute('aria-current')});
  pane.textContent='';pane.appendChild(h('p.loading-pane','Carregando…'));
  (VIEWS[t]||VIEWS.geral)(arg);
  if(history.replaceState)history.replaceState(null,'','#'+t);
  window.scrollTo(0,0);
}
tabs.forEach(function(b){b.addEventListener('click',function(){show(b.dataset.tab)})});
function render(){pane.textContent='';Array.prototype.forEach.call(arguments,function(n){if(n)pane.appendChild(n)})}

var VIEWS={};

/* ================= visão geral ================= */
VIEWS.geral=function(){
  get('resumo').then(function(r){if(!r.ok)return;
    var hr=new Date().getHours(),saud=hr<12?'Bom dia':hr<18?'Boa tarde':'Boa noite';
    var delta=r.propostas-r.propostas_antes;
    var mx=Math.max.apply(null,r.semanas.concat([1])),pts=r.semanas.map(function(v,i){return (i*120/7).toFixed(1)+' '+(30-v/mx*26).toFixed(1)}).join(' L');
    var spark=h('div');spark.innerHTML='<svg class="spark" viewBox="0 0 120 34" preserveAspectRatio="none" aria-hidden="true"><path d="M'+pts+'" fill="none" stroke="var(--petrol-ink)" stroke-width="2"/></svg>';
    var p=r.pend,pend=[];
    if(p.novos)pend.push([p.novos+(p.novos>1?' contatos novos':' contato novo'),'Ainda sem resposta','contatos','Responder']);
    if(p.agenda)pend.push([p.agenda+(p.agenda>1?' conversas para confirmar':' conversa para confirmar'),'Pedidos de horário pelo site','agenda','Confirmar']);
    if(p.depo)pend.push([p.depo+(p.depo>1?' depoimentos aguardando':' depoimento aguardando')+' aprovação','Nada vai ao site sem sua aprovação','depo','Revisar']);
    if(p.rascunhos)pend.push([p.rascunhos+(p.rascunhos>1?' artigos em rascunho':' artigo em rascunho'),'Guia do condomínio','artigos','Abrir']);
    p.sem_foto.forEach(function(c){pend.push([c.nome+' sem foto','Quadro de condomínios','condos','Enviar'])});
    if(r.alertas24)pend.unshift([r.alertas24+(r.alertas24>1?' alertas de segurança':' alerta de segurança')+' nas últimas 24 horas','Tentativas de acesso e ações suspeitas','logs','Ver registros']);
    render(
      title(saud+', '+String(r.nome).split(' ')[0],'Resumo dos últimos 30 dias no site.'),
      h('div.grid4',
        h('div.tile',h('small','Pedidos de proposta'),h('strong',r.propostas),h('span',delta>0?h('span.up','+'+delta):String(delta),' vs. 30 dias anteriores'),spark.firstChild),
        h('div.tile',h('small','Diagnósticos feitos'),h('strong',r.diagnosticos),h('span',r.diag_enviados+' enviaram o resultado')),
        h('div.tile',h('small','Conversas agendadas'),h('strong',r.agendados),h('span',r.proximo?'próxima: '+r.proximo:'nenhuma marcada')),
        h('div.tile',h('small','Contatos respondidos'),h('strong',r.taxa_resposta==null?'–':r.taxa_resposta+'%'),h('span','status diferente de “novo”'))
      ),
      h('div.cols',
        h('div.panel',h('h3','Últimos contatos',h('button',{type:'button',onclick:function(){show('contatos')}},'Ver todos')),
          r.recentes.length?h('ul.list',r.recentes.map(function(l){return h('li',h('div.t',h('b',l.nome+(l.condominio?' · '+l.condominio:'')),h('small',ORIGEM[l.origem]+' · '+l.criado_fmt)),pill(STATUS[l.status][0],STATUS[l.status][1]))}))
          :h('p.empty','Nenhum contato ainda. Quando alguém pedir proposta, agendar ou enviar o diagnóstico, aparece aqui.')),
        h('div.panel',h('h3','Pendências do site'),
          pend.length?h('ul.list',pend.map(function(x){return h('li',h('div.t',h('b',x[0]),h('small',x[1])),h('button.btn.btn-line.btn-sm',{type:'button',onclick:function(){show(x[2])}},x[3]))}))
          :h('p.empty','Tudo em dia.'))
      )
    );
    badge('b-novos',p.novos);badge('b-agenda',p.agenda);badge('b-depo',p.depo);badge('b-logs',r.alertas24);
  });
};

/* ================= contatos ================= */
var leadFiltro='',leadSel=null;
VIEWS.contatos=function(){
  get('leads',{status:leadFiltro}).then(function(r){if(!r.ok)return;
    var leads=r.leads;
    if(leadSel&&!leads.some(function(l){return l.id===leadSel}))leadSel=null;
    if(!leadSel&&leads[0])leadSel=leads[0].id;
    var chips=h('div.chips',{role:'group','aria-label':'Filtrar por status'},
      [['','Todos']].concat(Object.keys(STATUS).map(function(k){return [k,STATUS[k][0]]})).map(function(c){
        return h('button',{type:'button','aria-pressed':String(leadFiltro===c[0]),onclick:function(){leadFiltro=c[0];leadSel=null;VIEWS.contatos()}},c[1])}));
    var tb=h('tbody');
    leads.forEach(function(l){
      var tr=h('tr',{tabIndex:0,className:l.id===leadSel?'sel':'',onclick:function(){leadSel=l.id;VIEWS.contatos()},onkeydown:function(e){if(e.key==='Enter'){leadSel=l.id;VIEWS.contatos()}}},
        h('td',h('b',l.nome),h('small',l.condominio||'–')),h('td',ORIGEM[l.origem]),h('td',l.unidades||'–'),h('td.num',l.criado_fmt),h('td',pill(STATUS[l.status][0],STATUS[l.status][1])));
      tb.appendChild(tr);
    });
    var det=h('div.panel#lead-detail'),l=leads.filter(function(x){return x.id===leadSel})[0];
    if(l){
      var sel=h('select.st',{'aria-label':'Status do contato'},Object.keys(STATUS).map(function(k){return h('option',{value:k,selected:k===l.status},STATUS[k][0])}));
      sel.addEventListener('change',function(){api('lead_salvar',{id:l.id,status:sel.value}).then(function(x){if(x.ok){toast('Status atualizado');VIEWS.contatos()}})});
      var notas=h('textarea.notes',{rows:3,placeholder:'Anotações internas (só você vê)'});notas.value=l.notas||'';
      var papel={sindico:'Síndico atual',conselho:'Conselho',morador:'Morador',administradora:'Administradora'}[l.papel]||'–';
      det.appendChild(h('h3',l.nome));
      det.appendChild(h('div.detail',
        h('dl',[['Condomínio',l.condominio||'–'],['Unidades',l.unidades||'–'],['Você é',papel],['Origem',ORIGEM[l.origem]],['WhatsApp',l.whatsapp_fmt],['Recebido',l.criado_fmt]].map(function(x){return [h('dt',x[0]),h('dd',x[1])]})),
        l.mensagem?h('div.msg',l.mensagem):null,
        field('Notas',notas,true),
        h('div.acts',sel,
          h('a.btn.btn-ink.btn-sm',{href:waUrl(l.whatsapp,'Olá '+l.nome.split(' ')[0]+', aqui é o Junior, da JC Síndico. Recebi seu contato pelo site.'),target:'_blank',rel:'noopener'},'Responder no WhatsApp'),
          h('button.btn.btn-line.btn-sm',{type:'button',onclick:function(){api('lead_salvar',{id:l.id,notas:notas.value}).then(function(x){if(x.ok)toast('Notas salvas')})}},'Salvar notas'),
          h('button.btn.btn-line.btn-sm.danger.so-master',{type:'button',onclick:function(){if(confirmar('Excluir este contato definitivamente? (use quando a pessoa pedir a exclusão dos dados)'))api('lead_excluir',{id:l.id}).then(function(x){if(x.ok){toast('Contato excluído');leadSel=null;VIEWS.contatos()}})}},'Excluir')
        )));
    }else det.appendChild(h('p.empty','Nenhum contato'+(leadFiltro?' com este status.':' ainda.')));
    render(
      title('Contatos','Tudo o que chega pelo formulário, pelo diagnóstico e pela agenda.',h('a.btn.btn-line.btn-sm',{href:API+'?acao=leads_csv'},'Exportar planilha (CSV)')),
      chips,
      h('div.cols',h('div.panel.tbl',h('table',h('thead',h('tr',h('th','Contato'),h('th','Origem'),h('th','Unid.'),h('th','Data'),h('th','Status'))),tb)),det)
    );
  });
};

/* ================= agenda ================= */
/* ================= agenda ================= */
var DIAS_NOME=['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'],DIAS_ORDEM=[1,2,3,4,5,6,0];
function hmMin(s){return parseInt(s.slice(0,2),10)*60+parseInt(s.slice(3,5),10)}
function nSlots(j,dur){return j.ativo&&j.de&&j.ate&&hmMin(j.ate)-hmMin(j.de)>=dur?Math.floor((hmMin(j.ate)-hmMin(j.de))/dur):0}
VIEWS.agenda=function(){
  get('agenda').then(function(r){if(!r.ok)return;
    var porDia={};r.agendamentos.forEach(function(a){(porDia[a.dia]=porDia[a.dia]||[]).push(a)});
    var lista=Object.keys(porDia).length?h('div.ag-days',Object.keys(porDia).map(function(d){
      return h('div.ag-day',h('b',d),porDia[d].map(function(a){
        return h('div.ag-item'+(a.passado?'.past':''),
          h('span.ag-h',a.hora),
          h('div.t',h('b',a.nome||'(contato excluído)'),h('small',MODO[a.modo]+(a.whatsapp_fmt?' · '+a.whatsapp_fmt:''))),
          a.status==='confirmado'?pill('Confirmado','p-ok'):pill('A confirmar','p-warn'),
          h('span.ag-acts',
            a.whatsapp?h('a.btn.btn-line.btn-sm',{href:waUrl(a.whatsapp,'Olá '+(a.nome||'').split(' ')[0]+', aqui é o Junior. Confirmando nossa conversa '+a.dia+' às '+a.hora+'.'),target:'_blank',rel:'noopener'},'WhatsApp'):null,
            a.status!=='confirmado'&&!a.passado?h('button.btn.btn-ink.btn-sm',{type:'button',onclick:function(){api('ag_status',{id:a.id,status:'confirmado'}).then(function(x){if(x.ok){toast('Conversa confirmada');VIEWS.agenda()}})}},'Confirmar'):null,
            !a.passado?h('button.btn.btn-line.btn-sm',{type:'button',onclick:function(){if(confirmar('Cancelar esta conversa? O horário volta a ficar livre no site.'))api('ag_status',{id:a.id,status:'cancelado'}).then(function(x){if(x.ok){toast('Cancelada; horário liberado');VIEWS.agenda()}})}},'Cancelar'):null));
      }));
    })):h('p.empty','Nenhuma conversa marcada.');

    /* ---- editor dos horários de atendimento ---- */
    var S=JSON.parse(JSON.stringify(r.config)),op=r.opcoes,counts={},total=h('strong.sch-total');
    function recount(){var t=0;DIAS_ORDEM.forEach(function(d){var n=nSlots(S.dias[d].manha,S.duracao)+nSlots(S.dias[d].tarde,S.duracao);t+=n;
      if(counts[d]){counts[d].textContent=n?n+(n>1?' horários':' horário'):'fechado';counts[d].classList.toggle('off',!n)}});
      total.textContent=t?t+' horários por semana':'Nenhum horário aberto'}
    function combo(vals,cur,fmt,onch){var s=h('select',vals.map(function(v){return h('option',{value:v},fmt(v))}));s.value=String(cur);s.addEventListener('change',function(){onch(parseInt(s.value,10));recount()});return s}
    var durS=combo(op.duracoes,S.duracao,function(v){return v+' minutos'},function(v){S.duracao=v}),
        antS=combo(op.antecedencias,S.antecedencia,function(v){return v===1?'a partir de amanhã':'daqui a '+v+' dias'},function(v){S.antecedencia=v}),
        janS=combo(op.janelas,S.janela,function(v){return v+' dias à frente'},function(v){S.janela=v});
    function periodo(d,per,rot){
      var j=S.dias[d][per],box,
          de=h('input',{type:'time',step:300,value:j.de,'aria-label':rot+', início, '+DIAS_NOME[d]}),
          ate=h('input',{type:'time',step:300,value:j.ate,'aria-label':rot+', fim, '+DIAS_NOME[d]});
      function sync(){de.disabled=ate.disabled=!j.ativo;box.classList.toggle('off',!j.ativo);recount()}
      de.addEventListener('input',function(){j.de=de.value;recount()});ate.addEventListener('input',function(){j.ate=ate.value;recount()});
      box=h('div.sch-per',toggle(j.ativo,rot+' de '+DIAS_NOME[d],function(v){j.ativo=v;sync()}),h('span.sch-rot',rot),de,h('span.sch-ate','às'),ate);
      sync();return box;
    }
    var grade=h('div.sch');
    function desenhar(){grade.textContent='';counts={};
      DIAS_ORDEM.forEach(function(d){counts[d]=h('small.sch-n');
        grade.appendChild(h('div.sch-row',h('div.sch-day',h('b',DIAS_NOME[d]),counts[d]),periodo(d,'manha','Manhã'),periodo(d,'tarde','Tarde')))});
      recount()}
    desenhar();
    var salvando=false;
    var editor=h('div.panel.mt',h('h3','Horários de atendimento'),
      h('div.pad',h('p.muted','Escolha em quais dias e períodos você atende. Cada período vira horários de início, de acordo com a duração da conversa (ex.: 9h às 11h30 com conversas de 30 min = 9h, 9h30, 10h, 10h30 e 11h). Conversas já marcadas continuam valendo se você mudar os horários.')),
      h('div.sch-opts',field('Duração de cada conversa',durS),field('Aviso mínimo',antS,false,'(para pedir um horário)'),field('Mostrar no site',janS)),
      grade,
      h('div.sch-foot',total,h('span.sch-acts',
        h('button.btn.btn-line.btn-sm',{type:'button',onclick:function(){var m=JSON.stringify(S.dias[1]);[2,3,4,5].forEach(function(d){S.dias[d]=JSON.parse(m)});desenhar();toast('Segunda copiada para terça a sexta. Falta salvar.')}},'Copiar segunda para terça a sexta'),
        h('button.btn.btn-ink.btn-sm',{type:'button',onclick:function(ev){if(salvando)return;salvando=true;ev.currentTarget.disabled=true;
          api('agenda_config_salvar',S).then(function(x){salvando=false;if(x.ok){toast('Horários salvos. Já valem no site.');VIEWS.agenda()}else{ev.target.disabled=false}})}},'Salvar horários'))));

    /* ---- bloqueios ---- */
    var bData=h('input',{type:'date',required:true,min:new Date().toISOString().slice(0,10)}),bHora=h('select',h('option',{value:''},'Dia inteiro')),bMot=h('input',{maxLength:120,placeholder:'Ex.: assembleia, viagem'});
    bData.addEventListener('change',function(){if(!bData.value)return;var dow=new Date(bData.value+'T12:00:00').getDay();bHora.textContent='';bHora.appendChild(h('option',{value:''},'Dia inteiro'));(r.slots_dow[dow]||[]).forEach(function(x){bHora.appendChild(h('option',x))})});
    var bForm=h('form.inline-form.bl-form',{onsubmit:function(e){e.preventDefault();api('bloqueio_add',{data:bData.value,hora:bHora.value,motivo:bMot.value}).then(function(x){if(x.ok){toast('Bloqueado');VIEWS.agenda()}})}},
      field('Bloquear data',bData),field('Horário',bHora),field('Motivo',bMot,false,'(opcional)'),h('button.btn.btn-ink.btn-sm',{type:'submit'},'Bloquear'));
    var prev=r.dias.length?h('div.ag-prev',r.dias.map(function(d){var livres=d.slots.filter(function(s){return s.livre}).length;return h('span',h('b',d.dia+' '+d.rotulo),livres+' livres')})):h('p.empty','Nenhum horário aparece no site agora. Ative algum período nos horários de atendimento.');
    render(
      title('Agenda','Conversas pedidas pelo site e os horários em que você atende.'),
      h('div.panel.mt',h('h3','Próximas conversas'),h('div.pad',lista)),
      editor,
      h('div.cols',
        h('div.panel',h('h3','Como está aparecendo no site'),h('div.pad',h('small.muted','Próximos dias oferecidos:'),prev)),
        h('div.panel',h('h3','Dias e horários bloqueados'),bForm,
          r.bloqueios.length?h('ul.list',r.bloqueios.map(function(b){return h('li',h('div.t',h('b',b.dia+(b.hora?' · '+b.hora:' · dia inteiro')),h('small',b.motivo||'')),
            h('button.btn.btn-line.btn-sm',{type:'button',onclick:function(){api('bloqueio_del',{id:b.id}).then(function(x){if(x.ok){toast('Desbloqueado');VIEWS.agenda()}})}},'Remover'))})):h('p.empty','Nenhum bloqueio.'))
      )
    );
  });
};

/* ================= condomínios ================= */
/* Recorta a foto no navegador (envio leve). */
function loadImage(file){return new Promise(function(ok,fail){var u=URL.createObjectURL(file),i=new Image();i.onload=function(){ok(i)};i.onerror=function(){URL.revokeObjectURL(u);fail()};i.src=u})}
function cover(img,w,hh){var c=document.createElement('canvas');c.width=w;c.height=hh;var x=c.getContext('2d'),r=w/hh,sw=img.naturalWidth,sh=img.naturalHeight,cw,ch;
  if(sw/sh>r){ch=sh;cw=sh*r}else{cw=sw;ch=sw/r}x.imageSmoothingQuality='high';x.drawImage(img,(sw-cw)/2,(sh-ch)/2,cw,ch,0,0,w,hh);return c}
function toBlob(c,type,q){return new Promise(function(ok){c.toBlob(ok,type,q)})}

VIEWS.condos=function(edit){
  get('condominios').then(function(r){if(!r.ok)return;
    var lista=r.condominios,tb=h('tbody');
    lista.forEach(function(c){
      tb.appendChild(h('tr',
        h('td',c.foto?h('img.thumb',{src:SITE+c.foto,alt:'',loading:'lazy'}):h('span.thumb-empty','sem foto')),
        h('td',h('b',c.nome),h('small',c.bairro||'bairro não informado')),
        h('td.num',c.unidades),h('td.num',c.desde.slice(5,7)+'/'+c.desde.slice(0,4)),
        h('td',toggle(c.exibir==1,'Exibir '+c.nome,function(v){api('condo_exibir',{id:c.id,exibir:v?1:0}).then(function(x){if(x.ok)toast(v?'Exibido no site':'Oculto do site')})})),
        h('td.acts-cell',h('button.btn.btn-line.btn-sm',{type:'button',onclick:function(){VIEWS.condos(c)}},'Editar'),
          h('button.btn.btn-line.btn-sm.danger.so-master',{type:'button',onclick:function(){if(confirmar('Remover '+c.nome+' do site?'))api('condo_excluir',{id:c.id}).then(function(x){if(x.ok){toast('Removido');VIEWS.condos()}})}},'Remover'))
      ));
    });
    var c=edit||{},nome=h('input',{required:true,maxLength:120,placeholder:'Ex.: Residencial Sol',value:c.nome||''}),bairro=h('input',{maxLength:80,placeholder:'Ex.: Aldeota',value:c.bairro||''}),
      un=h('input',{type:'number',min:1,max:5000,required:true,placeholder:'48',value:c.unidades||''}),desde=h('input',{type:'month',required:true,value:c.desde?c.desde.slice(0,7):new Date().toISOString().slice(0,7)}),
      foto=h('input',{type:'file',accept:'image/jpeg,image/png,image/webp'}),prev=h('div.cprev'),fBlob=null;
    if(c.foto)prev.appendChild(h('img',{src:SITE+c.foto,alt:'Foto atual'}));
    foto.addEventListener('change',function(){fBlob=null;prev.textContent='';var f=foto.files[0];if(!f)return;
      if(['image/jpeg','image/png','image/webp'].indexOf(f.type)<0){toast('Envie a foto em JPG, PNG ou WEBP',true);foto.value='';return}
      if(f.size>25*1024*1024){toast('Arquivo muito grande',true);foto.value='';return}
      prev.appendChild(h('p.muted','Preparando a foto…'));
      loadImage(f).then(function(img){
        if(img.naturalWidth<400||img.naturalHeight<360){toast('A foto é pequena demais (mínimo 400 px de largura).',true);foto.value='';prev.textContent='';return}
        var pc=cover(img,920,832);
        return toBlob(pc,'image/jpeg',.88).then(function(bl){fBlob=bl;prev.textContent='';
          var a=h('img',{alt:'Foto recortada'});a.src=URL.createObjectURL(fBlob);
          prev.appendChild(a);prev.appendChild(h('small.muted','Recorte que vai para o site.'))});
      }).catch(function(){toast('Não foi possível ler a imagem.',true)});
    });
    var form=h('form.panel.condo-form',{onsubmit:function(e){e.preventDefault();
        var fd=new FormData();if(c.id)fd.append('id',c.id);fd.append('nome',nome.value);fd.append('bairro',bairro.value);fd.append('unidades',un.value);fd.append('desde',desde.value);
        if(foto.files[0]&&!fBlob){toast('Aguarde a foto terminar de processar.',true);return}
        if(fBlob)fd.append('foto',fBlob,'foto.jpg');
        var btn=form.querySelector('[type=submit]');btn.disabled=true;
        api('condo_salvar',fd,true).then(function(x){btn.disabled=false;if(x.ok){toast(c.id?'Condomínio atualizado':'Adicionado. Já aparece no site.');VIEWS.condos()}})}},
      h('h3',c.id?'Editar '+c.nome:'Adicionar condomínio',c.id?h('button',{type:'button',onclick:function(){VIEWS.condos()}},'Cancelar edição'):null),
      h('div.fgrid2',field('Nome',nome),field('Bairro',bairro,false,'(opcional)'),field('Unidades',un),field('Na gestão desde',desde),
        field('Foto da fachada',foto,true,'(JPG, PNG ou WEBP · o recorte é automático)'),h('div.full',prev),
        h('div.full.right',h('button.btn.btn-ink',{type:'submit'},c.id?'Salvar alterações':'Adicionar ao site'))));
    render(
      title('Condomínios','Lista exibida no carrossel da página inicial. Alterações aparecem no site na hora.'),
      h('div.panel.tbl.mt',h('table',h('thead',h('tr',h('th','Foto'),h('th','Condomínio'),h('th','Unidades'),h('th','Desde'),h('th','No site'),h('th',''))),tb)),
      form
    );
    if(edit)form.scrollIntoView({behavior:'smooth',block:'start'});
  });
};

/* ================= depoimentos ================= */
VIEWS.depo=function(edit){
  get('depoimentos').then(function(r){if(!r.ok)return;
    var ST={pendente:['Aguardando','p-warn'],publicado:['No site','p-ok'],recusado:['Recusado','p-mute']};
    var set=function(d,st,msg){return function(){api('depo_status',{id:d.id,status:st}).then(function(x){if(x.ok){toast(msg);VIEWS.depo()}})}};
    var lista=r.depoimentos.length?h('ul.list',r.depoimentos.map(function(d){
      return h('li.depo-li',h('div.t',h('b','“'+d.texto+'”'),h('small',[d.nome,d.papel,d.condominio].filter(Boolean).join(' · ')+' · '+(d.origem==='site'?'enviado pelo site':'cadastrado no painel')+' em '+d.criado_fmt)),
        h('span.acts-cell',pill(ST[d.status][0],ST[d.status][1]),
          d.status!=='publicado'?h('button.btn.btn-ink.btn-sm',{type:'button',onclick:set(d,'publicado','Depoimento publicado')},'Publicar'):h('button.btn.btn-line.btn-sm',{type:'button',onclick:set(d,'recusado','Retirado do site')},'Tirar do site'),
          d.status==='pendente'?h('button.btn.btn-line.btn-sm',{type:'button',onclick:set(d,'recusado','Depoimento recusado')},'Recusar'):null,
          h('button.btn.btn-line.btn-sm',{type:'button',onclick:function(){VIEWS.depo(d)}},'Editar'),
          h('button.btn.btn-line.btn-sm.danger.so-master',{type:'button',onclick:function(){if(confirmar('Excluir este depoimento?'))api('depo_excluir',{id:d.id}).then(function(x){if(x.ok){toast('Excluído');VIEWS.depo()}})}},'Excluir')));
    })):h('p.empty','Nenhum depoimento ainda. Envie o link abaixo para clientes satisfeitos.');
    var d=edit||{},texto=h('textarea',{rows:3,maxLength:600,required:true}),nome=h('input',{maxLength:100,required:true,value:d.nome||''}),papel=h('input',{maxLength:80,placeholder:'Ex.: conselheiro fiscal',value:d.papel||''}),cond=h('input',{maxLength:120,value:d.condominio||''}),aut=h('input',{type:'checkbox',checked:!!d.id});
    texto.value=d.texto||'';
    var link=location.origin+SITE+'depoimento';
    var form=h('form.panel',{onsubmit:function(e){e.preventDefault();api('depo_salvar',{id:d.id||0,texto:texto.value,nome:nome.value,papel:papel.value,condominio:cond.value,autorizado:aut.checked?1:0}).then(function(x){if(x.ok){toast(d.id?'Depoimento atualizado':'Depoimento publicado');VIEWS.depo()}})}},
      h('h3',d.id?'Editar depoimento':'Cadastrar depoimento recebido',d.id?h('button',{type:'button',onclick:function(){VIEWS.depo()}},'Cancelar edição'):null),
      h('div.fgrid2',field('Texto',texto,true),field('Nome',nome),field('Papel',papel,false,'(opcional)'),field('Condomínio',cond,true,'(opcional)'),
        h('label.consent.full',aut,h('span','A pessoa autorizou a publicação com esse nome.')),
        h('div.full.right',h('button.btn.btn-ink',{type:'submit'},d.id?'Salvar':'Publicar no site'))));
    render(
      title('Depoimentos','Nada vai para o site sem a sua aprovação. A seção só aparece quando houver pelo menos um publicado.'),
      h('div.panel.mt',h('h3','Recebidos'),lista),
      h('div.panel.share',h('div.t',h('b','Link para pedir depoimentos'),h('small',link)),
        h('button.btn.btn-line.btn-sm',{type:'button',onclick:function(){try{navigator.clipboard.writeText(link).then(function(){toast('Link copiado')})}catch(x){toast(link)}}},'Copiar link'),
        h('a.btn.btn-ink.btn-sm',{href:'https://wa.me/?text='+encodeURIComponent('Olá! Você pode deixar um depoimento sobre a gestão do condomínio? Leva menos de um minuto: '+link),target:'_blank',rel:'noopener'},'Enviar por WhatsApp')),
      form
    );
    if(edit)form.scrollIntoView({behavior:'smooth',block:'start'});
  });
};

/* ================= artigos ================= */
VIEWS.artigos=function(){
  get('artigos').then(function(r){if(!r.ok)return;
    render(
      title('Guia (artigos)','Cada artigo ganha endereço próprio, como '+location.host+SITE+'guia/fundo-de-reserva.',h('button.btn.btn-ink.btn-sm',{type:'button',onclick:function(){editor({})}},'Novo artigo')),
      h('div.panel.mt',r.artigos.length?h('ul.list',r.artigos.map(function(a){
        return h('li',h('div.t',h('b',a.titulo),h('small',a.status==='publicado'?SITE+'guia/'+a.slug+' · '+a.leituras+' leituras':'Editado em '+a.atualizado_fmt)),
          h('span.acts-cell',a.status==='publicado'?pill('publicado','p-ok'):pill('rascunho','p-mute'),
            h('button.btn.btn-line.btn-sm',{type:'button',onclick:function(){get('artigo',{id:a.id}).then(function(x){if(x.ok)editor(x.artigo)})}},'Editar'),
            h('a.btn.btn-line.btn-sm',{href:'previa.php?id='+a.id,target:'_blank',rel:'noopener'},'Ver'),
            h('button.btn.btn-line.btn-sm.danger.so-master',{type:'button',onclick:function(){if(confirmar('Excluir o artigo “'+a.titulo+'”?'))api('artigo_excluir',{id:a.id}).then(function(x){if(x.ok){toast('Artigo excluído');VIEWS.artigos()}})}},'Excluir')));
      })):h('p.empty','Nenhum artigo.'))
    );
  });
};
/* reduz a foto no navegador antes de enviar (fotos de celular passam de 6 MB): maior lado = max px, JPEG */
function prepImagem(file,max){
  return loadImage(file).then(function(img){
    var w=img.naturalWidth,hh=img.naturalHeight,f=Math.min(1,max/Math.max(w,hh)),c=document.createElement('canvas');
    c.width=Math.round(w*f);c.height=Math.round(hh*f);var x=c.getContext('2d');x.fillStyle='#fff';x.fillRect(0,0,c.width,c.height);x.imageSmoothingQuality='high';x.drawImage(img,0,0,c.width,c.height);
    return toBlob(c,'image/jpeg',.86).then(function(b){return {blob:b,w:w,h:hh}});
  });
}
function enviarMidia(tipo,blob,nome){
  var fd=new FormData();fd.append('tipo',tipo);fd.append('arquivo',blob,nome);
  return api('midia_enviar',fd,true);
}

function editor(a){
  var tit=h('input',{maxLength:160,required:true,value:a.titulo||''}),slug=h('input',{maxLength:100,value:a.slug||'',placeholder:'gerado a partir do título'}),
    cat=h('input',{maxLength:40,value:a.categoria||'GESTÃO',list:'cats'}),res=h('input',{maxLength:300,value:a.resumo||'',placeholder:'Uma frase que aparece no cartão e no Google'}),
    corpo=h('textarea.art-editor',{rows:18,id:'corpo'}),st=h('select',h('option',{value:'rascunho'},'Rascunho (não aparece no site)'),h('option',{value:'publicado'},'Publicado'));
  corpo.value=a.corpo||'';st.value=a.status||'rascunho';
  var slugTocado=!!a.slug;slug.addEventListener('input',function(){slugTocado=true});
  tit.addEventListener('input',function(){if(!slugTocado)slug.value=tit.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'').slice(0,90)});
  var id=a.id||0,capa=a.capa||'';

  /* ----- capa ----- */
  var capaIn=h('input',{type:'file',accept:'image/jpeg,image/png,image/webp'}),capaPrev=h('div.capa-prev'),capaRm=h('button.btn.btn-line.btn-sm',{type:'button',onclick:function(){capa='';drawCapa()}},'Remover capa');
  function drawCapa(){capaPrev.textContent='';capaRm.hidden=!capa;
    capaPrev.appendChild(capa?h('img',{src:SITE+capa,alt:'Capa atual'}):h('p.muted','Sem capa. O cartão do artigo usa um bloco azul com o nome da categoria.'))}
  capaIn.addEventListener('change',function(){var f=capaIn.files[0];if(!f)return;
    if(['image/jpeg','image/png','image/webp'].indexOf(f.type)<0){toast('Envie a capa em JPG, PNG ou WEBP',true);capaIn.value='';return}
    capaPrev.textContent='';capaPrev.appendChild(h('p.muted','Enviando a capa…'));
    prepImagem(f,1600).then(function(r){
      if(r.w<600){toast('A foto é pequena para capa (mínimo 600 px de largura).',true);drawCapa();return}
      return enviarMidia('capa',r.blob,'capa.jpg').then(function(x){if(x.ok){capa=x.caminho}drawCapa()})
    }).catch(function(){toast('Não foi possível ler a imagem.',true);drawCapa()});
    capaIn.value='';
  });
  drawCapa();

  /* ----- barra de formatação ----- */
  function sel(){return {a:corpo.selectionStart,b:corpo.selectionEnd,t:corpo.value}}
  function pôr(txt,ini,fim){var s=sel();corpo.value=s.t.slice(0,s.a)+txt+s.t.slice(s.b);corpo.focus();corpo.setSelectionRange(s.a+ini,s.a+fim)}
  function envolver(pre,suf,exemplo){var s=sel(),meio=s.t.slice(s.a,s.b)||exemplo;pôr(pre+meio+suf,pre.length,pre.length+meio.length)}
  function prefixarLinhas(pre,exemplo){var s=sel(),ini=s.t.lastIndexOf('\n',s.a-1)+1,fim=s.b;
    if(fim===s.a){var nl=s.t.indexOf('\n',fim);fim=nl<0?s.t.length:nl} /* sem seleção: formata a linha onde está o cursor */
    var trecho=s.t.slice(ini,fim)||exemplo;
    var novo=trecho.split('\n').map(function(l){return pre+l.replace(/^(#{2,3} |[-•] |> )/,'')}).join('\n');
    corpo.value=s.t.slice(0,ini)+novo+s.t.slice(fim);corpo.focus();corpo.setSelectionRange(ini+pre.length,ini+novo.length)}
  /* bloco próprio (foto/vídeo/linha): garante linha em branco antes e depois; devolve a posição do cursor */
  function inserirBloco(txt,cursorDentro){var s=sel(),antes=s.t.slice(0,s.a).replace(/\s+$/,''),depois=s.t.slice(s.b).replace(/^\s+/,''),pre=antes?'\n\n':'',pos=antes.length+pre.length;
    corpo.value=antes+pre+txt+(depois?'\n\n'+depois:'\n');corpo.focus();var p=pos+(cursorDentro==null?txt.length:cursorDentro);corpo.setSelectionRange(p,p)}
  var busy=false,msg=h('span.ed-msg');
  function ocupado(on,txt){busy=on;msg.textContent=on?txt:'';$$('.ed-bar button').forEach(function(b){b.disabled=on})}
  var fotos=h('input',{type:'file',accept:'image/jpeg,image/png,image/webp',multiple:true,hidden:true}),
      videoIn=h('input',{type:'file',accept:'video/mp4',hidden:true});
  fotos.addEventListener('change',function(){
    var lista=Array.prototype.slice.call(fotos.files);fotos.value='';if(!lista.length)return;
    var ok=lista.filter(function(f){return ['image/jpeg','image/png','image/webp'].indexOf(f.type)>=0});
    if(ok.length<lista.length)toast('Só JPG, PNG ou WEBP. Alguns arquivos foram ignorados.',true);
    var i=0;(function prox(){
      if(i>=ok.length){ocupado(false);return}
      ocupado(true,'Enviando foto '+(i+1)+' de '+ok.length+'…');
      prepImagem(ok[i],1600).then(function(r){return enviarMidia('foto',r.blob,'foto.jpg')}).then(function(x){
        if(x&&x.ok){var t='[['+'foto:'+x.caminho+'|';inserirBloco(t+']]',t.length)}
        i++;prox()
      }).catch(function(){toast('Não foi possível ler uma das fotos.',true);i++;prox()});
    })();
  });
  videoIn.addEventListener('change',function(){var f=videoIn.files[0];videoIn.value='';if(!f)return;
    if(f.size>50*1024*1024){toast('O vídeo passa de 50 MB. Reduza a qualidade ao exportar ou use o link do YouTube.',true);return}
    ocupado(true,'Enviando o vídeo… pode levar alguns minutos');
    enviarMidia('video',f,f.name).then(function(x){ocupado(false);if(x.ok){var t='[['+'video:'+x.caminho+'|';inserirBloco(t+']]',t.length);toast('Vídeo enviado. Escreva a legenda, se quiser.')}});
  });
  function btn(rot,dica,fn,cls){return h('button'+(cls||''),{type:'button',title:dica,'aria-label':dica,onclick:function(){if(!busy)fn()}},rot)}
  var barra=h('div.ed-bar',
    btn('Subtítulo','Subtítulo da seção',function(){prefixarLinhas('## ','Subtítulo')}),
    btn('Negrito','Negrito',function(){envolver('**','**','texto em destaque')},'.b'),
    btn('Itálico','Itálico',function(){envolver('*','*','texto')},'.i'),
    btn('Lista','Lista com marcadores',function(){prefixarLinhas('- ','item')}),
    btn('Citação','Citação em destaque',function(){prefixarLinhas('> ','citação')}),
    btn('Link','Link para outra página',function(){var u=window.prompt('Cole o endereço do link (começa com https://)');if(!u)return;u=u.trim();
      if(!/^(https?:\/\/|\/)\S+$/.test(u)){toast('Endereço inválido. Ele deve começar com https://',true);return}envolver('[','](' +u+')','texto do link')}),
    btn('Linha','Linha divisória',function(){inserirBloco('---')}),
    h('span.sep'),
    btn('📷 Foto','Inserir foto (da galeria ou câmera)',function(){fotos.click()},'.media'),
    btn('▶ Vídeo','Inserir vídeo',function(){
      var u=window.prompt('Cole o link do vídeo no YouTube.\n\nPara enviar um vídeo seu (arquivo MP4, até 50 MB), deixe em branco e toque em OK.');
      if(u===null)return;u=u.trim();
      if(!u){videoIn.click();return}
      if(!/^https?:\/\/(www\.|m\.)?(youtube\.com|youtu\.be)\//.test(u)){toast('Use um link do YouTube (youtube.com ou youtu.be).',true);return}
      var t='[['+'video:'+u+'|';inserirBloco(t+']]',t.length)},'.media'),
    msg,fotos,videoIn);

  function salvar(ver){return api('artigo_salvar',{id:id,titulo:tit.value,slug:slug.value,categoria:cat.value,resumo:res.value,capa:capa,corpo:corpo.value,status:st.value}).then(function(x){
    if(x.ok){id=x.id;slug.value=x.slug;slugTocado=true;toast(st.value==='publicado'?'Artigo publicado':'Rascunho salvo');if(ver)window.open('previa.php?id='+id,'_blank','noopener')}return x})}
  render(
    title(id?'Editar artigo':'Novo artigo','Use os botões para formatar. O site cuida do visual.',h('button.btn.btn-line.btn-sm',{type:'button',onclick:function(){VIEWS.artigos()}},'← Voltar à lista')),
    h('form.panel.mt',{onsubmit:function(e){e.preventDefault();if(busy){toast('Aguarde o envio terminar.',true);return}salvar(false)}},
      h('div.fgrid2',field('Título',tit,true),field('Endereço',slug,false,'(guia/…)'),field('Categoria',cat),field('Resumo',res,true),
        h('datalist',{id:'cats'},['ELEIÇÃO','FINANÇAS','MANUTENÇÃO','ASSEMBLEIA','SEGURANÇA','LEGISLAÇÃO','GESTÃO'].map(function(x){return h('option',{value:x})})),
        field('Foto de capa',capaIn,true,'(aparece no cartão, no topo do artigo e ao compartilhar · recorte automático panorâmico)'),h('div.full',capaPrev,capaRm),
        h('div.full.ed',h('label',{htmlFor:'corpo'},'Texto'),barra,corpo),
        h('div.full.help',h('b','Dicas: '),'deixe uma linha em branco entre parágrafos. Ao inserir uma foto ou vídeo, escreva a legenda depois do traço ',h('code','|'),' (ex.: ',h('code','[[foto:…|Assembleia de março]]'),'). Vídeos do YouTube: cole o link. Use ',h('b','Salvar e ver'),' para conferir como fica no site.'),
        field('Situação',st),
        h('div.right.end',h('button.btn.btn-line',{type:'button',onclick:function(){if(busy){toast('Aguarde o envio terminar.',true);return}salvar(true)}},'Salvar e ver'),h('button.btn.btn-ink',{type:'submit'},'Salvar'))))
  );
}

/* ================= usuários e minha conta ================= */
function copiar(txt,msg){try{navigator.clipboard.writeText(txt).then(function(){toast(msg||'Copiado')},function(){window.prompt('Copie:',txt)})}catch(x){window.prompt('Copie:',txt)}}
function codigosBox(cods,titulo){
  return h('div.panel.invite-ok',h('h3',titulo||'Novos códigos de recuperação'),
    h('div.pad',h('p','Guarde estes códigos agora: eles não serão mostrados de novo e os códigos antigos deixaram de valer. Cada código vale para uma única entrada.'),
      h('ul.rec-codes',cods.map(function(c){return h('li',c)})),
      h('div.copyrow',h('button.btn.btn-ink.btn-sm',{type:'button',onclick:function(){copiar(cods.join('\n'),'Códigos copiados')}},'Copiar códigos'))));
}
function conviteBox(x){
  var link=h('input',{readOnly:true,value:x.link,'aria-label':'Link de acesso',onclick:function(){this.select()}}),primeiro=String(x.nome).split(' ')[0];
  return h('div.panel.invite-ok',h('h3','Link de acesso para '+x.nome),
    h('div.pad',h('p',x.email_enviado?'Enviamos este link para '+x.email+'. Se não chegar (olhe o spam), copie e envie você mesmo:':'Não foi possível enviar e-mail automaticamente. Copie o link e envie para '+x.nome+' por WhatsApp ou e-mail:'),
      h('div.copyrow',link,h('button.btn.btn-ink.btn-sm',{type:'button',onclick:function(){copiar(x.link,'Link copiado')}},'Copiar link'),
        h('a.btn.btn-line.btn-sm',{href:'https://wa.me/?text='+encodeURIComponent('Olá '+primeiro+'! Segue o link para criar seu acesso ao painel do site JC Síndico (vale por 48 horas): '+x.link),target:'_blank',rel:'noopener'},'Enviar por WhatsApp')),
      h('small.muted','Este link só aparece agora e vale por 48 horas. Quem tiver o link consegue criar a senha: envie somente para a pessoa certa. Ela também vai precisar de um aplicativo autenticador no celular.')));
}
/* Confirmação de ações sensíveis: pede de novo a senha e o código atual do aplicativo. fn(senha,codigo) devolve Promise<boolean> */
function pedirAutorizacao(titulo,fn){
  var s=h('input',{type:'password',autocomplete:'current-password',required:true}),c=h('input',{inputMode:'numeric',autocomplete:'one-time-code',maxLength:7,required:true,placeholder:'6 dígitos'}),dlg=h('dialog.reauth');
  function fechar(){try{dlg.close()}catch(x){}dlg.remove()}
  var f=h('form',{onsubmit:function(e){e.preventDefault();var b=f.querySelector('[type=submit]');b.disabled=true;
      fn(s.value,c.value).then(function(ok){b.disabled=false;if(ok)fechar();else{c.value='';c.focus()}})}},
    h('h3',titulo),h('p.muted','Por segurança, confirme sua senha e o código atual do aplicativo (6 dígitos).'),
    field('Sua senha',s),field('Código do aplicativo',c),
    h('div.right',h('button.btn.btn-line',{type:'button',onclick:fechar},'Cancelar'),h('button.btn.btn-ink',{type:'submit'},'Confirmar')));
  dlg.appendChild(f);dlg.addEventListener('cancel',function(){dlg.remove()});document.body.appendChild(dlg);
  if(dlg.showModal)dlg.showModal();else dlg.setAttribute('open','');
  s.focus();
}

VIEWS.usuarios=function(extra){
  get('usuarios').then(function(r){if(!r.ok)return;
    var eu=r.usuarios.filter(function(u){return u.eu})[0]||{};
    var SIT={ativo:['Ativo','p-ok'],convite:['Convite pendente','p-warn'],convite_expirado:['Convite expirado','p-bad'],redefinindo:['Redefinindo senha','p-warn'],desativado:['Desativado','p-mute']};
    function resp(x){if(x.ok&&x.link)VIEWS.usuarios({convite:x});else if(x.ok)VIEWS.usuarios()}
    var lista=h('ul.list.users',r.usuarios.map(function(u){
      var pend=u.situacao==='convite'||u.situacao==='convite_expirado';
      return h('li.user-li',h('div.t',h('b',u.nome+(u.eu?' (você)':'')),h('small',u.email+' · '+(u.ultimo_login_fmt?'último acesso '+u.ultimo_login_fmt:'ainda não entrou')+(u.convite_expira_fmt&&(pend||u.situacao==='redefinindo')?' · link válido até '+u.convite_expira_fmt:''))),
        h('span.acts-cell',pill(u.perfil==='master'?'Master':'Gestor',u.perfil==='master'?'p-info':'p-mute'),pill(SIT[u.situacao][0],SIT[u.situacao][1]),u.totp_ativo?pill('2 etapas ativa','p-ok'):pill('2 etapas pendente','p-warn'),
          u.eu?null:[
            h('button.btn.btn-line.btn-sm',{type:'button',onclick:function(){var novo=u.perfil==='master'?'gestor':'master';if(confirmar(novo==='master'?u.nome+' passará a ter acesso total (master), inclusive para excluir e gerenciar usuários. Continuar?':u.nome+' deixará de poder excluir registros e gerenciar usuários. Continuar?'))pedirAutorizacao('Mudar perfil de '+u.nome,function(sn,cd){return api('usuario_perfil',{id:u.id,perfil:novo,senha:sn,codigo:cd}).then(function(x){if(x.ok){toast('Perfil alterado');VIEWS.usuarios();return true}return false})})}},u.perfil==='master'?'Tornar gestor':'Tornar master'),
            u.situacao!=='desativado'?h('button.btn.btn-line.btn-sm',{type:'button',onclick:function(){if(confirmar(pend?'Gerar um novo convite para '+u.nome+'? O link anterior deixa de valer.':'Gerar um link para '+u.nome+' criar uma nova senha? Ela continua entrando com a senha atual até usar o link.'))pedirAutorizacao(pend?'Novo convite para '+u.nome:'Redefinir senha de '+u.nome,function(sn,cd){return api('usuario_convite_novo',{id:u.id,senha:sn,codigo:cd}).then(function(x){if(x.ok){resp(x);return true}return false})})}},pend?'Novo convite':'Redefinir senha'):null,
            u.totp_ativo?h('button.btn.btn-line.btn-sm',{type:'button',onclick:function(){if(confirmar('Redefinir a verificação em 2 etapas de '+u.nome+'? A senha atual dela deixa de valer: ela receberá um link para criar uma nova senha e configurar o aplicativo de novo, e as sessões abertas dela caem.'))pedirAutorizacao('Redefinir 2 etapas de '+u.nome,function(sn,cd){return api('usuario_2fa_reset',{id:u.id,senha:sn,codigo:cd}).then(function(x){if(x.ok){resp(x);return true}return false})})}},'Redefinir 2 etapas'):null,
            h('button.btn.btn-line.btn-sm',{type:'button',onclick:function(){var des=u.situacao!=='desativado';if(!des||confirmar('Desativar o acesso de '+u.nome+'? Ela perde o acesso na hora.'))api('usuario_status',{id:u.id,ativo:des?0:1}).then(function(x){if(x.ok){toast(des?'Acesso desativado':'Acesso reativado');VIEWS.usuarios()}})}},u.situacao==='desativado'?'Reativar':'Desativar'),
            h('button.btn.btn-line.btn-sm.danger',{type:'button',onclick:function(){if(confirmar('Remover '+u.nome+' definitivamente?'))pedirAutorizacao('Remover '+u.nome,function(sn,cd){return api('usuario_excluir',{id:u.id,senha:sn,codigo:cd}).then(function(x){if(x.ok){toast('Usuário removido');VIEWS.usuarios();return true}return false})})}},'Remover')]));
    }));

    var cNome=h('input',{maxLength:80,required:true,placeholder:'Nome da pessoa'}),cEmail=h('input',{type:'email',maxLength:160,required:true,placeholder:'email@exemplo.com',autocomplete:'off'}),cPerfil=h('select',h('option',{value:'gestor'},'Gestor (tudo, exceto excluir e gerenciar usuários)'),h('option',{value:'master'},'Master (acesso total)'));
    var convForm=h('form.inline-form.bl-form',{onsubmit:function(e){e.preventDefault();var nome=cNome.value,email=cEmail.value,perfil=cPerfil.value;pedirAutorizacao('Convidar '+nome,function(sn,cd){return api('usuario_convidar',{nome:nome,email:email,perfil:perfil,senha:sn,codigo:cd}).then(function(x){if(x.ok){resp(x);return true}return false})})}},
      field('Nome',cNome),field('E-mail',cEmail),field('Perfil',cPerfil),h('button.btn.btn-ink.btn-sm',{type:'submit'},'Convidar'));

    /* minha conta */
    var at=h('input',{type:'password',autocomplete:'current-password',required:true}),nv=h('input',{type:'password',autocomplete:'new-password',required:true,minLength:8}),nv2=h('input',{type:'password',autocomplete:'new-password',required:true,minLength:8}),cod=h('input',{inputMode:'numeric',autocomplete:'one-time-code',maxLength:7,required:true,placeholder:'6 dígitos'});
    var fs=h('form.panel',{onsubmit:function(e){e.preventDefault();if(nv.value!==nv2.value){toast('As senhas novas não conferem',true);return}
        api('senha',{atual:at.value,nova:nv.value,codigo:cod.value}).then(function(x){if(x.ok){toast('Senha alterada');fs.reset()}})}},
      h('h3','Trocar minha senha'),h('div.fgrid2',field('Senha atual',at),field('Código do aplicativo',cod,false,'(2 etapas)'),field('Nova senha',nv,false,'(mín. 8, com letras, números e caractere especial)'),field('Repita a nova senha',nv2),h('div.full.right',h('button.btn.btn-ink',{type:'submit'},'Trocar senha'))));

    function formSeguro(txt,botao,perigo,fn){
      var s=h('input',{type:'password',autocomplete:'current-password',required:true}),c=h('input',{inputMode:'numeric',autocomplete:'one-time-code',maxLength:7,required:true,placeholder:'6 dígitos'});
      var f=h('form.inline-form.bl-form',{onsubmit:function(e){e.preventDefault();fn(s.value,c.value,f)}},field('Senha atual',s),field('Código do aplicativo',c),h('button.btn.btn-sm'+(perigo?'.danger.btn-line':'.btn-ink'),{type:'submit'},botao));
      return h('div',h('p.muted',txt),f)}
    var seguranca=h('div.panel',h('h3','Verificação em 2 etapas'),
      h('div.pad',h('p',eu.totp_ativo?'Ativa. Você ainda tem '+eu.recuperacao+(eu.recuperacao===1?' código de recuperação.':' códigos de recuperação.')+(eu.recuperacao<=2?' Gere novos em breve.':''):'Não configurada.')),
      h('div.pad.two',
        formSeguro('Gerar 10 novos códigos de recuperação (os atuais deixam de valer):','Gerar novos códigos',false,function(s,c,f){api('codigos_novos',{senha:s,codigo:c}).then(function(x){if(x.ok){f.reset();VIEWS.usuarios({codigos:x.codigos})}})}),
        formSeguro('Trocou de celular? Reconfigure a verificação: você sai agora e configura o novo aparelho ao entrar de novo.','Reconfigurar 2 etapas',true,function(s,c){if(confirmar('Isto encerra a sua sessão. Continuar?'))api('usuario_2fa_reset',{id:eu.id,senha:s,codigo:c}).then(function(x){if(x.ok)location.reload()})})));

    render(
      title(r.master?'Usuários':'Minha conta',r.master?'Quem tem acesso ao painel. Master: acesso total, convida e gerencia pessoas. Gestor: tudo, exceto excluir registros e gerenciar usuários. Todo acesso exige senha e verificação em 2 etapas.':'Você tem o perfil Gestor: pode usar todo o painel, exceto excluir registros e gerenciar usuários.'),
      extra&&extra.convite?conviteBox(extra.convite):null,
      extra&&extra.codigos?codigosBox(extra.codigos):null,
      r.master?h('div.panel.mt',h('h3','Pessoas com acesso'),lista):null,
      r.master?h('div.panel',h('h3','Convidar uma pessoa'),h('div.pad',h('p.muted','Ela recebe um link (válido por 48 horas) para criar a senha e ativar a verificação em 2 etapas.')),convForm):null,
      h('h3.sec','Minha conta · '+eu.nome),fs,seguranca
    );
  });
};

/* ================= registros de atividade ================= */
VIEWS.logs=function(){
  var F={cat:'',nivel:'',dias:7,q:''},corpo=h('div.logs'),topo=h('div.log-top'),maisBtn=h('button.btn.btn-line.btn-sm',{type:'button',hidden:true},'Mostrar mais'),ultimoId=0,busca,n=0;
  var CATS=[['','Tudo'],['acesso','Acessos'],['seguranca','Segurança'],['painel','Ações no painel'],['site','Envios do site']],
      NIV={info:'p-info',aviso:'p-warn',alerta:'p-bad'};
  function linha(l){
    var quem=l.usuario_nome||l.email||(l.categoria==='site'?'Visitante':'—');
    return h('div.log-row.n-'+l.nivel,h('time',l.criado_fmt),
      h('div.log-ev',pill(l.rotulo,NIV[l.nivel]),l.detalhe?h('small',l.detalhe):null),
      h('div.log-who',h('b',quem),l.usuario_nome&&l.email?h('small',l.email):null),
      h('code.log-ip',{title:l.agente||''},l.ip));
  }
  function qs(extra){var q='';Object.keys(F).forEach(function(k){if(F[k]!==''&&F[k]!=null)q+='&'+k+'='+encodeURIComponent(F[k])});return q+(extra||'')}
  function carregar(mais){
    var ref=++n;
    get('logs',Object.assign({},F,mais?{antes:ultimoId}:{})).then(function(r){if(!r.ok||ref!==n)return;
      if(!mais){corpo.textContent='';resumo(r)}
      if(!r.logs.length&&!mais)corpo.appendChild(h('p.empty','Nenhum registro com estes filtros.'));
      r.logs.forEach(function(l){corpo.appendChild(linha(l));ultimoId=l.id});
      maisBtn.hidden=!r.mais;
    });
  }
  function resumo(r){
    topo.textContent='';
    var s=r.resumo;
    topo.appendChild(h('div.grid4.log-sum',
      h('div.tile',h('small','Entradas (24 h)'),h('strong',s.logins)),
      h('div.tile'+(s.falhas?'.warn':''),h('small','Tentativas que falharam (24 h)'),h('strong',s.falhas)),
      h('div.tile'+(s.bloqueios?'.bad':''),h('small','Bloqueios (24 h)'),h('strong',s.bloqueios)),
      h('div.tile'+(s.alertas?'.bad':''),h('small','Alertas (24 h)'),h('strong',s.alertas))));
    if(r.suspeitos.length)topo.appendChild(h('div.panel.sus',h('h3','Endereços com muitas tentativas nas últimas 24 horas'),
      h('ul.list',r.suspeitos.map(function(x){return h('li',h('div.t',h('b',x.ip),h('small',x.n+' tentativas · '+x.emails+(x.emails==1?' e-mail diferente':' e-mails diferentes')+' · última '+x.ultimo_fmt)),
        h('button.btn.btn-line.btn-sm',{type:'button',onclick:function(){F.q=x.ip;busca.value=x.ip;carregar(false)}},'Ver registros'))}))));
  }
  var chips=h('div.chips-adm',CATS.map(function(c){var b=h('button',{type:'button','aria-pressed':String(F.cat===c[0])},c[1]);
    b.addEventListener('click',function(){F.cat=c[0];$$('button',chips).forEach(function(x){x.setAttribute('aria-pressed','false')});b.setAttribute('aria-pressed','true');carregar(false)});return b}));
  var dias=h('select',[[1,'Últimas 24 horas'],[7,'Últimos 7 dias'],[30,'Últimos 30 dias'],[90,'Últimos 90 dias'],[0,'Tudo (até 180 dias)']].map(function(x){return h('option',{value:x[0]},x[1])})),
      niv=h('select',[['','Todos os níveis'],['aviso','Só avisos e alertas'],['alerta','Só alertas']].map(function(x){return h('option',{value:x[0]},x[1])}));
  dias.value=String(F.dias);
  dias.addEventListener('change',function(){F.dias=parseInt(dias.value,10);carregar(false)});niv.addEventListener('change',function(){F.nivel=niv.value;carregar(false)});
  busca=h('input',{type:'search',placeholder:'Buscar por IP, e-mail, nome ou detalhe',maxLength:80,'aria-label':'Buscar nos registros'});
  var t;busca.addEventListener('input',function(){clearTimeout(t);t=setTimeout(function(){F.q=busca.value.trim();carregar(false)},350)});
  maisBtn.addEventListener('click',function(){carregar(true)});
  var csv=h('a.btn.btn-line.btn-sm',{href:'#',target:'_blank',rel:'noopener'},'Baixar planilha (CSV)');
  csv.addEventListener('click',function(){csv.href=API+'?acao=logs_csv'+qs()});
  render(
    title('Registros','Tudo o que acontece no site e no painel: entradas, tentativas de acesso, ações feitas e envios de visitantes. Guardados por 180 dias, com o endereço IP de cada evento.',csv),
    topo,
    h('div.panel.mt',h('div.log-filters',chips,h('div.log-sel',dias,niv,busca)),corpo,h('div.pad.center',maisBtn))
  );
  carregar(false);
};

/* ================= configurações ================= */
VIEWS.config=function(){
  get('config').then(function(r){if(!r.ok)return;
    var c=r.config,F={};
    [['whatsapp','WhatsApp','tel'],['email_contato','E-mail que recebe os avisos de contato','email'],['horario','Horário de atendimento'],['cnpj','CNPJ'],
     ['endereco','Endereço (linha 1)'],['endereco2','Endereço (linha 2)'],['instagram','Link do Instagram','url'],['linkedin','Link do LinkedIn','url']].forEach(function(x){
      F[x[0]]=[x[1],h('input',{type:x[2]||'text',value:c[x[0]]||''})]});
    var form=h('form.panel.mt',{onsubmit:function(e){e.preventDefault();var d={};Object.keys(F).forEach(function(k){d[k]=F[k][1].value});api('config_salvar',d).then(function(x){if(x.ok)toast('Alterações salvas. Já aparecem no site.')})}},
      h('h3','Dados de contato e endereço'),h('div.fgrid2',Object.keys(F).map(function(k){return field(F[k][0],F[k][1])}),h('div.full.right',h('button.btn.btn-ink',{type:'submit'},'Salvar alterações'))));
    var base=c.hero_estilo==='clara'?'clara':'escura';
    var escolha={hero_estilo_web:(c.hero_estilo_web==='clara'||c.hero_estilo_web==='escura')?c.hero_estilo_web:base,
                 hero_estilo_mobile:(c.hero_estilo_mobile==='clara'||c.hero_estilo_mobile==='escura')?c.hero_estilo_mobile:base};
    var OPTS=[['escura','Escura (versão original)','Fundo azul-marinho com mostrador de elevador, palavras grandes e as duas fotos em movimento.'],
              ['clara','Clara, com o prédio','Céu claro, o prédio em azul atrás da dupla e janelas amarelas que acendem em Serviços ao rolar.']];
    function grupo(chave,titulo,sub){
      var radios=OPTS.map(function(o){
        var inp=h('input',{type:'radio',name:chave,value:o[0]});inp.checked=(o[0]===escolha[chave]);
        inp.addEventListener('change',function(){escolha[chave]=o[0]});
        return h('label.hero-opt',inp,h('span.mini.'+o[0]),h('span.t',h('b',o[1]),h('small',o[2])));
      });
      return h('div.hero-grp',h('h4',titulo),h('p.muted',sub),h('div.hero-opts',radios));
    }
    var formHero=h('form.panel.mt',{onsubmit:function(e){e.preventDefault();api('config_salvar',{hero_estilo_web:escolha.hero_estilo_web,hero_estilo_mobile:escolha.hero_estilo_mobile}).then(function(x){if(x.ok)toast('Primeira tela atualizada. Pode levar até 5 minutos para aparecer a quem já visitou o site.')})}},
      h('h3','Primeira tela do site'),
      h('p.muted','Escolha a abertura da página inicial separadamente para computador e celular. Você pode trocar quando quiser.'),
      grupo('hero_estilo_web','No computador (web)','Telas largas: computador e tablet grande.'),
      grupo('hero_estilo_mobile','No celular','Aparelhos celulares, em pé ou deitados.'),
      h('div.full.right',h('button.btn.btn-ink',{type:'submit'},'Salvar escolha')));
    render(title('Configurações','Dados que aparecem em todo o site. A troca de senha fica em Usuários → Minha conta.'),form,formHero);
  });
};

/* início: abre a aba do endereço (#contatos etc.) e atualiza os contadores */
var start=(location.hash||'').slice(1);
show(VIEWS[start]?start:'geral');
if(start&&start!=='geral')get('resumo').then(function(r){if(r.ok){badge('b-novos',r.pend.novos);badge('b-agenda',r.pend.agenda);badge('b-depo',r.pend.depo);badge('b-logs',r.alertas24)}});
})();
