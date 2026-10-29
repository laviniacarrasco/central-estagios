<?php
/**
 * =============================================================================
 * PROTEGER AS SENHAS (texto puro -> hash bcrypt)
 * -----------------------------------------------------------------------------
 *   php ferramentas/proteger-senhas.php
 *
 * Troca toda senha que ainda está em texto puro pelo hash dela.
 * NINGUÉM perde o acesso: a pessoa continua entrando com a mesma senha
 * (o login já confere hash com password_verify). A diferença é que a senha
 * deixa de aparecer no usuarios.json, no git e no banco.
 * O mesmo botão existe na tela "Banco de dados".
 * =============================================================================
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Só pelo terminal.\n"); }
require_once __DIR__ . '/../includes/config.php';
@set_time_limit(0);

$n = dadosOperar('usuarios', function (array &$lista) {
    $c = 0;
    foreach ($lista as $i => $u) {
        $s = (string) ($u['senha'] ?? '');
        if ($s !== '' && !preg_match('/^\$2[axy]\$/', $s)) {
            $lista[$i]['senha'] = password_hash($s, PASSWORD_DEFAULT);
            if (++$c % 50 === 0) echo "{$c}...\n";
        }
    }
    return $c;
});
echo "Pronto: " . (int) $n . " senha(s) protegida(s).\n";
