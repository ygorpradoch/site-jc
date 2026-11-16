<?php
defined('JC') || exit;

/**
 * Registros de atividade ("logs"): acessos ao painel, tentativas suspeitas, ações do painel e envios do site.
 * Guardamos o IP e o navegador (para investigar tentativas de invasão) por 180 dias, prazo mínimo de guarda
 * de registros de acesso do Marco Civil da Internet (art. 15). Nunca registramos senhas, códigos de 2 etapas,
 * tokens nem o conteúdo de mensagens: só ids e rótulos.
 */

const JC_LOG_RETENCAO_DIAS = 180;

/** evento => [categoria, nível, rótulo em português] */
const JC_LOG_EVENTOS = [
    // acessos
    'primeiro_acesso' => ['acesso', 'info', 'Criou o primeiro acesso ao painel'],
    'login_ok' => ['acesso', 'info', 'Entrou no painel'],
    'login_falha' => ['acesso', 'aviso', 'Tentativa de login falhou'],
    'login_bloqueado' => ['acesso', 'alerta', 'Login bloqueado por excesso de tentativas'],
    '2fa_falha' => ['acesso', 'aviso', 'Código de verificação incorreto'],
    '2fa_bloqueado' => ['acesso', 'alerta', 'Verificação em 2 etapas bloqueada'],
    '2fa_configurado' => ['acesso', 'info', 'Ativou a verificação em 2 etapas'],
    'codigo_recuperacao_usado' => ['acesso', 'aviso', 'Entrou com um código de recuperação'],
    'logout' => ['acesso', 'info', 'Saiu do painel'],
    'senha_alterada' => ['acesso', 'info', 'Trocou a senha'],
    'convite_aceito' => ['acesso', 'info', 'Aceitou o convite e criou a senha'],
    // segurança
    'convite_invalido' => ['seguranca', 'aviso', 'Link de convite inválido ou expirado'],
    'setup_falha' => ['seguranca', 'alerta', 'Código de instalação incorreto'],
    'api_sem_sessao' => ['seguranca', 'aviso', 'Acesso aos dados do painel sem login'],
    'csrf_invalido' => ['seguranca', 'alerta', 'Pedido sem token de segurança'],
    'origem_invalida' => ['seguranca', 'aviso', 'Formulário enviado de outro site'],
    'honeypot' => ['seguranca', 'aviso', 'Robô detectado em formulário'],
    'limite_excedido' => ['seguranca', 'aviso', 'Limite de envios excedido'],
    'upload_recusado' => ['seguranca', 'aviso', 'Arquivo recusado no envio'],
    'logs_exportados' => ['painel', 'aviso', 'Exportou os registros de atividade'],
    // ações no painel
    'lead_status' => ['painel', 'info', 'Atualizou um contato'],
    'lead_excluido' => ['painel', 'aviso', 'Excluiu um contato'],
    'leads_exportados' => ['painel', 'aviso', 'Exportou a lista de contatos'],
    'agendamento_status' => ['painel', 'info', 'Mudou o status de uma conversa agendada'],
    'agenda_config' => ['painel', 'info', 'Alterou os horários de atendimento'],
    'bloqueio_add' => ['painel', 'info', 'Bloqueou data ou horário na agenda'],
    'bloqueio_del' => ['painel', 'info', 'Removeu um bloqueio da agenda'],
    'condo_salvo' => ['painel', 'info', 'Salvou um condomínio'],
    'condo_exibir' => ['painel', 'info', 'Mostrou ou ocultou um condomínio'],
    'condo_excluido' => ['painel', 'aviso', 'Removeu um condomínio'],
    'depo_status' => ['painel', 'info', 'Mudou a situação de um depoimento'],
    'depo_salvo' => ['painel', 'info', 'Salvou um depoimento'],
    'depo_excluido' => ['painel', 'aviso', 'Excluiu um depoimento'],
    'artigo_salvo' => ['painel', 'info', 'Salvou um artigo'],
    'artigo_excluido' => ['painel', 'aviso', 'Excluiu um artigo'],
    'midia_enviada' => ['painel', 'info', 'Enviou foto ou vídeo'],
    'config_salva' => ['painel', 'info', 'Alterou as configurações do site'],
    'usuario_convidado' => ['painel', 'aviso', 'Convidou uma pessoa para o painel'],
    'usuario_convite_novo' => ['painel', 'aviso', 'Gerou novo link de convite ou de redefinição de senha'],
    'usuario_status' => ['painel', 'aviso', 'Ativou ou desativou um usuário'],
    'usuario_perfil' => ['painel', 'alerta', 'Mudou o perfil de um usuário'],
    'acao_negada' => ['seguranca', 'aviso', 'Ação negada pelo perfil do usuário'],
    'usuario_excluido' => ['painel', 'alerta', 'Removeu um usuário'],
    'usuario_2fa_reset' => ['painel', 'alerta', 'Redefiniu a verificação em 2 etapas de um usuário'],
    'codigos_novos' => ['painel', 'aviso', 'Gerou novos códigos de recuperação'],
    // envios do site (visitantes)
    'proposta_enviada' => ['site', 'info', 'Pedido de proposta recebido'],
    'agenda_pedida' => ['site', 'info', 'Conversa agendada pelo site'],
    'diagnostico_feito' => ['site', 'info', 'Diagnóstico concluído'],
    'depoimento_enviado' => ['site', 'info', 'Depoimento enviado pelo site'],
];

