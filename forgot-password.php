<?php
require_once 'includes/config.php';

/**
 * Caso sua config.php ja faca session_start(), esta funcao evita erro de "sessao ja iniciada".
 */
if (!function_exists('session_start_safe')) {
    function session_start_safe() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

// Esta pagina eh publica (nao chama checkAuth), pois o usuario ainda nao esta logado.
header('Content-Type: text/html; charset=UTF-8');
session_start_safe();

$pageTitle = 'Recuperar Senha';

function somenteDigitos($valor) {
    return preg_replace('/\D/', '', (string) $valor);
}

$erro = '';
$sucesso = '';
$etapa = 1;

// Verifica se ja existe uma verificacao valida na sessao (etapa 2)
if (!empty($_SESSION['reset_user_id']) && !empty($_SESSION['reset_expira']) && time() < $_SESSION['reset_expira']) {
    $etapa = 2;
}

$usuarios = loadData('usuarios');

// ===== ETAPA 1: validar email + cpf =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'validar') {
    $emailInformado = trim($_POST['email'] ?? '');
    $cpfInformado   = somenteDigitos($_POST['cpf'] ?? '');

    $usuarioEncontrado = null;
    foreach ($usuarios as $u) {
        $emailUsuario = trim($u['email'] ?? '');
        // Ajuste 'cpf' abaixo se o campo no seu usuarios.json tiver outro nome (ex: 'documento')
        $cpfUsuario   = somenteDigitos($u['cpf'] ?? '');

        if (
            strcasecmp($emailUsuario, $emailInformado) === 0 &&
            $cpfUsuario !== '' &&
            $cpfUsuario === $cpfInformado
        ) {
            $usuarioEncontrado = $u;
            break;
        }
    }

    if ($usuarioEncontrado) {
        $_SESSION['reset_user_id'] = $usuarioEncontrado['id'];
        $_SESSION['reset_expira']  = time() + (10 * 60); // token valido por 10 minutos
        $etapa = 2;
    } else {
        $erro = 'E-mail ou CPF nao conferem com nenhum cadastro. Verifique os dados e tente novamente.';
        $etapa = 1;
    }
}

// ===== ETAPA 2: definir nova senha =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'redefinir') {

    if (empty($_SESSION['reset_user_id']) || empty($_SESSION['reset_expira']) || time() >= $_SESSION['reset_expira']) {
        $erro = 'Sua sessao de verificacao expirou. Por favor, comece novamente.';
        $etapa = 1;
        unset($_SESSION['reset_user_id'], $_SESSION['reset_expira']);
    } else {
        $novaSenha    = $_POST['nova_senha'] ?? '';
        $confirmSenha = $_POST['confirmar_senha'] ?? '';

        if (strlen($novaSenha) < 6) {
            $erro = 'A senha deve ter pelo menos 6 caracteres.';
            $etapa = 2;
        } elseif ($novaSenha !== $confirmSenha) {
            $erro = 'As senhas nao coincidem.';
            $etapa = 2;
        } else {
            $idAlvo = $_SESSION['reset_user_id'];
            $atualizado = false;

            foreach ($usuarios as &$u) {
                if ($u['id'] == $idAlvo) {
                    // Ajuste 'senha' abaixo se o campo no seu usuarios.json tiver outro nome
                    $u['senha'] = password_hash($novaSenha, PASSWORD_DEFAULT);
                    $atualizado = true;
                    break;
                }
            }
            unset($u);

            if ($atualizado) {
                saveData('usuarios', $usuarios);
                unset($_SESSION['reset_user_id'], $_SESSION['reset_expira']);
                header('Location: index.php?senha_redefinida=1');
                exit;
            } else {
                $erro = 'Nao foi possivel localizar seu cadastro. Tente novamente do inicio.';
                $etapa = 1;
            }
        }
    }
}

