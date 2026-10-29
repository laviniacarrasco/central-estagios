<?php
/**
 * =============================================================================
 * CONVERSAS DA MENTORIA: consulta e download (só Coordenação de Egressos)
 * -----------------------------------------------------------------------------
 *   GET ?pedido=ID                         -> JSON, usado pela janela "Ver log" (admin-mentoria.php)
 *   GET ?pedido=ID&baixar=txt | csv        -> uma conversa
 *   GET ?mentor=ID&baixar=txt | csv        -> todas as conversas de um mentor
 *   GET ?aluno=ID&baixar=txt | csv         -> todas as conversas de um aluno
 *   GET ?todas=1[&status=...]&baixar=csv   -> todas as conversas (uma linha por mensagem)
 *
 * Entra na conversa: o pedido inicial do aluno, a resposta do mentor (ou o
 * motivo do cancelamento) e todas as mensagens do chat. Mensagens denunciadas
 * aparecem marcadas. Cada consulta e cada download ficam no log (admin_log).
 * =============================================================================
 */
require_once 'includes/config.php';
require_once 'includes/mentoria.php';
require_once 'includes/melhorias.php';
checkAuth();

if (!isAdminEgressos()) {
    http_response_code(403);
    exit('Sem permissão.');
}

function convData(float $ts): string {
    return $ts > 0 ? date('d/m/Y H:i', (int) $ts) : '';
}

/** Linhas da conversa, em ordem: pedido, resposta e mensagens do chat */
function convLinhas(array $p): array {
    static $denunciadas = null;
    if ($denunciadas === null) $denunciadas = mentoriaMensagensDenunciadas();
    $alunoId  = (int) ($p['alunoId'] ?? 0);
    $mentorId = (int) ($p['mentorId'] ?? 0);
    $aluno    = (string) ($p['alunoNome'] ?? 'Aluno');
    $mentor   = (string) ($p['mentorNome'] ?? 'Mentor');
    $linhas   = [];

    if (trim((string) ($p['mensagem'] ?? '')) !== '') {
        $linhas[] = ['ts' => (float) ($p['criadoTs'] ?? 0), 'autor' => $aluno, 'papel' => 'Aluno', 'tipo' => 'Pedido de mentoria', 'texto' => (string) $p['mensagem']];
    }
    if (trim((string) ($p['resposta'] ?? '')) !== '') {
        if (!empty($p['respondidoTs'])) {
            $linhas[] = ['ts' => (float) $p['respondidoTs'], 'autor' => $mentor, 'papel' => 'Mentor',
                         'tipo' => ($p['status'] ?? '') === 'recusado' ? 'Recusa do pedido' : 'Resposta ao pedido', 'texto' => (string) $p['resposta']];
        } else {
            $linhas[] = ['ts' => (float) ($p['encerradoTs'] ?? 0), 'autor' => 'Central de Estágios', 'papel' => 'Sistema',
                         'tipo' => 'Cancelamento', 'texto' => (string) $p['resposta']];
        }
    }
    foreach ((array) ($p['mensagens'] ?? []) as $m) {
        $de = (int) ($m['de'] ?? 0);
        [$autor, $papel] = $de === $alunoId ? [$aluno, 'Aluno'] : ($de === $mentorId ? [$mentor, 'Mentor'] : ['Usuário #' . $de, 'Outro']);
        $den = isset($denunciadas[(string) ($m['id'] ?? '')]);
        $linhas[] = ['ts' => (float) ($m['ts'] ?? 0), 'autor' => $autor, 'papel' => $papel,
                     'tipo' => $den ? 'Mensagem DENUNCIADA' : 'Mensagem', 'texto' => (string) ($m['texto'] ?? '')];
    }
    usort($linhas, fn($a, $b) => $a['ts'] <=> $b['ts']);
    return $linhas;
}

