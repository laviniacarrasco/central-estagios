<?php
/**
 * =============================================================================
 * LOG DE ACESSOS (LOGINS) DOS USUARIOS
 * -----------------------------------------------------------------------------
 * Guarda um registro simples a cada login bem-sucedido, em
 * data/acessos_log.json, no formato:
 *   [ { "userId": 12, "data": "2026-08-20 14:03:11" }, ... ]
 *
 * Esse historico alimenta o widget "Engajamento na Plataforma" do dashboard
 * administrativo (contagem de acessos por dia nos ultimos 7 dias) e tambem
 * pode ser usado para outras metricas futuras (ex: alunos inativos).
 * ============================================================================
 */

define('ACESSOS_LOG_PATH', DATA_PATH . 'acessos_log.json');

/**
 * Registra um novo acesso (login) do usuario informado. Chamado logo apos
 * a autenticacao ser validada com sucesso.
 */
function registrarAcesso(int $userId): void {
    // dadosOperar trava a gravação, então dois logins ao mesmo tempo não se apagam
    dadosOperar('acessos_log', function (array &$log) use ($userId) {
        $log[] = [
            'userId' => $userId,
            'data'   => date('Y-m-d H:i:s'),
        ];
        // Guarda só os últimos 5000 registros (evita crescer para sempre)
        if (count($log) > 5000) {
            $log = array_slice($log, -5000);
        }
    });
}

/**
 * Le o log completo de acessos (com seguranca, mesmo se ainda nao existir).
 */
function getAcessosLog(): array {
    return dadosLer('acessos_log');
}

/**
 * Retorna a contagem de acessos (logins) para cada um dos ultimos 7 dias,
 * incluindo hoje. O resultado vem como um array associativo ordenado do
 * dia mais antigo para o mais recente, ex:
 *   ['Seg' => 12, 'Ter' => 8, ..., 'Dom' => 3]
 *
 * Cada login conta uma vez por dia por usuario (evita que um aluno que
 * atualiza a pagina 50 vezes infle o grafico); ajuste $unicoPorDia para
 * false se quiser contar cada login individualmente.
 */
function getAcessosUltimos7Dias(bool $unicoPorDia = true): array {
    $log = getAcessosLog();
    $diasSemanaPt = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    // Monta os ultimos 7 dias (do mais antigo para o mais recente)
    $dias = [];
    for ($i = 6; $i >= 0; $i--) {
        $data = new DateTime("-{$i} days");
        $chaveData = $data->format('Y-m-d');
        $label = $diasSemanaPt[intval($data->format('w'))];
        $dias[$chaveData] = ['label' => $label, 'usuarios' => []];
    }

    foreach ($log as $registro) {
        $dataRegistro = $registro['data'] ?? null;
        $userId = $registro['userId'] ?? null;
        if (!$dataRegistro || !$userId) continue;

        $chaveData = substr($dataRegistro, 0, 10); // YYYY-MM-DD
        if (!isset($dias[$chaveData])) continue;

        if ($unicoPorDia) {
            $dias[$chaveData]['usuarios'][$userId] = true;
        } else {
            $dias[$chaveData]['usuarios'][] = true;
        }
    }

    $resultado = [];
    foreach ($dias as $info) {
        $resultado[$info['label']] = count($info['usuarios']);
    }
    return $resultado;
}
