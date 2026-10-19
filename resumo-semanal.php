<?php
/**
 * Endpoint para agendador externo (ex: cron-job.org) chamar, SEM login.
 * Protegido por chave: defina ALERTAS_CRON_KEY no .env / Environment e chame
 *   https://SEU-SITE/resumo-semanal.php?chave=SUA_CHAVE
 *
 * A cada chamada ele: (1) gera o resumo da semana, se ainda nao foi gerado;
 * (2) envia parte da fila de e-mails. Pode chamar a cada 15-30 min sem risco:
 * o resumo semanal nunca e gerado duas vezes na mesma semana.
 * Parametro opcional: &forcar=1 (gera de novo; so use para teste).
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/alertas.php';

header('Content-Type: application/json; charset=UTF-8');

$chaveConfigurada = (string) ($_ENV['ALERTAS_CRON_KEY'] ?? (getenv('ALERTAS_CRON_KEY') ?: ''));
$chaveRecebida    = (string) ($_GET['chave'] ?? '');

if ($chaveConfigurada === '') {
    http_response_code(503);
    echo json_encode(['ok' => false, 'erro' => 'ALERTAS_CRON_KEY nao configurada no servidor']);
    exit;
}
if (!hash_equals($chaveConfigurada, $chaveRecebida)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'erro' => 'chave invalida']);
    exit;
}

@set_time_limit(60);
ignore_user_abort(true);

$r = alertasManutencao(!empty($_GET['forcar']), 25, 30);
echo json_encode(['ok' => true, 'quando' => date('c')] + $r, JSON_UNESCAPED_UNICODE);
