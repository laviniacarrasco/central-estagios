<?php
/**
 * =============================================================================
 * NOTIFICACOES POR E-MAIL
 * -----------------------------------------------------------------------------
 * Usa o enviarEmail() de includes/email-config.php (Gmail API). O mailer so e
 * carregado na primeira vez que algum e-mail e enviado.
 *
 * Nenhuma funcao aqui derruba a pagina se o e-mail falhar: o erro vai para o
 * log do servidor e o fluxo segue (aprovar/rejeitar continua funcionando).
 *
 * Variaveis de ambiente usadas:
 *   GMAIL_CLIENT_ID, GMAIL_CLIENT_SECRET, GMAIL_REFRESH_TOKEN, GMAIL_SENDER_EMAIL
 *   APP_URL (opcional, recomendado) ex: https://central.fsa.br  -> links dos e-mails
 * =============================================================================
 */

function emH($v): string {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function appBaseUrl(): string {
    $env = $_ENV['APP_URL'] ?? getenv('APP_URL');
    if ($env) return rtrim((string) $env, '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir  = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}

/** Carrega email-config.php de dentro de uma funcao sem perder as variaveis globais */
function notificacoesCarregarMailer(): bool {
    if (function_exists('enviarEmail')) return true;
    global $mailer, $ultimoErroEmail, $clientId, $clientSecret, $refreshToken, $senderEmail;
    require_once __DIR__ . '/email-config.php';
    return function_exists('enviarEmail');
}

function notificacoesTemplate(string $titulo, string $corpoHtml, ?string $ctaTexto = null, ?string $ctaUrl = null): string {
    $botao = '';
    if ($ctaTexto && $ctaUrl) {
        $botao = '<p style="margin:26px 0 8px"><a href="' . emH($ctaUrl) . '" style="background:#4A9FCA;color:#ffffff;'
               . 'padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block">'
               . emH($ctaTexto) . '</a></p>';
    }
    return '<div style="background:#f3f6f9;padding:24px;font-family:Arial,sans-serif;color:#333">'
         . '<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden">'
         . '<div style="background:#4A9FCA;color:#ffffff;padding:18px 24px;font-size:13px;letter-spacing:.5px">CENTRAL DE ESTÁGIOS &middot; FUNDAÇÃO SANTO ANDRÉ</div>'
         . '<div style="padding:24px;font-size:14px;line-height:1.55">'
         . '<h2 style="margin:0 0 14px;font-size:20px;color:#1e293b">' . emH($titulo) . '</h2>'
         . $corpoHtml . $botao
         . '</div>'
         . '<div style="padding:14px 24px;background:#f8fafc;font-size:11px;color:#94a3b8">Mensagem automática. Não responda este e-mail.</div>'
         . '</div></div>';
}

/** Envio base. Retorna true/false e nunca lanca excecao. */
function notificarEmail(string $email, string $nome, string $assunto, string $titulo, string $corpoHtml, ?string $ctaTexto = null, ?string $ctaUrl = null): bool {
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    $assunto = trim(str_replace(["\r", "\n"], ' ', $assunto));
    try {
        if (!notificacoesCarregarMailer()) return false;
        return enviarEmail($email, $nome, $assunto, notificacoesTemplate($titulo, $corpoHtml, $ctaTexto, $ctaUrl));
    } catch (Throwable $e) {
        error_log('notificarEmail: ' . $e->getMessage());
        return false;
    }
}

function notificacoesEmailDoUsuario(int $id): array {
    foreach (loadData('usuarios') as $u) {
        if (($u['id'] ?? null) == $id) return [trim((string) ($u['email'] ?? '')), (string) ($u['nome'] ?? '')];
    }
    return ['', ''];
}

/* ------------------------------------------------------------------ */
/* Aluno: resultado da solicitacao de vaga (admin-jobs.php)            */
/* ------------------------------------------------------------------ */
function notificarSolicitacaoAluno(array $req, bool $aprovada, string $motivo = '', bool $bloqueado = false): void {
    $email = trim((string) ($req['studentEmail'] ?? ''));
    $nome  = (string) ($req['studentName'] ?? '');
    if ($email === '' && !empty($req['studentId'])) [$email, $nome] = notificacoesEmailDoUsuario((int) $req['studentId']);
    if ($email === '') return;

    $vaga = emH($req['title'] ?? '') . ' &mdash; ' . emH($req['company'] ?? '');
    $ola  = '<p>Olá, <strong>' . emH($nome ?: 'aluno(a)') . '</strong>,</p>';

    if ($aprovada) {
        notificarEmail($email, $nome, 'Sua vaga foi aprovada: ' . ($req['title'] ?? ''), 'Sua vaga foi aprovada',
            $ola . '<p>A vaga <strong>' . $vaga . '</strong> que você enviou foi aprovada e já está publicada para os alunos.</p>'
                 . '<p>Obrigado por contribuir com a comunidade!</p>',
            'Ver vagas', appBaseUrl() . '/jobs.php');
        return;
    }

    $extra = $bloqueado
        ? '<p>Por conta desse conteúdo, novos envios de vaga estão <strong>temporariamente suspensos</strong> pela coordenação. Em caso de dúvida, procure a Central de Estágios.</p>'
        : '<p>Você pode corrigir as informações e reenviar a solicitação.</p>';
    notificarEmail($email, $nome, 'Sua solicitação de vaga não foi aprovada', 'Solicitação não aprovada',
        $ola . '<p>A vaga <strong>' . $vaga . '</strong> não foi aprovada pela coordenação.</p>'
             . '<p style="background:#fef2f2;border-left:4px solid #ef4444;padding:10px 14px"><strong>Motivo:</strong> ' . emH($motivo !== '' ? $motivo : 'Não especificado pela coordenação.') . '</p>'
             . $extra,
        $bloqueado ? null : 'Corrigir e reenviar', $bloqueado ? null : appBaseUrl() . '/request-job.php');
}

/* ------------------------------------------------------------------ */
/* Egresso: resultado da vaga divulgada (admin-egressos.php)           */
/* ------------------------------------------------------------------ */
function notificarEgressoVaga(array $vaga, bool $aprovada): void {
    [$email, $nome] = notificacoesEmailDoUsuario((int) ($vaga['egressoId'] ?? 0));
    if ($email === '') return;

    $titulo = emH($vaga['title'] ?? '') . ' &mdash; ' . emH($vaga['company'] ?? '');
    $ola    = '<p>Olá, <strong>' . emH($nome ?: 'egresso(a)') . '</strong>,</p>';

    if ($aprovada) {
        $ate = !empty($vaga['expiresAt']) ? '<p>Ela ficará no mural até <strong>' . emH(date('d/m/Y', strtotime($vaga['expiresAt']))) . '</strong>. Se precisar de mais tempo, você pode renovar.</p>' : '';
        notificarEmail($email, $nome, 'Sua vaga foi publicada: ' . ($vaga['title'] ?? ''), 'Sua vaga foi publicada',
            $ola . '<p>A vaga <strong>' . $titulo . '</strong> foi aprovada e já aparece para os alunos da FSA.</p>' . $ate
                 . '<p>Obrigado por abrir portas para quem está começando!</p>',
            'Ver minhas divulgações', appBaseUrl() . '/egresso-publicar.php');
        return;
    }

    notificarEmail($email, $nome, 'Sua vaga precisa de ajustes', 'Sua vaga não foi aprovada',
        $ola . '<p>A vaga <strong>' . $titulo . '</strong> não foi aprovada.</p>'
             . '<p style="background:#fef2f2;border-left:4px solid #ef4444;padding:10px 14px"><strong>Motivo:</strong> ' . emH(($vaga['motivoRejeicao'] ?? '') !== '' ? $vaga['motivoRejeicao'] : 'Não especificado.') . '</p>'
             . '<p>Você pode corrigir e reenviar para nova análise.</p>',
        'Corrigir e reenviar', appBaseUrl() . '/egresso-publicar.php?editar=' . (int) ($vaga['id'] ?? 0));
}

/* ------------------------------------------------------------------ */
/* Admin de egressos: aviso de vaga nova aguardando analise            */
/* ------------------------------------------------------------------ */
function notificarAdminsVagaEgresso(array $vaga): void {
    foreach (loadData('usuarios') as $u) {
        if (empty($u['is_admin']) || ($u['tipo'] ?? '') !== 'admin_egressos') continue;
        $email = trim((string) ($u['email'] ?? ''));
        if ($email === '') continue;
        notificarEmail($email, (string) ($u['nome'] ?? ''), 'Nova vaga de egresso para analisar', 'Vaga aguardando análise',
            '<p>O egresso <strong>' . emH($vaga['egressoName'] ?? '') . '</strong> enviou uma vaga:</p>'
            . '<p><strong>' . emH($vaga['title'] ?? '') . '</strong> &mdash; ' . emH($vaga['company'] ?? '') . '</p>'
            . '<p>Ela só aparece para os alunos depois de aprovada.</p>',
            'Analisar agora', appBaseUrl() . '/admin-egressos.php?tab=vagas');
    }
}
