<?php
/**
 * =============================================================================
 * CRIAR (OU ATUALIZAR) O ADMIN DE DENÚNCIAS
 * -----------------------------------------------------------------------------
 *   php ferramentas/criar-admin-denuncias.php CPF "Nome" email@fsa.br SENHA
 *
 * - Se o CPF ainda não existe: cria o usuário com is_admin = true e
 *   tipo = "admin_denuncias".
 * - Se o CPF já existe: só muda essa pessoa para Admin de Denúncias
 *   (mantém id, senha e o resto). A senha só é trocada se você passar uma.
 * A senha é salva em hash (bcrypt). Funciona com usuarios.json ou com o banco.
 * =============================================================================
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Só pelo terminal.\n"); }
require_once __DIR__ . '/../includes/config.php';

$cpf   = preg_replace('/\D/', '', (string) ($argv[1] ?? ''));
$nome  = trim((string) ($argv[2] ?? ''));
$email = trim((string) ($argv[3] ?? ''));
$senha = (string) ($argv[4] ?? '');

if (strlen($cpf) !== 11) exit("Uso: php ferramentas/criar-admin-denuncias.php CPF \"Nome\" email@fsa.br SENHA\n(CPF com 11 números)\n");

$r = dadosOperar('usuarios', function (array &$lista) use ($cpf, $nome, $email, $senha) {
    foreach ($lista as $i => $u) {
        if ((string) ($u['cpf'] ?? '') !== $cpf) continue;
        $lista[$i]['is_admin'] = true;
        $lista[$i]['tipo']     = 'admin_denuncias';
        if ($nome !== '')  $lista[$i]['nome']  = $nome;
        if ($email !== '') $lista[$i]['email'] = $email;
        if ($senha !== '') $lista[$i]['senha'] = password_hash($senha, PASSWORD_DEFAULT);
        return 'atualizado: ' . ($lista[$i]['nome'] ?? '') . ' (id ' . ($lista[$i]['id'] ?? '?') . ')';
    }
    if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha) < 6) {
        return 'ERRO: para criar um usuário novo, informe nome, e-mail válido e senha com 6+ caracteres.';
    }
    $maior = 0;
    foreach ($lista as $u) $maior = max($maior, (int) ($u['id'] ?? 0));
    $lista[] = [
        'id'       => $maior + 1,
        'cpf'      => $cpf,
        'nome'     => $nome,
        'email'    => $email,
        'senha'    => password_hash($senha, PASSWORD_DEFAULT),
        'is_admin' => true,
        'tipo'     => 'admin_denuncias',
    ];
    return 'criado: ' . $nome . ' (id ' . ($maior + 1) . ')';
});
echo "Admin de Denúncias " . $r . "\n";
echo "Se a pessoa estiver logada, ela precisa sair e entrar de novo.\n";
