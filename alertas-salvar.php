<?php
/**
 * Recebe o formulario do cartao "Meus Alertas" (profile.php) e volta para o perfil.
 * Aceita so POST e so aluno.
 */
require_once 'includes/config.php';
require_once 'includes/alertas.php';
checkAuth();

if (!empty($_SESSION['is_admin']) || !empty($_SESSION['is_egresso'])) {
    header('Location: dashboard.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php#alertas');
    exit;
}

$userId  = (int) $_SESSION['user_id'];
$usuario = egressoCarregarUsuario($userId);
if (!$usuario) { header('Location: index.php?logout=1'); exit; }

function alertasVoltar(string $msg): void {
    header('Location: profile.php?alertas=' . urlencode($msg) . '#alertas');
    exit;
}

$acao = $_POST['action'] ?? '';

if ($acao === 'salvar') {
    $cursos = array_values(array_intersect((array) ($_POST['cursos'] ?? []), getFsaCourses()));
    alertasSalvarPrefs($userId, [
        'vagaNova'      => isset($_POST['vagaNova']),
        'resumoSemanal' => isset($_POST['resumoSemanal']),
        'cursos'        => $cursos,
    ]);
    alertasVoltar('salvo');
}

if ($acao === 'teste') {
    if (!alertasEmailValido($usuario)) alertasVoltar('sem_email');
    alertasVoltar(alertasEnviarResumoTeste($usuario) ? 'teste_ok' : 'teste_falha');
}

alertasVoltar('');
