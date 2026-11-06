<?php
/**
 * =============================================================================
 * VAGAS PARA VOCÊ
 * -----------------------------------------------------------------------------
 * As vagas abertas que mais combinam com o currículo do aluno.
 *   - Com a IA (Gemini): botão "Analisar com IA" compara o currículo com todas
 *     as vagas e devolve nota + motivo. O resultado fica guardado por 24 h
 *     (ou até o currículo mudar), então a página abre rápido.
 *   - Sem IA (ou se o Gemini falhar): comparação por regras (includes/matching.php).
 * Lógica: includes/ia-gemini.php. Serviço da IA: ia-curriculo.php.
 * =============================================================================
 */
require_once 'includes/config.php';
require_once 'includes/ia-gemini.php';
checkAuth();

$pageTitle = 'Vagas para Você';
$usuario = egressoCarregarUsuario((int) $_SESSION['user_id']) ?: [];
$res     = iaVagasParaTela($usuario, 20);
$lista   = $res['lista'];
$podeIa  = iaDisponivel();
$consentiu = $podeIa && iaConsentiu((int) $_SESSION['user_id']);
$perfil  = matchPerfilAluno($usuario);

$jaCandidatou = [];
foreach (loadData('userCandidaturas', true) as $c) {
    $jaCandidatou[($c['origem'] ?? '') . ':' . (int) ($c['jobId'] ?? 0)] = true;
}
$quandoIa = $res['quando'] ? date('d/m \à\s H:i', strtotime($res['quando'])) : '';

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-5xl mx-auto animate-fade-in">

        <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
            <div>
                <h2 class="text-3xl font-bold text-slate-900 mb-1">Vagas para Você</h2>
                <p class="text-slate-500">As vagas abertas que mais combinam com o seu currículo.</p>
            </div>
            <?php if ($podeIa): ?>
                <button type="button" id="btnIaVagas" class="fsa-btn fsa-btn-primario">
                    <i class="fas fa-wand-magic-sparkles mr-2"></i><?= $res['fonte'] === 'ia' ? 'Analisar de novo com IA' : 'Analisar com IA' ?>
                </button>
            <?php endif; ?>
        </div>

        <!-- De onde vem a lista -->
        <?php if ($res['fonte'] === 'ia'): ?>
            <div class="mb-6 flex items-start gap-3 p-4 rounded-2xl border <?= $res['desatualizada'] ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-[#eef7fc] border-[#d6ebf7] text-[#2B7FA6]' ?> text-sm">
                <i class="fas <?= $res['desatualizada'] ? 'fa-clock' : 'fa-wand-magic-sparkles' ?> mt-0.5"></i>
                <p class="flex-1">
                    <strong>Análise da IA de <?= htmlspecialchars($quandoIa) ?>.</strong>
                    <?= $res['desatualizada'] ? 'Seu currículo ou as vagas mudaram desde então. Clique em "Analisar de novo com IA" para atualizar.' : 'A nota mostra o quanto cada vaga combina com o que está no seu currículo.' ?>
                </p>
            </div>
        <?php else: ?>
            <div class="mb-6 flex items-start gap-3 p-4 rounded-2xl bg-white border border-slate-200 text-sm text-slate-600">
                <i class="fas fa-info-circle text-slate-400 mt-0.5"></i>
                <p class="flex-1">
                    Comparação pelo seu curso, habilidades, experiências e idiomas com o texto das vagas.
                    <?php if ($podeIa): ?><strong class="text-slate-800">Para uma análise mais precisa, use "Analisar com IA".</strong><?php endif; ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if (!$perfil['temCurriculo']): ?>
            <div class="mb-6 p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl text-sm">
                <i class="fas fa-lightbulb mr-1"></i>
                Seu currículo ainda não tem habilidades nem experiências.
                <a href="curriculum.php" class="font-semibold underline">Complete seu currículo</a> para receber sugestões melhores.
            </div>
        <?php endif; ?>

        <div id="iaCarregando" class="hidden mb-6 p-6 rounded-2xl bg-white border border-slate-200 text-center">
            <i class="fas fa-circle-notch fa-spin text-2xl text-[#4A9FCA] mb-3"></i>
            <p class="font-semibold text-slate-800">A IA está lendo seu currículo e as vagas...</p>
            <p class="text-sm text-slate-500">Isso leva de 10 a 40 segundos.</p>
        </div>

        <?php if (empty($lista)): ?>
            <div class="p-16 text-center bg-white rounded-3xl border-2 border-dashed border-slate-200">
                <div class="w-16 h-16 rounded-2xl bg-slate-50 text-slate-300 flex items-center justify-center mx-auto mb-4"><i class="fas fa-star text-3xl"></i></div>
                <h3 class="text-xl font-bold text-slate-900 mb-2">Nenhuma vaga combina por enquanto</h3>
                <p class="text-slate-500 mb-6">Quando surgirem vagas para o seu perfil, elas aparecem aqui.</p>
                <a href="jobs.php" class="fsa-btn fsa-btn-primario">Ver todas as vagas</a>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($lista as $m):
                    $v = $m['vaga']; $origem = $m['origem']; $s = (int) $m['score'];
                    [$rotulo, $classe] = matchRotulo($s);
                    $jaFoi = !empty($jaCandidatou[$origem . ':' . (int) $v['id']]);
                    $cor = $s >= 70 ? '#27AE60' : ($s >= 45 ? '#4A9FCA' : '#F39C12');
                ?>
                    <article class="bg-white rounded-2xl p-6">
                        <div class="flex flex-col sm:flex-row gap-5">
                            <div class="relative w-20 h-20 flex-shrink-0">
                                <svg viewBox="0 0 36 36" class="w-20 h-20 -rotate-90">
                                    <circle cx="18" cy="18" r="15.9" fill="none" stroke="#eef2f6" stroke-width="3.5"></circle>
                                    <circle cx="18" cy="18" r="15.9" fill="none" stroke="<?= $cor ?>" stroke-width="3.5" stroke-linecap="round" stroke-dasharray="<?= $s ?> 100"></circle>
                                </svg>
                                <span class="absolute inset-0 flex items-center justify-center text-lg font-bold text-slate-900"><?= $s ?>%</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <h3 class="text-lg font-bold text-slate-900"><?= egh($v['title'] ?? '') ?></h3>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold border <?= $classe ?>"><?= egh($rotulo) ?></span>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $origem === 'egresso' ? 'bg-[#e8f3fa] text-[#2B7FA6]' : 'bg-slate-100 text-slate-600' ?>"><?= $origem === 'egresso' ? 'Vaga de egresso' : 'Vaga FSA' ?></span>
                                </div>
                                <p class="text-slate-600 font-semibold"><?= egh($v['company'] ?? '') ?></p>
                                <p class="text-sm text-slate-400 mt-1">
                                    <?php if (!empty($v['location'])): ?><i class="fas fa-map-marker-alt mr-1"></i><?= egh($v['location']) ?><?php endif; ?>
                                    <?php if (!empty($v['salary'])): ?><span class="ml-3"><i class="fas fa-wallet mr-1"></i><?= egh($v['salary']) ?></span><?php endif; ?>
                                </p>
                                <?= vagaValidadeHtml($v) ?>

                                <ul class="mt-3 space-y-1.5">
                                    <?php foreach ($m['motivos'] as $motivo): if (trim((string) $motivo) === '') continue; ?>
                                        <li class="flex items-start gap-2 text-sm text-slate-600">
                                            <i class="fas <?= !empty($m['ia']) ? 'fa-wand-magic-sparkles text-[#4A9FCA]' : 'fa-check text-emerald-500' ?> mt-1 text-xs"></i>
                                            <span><?= egh($motivo) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>

                                <div class="flex flex-wrap items-center gap-3 mt-4">
                                    <a href="candidatar.php?origem=<?= egh($origem) ?>&amp;job_id=<?= (int) $v['id'] ?>" target="_blank" class="fsa-btn fsa-btn-primario h-10">
                                        <i class="fas fa-paper-plane mr-2"></i>Candidatar-se
                                    </a>
                                    <?= favoritoBotaoHtml($origem, (int) $v['id']) ?>
                                    <?php if ($jaFoi): ?>
                                        <a href="minhas-vagas.php?aba=candidaturas" class="text-sm text-emerald-600 font-semibold"><i class="fas fa-check-circle mr-1"></i>Candidatura registrada</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p class="text-xs text-slate-400 mt-6 text-center">É uma sugestão para ajudar na sua busca, não uma garantia de aprovação.</p>
    </div>
