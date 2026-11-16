<?php /* Primeira tela escura (versão original): mostrador de elevador, palavras gigantes e fotos em parallax. Variáveis: $anos, $nCond, $totUn. */ ?>
  <section class="sh" id="inicio" data-floor="T" data-label="Térreo">
    <div class="sh-bg" aria-hidden="true"></div>
    <div class="sh-back" aria-hidden="true">SEU PRÉDIO SEU PRÉDIO</div>
    <svg class="sh-dial" id="dial" viewBox="-110 -110 220 220" aria-hidden="true">
      <circle r="100" stroke="rgba(156,201,255,.16)" stroke-width=".4" fill="none"/>
      <circle r="78" stroke="rgba(60,180,240,.5)" stroke-width=".35" stroke-dasharray="1 2.2" fill="none"/>
      <g id="ticks"></g>
    </svg>
    <img class="sh-photo2" src="<?= e(url('assets/img/felipe-frontal.webp')) ?>" alt="Felipe, da equipe JC, de braços cruzados" width="1100" height="1375">
    <img class="sh-photo" fetchpriority="high" src="<?= e(url('assets/img/junior-frontal.webp')) ?>" alt="Junior Chagas, síndico profissional, de braços cruzados com a camisa da JC" width="1100" height="1375">
    <div class="sh-front" aria-hidden="true">EM BOAS <em>MÃOS</em></div>
    <ul class="sh-chips" aria-label="Números da gestão">
      <li><b><?= $anos ?></b>anos de gestão</li>
      <li><b><?= $nCond ?></b>condomínios</li>
      <li><b><?= $totUn ?></b>unidades</li>
    </ul>
    <div class="sh-copy">
      <span class="eyebrow">JC Síndico Profissional · desde <?= e(setting('inicio_ano')) ?></span>
      <h1>Síndico profissional em Fortaleza, com contas abertas todo mês</h1>
      <p>Somos a <b>JC Síndico Profissional</b>. Nossa equipe cuida das finanças, da manutenção e das assembleias do seu condomínio, com visita toda semana e prestação de contas que qualquer morador entende.</p>
      <div class="btns"><a class="btn btn-sun" href="#contato" data-ride="contato">Pedir proposta</a><a class="btn btn-ghost" href="#diagnostico" data-ride="diagnostico">Diagnóstico grátis</a></div>
    </div>
  </section>
