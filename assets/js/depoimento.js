/* Formulário público de depoimento */
(function(){
'use strict';
var f=document.getElementById('depo-form');if(!f)return;
var api=(document.body.dataset.api||'/api/')+'depoimento.php';
var t=document.getElementById('d-texto'),cnt=document.getElementById('d-count'),er=document.getElementById('d-err'),ok=document.getElementById('d-okmsg');
t.addEventListener('input',function(){cnt.textContent=t.value.length+' / 600'});
function err(m,field){er.textContent=m;er.hidden=false;if(field){field.setAttribute('aria-invalid','true');field.focus()}}
f.addEventListener('submit',function(e){
  e.preventDefault();er.hidden=true;
  Array.prototype.forEach.call(f.querySelectorAll('[aria-invalid]'),function(x){x.removeAttribute('aria-invalid')});
  var nome=document.getElementById('d-nome'),aut=document.getElementById('d-ok');
  if(t.value.trim().length<20)return err('Escreva um pouco mais sobre a sua experiência (mínimo de 20 caracteres).',t);
  if(nome.value.trim().length<2)return err('Informe seu nome.',nome);
  if(!aut.checked)return err('Para publicar, precisamos da sua autorização.',aut);
  var b=f.querySelector('[type=submit]');b.disabled=true;
  fetch(api,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({
    texto:t.value,nome:nome.value,papel:document.getElementById('d-papel').value,condominio:document.getElementById('d-cond').value,
    autorizado:1,site_url:document.getElementById('d-site').value})})
  .then(function(r){return r.json()}).catch(function(){return {ok:false,erro:'Sem conexão. Tente de novo.'}})
  .then(function(r){b.disabled=false;if(!r.ok)return err(r.erro||'Não foi possível enviar.');f.reset();cnt.textContent='0 / 600';ok.hidden=false;ok.focus()});
});
})();
