<?php
require_once 'includes/config.php';
require_once 'includes/egressos.php';
checkAuth();

$pageTitle = 'Minhas Candidaturas';

$opcoesStatus = candidaturaStatusOptions();
$cands = loadData('userCandidaturas', true);

/* ---- acoes ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['action'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);

    if ($acao === 'status' && in_array($_POST['status'] ?? '', $opcoesStatus, true)) {
        foreach ($cands as &$c) {
            if (($c['id'] ?? 0) == $id) {
                $c['status'] = $_POST['status'];
                break;
            }
        }
        unset($c);
        saveData('userCandidaturas', $cands, true);
    } elseif ($acao === 'remover') {
        $cands = array_values(array_filter($cands, fn($c) => ($c['id'] ?? 0) != $id));
        saveData('userCandidaturas', $cands, true);
    }
    $voltaFiltro = (!empty($_POST['filtro']) && in_array($_POST['filtro'], $opcoesStatus, true))
        ? '?status=' . urlencode($_POST['filtro']) : '';
    header('Location: candidaturas.php' . $voltaFiltro);
    exit;
}

usort($cands, fn($a, $b) => ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0));

/* ---- resumo ---- */
$total = count($cands);
$cont  = array_fill_keys($opcoesStatus, 0);
foreach ($cands as $c) {
    $s = $c['status'] ?? 'Enviada';
    if (isset($cont[$s])) $cont[$s]++;
}
$emAndamento = $cont['Enviada'] + $cont['Em processo'] + $cont['Entrevista'];

$filtro = $_GET['status'] ?? '';
if (!in_array($filtro, $opcoesStatus, true)) $filtro = '';
$lista = $filtro !== ''
    ? array_values(array_filter($cands, fn($c) => ($c['status'] ?? 'Enviada') === $filtro))
    : $cands;

$avisos = [
    'sem_link'          => 'Esta vaga não tem link de candidatura cadastrado. Registramos seu interesse; veja na descrição da vaga como se candidatar.',
    'vaga_indisponivel' => 'Essa vaga não está mais disponível.',
];
$aviso = $avisos[$_GET['aviso'] ?? ''] ?? '';

