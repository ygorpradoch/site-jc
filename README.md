# Site JC Síndico Profissional

Site de divulgação + painel administrativo do Junior Chagas, síndico profissional em Fortaleza.

- **Front:** HTML/CSS/JS puro, sem etapa de build. GSAP 3.12.5 servido localmente (`assets/js/vendor`).
- **Back:** PHP 8.1+ com PDO (sempre com prepared statements), MySQL/MariaDB.
- **Hospedagem:** Hostinger Premium (compartilhada), deploy pela integração Git do hPanel.
- Nada de Node em produção, nenhum processo permanente e nenhum segredo no repositório ou no JavaScript.

## Estrutura

```
index.php           Página inicial (dados do banco renderizados no servidor)
guia.php            /guia e /guia/<slug>  (artigos com URL própria, SEO)
privacidade.php     /privacidade
depoimento.php      /depoimento  (link que o Junior envia aos clientes)
404.php, sitemap.php (/sitemap.xml), robots.php (/robots.txt)
api/                Endpoints públicos: lead, agenda, diagnostico, depoimento
admin/              Painel: index.php (login), api.php, previa.php, admin.js, admin.css
app/                Código privado (bloqueado na web): bootstrap, banco, auth, layout, SQL
assets/             CSS, JS, imagens, vídeos
uploads/            Fotos enviadas pelo painel (só imagens; scripts não executam)
```

## Segurança (resumo)

- Configuração com senhas **fora da pasta pública** (`jc-config/config.php`, uma pasta acima de `public_html`).
- `app/`, `.git`, `*.sql`, `*.md` bloqueados pelo `.htaccess`.
- Formulários públicos: checagem de origem, campo-isca contra robôs, limite de envios por IP (IP guardado só como hash).
- Painel: senha com `password_hash`, **verificação em 2 etapas obrigatória** (aplicativo autenticador), bloqueio de 15 min após 5 erros, sessão com cookie `HttpOnly`/`Secure`/`SameSite=Lax`, expira em 2 h, token CSRF em toda gravação. Detalhes em "Acesso ao painel" abaixo.
- Uploads: tipo real verificado (finfo + GD), tamanho limitado, imagem **recodificada** (remove metadados e conteúdo embutido), nome aleatório, pasta sem execução de scripts.
- Cabeçalhos: CSP, HSTS, `X-Frame-Options`, `nosniff`, `Referrer-Policy`.
- Erros nunca mostram detalhes ao visitante (vão para o log do PHP).

---

## Como publicar na Hostinger

> Regra: testar primeiro em subdomínio. **Não substituir o WordPress** de juniorsindico.pro antes de validar tudo e fazer backup.

### 1. Subdomínio de teste
1. hPanel → **Domínios → Subdomínios** → criar `novo.juniorsindico.pro`.
2. Anote a pasta criada (ex.: `public_html/novo`). Se ela vier com arquivos padrão, apague-os: o deploy por Git exige a pasta vazia.
3. hPanel → **Segurança → SSL**: confirme o certificado ativo para o subdomínio.
4. hPanel → **Avançado → Configuração do PHP**: PHP **8.1 ou superior**. Extensões `pdo_mysql`, `gd`, `fileinfo` ativas (já vêm por padrão).

### 2. Banco de dados
1. hPanel → **Bancos de dados → MySQL**: criar banco, usuário e senha forte.
2. **phpMyAdmin** → selecionar o banco → **Importar** `app/sql/schema.sql` e depois `app/sql/seed.sql`.

### 3. Configuração secreta (fora do Git)
1. hPanel → **Gerenciador de arquivos** → abra a pasta **acima** de `public_html` (ex.: `/home/u123456789/domains/juniorsindico.pro/`).
2. Crie a pasta `jc-config` e, dentro dela, o arquivo `config.php` com o conteúdo de `app/config.example.php`.
3. Preencha: dados do banco, `site_url` (`https://novo.juniorsindico.pro`), `mail_from`, `app_secret` e `setup_token` (textos longos e aleatórios). Deixe `debug => false` e `indexar => false`.
   **`app_secret` precisa ter 32+ caracteres aleatórios (ex.: 64) e NÃO pode ser trocada depois:** ela cifra os segredos da verificação em 2 etapas. Se for trocada, todos terão de reconfigurar o 2FA (veja "Perdi o acesso" abaixo).
4. hPanel → **E-mails**: crie a caixa usada em `mail_from` (ex.: `site@juniorsindico.pro`). Os avisos de novos contatos saem por ela.

