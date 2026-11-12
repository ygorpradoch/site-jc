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
- Painel: senha com `password_hash`, bloqueio de 15 min após 5 erros, sessão com cookie `HttpOnly`/`Secure`/`SameSite=Strict`, expira em 2 h, token CSRF em toda gravação.
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
4. hPanel → **E-mails**: crie a caixa usada em `mail_from` (ex.: `site@juniorsindico.pro`). Os avisos de novos contatos saem por ela.

### 4. Repositório e deploy
1. Crie um repositório **privado** no GitHub e envie este projeto (`git push`).
2. hPanel → **Avançado → Git** → informe a URL SSH do repositório, branch `main` e o diretório do subdomínio (ex.: `novo`).
3. Repositório privado: copie a **chave SSH** mostrada no hPanel e adicione em GitHub → Settings → **Deploy keys** (somente leitura).
4. Clique em **Deploy**. Para publicar a cada `git push`, ative o **Auto Deployment** e cadastre o webhook mostrado no hPanel em GitHub → Settings → Webhooks.

### 5. Primeiro acesso ao painel
1. Abra `https://novo.juniorsindico.pro/admin/`.
2. Informe o `setup_token`, nome, e-mail e senha (mín. 10 caracteres, letras e números).
3. Depois de criado, apague a linha `setup_token` do `config.php` (opcional, a tela de criação some sozinha).
4. Em **Configurações**, confira WhatsApp, e-mail que recebe os avisos, endereço e redes.

### 6. Checklist de validação no subdomínio
- [ ] Página inicial no celular (390 px) e no computador; animações e menu de andares.
- [ ] Formulário de proposta: chega no painel e o e-mail de aviso chega.
- [ ] Agendar conversa: horário some do site depois de marcado; confirmar e cancelar no painel.
- [ ] Diagnóstico: resultado, envio por WhatsApp e "Usar no formulário".
- [ ] Condomínios: adicionar com foto (gera recorte e desenho em linha), ocultar, editar, remover.
- [ ] Depoimentos: enviar por `/depoimento`, aprovar no painel, ver a seção aparecer.
- [ ] Guia: criar rascunho, pré-visualizar, publicar, abrir `/guia/<endereço>`.
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
