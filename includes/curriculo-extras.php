<?php
/** Curriculo: pontuacao + "adaptar para uma vaga" (comparacao de palavras, sem IA) */
require_once __DIR__ . '/melhorias.php';

$cvxDados = (isset($userData) && is_array($userData)) ? $userData : [];
$cvxPont  = mhCurriculoPontuacao($cvxDados);
$cvxPct   = (int) $cvxPont['pct'];
$cvxCor   = $cvxPct >= 80 ? '#27AE60' : ($cvxPct >= 50 ? '#F39C12' : '#E35444');

$cvxVagas = [];
foreach (array_values(loadData('platform_jobs')) as $j) {
    if (!vagaVigente($j)) continue;
    $cvxVagas[] = [
        't' => (string) ($j['title'] ?? '') . ' — ' . (string) ($j['company'] ?? ''),
        'd' => trim((string) ($j['title'] ?? '') . "\n" . (string) ($j['description'] ?? '') . "\n" . (string) ($j['requirements'] ?? '')),
    ];
}
if (empty($_SESSION['is_restrito'])) {
    foreach (egressoVagas() as $j) {
        if (egressoVagaStatusEfetivo($j) !== 'aprovada') continue;
        $cvxVagas[] = [
            't' => (string) ($j['title'] ?? '') . ' — ' . (string) ($j['company'] ?? ''),
            'd' => trim((string) ($j['title'] ?? '') . "\n" . (string) ($j['description'] ?? '') . "\n" . (string) ($j['requirements'] ?? '')),
        ];
    }
}
?>
<div class="cv-section p-6 mb-6" style="height:auto;">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

        <div>
            <h3 class="text-sm font-bold text-gray-800 uppercase tracking-widest mb-4">Pontuação do Currículo</h3>
            <div class="flex items-center gap-4 mb-4">
                <p class="text-5xl font-bold" style="color: <?= $cvxCor ?>;"><?= $cvxPct ?>%</p>
                <div class="flex-1">
                    <div class="h-3 bg-gray-100 rounded-full">
                        <div class="h-full rounded-full" style="width: <?= $cvxPct ?>%; background: <?= $cvxCor ?>;"></div>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">Quanto mais completo, mais fácil de ser encontrado e de combinar com vagas.</p>
                </div>
            </div>
            <?php if (empty($cvxPont['faltando'])): ?>
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm">
                    <i class="fas fa-check-circle mr-1"></i> Seu currículo está completo. Revise de tempos em tempos.
                </div>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($cvxPont['faltando'] as $f): ?>
                        <div class="flex items-start gap-3 p-3 bg-amber-50 rounded-xl">
                            <i class="fas fa-lightbulb text-amber-500 mt-0.5"></i>
                            <div>
                                <p class="text-sm font-semibold text-gray-800"><?= egh($f['label']) ?> <span class="text-xs font-normal text-gray-400">(+<?= (int) $f['pts'] ?> pontos)</span></p>
                                <p class="text-xs text-gray-500"><?= egh($f['dica']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div>
            <h3 class="text-sm font-bold text-gray-800 uppercase tracking-widest mb-4">Adaptar para uma Vaga</h3>
            <p class="text-xs text-gray-500 mb-3">Escolha uma vaga ou cole a descrição. Mostramos quais palavras-chave da vaga já aparecem no seu currículo e quais faltam.</p>
            <?php if (!empty($cvxVagas)): ?>
                <select id="adpVaga" class="w-full h-10 px-3 mb-3 border border-gray-200 rounded-lg text-sm bg-gray-50">
                    <option value="">Escolher uma vaga aberta...</option>
                    <?php foreach ($cvxVagas as $i => $v): ?>
                        <option value="<?= (int) $i ?>"><?= egh($v['t']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <textarea id="adpTexto" rows="4" placeholder="Cole aqui a descrição e os requisitos da vaga..."
                      class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 mb-3"></textarea>
            <button type="button" id="adpBtn" class="px-5 py-2 bg-[var(--cv-primary)] text-white rounded-lg text-sm font-semibold hover:opacity-90">
                <i class="fas fa-search mr-1"></i> Comparar
            </button>

            <div id="adpResultado" class="hidden mt-4">
                <p class="text-sm font-semibold text-gray-800 mb-2">Compatibilidade de palavras-chave: <span id="adpPct"></span></p>
                <p class="text-xs font-bold text-emerald-700 uppercase tracking-wide mb-1">Você já tem</p>
                <div id="adpTem" class="flex flex-wrap gap-2 mb-3"></div>
                <p class="text-xs font-bold text-amber-700 uppercase tracking-wide mb-1">Faltam no currículo</p>
                <div id="adpFalta" class="flex flex-wrap gap-2 mb-3"></div>
                <p class="text-xs text-gray-400">Inclua só o que você realmente sabe fazer. Use essas palavras no resumo, nas experiências ou nas habilidades.</p>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var CV = <?= egJson(mhCurriculoTexto($cvxDados)) ?>;
    var VAGAS = <?= egJson($cvxVagas) ?>;
    var STOP = ['para','com','uma','dos','das','que','por','nos','nas','mais','como','sua','seu','ser','ter','sobre','entre','area','vaga','vagas','estagio','estagiario','estagiaria','curso','cursando','experiencia','conhecimento','conhecimentos','atuar','apoio','empresa','equipe','trabalho','desejavel','diferencial','nivel','superior','graduacao','sera','serao','suas','seus','esta','este','isso','sao','tambem','forma','ainda','pelo','pela','sempre','alem','bem','atividades','responsavel','oportunidade','processo','processos'];

    function norm(s) {
        return String(s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9+#\s]/g, ' ');
    }
    function contar(texto) {
        var mapa = {};
        norm(texto).split(/\s+/).forEach(function (t) {
            if (t.length >= 4 && STOP.indexOf(t) < 0) mapa[t] = (mapa[t] || 0) + 1;
        });
        return mapa;
    }
    function chips(caixa, lista, classe) {
        caixa.textContent = '';
        if (!lista.length) {
            var vazio = document.createElement('span');
            vazio.className = 'text-xs text-gray-400';
            vazio.textContent = 'Nenhuma';
            caixa.appendChild(vazio);
            return;
        }
        lista.forEach(function (w) {
            var s = document.createElement('span');
            s.className = 'px-3 py-1 rounded-full text-xs font-semibold ' + classe;
            s.textContent = w;
            caixa.appendChild(s);
        });
    }

    var sel = document.getElementById('adpVaga');
    var txt = document.getElementById('adpTexto');
    if (sel) {
        sel.addEventListener('change', function () {
            if (sel.value !== '' && VAGAS[sel.value]) txt.value = VAGAS[sel.value].d;
        });
    }
    document.getElementById('adpBtn').addEventListener('click', function () {
        if (!txt.value.trim()) {
            if (window.fsaAviso) fsaAviso('Escolha uma vaga ou cole a descrição para comparar.', 'Falta a vaga');
            return;
        }
        var mapa = contar(txt.value);
        var palavras = Object.keys(mapa).sort(function (a, b) { return mapa[b] - mapa[a] || b.length - a.length; }).slice(0, 25);
        var cvNorm = ' ' + norm(CV).replace(/\s+/g, ' ') + ' ';
        var tem = [], falta = [];
        palavras.forEach(function (w) { (cvNorm.indexOf(' ' + w) >= 0 ? tem : falta).push(w); });
        var pct = palavras.length ? Math.round(tem.length / palavras.length * 100) : 0;
        document.getElementById('adpPct').textContent = pct + '%';
        chips(document.getElementById('adpTem'), tem, 'bg-emerald-50 text-emerald-700');
        chips(document.getElementById('adpFalta'), falta, 'bg-amber-50 text-amber-700');
        document.getElementById('adpResultado').classList.remove('hidden');
    });
})();
</script>
