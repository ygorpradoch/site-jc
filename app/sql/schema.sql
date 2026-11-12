-- JC Síndico Profissional — estrutura do banco (MySQL 5.7+ / MariaDB 10.4+)
-- Importar UMA vez pelo phpMyAdmin da Hostinger, no banco criado para o site.
-- Depois importar seed.sql (conteúdo inicial).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome          VARCHAR(80)  NOT NULL,
  email         VARCHAR(160) NOT NULL,
  senha_hash    VARCHAR(255) NOT NULL,
  criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_login  DATETIME     NULL,
  UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  chave       VARCHAR(190) NOT NULL PRIMARY KEY,
  tentativas  INT UNSIGNED NOT NULL DEFAULT 0,
  expira_em   INT UNSIGNED NOT NULL,
  KEY idx_expira (expira_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracoes (
  chave  VARCHAR(60) NOT NULL PRIMARY KEY,
  valor  TEXT        NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contatos que chegam pelo site (proposta, agenda, diagnóstico)
CREATE TABLE IF NOT EXISTS leads (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  origem         ENUM('proposta','agenda','diagnostico') NOT NULL DEFAULT 'proposta',
  nome           VARCHAR(120) NOT NULL,
  whatsapp       VARCHAR(11)  NOT NULL,
  papel          VARCHAR(20)  NULL,
  condominio     VARCHAR(160) NULL,
  unidades       VARCHAR(20)  NULL,
  mensagem       TEXT         NULL,
  status         ENUM('novo','conversa','proposta','fechado','perdido') NOT NULL DEFAULT 'novo',
  notas          TEXT         NULL,
  consentimento  TINYINT(1)   NOT NULL DEFAULT 0,
  ip_hash        CHAR(64)     NULL,
  criado_em      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_status (status),
  KEY idx_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conversas marcadas pelo site. "ativo" = 1 enquanto pendente/confirmado; NULL quando cancelado.
-- A chave única impede dois agendamentos ativos no mesmo horário.
CREATE TABLE IF NOT EXISTS agendamentos (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id    INT UNSIGNED NULL,
  data       DATE         NOT NULL,
  hora       TIME         NOT NULL,
  modo       ENUM('video','presencial','escritorio') NOT NULL DEFAULT 'video',
  status     ENUM('pendente','confirmado','cancelado') NOT NULL DEFAULT 'pendente',
  ativo      TINYINT(1)   NULL DEFAULT 1,
  criado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_horario (data, hora, ativo),
  KEY idx_lead (lead_id),
  CONSTRAINT fk_ag_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dias ou horários bloqueados pelo Junior (hora NULL = dia inteiro)
CREATE TABLE IF NOT EXISTS bloqueios (
  id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  data    DATE        NOT NULL,
  hora    TIME        NULL,
  motivo  VARCHAR(120) NULL,
  KEY idx_data (data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Diagnósticos concluídos (anônimos; viram contato só se a pessoa enviar)
CREATE TABLE IF NOT EXISTS diagnosticos (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  respostas         VARCHAR(100) NOT NULL,          -- ex.: "ok,no,ns,ok,..."
  em_dia            TINYINT UNSIGNED NOT NULL,
  pendentes         TINYINT UNSIGNED NOT NULL,
  enviou_whatsapp   TINYINT(1) NOT NULL DEFAULT 0,
  enviou_formulario TINYINT(1) NOT NULL DEFAULT 0,
  criado_em         DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS condominios (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome        VARCHAR(120) NOT NULL,
  bairro      VARCHAR(80)  NULL,
  unidades    SMALLINT UNSIGNED NOT NULL,
  desde       DATE         NOT NULL,
  foto        VARCHAR(200) NULL,
  foto_linha  VARCHAR(200) NULL,
  exibir      TINYINT(1)   NOT NULL DEFAULT 1,
  criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS depoimentos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  texto       VARCHAR(600) NOT NULL,
  nome        VARCHAR(100) NOT NULL,
  papel       VARCHAR(80)  NULL,
  condominio  VARCHAR(120) NULL,
  status      ENUM('pendente','publicado','recusado') NOT NULL DEFAULT 'pendente',
  autorizado  TINYINT(1)   NOT NULL DEFAULT 0,
  origem      ENUM('site','painel') NOT NULL DEFAULT 'site',
  ordem       SMALLINT     NOT NULL DEFAULT 0,
  criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS artigos (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug           VARCHAR(100) NOT NULL,
  titulo         VARCHAR(160) NOT NULL,
  categoria      VARCHAR(40)  NOT NULL DEFAULT 'GESTÃO',
  minutos        TINYINT UNSIGNED NOT NULL DEFAULT 3,
  resumo         VARCHAR(300) NOT NULL DEFAULT '',
  corpo          MEDIUMTEXT   NOT NULL,
  status         ENUM('rascunho','publicado') NOT NULL DEFAULT 'rascunho',
  leituras       INT UNSIGNED NOT NULL DEFAULT 0,
  publicado_em   DATETIME     NULL,
  atualizado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_slug (slug),
  KEY idx_status (status, publicado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
