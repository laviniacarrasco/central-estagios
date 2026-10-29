<?php
/** Perfil: completude + links e disponibilidade (salva em perfil-extras-salvar.php) */
require_once __DIR__ . '/melhorias.php';

$pxRestrito = !empty($_SESSION['is_restrito']);
$pxPrefs    = mhPreferencias();
$pxPont     = mhCurriculoPontuacao(is_array($curriculumData ?? null) ? $curriculumData : []);

$pxItens = [
    ['Foto de perfil',                       !empty($temFoto),                                              'Clique no lápis sobre a sua foto.'],
    ['Telefone e cidade',                    trim((string) ($telefone ?? '')) !== '' && trim((string) ($cidade ?? '')) !== '', 'Preencha no Currículo.'],
    ['Currículo com 70% ou mais',            $pxPont['pct'] >= 70,                                          'Veja a pontuação em Currículo.'],
    ['Link do LinkedIn',                     $pxPrefs['linkedin'] !== '',                                   'Preencha abaixo.'],
    ['Disponibilidade (turno e modalidade)', $pxPrefs['turno'] !== '' && $pxPrefs['modalidade'] !== '',    'Preencha abaixo.'],
];
if (!$pxRestrito) {
    $pxItens[] = ['Teste de Perfil por Competências', !empty($resultadoPerfil), 'Responda o formulário no cartão abaixo.'];
}
$pxFeitos = 0;
foreach ($pxItens as $i) { if ($i[1]) $pxFeitos++; }
$pxPct = (int) round($pxFeitos / count($pxItens) * 100);

$pxTurnos = ['Manhã', 'Tarde', 'Noite', 'Integral', 'Flexível'];
$pxModal  = ['Presencial', 'Híbrido', 'Remoto', 'Qualquer'];
$campo = 'w-full h-11 px-3 border border-gray-200 rounded-xl text-sm bg-gray-50 focus:ring-2 focus:ring-[#4A9FCA]';
?>
<div id="preferencias" class="bg-white p-6 rounded-2xl shadow-sm">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-xl font-bold text-gray-900">
            <i class="fas fa-tasks text-[#4A9FCA] mr-2"></i>Completude do Perfil
        </h3>
        <span class="text-2xl font-bold text-[#4A9FCA]"><?= $pxPct ?>%</span>
    </div>
    <div class="h-2 bg-gray-100 rounded-full mb-5">
        <div class="h-full bg-[#4A9FCA] rounded-full" style="width: <?= $pxPct ?>%"></div>
    </div>
    <div class="grid md:grid-cols-2 gap-3 mb-8">
        <?php foreach ($pxItens as $i): ?>
            <div class="flex items-start gap-3 p-3 rounded-xl <?= $i[1] ? 'bg-emerald-50' : 'bg-gray-50' ?>">
                <i class="fas <?= $i[1] ? 'fa-check-circle text-[#27AE60]' : 'fa-circle text-gray-300' ?> mt-0.5"></i>
                <div>
                    <p class="text-sm font-semibold <?= $i[1] ? 'text-gray-500' : 'text-gray-800' ?>"><?= egh($i[0]) ?></p>
                    <?php if (!$i[1]): ?><p class="text-xs text-gray-400"><?= egh($i[2]) ?></p><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <h4 class="font-bold text-gray-900 mb-1">Links e disponibilidade</h4>
    <p class="text-xs text-gray-500 mb-4">Ajuda a coordenação e as empresas a conhecerem você. Nada aqui é obrigatório.</p>
    <form method="POST" action="perfil-extras-salvar.php" class="grid md:grid-cols-2 gap-4" accept-charset="UTF-8">
        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">LinkedIn</label>
            <input type="text" name="linkedin" maxlength="200" value="<?= egh($pxPrefs['linkedin']) ?>" placeholder="linkedin.com/in/seu-nome" class="<?= $campo ?>">
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">GitHub</label>
            <input type="text" name="github" maxlength="200" value="<?= egh($pxPrefs['github']) ?>" placeholder="github.com/seu-usuario" class="<?= $campo ?>">
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Portfólio (Behance, site...)</label>
            <input type="text" name="portfolio" maxlength="200" value="<?= egh($pxPrefs['portfolio']) ?>" placeholder="seusite.com" class="<?= $campo ?>">
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Áreas de interesse</label>
            <input type="text" name="areas" maxlength="200" value="<?= egh($pxPrefs['areas']) ?>" placeholder="Ex.: dados, marketing, logística" class="<?= $campo ?>">
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Turno livre para estágio</label>
            <select name="turno" class="<?= $campo ?>">
                <option value="">Selecione...</option>
                <?php foreach ($pxTurnos as $t): ?>
                    <option value="<?= egh($t) ?>" <?= $pxPrefs['turno'] === $t ? 'selected' : '' ?>><?= egh($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Modalidade preferida</label>
            <select name="modalidade" class="<?= $campo ?>">
                <option value="">Selecione...</option>
                <?php foreach ($pxModal as $m): ?>
                    <option value="<?= egh($m) ?>" <?= $pxPrefs['modalidade'] === $m ? 'selected' : '' ?>><?= egh($m) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Posso começar a partir de</label>
            <input type="date" name="inicio" value="<?= egh($pxPrefs['inicio']) ?>" class="<?= $campo ?>">
        </div>
        <div class="flex items-end">
            <button type="submit" class="h-11 px-6 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl font-semibold text-sm transition-all">
                <i class="fas fa-save mr-1"></i> Salvar
            </button>
        </div>
    </form>
</div>
<?php if (($_GET['prefs'] ?? '') === 'ok'): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.fsaAviso) fsaAviso('Seus links e sua disponibilidade foram salvos.', 'Tudo certo!');
});
</script>
<?php endif; ?>
