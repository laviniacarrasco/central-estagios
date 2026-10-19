<?php
/**
 * Mural de vagas divulgadas por egressos.
 * Visivel para: alunos, admins e egressos.
 */
require_once 'includes/config.php';
require_once 'includes/egressos.php';
checkAuth();

$pageTitle  = 'Vagas de Egressos';
$souAdmin   = !empty($_SESSION['is_admin']);
$souEgresso = isEgresso();
$souAluno   = !$souAdmin && !$souEgresso;

$fsaCourses   = getFsaCourses();
$busca        = trim($_GET['search'] ?? '');
$categoriaSel = trim($_GET['categoria'] ?? '');
$cursoSel     = trim($_GET['curso'] ?? '');

$vagas = array_values(array_filter(egressoVagas(), fn($v) => egressoVagaStatusEfetivo($v) === 'aprovada'));
usort($vagas, fn($a, $b) => ($b['publishedTs'] ?? 0) <=> ($a['publishedTs'] ?? 0));
$totalPublicadas = count($vagas);

$filtradas = array_values(array_filter($vagas, function ($v) use ($busca, $categoriaSel, $cursoSel) {
    if ($busca !== '') {
        $alvo = ($v['title'] ?? '') . ' ' . ($v['company'] ?? '') . ' ' . ($v['description'] ?? '') . ' ' . ($v['location'] ?? '');
        if (mb_stripos($alvo, $busca) === false) return false;
    }
    if ($categoriaSel !== '' && ($v['categoria'] ?? '') !== $categoriaSel) return false;
    if ($cursoSel !== '') {
        $lista = array_filter(array_map('trim', explode(',', $v['courses'] ?? '')));
        if (!empty($lista) && !in_array($cursoSel, $lista, true)) return false;
    }
    return true;
}));

$novasSemana = count(array_filter($vagas, fn($v) => ($v['publishedTs'] ?? 0) >= strtotime('-7 days')));