</main>

<?php if ($podeIa): ?>
<script>
(function () {
    var btn = document.getElementById('btnIaVagas');
    var consentiu = <?= $consentiu ? 'true' : 'false' ?>;
    function post(acao) {
        var f = new FormData(); f.append('acao', acao);
        return fetch('ia-curriculo.php', { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: f }).then(function (r) { return r.json(); });
    }
    function analisar() {
        btn.disabled = true; btn.classList.add('opacity-60');
        document.getElementById('iaCarregando').classList.remove('hidden');
        post('vagas').then(function (d) {
            if (d.ok) { fsaTrocarPartes(['main']); return; }
            fsaAviso(d.erro || 'Não foi possível analisar agora.', 'A IA não respondeu');
        }).catch(function () {
            fsaAviso('Não foi possível falar com a IA. Tente de novo em alguns minutos.', 'A IA não respondeu');
        }).then(function () {
            btn.disabled = false; btn.classList.remove('opacity-60');
            document.getElementById('iaCarregando').classList.add('hidden');
        });
    }
    btn.addEventListener('click', function () {
        if (consentiu) return analisar();
        fsaConfirmar({
            titulo: 'Usar a IA no seu currículo?',
            texto: 'Seu currículo será enviado ao Google Gemini SEM nome, e-mail e telefone, só para comparar com as vagas. Você pode retirar a autorização nas Configurações.',
            botao: 'Concordo', tipo: 'aviso', icone: 'fa-wand-magic-sparkles'
        }, function () {
            post('consentir').then(function () { consentiu = true; analisar(); });
        });
    });
})();
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
