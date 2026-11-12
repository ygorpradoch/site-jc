<?php
/**
 * MODELO de configuração. NÃO coloque senhas neste arquivo do repositório.
 *
 * Na Hostinger, copie este conteúdo para um arquivo FORA da pasta pública:
 *   /home/SEU_USUARIO/domains/juniorsindico.pro/jc-config/config.php
 * (uma pasta acima de public_html). O site procura esse caminho sozinho.
 *
 * Para gerar valores aleatórios: https://www.random.org/strings/ ou, no terminal,
 *   php -r "echo bin2hex(random_bytes(32));"
 */
return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'u000000000_jcsite',     // nome do banco criado no hPanel
        'user' => 'u000000000_jcsite',     // usuário do banco
        'pass' => 'TROQUE-PELA-SENHA-DO-BANCO',
    ],

    // Endereço público do site, sem barra no final
    'site_url' => 'https://novo.juniorsindico.pro',
    // Deixe vazio quando o site estiver na raiz do (sub)domínio
    'base_path' => '',

    // E-mail criado no hPanel (ex.: site@juniorsindico.pro) usado como remetente dos avisos
    'mail_from' => 'site@juniorsindico.pro',

    // Chave secreta longa e aleatória (64 caracteres). Usada para anonimizar IPs.
    'app_secret' => 'TROQUE-POR-64-CARACTERES-ALEATORIOS',

    // Código exigido UMA vez para criar o primeiro administrador em /admin.
    // Depois de criar o acesso, pode apagar esta linha.
    'setup_token' => 'TROQUE-POR-UM-CODIGO-LONGO',

    // Mantenha false em produção (true mostra detalhes de erro)
    'debug' => false,

    // false enquanto estiver no subdomínio de teste: pede ao Google para não indexar
    'indexar' => false,
];
