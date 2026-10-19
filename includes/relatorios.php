<?php
/**
 * =============================================================================
 * RELATORIOS DE EMPREGABILIDADE DE EGRESSOS (Excel e PDF)
 * -----------------------------------------------------------------------------
 * - Excel: gera .xlsx "na mao" com ZipArchive (extensao zip do PHP). Se a extensao
 *   nao existir, cai para CSV.
 * - PDF: usa o dompdf SE estiver instalado (composer require dompdf/dompdf).
 *   Sem ele, abre a versao de impressao e o navegador salva como PDF.
 * =============================================================================
 */
require_once __DIR__ . '/egressos.php';

function relStatsVazio(): array {
    return ['total' => 0, 'informados' => 0, 'ocupados' => 0, 'na_area' => 0, 'buscando' => 0, 'estudando' => 0, 'meses' => 0, 'qm' => 0];
}

function relAcumular(array &$s, array $e): void {
    $g = egressoGrupoStatus($e['statusProfissional'] ?? '');
    $s['total']++;
    if ($g !== 'nao_informado') $s['informados']++;
    if (in_array($g, ['na_area', 'fora_area', 'empreendedor'], true)) $s['ocupados']++;
    if ($g === 'na_area') $s['na_area']++;
    if ($g === 'buscando') $s['buscando']++;
    if ($g === 'estudando') $s['estudando']++;
    if (isset($e['mesesAteColocacao']) && $e['mesesAteColocacao'] !== '' && $e['mesesAteColocacao'] !== null) {
        $s['meses'] += (int) $e['mesesAteColocacao'];
        $s['qm']++;
    }
}

function relStatsFinal(array $s): array {
    $s['empreg_total'] = $s['total'] ? round($s['ocupados'] / $s['total'] * 100, 1) : null;
    $s['empreg_resp']  = $s['informados'] ? round($s['ocupados'] / $s['informados'] * 100, 1) : null;
    $s['na_area_pct']  = $s['ocupados'] ? round($s['na_area'] / $s['ocupados'] * 100, 1) : null;
    $s['meses_media']  = $s['qm'] ? round($s['meses'] / $s['qm'], 1) : null;
    return $s;
}

/** $f = ['curso' => '', 'ano_de' => '', 'ano_ate' => ''] (ano = ano de conclusao) */
function relatorioEgressos(array $f): array {
    $curso = trim((string) ($f['curso'] ?? ''));
    $de    = (int) ($f['ano_de'] ?? 0);
    $ate   = (int) ($f['ano_ate'] ?? 0);

    $lista = [];
    foreach (loadData('usuarios') as $u) {
        if (($u['tipo'] ?? '') !== 'egresso') continue;
        if ($curso !== '' && ($u['curso'] ?? '') !== $curso) continue;
        $ano = (int) ($u['anoConclusao'] ?? 0);
        if ($de && $ano < $de) continue;
        if ($ate && $ano > $ate) continue;
        $lista[] = $u;
    }

    $geral = relStatsVazio();
    $porCurso = []; $porAno = []; $empresas = []; $situacoes = [];
    foreach ($lista as $e) {
        relAcumular($geral, $e);
        $c = trim($e['curso'] ?? '') ?: 'Sem curso';
        if (!isset($porCurso[$c])) $porCurso[$c] = relStatsVazio();
        relAcumular($porCurso[$c], $e);
        $a = trim((string) ($e['anoConclusao'] ?? '')) ?: 'N/I';
        if (!isset($porAno[$a])) $porAno[$a] = relStatsVazio();
        relAcumular($porAno[$a], $e);

        $rot = egressoStatusLabel($e['statusProfissional'] ?? '');
        $situacoes[$rot] = ($situacoes[$rot] ?? 0) + 1;

        $g = egressoGrupoStatus($e['statusProfissional'] ?? '');
        if (in_array($g, ['na_area', 'fora_area', 'empreendedor'], true) && trim($e['empresaAtual'] ?? '') !== '') {
            $k = mb_strtolower(trim($e['empresaAtual']));
            if (!isset($empresas[$k])) $empresas[$k] = ['nome' => trim($e['empresaAtual']), 'qtd' => 0];
            $empresas[$k]['qtd']++;
        }
    }

    uasort($porCurso, fn($x, $y) => $y['total'] <=> $x['total']);
    ksort($porAno);
    uasort($empresas, fn($x, $y) => $y['qtd'] <=> $x['qtd']);
    arsort($situacoes);

    return [
        'geral'     => relStatsFinal($geral),
        'porCurso'  => array_map('relStatsFinal', $porCurso),
        'porAno'    => array_map('relStatsFinal', $porAno),
        'empresas'  => array_slice(array_values($empresas), 0, 20),
        'situacoes' => $situacoes,
        'lista'     => $lista,
    ];
}

