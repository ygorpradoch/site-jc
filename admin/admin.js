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
    badge('b-novos',p.novos);badge('b-agenda',p.agenda);badge('b-depo',p.depo);
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
          h('button.btn.btn-line.btn-sm.danger',{type:'button',onclick:function(){if(confirmar('Excluir este contato definitivamente? (use quando a pessoa pedir a exclusão dos dados)'))api('lead_excluir',{id:l.id}).then(function(x){if(x.ok){toast('Contato excluído');leadSel=null;VIEWS.contatos()}})}},'Excluir')
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
    var disp=r.disp;
    function setDisp(k){return function(v){var d={};d[k]=v?1:0;api('disponibilidade',d).then(function(x){if(x.ok){toast('Disponibilidade atualizada no site');VIEWS.agenda()}})}}
    var bData=h('input',{type:'date',required:true,min:new Date().toISOString().slice(0,10)}),bHora=h('select',h('option',{value:''},'Dia inteiro'),['09:00','09:30','10:00','10:30','11:00','14:00','14:30','15:00','15:30','16:00'].map(function(x){return h('option',x)})),bMot=h('input',{maxLength:120,placeholder:'Ex.: assembleia, viagem'});
    var bForm=h('form.inline-form.bl-form',{onsubmit:function(e){e.preventDefault();api('bloqueio_add',{data:bData.value,hora:bHora.value,motivo:bMot.value}).then(function(x){if(x.ok){toast('Bloqueado');VIEWS.agenda()}})}},
      field('Bloquear data',bData),field('Horário',bHora),field('Motivo',bMot,false,'(opcional)'),h('button.btn.btn-ink.btn-sm',{type:'submit'},'Bloquear'));
    var prev=r.dias.length?h('div.ag-prev',r.dias.map(function(d){var livres=d.slots.filter(function(s){return s.livre}).length;return h('span',h('b',d.dia+' '+d.rotulo),livres+' livres')})):h('p.empty','Nenhum horário aparece no site agora. Ative manhã ou tarde.');
    render(
      title('Agenda','Conversas pedidas pelo site. Horários livres aparecem automaticamente para agendamento.'),
      h('div.panel.mt',h('h3','Próximas conversas'),h('div.pad',lista)),
      h('div.cols',
        h('div.panel',h('h3','Horários que aparecem no site'),h('ul.list',
          h('li',h('div.t',h('b','Manhã · 9h às 11h30'),h('small','Segunda a sexta')),toggle(disp.manha,'Manhã disponível',setDisp('manha'))),
          h('li',h('div.t',h('b','Tarde · 14h às 16h30'),h('small','Segunda a sexta')),toggle(disp.tarde,'Tarde disponível',setDisp('tarde'))),
          h('li',h('div.t',h('b','Sábado · 9h às 11h'),h('small','Somente se quiser atender no sábado')),toggle(disp.sabado,'Sábado disponível',setDisp('sabado')))),
          h('div.pad',h('small.muted','Próximos dias no site:'),prev)),
        h('div.panel',h('h3','Dias e horários bloqueados'),bForm,
          r.bloqueios.length?h('ul.list',r.bloqueios.map(function(b){return h('li',h('div.t',h('b',b.dia+(b.hora?' · '+b.hora:' · dia inteiro')),h('small',b.motivo||'')),
            h('button.btn.btn-line.btn-sm',{type:'button',onclick:function(){api('bloqueio_del',{id:b.id}).then(function(x){if(x.ok){toast('Desbloqueado');VIEWS.agenda()}})}},'Remover'))})):h('p.empty','Nenhum bloqueio.'))
      )
    );
  });
};

/* ================= condomínios ================= */
/* Recorta a foto no navegador (envio leve) e gera o desenho em linha, no mesmo estilo das fotos atuais. */
function loadImage(file){return new Promise(function(ok,fail){var u=URL.createObjectURL(file),i=new Image();i.onload=function(){ok(i)};i.onerror=function(){URL.revokeObjectURL(u);fail()};i.src=u})}
function cover(img,w,hh){var c=document.createElement('canvas');c.width=w;c.height=hh;var x=c.getContext('2d'),r=w/hh,sw=img.naturalWidth,sh=img.naturalHeight,cw,ch;
  if(sw/sh>r){ch=sh;cw=sh*r}else{cw=sw;ch=sw/r}x.imageSmoothingQuality='high';x.drawImage(img,(sw-cw)/2,(sh-ch)/2,cw,ch,0,0,w,hh);return c}
