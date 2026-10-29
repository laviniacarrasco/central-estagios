<?php
/**
 * =============================================================================
 * VAGAS (aba única)
 * -----------------------------------------------------------------------------
 * Junta numa tela só, no mesmo layout do antigo mural de egressos:
 *   - vagas da FSA (Admin Vagas)               -> cartão branco (amarelo se for destaque)
 *   - vagas externas aprovadas (Adzuna)        -> cartão branco + selo "Vaga externa"
 *   - vagas divulgadas por egressos, aprovadas -> cartão azul + selo "Egresso"
 *
 * Classificação: primeiro "Vagas em Destaque" (destaques da FSA e vagas de
 * egressos), depois "Mais vagas". Dentro de cada bloco vale a ordem escolhida.
 *
 * O antigo egressos.php só redireciona para cá (?origem=egresso).
 * Quem não tem o recurso "vagas_egresso" (aluno restrito) não vê as de egressos.
 * =============================================================================
 */
require_once 'includes/config.php';
require_once 'includes/vagas.php';
require_once 'includes/egressos.php';
require_once 'includes/melhorias.php';
checkAuth();

$pageTitle      = 'Vagas';
$podeVerEgresso = perfilPode('vagas_egresso');
$podeCandidatar = perfilPode('candidatar');   // aluno registra a candidatura; os outros vão direto ao link
$fsaCourses     = getFsaCourses();

/* -----------------------------------------------------------------------------
 * 1) Junta todas as vagas abertas num formato só
 *    origem: 'faculdade' ou 'egresso' (usado em candidatar.php e favoritos)
 *    classe: 'fsa', 'externa' ou 'egresso' (usada nas cores e no filtro)
 * -------------------------------------------------------------------------- */
$vagas = [];
foreach (loadData('platform_jobs') as $j) {
    if (!is_array($j) || !vagaVigente($j)) continue;
    $j['origem'] = 'faculdade';
    $j['classe'] = !empty($j['externa']) ? 'externa' : 'fsa';
    $vagas[] = $j;
}
if ($podeVerEgresso) {
    foreach (egressoVagas() as $v) {
        if (egressoVagaStatusEfetivo($v) !== 'aprovada') continue;
        $vagas[] = array_merge($v, [
            'origem'          => 'egresso',
            'classe'          => 'egresso',
            'featured'        => true,   // vaga de egresso sempre vai para "Destaque"
            'createdAt'       => $v['publishedAt'] ?? ($v['createdAt'] ?? ''),
            'applicationLink' => egressoNormalizarLink($v['applicationLink'] ?? ''),
        ]);
    }
}

/** Lista de cursos da vaga (aceita "A, B" ou array) */
function jobsCursos(array $v): array {
    $c = $v['courses'] ?? '';
    $lista = is_array($c) ? $c : explode(',', (string) $c);
    return array_values(array_filter(array_map('trim', $lista), fn($x) => $x !== ''));
}

/** É destaque? (featured pode vir como true ou "true" no JSON) */
function jobsDestaque(array $v): bool {
    $f = $v['featured'] ?? false;
    return $f === true || $f === 1 || $f === '1' || $f === 'true';
}

/* -----------------------------------------------------------------------------
 * 2) Filtros (tudo pela URL, então dá para compartilhar uma busca)
 * -------------------------------------------------------------------------- */
$busca    = trim((string) ($_GET['search'] ?? ''));
$cursoSel = trim((string) ($_GET['curso'] ?? ''));
if ($cursoSel === '' && !empty($_GET['courses'])) {          // link antigo: courses[]=...
    $antigo = (array) $_GET['courses'];
    $cursoSel = trim((string) reset($antigo));
}

$origens = ['' => 'Todas as origens', 'fsa' => 'Vagas da FSA', 'externa' => 'Vagas externas'];
if ($podeVerEgresso) $origens['egresso'] = 'Vagas de egressos';
$origemSel = (string) ($_GET['origem'] ?? '');
if (!isset($origens[$origemSel])) $origemSel = '';

