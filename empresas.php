<?php
/**
 * Empresas parceiras da FSA (consulta do aluno).
 * O cadastro e feito pelo admin em admin-empresas.php.
 */
require_once 'includes/config.php';
require_once 'includes/empresas.php';
checkAuth();

$pageTitle  = 'Empresas Parceiras';
$fsaCourses = getFsaCourses();
$busca      = trim((string) ($_GET['search'] ?? ''));
$cursoSel   = trim((string) ($_GET['curso'] ?? ''));
$soComVagas = !empty($_GET['com_vagas']);

$todas = array_values(array_filter(empresasLista(), function ($e) {
    return !empty($e['ativa']);
}));
$contagem = empresasContarVagas($todas);

$empresas = array_values(array_filter($todas, function ($e) use ($busca, $cursoSel, $soComVagas, $contagem) {
    if ($busca !== '') {
        $alvo = ($e['nome'] ?? '') . ' ' . ($e['setor'] ?? '') . ' ' . ($e['descricao'] ?? '');
        if (mb_stripos($alvo, $busca) === false) return false;
    }
    if ($cursoSel !== '') {
        $lista = empresasCursosLista($e['cursos'] ?? '');
        if (!empty($lista) && !in_array($cursoSel, $lista, true)) return false;
    }
    if ($soComVagas && ($contagem[(int) ($e['id'] ?? 0)]['total'] ?? 0) <= 0) return false;
    return true;
}));
usort($empresas, function ($a, $b) use ($contagem) {
    $va = $contagem[(int) ($a['id'] ?? 0)]['total'] ?? 0;
    $vb = $contagem[(int) ($b['id'] ?? 0)]['total'] ?? 0;
    if ($va !== $vb) return $vb <=> $va;
    return strcasecmp((string) ($a['nome'] ?? ''), (string) ($b['nome'] ?? ''));
});

