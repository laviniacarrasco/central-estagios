<?php
require_once 'includes/config.php';
require_once 'includes/matching.php';
require_once 'includes/egressos.php';
checkAuth();

$pageTitle = 'Vagas para Você';
$usuario = egressoCarregarUsuario((int) $_SESSION['user_id']) ?: [];

$perfil = matchPerfilAluno($usuario);

$candidatas = [];
foreach (array_values(loadData('platform_jobs')) as $j) {
    if (vagaVigente($j)) $candidatas[] = ['origem' => 'faculdade', 'vaga' => $j];
}
foreach (egressoVagas() as $j) {
    if (egressoVagaStatusEfetivo($j) === 'aprovada') $candidatas[] = ['origem' => 'egresso', 'vaga' => $j];
}

$lista = matchVagasPara($perfil, $candidatas, 25, 20);

// ja candidatou?
$jaCandidatou = [];
foreach (loadData('userCandidaturas', true) as $c) {
    $jaCandidatou[($c['origem'] ?? '') . ':' . (int) ($c['jobId'] ?? 0)] = true;
}

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-5xl mx-auto">

        <div class="mb-6 animate-fade-in">
            <h2 class="text-3xl font-bold text-slate-900 mb-2">Vagas para Você</h2>
            <p class="text-slate-500 font-medium">Vagas abertas que mais combinam com o seu curso e o que você escreveu no currículo.</p>
        </div>

        <?php if (!$perfil['temCurriculo']): ?>
            <div class="mb-6 p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-sm">
                <i class="fas fa-lightbulb mr-1"></i>
                Seu currículo ainda não tem habilidades nem experiências, então só consigo olhar o seu curso.
                <a href="curriculum.php" class="font-semibold underline">Complete seu currículo</a> para receber sugestões melhores.
            </div>
        <?php endif; ?>

        <p class="text-xs text-gray-400 mb-6">
            A compatibilidade é calculada comparando seu curso, habilidades, experiências, idiomas e competências de destaque do Perfil com o texto da vaga.
            É uma sugestão por palavras em comum, não uma garantia de aprovação.
        </p>

        <?php if (empty($lista)): ?>
            <div class="p-16 text-center border-2 border-dashed border-gray-200 rounded-3xl bg-white">
                <i class="fas fa-star text-6xl text-gray-200 mb-4"></i>
                <h3 class="text-xl font-bold text-slate-900 mb-2">Nenhuma vaga combina por enquanto</h3>
                <p class="text-slate-500 mb-6">Quando surgirem vagas para o seu curso, elas aparecem aqui.</p>
                <a href="jobs.php" class="px-6 py-3 bg-[#4A9FCA] text-white rounded-xl font-semibold text-sm">Ver todas as vagas</a>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($lista as $m):
                    $v = $m['vaga']; $origem = $m['origem'];
                    [$rotulo, $classe] = matchRotulo($m['score']);
                    $jaFoi = !empty($jaCandidatou[$origem . ':' . (int) $v['id']]);
                ?>
                    <div class="p-6 bg-white border border-gray-100 rounded-2xl shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap mb-1">
                                    <h3 class="text-lg font-bold text-slate-900"><?= egh($v['title'] ?? '') ?></h3>
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $origem === 'egresso' ? 'bg-purple-50 text-purple-700' : 'bg-sky-50 text-sky-700' ?>">
                                        <?= $origem === 'egresso' ? 'Vaga de egresso' : 'Vaga FSA' ?>
                                    </span>
                                </div>
                                <p class="text-slate-500 font-semibold"><?= egh($v['company'] ?? '') ?></p>
                                <p class="text-xs text-gray-400 mt-1">
                                    <?php if (!empty($v['location'])): ?><i class="fas fa-map-marker-alt mr-1"></i><?= egh($v['location']) ?> &bull; <?php endif; ?>
                                    <?= egh($v['salary'] ?? '') ?>
                                </p>
                                <?= vagaValidadeHtml($v) ?>
                            </div>
                            <div class="text-right">
                                <p class="text-3xl font-bold text-slate-900"><?= (int) $m['score'] ?>%</p>
                                <span class="px-3 py-1 rounded-full text-xs font-bold border <?= $classe ?>"><?= egh($rotulo) ?></span>
                            </div>
                        </div>

                        <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden mb-3">
                            <div class="h-2 bg-[#4A9FCA] rounded-full" style="width: <?= (int) $m['score'] ?>%"></div>
                        </div>

                        <ul class="text-sm text-gray-600 mb-4 space-y-1">
                            <?php foreach ($m['motivos'] as $motivo): ?>
                                <li><i class="fas fa-check text-emerald-500 mr-1"></i> <?= egh($motivo) ?></li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="flex flex-wrap items-center gap-3">
                            <a href="candidatar.php?origem=<?= egh($origem) ?>&amp;job_id=<?= (int) $v['id'] ?>" target="_blank"
                               class="px-6 py-2 bg-[#58A1D4] hover:bg-[#4A8FBA] text-white rounded-xl text-sm">
                                <i class="fas fa-paper-plane mr-1"></i> Candidatar-se
                            </a>
                            <?= favoritoBotaoHtml($origem, (int) $v['id']) ?>
                            <?php if ($jaFoi): ?>
                                <a href="minhas-vagas.php?aba=candidaturas" class="text-sm text-emerald-600 font-semibold"><i class="fas fa-check-circle mr-1"></i> Candidatura registrada</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
