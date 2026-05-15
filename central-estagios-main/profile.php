<?php

require_once 'includes/config.php';
checkAuth();

$pageTitle = 'Meu Perfil';
$userName = $_SESSION['user_name'] ?? 'Lavínia Carrasco';

function getInitials($name) {
    $parts = explode(' ', trim($name));
    $first = strtoupper(substr($parts[0], 0, 1));
    $last  = count($parts) > 1 ? strtoupper(substr(end($parts), 0, 1)) : '';
    return $first . $last;
}

$profileFile = 'data/userProfile.json';
$userProfile = file_exists($profileFile) ? json_decode(file_get_contents($profileFile), true) : [];

// ✅ Lê habilidades do currículo
$curriculumFile = 'data/userCurriculum.json';
$curriculumData = file_exists($curriculumFile)
    ? json_decode(file_get_contents($curriculumFile), true)
    : [];

$habilidades = $curriculumData['habilidades'] ?? ['Python', 'JavaScript', 'SQL', 'React', 'Machine Learning'];


// ✅ Upload de foto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto_perfil'])) {
    $uploadDir = 'uploads/profile/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $file = $_FILES['foto_perfil'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if (in_array($ext, $allowedExts) && $file['error'] === 0) {
        // Remove foto antiga
        if (!empty($userProfile['foto']) && file_exists($userProfile['foto'])) {
            unlink($userProfile['foto']);
        }

        $fileName = 'avatar_' . time() . '.' . $ext;
        $uploadPath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $userProfile['foto'] = $uploadPath;
            saveData('userProfile', $userProfile);
        }
    }
    header('Location: profile.php?foto=ok');
    exit;
}

// ✅ Remover foto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remover_foto'])) {
    if (!empty($userProfile['foto']) && file_exists($userProfile['foto'])) {
        unlink($userProfile['foto']); // Deleta o arquivo físico
    }
    $userProfile['foto'] = null;
    saveData('userProfile', $userProfile); // Salva JSON sem foto
    header('Location: profile.php?removida=1');
    exit;
}

