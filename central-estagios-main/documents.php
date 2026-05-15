<?php
require_once 'includes/config.php';
checkAuth();

$pageTitle = 'Documentos';

$initialDocuments = [
    ['id' => 1, 'title' => 'Python para Data Science', 'category' => 'Curso Complementar', 'institution' => 'Coursera', 'hours' => '40h', 'date' => '15/01/2026', 'status' => 'approved', 'filePath' => ''],
    ['id' => 2, 'title' => 'Machine Learning Básico', 'category' => 'Curso Complementar', 'institution' => 'Udemy', 'hours' => '30h', 'date' => '10/02/2026', 'status' => 'approved', 'filePath' => ''],
    ['id' => 3, 'title' => 'Desenvolvimento Web com React', 'category' => 'Curso Complementar', 'institution' => 'Rocketseat', 'hours' => '60h', 'date' => '05/03/2026', 'status' => 'approved', 'filePath' => ''],
];

$documentsFile = 'data/userCertificates.json';
$documents = file_exists($documentsFile) ? json_decode(file_get_contents($documentsFile), true) : $initialDocuments;

// ✅ Download do certificado
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['download'])) {
    $id       = $_GET['download'];
    $docFound = null;
    foreach ($documents as $doc) {
        if ($doc['id'] == $id) {
            $docFound = $doc;
            break;
        }
    }
    if ($docFound && !empty($docFound['filePath']) && file_exists($docFound['filePath'])) {
        $filePath = $docFound['filePath'];
        $fileName = basename($filePath);
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    } else {
        header('Location: documents.php?sem_arquivo=1');
        exit;
    }
}

// ✅ Excluir certificado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = $_POST['id'] ?? 0;
    $documents = array_filter($documents, function($doc) use ($id) {
        return $doc['id'] != $id;
    });
    saveData('userCertificates', array_values($documents));
    header('Location: documents.php');
    exit;
}

$totalHours = array_reduce($documents, function($sum, $doc) {
    return $sum + intval($doc['hours']);
}, 0);