/** Célula de CSV segura (aspas e proteção contra fórmulas no Excel) */
function convCsv($v): string {
    $v = (string) $v;
    if ($v !== '' && strpbrk($v[0], "=+-@\t") !== false) $v = "'" . $v;
    return '"' . str_replace('"', '""', $v) . '"';
}

function convBaixar(string $nome, string $tipo, string $conteudo): void {
    header('Content-Type: ' . ($tipo === 'csv' ? 'text/csv' : 'text/plain') . '; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nome . '.' . $tipo . '"');
    header('Cache-Control: no-store');
    echo "\xEF\xBB\xBF" . $conteudo;   // BOM: o Excel e o Bloco de Notas abrem com acento certo
    exit;
}

const CONV_CAB_CSV = ['Pedido', 'Aluno', 'Curso do aluno', 'Mentor', 'Assunto', 'Status', 'Data e hora', 'Quem escreveu', 'Papel', 'Tipo', 'Texto'];

function convLinhasCsv(array $p): array {
    [$status] = mentoriaPedidoStatusInfo($p['status'] ?? '');
    $out = [];
    foreach (convLinhas($p) as $l) {
        $out[] = implode(';', array_map('convCsv', [
            (int) ($p['id'] ?? 0), $p['alunoNome'] ?? '', $p['alunoCurso'] ?? '', $p['mentorNome'] ?? '', $p['assunto'] ?? '',
            $status, convData($l['ts']), $l['autor'], $l['papel'], $l['tipo'], $l['texto'],
        ]));
    }
    return $out;
}

function convCsvCompleto(array $pedidos): string {
    $linhas = [implode(';', array_map('convCsv', CONV_CAB_CSV))];
    foreach ($pedidos as $p) $linhas = array_merge($linhas, convLinhasCsv($p));
    return implode("\r\n", $linhas);
}

/** Uma conversa em texto (cabeçalho + mensagens) */
function convTxt(array $p): string {
    [$status] = mentoriaPedidoStatusInfo($p['status'] ?? '');
    $t  = str_repeat('=', 60) . "\r\n";
    $t .= 'Pedido:    #' . (int) ($p['id'] ?? 0) . "\r\n";
    $t .= 'Aluno:     ' . ($p['alunoNome'] ?? '') . (!empty($p['alunoCurso']) ? ' (' . $p['alunoCurso'] . ')' : '') . "\r\n";
    $t .= 'Mentor:    ' . ($p['mentorNome'] ?? '') . "\r\n";
    $t .= 'Assunto:   ' . ($p['assunto'] ?? '') . "\r\n";
    $t .= 'Status:    ' . $status . "\r\n";
    $t .= 'Pedido em: ' . ($p['criadoEm'] ?? '') . "\r\n";
    $t .= str_repeat('=', 60) . "\r\n\r\n";
    $linhas = convLinhas($p);
    if (!$linhas) $t .= "(sem mensagens)\r\n\r\n";
    foreach ($linhas as $l) {
        $t .= '[' . convData($l['ts']) . '] ' . $l['autor'] . ' (' . $l['papel'] . ($l['tipo'] !== 'Mensagem' ? ', ' . $l['tipo'] : '') . "):\r\n";
        $t .= str_replace("\n", "\r\n", $l['texto']) . "\r\n\r\n";
    }
    return $t;
}

function convCabecalhoTxt(string $titulo): string {
    return "CONVERSAS DE MENTORIA - Central de Estágios FSA\r\n" . $titulo . "\r\n"
         . 'Baixado em ' . date('d/m/Y H:i') . ' por ' . ($_SESSION['user_name'] ?? '') . "\r\n"
         . "Documento com dados pessoais: guarde com sigilo.\r\n\r\n";
}

$baixar = in_array($_GET['baixar'] ?? '', ['txt', 'csv'], true) ? $_GET['baixar'] : '';

/* ---------- Todas as conversas (CSV) ---------- */
if (!empty($_GET['todas'])) {
    $statusFiltro = (string) ($_GET['status'] ?? '');
    $lista = array_values(array_filter(mentoriaPedidos(), fn($p) => $statusFiltro === '' || ($p['status'] ?? '') === $statusFiltro));
    mhLogAdmin('Conversas de mentoria baixadas (todas)', count($lista) . ' conversa(s)' . ($statusFiltro !== '' ? ', status ' . $statusFiltro : ''));
    convBaixar('conversas-mentoria-' . date('Y-m-d'), 'csv', convCsvCompleto($lista));
}

/* ---------- Todas as conversas de um mentor ou de um aluno ---------- */
if (!empty($_GET['mentor']) || !empty($_GET['aluno'])) {
    $ehMentor = !empty($_GET['mentor']);
    $pessoaId = (int) ($ehMentor ? $_GET['mentor'] : $_GET['aluno']);
    $campo    = $ehMentor ? 'mentorId' : 'alunoId';
    $lista    = array_values(array_filter(mentoriaPedidos(), fn($p) => (int) ($p[$campo] ?? 0) === $pessoaId));
    usort($lista, fn($a, $b) => ($a['criadoTs'] ?? 0) <=> ($b['criadoTs'] ?? 0));
    if (!$lista) { http_response_code(404); exit('Nenhuma conversa encontrada.'); }
    $nome  = (string) ($lista[0][$ehMentor ? 'mentorNome' : 'alunoNome'] ?? '');
    $papel = $ehMentor ? 'Mentor' : 'Aluno';
    $arq   = 'conversas-' . strtolower($papel) . '-' . $pessoaId . '-' . date('Y-m-d');
    mhLogAdmin('Conversas de mentoria baixadas (' . strtoupper($baixar ?: 'txt') . ')', $papel . ': ' . $nome . ' | ' . count($lista) . ' conversa(s)');
    if ($baixar === 'csv') convBaixar($arq, 'csv', convCsvCompleto($lista));
    $t = convCabecalhoTxt($papel . ': ' . $nome . ' | ' . count($lista) . ' conversa(s)');
    foreach ($lista as $p) $t .= convTxt($p) . "\r\n";
    convBaixar($arq, 'txt', $t);
}

/* ---------- Uma conversa ---------- */
$p = mentoriaPedidoPorId((int) ($_GET['pedido'] ?? 0));
if (!$p) {
    if ($baixar === '') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'erro' => 'Conversa não encontrada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(404);
    exit('Conversa não encontrada.');
}

$id = (int) $p['id'];
[$status] = mentoriaPedidoStatusInfo($p['status'] ?? '');
$detalheLog = 'Pedido #' . $id . ': ' . ($p['alunoNome'] ?? '') . ' x ' . ($p['mentorNome'] ?? '');

if ($baixar === 'csv') {
    mhLogAdmin('Conversa de mentoria baixada (CSV)', $detalheLog);
    convBaixar('conversa-mentoria-' . $id, 'csv', convCsvCompleto([$p]));
}
if ($baixar === 'txt') {
    mhLogAdmin('Conversa de mentoria baixada (TXT)', $detalheLog);
    convBaixar('conversa-mentoria-' . $id, 'txt', convCabecalhoTxt('Pedido #' . $id) . convTxt($p));
}

/* ---------- JSON para a janela "Ver log" ---------- */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'Requisição inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}
mhLogAdmin('Conversa de mentoria consultada', $detalheLog);
echo json_encode([
    'ok'       => true,
    'aluno'    => (string) ($p['alunoNome'] ?? ''),
    'mentor'   => (string) ($p['mentorNome'] ?? ''),
    'assunto'  => (string) ($p['assunto'] ?? ''),
    'status'   => $status,
    'criadoEm' => (string) ($p['criadoEm'] ?? ''),
    'linhas'   => array_map(fn($l) => ['autor' => $l['autor'], 'papel' => $l['papel'], 'tipo' => $l['tipo'], 'texto' => $l['texto'], 'data' => convData($l['ts'])], convLinhas($p)),
], JSON_UNESCAPED_UNICODE);
