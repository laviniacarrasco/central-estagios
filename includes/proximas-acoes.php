<?php
/** Dashboard do aluno: primeiros passos, proximas acoes e vagas recomendadas */
require_once __DIR__ . '/melhorias.php';

$mhRestrito = !empty($_SESSION['is_restrito']);
$mhAc       = mhDashboardAcoes($mhRestrito);
$mhFeitos   = 0;
foreach ($mhAc['passos'] as $p) { if ($p[1]) $mhFeitos++; }
$mhTotal    = max(1, count($mhAc['passos']));
$mhPct      = (int) round($mhFeitos / $mhTotal * 100);
$mhCores    = [
    'amber' => 'bg-amber-50 text-amber-600',
    'blue'  => 'bg-[#4A9FCA]/10 text-[#2B7FA6]',
    'red'   => 'bg-red-50 text-red-500',
    'green' => 'bg-emerald-50 text-emerald-600',
];
?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">

    <!-- Primeiros passos -->
    <div class="bg-white p-8 rounded-[24px] shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-sm text-slate-400 uppercase tracking-widest font-bold">Primeiros passos</h4>
            <i class="fas fa-flag-checkered text-[#4A9FCA] text-xl"></i>
        </div>
        <p class="text-sm text-slate-500 mb-3"><?= $mhFeitos ?> de <?= $mhTotal ?> concluídos</p>
        <div class="h-2 bg-gray-100 rounded-full mb-6">
            <div class="h-full bg-[#4A9FCA] rounded-full" style="width: <?= $mhPct ?>%"></div>
        </div>
        <div class="space-y-3">
            <?php foreach ($mhAc['passos'] as $p): ?>
                <a href="<?= egh($p[2]) ?>" class="flex items-center gap-3 p-3 rounded-xl transition-colors <?= $p[1] ? 'bg-emerald-50' : 'bg-gray-50 hover:bg-blue-50' ?>">
                    <span class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 <?= $p[1] ? 'bg-[#27AE60] text-white' : 'bg-white border border-gray-200 text-gray-400' ?>">
                        <i class="fas <?= $p[1] ? 'fa-check' : egh($p[3]) ?> text-xs"></i>
                    </span>
                    <span class="text-sm <?= $p[1] ? 'text-gray-400 line-through' : 'text-gray-700 font-semibold' ?>"><?= egh($p[0]) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Proximas acoes -->
    <div class="bg-white p-8 rounded-[24px] shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-sm text-slate-400 uppercase tracking-widest font-bold">Próximas ações</h4>
            <i class="fas fa-bolt text-[#F39C12] text-xl"></i>
        </div>
        <?php if (empty($mhAc['acoes'])): ?>
            <div class="p-8 text-center border-2 border-dashed border-gray-200 rounded-2xl">
                <i class="fas fa-check-circle text-4xl text-gray-200 mb-3"></i>
                <p class="text-sm font-semibold text-slate-700 mb-1">Tudo em dia!</p>
                <p class="text-xs text-slate-400">Entrevistas, prazos e novidades aparecem aqui.</p>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($mhAc['acoes'] as $a): ?>
                    <a href="<?= egh($a['link']) ?>" class="flex items-start gap-3 p-3 bg-gray-50 hover:bg-blue-50 rounded-xl transition-colors">
                        <span class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 <?= $mhCores[$a['cor']] ?? $mhCores['blue'] ?>">
                            <i class="fas <?= egh($a['icone']) ?> text-sm"></i>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-gray-800"><?= egh($a['titulo']) ?></span>
                            <span class="block text-xs text-gray-500"><?= egh($a['texto']) ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($mhAc['rec'])): ?>
<div class="mb-12">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i class="fas fa-star text-yellow-400"></i> Vagas recomendadas para você
        </h3>
        <a href="<?= $mhRestrito ? 'jobs.php' : 'vagas-para-voce.php' ?>" class="text-sm text-[#4A9FCA] font-semibold hover:underline">Ver todas</a>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php foreach ($mhAc['rec'] as $r): $v = $r['vaga']; [$mhRot, $mhCls] = matchRotulo((int) $r['score']); ?>
            <a href="jobs.php?search=<?= urlencode((string) ($v['title'] ?? '')) ?>" class="block bg-white p-6 rounded-[24px] shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold border mb-3 <?= $mhCls ?>"><?= egh($mhRot) ?></span>
                <h4 class="text-lg font-bold text-slate-900 leading-snug"><?= egh($v['title'] ?? '') ?></h4>
                <p class="text-slate-500 font-semibold"><?= egh($v['company'] ?? '') ?></p>
                <p class="text-xs text-gray-400 mt-2"><i class="fas fa-map-marker-alt mr-1 text-[#E35444]"></i><?= egh($v['location'] ?? '') ?></p>
                <?php if (!empty($r['motivos'][0])): ?>
                    <p class="text-xs text-gray-500 mt-3"><i class="fas fa-check-circle text-[#27AE60] mr-1"></i><?= egh($r['motivos'][0]) ?></p>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
