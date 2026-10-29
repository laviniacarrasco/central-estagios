<?php
/**
 * =============================================================================
 * CHAT DA MENTORIA (responde em JSON, usado pela tela mentoria.php)
 * -----------------------------------------------------------------------------
 *   GET  mentoria-chat.php?pedido=ID   -> mensagens da conversa (e marca como lidas)
 *   POST mentoria-chat.php             -> envia uma mensagem (campos: pedido, texto)
 *   GET  mentoria-chat.php?contar=1    -> número da bolinha do ícone Mentoria
 *   POST acao=denunciar                -> denuncia uma mensagem (campo mensagem) ou a conversa
 *                                         (campos: pedido, categoria, descricao)
 *
 * Quem pode abrir: aluno e egresso (lista em includes/perfis.php).
 * Dentro de cada conversa, só o aluno e o mentor daquele pedido.
 * =============================================================================
 */
require_once 'includes/config.php';
require_once 'includes/mentoria.php';
checkAuth();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$userId = (int) $_SESSION['user_id'];

/** Responde em JSON e encerra */
function chatResponder(array $dados, int $codigo = 200): void {
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

// Proteção simples: as chamadas da própria tela mandam este cabeçalho.
// Um formulário de outro site não consegue mandar, então ninguém envia
// mensagem em nome de outra pessoa a partir de outra página.
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') {
    chatResponder(['ok' => false, 'erro' => 'requisicao_invalida'], 400);
}

// Só o número da bolinha (o header pergunta isso de tempos em tempos)
if (isset($_GET['contar'])) {
    chatResponder(['ok' => true, 'total' => (int) mentoriaPendentesParaMim()]);
}

$pedidoId = (int) ($_POST['pedido'] ?? $_GET['pedido'] ?? 0);

// ---- Denunciar uma mensagem ou a conversa ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'denunciar') {
    $r = mentoriaCriarDenuncia($userId, $pedidoId, (string) ($_POST['categoria'] ?? 'outro'),
                               (string) ($_POST['descricao'] ?? ''), (string) ($_POST['mensagem'] ?? ''));
    if (empty($r['ok'])) chatResponder(['ok' => false, 'erro' => mentoriaDenunciaErro($r['erro'] ?? 'falha')], 400);
    chatResponder(['ok' => true]);
}

// ---- Enviar mensagem ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $r = mentoriaEnviarMensagem($pedidoId, $userId, (string) ($_POST['texto'] ?? ''));
    if (empty($r['ok'])) {
        $textos = [
            'vazia'          => 'Escreva uma mensagem antes de enviar.',
            'fechado'        => 'Esta mentoria foi encerrada. A conversa ficou só para leitura.',
            'nao_encontrado' => 'Conversa não encontrada.',
            'falha'          => 'Não foi possível enviar agora. Tente de novo.',
        ];
        chatResponder(['ok' => false, 'erro' => $textos[$r['erro'] ?? 'falha'] ?? $textos['falha']], 400);
    }
}

// ---- Ler a conversa ----
$p = mentoriaPedidoPorId($pedidoId);
if (!$p || mentoriaPapelNoPedido($p, $userId) === '' || !mentoriaChatVisivel($p)) {
    chatResponder(['ok' => false, 'erro' => 'Conversa não encontrada.'], 404);
}

mentoriaMarcarLido($pedidoId, $userId);

$souAluno  = mentoriaPapelNoPedido($p, $userId) === 'aluno';
$mensagens = [];
$ultimoTs  = 0;
foreach ((array) ($p['mensagens'] ?? []) as $m) {
    $ts = (float) ($m['ts'] ?? 0);
    $ultimoTs = max($ultimoTs, $ts);
    $mensagens[] = [
        'id'    => (string) ($m['id'] ?? ''),
        'minha' => (int) ($m['de'] ?? 0) === $userId,
        'texto' => (string) ($m['texto'] ?? ''),     // a tela escreve com textContent (sem HTML)
        'hora'  => date('d/m H:i', (int) $ts),
    ];
}

chatResponder([
    'ok'        => true,
    'aberto'    => mentoriaChatAberto($p),
    'outroNome' => (string) ($souAluno ? ($p['mentorNome'] ?? 'Mentor') : ($p['alunoNome'] ?? 'Aluno')),
    'assunto'   => (string) ($p['assunto'] ?? ''),
    'mensagens' => $mensagens,
    'ultimoTs'  => $ultimoTs,
]);
