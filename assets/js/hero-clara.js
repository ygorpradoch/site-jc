/* Hero claro: três janelas acesas do prédio viajam até o indicador de andar, o LED e o ícone do 1º cartão de Serviços.
   Só transform/opacity, sem pin. Roda depois do GSAP; não faz nada com movimento reduzido. */
(function(){
  if(!window.gsap||!window.ScrollTrigger||window.matchMedia('(prefers-reduced-motion: reduce)').matches)return;
  var hero=document.getElementById('inicio');
  if(!hero||!hero.classList.contains('hc'))return;
  gsap.registerPlugin(ScrollTrigger);

  var Q=function(s){return hero.querySelector(s)};
  var ppl=Q('.hc-people'),city=Q('.hc-city'),layer=Q('.hc-flys');
  var floorEl=document.querySelector('.floor'),c1=document.querySelector('.scard.c1');
  var docPos=function(el){var x=0,y=0;while(el){x+=el.offsetLeft;y+=el.offsetTop;el=el.offsetParent}return{x:x,y:y}};
  var pcts=[],flyO=[],flyI=[],geo=[];
  gsap.utils.toArray('.hc-fly').forEach(function(f){
    pcts.push({l:parseFloat(f.style.left)/100,t:parseFloat(f.style.top)/100,k:f.getAttribute('data-t')});
    var o=document.createElement('div');o.className='hc-flyO';layer.appendChild(o);o.appendChild(f);f.style.left=f.style.top='';
    flyO.push(o);flyI.push(f);
  });
  var hitEl=[floorEl,c1.querySelector('.led'),c1.querySelector('.ico')];
  /* posições medidas por offset (ignoram transform); refeitas em refresh/resize/fonts */
  function measure(){
    var hp=docPos(hero),cp=docPos(city),cw=city.offsetWidth,ch=city.offsetHeight;
    var fw=cw*14/1624,fh=fw*20/14;
    flyO.forEach(function(o,i){
      var x=cp.x+pcts[i].l*cw,y=cp.y+pcts[i].t*ch;
      o.style.left=(x-hp.x)+'px';o.style.top=(y-hp.y)+'px';
      flyI[i].style.width=fw+'px';flyI[i].style.height=fh+'px';
      geo[i]={cx:x+fw/2,cy:y+fh/2};
    });
  }
  function target(k){
    if(k==='floor'){var r=floorEl.getBoundingClientRect();return{x:r.left+r.width/2+scrollX,y:r.top+r.height/2+hero.offsetHeight}}
    var sp=docPos(c1.parentNode),el=c1.querySelector(k==='led'?'.led':'.ico'),er=el.getBoundingClientRect(),cr=c1.getBoundingClientRect();
    return{x:sp.x+(er.left-cr.left)+er.width/2,y:sp.y+(er.top-cr.top)+er.height/2};
  }
  measure();
  ScrollTrigger.addEventListener('refreshInit',measure);
  var E=function(){return hero.offsetHeight};
  var arr=[],lit=[false,false,false],tl;
  tl=gsap.timeline({defaults:{ease:'none'},scrollTrigger:{trigger:hero,start:0,end:function(){return E()},scrub:.3,invalidateOnRefresh:true,
    onUpdate:function(self){
      layer.classList.toggle('on',self.progress>.003);
      var t=tl.time();
      for(var i=0;i<3;i++){var h=t>=arr[i];if(h!==lit[i]){lit[i]=h;hitEl[i].classList.toggle('hc-hit',h)}}
    }}});
  tl.to(ppl,{y:function(){return -.16*E()},duration:1},0)
    .to(ppl,{opacity:0,duration:.5},.1);
  flyO.forEach(function(o,i){
    var d=[.85,1,.9][i],s=[.1,0,.06][i];
    arr[i]=s+d-.08;
    tl.to(flyI[i],{x:function(){return target(pcts[i].k).x-geo[i].cx},y:function(){return target(pcts[i].k).y-geo[i].cy},scale:1.7,duration:d,ease:'power1.inOut'},s)
      .to(flyI[i],{opacity:0,duration:.1},s+d-.08);
  });
  addEventListener('load',function(){ScrollTrigger.refresh()});
  if(window.ResizeObserver){var rt,ro=new ResizeObserver(function(){clearTimeout(rt);rt=setTimeout(function(){ScrollTrigger.refresh()},150)});ro.observe(hero)}
  if(document.fonts&&document.fonts.ready)document.fonts.ready.then(function(){ScrollTrigger.refresh()});
})();