### 4. Repositório e deploy
1. Crie um repositório **privado** no GitHub e envie este projeto (`git push`).
2. hPanel → **Avançado → Git** → informe a URL SSH do repositório, branch `main` e o diretório do subdomínio (ex.: `novo`).
3. Repositório privado: copie a **chave SSH** mostrada no hPanel e adicione em GitHub → Settings → **Deploy keys** (somente leitura).
4. Clique em **Deploy**. Para publicar a cada `git push`, ative o **Auto Deployment** e cadastre o webhook mostrado no hPanel em GitHub → Settings → Webhooks.

### 5. Primeiro acesso ao painel
1. Abra `https://novo.juniorsindico.pro/admin/`.
2. Informe o `setup_token`, nome, e-mail e senha (mín. 8 caracteres, com letras, números e caractere especial).
3. O painel exige configurar a **verificação em 2 etapas** antes de entrar: instale Google Authenticator, Microsoft Authenticator ou Authy, leia o QR code e digite o código. Em seguida **guarde os 10 códigos de recuperação** (aparecem uma única vez).
4. Depois de criado, apague a linha `setup_token` do `config.php` (opcional, a tela de criação some sozinha).
5. Em **Configurações**, confira WhatsApp, e-mail que recebe os avisos, endereço e redes.
6. Em **Agenda → Horários de atendimento**, ajuste os dias, períodos e a duração das conversas.

### 6. Checklist de validação no subdomínio
- [ ] Página inicial no celular (390 px) e no computador; animações e menu de andares.
- [ ] Formulário de proposta: chega no painel e o e-mail de aviso chega.
- [ ] Agendar conversa: horário some do site depois de marcado; confirmar e cancelar no painel.
- [ ] Diagnóstico: resultado, envio por WhatsApp e "Usar no formulário".
- [ ] Condomínios: adicionar com foto (gera recorte e desenho em linha), ocultar, editar, remover.
- [ ] Depoimentos: enviar por `/depoimento`, aprovar no painel, ver a seção aparecer.
- [ ] Guia: criar rascunho, pré-visualizar, publicar, abrir `/guia/<endereço>`.
- [ ] Blog: enviar capa, inserir foto e vídeo MP4 pelo painel, colar link do YouTube, conferir o botão de compartilhar e o `og:image` (capa) ao colar o link do artigo no WhatsApp.
- [ ] hPanel → PHP → Opções: `upload_max_filesize` e `post_max_size` em pelo menos 64M (vídeos MP4 aceitam até 50 MB). Se o limite for menor, o painel avisa e sugere o link do YouTube.
- [ ] Painel: login com 2 etapas; convidar uma segunda pessoa (o link chega por e-mail ou é copiado) e ver que ela também precisa ativar o 2FA.
- [ ] Registros: tentar entrar com senha errada e conferir o evento, o IP e o selo de alertas no menu.
- [ ] Agenda: mudar a duração/horários e ver o site público usar a nova grade.
- [ ] `/admin/` bloqueia após 5 senhas erradas; `/app/config.example.php` e `/app/sql/schema.sql` devolvem 403.
- [ ] Teste de cabeçalhos em https://securityheaders.com e velocidade no PageSpeed Insights.

### 7. Trocar o WordPress pelo site novo (só depois de validado)
1. **Backup completo** do WordPress: hPanel → Arquivos → Backups (arquivos + banco) e baixe uma cópia.
2. No Google Search Console, exporte as páginas antigas com visitas e ative/ajuste os redirecionamentos 301 comentados no `.htaccess`.
3. Mova o WordPress para uma pasta de backup fora de `public_html` (não apague).
4. Aponte o deploy do Git para `public_html` (pasta vazia) e faça o deploy.
5. No `config.php`: `site_url => 'https://juniorsindico.pro'` e `indexar => true`.
6. Envie `https://juniorsindico.pro/sitemap.xml` no Search Console.

## Rodar localmente (opcional)

Requer PHP 8.1+ e MySQL/MariaDB. Crie `app/config.local.php` (ignorado pelo Git) a partir de `app/config.example.php` e rode `php -S localhost:8000` na raiz. Sem o `.htaccess`, use `guia.php?slug=...` no lugar de `/guia/...`.

## Conteúdo a confirmar com o Junior

