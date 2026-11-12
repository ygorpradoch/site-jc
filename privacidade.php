<?php
/* Texto-base de política de privacidade (LGPD). REVISAR com assessoria jurídica antes da publicação no domínio principal. */
require __DIR__ . '/app/bootstrap.php';
require JC_APP . '/layout.php';
header('Cache-Control: public, max-age=3600');
layout_head([
    'title' => 'Política de Privacidade | JC Síndico Profissional',
    'desc' => 'Como a JC Síndico Profissional coleta, usa e protege os dados enviados pelo site.',
    'path' => 'privacidade',
]);
$email = setting('email_contato');
?>
<body class="sub">
<?php layout_header(false); ?>
<main id="conteudo" class="art-page">
  <article class="wrap art">
    <nav class="crumbs" aria-label="Você está em"><a href="<?= e(url('')) ?>">Início</a> / Privacidade</nav>
    <span class="eyebrow">LGPD · Lei 13.709/2018</span>
    <h1>Política de Privacidade</h1>
    <p class="art-lead">Este site pede poucos dados e usa cada um só para responder quem entrou em contato.</p>
    <div class="art-body">
      <h2>Quem é o responsável</h2>
      <p>Junior Chagas Síndico Profissional, CNPJ <?= e(setting('cnpj')) ?>, <?= e(setting('endereco')) ?>, <?= e(setting('endereco2')) ?>. Contato para assuntos de privacidade: <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a> ou WhatsApp <?= e(setting('whatsapp')) ?>.</p>

      <h2>Quais dados coletamos e para quê</h2>
      <ul>
        <li><strong>Pedido de proposta:</strong> nome, WhatsApp, papel no condomínio, nome do condomínio, número de unidades e a mensagem que você escrever. Usamos para preparar e enviar a proposta e conversar sobre ela.</li>
        <li><strong>Agendamento de conversa:</strong> nome, WhatsApp, dia, horário e formato escolhidos. Usamos para confirmar e realizar a conversa.</li>
        <li><strong>Diagnóstico gratuito:</strong> as respostas são guardadas sem nome nem telefone, só para estatística. Se você decidir enviar o resultado, ele segue junto com o seu pedido de proposta ou pela conversa no WhatsApp.</li>
        <li><strong>Depoimentos:</strong> texto, nome, papel e condomínio que você informar, publicados apenas com a sua autorização.</li>
        <li><strong>Segurança:</strong> para evitar envios automáticos e abuso, registramos uma versão cifrada (hash) do endereço IP. O IP em si não é armazenado.</li>
      </ul>

      <h2>Base legal</h2>
      <p>O tratamento é feito com o seu <strong>consentimento</strong>, dado ao marcar a caixa de concordância antes de enviar, e para os <strong>procedimentos preliminares de um contrato</strong> que você pediu (art. 7º, I e V, da LGPD). O registro de segurança se apoia no <strong>legítimo interesse</strong> de proteger o site (art. 7º, IX).</p>

      <h2>Com quem compartilhamos</h2>
      <p>Não vendemos nem cedemos seus dados. Eles ficam armazenados no servidor de hospedagem do site (Hostinger) e são acessados apenas pela equipe JC. A conversa pelo WhatsApp segue as regras do próprio aplicativo. As fontes tipográficas do site são carregadas do Google Fonts, que recebe o endereço IP do seu navegador para entregar os arquivos.</p>

      <h2>Cookies</h2>
      <p>As páginas públicas não usam cookies de rastreamento nem de publicidade. O painel administrativo usa um cookie de sessão, apenas para quem faz login.</p>

      <h2>Por quanto tempo guardamos</h2>
      <p>Os dados de contato ficam guardados enquanto houver conversa ou contrato em andamento e por até 2 anos depois do último contato, salvo obrigação legal de guardar por mais tempo. Você pode pedir a exclusão antes disso.</p>

      <h2>Seus direitos</h2>
      <p>Você pode, a qualquer momento, pedir para confirmar se tratamos seus dados, acessar, corrigir, excluir, saber com quem foram compartilhados e revogar o consentimento (art. 18 da LGPD). Basta escrever para <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>. Respondemos em até 15 dias. Você também pode procurar a Autoridade Nacional de Proteção de Dados (ANPD).</p>

      <h2>Segurança</h2>
      <p>O site usa conexão criptografada (HTTPS), o painel exige senha com limite de tentativas e o acesso aos dados é restrito à equipe.</p>

      <h2>Alterações</h2>
      <p>Esta política pode ser atualizada. A versão em vigor é sempre a publicada nesta página.</p>
    </div>
  </article>
</main>
<?php layout_footer(false); ?>
</body>
</html>