/* candidaturas ja feitas pelo aluno (para mostrar "Candidatura registrada") */
$jaCandidatou = [];
if ($souAluno) {
    foreach (loadData('userCandidaturas', true) as $c) {
        if (($c['origem'] ?? '') === 'egresso') $jaCandidatou[(int) $c['jobId']] = true;
    }
}

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-7xl mx-auto">

        <div class="mb-8 animate-fade-in flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-3xl font-bold text-slate-900 mb-2">Vagas de Egressos</h2>
                <p class="text-slate-500 font-medium">Oportunidades de estágio e emprego divulgadas por ex-alunos da FSA.</p>
            </div>
            <?php if ($souEgresso): ?>
                <a href="egresso-publicar.php" class="px-6 py-3 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl font-semibold text-sm">
                    <i class="fas fa-plus-circle mr-1"></i> Divulgar uma vaga
                </a>
            <?php elseif (isAdminEgressos()): ?>
                <a href="admin-egressos.php" class="px-6 py-3 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl font-semibold text-sm">
                    <i class="fas fa-user-graduate mr-1"></i> Painel de Egressos
                </a>
            <?php endif; ?>
        </div>

        <form method="GET" action="egressos.php" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-8">
            <div class="md:col-span-2 relative">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="search" value="<?= egh($busca) ?>" placeholder="Buscar por vaga, empresa ou local..."
                       class="w-full h-12 pl-12 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#4A9FCA]">
            </div>
            <select name="categoria" class="h-12 px-4 bg-white border border-gray-200 rounded-xl" onchange="this.form.submit()">
                <option value="">Todos os tipos</option>
                <?php foreach (egressoCategorias() as $cat): ?>
                    <option value="<?= egh($cat) ?>" <?= $cat === $categoriaSel ? 'selected' : '' ?>><?= egh($cat) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="curso" class="h-12 px-4 bg-white border border-gray-200 rounded-xl" onchange="this.form.submit()">
                <option value="">Todos os cursos</option>
                <?php foreach ($fsaCourses as $c): ?>
                    <option value="<?= egh($c) ?>" <?= $c === $cursoSel ? 'selected' : '' ?>><?= egh($c) ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="p-6 bg-[#58A1D4] text-white rounded-2xl h-28">
                <p class="text-white/90 text-sm font-medium mb-1">Vagas publicadas</p>
                <p class="text-4xl font-bold"><?= $totalPublicadas ?></p>
            </div>
            <div class="p-6 bg-[#27AE60] text-white rounded-2xl h-28">
                <p class="text-white/90 text-sm font-medium mb-1">Novas esta semana</p>
                <p class="text-4xl font-bold"><?= $novasSemana ?></p>
            </div>
            <div class="p-6 bg-[#E35444] text-white rounded-2xl h-28">
                <p class="text-white/90 text-sm font-medium mb-1">Resultado da busca</p>
                <p class="text-4xl font-bold"><?= count($filtradas) ?></p>
            </div>
        </div>

        <?php if (empty($filtradas)): ?>
            <div class="p-16 text-center border-2 border-dashed border-gray-200 rounded-3xl bg-white">
                <i class="fas fa-user-graduate text-6xl text-gray-200 mb-4"></i>
                <h3 class="text-xl font-bold text-slate-900 mb-2">Nenhuma vaga encontrada</h3>
                <p class="text-slate-500">
                    <?= $totalPublicadas === 0 ? 'Ainda não há vagas divulgadas por egressos.' : 'Tente ajustar os filtros ou a busca.' ?>
                </p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($filtradas as $v):
                    $linkDireto = egressoNormalizarLink($v['applicationLink'] ?? '');
                    $cursos = trim($v['courses'] ?? '') !== '' ? $v['courses'] : 'Todos os cursos';
                ?>
                    <div class="p-6 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-start gap-6">
                            <div class="w-14 h-14 bg-purple-50 rounded-xl flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-building text-purple-400 text-xl"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap justify-between items-start gap-2 mb-3">
                                    <div>
                                        <h3 class="text-lg font-bold text-slate-900"><?= egh($v['title'] ?? '') ?></h3>
                                        <p class="text-slate-500 font-semibold"><?= egh($v['company'] ?? '') ?></p>
                                    </div>
                                    <div class="flex gap-2">
                                        <span class="px-3 py-1 bg-purple-50 text-purple-700 rounded-full text-xs font-bold"><?= egh($v['categoria'] ?? 'Vaga') ?></span>
                                        <span class="px-3 py-1 border border-slate-200 text-slate-500 rounded-full text-xs font-bold"><?= egh($v['type'] ?? '') ?></span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4 text-sm font-medium">
                                    <div class="flex items-center gap-2 text-slate-500"><i class="fas fa-map-marker-alt text-[#E35444]"></i><?= egh($v['location'] ?? '—') ?></div>
                                    <div class="flex items-center gap-2 text-slate-500"><i class="fas fa-clock text-[#58A1D4]"></i><?= egh($v['hours'] ?? '—') ?></div>
                                    <div class="flex items-center gap-2 font-bold text-[#27AE60]"><i class="fas fa-dollar-sign"></i><?= egh($v['salary'] ?? 'A combinar') ?></div>
                                    <div class="text-slate-400"><?= egh($v['publishedAt'] ?? '') ?><?= vagaValidadeHtml($v) ?></div>
                                </div>

                                <p class="text-xs text-slate-500 mb-4">
                                    <i class="fas fa-user-graduate text-purple-400 mr-1"></i>
                                    Divulgada por <strong><?= egh($v['egressoName'] ?? 'Egresso') ?></strong>
                                    <?php if (!empty($v['egressoCurso'])): ?> &bull; egresso(a) de <?= egh($v['egressoCurso']) ?><?php endif; ?>
                                    <?php if (!empty($v['egressoAno'])): ?> (turma <?= egh($v['egressoAno']) ?>)<?php endif; ?>
                                </p>

                                <details class="mb-4">
                                    <summary class="cursor-pointer text-sm font-semibold text-[#4A9FCA] select-none">
                                        <i class="fas fa-eye mr-1"></i> Ver detalhes
                                    </summary>
                                    <div class="mt-3 space-y-3 text-sm text-gray-600">
                                        <div><p class="font-bold text-gray-700 uppercase text-xs mb-1">Cursos indicados</p><p class="bg-gray-50 p-3 rounded-xl"><?= egh($cursos) ?></p></div>
                                        <div><p class="font-bold text-gray-700 uppercase text-xs mb-1">Descrição</p><p class="bg-gray-50 p-3 rounded-xl whitespace-pre-line"><?= egh($v['description'] ?? '') ?: 'Não informado' ?></p></div>
                                        <div><p class="font-bold text-gray-700 uppercase text-xs mb-1">Requisitos</p><p class="bg-gray-50 p-3 rounded-xl whitespace-pre-line"><?= egh($v['requirements'] ?? '') ?: 'Não informado' ?></p></div>
                                        <div><p class="font-bold text-gray-700 uppercase text-xs mb-1">Benefícios</p><p class="bg-gray-50 p-3 rounded-xl whitespace-pre-line"><?= egh($v['benefits'] ?? '') ?: 'Não informado' ?></p></div>
                                    </div>
                                </details>

                                <div class="flex flex-wrap items-center gap-3">
                                    <?php if ($souAluno): ?>
                                        <a href="candidatar.php?origem=egresso&amp;job_id=<?= (int) $v['id'] ?>" target="_blank"
                                           class="px-6 py-2 bg-[#58A1D4] hover:bg-[#4A8FBA] text-white rounded-xl text-sm">
                                            <i class="fas fa-paper-plane mr-1"></i> Candidatar-se
                                        </a>
                                        <?= favoritoBotaoHtml('egresso', (int) $v['id']) ?>
                                        <?php if (!empty($jaCandidatou[(int) $v['id']])): ?>
                                            <a href="candidaturas.php" class="text-sm text-emerald-600 font-semibold">
                                                <i class="fas fa-check-circle mr-1"></i> Candidatura registrada
                                            </a>
                                        <?php endif; ?>
                                    <?php elseif ($linkDireto !== ''): ?>
                                        <a href="<?= egh($linkDireto) ?>" target="_blank" rel="noopener"
                                           class="px-6 py-2 border border-gray-200 text-gray-600 rounded-xl hover:bg-gray-50 text-sm">
                                            <i class="fas fa-external-link-alt mr-1"></i> Abrir link da vaga
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php include 'includes/footer.php'; ?>
