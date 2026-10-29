<?php
/**
 * Teste da busca de vagas externas (so pelo terminal):
 *   php teste-gupy.php
 * Nao grava nada: so mostra o que a Gupy devolveu.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/melhorias.php';
require_once __DIR__ . '/includes/vagas-externas.php';

$r = vexBuscarGupy();
echo "=== CONSULTAS ===\n" . implode("\n", $r['log']) . "\n\n";
if ($r['erro'] !== '') echo "ERRO: " . $r['erro'] . "\n\n";

$noFiltro = array_filter($r['vagas'], fn($v) => (vexCidadeAceita($v['cidade']) || $v['remoto']));
$estagio  = array_filter($noFiltro, fn($v) => $v['tipoOk'] || preg_match('/estag|trainee|aprendiz/', mhSemAcento($v['titulo'])));

echo "Vagas recebidas: " . count($r['vagas']) . "\n";
echo "Em SP/ABC ou remotas: " . count($noFiltro) . "\n";
echo "Dessas, de estagio/trainee: " . count($estagio) . "\n\n";
echo "=== 10 PRIMEIRAS ===\n";
foreach (array_slice(array_values($estagio), 0, 10) as $v) {
    echo "- {$v['titulo']} | {$v['empresa']} | " . ($v['cidade'] ?: '-') . " | {$v['modalidade']}\n  {$v['link']}\n";
}
