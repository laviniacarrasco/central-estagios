<?php
// Teste rápido: php teste_gupy.php
require __DIR__ . '/GupyFonte.php';

$fonte = new GupyFonte();
$vagas = $fonte->buscar();

echo "=== LOG ===\n" . implode("\n", $fonte->log()) . "\n\n";
echo "=== PRIMEIRAS 5 VAGAS ===\n";
foreach (array_slice($vagas, 0, 5) as $v) {
    printf("- %s | %s | %s%s\n  %s\n",
        $v['titulo'], $v['empresa'], $v['cidade'],
        $v['remoto'] ? ' (remoto)' : '', $v['link']);
}
echo "\nTotal: " . count($vagas) . "\n";