function relFmt($v, string $suf = ''): string {
    return $v === null ? '—' : str_replace('.', ',', (string) $v) . $suf;
}

function relDescricaoFiltro(array $f): string {
    $p = [];
    $p[] = 'Curso: ' . (trim((string) ($f['curso'] ?? '')) ?: 'todos');
    $de = trim((string) ($f['ano_de'] ?? '')); $ate = trim((string) ($f['ano_ate'] ?? ''));
    $p[] = 'Ano de conclusão: ' . ($de || $ate ? ($de ?: '…') . ' a ' . ($ate ?: '…') : 'todos');
    return implode(' | ', $p);
}

/** Abas do Excel: nome => linhas (a primeira linha e o cabecalho) */
function relatorioAbas(array $d, array $f): array {
    $g = $d['geral'];
    $abas = [];
    $abas['Resumo'] = [
        ['Indicador', 'Valor'],
        ['Filtro aplicado', relDescricaoFiltro($f)],
        ['Gerado em', date('d/m/Y H:i')],
        ['Egressos no recorte', $g['total']],
        ['Com situação informada', $g['informados']],
        ['Empregados, estagiando ou empreendendo', $g['ocupados']],
        ['Empregabilidade sobre o total (%)', $g['empreg_total']],
        ['Empregabilidade sobre quem informou (%)', $g['empreg_resp']],
        ['Atuando na área, entre os ocupados (%)', $g['na_area_pct']],
        ['Buscando oportunidade', $g['buscando']],
        ['Estudando (pós-graduação)', $g['estudando']],
        ['Tempo médio até a 1ª colocação (meses)', $g['meses_media']],
    ];
    $linhaStats = fn($nome, $s) => [$nome, $s['total'], $s['informados'], $s['ocupados'], $s['empreg_total'], $s['empreg_resp'], $s['na_area_pct'], $s['meses_media']];
    $cab = ['', 'Egressos', 'Informaram situação', 'Ocupados', 'Empregabilidade total (%)', 'Empregabilidade s/ informados (%)', 'Na área (%)', 'Meses até 1ª colocação'];

    $abas['Por curso'] = [array_merge(['Curso'], array_slice($cab, 1))];
    foreach ($d['porCurso'] as $n => $s) $abas['Por curso'][] = $linhaStats($n, $s);

    $abas['Por ano'] = [array_merge(['Ano de conclusão'], array_slice($cab, 1))];
    foreach ($d['porAno'] as $n => $s) $abas['Por ano'][] = $linhaStats((string) $n, $s);

    $abas['Situação'] = [['Situação profissional', 'Egressos']];
    foreach ($d['situacoes'] as $n => $q) $abas['Situação'][] = [$n, $q];

    $abas['Empresas'] = [['Empresa', 'Egressos']];
    foreach ($d['empresas'] as $e) $abas['Empresas'][] = [$e['nome'], $e['qtd']];

    if (!empty($f['incluir_lista'])) {
        $abas['Egressos'] = [['Nome', 'E-mail', 'Curso', 'Ano', 'Situação', 'Empresa', 'Cargo', 'Cidade', 'Meses até 1ª colocação']];
        foreach ($d['lista'] as $e) {
            $abas['Egressos'][] = [$e['nome'] ?? '', $e['email'] ?? '', $e['curso'] ?? '', (string) ($e['anoConclusao'] ?? ''),
                egressoStatusLabel($e['statusProfissional'] ?? ''), $e['empresaAtual'] ?? '', $e['cargoAtual'] ?? '', $e['cidade'] ?? '',
                isset($e['mesesAteColocacao']) && $e['mesesAteColocacao'] !== '' ? (int) $e['mesesAteColocacao'] : ''];
        }
    }
    return $abas;
}

