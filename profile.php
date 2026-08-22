<?php
require_once 'includes/config.php';
require_once 'includes/perfil_api.php'; //NOVO: ponte com o app de Perfil por Competencias (Flask)
checkAuth();

$pageTitle = 'Meu Perfil';
$userName = $_SESSION['user_name'] ?? 'Usuário';

function getInitials($name) {
    $parts = explode(' ', trim($name));
    $first = strtoupper(substr($parts[0], 0, 1));
    $last  = count($parts) > 1 ? strtoupper(substr(end($parts), 0, 1)) : '';
    return $first . $last;
}

//Dados cadastrais (fonte da verdade: usuarios.json)
$usuarios = loadData('usuarios');
$usuarioAtual = null;
foreach ($usuarios as $u) {
    if ($u['id'] == $_SESSION['user_id']) {
        $usuarioAtual = $u;
        break;
    }
}

$email      = $usuarioAtual['email']    ?? '';
$cursoAtual = $usuarioAtual['curso']    ?? '';
$ra         = $usuarioAtual['ra']       ?? '';
$periodo    = $usuarioAtual['periodo']  ?? '';
$turno      = $usuarioAtual['turno']    ?? '';
$situacao   = $usuarioAtual['situacao'] ?? '';

//Foto de perfil isolada por usuário, criada automaticamente ao salvar
$userProfile = loadData('userProfile', true);

//Currículo isolado por usuário, mesmo arquivo lido/escrito pelo curriculum.php
$curriculumData = loadData('userCurriculum', true);
$habilidades = $curriculumData['habilidades'] ?? [];
$telefone    = $curriculumData['telefone']    ?? '';
$cidade      = $curriculumData['cidade']      ?? '';

//NOVO: resultado do Perfil por Competencias (busca no servidor Flask via cURL)
$resultadoPerfil = buscarResultadoPerfil($userName);
$urlFormPerfil   = urlFormularioPerfil($userName);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto_perfil'])) {
    $uploadDir = "uploads/profile/{$_SESSION['user_id']}/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $file = $_FILES['foto_perfil'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $maxSize = 5 * 1024 * 1024; // limite de 5MB

    $extValida = in_array($ext, $allowedExts);
    $tamanhoValido = $file['size'] <= $maxSize;
    $isImagemReal = $extValida && @getimagesize($file['tmp_name']) !== false; //valida conteúdo real

    if ($extValida && $tamanhoValido && $isImagemReal && $file['error'] === 0) {
        if (!empty($userProfile['foto']) && file_exists($userProfile['foto'])) {
            unlink($userProfile['foto']);
        }

        $fileName = 'avatar_' . time() . '.' . $ext;
        $uploadPath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $userProfile['foto'] = $uploadPath;
            saveData('userProfile', $userProfile, true);
            header('Location: profile.php?foto=ok');
            exit;
        }
    }

    //Se chegou aqui, algo falhou na validação
    header('Location: profile.php?foto=erro');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remover_foto'])) {
    if (!empty($userProfile['foto']) && file_exists($userProfile['foto'])) {
        unlink($userProfile['foto']);
    }
    $userProfile['foto'] = null;
    saveData('userProfile', $userProfile, true);
    header('Location: profile.php?removida=1');
    exit;
}

