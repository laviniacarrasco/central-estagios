<?php
/**
 * Importa vagas externas (Gupy e/ou Adzuna, conforme VAGAS_FONTE) para a fila "Vagas Sugeridas".
 * Para agendar (cron-job.org, por exemplo, 1 vez por dia):
 *   https://SEU-SITE/importar-vagas-cron.php?chave=SUA_CHAVE
 * Usa a mesma chave ALERTAS_CRON_KEY do resumo semanal. Nao publica nada:
 * as vagas ficam esperando a aprovacao do admin.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/melhorias.php';
require_once __DIR__ . '/includes/vagas-externas.php';

header('Content-Type: application/json; charset=utf-8');

$chave    = (string) ($_ENV['ALERTAS_CRON_KEY'] ?? (getenv('ALERTAS_CRON_KEY') ?: ''));
$recebida = (string) ($_GET['chave'] ?? '');
if ($chave === '' || !hash_equals($chave, $recebida)) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$r = vexImportar();
echo json_encode(['ok' => $r['erro'] === ''] + $r, JSON_UNESCAPED_UNICODE);
