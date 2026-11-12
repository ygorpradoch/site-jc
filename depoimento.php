<?php
/**
 * Página para clientes deixarem um depoimento. Não aparece no menu:
 * o Junior envia o link (/depoimento) para síndicos, conselheiros e moradores.
 * Nada é publicado sem aprovação no painel.
 */
require __DIR__ . '/app/bootstrap.php';
require JC_APP . '/layout.php';
layout_head([
    'title' => 'Deixe seu depoimento | JC Síndico Profissional',
    'desc' => 'Conte como é ter o Junior Chagas como síndico do seu condomínio.',
    'path' => 'depoimento',
    'robots' => 'noindex, follow',
]);
?>
<body class="sub" data-api="<?= e(url('api/')) ?>">
<?php layout_header(false); ?>
<main id="conteudo" class="art-page">
  <div class="wrap narrow">
    <span class="eyebrow">Depoimento</span>
    <h1 class="list-h">Como é ter o Junior como síndico?</h1>
    <p class="art-lead">Sua opinião ajuda outros condomínios a decidir. O texto só aparece no site depois de revisado, com o nome e o papel que você informar.</p>
    <form class="fgrid formcard depo-form" id="depo-form" novalidate>
      <div class="field full"><label for="d-texto">Seu depoimento</label><textarea id="d-texto" name="texto" maxlength="600" required placeholder="Ex.: a prestação de contas chega todo mês sem a gente precisar pedir."></textarea><small class="count" id="d-count">0 / 600</small></div>
      <div class="field"><label for="d-nome">Nome</label><input id="d-nome" name="nome" maxlength="100" autocomplete="name" required></div>
      <div class="field"><label for="d-papel">Você é <span>(opcional)</span></label><input id="d-papel" name="papel" maxlength="80" placeholder="Ex.: conselheira fiscal"></div>
      <div class="field full"><label for="d-cond">Condomínio <span>(opcional)</span></label><input id="d-cond" name="condominio" maxlength="120"></div>
      <div class="hp" aria-hidden="true"><label for="d-site">Não preencha</label><input id="d-site" name="site_url" tabindex="-1" autocomplete="off"></div>
      <label class="consent full"><input type="checkbox" id="d-ok" name="autorizado" value="1" required> <span>Autorizo a publicação deste texto no site, com o nome e o papel informados. Posso pedir a retirada a qualquer momento. <a href="<?= e(url('privacidade')) ?>" target="_blank">Política de Privacidade</a>.</span></label>
      <p class="err-msg full" id="d-err" role="alert" hidden></p>
      <div class="form-ok full" id="d-okmsg" role="status" tabindex="-1" hidden>Obrigado! Recebi seu depoimento.</div>
      <div class="form-foot full"><small>Leva menos de um minuto.</small><button class="btn btn-ink" type="submit">Enviar depoimento</button></div>
    </form>
  </div>
</main>
<?php layout_footer(false); ?>
<script defer src="<?= e(asset('assets/js/depoimento.js')) ?>"></script>
</body>
</html>