- Bio e sobrenome do Felipe; títulos definitivos e autorização de imagem dos 4 vídeos.
- Bairro de cada condomínio (editável no painel) e autorização para nome e foto; foto do Ed. Pacific Hills.
- Prazos do "Como funciona" e prazo de aviso prévio do contrato.
- Revisão técnica e jurídica dos artigos, das orientações do diagnóstico e da Política de Privacidade.
- Seguro de responsabilidade civil profissional vigente (citado no site).
- Instagram oficial (@jc.sindicoprofissional) e razão social exata.
- Foto original em alta resolução da sessão do Junior (a abertura usa um recorte ampliado).
- Depoimentos reais: a seção só aparece quando houver pelo menos um aprovado.

## Blog (Guia do condomínio)

- Editor do painel com botões (subtítulo, negrito, itálico, lista, citação, link, linha, foto, vídeo). O texto é guardado em formato simples e convertido em HTML pelo servidor; todo conteúdo é escapado.
- Fotos: o navegador reduz antes de enviar e o servidor recodifica para WebP (máx. 1600 px). Capa: recorte central 1200×630, usada nos cartões, no topo do artigo e na prévia de compartilhamento.
- Vídeos: link do YouTube (embed `youtube-nocookie`, único domínio liberado em `frame-src` no `.htaccess`) ou arquivo MP4 de até 50 MB em `uploads/artigos/` (checagem de tipo real e assinatura `ftyp`; o servidor não recodifica vídeo).
- Marcações no texto: `## Subtítulo`, `### Menor`, `- lista`, `1. lista`, `> citação`, `---`, `**negrito**`, `*itálico*`, `[texto](https://link)`, `[[foto:uploads/artigos/…|legenda]]`, `[[video:link-ou-arquivo|legenda]]`.
- Lista `/guia` com filtro por categoria (`?categoria=`) e paginação (9 por página). Cada artigo mostra botões de compartilhar e artigos relacionados (mesma categoria primeiro).
- Excluir um artigo apaga do disco a capa e as fotos/vídeos que só ele usava.

## Acesso ao painel (usuários, 2 etapas e registros)

- **Perfil único: master.** Todo usuário tem acesso total e pode convidar, desativar, remover e redefinir o 2FA de outros (nunca de si mesmo sem senha + código). Ninguém consegue desativar ou remover a própria conta.
- **Entrada em 2 etapas:** e-mail + senha, depois o código de 6 dígitos do aplicativo (TOTP, RFC 6238). Cada código só vale uma vez; 5 erros bloqueiam por alguns minutos. Quem ainda não configurou o 2FA é obrigado a configurar antes de entrar.
- **Convite:** em Usuários → Convidar, o sistema gera um link válido por 48 h (e tenta enviar por e-mail usando `mail_from`; se o e-mail não sair, copie o link e mande por WhatsApp). A pessoa cria a senha e ativa o 2FA. O link só pode ser usado uma vez e é guardado apenas como hash.
- **Redefinir senha de outro usuário:** Usuários → "Redefinir senha" gera um novo link. **Trocou de celular / perdeu o aplicativo:** use um código de recuperação para entrar e depois "Reconfigurar 2 etapas" em Usuários → Minha conta; ou peça a outro master para usar "Redefinir 2 etapas".
- **Perdi o acesso de todos os masters (último recurso):** no phpMyAdmin, execute `UPDATE usuarios SET totp_segredo = NULL, totp_ativo = 0, totp_passo = NULL, codigos_recuperacao = NULL WHERE email = 'seu@email.com';` e entre de novo com a senha (o painel pedirá para configurar o 2FA). Se a senha também foi perdida, apague o usuário (`DELETE FROM usuarios WHERE email = ...`): a tela de primeiro acesso volta a pedir o `setup_token` do `config.php`.
- **Registros** (menu Administração): acessos e tentativas de acesso, ações feitas no painel, eventos de segurança (robôs, origem inválida, limite de envios) e envios do site. Guardam IP e navegador por **180 dias** (Marco Civil, art. 15) e nunca senhas, códigos ou textos de mensagens. A Política de Privacidade já cita isso (pendente de revisão jurídica).
- **Banco já existente** (criado com versão anterior do schema): importe `app/sql/migracao-02-usuarios-2fa-logs.sql` uma vez, depois de fazer backup. O primeiro usuário passará a ser obrigado a ativar o 2FA no próximo login.

## Agenda

Em **Agenda → Horários de atendimento** o master define, para cada dia da semana (inclusive sábado e domingo), se atende de manhã e/ou à tarde e em que horário, a duração de cada conversa (15 a 90 min), o aviso mínimo e quantos dias à frente o site mostra. Conversas já marcadas continuam valendo se os horários mudarem; bloqueios de data/horário continuam funcionando por cima da grade.
