<?php
/**
 * Registra a candidatura do aluno e leva ele para o link da vaga.
 * Usado pelos botoes "Candidatar-se" de jobs.php (vagas da FSA) e
 * egressos.php (vagas divulgadas por egressos).
 *
 * O sistema registra o CLIQUE em "Candidatar-se". Como a candidatura em si
 * acontece no site da empresa, o aluno ajusta o andamento (ou remove o
 * registro) em candidaturas.php.
 */
require_once 'includes/config.php';
require_once 'includes/egressos.php';
checkAuth();

// So aluno tem lista de candidaturas
if (!empty($_SESSION['is_admin']) || isEgresso()) {
    header('Location: dashboard.php');
    exit;
}

$origem = $_GET['origem'] ?? 'faculdade';
$jobId  = (int) ($_GET['job_id'] ?? 0);

if (!in_array($origem, ['faculdade', 'egresso'], true) || $jobId <= 0) {
    header('Location: candidaturas.php');
    exit;
}

/* ---- localiza a vaga ---- */
$vaga = null;
if ($origem === 'faculdade') {
    foreach (loadData('platform_jobs') as $j) {
        if (($j['id'] ?? 0) == $jobId && ($j['status'] ?? '') === 'Ativa') {
            $vaga = $j;
            break;
        }
    }
    $link = $vaga ? trim($vaga['applicationLink'] ?? '') : '';
    if ($link !== '') $link = egressoNormalizarLink($link);
} else {
    foreach (egressoVagas() as $j) {
        if (($j['id'] ?? 0) == $jobId && ($j['status'] ?? '') === 'aprovada') {
            $vaga = $j;
            break;
        }
    }
    $link = $vaga ? egressoNormalizarLink($vaga['applicationLink'] ?? '') : '';
}

if (!$vaga) {
    header('Location: candidaturas.php?aviso=vaga_indisponivel');
    exit;
}

/* ---- registra (uma vez por vaga) ---- */
$cands = loadData('userCandidaturas', true);
$jaExiste = false;
foreach ($cands as $c) {
    if (($c['origem'] ?? '') === $origem && ($c['jobId'] ?? 0) == $jobId) {
        $jaExiste = true;
        break;
    }
}

if (!$jaExiste) {
    array_unshift($cands, [
        'id'        => egressoNovoId(),
        'origem'    => $origem,
        'jobId'     => $jobId,
        'title'     => $vaga['title']    ?? '',
        'company'   => $vaga['company']  ?? '',
        'location'  => $vaga['location'] ?? '',
        'link'      => $link,
        'status'    => 'Enviada',
        'createdAt' => date('d/m/Y H:i'),
        'ts'        => time(),
    ]);
    saveData('userCandidaturas', $cands, true);

    /* conta +1 candidato na vaga (campo "applicants" que o admin ja ve) */
    if ($origem === 'faculdade') {
        $jobs = loadData('platform_jobs');
        foreach ($jobs as $k => $j) {
            if (($j['id'] ?? 0) == $jobId) {
                $jobs[$k]['applicants'] = (int) ($j['applicants'] ?? 0) + 1;
                break;
            }
        }
        saveData('platform_jobs', $jobs);
    } else {
        $todas = egressoVagas();
        foreach ($todas as $k => $j) {
            if (($j['id'] ?? 0) == $jobId) {
                $todas[$k]['applicants'] = (int) ($j['applicants'] ?? 0) + 1;
                break;
            }
        }
        egressoSalvarVagas($todas);
    }
}

/* ---- segue para a vaga ---- */
if ($link !== '') {
    header('Location: ' . $link);
} else {
    header('Location: candidaturas.php?aviso=sem_link');
}
exit;