function candStatusClasse($s): string {
    switch ($s) {
        case 'Aprovado(a)':         return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'Entrevista':          return 'bg-purple-50 text-purple-700 border-purple-200';
        case 'Em processo':         return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'Não selecionado(a)':  return 'bg-red-50 text-red-700 border-red-200';
        case 'Desisti':             return 'bg-gray-100 text-gray-600 border-gray-200';
        default:                    return 'bg-amber-50 text-amber-700 border-amber-200';
    }
}

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-5xl mx-auto">

        <div class="mb-8 animate-fade-in">
            <h2 class="text-3xl font-bold text-slate-900 mb-2">Minhas Candidaturas</h2>
            <p class="text-slate-500 font-medium">Acompanhe as vagas em que você clicou em "Candidatar-se", da FSA e de egressos.</p>
        </div>

        <?php if ($aviso): ?>
            <div class="mb-6 p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-sm">
                <i class="fas fa-info-circle mr-1"></i> <?= egh($aviso) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="p-5 bg-[#58A1D4] text-white rounded-2xl">
                <p class="text-white/90 text-sm font-medium">Total</p>
                <p class="text-4xl font-bold"><?= $total ?></p>
            </div>
            <div class="p-5 bg-[#E35444] text-white rounded-2xl">
                <p class="text-white/90 text-sm font-medium">Em andamento</p>
                <p class="text-4xl font-bold"><?= $emAndamento ?></p>
            </div>
            <div class="p-5 bg-purple-500 text-white rounded-2xl">
                <p class="text-white/90 text-sm font-medium">Entrevistas</p>
                <p class="text-4xl font-bold"><?= $cont['Entrevista'] ?></p>
            </div>
            <div class="p-5 bg-[#27AE60] text-white rounded-2xl">
                <p class="text-white/90 text-sm font-medium">Aprovações</p>
                <p class="text-4xl font-bold"><?= $cont['Aprovado(a)'] ?></p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 mb-6">
            <a href="candidaturas.php"
               class="px-4 py-2 rounded-full text-sm font-semibold border <?= $filtro === '' ? 'bg-[#4A9FCA] text-white border-[#4A9FCA]' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' ?>">
                Todas
            </a>
            <?php foreach ($opcoesStatus as $op): ?>
                <a href="candidaturas.php?status=<?= urlencode($op) ?>"
                   class="px-4 py-2 rounded-full text-sm font-semibold border <?= $filtro === $op ? 'bg-[#4A9FCA] text-white border-[#4A9FCA]' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' ?>">
                    <?= egh($op) ?> (<?= $cont[$op] ?>)
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($lista)): ?>
            <div class="p-16 text-center border-2 border-dashed border-gray-200 rounded-3xl bg-white">
                <i class="fas fa-paper-plane text-6xl text-gray-200 mb-4"></i>
                <h3 class="text-xl font-bold text-slate-900 mb-2">
                    <?= $total === 0 ? 'Você ainda não se candidatou a nenhuma vaga' : 'Nenhuma candidatura com esse andamento' ?>
                </h3>
                <p class="text-slate-500 mb-6">Quando você clicar em "Candidatar-se", a vaga aparece aqui.</p>
                <div class="flex justify-center gap-3 flex-wrap">
                    <a href="jobs.php" class="px-6 py-3 bg-[#4A9FCA] text-white rounded-xl font-semibold text-sm">Ver vagas da FSA</a>
                    <a href="egressos.php" class="px-6 py-3 border border-gray-200 text-gray-600 rounded-xl font-semibold text-sm hover:bg-gray-50">Vagas de egressos</a>
                </div>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($lista as $c): $st = $c['status'] ?? 'Enviada'; ?>
                    <div class="p-6 bg-white border border-gray-100 rounded-2xl shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap mb-1">
                                    <h3 class="text-lg font-bold text-slate-900"><?= egh($c['title'] ?? '') ?></h3>
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= ($c['origem'] ?? '') === 'egresso' ? 'bg-purple-50 text-purple-700' : 'bg-sky-50 text-sky-700' ?>">
                                        <?= ($c['origem'] ?? '') === 'egresso' ? 'Vaga de egresso' : 'Vaga FSA' ?>
                                    </span>
                                </div>
                                <p class="text-slate-500 font-semibold"><?= egh($c['company'] ?? '') ?></p>
                                <p class="text-xs text-gray-400 mt-1">
                                    <?php if (!empty($c['location'])): ?><i class="fas fa-map-marker-alt mr-1"></i><?= egh($c['location']) ?> &bull; <?php endif; ?>
                                    Candidatura em <?= egh($c['createdAt'] ?? '—') ?>
                                </p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-bold border <?= candStatusClasse($st) ?>"><?= egh($st) ?></span>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <form method="POST" class="flex items-center gap-2">
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <input type="hidden" name="filtro" value="<?= egh($filtro) ?>">
                                <label class="text-xs text-gray-500">Andamento:</label>
                                <select name="status" onchange="this.form.submit()" class="h-9 px-3 border border-gray-200 rounded-lg text-sm bg-gray-50">
                                    <?php foreach ($opcoesStatus as $op): ?>
                                        <option value="<?= egh($op) ?>" <?= $op === $st ? 'selected' : '' ?>><?= egh($op) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>

                            <?php if (!empty($c['link'])): ?>
                                <a href="<?= egh($c['link']) ?>" target="_blank" rel="noopener"
                                   class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 text-sm">
                                    <i class="fas fa-external-link-alt mr-1"></i> Abrir vaga
                                </a>
                            <?php endif; ?>

                            <form method="POST" class="ml-auto" onsubmit="return confirm('Remover esta candidatura da sua lista?');">
                                <input type="hidden" name="action" value="remover">
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <input type="hidden" name="filtro" value="<?= egh($filtro) ?>">
                                <button type="submit" class="px-3 py-2 text-red-500 hover:bg-red-50 rounded-lg text-sm">
                                    <i class="fas fa-trash mr-1"></i> Remover
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php include 'includes/footer.php'; ?>
