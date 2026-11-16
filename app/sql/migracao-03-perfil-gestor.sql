-- Migração 03: perfis de usuário (master e gestor).
-- Use SOMENTE em um banco que já foi criado com a versão anterior do schema.sql.
-- (Instalação nova: importe apenas schema.sql, que já inclui tudo isto.)
-- Faça backup do banco antes (phpMyAdmin → Exportar).

ALTER TABLE usuarios
  ADD COLUMN perfil ENUM('master','gestor') NOT NULL DEFAULT 'gestor' AFTER senha_hash;

-- Quem já existe hoje é master (era o único perfil)
UPDATE usuarios SET perfil = 'master';
