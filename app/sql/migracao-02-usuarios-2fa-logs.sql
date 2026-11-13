-- Migração 02: usuários com convite e 2 etapas (TOTP) + registros de atividade.
-- Use SOMENTE em um banco que já foi criado com a versão anterior do schema.sql.
-- (Instalação nova: importe apenas schema.sql, que já inclui tudo isto.)
-- Faça backup do banco antes (phpMyAdmin → Exportar).

SET NAMES utf8mb4;

ALTER TABLE usuarios
  ADD COLUMN ativo               TINYINT(1)   NOT NULL DEFAULT 1        AFTER senha_hash,
  ADD COLUMN totp_segredo        VARCHAR(255) NULL                      AFTER ativo,
  ADD COLUMN totp_ativo          TINYINT(1)   NOT NULL DEFAULT 0        AFTER totp_segredo,
  ADD COLUMN totp_passo          BIGINT       NULL                      AFTER totp_ativo,
  ADD COLUMN codigos_recuperacao TEXT         NULL                      AFTER totp_passo,
  ADD COLUMN convite_hash        CHAR(64)     NULL                      AFTER codigos_recuperacao,
  ADD COLUMN convite_expira      DATETIME     NULL                      AFTER convite_hash,
  ADD COLUMN criado_por          INT UNSIGNED NULL                      AFTER convite_expira,
  ADD KEY idx_convite (convite_hash);

CREATE TABLE IF NOT EXISTS logs (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  categoria     ENUM('acesso','seguranca','painel','site') NOT NULL,
  evento        VARCHAR(40)  NOT NULL,
  nivel         ENUM('info','aviso','alerta') NOT NULL DEFAULT 'info',
  usuario_id    INT UNSIGNED NULL,
  usuario_nome  VARCHAR(80)  NULL,
  email         VARCHAR(160) NULL,
  ip            VARCHAR(45)  NOT NULL DEFAULT '',
  agente        VARCHAR(200) NULL,
  detalhe       VARCHAR(500) NULL,
  KEY idx_criado (criado_em),
  KEY idx_cat (categoria, criado_em),
  KEY idx_nivel (nivel, criado_em),
  KEY idx_ip (ip, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A agenda agora é configurada pelo painel (chave "agenda_config"); as chaves antigas não são mais usadas.
DELETE FROM configuracoes WHERE chave IN ('agenda_manha', 'agenda_tarde', 'agenda_sabado');
