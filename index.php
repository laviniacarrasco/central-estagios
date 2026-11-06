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
    header('Location: ' . perfilHome());
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

            // Egressos: tipo "egresso" (area propria) e "admin_egressos" (admin que cuida deles)
            $tipoUsuario = $usuarioEncontrado['tipo'] ?? '';
            $_SESSION['is_egresso']        = ($tipoUsuario === 'egresso') && empty($usuarioEncontrado['is_admin']);
            $_SESSION['is_admin_egressos'] = ($tipoUsuario === 'admin_egressos') && !empty($usuarioEncontrado['is_admin']);
            // Aluno restrito: so inicio, vagas (sem egressos), perfil/curriculo e dicas (sem IA)
            $_SESSION['is_restrito']       = ($tipoUsuario === 'aluno_restrito') && empty($usuarioEncontrado['is_admin']);

            // Perfil central (includes/perfis.php): define menu, paginas e recursos
            $_SESSION['perfil'] = perfilDoUsuario($usuarioEncontrado);
            perfilSincronizarSessao();

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

            header('Location: ' . perfilHome());
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
    <title>Central de Estágios - Entrar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
        .campo:focus { outline: none; border-color: #4A9FCA; box-shadow: 0 0 0 3px rgba(74, 159, 202, .18); }
    </style>
</head>
<body class="min-h-screen bg-[#F4F7FA] flex">

    <!-- Lado da marca (some no celular) -->
    <aside class="hidden lg:flex lg:w-1/2 relative overflow-hidden bg-gradient-to-br from-[#4A9FCA] via-[#3A8FB0] to-[#2B7FA6] text-white p-14 flex-col justify-between">
        <div class="absolute -right-24 -top-24 w-96 h-96 rounded-full bg-white/10"></div>
        <div class="absolute -left-20 bottom-10 w-72 h-72 rounded-full bg-white/10"></div>
        <div class="relative flex items-center gap-3">
            <img src="assets/img/logo-fsa.png" alt="Fundação Santo André" class="h-16 w-auto">
            
        </div>
        <div class="relative max-w-lg">
            <h1 class="text-4xl xl:text-5xl font-extrabold leading-tight mb-5">Seu caminho até o estágio começa aqui.</h1>
            <p class="text-white/85 text-lg mb-10">Vagas, currículo, assinaturas e mentoria com ex-alunos, tudo num lugar só.</p>
            <ul class="space-y-4">
                <?php foreach ([['fa-briefcase', 'Vagas da FSA, de parceiros e de egressos'], ['fa-wand-magic-sparkles', 'Currículo com avaliação por IA'], ['fa-hands-helping', 'Mentoria com quem já passou por isso']] as [$ic, $tx]): ?>
                    <li class="flex items-center gap-3"><span class="w-10 h-10 rounded-xl bg-white/15 flex items-center justify-center"><i class="fas <?= $ic ?>"></i></span><span class="font-medium"><?= $tx ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <p class="relative text-sm text-white/70">Central de Estágios e Carreiras · FSA</p>
    </aside>

    <!-- Formulário -->
    <main class="flex-1 flex items-center justify-center p-6">
        <div class="w-full max-w-md">
            <div class="lg:hidden flex justify-center mb-8">
                <img src="assets/img/logo-fsa.png" alt="Fundação Santo André" class="h-16">
            </div>

            <h2 class="text-3xl font-extrabold text-slate-900 mb-2">Entrar</h2>
            <p class="text-slate-500 mb-8">Use seu CPF e sua senha da Central de Estágios.</p>

            <?php if (!empty($_GET['senha_redefinida'])): ?>
                <div class="mb-5 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm"><i class="fas fa-check-circle mr-2"></i>Senha redefinida! Entre com a nova senha.</div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="mb-5 p-4 bg-red-50 border border-red-200 text-red-600 rounded-xl text-sm"><i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" class="space-y-5" id="loginForm">
                <div>
                    <label for="cpf" class="block text-sm font-semibold text-slate-700 mb-1.5">CPF</label>
                    <div class="relative">
                        <i class="fas fa-id-card absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" name="cpf" id="cpf" placeholder="000.000.000-00" maxlength="14" inputmode="numeric" autocomplete="username"
                               class="campo w-full h-12 pl-11 pr-4 bg-white border border-slate-200 rounded-xl transition" required autofocus>
                    </div>
                </div>
                <div>
                    <label for="senha" class="block text-sm font-semibold text-slate-700 mb-1.5">Senha</label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="password" name="password" id="senha" placeholder="Sua senha" autocomplete="current-password"
                               class="campo w-full h-12 pl-11 pr-12 bg-white border border-slate-200 rounded-xl transition" required>
                        <button type="button" id="verSenha" class="absolute right-3 top-1/2 -translate-y-1/2 w-8 h-8 text-slate-400 hover:text-slate-600" aria-label="Mostrar senha"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="flex justify-end">
                    <a href="forgot-password.php" class="text-sm font-semibold text-[#2B7FA6] hover:underline">Esqueci minha senha</a>
                </div>
                <button type="submit" class="w-full h-12 bg-[#4A9FCA] hover:bg-[#2B7FA6] text-white font-bold rounded-xl shadow-lg shadow-[#4A9FCA]/30 transition">
                    Entrar <i class="fas fa-arrow-right ml-2"></i>
                </button>
            </form>

            <div class="mt-8 p-4 rounded-xl bg-white border border-slate-200 text-sm text-slate-600">
                <strong class="text-slate-800">Primeiro acesso?</strong> Seu cadastro vem da secretaria. Clique em
                <a href="forgot-password.php" class="font-semibold text-[#2B7FA6] hover:underline">Esqueci minha senha</a> para criar a sua.
            </div>
        </div>
    </main>

    <script>
        document.getElementById('cpf').addEventListener('input', function (e) {
            let v = e.target.value.replace(/\D/g, '').slice(0, 11);
            if (v.length > 9) v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
            else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
            else if (v.length > 3) v = v.replace(/(\d{3})(\d{1,3})/, '$1.$2');
            e.target.value = v;
        });
        document.getElementById('verSenha').addEventListener('click', function () {
            const s = document.getElementById('senha'), ver = s.type === 'password';
            s.type = ver ? 'text' : 'password';
            this.innerHTML = ver ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
        });
    </script>
</body>
</html>