function lineArt(img){
  var W=460,H=416,c=cover(img,W,H),x=c.getContext('2d'),d=x.getImageData(0,0,W,H),p=d.data,g=new Float32Array(W*H),b=new Float32Array(W*H),m=new Float32Array(W*H),i,y,xx;
  for(i=0;i<W*H;i++)g[i]=.299*p[i*4]+.587*p[i*4+1]+.114*p[i*4+2];
  for(y=1;y<H-1;y++)for(xx=1;xx<W-1;xx++){i=y*W+xx;b[i]=(g[i-W-1]+2*g[i-W]+g[i-W+1]+2*g[i-1]+4*g[i]+2*g[i+1]+g[i+W-1]+2*g[i+W]+g[i+W+1])/16}
  var hist=[];
  for(y=2;y<H-2;y++)for(xx=2;xx<W-2;xx++){i=y*W+xx;
    var gx=-b[i-W-1]-2*b[i-1]-b[i+W-1]+b[i-W+1]+2*b[i+1]+b[i+W+1],gy=-b[i-W-1]-2*b[i-W]-b[i-W+1]+b[i+W-1]+2*b[i+W]+b[i+W+1];
    m[i]=Math.sqrt(gx*gx+gy*gy);if((i&7)===0)hist.push(m[i])}
  hist.sort(function(a,z){return a-z});
  var lo=hist[Math.floor(hist.length*.82)]||20,hi=hist[Math.floor(hist.length*.97)]||80;if(hi<=lo)hi=lo+1;
  for(i=0;i<W*H;i++){var a=Math.max(0,Math.min(1,(m[i]-lo)/(hi-lo)));p[i*4]=171;p[i*4+1]=209;p[i*4+2]=255;p[i*4+3]=Math.round(a*255)}
  x.putImageData(d,0,0);return c;
}
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
          h('button.btn.btn-line.btn-sm.danger',{type:'button',onclick:function(){if(confirmar('Remover '+c.nome+' do site?'))api('condo_excluir',{id:c.id}).then(function(x){if(x.ok){toast('Removido');VIEWS.condos()}})}},'Remover'))
      ));
    });
    var c=edit||{},nome=h('input',{required:true,maxLength:120,placeholder:'Ex.: Residencial Sol',value:c.nome||''}),bairro=h('input',{maxLength:80,placeholder:'Ex.: Aldeota',value:c.bairro||''}),
      un=h('input',{type:'number',min:1,max:5000,required:true,placeholder:'48',value:c.unidades||''}),desde=h('input',{type:'month',required:true,value:c.desde?c.desde.slice(0,7):new Date().toISOString().slice(0,7)}),
      foto=h('input',{type:'file',accept:'image/jpeg,image/png,image/webp'}),prev=h('div.cprev'),fBlob=null,lBlob=null;
    if(c.foto)prev.appendChild(h('img',{src:SITE+c.foto,alt:'Foto atual'}));
    foto.addEventListener('change',function(){fBlob=lBlob=null;prev.textContent='';var f=foto.files[0];if(!f)return;
      if(['image/jpeg','image/png','image/webp'].indexOf(f.type)<0){toast('Envie a foto em JPG, PNG ou WEBP',true);foto.value='';return}
      if(f.size>25*1024*1024){toast('Arquivo muito grande',true);foto.value='';return}
      prev.appendChild(h('p.muted','Preparando a foto e o desenho…'));
      loadImage(f).then(function(img){
        if(img.naturalWidth<400||img.naturalHeight<360){toast('A foto é pequena demais (mínimo 400 px de largura).',true);foto.value='';prev.textContent='';return}
        var pc=cover(img,920,832),lc=lineArt(img);
        return Promise.all([toBlob(pc,'image/jpeg',.88),toBlob(lc,'image/png')]).then(function(bs){fBlob=bs[0];lBlob=bs[1];prev.textContent='';
          var a=h('img',{alt:'Foto recortada'});a.src=URL.createObjectURL(fBlob);var b=h('img.ln',{alt:'Desenho em linha'});b.src=URL.createObjectURL(lBlob);
          prev.appendChild(a);prev.appendChild(b);prev.appendChild(h('small.muted','Recorte e desenho que vão para o site.'))});
      }).catch(function(){toast('Não foi possível ler a imagem.',true)});
    });
    var form=h('form.panel.condo-form',{onsubmit:function(e){e.preventDefault();
        var fd=new FormData();if(c.id)fd.append('id',c.id);fd.append('nome',nome.value);fd.append('bairro',bairro.value);fd.append('unidades',un.value);fd.append('desde',desde.value);
        if(foto.files[0]&&!fBlob){toast('Aguarde a foto terminar de processar.',true);return}
        if(fBlob){fd.append('foto',fBlob,'foto.jpg');fd.append('linha',lBlob,'linha.png')}
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
          h('button.btn.btn-line.btn-sm.danger',{type:'button',onclick:function(){if(confirmar('Excluir este depoimento?'))api('depo_excluir',{id:d.id}).then(function(x){if(x.ok){toast('Excluído');VIEWS.depo()}})}},'Excluir')));
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
            h('button.btn.btn-line.btn-sm.danger',{type:'button',onclick:function(){if(confirmar('Excluir o artigo “'+a.titulo+'”?'))api('artigo_excluir',{id:a.id}).then(function(x){if(x.ok){toast('Artigo excluído');VIEWS.artigos()}})}},'Excluir')));
      })):h('p.empty','Nenhum artigo.'))
    );
  });
};
function editor(a){
  var tit=h('input',{maxLength:160,required:true,value:a.titulo||''}),slug=h('input',{maxLength:100,value:a.slug||'',placeholder:'gerado a partir do título'}),
    cat=h('input',{maxLength:40,value:a.categoria||'GESTÃO',list:'cats'}),res=h('input',{maxLength:300,value:a.resumo||'',placeholder:'Uma frase que aparece no cartão e no Google'}),
    corpo=h('textarea.art-editor',{rows:18}),st=h('select',h('option',{value:'rascunho'},'Rascunho (não aparece no site)'),h('option',{value:'publicado'},'Publicado'));
  corpo.value=a.corpo||'';st.value=a.status||'rascunho';
  var slugTocado=!!a.slug;slug.addEventListener('input',function(){slugTocado=true});
  tit.addEventListener('input',function(){if(!slugTocado)slug.value=tit.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'').slice(0,90)});
  var id=a.id||0;
  function salvar(ver){return api('artigo_salvar',{id:id,titulo:tit.value,slug:slug.value,categoria:cat.value,resumo:res.value,corpo:corpo.value,status:st.value}).then(function(x){
    if(x.ok){id=x.id;slug.value=x.slug;slugTocado=true;toast(st.value==='publicado'?'Artigo publicado':'Rascunho salvo');if(ver)window.open('previa.php?id='+id,'_blank','noopener')}return x})}
  render(
    title(id?'Editar artigo':'Novo artigo','Escreva em texto simples. O site cuida da formatação.',h('button.btn.btn-line.btn-sm',{type:'button',onclick:function(){VIEWS.artigos()}},'← Voltar à lista')),
    h('form.panel.mt',{onsubmit:function(e){e.preventDefault();salvar(false)}},
      h('div.fgrid2',field('Título',tit,true),field('Endereço',slug,false,'(guia/…)'),field('Categoria',cat),field('Resumo',res,true),
        h('datalist',{id:'cats'},['ELEIÇÃO','FINANÇAS','MANUTENÇÃO','ASSEMBLEIA','SEGURANÇA','LEGISLAÇÃO','GESTÃO'].map(function(x){return h('option',{value:x})})),
        field('Texto',corpo,true),
        h('div.full.help',h('b','Como formatar: '),'deixe uma linha em branco entre parágrafos · ',h('code','## Subtítulo'),' · ',h('code','- item de lista'),' · ',h('code','**negrito**')),
        field('Situação',st),
        h('div.right.end',h('button.btn.btn-line',{type:'button',onclick:function(){salvar(true)}},'Salvar e ver'),h('button.btn.btn-ink',{type:'submit'},'Salvar'))))
  );
}

