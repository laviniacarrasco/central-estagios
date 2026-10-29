<?php
/**
 * Recebe o formulario "Links e disponibilidade" do Perfil.
 * Grava em data/users/<id>/userProfile.json, dentro da chave "preferencias",
 * sem mexer na foto. So aluno (inclusive o restrito).
 */
require_once 'includes/config.php';
require_once 'includes/egressos.php';
checkAuth();

if (!empty($_SESSION['is_admin']) || !empty($_SESSION['is_egresso'])) {
    header('Location: dashboard.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit;
}

$limpa = function ($v, int $max = 200): string {
    return mb_substr(trim(strip_tags((string) $v)), 0, $max);
};
$link = function ($v): string {
    $v = trim((string) $v);
    if ($v === '') return '';
    $ok = egressoNormalizarLink($v);
    return (strpos($ok, 'mailto:') === 0) ? '' : $ok;
};

$turnos = ['Manhã', 'Tarde', 'Noite', 'Integral', 'Flexível'];
$modais = ['Presencial', 'Híbrido', 'Remoto', 'Qualquer'];

$turno  = (string) ($_POST['turno'] ?? '');
$modal  = (string) ($_POST['modalidade'] ?? '');
$inicio = trim((string) ($_POST['inicio'] ?? ''));
$d = DateTime::createFromFormat('Y-m-d', $inicio);
if (!$d || $d->format('Y-m-d') !== $inicio) $inicio = '';

$perfil = loadData('userProfile', true);
if (!is_array($perfil)) $perfil = [];
$perfil['preferencias'] = [
    'linkedin'   => $link($_POST['linkedin'] ?? ''),
    'github'     => $link($_POST['github'] ?? ''),
    'portfolio'  => $link($_POST['portfolio'] ?? ''),
    'areas'      => $limpa($_POST['areas'] ?? ''),
    'turno'      => in_array($turno, $turnos, true) ? $turno : '',
    'modalidade' => in_array($modal, $modais, true) ? $modal : '',
    'inicio'     => $inicio,
];
saveData('userProfile', $perfil, true);

header('Location: profile.php?prefs=ok#preferencias');
exit;
