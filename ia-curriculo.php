<?php
/**
 * =============================================================================
 * IA DO CURRÍCULO — serviço chamado pelas telas (responde em JSON)
 * -----------------------------------------------------------------------------
 * POST acao=consentir   -> o aluno concordou com o envio (sem dados pessoais)
 * POST acao=avaliar     -> avalia o currículo (curriculum.php)
 * POST acao=vagas       -> analisa as vagas que combinam (vagas-para-voce.php)
 * Exige o cabeçalho X-Requested-With: fetch (só a própria Central chama).
 * A lógica fica em includes/ia-gemini.php.
 * =============================================================================
 */
require_once 'includes/config.php';
require_once 'includes/ia-gemini.php';
checkAuth();

header('Content-Type: application/json; charset=utf-8');
function iaResponder(array $d, int $code = 200): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch' || $_SERVER['REQUEST_METHOD'] !== 'POST') iaResponder(['ok' => false, 'erro' => 'Requisição inválida.'], 400);
if (!perfilPode('ia_curriculo') && !perfilPode('ia_cv')) iaResponder(['ok' => false, 'erro' => 'A IA não está disponível para o seu perfil.'], 403);
if (!iaConfigurada()) iaResponder(['ok' => false, 'erro' => 'A IA ainda não foi configurada pela coordenação.']);

@set_time_limit(600);
$userId  = (int) $_SESSION['user_id'];
$usuario = egressoCarregarUsuario($userId) ?: ['id' => $userId];
$acao    = (string) ($_POST['acao'] ?? '');

if ($acao === 'consentir') {
    iaRegistrarConsentimento($userId);
    iaResponder(['ok' => true]);
}
if (!iaConsentiu($userId)) iaResponder(['ok' => false, 'precisaConsentir' => true, 'erro' => 'Confirme o aviso de privacidade antes.']);

if ($acao === 'avaliar') {
    $cv = loadData('userCurriculum', true);
    $r = iaAvaliarCurriculo($userId, is_array($cv) ? $cv : [], (string) ($usuario['curso'] ?? ''));
    iaResponder($r['ok'] ? ['ok' => true] : ['ok' => false, 'erro' => $r['erro']]);
}
if ($acao === 'vagas') {
    // v33: a análise de vagas continua só para quem tem o recurso completo
    if (!perfilPode('ia_curriculo')) iaResponder(['ok' => false, 'erro' => 'A análise de vagas com IA não está disponível para o seu perfil.'], 403);
    $r = iaAnalisarVagas($usuario);
    iaResponder($r['ok'] ? ['ok' => true, 'total' => count($r['dados']['lista'] ?? [])] : ['ok' => false, 'erro' => $r['erro']]);
}
iaResponder(['ok' => false, 'erro' => 'Ação desconhecida.'], 400);
