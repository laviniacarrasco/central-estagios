<?php
/**
 * =============================================================================
 * CURRÍCULO EM PDF (texto de verdade, legível por sistemas ATS)
 * -----------------------------------------------------------------------------
 * Antes o PDF era uma FOTO da tela (html2canvas): os robôs de recrutamento (ATS)
 * não conseguiam ler o texto e a quebra de página cortava linhas no meio.
 * Agora o PDF é montado com texto, numa coluna, com títulos padrão
 * ("Experiência profissional", "Formação acadêmica"...), que é o que os ATS
 * e as IAs de triagem esperam. Nenhum bloco é cortado entre páginas.
 *
 * Como gera:
 *   - Com a biblioteca Dompdf (composer require dompdf/dompdf): baixa o .pdf direto.
 *   - Sem a Dompdf: abre a versão para impressão e o navegador oferece
 *     "Salvar como PDF" (também gera PDF com texto de verdade).
 *   - ?ver=1 mostra a versão para impressão mesmo com a Dompdf (para conferir).
 *
 * Modelos (os mesmos da tela): classico | moderno | minimalista
 * Os dados vêm do curriculum.php (CV_SOMENTE_DADOS: só carrega, não desenha a tela).
 * =============================================================================
 */
define('CV_SOMENTE_DADOS', true);
require __DIR__ . '/curriculum.php';           // $userData, $corAtual, $layoutAtual, $certs, $idiomaCVAtual...
require_once __DIR__ . '/includes/melhorias.php';

$cvIdioma = in_array($idiomaCVAtual ?? 'pt', ['pt', 'en', 'es'], true) ? $idiomaCVAtual : 'pt';
$T = [
    'pt' => ['resumo' => 'Resumo profissional', 'exp' => 'Experiência profissional', 'form' => 'Formação acadêmica', 'hab' => 'Habilidades',
             'idi' => 'Idiomas', 'cert' => 'Cursos e certificações', 'atual' => 'Atual', 'cursando' => 'Cursando', 'previsao' => 'previsão de conclusão',
             'em' => 'em', 'meses' => ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez']],
    'en' => ['resumo' => 'Professional summary', 'exp' => 'Professional experience', 'form' => 'Education', 'hab' => 'Skills',
             'idi' => 'Languages', 'cert' => 'Courses and certifications', 'atual' => 'Present', 'cursando' => 'In progress', 'previsao' => 'expected graduation',
             'em' => 'in', 'meses' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']],
    'es' => ['resumo' => 'Resumen profesional', 'exp' => 'Experiencia profesional', 'form' => 'Formación académica', 'hab' => 'Habilidades',
             'idi' => 'Idiomas', 'cert' => 'Cursos y certificaciones', 'atual' => 'Actual', 'cursando' => 'En curso', 'previsao' => 'finalización prevista',
             'em' => 'en', 'meses' => ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic']],
][$cvIdioma];

$h   = fn($v) => htmlspecialchars(trim(html_entity_decode((string) $v, ENT_QUOTES, 'UTF-8')), ENT_QUOTES, 'UTF-8');
$mes = function ($ymd) use ($T): string {
    if (!preg_match('/^(\d{4})-(\d{2})/', (string) $ymd, $m)) return '';
    return ($T['meses'][(int) $m[2] - 1] ?? '') . '/' . $m[1];
};
$periodo = function (array $x, bool $formacao = false) use ($mes, $T): string {
    $ini = $mes($x['inicio'] ?? '');
    $atual = ($x['atual'] ?? '0') === '1'
        || ($formacao && !empty($x['fim']) && substr((string) $x['fim'], 0, 7) >= date('Y-m'));   // conclusão no futuro = cursando
    $fim = $atual ? ($formacao ? $T['cursando'] : $T['atual']) : $mes($x['fim'] ?? '');
    if ($formacao && $atual && !empty($x['fim'])) $fim = $T['cursando'] . ' (' . $T['previsao'] . ' ' . $mes($x['fim']) . ')';
    return trim($ini . ($ini && $fim ? ' – ' : '') . $fim);
};
/** Descrição: cada linha vira um tópico (o que ATS e recrutadores leem melhor) */
$descricao = function ($txt) use ($h): string {
    $linhas = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $txt))));
    if (!$linhas) return '';
    if (count($linhas) === 1 && !preg_match('/^[-•*]\s*/u', $linhas[0])) return '<p class="desc">' . $h($linhas[0]) . '</p>';
    $out = '<ul class="topicos">';
    foreach ($linhas as $l) $out .= '<li>' . $h(preg_replace('/^[-•*]\s*/u', '', $l)) . '</li>';
    return $out . '</ul>';
};