$cidades   = mhCidadesFiltro();
$cidadeSel = (string) ($_GET['cidade'] ?? '');
if (!isset($cidades[$cidadeSel])) $cidadeSel = '';

$modalidades = mhModalidadesFiltro();
$modSel      = (string) ($_GET['modalidade'] ?? '');
if (!isset($modalidades[$modSel])) $modSel = '';

$ordens   = mhOrdemFiltro();
$ordemSel = (string) ($_GET['ordem'] ?? 'recentes');
if (!isset($ordens[$ordemSel])) $ordemSel = 'recentes';

$buscaNorm = mhSemAcento($busca);
$filtradas = array_values(array_filter($vagas, function ($v) use ($buscaNorm, $cursoSel, $origemSel, $cidadeSel, $modSel) {
    if ($buscaNorm !== '') {
        $alvo = mhSemAcento(implode(' ', [
            $v['title'] ?? '', $v['company'] ?? '', $v['description'] ?? '', $v['location'] ?? '', implode(' ', jobsCursos($v)),
        ]));
        if (mb_strpos($alvo, $buscaNorm) === false) return false;
    }
    if ($cursoSel !== '') {
        $lista = jobsCursos($v);
        if ($lista && !in_array($cursoSel, $lista, true)) return false;   // vaga sem curso = aberta a todos
    }
    if ($origemSel !== '' && $v['classe'] !== $origemSel) return false;
    if ($cidadeSel !== '' && !mhCidadeCasa($v, $cidadeSel)) return false;
    if ($modSel !== '' && mhModalidade($v) !== $modSel) return false;
    return true;
}));

/* 3) Ordem */
usort($filtradas, function ($a, $b) use ($ordemSel) {
    if ($ordemSel === 'bolsa') return mhBolsaValor($b['salary'] ?? '') <=> mhBolsaValor($a['salary'] ?? '');
    if ($ordemSel === 'vencer') {
        $da = vagaDiasRestantes($a); $db = vagaDiasRestantes($b);
        return ($da ?? PHP_INT_MAX) <=> ($db ?? PHP_INT_MAX);   // sem prazo vai para o fim
    }
    return mhVagaTs($b) <=> mhVagaTs($a);   // mais recentes
});

$destaque = array_values(array_filter($filtradas, 'jobsDestaque'));
$demais   = array_values(array_filter($filtradas, fn($v) => !jobsDestaque($v)));
$novasSemana = count(array_filter($vagas, fn($v) => mhVagaTs($v) >= strtotime('-7 days')));
$filtrosAtivos = $busca !== '' || $cursoSel !== '' || $origemSel !== '' || $cidadeSel !== '' || $modSel !== '' || $ordemSel !== 'recentes';

/* -----------------------------------------------------------------------------
 * 4) Cartão de uma vaga (mesmo layout do antigo mural de egressos)
 * -------------------------------------------------------------------------- */
