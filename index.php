<?php
require __DIR__ . '/app/bootstrap.php';
require JC_APP . '/layout.php';

$condos = condominios_site();
$totUn = array_sum(array_map(fn($c) => (int) $c['unidades'], $condos));
$nCond = count($condos);
$anos = anos_gestao();
$depos = depoimentos_site();
$posts = artigos_publicados(3);
$wa = setting('whatsapp');
$waDigits = preg_replace('/\D+/', '', $wa);
$waRef = wa_link('Olá, gostaria de falar com o conselho de um condomínio atendido pela JC.');

header('Cache-Control: public, max-age=300');

layout_head([
    'title' => 'Síndico profissional em Fortaleza | JC Síndico Profissional',
    'desc' => "A JC Síndico Profissional administra condomínios em Fortaleza desde 2014. Gestão financeira, manutenção, assembleias e visita semanal, com prestação de contas todo mês. {$nCond} condomínios, {$totUn} unidades.",
    'path' => '',
    'preload' => ['assets/img/junior-novo.webp'],
    'jsonld' => jsonld_negocio(),
]);
?>
<body data-wa="<?= e($waDigits) ?>" data-api="<?= e(url('api/')) ?>">
<?php layout_header(true); ?>

<div class="lift-panel" id="lift-panel" hidden role="dialog" aria-modal="true" aria-label="Escolha o andar">
  <div class="lift-plate">
    <div class="ttl">Escolha o andar</div>
    <div class="grid">
      <button type="button" data-ride="inicio"><i>T</i>Início</button>
      <button type="button" data-ride="servicos"><i>1</i>Serviços</button>
      <button type="button" data-ride="diagnostico"><i>2</i>Diagnóstico</button>
      <button type="button" data-ride="como-funciona"><i>3</i>Como funciona</button>
      <button type="button" data-ride="condominios"><i>4</i>Condomínios</button>
      <button type="button" data-ride="quem-sou"><i>5</i>Quem somos</button>
      <button type="button" data-ride="videos"><i>6</i>Vídeos</button>
      <button type="button" data-ride="guia"><i>7</i>Guia</button>
      <button type="button" data-ride="contato"><i>8</i>Contato</button>
    </div>
    <div class="plate-cta"><a class="btn btn-sun" href="#contato" data-ride="contato">Pedir proposta</a><a class="btn btn-ghost" href="<?= e(wa_link()) ?>" target="_blank" rel="noopener">WhatsApp</a></div>
    <button type="button" class="close" id="lift-x">Fechar</button>
  </div>
</div>

<div class="doors" id="doors" aria-hidden="true">
  <div class="door l"><div class="half"><img src="<?= e(url('assets/img/logo-h-branco.svg')) ?>" alt="" width="1148" height="641"></div></div>
  <div class="door r"><div class="half"><img src="<?= e(url('assets/img/logo-h-branco.svg')) ?>" alt="" width="1148" height="641"></div></div>
  <div class="seam" id="seam"></div>
  <div class="d-display" id="d-display">1<small>Serviços</small></div>
</div>