$cv     = $userData;
$prefs  = mhPreferencias();
$modelo = in_array($layoutAtual, ['classico', 'moderno', 'minimalista'], true) ? $layoutAtual : 'classico';
$cor    = $modelo === 'minimalista' ? '#222222' : ($corAtual['primary'] ?? '#1F3A5F');
$cor2   = $modelo === 'minimalista' ? '#555555' : ($corAtual['secondary'] ?? '#5B6572');

$contatos = array_values(array_filter([
    trim((string) ($cv['email'] ?? '')), trim((string) ($cv['telefone'] ?? '')), trim((string) ($cv['cidade'] ?? '')),
    preg_replace('#^https?://(www\.)?#', '', (string) ($prefs['linkedin'] ?? '')),
    preg_replace('#^https?://(www\.)?#', '', (string) ($prefs['github'] ?? '')),
    preg_replace('#^https?://(www\.)?#', '', (string) ($prefs['portfolio'] ?? '')),
]));

// Foto (opcional), embutida no arquivo. Os ATS ignoram imagens: não atrapalha a leitura.
$fotoDataUri = '';
if (!empty($cv['exibirFoto']) && !empty($cv['fotoCV']) && $modelo !== 'minimalista') {
    $base = realpath(DATA_PATH);
    $arq  = realpath(DATA_PATH . $cv['fotoCV']);
    if ($arq && $base && strpos($arq, $base) === 0 && is_file($arq) && filesize($arq) < 6 * 1024 * 1024) {
        $info = @getimagesize($arq);
        $mime = $info['mime'] ?? '';
        if (in_array($mime, ['image/jpeg', 'image/png'], true)) $fotoDataUri = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($arq));
    }
}

$habilidades = array_values(array_filter(array_map(fn($x) => trim((string) $x), (array) ($cv['habilidades'] ?? []))));
$nomeArquivo = trim(preg_replace('/[^A-Za-z0-9]+/', '_', (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) ($cv['nome'] ?? ''))), '_') ?: 'curriculo';
$temDompdf = class_exists('Dompdf\\Dompdf');
$modoHtml  = !$temDompdf || isset($_GET['ver']);