$remainingHours      = max(0, 200 - $totalHours);
$progressPercentage  = min(100, ($totalHours / 200) * 100);

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-7xl mx-auto">

        <!-- Header -->
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Atividades Complementares</h2>
            <p class="text-gray-600">Visualize e gerencie seus certificados aprovados</p>
        </div>

        <!-- Aviso sem arquivo -->
        <?php if (isset($_GET['sem_arquivo'])): ?>
            <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-xl flex items-center gap-3">
                <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                <p class="text-yellow-800 font-medium">Este certificado não possui arquivo disponível para download.</p>
            </div>
        <?php endif; ?>

        <!-- Info Card -->
        <div class="p-6 mb-8 bg-gradient-to-r from-blue-50 to-purple-50 border border-blue-200 rounded-2xl">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 bg-[#4A9FCA] rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-info-circle text-white text-xl"></i>
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-900 mb-2">Importante</h3>
                    <p class="text-sm text-gray-700">
                        Todos os certificados aqui foram <strong>aprovados pela coordenação</strong> e aparecem automaticamente no seu currículo.
                    </p>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="p-6 bg-gradient-to-br from-[#27AE60] to-[#229954] text-white rounded-2xl">
                <p class="text-white/80 text-sm mb-1">Horas Aprovadas</p>
                <p class="text-4xl font-bold"><?php echo $totalHours; ?>h</p>
                <div class="mt-4 pt-4 border-t border-white/20">
                    <div class="w-full h-2 bg-white/20 rounded-full overflow-hidden mt-2">
                        <div class="h-full bg-white rounded-full transition-all duration-500" style="width: <?php echo $progressPercentage; ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="p-6 bg-gradient-to-br from-[#4A9FCA] to-[#3A8FB0] text-white rounded-2xl">
                <p class="text-white/80 text-sm mb-1">Certificados</p>
                <p class="text-4xl font-bold"><?php echo count($documents); ?></p>
                <p class="text-xs text-white/80 mt-4 pt-4 border-t border-white/20">Aprovados pela coordenação</p>
            </div>

            <div class="p-6 bg-gradient-to-br from-[#E74C3C] to-[#C0392B] text-white rounded-2xl">
                <p class="text-white/80 text-sm mb-1">Faltam</p>
                <p class="text-4xl font-bold"><?php echo $remainingHours; ?>h</p>
                <p class="text-xs text-white/80 mt-4 pt-4 border-t border-white/20">Para completar 200h</p>
            </div>
        </div>

        <!-- Documents List -->
        <div class="space-y-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-semibold text-gray-900">Certificados Aprovados</h3>
                <span class="px-3 py-1 bg-[#27AE60] text-white rounded-full text-sm">
                    <?php echo count($documents); ?> Certificados
                </span>
            </div>

            <?php foreach ($documents as $index => $doc): ?>
                <div class="bg-white p-6 rounded-2xl shadow-sm hover:shadow-lg transition-all">
                    <div class="flex items-start gap-4">

                        <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-[#27AE60] to-[#229954] flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-certificate text-white text-xl"></i>
                        </div>

                        <div class="flex-1">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <div class="flex items-center gap-2 mb-2">
                                        <h4 class="font-semibold text-gray-900 text-lg"><?php echo htmlspecialchars($doc['title']); ?></h4>
                                        <span class="px-2 py-1 bg-[#27AE60] text-white text-xs rounded-full">Aprovado</span>
                                    </div>
                                    <p class="text-sm text-gray-600 font-medium"><?php echo htmlspecialchars($doc['institution']); ?></p>
                                </div>
                            </div>

                            <div class="flex items-center gap-4 text-sm text-gray-600 mb-4">
                                <span class="flex items-center gap-1 font-semibold text-[#4A9FCA]">
                                    <i class="fas fa-clock"></i> <?php echo $doc['hours']; ?>
                                </span>
                                <span>•</span>
                                <span>Concluído em <?php echo $doc['date']; ?></span>
                                <span>•</span>
                                <span class="px-2 py-1 border rounded-full text-xs"><?php echo $doc['category']; ?></span>
                            </div>

                            <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4 flex items-center gap-2">
                                <i class="fas fa-check-circle text-green-600"></i>
                                <p class="text-sm text-green-800">Certificado aprovado e computado nas suas horas complementares.</p>
                            </div>

                            <div class="flex gap-3">
                                <!-- ✅ Baixar Certificado -->
                                <?php if (!empty($doc['filePath'])): ?>
                                    <a href="documents.php?download=<?php echo $doc['id']; ?>"
                                       class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50 transition-all flex items-center gap-2">
                                        <i class="fas fa-download"></i> Baixar Certificado
                                    </a>
                                <?php else: ?>
                                    <button
                                        onclick="abrirModalSemArquivo('<?php echo htmlspecialchars($doc['title'], ENT_QUOTES); ?>')"
                                        class="px-4 py-2 border border-gray-200 text-gray-400 rounded-lg text-sm hover:bg-gray-50 transition-all flex items-center gap-2 cursor-pointer">
                                        <i class="fas fa-download"></i> Baixar Certificado
                                    </button>
                                <?php endif; ?>

                                <!-- Excluir -->
                                <button type="button"
                                    onclick="abrirModalExcluir(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['title'], ENT_QUOTES); ?>')"
                                    class="px-4 py-2 border border-red-200 text-red-600 rounded-lg text-sm hover:bg-red-50 transition-all flex items-center gap-2">
                                    <i class="fas fa-trash-alt"></i> Excluir
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($documents)): ?>
                <div class="text-center py-20 bg-white rounded-xl border-2 border-dashed border-gray-200">
                    <i class="fas fa-file-alt text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 font-medium">Nenhum certificado aprovado encontrado.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Progress Card -->
        <div class="bg-white p-6 rounded-2xl shadow-sm mt-8">
            <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <i class="fas fa-trophy text-yellow-400"></i> Progresso das Atividades Complementares
            </h3>
            <div class="mb-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700"><?php echo $totalHours; ?>h de 200h necessárias</span>
                    <span class="text-sm font-bold text-[#27AE60]"><?php echo round($progressPercentage); ?>%</span>
                </div>
                <div class="w-full h-4 bg-gray-200 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-[#27AE60] to-[#229954] rounded-full transition-all duration-1000"
                         style="width: <?php echo $progressPercentage; ?>%"></div>
                </div>
            </div>
            <p class="text-sm text-gray-600">
                <?php if ($remainingHours > 0): ?>
                    Continue participando de cursos e eventos para completar as <?php echo $remainingHours; ?>h restantes!
                <?php else: ?>
                    Parabéns! Você completou todas as horas complementares necessárias.
                <?php endif; ?>
            </p>
        </div>

        <!-- Form oculto de exclusão -->
        <form id="formExcluir" method="POST" action="documents.php">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="excluirId" value="">
        </form>

        <!-- Modal Excluir -->
        <div id="modalExcluir" class="fixed inset-0 z-[999] hidden items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="fecharModalExcluir()"></div>
            <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-sm p-8 z-10">

                <div class="w-16 h-16 bg-red-100 rounded-2xl flex items-center justify-center mx-auto mb-5">
                    <i class="fas fa-trash-alt text-red-500 text-3xl"></i>
                </div>

                <h3 class="text-xl font-bold text-gray-900 text-center mb-2">Excluir Certificado?</h3>
                <p class="text-gray-500 text-sm text-center mb-1 leading-relaxed">Você está prestes a excluir:</p>
                <p id="modalNomeCertificado" class="text-[#4A9FCA] font-semibold text-sm text-center mb-3 leading-relaxed"></p>
                <p class="text-gray-400 text-xs text-center mb-8">Esta ação não pode ser desfeita e as horas serão removidas do seu total.</p>

                <div class="flex gap-3">
                    <button onclick="fecharModalExcluir()"
                            class="flex-1 py-3 px-4 rounded-2xl border-2 border-gray-200 text-gray-600 font-semibold hover:bg-gray-50 transition-all text-sm">
                        <i class="fas fa-times mr-1"></i> Cancelar
                    </button>
                    <button onclick="confirmarExcluir()"
                            class="flex-1 py-3 px-4 rounded-2xl bg-red-500 hover:bg-red-600 text-white font-semibold transition-all text-sm shadow-lg shadow-red-100">
                        <i class="fas fa-trash-alt mr-1"></i> Excluir
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Sem Arquivo -->
        <div id="modalSemArquivo" class="fixed inset-0 z-[999] hidden items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="fecharModalSemArquivo()"></div>
            <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-sm p-8 z-10">

                <div class="w-16 h-16 bg-yellow-100 rounded-2xl flex items-center justify-center mx-auto mb-5">
                    <i class="fas fa-exclamation-triangle text-yellow-500 text-3xl"></i>
                </div>

                <h3 class="text-xl font-bold text-gray-900 text-center mb-2">Arquivo Indisponível</h3>
                <p class="text-gray-500 text-sm text-center mb-1 leading-relaxed">O certificado</p>
                <p id="modalNomeSemArquivo" class="text-[#4A9FCA] font-semibold text-sm text-center mb-3"></p>
                <p class="text-gray-400 text-xs text-center mb-8">
                    Não possui arquivo anexado. Entre em contato com a coordenação para solicitar o documento.
                </p>

                <button onclick="fecharModalSemArquivo()"
                        class="w-full py-3 px-4 rounded-2xl bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white font-semibold transition-all text-sm">
                    Entendido
                </button>
            </div>
        </div>

    </div>
</main>

<script>
    // =============================
    // Modal Excluir
    // =============================
    function abrirModalExcluir(id, nome) {
        document.getElementById('excluirId').value                  = id;
        document.getElementById('modalNomeCertificado').textContent = nome;
        const modal = document.getElementById('modalExcluir');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    function fecharModalExcluir() {
        const modal = document.getElementById('modalExcluir');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    function confirmarExcluir() {
        document.getElementById('formExcluir').submit();
    }

    // =============================
    // Modal Sem Arquivo
    // =============================
    function abrirModalSemArquivo(nome) {
        document.getElementById('modalNomeSemArquivo').textContent = nome;
        const modal = document.getElementById('modalSemArquivo');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    function fecharModalSemArquivo() {
        const modal = document.getElementById('modalSemArquivo');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    // Fecha com ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            fecharModalExcluir();
            fecharModalSemArquivo();
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
