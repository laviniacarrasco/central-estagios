<?php
/**
 * Salva / remove uma vaga das "Vagas salvas" do aluno (alterna).
 * Aceita apenas POST. Depois volta para a pagina de onde o aluno clicou.
 */
require_once 'includes/config.php';
require_once 'includes/egressos.php';
checkAuth();

if (!empty($_SESSION['is_admin']) || isEgresso()) {
    header('Location: dashboard.php');
    exit;
}

$paginasVolta = ['jobs.php', 'minhas-vagas.php', 'vagas-para-voce.php'];
function favoritarVoltar(string $volta): void {
    global $paginasVolta;
    $path = basename((string) parse_url($volta, PHP_URL_PATH));
    $qs   = (string) parse_url($volta, PHP_URL_QUERY);
    if (!in_array($path, $paginasVolta, true)) { $path = 'minhas-vagas.php'; $qs = 'aba=salvas'; }
    header('Location: ' . $path . ($qs !== '' ? '?' . $qs : ''));
    exit;
}

$volta = (string) ($_POST['volta'] ?? 'minhas-vagas.php?aba=salvas');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') favoritarVoltar($volta);

$origem = $_POST['origem'] ?? '';
$jobId  = (int) ($_POST['job_id'] ?? 0);
if (!in_array($origem, ['faculdade', 'egresso'], true) || $jobId <= 0) favoritarVoltar($volta);

$favs = favoritosLista();
$jaTem = false;
foreach ($favs as $f) {
    if (($f['origem'] ?? '') === $origem && (int) ($f['jobId'] ?? 0) === $jobId) { $jaTem = true; break; }
}

if ($jaTem) {
    $favs = array_values(array_filter($favs, fn($f) => !(($f['origem'] ?? '') === $origem && (int) ($f['jobId'] ?? 0) === $jobId)));
} else {
    $vaga = null;
    $fonte = $origem === 'egresso' ? egressoVagas() : loadData('platform_jobs');
    foreach ($fonte as $j) {
        if ((int) ($j['id'] ?? 0) === $jobId) { $vaga = $j; break; }
    }
    if ($vaga) {
        array_unshift($favs, [
            'origem'  => $origem,
            'jobId'   => $jobId,
            'title'   => $vaga['title'] ?? '',
            'company' => $vaga['company'] ?? '',
            'savedTs' => time(),
        ]);
    }
}
saveData('userFavoritos', $favs, true);
favoritarVoltar($volta);
