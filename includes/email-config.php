<?php
/**
 * email-config.php
 *
 * Configuração e exemplo de uso do GmailMailer.
 *
 * IMPORTANTE:
 * - Nenhuma credencial fica escrita aqui.
 * - Os valores são lidos de $_ENV (Dotenv) ou de variáveis de ambiente (getenv).
 * - No Render: Dashboard -> seu serviço -> Environment -> Add Environment Variable
 *   Adicione:
 *     GMAIL_CLIENT_ID
 *     GMAIL_CLIENT_SECRET
 *     GMAIL_REFRESH_TOKEN
 *     GMAIL_SENDER_EMAIL   (ex: seu-email@gmail.com)
 *
 * - Em ambiente local (seu próprio computador), use um arquivo ".env"
 *   (que NÃO deve ir para o Git), carregado em includes/config.php
 *   pelo vlucas/phpdotenv.
 */

require_once __DIR__ . '/GmailMailer.php';

// --- Leitura das credenciais (NUNCA hardcoded) ---
$clientId     = $_ENV['GMAIL_CLIENT_ID']     ?? getenv('GMAIL_CLIENT_ID');
$clientSecret = $_ENV['GMAIL_CLIENT_SECRET'] ?? getenv('GMAIL_CLIENT_SECRET');
$refreshToken = $_ENV['GMAIL_REFRESH_TOKEN'] ?? getenv('GMAIL_REFRESH_TOKEN');
$senderEmail  = $_ENV['GMAIL_SENDER_EMAIL']  ?? getenv('GMAIL_SENDER_EMAIL');

// Guarda a mensagem de erro real da última tentativa de envio,
// para podermos exibi-la (ou logá-la) de forma mais útil do que
// um simples "true/false".
$ultimoErroEmail = null;

// Validação básica - evita erros confusos caso esqueça de configurar no Render
if (!$clientId || !$clientSecret || !$refreshToken || !$senderEmail) {
    $faltando = [];
    if (!$clientId)     $faltando[] = 'GMAIL_CLIENT_ID';
    if (!$clientSecret) $faltando[] = 'GMAIL_CLIENT_SECRET';
    if (!$refreshToken) $faltando[] = 'GMAIL_REFRESH_TOKEN';
    if (!$senderEmail)  $faltando[] = 'GMAIL_SENDER_EMAIL';

    $ultimoErroEmail = 'Variável(is) de ambiente ausente(s): ' . implode(', ', $faltando);
    error_log('ERRO: ' . $ultimoErroEmail);
}

$mailer = new GmailMailer(
    (string)$clientId,
    (string)$clientSecret,
    (string)$refreshToken,
    (string)$senderEmail
);

/**
 * Função utilitária para enviar e-mail em qualquer parte do seu sistema.
 *
 * A assinatura bate com o jeito que o admin-dashboard.php chama esta função:
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
    global $mailer, $ultimoErroEmail, $clientId, $clientSecret, $refreshToken, $senderEmail;

    // Se faltar alguma credencial, não tenta enviar.
    // A mensagem explicativa já está em $ultimoErroEmail (definida acima).
    if (!$clientId || !$clientSecret || !$refreshToken || !$senderEmail) {
        return false;
    }

    try {
        $mailer->send($destinatarioEmail, $assunto, $corpoHtml, 'Central de Estágios');
        $ultimoErroEmail = null;
        return true;
    } catch (Exception $e) {
        $ultimoErroEmail = $e->getMessage();
        error_log('Erro ao enviar e-mail: ' . $e->getMessage());
        return false;
    }
}

/**
 * Retorna a mensagem de erro real da última tentativa de envio de e-mail
 * (ou null, se o último envio deu certo / ainda não houve tentativa).
 */
function getUltimoErroEmail(): ?string
{
    global $ultimoErroEmail;
    return $ultimoErroEmail;
}

// --- Exemplo de teste manual (remova ou comente em produção) ---
// if (enviarEmail('destinatario_teste@example.com', 'Fulano de Tal', 'Teste Gmail API', '<h1>Funcionou!</h1><p>Este é um teste.</p>')) {
//     echo "E-mail enviado com sucesso!";
// } else {
//     echo "Falha ao enviar e-mail: " . getUltimoErroEmail();
// }
