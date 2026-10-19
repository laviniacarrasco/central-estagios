<?php
require_once 'includes/config.php';
require_once 'includes/relatorios.php';
checkAuth();

if (!isAdminEgressos()) {
    header('Location: admin-dashboard.php');
    exit;
}

$fsaCourses = getFsaCourses();

$curso = trim($_GET['curso'] ?? '');
if ($curso !== '' && !in_array($curso, $fsaCourses, true)) $curso = '';
$anoOk = fn($v) => preg_match('/^\d{4}$/', trim((string) $v)) ? trim((string) $v) : '';
$filtro = [
    'curso'         => $curso,
    'ano_de'        => $anoOk($_GET['ano_de'] ?? ''),
    'ano_ate'       => $anoOk($_GET['ano_ate'] ?? ''),
    'incluir_lista' => !empty($_GET['incluir_lista']),
];

$dados   = relatorioEgressos($filtro);
$formato = $_GET['formato'] ?? '';
$nomeArq = 'empregabilidade-egressos-' . date('Y-m-d');

if ($formato === 'xlsx') {
    $abas  = relatorioAbas($dados, $filtro);
    $bytes = relatorioXlsx($abas);
    if ($bytes === null) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nomeArq . '.csv"');
        echo relatorioCsv($abas);
    } else {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nomeArq . '.xlsx"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
    }
    exit;
}

if ($formato === 'pdf') {
    if (class_exists('\Dompdf\Dompdf')) {
        $pdf = new \Dompdf\Dompdf();
        $pdf->loadHtml(relatorioHtmlDocumento($dados, $filtro, false), 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        $pdf->stream($nomeArq . '.pdf', ['Attachment' => true]);
        exit;
    }
    $formato = 'imprimir';   // dompdf nao instalado: usa a impressao do navegador
}
if ($formato === 'imprimir') {
    echo relatorioHtmlDocumento($dados, $filtro, true);
    exit;
}

$pageTitle = 'Relatórios de Egressos';
$g = $dados['geral'];
$qs = http_build_query(array_filter($filtro, fn($v) => $v !== '' && $v !== false));

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-6xl mx-auto">

        <div class="mb-8 animate-fade-in">
            <h2 class="text-3xl font-bold text-slate-900 mb-2">Relatórios de Egressos</h2>
            <p class="text-slate-500 font-medium">Empregabilidade por curso e período, para exportar em Excel ou PDF.</p>
        </div>

        <form method="GET" class="bg-white p-6 rounded-2xl border border-gray-100 mb-8 grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Curso</label>
                <select name="curso" class="w-full h-11 px-3 border border-gray-200 rounded-lg bg-white">
                    <option value="">Todos os cursos</option>
                    <?php foreach ($fsaCourses as $c): ?><option value="<?= egh($c) ?>" <?= $c === $curso ? 'selected' : '' ?>><?= egh($c) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Concluiu de (ano)</label>
                <input type="text" name="ano_de" maxlength="4" value="<?= egh($filtro['ano_de']) ?>" placeholder="2020" class="w-full h-11 px-3 border border-gray-200 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">até (ano)</label>
                <input type="text" name="ano_ate" maxlength="4" value="<?= egh($filtro['ano_ate']) ?>" placeholder="<?= date('Y') ?>" class="w-full h-11 px-3 border border-gray-200 rounded-lg">
            </div>
            <button class="h-11 bg-[#4A9FCA] text-white rounded-xl font-semibold">Aplicar filtro</button>
            <label class="md:col-span-5 flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" name="incluir_lista" value="1" <?= $filtro['incluir_lista'] ? 'checked' : '' ?>>
                Incluir no Excel a lista nominal dos egressos (nome, e-mail, empresa). Deixe desmarcado para um relatório só com números.
            </label>
        </form>

        <div class="flex flex-wrap gap-3 mb-8">
            <a href="admin-relatorios.php?<?= egh($qs) ?>&amp;formato=xlsx" class="px-6 h-12 inline-flex items-center bg-[#27AE60] text-white rounded-xl font-semibold text-sm"><i class="fas fa-file-excel mr-2"></i> Baixar Excel</a>
            <a href="admin-relatorios.php?<?= egh($qs) ?>&amp;formato=pdf" target="_blank" class="px-6 h-12 inline-flex items-center bg-[#E35444] text-white rounded-xl font-semibold text-sm"><i class="fas fa-file-pdf mr-2"></i> Baixar PDF</a>
        </div>

        <?php if ($g['total'] === 0): ?>
            <div class="p-12 text-center border-2 border-dashed border-gray-200 rounded-3xl bg-white text-slate-500">Nenhum egresso nesse recorte.</div>
        <?php else: ?>
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                <div class="p-5 bg-[#58A1D4] text-white rounded-2xl"><p class="text-white/90 text-xs">Egressos</p><p class="text-3xl font-bold"><?= $g['total'] ?></p></div>
                <div class="p-5 bg-[#27AE60] text-white rounded-2xl"><p class="text-white/90 text-xs">Empregab. (total)</p><p class="text-3xl font-bold"><?= relFmt($g['empreg_total'], '%') ?></p></div>
                <div class="p-5 bg-emerald-600 text-white rounded-2xl"><p class="text-white/90 text-xs">Empregab. (quem informou)</p><p class="text-3xl font-bold"><?= relFmt($g['empreg_resp'], '%') ?></p></div>
                <div class="p-5 bg-purple-500 text-white rounded-2xl"><p class="text-white/90 text-xs">Na área (ocupados)</p><p class="text-3xl font-bold"><?= relFmt($g['na_area_pct'], '%') ?></p></div>
                <div class="p-5 bg-[#E35444] text-white rounded-2xl"><p class="text-white/90 text-xs">Meses até 1ª colocação</p><p class="text-3xl font-bold"><?= relFmt($g['meses_media']) ?></p></div>
            </div>

            <?php
            $blocos = [
                'Por curso' => $dados['porCurso'],
                'Por ano de conclusão' => $dados['porAno'],
            ];
            foreach ($blocos as $titulo => $conj): ?>
                <div class="bg-white border border-gray-100 rounded-2xl overflow-x-auto mb-8">
                    <div class="p-4 border-b font-bold text-slate-900"><?= egh($titulo) ?></div>
                    <table class="w-full text-sm">
                        <thead><tr class="text-left text-gray-500 border-b bg-gray-50">
                            <th class="p-3"><?= $titulo === 'Por curso' ? 'Curso' : 'Ano' ?></th><th class="p-3">Egressos</th><th class="p-3">Informaram</th><th class="p-3">Ocupados</th>
                            <th class="p-3">Empregab. total</th><th class="p-3">Empregab. informados</th><th class="p-3">Na área</th><th class="p-3">Meses 1ª colocação</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($conj as $nome => $s): ?>
                            <tr class="border-b last:border-0">
                                <td class="p-3 font-medium text-gray-800"><?= egh($nome) ?></td><td class="p-3"><?= $s['total'] ?></td><td class="p-3"><?= $s['informados'] ?></td><td class="p-3"><?= $s['ocupados'] ?></td>
                                <td class="p-3"><?= relFmt($s['empreg_total'], '%') ?></td><td class="p-3"><?= relFmt($s['empreg_resp'], '%') ?></td><td class="p-3"><?= relFmt($s['na_area_pct'], '%') ?></td><td class="p-3"><?= relFmt($s['meses_media']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>

            <p class="text-xs text-gray-400">A situação profissional é informada pelo próprio egresso no perfil. Quem não informou entra no total, mas não no cálculo "sobre quem informou".</p>
        <?php endif; ?>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