$totalComVagas = 0;
foreach ($todas as $e) {
    if (($contagem[(int) ($e['id'] ?? 0)]['total'] ?? 0) > 0) $totalComVagas++;
}

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-7xl mx-auto">

        <div class="mb-8 animate-fade-in">
            <h2 class="text-3xl font-bold text-slate-900 mb-2">Empresas Parceiras</h2>
            <p class="text-slate-500 font-medium">Empresas que têm relacionamento com a FSA e costumam abrir oportunidades para os nossos alunos.</p>
        </div>

        <form method="GET" action="empresas.php" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-8">
            <div class="md:col-span-2 relative">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="search" value="<?= egh($busca) ?>" placeholder="Buscar por empresa, setor ou descrição..."
                       class="w-full h-12 pl-12 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#4A9FCA]">
            </div>
            <select name="curso" class="h-12 px-4 bg-white border border-gray-200 rounded-xl" onchange="this.form.submit()">
                <option value="">Todos os cursos</option>
                <?php foreach ($fsaCourses as $c): ?>
                    <option value="<?= egh($c) ?>" <?= $c === $cursoSel ? 'selected' : '' ?>><?= egh($c) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="h-12 px-4 bg-white border border-gray-200 rounded-xl flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                <input type="checkbox" name="com_vagas" value="1" <?= $soComVagas ? 'checked' : '' ?> onchange="this.form.submit()">
                Só com vagas abertas
            </label>
        </form>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="p-6 bg-[#58A1D4] text-white rounded-2xl h-28">
                <p class="text-white/90 text-sm font-medium mb-1">Empresas parceiras</p>
                <p class="text-4xl font-bold"><?= count($todas) ?></p>
            </div>
            <div class="p-6 bg-[#27AE60] text-white rounded-2xl h-28">
                <p class="text-white/90 text-sm font-medium mb-1">Com vagas abertas agora</p>
                <p class="text-4xl font-bold"><?= $totalComVagas ?></p>
            </div>
            <div class="p-6 bg-[#E35444] text-white rounded-2xl h-28">
                <p class="text-white/90 text-sm font-medium mb-1">Resultado da busca</p>
                <p class="text-4xl font-bold"><?= count($empresas) ?></p>
            </div>
        </div>

        <?php if (empty($empresas)): ?>
            <div class="p-16 text-center border-2 border-dashed border-gray-200 rounded-3xl bg-white">
                <i class="fas fa-handshake text-6xl text-gray-200 mb-4"></i>
                <h3 class="text-xl font-bold text-slate-900 mb-2">Nenhuma empresa encontrada</h3>
                <p class="text-slate-500">
                    <?= empty($todas) ? 'Ainda não há empresas parceiras cadastradas.' : 'Tente ajustar a busca ou os filtros.' ?>
                </p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <?php foreach ($empresas as $e):
                    $eid    = (int) ($e['id'] ?? 0);
                    $vagas  = $contagem[$eid] ?? ['total' => 0];
                    $cursos = empresasCursosLista($e['cursos'] ?? '');
                    $logo   = (string) ($e['logo'] ?? '');
                    $temLogo = $logo !== '' && is_file(__DIR__ . '/' . $logo);
                    $site   = (string) ($e['site'] ?? '');
                    $emailRh = trim((string) ($e['email'] ?? ''));
                ?>
                    <div class="p-6 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md transition-shadow flex flex-col">
                        <div class="flex items-start gap-4 mb-4">
                            <?php if ($temLogo): ?>
                                <img src="<?= egh($logo) ?>" alt="" class="w-16 h-16 rounded-xl object-contain border border-gray-100 bg-white p-1 flex-shrink-0">
                            <?php else: ?>
                                <div class="w-16 h-16 bg-gradient-to-br from-[#4A9FCA] to-[#3A8FB0] rounded-xl flex items-center justify-center text-white font-bold text-2xl flex-shrink-0">
                                    <?= egh(mb_strtoupper(mb_substr((string) ($e['nome'] ?? '?'), 0, 1))) ?>
                                </div>
                            <?php endif; ?>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-lg font-bold text-slate-900"><?= egh($e['nome'] ?? '') ?></h3>
                                <?php if (!empty($e['setor'])): ?>
                                    <p class="text-sm text-slate-500"><?= egh($e['setor']) ?></p>
                                <?php endif; ?>
                            </div>
                            <?php if ($vagas['total'] > 0): ?>
                                <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 flex-shrink-0">
                                    <?= (int) $vagas['total'] ?> vaga<?= $vagas['total'] > 1 ? 's' : '' ?> aberta<?= $vagas['total'] > 1 ? 's' : '' ?>
                                </span>
                            <?php else: ?>
                                <span class="px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-500 flex-shrink-0">Sem vagas agora</span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($e['descricao'])): ?>
                            <p class="text-sm text-gray-600 leading-relaxed mb-4 whitespace-pre-line"><?= egh($e['descricao']) ?></p>
                        <?php endif; ?>

                        <div class="flex flex-wrap gap-2 mb-4">
                            <?php if (empty($cursos)): ?>
                                <span class="px-3 py-1 bg-[#eaf4fa] text-[#2B7FA6] rounded-full text-xs font-semibold">Todos os cursos</span>
                            <?php else: ?>
                                <?php foreach ($cursos as $c): ?>
                                    <span class="px-3 py-1 bg-[#eaf4fa] text-[#2B7FA6] rounded-full text-xs font-semibold"><?= egh($c) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="mt-auto pt-2 flex flex-wrap gap-3">
                            <?php if ($vagas['total'] > 0): ?>
                                <a href="jobs.php?search=<?= urlencode((string) ($e['nome'] ?? '')) ?>"
                                   class="px-5 py-2.5 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl text-sm font-semibold transition-all">
                                    <i class="fas fa-briefcase mr-1"></i> Ver vagas
                                </a>
                            <?php endif; ?>
                            <?php if ($site !== ''): ?>
                                <a href="<?= egh($site) ?>" target="_blank" rel="noopener"
                                   class="px-5 py-2.5 border border-gray-200 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-50 transition-all">
                                    <i class="fas fa-external-link-alt mr-1"></i> Site
                                </a>
                            <?php endif; ?>
                            <?php if ($emailRh !== ''): ?>
                                <a href="mailto:<?= egh($emailRh) ?>"
                                   class="px-5 py-2.5 border border-gray-200 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-50 transition-all">
                                    <i class="fas fa-envelope mr-1"></i> Contato
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php include 'includes/footer.php'; ?>
