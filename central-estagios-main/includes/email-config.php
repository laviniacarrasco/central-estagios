    <?php
/**
 * ============================================================
 * CONFIGURACAO DE ENVIO DE E-MAIL (GMAIL SMTP + PHPMAILER)
 * ------------------------------------------------------------
 * Preencha os 3 valores abaixo com os dados da conta Gmail que
 * vai ser usada para enviar os avisos automaticos aos alunos.
 *
 * IMPORTANTE: GMAIL_APP_PASSWORD NAO e a senha normal do Gmail.
 * E uma "senha de app" de 16 caracteres gerada em:
 * Conta Google > Seguranca > Verificacao em 2 etapas > Senhas de app
 * ============================================================
 */
define('GMAIL_USER', 'coloque-aqui@gmail.com');           // <-- SEU E-MAIL GMAIL
define('GMAIL_APP_PASSWORD', 'xxxx xxxx xxxx xxxx');       // <-- SENHA DE APP (16 caracteres)
define('GMAIL_NOME_REMETENTE', 'Central de Estágios FSA'); // Nome que aparece para o aluno

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Envia um e-mail via Gmail SMTP.
 * Retorna true em caso de sucesso, ou false em caso de erro.
 */
function enviarEmail($destinatarioEmail, $destinatarioNome, $assunto, $corpoHtml) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_USER;
        $mail->Password   = GMAIL_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(GMAIL_USER, GMAIL_NOME_REMETENTE);
        $mail->addAddress($destinatarioEmail, $destinatarioNome);

        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body    = $corpoHtml;
        $mail->AltBody = strip_tags($corpoHtml);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Erro ao enviar e-mail: ' . $mail->ErrorInfo);
        return false;
    }
}