/* ------------------------------------------------------------------ */
/* XLSX                                                                */
/* ------------------------------------------------------------------ */

function relXlsxColuna(int $i): string {
    $s = '';
    $i++;
    while ($i > 0) {
        $m = ($i - 1) % 26;
        $s = chr(65 + $m) . $s;
        $i = intdiv($i - 1, 26);
    }
    return $s;
}

function relXlsxTexto($v): string {
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $v);
    return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/** Devolve os bytes do .xlsx, ou null se a extensao zip nao existir */
function relatorioXlsx(array $abas): ?string {
    if (!class_exists('ZipArchive')) return null;

    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return null;

    $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $nomes = []; $i = 0;
    $overrides = ''; $sheetsXml = ''; $relsXml = '';

    foreach ($abas as $nome => $linhas) {
        $i++;
        $limpo = mb_substr(preg_replace('/[\[\]\:\*\?\/\\\\]/', '', (string) $nome), 0, 31) ?: 'Aba' . $i;
        $nomes[] = $limpo;

        $maxCols = 0;
        foreach ($linhas as $l) $maxCols = max($maxCols, count($l));
        $cols = '';
        for ($c = 1; $c <= $maxCols; $c++) {
            $cols .= '<col min="' . $c . '" max="' . $c . '" width="' . ($c === 1 ? 42 : 22) . '" customWidth="1"/>';
        }

        $rowsXml = '';
        foreach ($linhas as $r => $linha) {
            $rowsXml .= '<row r="' . ($r + 1) . '">';
            foreach (array_values($linha) as $c => $val) {
                $ref = relXlsxColuna($c) . ($r + 1);
                $estilo = $r === 0 ? ' s="1"' : '';
                if ($val === null || $val === '') {
                    $rowsXml .= '<c r="' . $ref . '"' . $estilo . '/>';
                } elseif (is_int($val) || is_float($val)) {
                    $rowsXml .= '<c r="' . $ref . '"' . $estilo . '><v>' . $val . '</v></c>';
                } else {
                    $rowsXml .= '<c r="' . $ref . '"' . $estilo . ' t="inlineStr"><is><t xml:space="preserve">' . relXlsxTexto($val) . '</t></is></c>';
                }
            }
            $rowsXml .= '</row>';
        }

        $zip->addFromString('xl/worksheets/sheet' . $i . '.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="' . $ns . '"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>' . $cols . '</cols><sheetData>' . $rowsXml . '</sheetData></worksheet>');

        $overrides .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $sheetsXml .= '<sheet name="' . relXlsxTexto($limpo) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
        $relsXml   .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
    }

    $relsXml .= '<Relationship Id="rId' . ($i + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

    $zip->addFromString('[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . $overrides . '</Types>');
    $zip->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="' . $ns . '" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $sheetsXml . '</sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $relsXml . '</Relationships>');
    $zip->addFromString('xl/styles.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="' . $ns . '"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF4A9FCA"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs></styleSheet>');
    $zip->close();

    $bytes = file_get_contents($tmp);
    @unlink($tmp);
    return $bytes === false ? null : $bytes;
}

function relatorioCsv(array $abas): string {
    $out = "\xEF\xBB\xBF";
    foreach ($abas as $nome => $linhas) {
        $out .= '"' . str_replace('"', '""', $nome) . "\"\r\n";
        foreach ($linhas as $l) {
            $out .= implode(';', array_map(fn($v) => '"' . str_replace('"', '""', (string) $v) . '"', $l)) . "\r\n";
        }
        $out .= "\r\n";
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/* Documento HTML (PDF e impressao). Sem flex/grid: o dompdf nao suporta */
/* ------------------------------------------------------------------ */

function relTabelaHtml(array $linhas): string {
    $h = '<table><thead><tr>';
    foreach ($linhas[0] as $c) $h .= '<th>' . egh($c) . '</th>';
    $h .= '</tr></thead><tbody>';
    foreach (array_slice($linhas, 1) as $l) {
        $h .= '<tr>';
        foreach ($l as $i => $v) $h .= '<td' . ($i > 0 ? ' class="n"' : '') . '>' . egh(is_float($v) ? relFmt($v) : (string) ($v ?? '—')) . '</td>';
        $h .= '</tr>';
    }
    return $h . '</tbody></table>';
}

function relatorioHtmlDocumento(array $d, array $f, bool $paraImpressao = false): string {
    $abas = relatorioAbas($d, array_merge($f, ['incluir_lista' => false]));
    $g = $d['geral'];
    $kpis = [
        ['Egressos no recorte', $g['total']],
        ['Empregabilidade (sobre o total)', relFmt($g['empreg_total'], '%')],
        ['Empregabilidade (sobre quem informou)', relFmt($g['empreg_resp'], '%')],
        ['Atuando na área (entre ocupados)', relFmt($g['na_area_pct'], '%')],
        ['Tempo médio até 1ª colocação', $g['meses_media'] === null ? '—' : relFmt($g['meses_media']) . ' meses'],
    ];

    $h = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Relatório de Empregabilidade de Egressos</title><style>'
       . 'body{font-family:DejaVu Sans,Arial,sans-serif;font-size:11px;color:#1e293b;margin:24px}'
       . 'h1{font-size:18px;margin:0 0 4px}h2{font-size:13px;margin:22px 0 6px;color:#2B7FA6}'
       . '.sub{color:#64748b;font-size:10px;margin-bottom:14px}'
       . 'table{width:100%;border-collapse:collapse;margin-bottom:6px}'
       . 'th{background:#4A9FCA;color:#fff;text-align:left;padding:5px 6px;font-size:10px}'
       . 'td{border-bottom:1px solid #e2e8f0;padding:5px 6px}td.n{text-align:right}'
       . '.kpi td{border:1px solid #e2e8f0;padding:8px 10px;width:20%}.kpi .v{font-size:16px;font-weight:bold;color:#2B7FA6}'
       . '.aviso{background:#fef9c3;padding:8px;margin-bottom:12px}.nota{color:#64748b;font-size:9px;margin-top:14px}'
       . '@media print{.aviso{display:none}body{margin:10px}}'
       . '</style></head><body>';
    if ($paraImpressao) {
        $h .= '<div class="aviso">Na janela de impressão, escolha <strong>Salvar como PDF</strong> como destino.</div>';
    }
    $h .= '<h1>Relatório de Empregabilidade de Egressos</h1>'
        . '<div class="sub">Central de Estágios &middot; Fundação Santo André<br>' . egh(relDescricaoFiltro($f))
        . '<br>Gerado em ' . date('d/m/Y H:i') . ' por ' . egh($_SESSION['user_name'] ?? '—') . '</div>';

    $h .= '<table class="kpi"><tr>';
    foreach ($kpis as $k) $h .= '<td><div>' . egh($k[0]) . '</div><div class="v">' . egh((string) $k[1]) . '</div></td>';
    $h .= '</tr></table>';

    $h .= '<h2>Por curso</h2>' . relTabelaHtml($abas['Por curso']);
    $h .= '<h2>Por ano de conclusão</h2>' . relTabelaHtml($abas['Por ano']);
    $h .= '<h2>Situação profissional</h2>' . relTabelaHtml($abas['Situação']);
    if (count($abas['Empresas']) > 1) $h .= '<h2>Empresas onde os egressos atuam</h2>' . relTabelaHtml($abas['Empresas']);

    $h .= '<div class="nota">Empregabilidade = egressos empregados, estagiando ou empreendendo ÷ egressos. Os dados de situação são informados pelos próprios egressos no perfil; '
        . 'quem não informou entra no total, mas não no cálculo "sobre quem informou".</div>';

    if ($paraImpressao) $h .= '<script>window.onload=function(){window.print();}</script>';
    return $h . '</body></html>';
}
