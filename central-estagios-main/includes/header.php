<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userName = $_SESSION['user_name'] ?? 'Visitante';
$pageTitle = $pageTitle ?? 'Central de Estágios';

// Função de iniciais: primeira letra do nome + primeira letra do último sobrenome
if (!function_exists('getInitials')) {
    function getInitials($name) {
        $parts = explode(' ', trim($name));
        $first = strtoupper(substr($parts[0], 0, 1));
        $last  = count($parts) > 1 ? strtoupper(substr(end($parts), 0, 1)) : '';
        return $first . $last;
    }
}


// Carregar foto de perfil salva
$profileFile = 'data/userProfile.json';
$userProfile = file_exists($profileFile) ? json_decode(file_get_contents($profileFile), true) : [];
$fotoSalva = $userProfile['foto'] ?? null;
$temFoto = $fotoSalva && file_exists($fotoSalva);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - FSA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeIn 0.5s ease-out; }
        .sidebar { background: linear-gradient(180deg, #4A9FCA 0%, #2B7FA6 100%); }
    </style>
</head>
<body class="min-h-screen bg-gray-50">

    <!-- Sidebar -->
    <div class="sidebar fixed left-0 top-0 h-full w-16 flex flex-col items-center py-4 z-40">
        <div class="mb-8 mt-4">
            <img src="https://vectorseek.com/wp-content/uploads/2024/02/Fundacao-Santo-Andre-Logo-Vector.svg-.png" alt="FSA" class="h-10">
        </div>

 <nav class="flex flex-col gap-4 flex-1">
    <a href="dashboard.php" class="p-2 text-white hover:bg-white/20 rounded-lg transition-all" title="Início">
        <i class="fas fa-home text-xl"></i>
    </a>
    <a href="profile.php" class="p-2 text-white hover:bg-white/20 rounded-lg transition-all" title="Perfil">
        <i class="fas fa-user text-xl"></i>
    </a>
    <a href="curriculum.php" class="p-2 text-white hover:bg-white/20 rounded-lg transition-all" title="Currículo">
        <i class="fas fa-file-alt text-xl"></i>
    </a>
    <a href="documents.php" class="p-2 text-white hover:bg-white/20 rounded-lg transition-all" title="Documentos">
        <i class="fas fa-folder text-xl"></i>
    </a>
    <a href="jobs.php" class="p-2 text-white hover:bg-white/20 rounded-lg transition-all" title="Vagas">
        <i class="fas fa-briefcase text-xl"></i>
    </a>
    <a href="my-applications.php" class="p-2 text-white hover:bg-white/20 rounded-lg transition-all" title="Candidaturas">
        <i class="fas fa-clipboard-list text-xl"></i>
    </a>
    <a href="career-tips.php" class="p-2 text-white hover:bg-white/20 rounded-lg transition-all" title="Dicas">
        <i class="fas fa-lightbulb text-xl"></i>
    </a>
    <hr class="border-white/20 my-2">
    <a href="admin-jobs.php" class="p-2 text-white/70 hover:bg-white/20 rounded-lg transition-all" title="Admin Vagas">
        <i class="fas fa-cog text-lg"></i>
    </a>
    <a href="posts.php" class="p-2 text-white/70 hover:bg-white/20 rounded-lg transition-all" title="Admin Posts">
        <i class="fas fa-newspaper text-lg"></i>
    </a>
</nav>

        <div class="mb-4">
            <a href="index.php?logout=1" class="p-2 text-white/70 hover:bg-white/20 rounded-lg transition-all" title="Sair">
                <i class="fas fa-sign-out-alt text-xl"></i>
            </a>
        </div>
    </div>

    <!-- Header -->
    <header class="fixed top-0 right-0 left-16 h-16 bg-white border-b border-gray-200 flex items-center px-8 z-30">
        <h1 class="text-xl font-bold text-gray-900"><?php echo $pageTitle; ?></h1>

        <a href="profile.php" class="ml-auto flex items-center gap-3 px-4 py-2 border-2 border-gray-200 rounded-full hover:border-[#4A9FCA] hover:bg-gray-50 transition-all cursor-pointer" title="Ver Perfil">
            <span class="text-sm text-gray-700 font-medium"><?php echo htmlspecialchars($userName); ?></span>

            <?php if ($temFoto): ?>
                <img
                    src="<?php echo htmlspecialchars($fotoSalva); ?>"
                    alt="Foto de perfil"
                    class="w-9 h-9 rounded-full object-cover border-2 border-[#4A9FCA]"
                    onerror="this.style.display='none'; document.getElementById('headerInitials').style.display='flex';">
                <div id="headerInitials" class="w-9 h-9 bg-gradient-to-br from-[#4A9FCA] to-[#3A8FB0] rounded-full items-center justify-center text-white font-bold text-sm hidden">
                    <?php echo getInitials($userName); ?>
                </div>
            <?php else: ?>
                <div class="w-9 h-9 bg-gradient-to-br from-[#4A9FCA] to-[#3A8FB0] rounded-full flex items-center justify-center text-white font-bold text-sm">
                    <?php echo getInitials($userName); ?>
                </div>
            <?php endif; ?>
        </a>
    </header>
