<?php
defined('JC') || exit;

/** Ícone do WhatsApp (SVG inline). */
const JC_WA_SVG = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.8 11.9 11.9 0 0 0 4.6 4c1.7.7 2.4.8 3.2.7.5-.1 1.5-.6 1.8-1.2.2-.6.2-1.1.1-1.2l-.5-.3Z"/></svg>';

/**
 * <head> completo.
 * $o: title, desc, path (canonical), type (website|article), image, jsonld (array), robots
 */
function layout_head(array $o): void
{
    $title = $o['title'] ?? 'JC Síndico Profissional';
    $desc = $o['desc'] ?? '';
    $canon = abs_url($o['path'] ?? '');
    $img = abs_url($o['image'] ?? 'assets/img/og.jpg');
    $robots = $o['robots'] ?? (cfg('indexar', false) ? 'index, follow' : 'noindex, nofollow');
    ?><!doctype html>
<html lang="pt-BR" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="robots" content="<?= e($robots) ?>">
<link rel="canonical" href="<?= e($canon) ?>">
<meta name="theme-color" content="#0A1E3F">
<meta name="color-scheme" content="light"> <!-- o site público é sempre claro/escuro por seção, independente do modo do aparelho; o painel admin segue o aparelho -->
<link rel="icon" href="<?= e(url('favicon.svg')) ?>" type="image/svg+xml">
<link rel="icon" href="<?= e(url('favicon.png')) ?>" type="image/png" sizes="192x192">
<link rel="apple-touch-icon" href="<?= e(url('apple-touch-icon.png')) ?>">
<meta property="og:locale" content="pt_BR">
<meta property="og:site_name" content="JC Síndico Profissional">
<meta property="og:type" content="<?= e($o['type'] ?? 'website') ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($canon) ?>">
<meta property="og:image" content="<?= e($img) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..900&family=Figtree:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
<?php foreach (($o['css'] ?? []) as $c): ?>
<link rel="stylesheet" href="<?= e(asset($c)) ?>">
<?php endforeach; ?>
<?php foreach (($o['preload'] ?? []) as $p): ?>
<link rel="preload" as="image" href="<?= e(url($p)) ?>" fetchpriority="high">
<?php endforeach; ?>
<?php if (!empty($o['jsonld'])): ?>
<script type="application/ld+json"><?= json_encode($o['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif; ?>
</head>
<?php
}

/** Cabeçalho. Na página inicial os links rolam até as seções; nas internas voltam para a inicial. */
function layout_header(bool $home): void
{
    $h = $home ? '' : url('');
    $links = [
        'servicos' => 'Serviços', 'diagnostico' => 'Diagnóstico', 'como-funciona' => 'Como funciona',
        'condominios' => 'Condomínios', 'quem-sou' => 'Quem somos', 'videos' => 'Vídeos', 'guia' => 'Guia', 'contato' => 'Contato',
    ];
    ?>
<a class="skip" href="#conteudo">Pular para o conteúdo</a>
<header class="top<?= $home ? '' : ' scrolled sub-top' ?>" id="topbar">
  <div class="wrap">
    <a class="brand" href="<?= e($h ?: '#inicio') ?>" aria-label="JC Síndico Profissional, início">
      <img src="<?= e(url('assets/img/logo-h-branco.svg')) ?>" alt="JC Síndico Profissional" width="1148" height="641">
    </a>
    <nav class="nav" id="nav" aria-label="Principal">
      <?php foreach ($links as $id => $txt): ?>
      <a href="<?= e(($id === 'guia' && !$home) ? url('guia') : $h . '#' . $id) ?>"><?= e($txt) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="top-cta">
      <?php if ($home): ?><a class="btn btn-ghost" href="#contato" data-open-tab="agendar">Agendar conversa</a><?php endif; ?>
      <a class="btn btn-sun" href="<?= e($h) ?>#contato">Pedir proposta</a>
    </div>
    <?php if ($home): ?>
    <div class="lift-ui">
      <div class="floor" aria-hidden="true"><span class="dir" id="f-dir">▲</span><span class="num" id="f-num">T</span><span class="lbl" id="f-lbl">Térreo</span></div>
      <button class="menu-btn" id="burger" type="button" aria-label="Abrir menu de andares" aria-expanded="false" aria-controls="lift-panel">MENU</button>
    </div>
    <?php else: ?>
    <a class="sub-back" href="<?= e(url('')) ?>">← Início</a>
    <?php endif; ?>
  </div>
</header>
<?php
}

function layout_footer(bool $home): void
{
    $h = $home ? '' : url('');
    ?>
<footer class="foot" id="foot">
  <div class="wrap">
    <span>Junior Chagas Síndico Profissional · CNPJ <?= e(setting('cnpj')) ?><br><?= e(setting('endereco')) ?> · <?= e(setting('endereco2')) ?> · WhatsApp <?= e(setting('whatsapp')) ?></span>
    <nav aria-label="Rodapé">
      <a href="<?= e($h) ?>#servicos">Serviços</a>
      <a href="<?= e($h) ?>#diagnostico">Diagnóstico</a>
      <a href="<?= e(url('guia')) ?>">Guia</a>
      <a href="<?= e(url('privacidade')) ?>">Privacidade</a>
      <a href="<?= e(setting('instagram')) ?>" target="_blank" rel="noopener">Instagram</a>
    </nav>
  </div>
</footer>
<a class="wa-float" id="wa-float" href="<?= e(wa_link()) ?>" target="_blank" rel="noopener" aria-label="Conversar no WhatsApp">
  <?= JC_WA_SVG ?>
  <span>WhatsApp</span>
</a>
<?php
}

/** Dados estruturados do negócio (Google). */
function jsonld_negocio(): array
{
    $wa = preg_replace('/\D+/', '', setting('whatsapp')) ?? '';
    return [
        '@context' => 'https://schema.org',
        '@type' => 'ProfessionalService',
        'name' => 'JC Síndico Profissional',
        'description' => 'Síndico profissional em Fortaleza desde 2014: gestão financeira, manutenção, assembleias e visitas semanais.',
        'url' => abs_url(''),
        'image' => abs_url('assets/img/og.jpg'),
        'logo' => abs_url('assets/img/logo-v-azul.svg'),
        'telephone' => '+55' . $wa,
        'taxID' => setting('cnpj'),
        'founder' => ['@type' => 'Person', 'name' => 'Junior Chagas', 'jobTitle' => 'Síndico profissional'],
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => setting('endereco'),
            'addressLocality' => 'Fortaleza',
            'addressRegion' => 'CE',
            'postalCode' => '60811-341',
            'addressCountry' => 'BR',
        ],
        'areaServed' => ['@type' => 'City', 'name' => 'Fortaleza'],
        'sameAs' => array_values(array_filter([setting('instagram'), setting('linkedin')])),
        'openingHours' => ['Mo-Fr 08:00-18:00', 'Sa 08:00-12:00'],
    ];
}

/** Cartão de artigo (home, lista do guia e "continue lendo"). Capa enviada pelo painel, ou um bloco da marca. */
function post_card(array $p, bool $resumo = true): string
{
    $capa = capa_url($p['capa'] ?? null) ?? (($pd = capa_padrao((string) $p['categoria'])) ? url($pd) : null);
    $cat = mb_strtoupper((string) $p['categoria']);
    $img = $capa
        ? '<span class="pcover"><img src="' . e($capa) . '" alt="" width="1200" height="630" loading="lazy" decoding="async"></span>'
        : '<span class="pcover ph" aria-hidden="true"><i>' . e($cat) . '</i></span>';
    return '<a class="post" href="' . e(url('guia/' . $p['slug'])) . '">' . $img
        . '<span class="meta">' . e($cat) . ' · ' . (int) $p['minutos'] . ' MIN</span>'
        . '<span class="pt">' . e($p['titulo']) . '</span>'
        . ($resumo && $p['resumo'] !== '' ? '<p>' . e($p['resumo']) . '</p>' : '')
        . '<span class="more">Ler artigo</span></a>';
}