function jobsCartao(array $v, bool $podeCandidatar): void {
    $classe     = $v['classe'];
    $ehEgresso  = $classe === 'egresso';
    $ehDestaque = !$ehEgresso && jobsDestaque($v);
    $id         = (int) ($v['id'] ?? 0);
    $link       = egressoNormalizarLink((string) ($v['applicationLink'] ?? ''));
    $cursos     = jobsCursos($v);

    // Cores por classificação
    if ($ehEgresso)      { $caixa = 'border-2 border-[#4A9FCA]/40 bg-[#4A9FCA]/5'; $iconeCaixa = 'bg-[#4A9FCA]/15 text-[#2B7FA6]'; $icone = 'fa-user-graduate'; }
    elseif ($ehDestaque) { $caixa = 'border-2 border-yellow-200 bg-yellow-50/40';  $iconeCaixa = 'bg-yellow-100 text-yellow-600';  $icone = 'fa-building'; }
    else                 { $caixa = 'border border-gray-100 bg-white';             $iconeCaixa = 'bg-[#4A9FCA]/10 text-[#4A9FCA]';  $icone = 'fa-building'; }

    // Botão principal: aluno registra em Minhas Vagas; os outros perfis abrem o link direto
    $hrefCandidatar = $podeCandidatar
        ? 'candidatar.php?origem=' . rawurlencode($v['origem']) . '&job_id=' . $id
        : $link;
    ?>
    <div class="p-6 <?= $caixa ?> rounded-2xl shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-start gap-6">
            <div class="w-14 h-14 <?= $iconeCaixa ?> rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fas <?= $icone ?> text-xl"></i>
            </div>
            <div class="flex-1 min-w-0">

                <!-- Título, empresa e etiquetas -->
                <div class="flex flex-wrap justify-between items-start gap-2 mb-3">
                    <div class="min-w-0">
                        <h3 class="text-lg font-bold text-slate-900"><?= egh($v['title'] ?? '') ?></h3>
                        <p class="text-slate-500 font-semibold"><?= egh($v['company'] ?? '') ?></p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <?php if ($ehEgresso): ?>
                            <span class="px-3 py-1 bg-[#4A9FCA] text-white rounded-full text-xs font-bold"><i class="fas fa-user-graduate mr-1"></i>Egresso</span>
                            <?php if (!empty($v['categoria'])): ?>
                                <span class="px-3 py-1 bg-[#4A9FCA]/10 text-[#2B7FA6] rounded-full text-xs font-bold"><?= egh($v['categoria']) ?></span>
                            <?php endif; ?>
                        <?php elseif ($ehDestaque): ?>
                            <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-bold"><i class="fas fa-star mr-1"></i>Destaque</span>
                        <?php endif; ?>
                        <?php if (!empty($v['type'])): ?>
                            <span class="px-3 py-1 border border-slate-200 text-slate-500 rounded-full text-xs font-bold"><?= egh($v['type']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Selos: nova, perto de vencer, vaga externa, já se candidatou -->
                <?= vagaSelosHtml($v) ?>

                <!-- Local, carga horária, bolsa, data -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4 mt-3 text-sm font-medium">
                    <div class="flex items-center gap-2 text-slate-500"><i class="fas fa-map-marker-alt text-[#E35444]"></i><?= egh($v['location'] ?? '') ?: '—' ?></div>
                    <div class="flex items-center gap-2 text-slate-500"><i class="fas fa-clock text-[#58A1D4]"></i><?= egh($v['hours'] ?? '') ?: '—' ?></div>
                    <div class="flex items-center gap-2 font-bold text-[#27AE60]"><i class="fas fa-dollar-sign"></i><?= egh($v['salary'] ?? '') ?: 'A combinar' ?></div>
                    <div class="text-slate-400"><?= egh($v['createdAt'] ?? '') ?><?= vagaValidadeHtml($v) ?></div>
                </div>

                <?php if ($ehEgresso): ?>
                    <p class="text-xs text-slate-500 mb-4">
                        <i class="fas fa-user-graduate text-[#4A9FCA] mr-1"></i>
                        Divulgada por <strong><?= egh($v['egressoName'] ?? 'Egresso') ?></strong>
                        <?php if (!empty($v['egressoCurso'])): ?> &bull; egresso(a) de <?= egh($v['egressoCurso']) ?><?php endif; ?>
                        <?php if (!empty($v['egressoAno'])): ?> (turma <?= egh($v['egressoAno']) ?>)<?php endif; ?>
                    </p>
                <?php endif; ?>

                <!-- Detalhes (abre e fecha) -->
                <details class="mb-4">
                    <summary class="cursor-pointer text-sm font-semibold text-[#4A9FCA] select-none">
                        <i class="fas fa-eye mr-1"></i> Ver detalhes
                    </summary>
                    <div class="mt-3 space-y-3 text-sm text-gray-600">
                        <div><p class="font-bold text-gray-700 uppercase text-xs mb-1">Cursos indicados</p><p class="bg-gray-50 p-3 rounded-xl"><?= egh($cursos ? implode(', ', $cursos) : 'Todos os cursos') ?></p></div>
                        <div><p class="font-bold text-gray-700 uppercase text-xs mb-1">Descrição</p><p class="bg-gray-50 p-3 rounded-xl whitespace-pre-line"><?= egh($v['description'] ?? '') ?: 'Não informado' ?></p></div>
                        <?php if (!empty($v['requirements'])): ?>
                            <div><p class="font-bold text-gray-700 uppercase text-xs mb-1">Requisitos</p><p class="bg-gray-50 p-3 rounded-xl whitespace-pre-line"><?= egh($v['requirements']) ?></p></div>
                        <?php endif; ?>
                        <?php if (!empty($v['benefits'])): ?>
                            <div><p class="font-bold text-gray-700 uppercase text-xs mb-1">Benefícios</p><p class="bg-gray-50 p-3 rounded-xl whitespace-pre-line"><?= egh($v['benefits']) ?></p></div>
                        <?php endif; ?>
                    </div>
                </details>

                <!-- Ações -->
                <div class="flex flex-wrap items-center gap-2">
                    <?php if ($hrefCandidatar !== ''): ?>
                        <a href="<?= egh($hrefCandidatar) ?>" target="_blank" rel="noopener"
                           class="px-5 py-2 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl text-sm font-semibold transition-all">
                            <i class="fas fa-paper-plane mr-1"></i> <?= $podeCandidatar ? 'Candidatar-se' : 'Abrir vaga' ?>
                        </a>
                    <?php else: ?>
                        <span class="px-5 py-2 bg-gray-100 text-gray-400 rounded-xl text-sm font-semibold">Link indisponível</span>
                    <?php endif; ?>
                    <?= favoritoBotaoHtml($v['origem'], $id) ?>
                    <?= vagaCompartilharHtml($v) ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-7xl mx-auto">

        <!-- Título + atalhos de quem publica vaga -->
        <div class="mb-8 animate-fade-in flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-3xl font-bold text-slate-900 mb-2">Vagas</h2>
                <p class="text-slate-500 font-medium">Vagas da FSA, de empresas parceiras e divulgadas por ex-alunos, num lugar só.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if (perfilPode('divulgar_vaga')): ?>
                    <a href="egresso-publicar.php" class="px-6 py-3 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl font-semibold text-sm"><i class="fas fa-plus-circle mr-1"></i> Divulgar uma vaga</a>
                <?php endif; ?>
                <?php if (perfilPode('gestao')): ?>
                    <a href="admin-jobs.php" class="px-6 py-3 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl font-semibold text-sm"><i class="fas fa-cog mr-1"></i> Gerenciar vagas</a>
                <?php endif; ?>
                <?php if (perfilPode('gestao_egressos')): ?>
                    <a href="admin-egressos.php" class="px-6 py-3 border border-[#4A9FCA] text-[#2B7FA6] rounded-xl font-semibold text-sm hover:bg-blue-50"><i class="fas fa-user-graduate mr-1"></i> Painel de Egressos</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Busca e filtros -->
        <form method="GET" action="jobs.php" class="bg-white p-4 rounded-2xl border border-gray-100 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div class="md:col-span-3 relative">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" name="search" value="<?= egh($busca) ?>" placeholder="Buscar por vaga, empresa, local ou curso..."
                           class="w-full h-12 pl-12 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#4A9FCA]">
                </div>
                <button type="submit" class="h-12 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl font-semibold"><i class="fas fa-search mr-1"></i> Buscar</button>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mt-3">
                <?php
                // [nome do campo, opções, valor escolhido]
                $selects = [
                    ['origem', $origens, $origemSel],
                    ['curso', ['' => 'Todos os cursos'] + array_combine($fsaCourses, $fsaCourses), $cursoSel],
                    ['cidade', $cidades, $cidadeSel],
                    ['modalidade', $modalidades, $modSel],
                    ['ordem', $ordens, $ordemSel],
                ];
                foreach ($selects as [$nome, $opcoes, $valor]): ?>
                    <select name="<?= $nome ?>" onchange="this.form.submit()" class="h-11 px-3 bg-white border border-gray-200 rounded-xl text-sm">
                        <?php foreach ($opcoes as $k => $rot): ?>
                            <option value="<?= egh((string) $k) ?>" <?= (string) $k === $valor ? 'selected' : '' ?>><?= egh($rot) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endforeach; ?>
            </div>
            <?php if ($filtrosAtivos): ?>
                <a href="jobs.php" class="inline-block mt-3 text-sm text-red-500 hover:underline"><i class="fas fa-times mr-1"></i>Limpar filtros</a>
            <?php endif; ?>
        </form>

        <!-- Números -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="p-6 bg-[#58A1D4] text-white rounded-2xl h-28 relative overflow-hidden">
                <p class="text-white/90 text-sm font-medium mb-1"><?= $filtrosAtivos ? 'Resultado da busca' : 'Total de vagas' ?></p>
                <p class="text-4xl font-bold"><?= count($filtradas) ?></p>
                <i class="fas fa-briefcase absolute -right-3 -bottom-3 text-6xl text-white/10"></i>
            </div>
            <div class="p-6 bg-[#E35444] text-white rounded-2xl h-28 relative overflow-hidden">
                <p class="text-white/90 text-sm font-medium mb-1">Vagas em destaque</p>
                <p class="text-4xl font-bold"><?= count($destaque) ?></p>
                <i class="fas fa-star absolute -right-3 -bottom-3 text-6xl text-white/10"></i>
            </div>
            <div class="p-6 bg-[#27AE60] text-white rounded-2xl h-28 relative overflow-hidden">
                <p class="text-white/90 text-sm font-medium mb-1">Novas esta semana</p>
                <p class="text-4xl font-bold"><?= $novasSemana ?></p>
                <i class="fas fa-chart-line absolute -right-3 -bottom-3 text-6xl text-white/10"></i>
            </div>
        </div>

        <!-- Legenda das cores -->
        <div class="flex flex-wrap gap-4 text-xs text-gray-500 mb-8">
            <span class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-white border border-gray-300"></span>Vaga da FSA</span>
            <span class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-yellow-100 border border-yellow-300"></span>Destaque</span>
            <?php if ($podeVerEgresso): ?>
                <span class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-[#4A9FCA]/20 border border-[#4A9FCA]"></span>Divulgada por egresso</span>
            <?php endif; ?>
            <span class="flex items-center gap-2"><i class="fas fa-external-link-alt text-gray-400"></i>Vaga externa (empresa de fora)</span>
        </div>

        <?php if (empty($filtradas)): ?>
            <div class="p-16 text-center border-2 border-dashed border-gray-200 rounded-3xl bg-white">
                <i class="fas fa-briefcase text-6xl text-gray-200 mb-4"></i>
                <h3 class="text-xl font-bold text-slate-900 mb-2">Nenhuma vaga encontrada</h3>
                <p class="text-slate-500"><?= empty($vagas) ? 'Ainda não há vagas abertas.' : 'Tente ajustar os filtros ou a busca.' ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($destaque)): ?>
            <h3 class="text-xl font-bold text-slate-900 mb-4 flex items-center gap-2"><i class="fas fa-star text-yellow-400"></i> Vagas em Destaque</h3>
            <div class="space-y-4 mb-10">
                <?php foreach ($destaque as $v) jobsCartao($v, $podeCandidatar); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($demais)): ?>
            <h3 class="text-xl font-bold text-slate-900 mb-4 flex items-center gap-2"><i class="fas fa-briefcase text-[#4A9FCA]"></i> <?= $destaque ? 'Mais vagas' : 'Vagas' ?></h3>
            <div class="space-y-4">
                <?php foreach ($demais as $v) jobsCartao($v, $podeCandidatar); ?>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php include 'includes/footer.php'; ?>
