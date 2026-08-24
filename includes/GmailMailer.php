<?php
/**
 * GmailMailer.php
 *
 * Classe responsável por enviar e-mails usando a Gmail API (OAuth2),
 * sem precisar de senha de app nem SMTP tradicional.
 *
 * Requer apenas cURL (nativo do PHP) - não precisa instalar nenhuma lib externa.
 *
 * Credenciais NUNCA devem ficar escritas neste arquivo.
 * Elas vêm de variáveis de ambiente (configuradas no Render, .env, etc.)
 */

class GmailMailer
{
    private string $clientId;
    private string $clientSecret;
    private string $refreshToken;
    private string $senderEmail;

    public function __construct(string $clientId, string $clientSecret, string $refreshToken, string $senderEmail)
    {
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;
        $this->refreshToken = $refreshToken;
        $this->senderEmail  = $senderEmail;
    }

    /**
     * Aplica o certificado CA local (cacert.pem) na conexão cURL, se o
     * arquivo existir. Isso resolve o erro comum no Windows:
     * "SSL certificate OpenSSL verify result: unable to get local issuer
     * certificate (20)".
     *
     * Em produção (Render/Linux) o arquivo normalmente não é necessário,
     * pois o sistema já tem os certificados configurados — mas não tem
     * problema manter o arquivo lá, ele só é usado se existir.
     */
    private function applyCaBundle($ch): void
    {
        $caFile = __DIR__ . '/cacert.pem';
        if (file_exists($caFile)) {
            curl_setopt($ch, CURLOPT_CAINFO, $caFile);
        }
    }

    /**
     * Troca o refresh token por um access token novo (eles expiram em ~1h).
     */
    private function getAccessToken(): string
    {
        $url = 'https://oauth2.googleapis.com/token';

        $postFields = http_build_query([
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
            'grant_type'    => 'refresh_token',
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $this->applyCaBundle($ch);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new Exception("Erro de conexão ao obter access token: {$curlErr}");
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200 || empty($data['access_token'])) {
            $errMsg = $data['error_description'] ?? $response;
            throw new Exception("Falha ao obter access token (HTTP {$httpCode}): {$errMsg}");
        }

        return $data['access_token'];
    }

    /**
     * Monta o e-mail no formato MIME e envia via Gmail API.
     *
     * @param string $to        E-mail do destinatário
     * @param string $subject   Assunto
     * @param string $htmlBody  Corpo do e-mail em HTML
     * @param string $fromName  Nome de exibição do remetente (opcional)
     *
     * @return array Resposta da API do Gmail (decodificada)
     * @throws Exception
     */
    public function send(string $to, string $subject, string $htmlBody, string $fromName = ''): array
    {
        $accessToken = $this->getAccessToken();

    $fromHeader = $fromName !== ''
    ? '=?UTF-8?B?' . base64_encode($fromName) . '?=' . " <{$this->senderEmail}>"
    : $this->senderEmail;


        // Assunto com suporte a acentuação (UTF-8)
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $rawMessage  = "From: {$fromHeader}\r\n";
        $rawMessage .= "To: {$to}\r\n";
        $rawMessage .= "Subject: {$encodedSubject}\r\n";
        $rawMessage .= "MIME-Version: 1.0\r\n";
        $rawMessage .= "Content-Type: text/html; charset=UTF-8\r\n";
        $rawMessage .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $rawMessage .= chunk_split(base64_encode($htmlBody));

        // Gmail API exige base64url (sem +, /, = padrão)
        $base64UrlMessage = rtrim(strtr(base64_encode($rawMessage), '+/', '-_'), '=');

        $url = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';

        $payload = json_encode(['raw' => $base64UrlMessage]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
        ]);
        $this->applyCaBundle($ch);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new Exception("Erro de conexão ao enviar e-mail: {$curlErr}");
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            $errMsg = $data['error']['message'] ?? $response;
            throw new Exception("Falha ao enviar e-mail (HTTP {$httpCode}): {$errMsg}");
        }

        return $data;
    }
}
