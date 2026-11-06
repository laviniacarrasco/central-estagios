<?php
/**
 * =============================================================================
 * CURRÍCULO: porcentagem + avaliação da IA (Gemini)
 * -----------------------------------------------------------------------------
 * Incluído no topo do curriculum.php.
 *   - Porcentagem: regras de mhCurriculoPontuacao (includes/melhorias.php).
 *     É a ÚNICA porcentagem da Central (o perfil não tem).
 *   - Avaliação da IA: nota, pontos fortes, o que melhorar e palavras-chave.
 *     Fica guardada e só é refeita quando o currículo muda (ia-gemini.php).
 * v33: aluno com acesso restrito também tem a avaliação da IA (só do currículo).
 * =============================================================================
 */
require_once __DIR__ . '/ia-gemini.php';

$cviCv    = loadData('userCurriculum', true);
$cviCv    = is_array($cviCv) ? $cviCv : [];
$cviPont  = mhCurriculoPontuacao($cviCv);
$cviPct   = (int) $cviPont['pct'];
$cviCor   = $cviPct >= 80 ? '#27AE60' : ($cviPct >= 50 ? '#F39C12' : '#E35444');
$cviIa    = iaCvDisponivel();   // v33: aluno restrito também usa a IA do currículo
$cviUser  = egressoCarregarUsuario((int) $_SESSION['user_id']) ?: [];
$cviAval  = $cviIa ? iaAvaliacaoGuardada((int) $_SESSION['user_id'], $cviCv, (string) ($cviUser['curso'] ?? '')) : null;
$cviConsentiu = $cviIa && iaConsentiu((int) $_SESSION['user_id']);
$cviRes   = $cviAval['resultado'] ?? null;
?>
<div id="cvIaBloco" class="grid grid-cols-1 <?= $cviIa ? 'lg:grid-cols-5' : '' ?> gap-6 mb-6 items-start">

    <!-- Porcentagem do currículo -->
    <section class="<?= $cviIa ? 'lg:col-span-2' : '' ?> bg-white rounded-2xl p-6">
        <div class="flex items-center gap-5 mb-5">
            <div class="relative w-24 h-24 flex-shrink-0">
                <svg viewBox="0 0 36 36" class="w-24 h-24 -rotate-90">
                    <circle cx="18" cy="18" r="15.9" fill="none" stroke="#eef2f6" stroke-width="3.2"></circle>
                    <circle cx="18" cy="18" r="15.9" fill="none" stroke="<?= $cviCor ?>" stroke-width="3.2" stroke-linecap="round" stroke-dasharray="<?= $cviPct ?> 100"></circle>
                </svg>
                <span class="absolute inset-0 flex items-center justify-center text-2xl font-bold text-slate-900"><?= $cviPct ?>%</span>
            </div>
            <div>
                <h3 class="font-bold text-slate-900 text-lg">Currículo <?= $cviPct >= 100 ? 'completo' : 'em construção' ?></h3>
                <p class="text-sm text-slate-500">Quanto mais completo, mais ele combina com as vagas.</p>
            </div>
        </div>
        <?php if (empty($cviPont['faltando'])): ?>
            <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm"><i class="fas fa-check-circle mr-1"></i> Tudo preenchido. Revise de tempos em tempos.</div>
        <?php else: ?>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-2">O que falta</p>
            <ul class="space-y-2">
                <?php foreach (array_slice($cviPont['faltando'], 0, 5) as $iFalta => $f): ?>
                    <?php if ($iFalta === 2): ?></ul><ul id="cvFaltaMais" class="space-y-2 mt-2"><?php endif; ?>
                    <li class="flex items-start gap-3 p-3 rounded-xl bg-amber-50/70">
                        <span class="text-xs font-bold text-amber-700 bg-amber-100 rounded-lg px-2 py-0.5 mt-0.5">+<?= (int) $f['pts'] ?></span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-800"><?= egh($f['label']) ?></p>
                            <p class="text-xs text-slate-500"><?= egh($f['dica']) ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($cviPont['faltando']) > 2): ?>
                <button type="button" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-[#2B7FA6]"
                        data-recolher="cvFalta" aria-controls="cvFaltaMais" data-padrao="fechado"
                        data-txt-fechado="Ver mais <?= min(5, count($cviPont['faltando'])) - 2 ?>" data-txt-aberto="Mostrar menos">
                    <span data-rotulo>Ver mais</span> <i class="fas fa-chevron-down text-xs"></i>
                </button>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php if ($cviIa): ?>
    <!-- Avaliação da IA -->
    <section class="lg:col-span-3 bg-white rounded-2xl p-6" id="cvIaCard">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div class="flex items-center gap-3">
                <span class="w-11 h-11 rounded-2xl bg-gradient-to-br from-[#4A9FCA] to-[#2B7FA6] text-white flex items-center justify-center"><i class="fas fa-wand-magic-sparkles"></i></span>
                <div>
                    <h3 class="font-bold text-slate-900 text-lg">Avaliação da IA</h3>
                    <p class="text-sm text-slate-500"><?= $cviRes ? 'Feita em ' . htmlspecialchars(date('d/m/Y \à\s H:i', strtotime($cviAval['quando']))) : 'Uma opinião sobre o seu currículo, com dicas práticas.' ?></p>
                </div>
            </div>
            <button type="button" id="btnIaAvaliar" class="fsa-btn <?= $cviRes ? 'fsa-btn-secundario' : 'fsa-btn-primario' ?>">
                <i class="fas fa-wand-magic-sparkles mr-2"></i><?= $cviRes ? 'Avaliar de novo' : 'Avaliar com IA' ?>
            </button>
        </div>

        <div id="iaAvalCarregando" class="hidden py-10 text-center">
            <i class="fas fa-circle-notch fa-spin text-2xl text-[#4A9FCA] mb-3"></i>
            <p class="font-semibold text-slate-800">A IA está lendo seu currículo...</p>
            <p class="text-sm text-slate-500">Leva de 10 a 30 segundos.</p>
        </div>

        <div id="iaAvalConteudo">
        <?php if (!$cviRes): ?>
            <div class="p-6 rounded-2xl bg-gradient-to-br from-[#eef7fc] to-white border border-[#d6ebf7]">
                <ul class="space-y-2 text-sm text-slate-600">
                    <li><i class="fas fa-check text-[#4A9FCA] mr-2"></i>Nota de 0 a 100 e uma impressão geral</li>
                    <li><i class="fas fa-check text-[#4A9FCA] mr-2"></i>Pontos fortes e o que melhorar, seção por seção</li>
                    <li><i class="fas fa-check text-[#4A9FCA] mr-2"></i>Palavras-chave da sua área que fazem falta</li>
                    <li><i class="fas fa-check text-[#4A9FCA] mr-2"></i>Nota de leitura por robôs de recrutamento (ATS)</li>
                </ul>
                <p class="text-xs text-slate-400 mt-4"><i class="fas fa-lock mr-1"></i>Nome, e-mail e telefone não são enviados.</p>
            </div>
        <?php else: ?>
            <?php if (empty($cviAval['atual'])): ?>
                <div class="mb-4 p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm"><i class="fas fa-clock mr-1"></i> Você mudou o currículo depois desta avaliação. Clique em "Avaliar de novo" para atualizar.</div>
            <?php endif; ?>
            <div class="flex items-center gap-4 mb-4">
                <div class="text-center px-4 py-3 rounded-2xl <?= $cviRes['nota'] >= 70 ? 'bg-emerald-50 text-emerald-700' : ($cviRes['nota'] >= 50 ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-700') ?>">
                    <p class="text-3xl font-bold leading-none"><?= (int) $cviRes['nota'] ?></p>
                    <p class="text-[11px] font-semibold mt-1">de 100</p>
                </div>
                <p class="text-sm text-slate-700 leading-relaxed flex-1"><?= egh($cviRes['resumo']) ?></p>
            </div>
            <button type="button" class="w-full flex items-center justify-between gap-2 px-4 py-2.5 rounded-xl bg-slate-50 hover:bg-slate-100 text-sm font-semibold text-[#2B7FA6]"
                    data-recolher="cvIaDetalhes" aria-controls="cvIaDetalhes" data-padrao="fechado"
                    data-txt-fechado="Ver a análise completa (pontos fortes, o que melhorar, ATS)" data-txt-aberto="Mostrar menos">
                <span data-rotulo>Ver a análise completa</span> <i class="fas fa-chevron-down text-xs"></i>
            </button>
            <div id="cvIaDetalhes" class="mt-4">
            <div class="grid md:grid-cols-2 gap-4">
                <?php if (!empty($cviRes['pontosFortes'])): ?>
                <div class="p-4 rounded-2xl bg-emerald-50/60">
                    <p class="text-xs font-bold text-emerald-700 uppercase tracking-wide mb-2"><i class="fas fa-thumbs-up mr-1"></i> Pontos fortes</p>
                    <ul class="space-y-1.5 text-sm text-slate-700">
                        <?php foreach ($cviRes['pontosFortes'] as $p): ?><li class="flex gap-2"><span class="text-emerald-500">•</span><span><?= egh($p) ?></span></li><?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <?php if (!empty($cviRes['melhorar'])): ?>
                <div class="p-4 rounded-2xl bg-amber-50/60">
                    <p class="text-xs font-bold text-amber-700 uppercase tracking-wide mb-2"><i class="fas fa-pen mr-1"></i> O que melhorar</p>
                    <ul class="space-y-2 text-sm text-slate-700">
                        <?php foreach ($cviRes['melhorar'] as $m): ?>
                            <li><span class="text-[11px] font-bold text-amber-700 bg-amber-100 rounded px-1.5 py-0.5 mr-1"><?= egh($m['secao']) ?></span><?= egh($m['dica']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($cviRes['palavrasChave'])): ?>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mt-4 mb-2">Palavras-chave para considerar</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($cviRes['palavrasChave'] as $k): ?><span class="fsa-chip"><?= egh($k) ?></span><?php endforeach; ?>
                </div>
                <p class="text-xs text-slate-400 mt-2">Inclua só o que você realmente sabe fazer.</p>
            <?php endif; ?>
            <?php if (!empty($cviRes['ats']['nota'])): $ats = $cviRes['ats']; ?>
                <div class="mt-5 p-4 rounded-2xl border border-slate-200">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="w-9 h-9 rounded-xl bg-slate-900 text-white flex items-center justify-center"><i class="fas fa-robot text-sm"></i></span>
                        <div class="flex-1">
                            <p class="font-bold text-slate-900 text-sm">Leitura por robôs de recrutamento (ATS)</p>
                            <p class="text-xs text-slate-500">Como os sistemas e IAs de triagem de estágio entendem o seu currículo.</p>
                        </div>
                        <span class="text-lg font-bold <?= $ats['nota'] >= 70 ? 'text-emerald-600' : ($ats['nota'] >= 50 ? 'text-amber-600' : 'text-red-600') ?>"><?= (int) $ats['nota'] ?><span class="text-xs text-slate-400">/100</span></span>
                    </div>
                    <div class="h-1.5 bg-slate-100 rounded-full mb-3"><div class="h-full rounded-full <?= $ats['nota'] >= 70 ? 'bg-emerald-500' : ($ats['nota'] >= 50 ? 'bg-amber-500' : 'bg-red-500') ?>" style="width: <?= (int) $ats['nota'] ?>%"></div></div>
                    <?php if (!empty($ats['dicas'])): ?>
                        <ul class="space-y-1.5 text-sm text-slate-700">
                            <?php foreach ($ats['dicas'] as $d): ?><li class="flex gap-2"><i class="fas fa-arrow-right text-[#4A9FCA] text-xs mt-1.5"></i><span><?= egh($d) ?></span></li><?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <p class="text-xs text-slate-400 mt-3"><i class="fas fa-file-pdf mr-1"></i>O PDF da Central sai com texto selecionável, em uma coluna e com títulos padrão, o formato que esses sistemas leem melhor.</p>
                </div>
            <?php elseif ($cviRes): ?>
                <p class="mt-4 text-xs text-slate-400"><i class="fas fa-robot mr-1"></i>Clique em "Avaliar de novo" para ver também a nota de leitura por ATS.</p>
            <?php endif; ?>
            </div>
            <a href="vagas-para-voce.php" class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-[#2B7FA6] hover:underline"><i class="fas fa-briefcase"></i> Ver as vagas que combinam com você</a>
        <?php endif; ?>
        </div>
    </section>

    <script>
    (function () {
        var btn = document.getElementById('btnIaAvaliar');
        var consentiu = <?= $cviConsentiu ? 'true' : 'false' ?>;
        function post(acao) {
            var f = new FormData(); f.append('acao', acao);
            return fetch('ia-curriculo.php', { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: f }).then(function (r) { return r.json(); });
        }
        function avaliar() {
            if (typeof modoEdicao !== 'undefined' && modoEdicao) { fsaAviso('Salve o currículo antes de pedir a avaliação.', 'Currículo em edição'); return; }
            btn.disabled = true; btn.classList.add('opacity-60');
            document.getElementById('iaAvalConteudo').classList.add('hidden');
            document.getElementById('iaAvalCarregando').classList.remove('hidden');
            post('avaliar').then(function (d) {
                if (d.ok) { fsaTrocarPartes(['#cvIaBloco']); return; }   // atualiza só o quadro, sem recarregar
                fsaAviso(d.erro || 'Não foi possível avaliar agora.', 'A IA não respondeu');
            }).catch(function () {
                fsaAviso('Não foi possível falar com a IA. Tente de novo em alguns minutos.', 'A IA não respondeu');
            }).then(function () {
                btn.disabled = false; btn.classList.remove('opacity-60');
                document.getElementById('iaAvalCarregando').classList.add('hidden');
                document.getElementById('iaAvalConteudo').classList.remove('hidden');
            });
        }
        btn.addEventListener('click', function () {
            if (consentiu) return avaliar();
            fsaConfirmar({
                titulo: 'Usar a IA no seu currículo?',
                texto: 'Seu currículo será enviado ao Google Gemini SEM nome, e-mail e telefone, só para gerar a avaliação. Você pode retirar a autorização nas Configurações.',
                botao: 'Concordo', tipo: 'aviso', icone: 'fa-wand-magic-sparkles'
            }, function () { post('consentir').then(function () { consentiu = true; avaliar(); }); });
        });
    })();
    </script>
    <?php endif; ?>
</div>