/** Eventos que qualquer visitante (sem login) consegue provocar: limitados por IP para não encher o banco. */
const JC_LOG_ANONIMOS = ['api_sem_sessao', 'csrf_invalido', 'login_bloqueado', 'convite_invalido', 'origem_invalida', 'honeypot', 'limite_excedido', 'login_falha', '2fa_falha', 'upload_recusado', 'setup_falha'];

/** Texto seguro para a coluna: UTF-8 válido (bytes inválidos no navegador/IP não derrubam o INSERT) e tamanho limitado. */
function log_txt($s, int $max): string
{
    $s = (string) $s;
    $s = function_exists('iconv') ? (string) @iconv('UTF-8', 'UTF-8//IGNORE', $s) : $s;
    return mb_substr(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s) ?? '', 0, $max);
}

/**
 * Registra um evento. Nunca quebra a página: se o banco falhar, só escreve no log de erros do PHP.
 * $o: usuario (['id','nome']), email (tentativa de login).
 */
function log_evento(string $evento, ?string $detalhe = null, array $o = []): void
{
    try {
        [$cat, $nivel] = JC_LOG_EVENTOS[$evento] ?? ['painel', 'info'];
        // Até 40 por minuto por IP e tipo de evento anônimo; o excesso é descartado (evita inundar a tabela)
        if (in_array($evento, JC_LOG_ANONIMOS, true) && !rate_contar('log:' . $evento, 40, 60)) {
            return;
        }
        $u = $o['usuario'] ?? ($GLOBALS['JC_LOG_USER'] ?? null);
        $agente = log_txt($_SERVER['HTTP_USER_AGENT'] ?? '', 200);
        db_q(
            'INSERT INTO logs (categoria, evento, nivel, usuario_id, usuario_nome, email, ip, agente, detalhe) VALUES (?,?,?,?,?,?,?,?,?)',
            [
                $cat, $evento, $nivel,
                $u['id'] ?? null, isset($u['nome']) ? log_txt($u['nome'], 80) : null,
                isset($o['email']) ? log_txt($o['email'], 160) : (isset($u['email']) ? log_txt($u['email'], 160) : null),
                log_txt(client_ip(), 45),
                $agente !== '' ? $agente : null,
                $detalhe !== null ? log_txt($detalhe, 500) : null,
            ]
        );
        if (random_int(1, 100) === 1) { // limpeza ocasional dos registros antigos
            db_q('DELETE FROM logs WHERE criado_em < NOW() - INTERVAL ' . JC_LOG_RETENCAO_DIAS . ' DAY');
        }
    } catch (Throwable $e) {
        error_log('[JC] log_evento: ' . $e->getMessage());
    }
}
