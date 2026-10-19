<?php
/**
 * =============================================================================
 * MODULO EMPRESAS PARCEIRAS
 * -----------------------------------------------------------------------------
 * O admin cadastra as empresas em admin-empresas.php (data/empresas_parceiras.json)
 * e os alunos consultam em empresas.php. As vagas abertas de cada empresa sao
 * encontradas pelo nome (vagas da FSA e vagas de egressos que citam a empresa).
 * =============================================================================
 */

require_once __DIR__ . '/egressos.php';

function empresasLista(): array {
    $d = loadData('empresas_parceiras');
    return is_array($d) ? array_values($d) : [];
}

function empresasSalvar(array $lista): void {
    saveData('empresas_parceiras', array_values($lista));
}

function empresasCursosLista($texto): array {
    return array_values(array_filter(array_map('trim', explode(',', (string) $texto))));
}

/** Remove um arquivo de logo, somente se estiver dentro de uploads/empresas/ */
function empresasApagarLogo($caminho): void {
    $caminho = (string) $caminho;
    if ($caminho === '' || strpos($caminho, 'uploads/empresas/') !== 0 || strpos($caminho, '..') !== false) return;
    $arq = __DIR__ . '/../' . $caminho;
    if (is_file($arq)) @unlink($arq);
}

/** [id => ['fsa'=>n, 'egresso'=>n, 'total'=>n]] com as vagas abertas de cada empresa */
function empresasContarVagas(array $empresas): array {
    $fsa = array_filter(loadData('platform_jobs'), 'vagaVigente');
    $egr = array_filter(egressoVagas(), function ($v) {
        return egressoVagaStatusEfetivo($v) === 'aprovada';
    });
    $out = [];
    foreach ($empresas as $e) {
        $nome = trim((string) ($e['nome'] ?? ''));
        $n1 = 0;
        $n2 = 0;
        if (mb_strlen($nome) >= 2) {
            foreach ($fsa as $j) {
                if (mb_stripos((string) ($j['company'] ?? ''), $nome) !== false) $n1++;
            }
            foreach ($egr as $j) {
                if (mb_stripos((string) ($j['company'] ?? ''), $nome) !== false) $n2++;
            }
        }
        $out[(int) ($e['id'] ?? 0)] = ['fsa' => $n1, 'egresso' => $n2, 'total' => $n1 + $n2];
    }
    return $out;
}
