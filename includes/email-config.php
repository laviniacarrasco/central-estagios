<?php
/**
 * email-config.php
 *
 * Configuração e exemplo de uso do GmailMailer.
 *
 * IMPORTANTE:
 * - Nenhuma credencial fica escrita aqui.
 * - Os valores são lidos de variáveis de ambiente (getenv).
 * - No Render: Dashboard -> seu serviço -> Environment -> Add Environment Variable
 *   Adicione:
 *     GMAIL_CLIENT_ID
 *     GMAIL_CLIENT_SECRET
 *     GMAIL_REFRESH_TOKEN
 *     GMAIL_SENDER_EMAIL   (ex: laviniacarrasco29@gmail.com)
 *
 * - Em ambiente local (seu próprio computador), você pode usar um arquivo
 *   ".env" (que NÃO deve ir para o Git) + uma lib como vlucas/phpdotenv,
 *   ou simplesmente exportar as variáveis no terminal antes de testar.
 */

require_once __DIR__ . '/GmailMailer.php';

// --- Leitura das credenciais (NUNCA hardcoded) ---
$clientId     = getenv('GMAIL_CLIENT_ID');
$clientSecret = getenv('GMAIL_CLIENT_SECRET');
$refreshToken = getenv('GMAIL_REFRESH_TOKEN');
$senderEmail  = getenv('GMAIL_SENDER_EMAIL');

// Validação básica - evita erros confusos caso esqueça de configurar no Render
if (!$clientId || !$clientSecret || !$refreshToken || !$senderEmail) {
    // Em produção, é melhor logar isso em vez de exibir na tela
    error_log('ERRO: Variáveis de ambiente do Gmail não configuradas corretamente.');
    // Você pode optar por lançar uma exceção aqui, dependendo do fluxo do seu app:
    // throw new Exception('Configuração de e-mail ausente.');
}

$mailer = new GmailMailer($clientId, $clientSecret, $refreshToken, $senderEmail);

/**
 * Função utilitária para enviar e-mail em qualquer parte do seu sistema.
 *
 * ATENÇÃO: a assinatura abaixo foi ajustada para bater exatamente com o
 * jeito que o admin-dashboard.php já chama esta função:
 *
 *   enviarEmail($alunoAlvo['email'], $nomeAluno, $assunto, $corpo);
 *
 * Ou seja: (email do destinatário, nome do destinatário, assunto, corpo HTML)
 *
 * @param string $destinatarioEmail  E-mail de quem vai receber
 * @param string $destinatarioNome   Nome de quem vai receber (usado apenas
 *                                   para possível personalização futura;
 *                                   hoje não é obrigatório para o envio)
 * @param string $assunto            Assunto do e-mail
 * @param string $corpoHtml          Corpo do e-mail em HTML
 *
 * @return bool true se enviado com sucesso, false se falhou
 */
function enviarEmail(string $destinatarioEmail, string $destinatarioNome, string $assunto, string $corpoHtml): bool
{
    global $mailer;

    try {
        $mailer->send($destinatarioEmail, $assunto, $corpoHtml, 'Central de Estágios');
        return true;
    } catch (Exception $e) {
        error_log('Erro ao enviar e-mail: ' . $e->getMessage());
        return false;
    }
}

// --- Exemplo de teste manual (remova ou comente em produção) ---
// if (enviarEmail('destinatario_teste@example.com', 'Fulano de Tal', 'Teste Gmail API', '<h1>Funcionou!</h1><p>Este é um teste.</p>')) {
//     echo "E-mail enviado com sucesso!";
// } else {
//     echo "Falha ao enviar e-mail. Verifique os logs.";
// }