$fotoSalva = $userProfile['foto'] ?? null;
$temFoto = $fotoSalva && file_exists($fotoSalva);

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Meu Perfil</h2>
            <p class="text-gray-600">Mantenha suas informações atualizadas</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Coluna Esquerda -->
            <div class="space-y-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm">
                    <div class="flex flex-col items-center text-center mb-6">

                        <!-- Foto com lápis -->
                        <div class="relative mb-4">
                            <?php if ($temFoto): ?>
                                <img
                                    src="<?php echo htmlspecialchars($fotoSalva); ?>"
                                    alt="Foto de perfil"
                                    class="w-28 h-28 rounded-full object-cover border-4 border-[#4A9FCA] shadow-md">
                            <?php else: ?>
                                <div class="w-28 h-28 bg-gradient-to-br from-[#4A9FCA] to-[#3A8FB0] rounded-full flex items-center justify-center shadow-md">
                                    <span class="text-white text-4xl font-bold">
                                        <?php echo getInitials($userName); ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <!-- Botão lápis -->
                            <label for="uploadFoto"
                                class="absolute bottom-1 right-1 w-8 h-8 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-full flex items-center justify-center cursor-pointer shadow-lg transition-all hover:scale-110"
                                title="Alterar foto de perfil">
                                <i class="fas fa-pencil-alt text-xs"></i>
                            </label>

                            <!-- Form upload -->
                            <form id="fotoForm" method="POST" action="profile.php" enctype="multipart/form-data">
                                <input
                                    type="file"
                                    id="uploadFoto"
                                    name="foto_perfil"
                                    accept="image/*"
                                    class="hidden"
                                    onchange="document.getElementById('fotoForm').submit()">
                            </form>
                        </div>

                        <h2 class="text-2xl font-bold text-gray-900 mb-1"><?php echo htmlspecialchars($userName); ?></h2>
                        <span class="px-3 py-1 bg-[#4A9FCA] text-white rounded-full text-sm">Ciência de Dados e IA</span>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center gap-3 p-2">
                            <i class="fas fa-envelope text-[#4A9FCA]"></i>
                            <div>
                                <p class="text-xs text-gray-500">E-mail Institucional</p>
                                <p class="text-sm text-gray-700">lavinia.769029@graduacao.fsa.br</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 p-2">
                            <i class="fas fa-phone text-[#4A9FCA]"></i>
                            <div>
                                <p class="text-xs text-gray-500">Telefone</p>
                                <p class="text-sm text-gray-700">(11) 98765-4321</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 p-2">
                            <i class="fas fa-map-marker-alt text-[#4A9FCA]"></i>
                            <div>
                                <p class="text-xs text-gray-500">Localização</p>
                                <p class="text-sm text-gray-700">Santo André, SP</p>
                            </div>
                        </div>
                    </div>

                    <!-- ✅ Botão remover foto corrigido -->
                    <?php if ($temFoto): ?>
                        <form method="POST" action="profile.php" class="mt-4" onsubmit="return confirm('Tem certeza que deseja remover a foto de perfil?')">
                            <input type="hidden" name="remover_foto" value="1">
                            <button type="submit" class="w-full py-2 text-sm text-red-500 border border-red-200 rounded-xl hover:bg-red-50 transition-colors">
                                <i class="fas fa-trash-alt mr-1"></i> Remover foto
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Coluna Direita -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Informações Acadêmicas -->
                <div class="bg-white p-6 rounded-2xl shadow-sm">
                    <h3 class="text-xl font-bold text-gray-900 mb-6">
                        <i class="fas fa-graduation-cap text-[#4A9FCA] mr-2"></i>Informações Acadêmicas
                    </h3>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="p-4 bg-purple-50 rounded-lg border border-purple-100">
                            <p class="text-xs text-purple-700 font-bold uppercase mb-1">Período</p>
                            <p class="font-semibold text-gray-800">3º Semestre</p>
                        </div>
                        <div class="p-4 bg-green-50 rounded-lg border border-green-100">
                            <p class="text-xs text-green-700 font-bold uppercase mb-1">Turno</p>
                            <p class="font-semibold text-gray-800">Noturno</p>
                        </div>
                        <div class="p-4 bg-orange-50 rounded-lg border border-orange-100">
                            <p class="text-xs text-orange-700 font-bold uppercase mb-1">Situação</p>
                            <p class="font-semibold text-gray-800">Ativo</p>
                        </div>
                        <div class="p-4 bg-blue-50 rounded-lg border border-blue-100">
                            <p class="text-xs text-blue-700 font-bold uppercase mb-1">RA</p>
                            <p class="font-semibold text-gray-800">769029</p>
                        </div>
                    </div>
                </div>

        <!-- Habilidades -->
    <div class="bg-white p-6 rounded-2xl shadow-sm">
    <h3 class="text-xl font-bold text-gray-900 mb-4">
        <i class="fas fa-star text-[#4A9FCA] mr-2"></i>Habilidades
    </h3>

    <?php if (!empty($habilidades)): ?>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($habilidades as $skill): ?>
                <span class="px-4 py-2 bg-[#4A9FCA] text-white rounded-full text-sm">
                    <?php echo htmlspecialchars(trim($skill)); ?>
                </span>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-gray-400 text-sm italic">Nenhuma habilidade cadastrada. Atualize seu currículo.</p>
    <?php endif; ?>

    <a href="curriculum.php" class="inline-flex items-center gap-2 mt-4 text-sm text-[#4A9FCA] hover:underline">
        <i class="fas fa-edit text-xs"></i> Editar no Currículo
    </a>
</div>


                <!-- Magazine FSA -->
                <div class="bg-gradient-to-br from-[#E74C3C] to-[#C0392B] text-white p-6 rounded-2xl">
                    <h3 class="text-2xl font-bold mb-3">Magazine FSA</h3>
                    <p class="text-white/90 mb-4 text-sm">
                        Fique por dentro das últimas notícias e oportunidades exclusivas para alunos.
                    </p>
                    <a href="https://www.fsa.br/noticias" target="_blank"
                        class="inline-block bg-white text-[#E74C3C] px-6 py-3 rounded-xl font-bold hover:bg-gray-100 transition-all">
                        Acessar Agora
                    </a>
                </div>

            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
