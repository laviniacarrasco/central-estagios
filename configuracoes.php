<?php
/**
 * =============================================================================
 * CONFIGURAÇÕES — serviço da caixa de configurações (engrenagem do topo)
 * -----------------------------------------------------------------------------
 * A caixa abre por cima de QUALQUER página (includes/configuracoes-modal.php)
 * e salva sem recarregar: a pessoa continua na mesma tela.
 *
 * GET  ?carregar=1         -> dados para preencher a caixa
 * POST acao=salvar_alertas -> alertas de vagas por e-mail (usuarios.json -> alertas)
 * POST acao=teste_alertas  -> envia um resumo de teste
 * POST acao=salvar_prefs   -> disponibilidade e links (userProfile -> preferencias)
 * POST acao=revogar_ia     -> retira a autorização de uso da IA no currículo
 * Exige o cabeçalho X-Requested-With: fetch.
 * =============================================================================
 */
require_once 'includes/config.php';
require_once 'includes/alertas.php';
require_once 'includes/egressos.php';
checkAuth();

header('Content-Type: application/json; charset=utf-8');
function cfgResponder(array $d, int $code = 200): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') cfgResponder(['ok' => false, 'erro' => 'Requisição inválida.'], 400);

$userId      = (int) $_SESSION['user_id'];
$usuario     = egressoCarregarUsuario($userId) ?: [];
$podeAlertas = perfilPode('alertas');
$podePrefs   = perfilPode('area_aluno');
const CFG_TURNOS = ['Manhã', 'Tarde', 'Noite', 'Integral', 'Flexível'];
const CFG_MODAIS = ['Presencial', 'Híbrido', 'Remoto', 'Qualquer'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $prof = loadData('userProfile', true);
    $pr = (is_array($prof) && is_array($prof['preferencias'] ?? null)) ? $prof['preferencias'] : [];
    cfgResponder([
        'ok'          => true,
        'nome'        => (string) ($usuario['nome'] ?? ($_SESSION['user_name'] ?? '')),
        'email'       => (string) ($usuario['email'] ?? ''),
        'curso'       => (string) ($usuario['curso'] ?? ''),
        'perfil'      => perfilRotulo(),
        'podeAlertas' => $podeAlertas,
        'podePrefs'   => $podePrefs,
        'podeIa'      => perfilPode('ia_curriculo') || perfilPode('ia_cv'),
        'temEmail'    => $usuario ? alertasEmailValido($usuario) : false,
        'alertas'     => $podeAlertas && $usuario ? alertasPrefs($usuario) : null,
        'cursos'      => getFsaCourses(),
        'prefs'       => $pr + ['linkedin' => '', 'github' => '', 'portfolio' => '', 'areas' => '', 'turno' => '', 'modalidade' => '', 'inicio' => ''],
        'turnos'      => CFG_TURNOS,
        'modalidades' => CFG_MODAIS,
        'iaConsentimento' => (string) ($prof['iaConsentimento'] ?? ''),
    ]);
}

$acao = (string) ($_POST['acao'] ?? '');

if ($acao === 'salvar_alertas') {
    if (!$podeAlertas) cfgResponder(['ok' => false, 'erro' => 'Alertas não disponíveis para o seu perfil.'], 403);
    $cursos = array_values(array_intersect((array) ($_POST['cursos'] ?? []), getFsaCourses()));
    alertasSalvarPrefs($userId, [
        'vagaNova'      => ($_POST['vagaNova'] ?? '') === '1',
        'resumoSemanal' => ($_POST['resumoSemanal'] ?? '') === '1',
        'cursos'        => $cursos,
    ]);
    cfgResponder(['ok' => true, 'msg' => 'Alertas salvos.']);
}

if ($acao === 'teste_alertas') {
    if (!$podeAlertas) cfgResponder(['ok' => false, 'erro' => 'Alertas não disponíveis para o seu perfil.'], 403);
    if (!$usuario || !alertasEmailValido($usuario)) cfgResponder(['ok' => false, 'erro' => 'Seu cadastro não tem um e-mail válido. Fale com a coordenação.']);
    cfgResponder(alertasEnviarResumoTeste($usuario)
        ? ['ok' => true, 'msg' => 'Resumo de teste enviado. Confira sua caixa de entrada (e o spam).']
        : ['ok' => false, 'erro' => 'Não foi possível enviar o e-mail de teste agora.']);
}

if ($acao === 'salvar_prefs') {
    if (!$podePrefs) cfgResponder(['ok' => false, 'erro' => 'Opção não disponível para o seu perfil.'], 403);
    $limpa = fn($v, int $max = 200): string => mb_substr(trim(strip_tags((string) $v)), 0, $max);
    $link = function ($v): string {
        $v = trim((string) $v);
        if ($v === '') return '';
        $ok = egressoNormalizarLink($v);
        return (strpos($ok, 'mailto:') === 0) ? '' : $ok;
    };
    $inicio = trim((string) ($_POST['inicio'] ?? ''));
    $d = DateTime::createFromFormat('Y-m-d', $inicio);
    if (!$d || $d->format('Y-m-d') !== $inicio) $inicio = '';
    $turno = (string) ($_POST['turno'] ?? '');
    $modal = (string) ($_POST['modalidade'] ?? '');
    $novo = [
        'linkedin'   => $link($_POST['linkedin'] ?? ''),
        'github'     => $link($_POST['github'] ?? ''),
        'portfolio'  => $link($_POST['portfolio'] ?? ''),
        'areas'      => $limpa($_POST['areas'] ?? ''),
        'turno'      => in_array($turno, CFG_TURNOS, true) ? $turno : '',
        'modalidade' => in_array($modal, CFG_MODAIS, true) ? $modal : '',
        'inicio'     => $inicio,
    ];
    dadosOperar('users/' . $userId . '/userProfile', function (array &$p) use ($novo) { $p['preferencias'] = $novo; });
    cfgResponder(['ok' => true, 'msg' => 'Preferências salvas.', 'prefs' => $novo]);
}

if ($acao === 'revogar_ia') {
    dadosOperar('users/' . $userId . '/userProfile', function (array &$p) { unset($p['iaConsentimento']); });
    cfgResponder(['ok' => true, 'msg' => 'Pronto. A IA só será usada de novo se você concordar outra vez.']);
}

cfgResponder(['ok' => false, 'erro' => 'Ação desconhecida.'], 400);