<main id="conteudo">
  <section class="sh" id="inicio" data-floor="T" data-label="Térreo">
    <div class="sh-bg" aria-hidden="true"></div>
    <div class="sh-back" aria-hidden="true">SEU PRÉDIO SEU PRÉDIO</div>
    <svg class="sh-dial" id="dial" viewBox="-110 -110 220 220" aria-hidden="true">
      <circle r="100" stroke="rgba(156,201,255,.16)" stroke-width=".4" fill="none"/>
      <circle r="78" stroke="rgba(60,180,240,.5)" stroke-width=".35" stroke-dasharray="1 2.2" fill="none"/>
      <g id="ticks"></g>
    </svg>
    <img class="sh-photo2" src="<?= e(url('assets/img/felipe-bracos.webp')) ?>" alt="Felipe, da equipe JC, de braços cruzados" width="734" height="1000">
    <img class="sh-photo" fetchpriority="high" src="<?= e(url('assets/img/junior-novo.webp')) ?>" alt="Junior Chagas, síndico profissional, de braços cruzados com a camisa da JC" width="965" height="1193">
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

  <aside class="ref-band" aria-label="Referência de clientes">
    <div class="wrap"><p><b>Quer falar com quem já contratou a JC?</b> Colocamos você em contato com o conselho de um dos nossos condomínios.</p><a href="<?= e($waRef) ?>" target="_blank" rel="noopener" class="ref-link">Pedir uma referência →</a></div>
  </aside>

  <section class="block svc-sec" id="servicos" data-floor="1" data-label="Serviços">
    <div class="svc-marquee" aria-hidden="true"><span>FINANÇAS · MANUTENÇÃO · ASSEMBLEIAS · PRESENÇA · FINANÇAS · MANUTENÇÃO · ASSEMBLEIAS · PRESENÇA ·</span></div>
    <div class="wrap">
      <div class="head">
        <div><span class="eyebrow">1º andar · Serviços</span><h2 class="h2">Quatro frentes, um único responsável</h2></div>
        <p>São 16 serviços organizados em quatro áreas. O condomínio contrata a gestão completa e tem uma pessoa respondendo por tudo.</p>
      </div>
      <div class="stack">
        <article class="scard c1" style="--i:0"><div class="ctop"><svg class="ico" viewBox="0 0 64 64" aria-hidden="true"><circle pathLength="1" cx="24" cy="38" r="14"/><path pathLength="1" d="M24 30v16M28.5 33.5h-6a3 3 0 0 0 0 6h3a3 3 0 0 1 0 6h-6"/><path pathLength="1" d="M44 52V34M52 52V22M58 14l-8 8-6-4-8 8"/></svg><span class="led"><b>4</b>serviços</span></div><span class="k">Finanças</span><h3>Contas em ordem e à vista</h3><p>Prestação de contas todo mês, que qualquer morador consegue ler.</p>
          <ul><li>Gestão administrativa e financeira</li><li>Previsão orçamentária e análise financeira</li><li>Conferência e controle das contas</li><li>Análise e negociação de contratos</li></ul><div class="dim"></div></article>
        <article class="scard c2" style="--i:1"><div class="ctop"><svg class="ico" viewBox="0 0 64 64" aria-hidden="true"><path pathLength="1" d="M42 8a13 13 0 0 0-12 17.5L10 45.5a5.5 5.5 0 0 0 7.8 7.8l20-20A13 13 0 0 0 55.5 21L48 28.5l-7-1.5-1.5-7L47 12.5A13 13 0 0 0 42 8z"/><path pathLength="1" d="M14 50l3-3"/></svg><span class="led"><b>5</b>serviços</span></div><span class="k">Manutenção e obras</span><h3>Prédio conservado, sem surpresa no caixa</h3><p>Plano de manutenção e obras acompanhadas de perto.</p>
          <ul><li>Implantação do plano de manutenção</li><li>Manutenções periódicas e vistorias</li><li>Contratação e acompanhamento de obras</li><li>Orçamentos e escolha de fornecedores</li><li>Redução do consumo de água e energia</li></ul><div class="dim"></div></article>
        <article class="scard c3" style="--i:2"><div class="ctop"><svg class="ico" viewBox="0 0 64 64" aria-hidden="true"><rect pathLength="1" x="12" y="8" width="32" height="44" rx="3"/><path pathLength="1" d="M19 20h18M19 28h18M19 36h10"/><path pathLength="1" d="M36 46l6 6 14-16"/></svg><span class="led"><b>3</b>serviços</span></div><span class="k">Assembleias e regras</span><h3>Decisões tomadas do jeito certo</h3><p>Assembleias organizadas, objetivas e registradas.</p>
          <ul><li>Convocação e condução de assembleias</li><li>Reuniões com o conselho fiscal e eleições</li><li>Revisão do regimento interno</li></ul><div class="dim"></div></article>
        <article class="scard c4" style="--i:3"><div class="ctop"><svg class="ico" viewBox="0 0 64 64" aria-hidden="true"><path pathLength="1" d="M32 6l20 8v15c0 13-8.5 22-20 27C20.5 51 12 42 12 29V14z"/><path pathLength="1" d="M32 20v12l8 5"/></svg><span class="led"><b>4</b>serviços</span></div><span class="k">Presença e segurança</span><h3>Alguém que aparece, inclusive fora de hora</h3><p>Visita toda semana e atendimento de emergência.</p>
          <ul><li>Visitas semanais programadas</li><li>Visitas emergenciais</li><li>Análise do sistema de segurança</li><li>Seguro de responsabilidade civil profissional</li></ul><div class="dim"></div></article>
      </div>
    </div>
  </section>

  <section class="block alt diag-sec" id="diagnostico" data-floor="2" data-label="Diagnóstico">
    <div class="wrap qz-wrap">
      <div class="qz-intro">
        <span class="eyebrow">2º andar · Diagnóstico gratuito</span>
        <h2 class="h2">Seu condomínio está em dia?</h2>
        <p><?= count(JC_DIAG) ?> perguntas rápidas sobre o que todo prédio precisa manter. No final você vê o que está pendente e pode mandar o resultado para a JC.</p>
        <ul class="qz-facts"><li><b><?= count(JC_DIAG) ?></b>perguntas</li><li><b>1</b>minuto</li><li><b>0</b>cadastro</li></ul>
        <div class="qz-legend" aria-hidden="true"><span><i class="ok"></i>em dia</span><span><i class="no"></i>pendente</span><span><i class="ns"></i>não sei</span></div>
      </div>
      <div class="qz" id="qz" tabindex="-1">
        <div class="qz-top">
          <span class="qz-step" id="qz-step">Pergunta 1 de <?= count(JC_DIAG) ?></span>
          <div class="qz-floors" id="qz-floors" aria-hidden="true"></div>
        </div>
        <div class="qz-stage" id="qz-stage" aria-live="polite">
          <noscript><p>Ative o JavaScript para fazer o diagnóstico, ou <a href="<?= e(wa_link('Olá, quero um diagnóstico do meu condomínio.')) ?>">peça pelo WhatsApp</a>.</p></noscript>
        </div>
        <div class="qz-nav" id="qz-nav">
          <button type="button" class="qz-link" id="qz-back">← Voltar</button>
          <span class="qz-hint">Atalhos: 1 · 2 · 3</span>
          <button type="button" class="qz-link" id="qz-skip">Pular →</button>
        </div>
      </div>
    </div>
    <script type="application/json" id="diag-data"><?= json_encode(JC_DIAG, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  </section>

  <section class="block" id="como-funciona" data-floor="3" data-label="Como funciona">
    <div class="wrap">
      <div class="head">
        <div><span class="eyebrow">3º andar · Como funciona</span><h2 class="h2">Da primeira conversa ao primeiro balancete</h2></div>
        <p>A troca de síndico segue o rito previsto no Código Civil e na convenção do condomínio. A JC acompanha cada etapa com o conselho.</p>
      </div>
      <ol class="steps">
        <li><b>Conversa inicial</b><span class="when">semana 1</span><p>Reunião com o síndico atual ou o conselho para entender o prédio, as contas e os problemas em aberto.</p></li>
        <li><b>Proposta por escrito</b><span class="when">até 5 dias úteis</span><p>Escopo, frequência de visitas e valor mensal, prontos para apresentar aos condôminos.</p></li>
        <li><b>Assembleia</b><span class="when">conforme convenção</span><p>Apresentamos a proposta pessoalmente e respondemos às perguntas antes da votação.</p></li>
        <li><b>Transição</b><span class="when">primeiros 30 dias</span><p>Levantamento de contratos, documentos, pendências e saldo bancário, com relatório ao conselho.</p></li>
        <li><b>Gestão contínua</b><span class="when">todo mês</span><p>Visitas semanais, prestação de contas mensal e assembleias nos prazos.</p></li>
      </ol>
    </div>
  </section>

  <section class="cond-sec" id="condominios" data-floor="4" data-label="Condomínios">
    <div class="wrap">
      <div class="head">
        <div><span class="eyebrow">4º andar · Condomínios atendidos</span><h2 class="h2">Condomínios que administramos em Fortaleza</h2></div>
        <p>O primeiro contrato completou <?= $anos ?> anos e continua ativo. <b><?= $nCond ?> condomínios, <?= $totUn ?> unidades.</b> Quer uma referência? Pedimos ao conselho de um deles para conversar com você.</p>
      </div>
      <div class="condos-x" id="condos-x" tabindex="0" role="region" aria-label="Condomínios atendidos. Deslize para o lado ou use as setas.">
        <div class="condos" id="condos">
          <?php foreach ($condos as $c): $ano = substr($c['desde'], 0, 4); ?>
          <article class="condo">
            <div class="pic">
              <?php if ($c['foto']): ?>
              <img class="photo" loading="lazy" src="<?= e(url($c['foto'])) ?>" alt="Fachada do <?= e($c['nome']) ?>, condomínio administrado pela JC Síndico Profissional em Fortaleza" width="460" height="416">
              <?php else: ?>
              <span class="nofoto">FOTO EM BREVE</span>
              <?php endif; ?>
              <svg viewBox="0 0 460 416" preserveAspectRatio="none" aria-hidden="true">
                <line class="dl" pathLength="1" x1="440" y1="24" x2="440" y2="392"/><line class="dl" pathLength="1" x1="432" y1="24" x2="448" y2="24"/>
                <line class="dl" pathLength="1" x1="432" y1="392" x2="448" y2="392"/><line class="dl" pathLength="1" x1="20" y1="404" x2="300" y2="404"/>
                <text class="dt" x="20" y="396"><?= (int) $c['unidades'] ?> UNID. · DESDE <?= e($ano) ?></text>
              </svg>
            </div>
            <div class="meta">
              <span class="k"><?= e($c['bairro'] ? $c['bairro'] . ' · Fortaleza' : 'Fortaleza · CE') ?></span>
              <h3><?= e($c['nome']) ?></h3>
              <dl>
                <div><dt>Unidades</dt><dd><?= (int) $c['unidades'] ?></dd></div>
                <div><dt>Na gestão desde</dt><dd><?= e(fmt_mes_ano($c['desde'])) ?></dd></div>
              </dl>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="h-foot">
        <button type="button" class="h-arrow" id="h-prev" aria-label="Condomínio anterior">‹</button>
        <button type="button" class="h-arrow" id="h-next" aria-label="Próximo condomínio">›</button>
        <button type="button" class="h-arrow h-pause" id="h-pause" aria-pressed="false" aria-label="Pausar passagem automática">❚❚</button>
        <div class="h-bar"><i id="h-bar"></i></div>
        <span id="h-count">Deslize para ver os outros</span>
      </div>
    </div>
  </section>

  <?php if ($depos): ?>
  <section class="block" id="depoimentos">
    <div class="wrap">
      <div class="head">
        <div><span class="eyebrow">Depoimentos</span><h2 class="h2">O que dizem os conselhos</h2></div>
        <p>Palavras de síndicos, conselheiros e moradores dos condomínios que administramos, publicadas com autorização.</p>
      </div>
      <div class="quotes">
        <?php foreach ($depos as $d): ?>
        <figure class="quote" style="margin:0"><p>“<?= e($d['texto']) ?>”</p><footer><b><?= e($d['nome']) ?></b><?= e(implode(' · ', array_filter([$d['papel'], $d['condominio']]))) ?></footer></figure>
        <?php endforeach; ?>
      </div>
      <?php if (count($depos) > 1): ?><span class="swipe">Deslize para ler mais →</span><?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="block alt" id="quem-sou" data-floor="5" data-label="Quem somos">
    <div class="wrap about">
      <div class="portrait">
        <img class="full" src="<?= e(url('assets/img/junior-foto.webp')) ?>" alt="Junior Chagas com a camisa da JC Síndico Profissional" width="900" height="1599" loading="lazy">
        <div class="cap"><b>Junior Chagas</b><span>SÍNDICO PROFISSIONAL · CNPJ <?= e(setting('cnpj')) ?></span></div>
      </div>
      <div class="about-text">
        <span class="eyebrow">5º andar · Quem somos</span>
        <h2 class="h2">Uma gestão que conhece o prédio pelo nome dos funcionários</h2>
        <p class="lead">A JC começou em <?= e(setting('inicio_ano')) ?>, com o Junior Chagas à frente do primeiro condomínio, e fez da gestão condominial profissional a sua especialidade. Hoje atendemos <?= $nCond ?> condomínios em Fortaleza, sempre com o mesmo método.</p>
        <p class="muted">Trabalhamos com três compromissos: transparência nas contas, imparcialidade nas decisões entre moradores e cuidado constante com a saúde financeira do condomínio. Sem vínculo com nenhuma unidade, tratamos todos da mesma forma.</p>
        <ol class="timeline">
          <li><span class="y">2023</span><div><b>Processos Gerenciais</b><small>Faculdade Estácio de Sá</small></div></li>
          <li><span class="y">2020</span><div><b>Curso de Gestão Condominial</b><small>SENAC Ceará</small></div></li>
          <li><span class="y">2017</span><div><b>Síndico Profissional</b><small>Curso SíndicoNet</small></div></li>
          <li><span class="y">2014</span><div><b>Primeiro condomínio sob gestão</b><small>Cond. Praia Nova, até hoje</small></div></li>
          <li><span class="y">2008</span><div><b>Pedagogia</b><small>Faculdade da Ibiapaba</small></div></li>
        </ol>
      </div>
    </div>
    <div class="wrap about about-2">
      <div class="portrait">
        <img class="full" src="<?= e(url('assets/img/felipe-foto.webp')) ?>" alt="Felipe Matos, síndico profissional auxiliar da JC Síndico Profissional" width="900" height="1600" loading="lazy">
        <div class="cap"><b>Felipe Matos</b><span>SÍNDICO PROFISSIONAL AUXILIAR</span></div>
      </div>
      <div class="about-text">
        <span class="eyebrow">Equipe JC</span>
        <h2 class="h2">Mais de uma pessoa cuidando do seu prédio, com o mesmo método</h2>
        <p class="lead">Felipe coordena há mais de 5 anos a rotina de condomínios comerciais, residenciais (horizontais e verticais) e associações, assessorando síndicos, diretores e conselheiros fiscais nas tomadas de decisão.</p>
        <p class="muted">Na JC, acompanha o Junior nas visitas, organiza as demandas dos moradores e faz a ponte com fornecedores, para que nenhum pedido fique sem resposta. Quando o Junior está em assembleia ou vistoria, o Felipe segue com o atendimento.</p>
        <ol class="timeline">
          <li><span class="y">2026</span><div><b>Processos Gerenciais</b><small>Formação concluída</small></div></li>
          <li><span class="y">2021</span><div><b>Coordenador de mais de 32 condomínios</b><small>Comerciais, residenciais (horizontais e verticais) e associações</small></div></li>
          <li><span class="y">2018</span><div><b>Supervisor administrativo</b><small>Uma das maiores redes de supermercados do Ceará</small></div></li>
        </ol>
      </div>
    </div>
  </section>

  <section class="reels-sec" id="videos" data-floor="6" data-label="Vídeos">
    <div class="wrap">
      <div class="head">
        <div><span class="eyebrow">6º andar · Vídeos</span><h2 class="h2">Direto do condomínio, em menos de um minuto</h2></div>
        <p>Dicas e bastidores gravados pela equipe da JC. Os vídeos tocam sem som quando aparecem; toque para ouvir.</p>
      </div>
      <div class="reels" id="reels">
        <?php
        $reels = [
            [1, 'Felipe', 'Tire suas dúvidas', 'Vídeo do Felipe tirando dúvidas'],
            [2, 'Felipe', 'Por dentro do condomínio', 'Vídeo do Felipe no condomínio'],
            [3, 'Junior', 'Para onde vão as despesas', 'Vídeo do Junior sobre despesas'],
            [4, 'Junior', 'Prestação de contas na prática', 'Vídeo do Junior sobre prestação de contas'],
        ];
        foreach ($reels as [$n, $quem, $tit, $aria]): ?>
        <figure class="reel" data-src="<?= e(url("assets/vid/reel$n.mp4")) ?>" data-preview="<?= e(url("assets/vid/reel$n-p.mp4")) ?>" data-poster="<?= e(url("assets/vid/reel$n.webp")) ?>"><div class="reel-box"><video muted playsinline loop preload="none" aria-label="<?= e($aria) ?>"></video><button type="button" class="pp" aria-label="Pausar vídeo">❚❚</button><button type="button" class="snd">Som</button></div><figcaption><b><?= e($quem) ?></b><?= e($tit) ?></figcaption></figure>
        <?php endforeach; ?>
      </div>
      <p class="reels-foot"><a href="<?= e(setting('instagram')) ?>" target="_blank" rel="noopener">Mais vídeos no Instagram @jc.sindicoprofissional</a></p>
    </div>
  </section>

  <section class="block" id="por-que">
    <div class="wrap">
      <div class="head">
        <div><span class="eyebrow">Por que um profissional</span><h2 class="h2">Síndico morador e síndico profissional, lado a lado</h2></div>
        <p>O síndico morador faz muito pelo prédio, geralmente no tempo que sobra. A diferença está na dedicação e no preparo técnico.</p>
      </div>
      <div class="compare"><table>
        <thead><tr><th></th><th>Síndico morador</th><th>Síndico profissional</th></tr></thead>
        <tbody>
          <tr><th>Tempo dedicado</th><td data-l="Síndico morador">Divide com o próprio trabalho e a família.</td><td data-l="Síndico profissional">A gestão do condomínio é o trabalho principal, com agenda fixa de visitas.</td></tr>
          <tr><th>Conflitos entre vizinhos</th><td data-l="Síndico morador">Decide sobre pessoas com quem convive todos os dias.</td><td data-l="Síndico profissional">Não mora no prédio, então decide com base na convenção e no regimento.</td></tr>
          <tr><th>Contratos e obras</th><td data-l="Síndico morador">Aprende na prática, caso a caso.</td><td data-l="Síndico profissional">Tem referência de preços de vários fornecedores.</td></tr>
          <tr><th>Prestação de contas</th><td data-l="Síndico morador">Formato varia de gestão para gestão.</td><td data-l="Síndico profissional">Relatório mensal padronizado, enviado a todas as unidades.</td></tr>
          <tr><th>Responsabilidade</th><td data-l="Síndico morador">Normalmente não tem seguro de responsabilidade civil próprio.</td><td data-l="Síndico profissional">Tem seguro de responsabilidade civil profissional, com apólice vigente.</td></tr>
        </tbody>
      </table></div>
    </div>
  </section>

  <section class="block alt" id="guia" data-floor="7" data-label="Guia">
    <div class="wrap">
      <div class="head">
        <div><span class="eyebrow">Guia do condomínio</span><h2 class="h2">Artigos para síndicos e conselheiros</h2></div>
        <p>Textos curtos sobre as dúvidas que aparecem nas assembleias. <a href="<?= e(url('guia')) ?>">Ver todos os artigos →</a></p>
      </div>
      <div class="posts">
        <?php foreach ($posts as $p): ?>
        <?= post_card($p) ?>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="block" id="duvidas">
    <div class="wrap faq">
      <div class="faq-side">
        <span class="eyebrow">Dúvidas frequentes</span>
        <h2 class="h2">O que os conselhos costumam perguntar</h2>
        <p>Se a sua pergunta não estiver aqui, mande no WhatsApp. Respondemos até o próximo dia útil.</p>
      </div>
      <div>
        <details open><summary>Uma pessoa de fora do prédio pode ser síndica?</summary><p>Pode. A lei permite que a assembleia eleja um síndico que não seja condômino, pessoa física ou empresa.<small>Código Civil, art. 1.347</small></p></details>
        <details><summary>Quanto custa um síndico profissional?</summary><p>O valor depende do número de unidades, da estrutura do prédio e da frequência de visitas. Enviamos uma proposta fechada depois da conversa inicial, sem custo.</p></details>
        <details><summary>Como fica a administradora que já atende o condomínio?</summary><p>Ela pode continuar. A JC trabalha junto com a administradora atual, que segue cuidando de boletos e folha, e fica responsável pela gestão e pelas decisões do dia a dia.</p></details>
        <details><summary>Os moradores conseguem acompanhar as contas?</summary><p>Sim. Todo mês cada unidade recebe a prestação de contas, com receitas, despesas, saldo e fundo de reserva.</p></details>
        <details><summary>E se o condomínio não ficar satisfeito?</summary><p>A assembleia pode destituir o síndico conforme o Código Civil e a convenção do condomínio. Além disso, o contrato prevê encerramento com aviso prévio.<small>Código Civil, art. 1.349</small></p></details>
      </div>
    </div>
  </section>

  <section class="contact" id="contato" data-floor="8" data-label="Contato">
    <div class="wrap">
      <div>
        <span class="eyebrow">Contato</span>
        <h2 class="h2">Conte sobre o seu condomínio</h2>
        <p class="lead">Peça uma proposta ou marque uma conversa de 30 minutos, por vídeo ou no seu prédio.</p>
      </div>

      <div class="formcard">
        <div class="ftabs" role="tablist" aria-label="Tipo de contato">
          <button type="button" role="tab" id="t-prop" aria-selected="true" aria-controls="lead-form">Pedir proposta</button>
          <button type="button" role="tab" id="t-agen" aria-selected="false" aria-controls="sched-form">Agendar conversa</button>
        </div>

        <form class="fgrid" id="lead-form" role="tabpanel" aria-labelledby="t-prop" novalidate>
          <fieldset class="roles full"><legend>Você é</legend>
            <label><input type="radio" name="papel" value="sindico" checked> Síndico atual</label>
            <label><input type="radio" name="papel" value="conselho"> Conselho</label>
            <label><input type="radio" name="papel" value="morador"> Morador</label>
            <label><input type="radio" name="papel" value="administradora"> Administradora</label>
          </fieldset>
          <div class="field"><label for="f-nome">Nome</label><input id="f-nome" name="nome" autocomplete="name" required maxlength="120" aria-describedby="lead-err" placeholder="Seu nome"></div>
          <div class="field"><label for="f-wa">WhatsApp</label><input id="f-wa" name="whatsapp" type="tel" inputmode="tel" autocomplete="tel" required maxlength="20" aria-describedby="lead-err" placeholder="(85) 9 0000-0000"></div>
          <div class="field"><label for="f-cond">Condomínio e bairro</label><input id="f-cond" name="condominio" maxlength="160" placeholder="Ex.: Residencial Sol, Aldeota"></div>
          <div class="field"><label for="f-un">Nº de unidades</label><select id="f-un" name="unidades"><option>Até 30</option><option>31 a 80</option><option>81 a 150</option><option>Mais de 150</option></select></div>
          <div class="field full"><label for="f-msg">Qual o principal problema hoje? <span>(opcional)</span></label><textarea id="f-msg" name="mensagem" maxlength="2000" placeholder="Ex.: o mandato do síndico termina em janeiro e ninguém quer assumir."></textarea></div>
          <div class="hp" aria-hidden="true"><label for="f-site">Não preencha</label><input id="f-site" name="site_url" tabindex="-1" autocomplete="off"></div>
          <label class="consent full"><input type="checkbox" id="f-ok" name="consentimento" value="1" required aria-describedby="lead-err"> <span>Concordo com o uso dos meus dados para receber a proposta, conforme a <a href="<?= e(url('privacidade')) ?>" target="_blank">Política de Privacidade</a>.</span></label>
          <p class="err-msg full" id="lead-err" role="alert" hidden></p>
          <div class="form-ok full" id="lead-ok" role="status" tabindex="-1" hidden>Recebemos seu pedido. Respondemos pelo WhatsApp até o próximo dia útil.</div>
          <div class="form-foot full"><small>Seus dados são usados só para responder este pedido.</small><button class="btn btn-ink" type="submit">Enviar pedido</button></div>
        </form>

        <form class="fgrid" id="sched-form" role="tabpanel" aria-labelledby="t-agen" hidden novalidate>
          <div class="full"><span class="flabel">Formato</span>
            <div class="roles" style="margin-top:8px">
              <label><input type="radio" name="modo" value="video" checked> Videochamada</label>
              <label><input type="radio" name="modo" value="presencial"> No condomínio</label>
              <label><input type="radio" name="modo" value="escritorio"> No escritório</label>
            </div></div>
          <div class="full"><span class="flabel" id="lbl-dia">Dia</span><div class="days" id="days" role="group" aria-labelledby="lbl-dia" style="margin-top:8px"><span class="slots-msg">Carregando horários…</span></div></div>
          <div class="full"><span class="flabel" id="lbl-hora">Horário <span style="font-weight:400;color:var(--ink-soft)">· 30 min</span></span><div class="slots" id="slots" role="group" aria-labelledby="lbl-hora" style="margin-top:8px"></div></div>
          <div class="field"><label for="s-nome">Nome</label><input id="s-nome" name="nome" autocomplete="name" required maxlength="120" placeholder="Seu nome"></div>
          <div class="field"><label for="s-wa">WhatsApp</label><input id="s-wa" name="whatsapp" type="tel" inputmode="tel" autocomplete="tel" required maxlength="20" placeholder="(85) 9 0000-0000"></div>
          <div class="hp" aria-hidden="true"><label for="s-site">Não preencha</label><input id="s-site" name="site_url" tabindex="-1" autocomplete="off"></div>
          <label class="consent full"><input type="checkbox" id="s-ok" name="consentimento" value="1" required> <span>Concordo com o uso dos meus dados para marcar a conversa, conforme a <a href="<?= e(url('privacidade')) ?>" target="_blank">Política de Privacidade</a>.</span></label>
          <p class="err-msg full" id="sched-err" role="alert" hidden></p>
          <div class="form-ok full" id="sched-ok" role="status" tabindex="-1" hidden></div>
          <div class="form-foot full"><small>Confirmamos o horário pelo WhatsApp.</small><button class="btn btn-ink" type="submit">Pedir este horário</button></div>
        </form>
      </div>
      <dl class="cinfo">
        <div><dt>WhatsApp</dt><dd><a class="num" id="wa-num" href="<?= e(wa_link()) ?>" target="_blank" rel="noopener"><?= e($wa) ?></a><button class="copy" type="button" data-copy="<?= e($wa) ?>">copiar</button></dd></div>
        <div><dt>Endereço</dt><dd><?= e(setting('endereco')) ?><br><?= e(setting('endereco2')) ?></dd></div>
        <div><dt>Horário</dt><dd><?= e(setting('horario')) ?></dd></div>
        <div><dt>Redes</dt><dd><a href="<?= e(setting('instagram')) ?>" target="_blank" rel="noopener">Instagram</a> · <a href="<?= e(setting('linkedin')) ?>" target="_blank" rel="noopener">LinkedIn</a></dd></div>
      </dl>
    </div>
  </section>
</main>

<?php layout_footer(true); ?>
<div class="toast" id="toast" role="status" hidden></div>

<script defer src="<?= e(asset('assets/js/vendor/gsap.min.js')) ?>"></script>
<script defer src="<?= e(asset('assets/js/vendor/ScrollTrigger.min.js')) ?>"></script>
<script defer src="<?= e(asset('assets/js/site.js')) ?>"></script>
</body>
</html>