// Permite cancelar a redefinicao e voltar para a etapa 1
if (isset($_GET['cancelar'])) {
    unset($_SESSION['reset_user_id'], $_SESSION['reset_expira']);
    header('Location: forgot-password.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?php echo $pageTitle; ?> - Central de Estagios</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; }
        .fp-card { box-shadow: 0 10px 40px rgba(0,0,0,.08); }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-[#4A9FCA] rounded-2xl flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-key text-white text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Recuperar Senha</h1>
            <p class="text-gray-500 text-sm mt-1">
                <?php echo $etapa === 1
                    ? 'Informe seu e-mail e CPF cadastrados para continuar'
                    : 'Defina sua nova senha de acesso'; ?>
            </p>
        </div>

        <div class="bg-white fp-card rounded-2xl p-8">

            <?php if ($erro): ?>
                <div class="mb-5 p-3 bg-red-50 border border-red-200 text-red-600 text-sm rounded-xl flex items-start gap-2">
                    <i class="fas fa-exclamation-circle mt-0.5"></i>
                    <span><?php echo htmlspecialchars($erro); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($etapa === 1): ?>

                <form method="POST" action="forgot-password.php" accept-charset="UTF-8" class="space-y-4">
                    <input type="hidden" name="action" value="validar">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">E-mail cadastrado</label>
                        <div class="relative">
                            <i class="fas fa-envelope absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="email" name="email" required
                                class="w-full h-11 pl-10 pr-3 border border-gray-200 rounded-xl focus:outline-none focus:border-[#4A9FCA] focus:ring-2 focus:ring-[#4A9FCA]/20"
                                placeholder="seuemail@exemplo.com">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                        <div class="relative">
                            <i class="fas fa-id-card absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="text" name="cpf" id="cpfInput" required maxlength="14"
                                class="w-full h-11 pl-10 pr-3 border border-gray-200 rounded-xl focus:outline-none focus:border-[#4A9FCA] focus:ring-2 focus:ring-[#4A9FCA]/20"
                                placeholder="000.000.000-00">
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full h-11 bg-[#4A9FCA] text-white font-semibold rounded-xl hover:bg-[#3A8FB0] transition-colors flex items-center justify-center gap-2">
                        <i class="fas fa-search"></i> Verificar Dados
                    </button>
                </form>

            <?php else: ?>

                <form method="POST" action="forgot-password.php" accept-charset="UTF-8" class="space-y-4">
                    <input type="hidden" name="action" value="redefinir">

                    <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-xl flex items-start gap-2">
                        <i class="fas fa-check-circle mt-0.5"></i>
                        <span>Identidade confirmada. Agora crie sua nova senha.</span>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nova senha</label>
                        <div class="relative">
                            <i class="fas fa-lock absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="password" name="nova_senha" id="novaSenha" required minlength="6"
                                class="w-full h-11 pl-10 pr-10 border border-gray-200 rounded-xl focus:outline-none focus:border-[#4A9FCA] focus:ring-2 focus:ring-[#4A9FCA]/20"
                                placeholder="Minimo 6 caracteres">
                            <button type="button" onclick="alternarSenha('novaSenha', this)"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar nova senha</label>
                        <div class="relative">
                            <i class="fas fa-lock absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="password" name="confirmar_senha" id="confirmarSenha" required minlength="6"
                                class="w-full h-11 pl-10 pr-10 border border-gray-200 rounded-xl focus:outline-none focus:border-[#4A9FCA] focus:ring-2 focus:ring-[#4A9FCA]/20"
                                placeholder="Repita a nova senha">
                            <button type="button" onclick="alternarSenha('confirmarSenha', this)"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full h-11 bg-[#4A9FCA] text-white font-semibold rounded-xl hover:bg-[#3A8FB0] transition-colors flex items-center justify-center gap-2">
                        <i class="fas fa-save"></i> Redefinir Senha
                    </button>

                    <a href="forgot-password.php?cancelar=1"
                        class="block text-center text-sm text-gray-500 hover:text-gray-700 mt-2">
                        Cancelar e comecar novamente
                    </a>
                </form>

            <?php endif; ?>

        </div>

        <p class="text-center text-sm text-gray-500 mt-6">
            Lembrou sua senha?
            <a href="index.php" class="text-[#4A9FCA] font-semibold hover:underline">Voltar para o login</a>
        </p>
    </div>

    <script>
        // Mascara simples de CPF: 000.000.000-00
        const cpfInput = document.getElementById('cpfInput');
        if (cpfInput) {
            cpfInput.addEventListener('input', () => {
                let v = cpfInput.value.replace(/\D/g, '').slice(0, 11);
                v = v.replace(/(\d{3})(\d)/, '$1.$2');
                v = v.replace(/(\d{3})(\d)/, '$1.$2');
                v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                cpfInput.value = v;
            });
        }

        function alternarSenha(campoId, btn) {
            const campo = document.getElementById(campoId);
            const icone = btn.querySelector('i');
            if (campo.type === 'password') {
                campo.type = 'text';
                icone.classList.remove('fa-eye');
                icone.classList.add('fa-eye-slash');
            } else {
                campo.type = 'password';
                icone.classList.remove('fa-eye-slash');
                icone.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
