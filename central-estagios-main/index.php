<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cpf = $_POST['cpf'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($cpf) && !empty($password)) {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_name'] = 'Lavínia Carrasco';
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Preencha todos os campos!';
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
            
            <!-- Logo FSA -->
            <div class="flex justify-center mb-6">
                <img src="https://vectorseek.com/wp-content/uploads/2024/02/Fundacao-Santo-Andre-Logo-Vector.svg-.png" alt="Fundação Santo André" class="h-20">
            </div>
            
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-800 mb-2">Central de Estágios</h1>
                <p class="text-gray-600">Fundação Santo André</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-600 rounded-lg text-sm">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                    <input type="text" name="cpf" placeholder="000.000.000-00" 
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
                <a href="#" class="block text-sm text-[#4A9FCA] hover:underline">Esqueci minha senha</a>
                <a href="#" class="block text-sm text-gray-600 hover:underline">Primeiro acesso? Cadastre-se</a>
            </div>
        </div>
    </div>
</body>
</html>