/* ================= configurações ================= */
VIEWS.config=function(){
  get('config').then(function(r){if(!r.ok)return;
    var c=r.config,F={};
    [['whatsapp','WhatsApp','tel'],['email_contato','E-mail que recebe os avisos de contato','email'],['horario','Horário de atendimento'],['cnpj','CNPJ'],
     ['endereco','Endereço (linha 1)'],['endereco2','Endereço (linha 2)'],['instagram','Link do Instagram','url'],['linkedin','Link do LinkedIn','url']].forEach(function(x){
      F[x[0]]=[x[1],h('input',{type:x[2]||'text',value:c[x[0]]||''})]});
    var form=h('form.panel.mt',{onsubmit:function(e){e.preventDefault();var d={};Object.keys(F).forEach(function(k){d[k]=F[k][1].value});api('config_salvar',d).then(function(x){if(x.ok)toast('Alterações salvas. Já aparecem no site.')})}},
      h('h3','Dados de contato e endereço'),h('div.fgrid2',Object.keys(F).map(function(k){return field(F[k][0],F[k][1])}),h('div.full.right',h('button.btn.btn-ink',{type:'submit'},'Salvar alterações'))));
    var at=h('input',{type:'password',autocomplete:'current-password',required:true}),nv=h('input',{type:'password',autocomplete:'new-password',required:true,minLength:10}),nv2=h('input',{type:'password',autocomplete:'new-password',required:true,minLength:10});
    var fs=h('form.panel',{onsubmit:function(e){e.preventDefault();if(nv.value!==nv2.value){toast('As senhas novas não conferem',true);return}
        api('senha',{atual:at.value,nova:nv.value}).then(function(x){if(x.ok){toast('Senha alterada');fs.reset()}})}},
      h('h3','Trocar senha · '+r.email),h('div.fgrid2',field('Senha atual',at,true),field('Nova senha',nv,false,'(mín. 10, letras e números)'),field('Repita a nova senha',nv2),h('div.full.right',h('button.btn.btn-ink',{type:'submit'},'Trocar senha'))));
    render(title('Configurações','Dados que aparecem em todo o site.'),form,fs);
  });
};

/* início: abre a aba do endereço (#contatos etc.) e atualiza os contadores */
var start=(location.hash||'').slice(1);
show(VIEWS[start]?start:'geral');
if(start&&start!=='geral')get('resumo').then(function(r){if(r.ok){badge('b-novos',r.pend.novos);badge('b-agenda',r.pend.agenda);badge('b-depo',r.pend.depo)}});
})();
