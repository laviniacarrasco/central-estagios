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
    $log = [];
    if (file_exists(ACESSOS_LOG_PATH)) {
        $conteudo = json_decode(file_get_contents(ACESSOS_LOG_PATH), true);
        if (is_array($conteudo)) {
            $log = $conteudo;
        }
    }

    $log[] = [
        'userId' => $userId,
        'data'   => date('Y-m-d H:i:s'),
    ];

    // Mantem o arquivo com um tamanho razoavel, guardando apenas os
    // ultimos 5000 registros (evita crescimento infinito do arquivo).
    if (count($log) > 5000) {
        $log = array_slice($log, -5000);
    }

    $dir = dirname(ACESSOS_LOG_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents(ACESSOS_LOG_PATH, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Le o log completo de acessos (com seguranca, mesmo se o arquivo nao
 * existir ainda ou estiver corrompido).
 */
function getAcessosLog(): array {
    if (!file_exists(ACESSOS_LOG_PATH)) {
        return [];
    }
    $conteudo = json_decode(file_get_contents(ACESSOS_LOG_PATH), true);
    return is_array($conteudo) ? $conteudo : [];
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