$fotoSalva = $userProfile['foto'] ?? null;
$temFoto = $fotoSalva && file_exists($fotoSalva);

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-7xl mx-auto">
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Meu Perfil</h2>
            <p class="text-gray-600">Mantenha suas informações atualizadas</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <div class="space-y-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm">
                    <div class="flex flex-col items-center text-center mb-6">

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

                            <label for="uploadFoto"
                                class="absolute bottom-1 right-1 w-8 h-8 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-full flex items-center justify-center cursor-pointer shadow-lg transition-all hover:scale-110"
                                title="Alterar foto de perfil">
                                <i class="fas fa-pencil-alt text-xs"></i>
                            </label>

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
                        <span class="px-3 py-1 bg-[#4A9FCA] text-white rounded-full text-sm"><?php echo htmlspecialchars($cursoAtual); ?></span>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center gap-3 p-2">
                            <i class="fas fa-envelope text-[#4A9FCA]"></i>
                            <div>
                                <p class="text-xs text-gray-500">E-mail Institucional</p>
                                <p class="text-sm text-gray-700"><?php echo htmlspecialchars($email); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 p-2">
                            <i class="fas fa-phone text-[#4A9FCA]"></i>
                            <div>
                                <p class="text-xs text-gray-500">Telefone</p>
                                <p class="text-sm text-gray-700"><?php echo htmlspecialchars($telefone); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 p-2">
                            <i class="fas fa-map-marker-alt text-[#4A9FCA]"></i>
                            <div>
                                <p class="text-xs text-gray-500">Localização</p>
                                <p class="text-sm text-gray-700"><?php echo htmlspecialchars($cidade); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php if ($temFoto): ?>
                        <button type="button" onclick="abrirModalRemoverFoto()"
                            class="w-full py-2 text-sm text-red-500 border border-red-200 rounded-xl hover:bg-red-50 transition-colors mt-4">
                            <i class="fas fa-trash-alt mr-1"></i> Remover foto
                        </button>

                        <form id="formRemoverFotoPerfil" method="POST" action="profile.php" class="hidden">
                            <input type="hidden" name="remover_foto" value="1">
                        </form>
                    <?php endif; ?>

                </div>
            </div>

            <div class="lg:col-span-2 space-y-6">

                <div class="bg-white p-6 rounded-2xl shadow-sm">
                    <h3 class="text-xl font-bold text-gray-900 mb-6">
                        <i class="fas fa-graduation-cap text-[#4A9FCA] mr-2"></i>Informações Acadêmicas
                    </h3>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="p-4 bg-purple-50 rounded-lg border border-purple-100">
                            <p class="text-xs text-purple-700 font-bold uppercase mb-1">Período</p>
                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($periodo); ?></p>
                        </div>
                        <div class="p-4 bg-green-50 rounded-lg border border-green-100">
                            <p class="text-xs text-green-700 font-bold uppercase mb-1">Turno</p>
                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($turno); ?></p>
                        </div>
                        <div class="p-4 bg-orange-50 rounded-lg border border-orange-100">
                            <p class="text-xs text-orange-700 font-bold uppercase mb-1">Situação</p>
                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($situacao); ?></p>
                        </div>
                        <div class="p-4 bg-blue-50 rounded-lg border border-blue-100">
                            <p class="text-xs text-blue-700 font-bold uppercase mb-1">RA</p>
                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($ra); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-3 h-3 bg-[#4A9FCA] rotate-45 flex-shrink-0"></div>
                        <h3 class="text-sm font-bold text-gray-800 uppercase tracking-widest">Habilidades e Competências</h3>
                    </div>

                    <?php if (!empty($habilidades)): ?>
                        <div class="flex flex-wrap gap-2.5">
                            <?php foreach ($habilidades as $skill): ?>
                            <span class="inline-flex items-center gap-2 px-4 py-2 bg-[#eaf4fa] text-[#2B7FA6] rounded-full text-sm font-semibold">
                                <span class="w-1.5 h-1.5 rounded-full bg-[#4A9FCA]"></span>
                                <?php echo htmlspecialchars(trim($skill)); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-400 text-sm italic">Nenhuma habilidade cadastrada. Atualize seu currículo.</p>
                    <?php endif; ?>

                    <a href="curriculum.php" class="inline-flex items-center gap-2 mt-5 text-sm text-[#4A9FCA] hover:underline">
                        <i class="fas fa-edit text-xs"></i> Editar no Currículo
                    </a>
                </div>

                <!-- ============================================================ -->
                <!-- NOVO: Card "Perfil por Competências" (integração com Flask) -->
                <!-- ============================================================ -->
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-12 h-12 bg-gradient-to-br from-[#4A9FCA] to-[#3A8FB0] rounded-xl flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-brain text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-900">Perfil por Competências</h3>
                            <p class="text-sm text-gray-500">Teste comportamental com parecer gerado por IA</p>
                        </div>
                    </div>

                    <?php if (!$resultadoPerfil): ?>
                        <!-- Aluno ainda nÃ£o respondeu o formulário -->
                        <div class="p-6 bg-blue-50 border border-blue-100 rounded-2xl text-center">
                            <i class="fas fa-clipboard-list text-3xl text-[#4A9FCA] mb-3"></i>
                            <p class="text-gray-700 font-semibold mb-1">Você ainda não respondeu o teste de perfil.</p>
                            <p class="text-sm text-gray-500 mb-5">Responda 100 perguntas rápidas e receba um parecer completo gerado por IA, além de um relatório em PDF.</p>
                            <a href="<?php echo htmlspecialchars($urlFormPerfil); ?>" target="_blank"
                               class="inline-flex items-center gap-2 px-6 py-3 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl font-bold transition-all">
                                <i class="fas fa-play"></i> Responder formulário
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Aluno já respondeu: mostra data, parecer e ações -->
                        <p class="text-xs text-gray-400 mb-4">
                            <i class="fas fa-clock mr-1"></i>
                            Última resposta em <?php echo htmlspecialchars($resultadoPerfil['data_formatada'] ?? '—'); ?>
                        </p>

                        <div class="p-6 bg-gray-50 rounded-2xl mb-5">
                            <h4 class="font-bold text-gray-900 mb-3 flex items-center gap-2">
                                <i class="fas fa-robot text-[#4A9FCA]"></i> Parecer da IA
                            </h4>
                            <div class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">
                    <?php echo htmlspecialchars($resultadoPerfil['parecer'] ?? 'Parecer indisponível.'); ?>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <a href="<?php echo htmlspecialchars(urlPdfPerfil($resultadoPerfil)); ?>" target="_blank"
                               class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl text-sm font-semibold transition-all">
                                <i class="fas fa-file-pdf"></i> Baixar relatório em PDF
                            </a>
                            <a href="<?php echo htmlspecialchars($urlFormPerfil); ?>" target="_blank"
                               class="inline-flex items-center gap-2 px-5 py-2.5 border border-gray-200 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-50 transition-all">
                                <i class="fas fa-redo"></i> Refazer avaliação
                            </a>
                            <a href="career-tips.php"
                               class="inline-flex items-center gap-2 px-5 py-2.5 border border-gray-200 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-50 transition-all">
                                <i class="fas fa-chart-pie"></i> Ver gráfico em Dicas de Carreira
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

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

<!-- Modal: Remover Foto de Perfil -->
    <div id="modalRemoverFoto" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" style="animation:overlayFade .2s ease;" onclick="fecharModalRemoverFoto()"></div>

        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6 text-center" style="animation:modalPop .2s ease;">
            <div class="w-14 h-14 bg-red-100 rounded-2xl flex items-center justify-center mb-4 mx-auto">
                <i class="fas fa-trash-alt text-red-500 text-2xl"></i>
            </div>

            <h3 class="text-xl font-bold text-gray-900 mb-2">Remover foto de perfil?</h3>

            <p class="text-gray-500 text-sm mb-1">Você está prestes a remover sua foto de perfil atual.</p>
            <p class="text-gray-400 text-sm mb-6">
                Suas iniciais voltarão a ser exibidas no lugar da foto. Você pode enviar uma nova a qualquer momento.
            </p>

            <div class="flex gap-3">
                <button type="button" onclick="fecharModalRemoverFoto()"
                        class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 border border-gray-300 rounded-xl text-gray-700 font-medium hover:bg-gray-50 transition-all">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="button" onclick="document.getElementById('formRemoverFotoPerfil').submit()"
                        class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-red-500 rounded-xl text-white font-medium hover:bg-red-600 transition-all">
                    <i class="fas fa-trash-alt"></i> Remover
                </button>
            </div>
        </div>
    </div>

<script>
function abrirModalRemoverFoto() {
    document.getElementById('modalRemoverFoto').classList.remove('hidden');
    document.getElementById('modalRemoverFoto').classList.add('flex');
}
function fecharModalRemoverFoto() {
    document.getElementById('modalRemoverFoto').classList.add('hidden');
    document.getElementById('modalRemoverFoto').classList.remove('flex');
}
</script>

<?php include 'includes/footer.php'; ?>