ob_start();
?><!DOCTYPE html>
<html lang="<?= $cvIdioma === 'pt' ? 'pt-BR' : $cvIdioma ?>">
<head>
<meta charset="UTF-8">
<title><?= $h($cv['nome'] ?? '') ?> - Currículo</title>
<style>
    @page { size: A4; margin: 15mm 16mm; }
    body { margin: 0; font-family: Helvetica, Arial, sans-serif; font-size: 10pt; line-height: 1.42; color: #222; }
    p { margin: 0; }
    h1, h2 { margin: 0; }
    .cab { width: 100%; border-collapse: collapse; }
    .cab td { vertical-align: middle; padding: 0; }
    .nome { font-size: 21pt; font-weight: bold; line-height: 1.15; color: <?= $cor ?>; margin: 0 0 4pt; }
    .contato { font-size: 9.2pt; color: #444; }
    .sep { color: #999; padding: 0 4pt; }
    .foto { width: 64pt; height: 64pt; }
    .titulo { font-size: 9.6pt; font-weight: bold; text-transform: uppercase; letter-spacing: .9pt; color: <?= $cor ?>;
              margin: 13pt 0 6pt; padding-bottom: 2.5pt; border-bottom: 1pt solid <?= $cor ?>; page-break-after: avoid; }
    .item { margin: 0 0 7.5pt; page-break-inside: avoid; }
    .linha { width: 100%; border-collapse: collapse; }
    .linha td { padding: 0; vertical-align: top; }
    .linha td.per { text-align: right; white-space: nowrap; width: 140pt; font-size: 9pt; color: <?= $cor2 ?>; padding-left: 8pt; }
    .cargo { font-weight: bold; font-size: 10.2pt; color: #111; }
    .local { color: #555; }
    .desc { margin-top: 2pt; color: #333; }
    .topicos { margin: 2.5pt 0 0 0; padding-left: 13pt; }
    .topicos li { margin: 0 0 1.5pt; color: #333; }
    .resumo { color: #333; }
    .habs { color: #333; }
    .idiomas { border-collapse: collapse; }
    .idiomas td { padding: 0 18pt 2pt 0; }
    .cert { margin: 0 0 3pt; page-break-inside: avoid; }

    /* Moderno: faixa fina no topo, títulos com marcador e habilidades em etiquetas */
    .moderno .faixa { height: 4pt; background: <?= $cor ?>; margin: 0 0 12pt; }
    .moderno .titulo { border-bottom: none; border-left: 3pt solid <?= $cor ?>; padding: 0 0 0 6pt; color: #111; }
    .moderno .etiqueta { display: inline-block; border: .75pt solid #cfd6de; padding: 1pt 5pt; margin: 0 3pt 3.5pt 0; font-size: 9pt; color: #333; }

    /* Minimalista: tons de cinza e cabeçalho centralizado */
    .minimalista .nome { text-align: center; color: #111; font-size: 20pt; letter-spacing: .5pt; }
    .minimalista .contato { text-align: center; }
    .minimalista .titulo { color: #333; border-bottom: .6pt solid #9a9a9a; letter-spacing: 1.4pt; font-size: 9pt; }

    /* Clássico: linha forte abaixo do cabeçalho */
    .classico .regua { border-top: 2pt solid <?= $cor ?>; margin: 8pt 0 0; }

    /* Só na versão para imprimir (navegador) */
    .barra { font-family: Arial, sans-serif; position: sticky; top: 0; background: #0f172a; color: #fff; padding: 10px 16px; display: flex; gap: 12px; align-items: center; justify-content: space-between; font-size: 14px; }
    .barra button { background: #4A9FCA; color: #fff; border: 0; border-radius: 8px; padding: 9px 16px; font-weight: bold; cursor: pointer; }
    .folha { max-width: 210mm; margin: 0 auto; padding: 15mm 16mm; background: #fff; box-sizing: border-box; }
    @media screen { body.tela { background: #e9edf2; } body.tela .folha { margin: 20px auto; box-shadow: 0 4px 24px rgba(0,0,0,.12); } }
    @media print { .barra { display: none; } body.tela .folha { padding: 0; max-width: none; margin: 0; box-shadow: none; } body.tela { background: #fff; } }
</style>
</head>
<body class="<?= $modelo ?><?= $modoHtml ? ' tela' : '' ?>">
<?php if ($modoHtml): ?>
    <div class="barra">
        <span>Na janela de impressão, escolha <strong>Salvar como PDF</strong> e desmarque "Cabeçalhos e rodapés".</span>
        <button type="button" onclick="window.print()">Salvar como PDF</button>
    </div>
    <div class="folha">
<?php endif; ?>

<?php if ($modelo === 'moderno'): ?><div class="faixa"></div><?php endif; ?>

<table class="cab"><tr>
    <td>
        <h1 class="nome"><?= $h($cv['nome'] ?? '') ?></h1>
        <p class="contato"><?php foreach ($contatos as $i => $c): ?><?= $i ? '<span class="sep">|</span>' : '' ?><?= $h($c) ?><?php endforeach; ?></p>
    </td>
    <?php if ($fotoDataUri): ?>
        <td style="width:72pt;text-align:right;"><img class="foto" src="<?= $fotoDataUri ?>" alt=""></td>
    <?php endif; ?>
</tr></table>
<?php if ($modelo === 'classico'): ?><div class="regua"></div><?php endif; ?>

<?php if (trim((string) ($cv['resumo'] ?? '')) !== ''): ?>
    <h2 class="titulo"><?= $T['resumo'] ?></h2>
    <p class="resumo"><?= nl2br($h($cv['resumo'])) ?></p>
<?php endif; ?>

<?php if (!empty($cv['experiencias'])): ?>
    <h2 class="titulo"><?= $T['exp'] ?></h2>
    <?php foreach ($cv['experiencias'] as $e): if (!is_array($e) || trim((string) ($e['cargo'] ?? '')) === '') continue; ?>
        <div class="item">
            <table class="linha"><tr>
                <td><span class="cargo"><?= $h($e['cargo']) ?></span><?php if (!empty($e['empresa'])): ?> <span class="local">— <?= $h($e['empresa']) ?><?= !empty($e['cidade']) ? ', ' . $h($e['cidade']) : '' ?></span><?php endif; ?></td>
                <td class="per"><?= $h($periodo($e)) ?></td>
            </tr></table>
            <?= $descricao($e['descricao'] ?? '') ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($cv['formacoes'])): ?>
    <h2 class="titulo"><?= $T['form'] ?></h2>
    <?php foreach ($cv['formacoes'] as $f): if (!is_array($f) || trim((string) ($f['curso'] ?? '')) === '') continue;
        $tipo = (string) ($tiposFormacaoLabels[$f['tipo'] ?? ''] ?? ($f['tipo'] ?? '')); ?>
        <div class="item">
            <table class="linha"><tr>
                <td>
                    <span class="cargo"><?= trim($tipo) !== '' ? $h($tipo) . ' ' . $T['em'] . ' ' : '' ?><?= $h($f['curso']) ?></span>
                    <?php if (!empty($f['instituicao'])): ?><br><span class="local"><?= $h($f['instituicao']) ?></span><?php endif; ?>
                </td>
                <td class="per"><?= $h($periodo($f, true)) ?></td>
            </tr></table>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($habilidades): ?>
    <h2 class="titulo"><?= $T['hab'] ?></h2>
    <?php if ($modelo === 'moderno'): ?>
        <div><?php foreach ($habilidades as $s): ?><span class="etiqueta"><?= $h($s) ?></span> <?php endforeach; ?></div>
    <?php else: ?>
        <p class="habs"><?= implode(' &nbsp;•&nbsp; ', array_map($h, $habilidades)) ?></p>
    <?php endif; ?>
<?php endif; ?>

<?php if (!empty($cv['idiomas'])): ?>
    <h2 class="titulo"><?= $T['idi'] ?></h2>
    <table class="idiomas"><tr>
        <?php foreach ($cv['idiomas'] as $i): if (!is_array($i) || trim((string) ($i['idioma'] ?? '')) === '') continue;
            $nivel = (string) ($niveisIdiomaLabels[$i['nivel'] ?? ''] ?? ($i['nivel'] ?? '')); ?>
            <td><strong><?= $h($i['idioma']) ?></strong><?= trim($nivel) !== '' ? ' — ' . $h($nivel) : '' ?></td>
        <?php endforeach; ?>
    </tr></table>
<?php endif; ?>

<?php if (!empty($certs) && is_array($certs)): ?>
    <h2 class="titulo"><?= $T['cert'] ?></h2>
    <?php foreach ($certs as $c): if (!is_array($c) || trim((string) ($c['title'] ?? '')) === '') continue; ?>
        <p class="cert"><strong><?= $h($c['title']) ?></strong><?= !empty($c['institution']) ? ' — ' . $h($c['institution']) : '' ?><?= !empty($c['hours']) ? ' (' . $h(preg_replace('/\s*h(oras)?\s*$/i', '', (string) $c['hours'])) . 'h)' : '' ?></p>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($modoHtml): ?>
    </div>
    <?php if (!isset($_GET['ver'])): ?><script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script><?php endif; ?>
<?php endif; ?>
</body>
</html>
<?php
$html = ob_get_clean();

if ($modoHtml) {
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
    exit;
}

try {
    $opcoes = new Dompdf\Options();
    $opcoes->set('defaultFont', 'Helvetica');
    $opcoes->set('isRemoteEnabled', false);      // nada de baixar arquivos da internet
    $opcoes->setChroot(__DIR__);
    $pdf = new Dompdf\Dompdf($opcoes);
    $pdf->loadHtml($html, 'UTF-8');
    $pdf->setPaper('A4', 'portrait');
    $pdf->addInfo('Title', 'Currículo - ' . trim((string) ($cv['nome'] ?? '')));
    $pdf->addInfo('Author', trim((string) ($cv['nome'] ?? '')));
    $pdf->render();
    $pdf->stream('Curriculo_' . $nomeArquivo . '.pdf', ['Attachment' => true]);
} catch (Throwable $e) {
    error_log('Currículo PDF: ' . $e->getMessage());
    header('Location: curriculo-pdf.php?ver=1');      // se a Dompdf falhar, usa a versão para impressão
}
exit;
