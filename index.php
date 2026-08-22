<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/acesso_log.php';

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$error = '';

// Se já estiver logado, vai direto pro dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cpfInput = $_POST['cpf'] ?? '';
    $password = $_POST['password'] ?? '';

    $cpf = preg_replace('/\D/', '', $cpfInput);

    if (empty($cpf) || empty($password)) {
        $error = 'Preencha todos os campos!';
    } else {
        $usuarios = loadData('usuarios');
        $usuarioEncontrado = null;

        foreach ($usuarios as $usuario) {
            if (isset($usuario['cpf']) && $usuario['cpf'] === $cpf) {
                $usuarioEncontrado = $usuario;
                break;
            }
        }

        $senhaValida = false;

        if ($usuarioEncontrado && isset($usuarioEncontrado['senha'])) {
            $senhaSalva = $usuarioEncontrado['senha'];

            if (preg_match('/^\$2[axy]\$/', $senhaSalva)) {
                // Senha ja esta em hash bcrypt (ex: quem redefiniu pelo "Esqueci minha senha")
                $senhaValida = password_verify($password, $senhaSalva);
            } else {
                // Senha antiga, ainda em texto puro
                $senhaValida = ($senhaSalva === $password);

                // Aproveita o login para migrar essa senha para hash automaticamente
                if ($senhaValida) {
                    $usuariosAtualizados = loadData('usuarios');
                    foreach ($usuariosAtualizados as &$u) {
                        if ($u['id'] == $usuarioEncontrado['id']) {
                            $u['senha'] = password_hash($password, PASSWORD_DEFAULT);
                            break;
                        }
                    }
                    unset($u);
                    saveData('usuarios', $usuariosAtualizados);
                }
            }
        }

        if ($usuarioEncontrado && $senhaValida) {
            $_SESSION['user_id']    = $usuarioEncontrado['id'];
            $_SESSION['user_name']  = $usuarioEncontrado['nome'];
            $_SESSION['user_cpf']   = $usuarioEncontrado['cpf'];
            $_SESSION['is_admin']   = !empty($usuarioEncontrado['is_admin']);

            /* =================================================================
             * REGISTRO DE ACESSO (NOVO)
             * -----------------------------------------------------------------
             * 1) Grava a data/hora do login mais recente diretamente no
             *    cadastro do usuario (campo 'ultimoAcesso' em usuarios.json),
             *    usado para exibir "Último acesso" na tabela do admin.
             * 2) Registra o evento no log geral de acessos (acessos_log.json),
             *    usado para o grafico de engajamento dos ultimos 7 dias.
             * ================================================================= */
            $usuariosParaAtualizar = loadData('usuarios');
            foreach ($usuariosParaAtualizar as &$u) {
                if ($u['id'] == $usuarioEncontrado['id']) {
                    $u['ultimoAcesso'] = date('Y-m-d H:i:s');
                    break;
                }
            }
            unset($u);
            saveData('usuarios', $usuariosParaAtualizar);

            registrarAcesso($usuarioEncontrado['id']);

            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'CPF ou senha inválidos!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central de Estágios - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-[#4A9FCA] via-[#3A8FB0] to-[#2B7FA6] flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-2xl p-8">

            <div class="flex justify-center mb-6">
                <img src="https://vectorseek.com/wp-content/uploads/2024/02/Fundacao-Santo-Andre-Logo-Vector.svg-.png" alt="Fundação Santo André" class="h-20">
            </div>

            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-800 mb-2">Central de Estágios</h1>
                <p class="text-gray-600">Fundação Santo André</p>
            </div>

            <?php if (!empty($_GET['senha_redefinida'])): ?>
                <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg text-sm">
                    Senha redefinida com sucesso! Faça login com sua nova senha.
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-600 rounded-lg text-sm">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6" id="loginForm">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                    <input type="text" name="cpf" id="cpf" placeholder="000.000.000-00"
                           maxlength="14" inputmode="numeric"
                           class="w-full h-12 px-4 border rounded-lg focus:ring-2 focus:ring-[#4A9FCA] focus:border-transparent transition-all" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Senha</label>
                    <input type="password" name="password" placeholder="••••••••••"
                           class="w-full h-12 px-4 border rounded-lg focus:ring-2 focus:ring-[#4A9FCA] focus:border-transparent transition-all" required>
                </div>

                <button type="submit" class="w-full h-12 bg-gradient-to-r from-[#4A9FCA] to-[#3A8FB0] text-white text-lg font-semibold rounded-lg hover:shadow-lg transition-all">
                    Entrar
                </button>
            </form>

            <div class="mt-6 text-center space-y-2">
                <a href="forgot-password.php" class="block text-sm text-[#4A9FCA] hover:underline">Esqueci minha senha</a>
                <a href="#" class="block text-sm text-gray-600 hover:underline">Primeiro acesso? Cadastre-se</a>
            </div>
        </div>
    </div>

    <script>
        const cpfInput = document.getElementById('cpf');
        cpfInput.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.slice(0, 11);

            if (value.length > 9) {
                value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
            } else if (value.length > 6) {
                value = value.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
            } else if (value.length > 3) {
                value = value.replace(/(\d{3})(\d{1,3})/, '$1.$2');
            }

            e.target.value = value;
        });
    </script>
</body>
</html>
