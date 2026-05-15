<?php
require_once 'includes/config.php';
checkAuth();

$pageTitle = 'Gerenciar Vagas';

// ================================================
// VAGAS INICIAIS (usadas apenas se não houver JSON)
// ================================================
if (!function_exists('getInitialJobs')) {
    function getInitialJobs(): array {
        return [
            [
                'id'              => 1,
                'title'           => 'Estagiário de Desenvolvimento Web',
                'company'         => 'Tech Solutions',
                'location'        => 'Santo André, SP',
                'type'            => 'Híbrido',
                'hours'           => '30h semanais',
                'salary'          => 'R$ 1.200,00',
                'description'     => 'Desenvolvimento de sistemas web com PHP e JavaScript.',
                'requirements'    => 'Conhecimentos em HTML, CSS, JavaScript e PHP.',
                'benefits'        => 'Vale transporte, Vale refeição',
                'courses'         => 'Ciência da Computação, Sistemas de Informação',
                'applicationLink' => '',
                'featured'        => true,
                'status'          => 'Ativa',
                'applicants'      => 12,
                'createdAt'       => '01/05/2026'
            ],
            [
                'id'              => 2,
                'title'           => 'Estagiário de Data Science',
                'company'         => 'DataCorp',
                'location'        => 'São Paulo, SP',
                'type'            => 'Remoto',
                'hours'           => '20h semanais',
                'salary'          => 'R$ 1.500,00',
                'description'     => 'Análise de dados e criação de modelos preditivos.',
                'requirements'    => 'Python, Pandas, Machine Learning básico.',
                'benefits'        => 'Vale transporte, Plano de saúde',
                'courses'         => 'Ciência de Dados, Engenharia de Computação',
                'applicationLink' => '',
                'featured'        => true,
                'status'          => 'Ativa',
                'applicants'      => 8,
                'createdAt'       => '03/05/2026'
            ],
            [
                'id'              => 3,
                'title'           => 'Estagiário de Marketing Digital',
                'company'         => 'AgênciaX',
                'location'        => 'ABC Paulista, SP',
                'type'            => 'Presencial',
                'hours'           => '30h semanais',
                'salary'          => 'R$ 900,00',
                'description'     => 'Criação de conteúdo e gestão de redes sociais.',
                'requirements'    => 'Canva, Noções de SEO, Boa comunicação.',
                'benefits'        => 'Vale transporte',
                'courses'         => 'Marketing, Publicidade e Propaganda',
                'applicationLink' => '',
                'featured'        => false,
                'status'          => 'Ativa',
                'applicants'      => 5,
                'createdAt'       => '05/05/2026'
            ],
        ];
    }
}

// ================================================
// CARREGAR OU INICIALIZAR OS DADOS
// Se o JSON não existir → salva os dados iniciais
// ================================================
$jobsFile = __DIR__ . '/data/platform_jobs.json';

if (!file_exists($jobsFile)) {
    $jobs = getInitialJobs();
    saveData('platform_jobs', $jobs); // ← Cria o arquivo na primeira execução
} else {
    $jobs = loadData('platform_jobs');
}

// ================================================
// RESET (via GET ?reset=1)
// ================================================
if (isset($_GET['reset'])) {
    $jobs = getInitialJobs();
    saveData('platform_jobs', $jobs);
    header('Location: admin-jobs.php?msg=reset');
    exit;
}

// ================================================
// PROCESSAR FORMULÁRIOS
// ================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // ── CRIAR NOVA VAGA ──────────────────────────
    if ($_POST['action'] === 'save') {
        $newJob = [
            'id'              => time(),
            'title'           => trim($_POST['title']           ?? ''),
            'company'         => trim($_POST['company']         ?? ''),
            'location'        => trim($_POST['location']        ?? ''),
            'type'            => trim($_POST['type']            ?? 'Presencial'),
            'hours'           => trim($_POST['hours']           ?? ''),
            'salary'          => trim($_POST['salary']          ?? ''),
            'description'     => trim($_POST['description']     ?? ''),
            'requirements'    => trim($_POST['requirements']    ?? ''),
            'benefits'        => trim($_POST['benefits']        ?? ''),
            'courses'         => trim($_POST['courses']         ?? ''),
            'applicationLink' => trim($_POST['applicationLink'] ?? ''),
            'featured'        => isset($_POST['featured']),
            'status'          => 'Ativa',
            'applicants'      => 0,
            'createdAt'       => date('d/m/Y')
        ];

        array_unshift($jobs, $newJob); // Adiciona no topo da lista
        saveData('platform_jobs', $jobs);
        header('Location: admin-jobs.php?msg=created');
        exit;
    }

    // ── EDITAR VAGA ──────────────────────────────
    if ($_POST['action'] === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        foreach ($jobs as &$job) {
            if ((int)$job['id'] === $id) {
                $job['title']           = trim($_POST['title']           ?? $job['title']);
                $job['company']         = trim($_POST['company']         ?? $job['company']);
                $job['location']        = trim($_POST['location']        ?? $job['location']);
                $job['type']            = trim($_POST['type']            ?? $job['type']);
                $job['hours']           = trim($_POST['hours']           ?? $job['hours']);
                $job['salary']          = trim($_POST['salary']          ?? $job['salary']);
                $job['description']     = trim($_POST['description']     ?? $job['description']);
                $job['requirements']    = trim($_POST['requirements']    ?? $job['requirements']);
                $job['benefits']        = trim($_POST['benefits']        ?? $job['benefits']);
                $job['courses']         = trim($_POST['courses']         ?? $job['courses']);
                $job['applicationLink'] = trim($_POST['applicationLink'] ?? $job['applicationLink']);
                $job['featured']        = isset($_POST['featured']);
                break;
            }
        }
        unset($job);
        saveData('platform_jobs', $jobs);
        header('Location: admin-jobs.php?msg=edited');
        exit;
    }

    // ── EXCLUIR VAGA ─────────────────────────────
    if ($_POST['action'] === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $jobs = array_values(array_filter($jobs, fn($j) => (int)$j['id'] !== $id));
        saveData('platform_jobs', $jobs);
        header('Location: admin-jobs.php?msg=deleted');
        exit;
    }
}

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-7xl mx-auto">

        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 mb-1">Gerenciamento de Vagas</h2>
                <p class="text-gray-500 text-sm">Crie e gerencie oportunidades de estágio</p>
            </div>
            <div class="flex gap-3">
                <a href="admin-jobs.php?reset=1"
                   onclick="return confirm('Resetar para vagas iniciais?')"
                   class="px-4 py-2 border border-gray-200 text-gray-500 rounded-lg text-sm hover:bg-gray-50">
                    <i class="fas fa-undo mr-1"></i> Reset
                </a>
                <button onclick="openCreateModal()"
                        class="px-4 py-2 bg-[#4A9FCA] text-white rounded-lg hover:bg-[#3A8FB0] flex items-center gap-2">
                    <i class="fas fa-plus"></i> Nova Vaga
                </button>
            </div>
        </div>

        <!-- Feedback de Ações -->
        <?php if (isset($_GET['msg'])): ?>
            <?php $msgs = [
                'created' => ['bg-green-50 border-green-200 text-green-700', 'fa-check-circle', 'Vaga criada com sucesso! Já está visível em Vagas.'],
                'edited'  => ['bg-blue-50 border-blue-200 text-blue-700',   'fa-check-circle', 'Vaga atualizada com sucesso!'],
                'deleted' => ['bg-red-50 border-red-200 text-red-700',       'fa-trash-alt',    'Vaga excluída com sucesso.'],
                'reset'   => ['bg-yellow-50 border-yellow-200 text-yellow-700', 'fa-undo',       'Vagas resetadas para o padrão inicial.'],
            ]; $m = $msgs[$_GET['msg']] ?? null; ?>
            <?php if ($m): ?>
                <div class="mb-6 p-4 border rounded-xl flex items-center gap-3 <?php echo $m[0]; ?>">
                    <i class="fas <?php echo $m[1]; ?>"></i>
                    <span class="text-sm font-medium"><?php echo $m[2]; ?></span>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Contador -->
        <div class="mb-6 text-sm text-gray-500">
            <span class="font-bold text-gray-800"><?php echo count($jobs); ?></span> vaga(s) cadastrada(s) •
            <span class="font-bold text-green-600"><?php echo count(array_filter($jobs, fn($j) => $j['status'] === 'Ativa')); ?></span> ativa(s) •
            <span class="font-bold text-yellow-500"><?php echo count(array_filter($jobs, fn($j) => $j['featured'] ?? false)); ?></span> em destaque
        </div>

        <!-- ============================================ -->
        <!-- MODAL: CRIAR VAGA                          -->
        <!-- ============================================ -->
        <div id="jobModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl p-8 max-w-3xl w-full max-h-[90vh] overflow-y-auto shadow-2xl">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-2xl font-bold text-gray-900">Nova Vaga</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <form method="POST" action="admin-jobs.php" class="space-y-4">
                    <input type="hidden" name="action" value="save">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Título da Vaga *</label>
                            <input type="text" name="title" class="w-full h-10 px-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#4A9FCA] focus:outline-none" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Empresa *</label>
                            <input type="text" name="company" class="w-full h-10 px-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#4A9FCA] focus:outline-none" required>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Localização</label>
                            <input type="text" name="location" placeholder="Ex: Santo André, SP" class="w-full h-10 px-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#4A9FCA] focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Modalidade</label>
                            <select name="type" class="w-full h-10 px-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#4A9FCA] focus:outline-none">
                                <option value="Presencial">Presencial</option>
                                <option value="Remoto">Remoto</option>
                                <option value="Híbrido">Híbrido</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Carga Horária</label>
                            <input type="text" name="hours" placeholder="Ex: 30h semanais" class="w-full h-10 px-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#4A9FCA] focus:outline-none">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Bolsa Auxílio</label>
                            <input type="text" name="salary" placeholder="Ex: R$ 1.200,00" class="w-full h-10 px-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#4A9FCA] focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Link para Candidatura</label>
                            <input type="url" name="applicationLink" placeholder="https://..." class="w-full h-10 px-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#4A9FCA] focus:outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Descrição da Vaga *</label>
                        <textarea name="description" rows="3" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#4A9FCA] focus:outline-none" required></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Requisitos</label>
                        <textarea name="requirements" rows="3" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#4A9FCA] focus:outline-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Benefícios</label>
                        <textarea name="benefits" rows="2" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#4A9FCA] focus:outline-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Cursos Relacionados</label>
                        <input type="text" name="courses" placeholder="Ex: Ciência da Computação, Sistemas de Informação" class="w-full h-10 px-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#4A9FCA] focus:outline-none">
                    </div>
                    <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="featured" class="w-4 h-4 rounded accent-yellow-500">
                            <span class="text-sm font-medium text-yellow-800">⭐ Marcar como Vaga em Destaque</span>
                        </label>
                    </div>
                    <div class="flex gap-4 pt-4">
                        <button type="button" onclick="closeModal()" class="flex-1 py-3 border border-gray-200 rounded-lg text-gray-600 font-medium hover:bg-gray-50">Cancelar</button>
                        <button type="submit" class="flex-1 py-3 bg-[#4A9FCA] text-white rounded-lg font-semibold hover:bg-[#3A8FB0]">
                            <i class="fas fa-plus mr-1"></i> Publicar Vaga
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- MODAL: EDITAR VAGA                         -->
        <!-- ============================================ -->
        <div id="editModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl p-8 max-w-3xl w-full max-h-[90vh] overflow-y-auto shadow-2xl">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-2xl font-bold text-gray-900">Editar Vaga</h3>
                    <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <form method="POST" action="admin-jobs.php" class="space-y-4">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="editId">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Título da Vaga *</label>
                            <input type="text" name="title" id="editTitle" class="w-full h-10 px-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#4A9FCA] focus:outline-none" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Empresa *</label>
                            <input type="text" name="company" id="editCompany" class="w-full h-10 px-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#4A9FCA] focus:outline-none" required>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Localização</label>
                            <input type="text" name="location" id="editLocation" class="w-full h-10 px-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#4A9FCA] focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Modalidade</label>
                            <select name="type" id="editType" class="w-full h-10 px-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#4A9FCA] focus:outline-none">
                                <option value="Presencial">Presencial</option>
                                <option value="Remoto">Remoto</option>
                                <option value="Híbrido">Híbrido</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Carga Horária</label>
                            <input type="text" name="hours" id="editHours" class="w-full h-10 px-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#4A9FCA] focus:outline-none">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Bolsa Auxílio</label>
                            <input type="text" name="salary" id="editSalary" class="w-full h-10 px-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#4A9FCA] focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Link para Candidatura</label>
                            <input type="url" name="applicationLink" id="editApplicationLink" class="w-full h-10 px-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#4A9FCA] focus:outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Descrição da Vaga</label>
                        <textarea name="description" id="editDescription" rows="3" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#4A9FCA] focus:outline-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Requisitos</label>
                        <textarea name="requirements" id="editRequirements" rows="3" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#4A9FCA] focus:outline-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Benefícios</label>
                        <textarea name="benefits" id="editBenefits" rows="2" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#4A9FCA] focus:outline-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Cursos Relacionados</label>
                        <input type="text" name="courses" id="editCourses" class="w-full h-10 px-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#4A9FCA] focus:outline-none">
                    </div>
                    <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="featured" id="editFeatured" class="w-4 h-4 rounded accent-yellow-500">
                            <span class="text-sm font-medium text-yellow-800">⭐ Marcar como Vaga em Destaque</span>
                        </label>
                    </div>
                    <div class="flex gap-4 pt-4">
                        <button type="button" onclick="closeEditModal()" class="flex-1 py-3 border border-gray-200 rounded-lg text-gray-600 font-medium hover:bg-gray-50">Cancelar</button>
                        <button type="submit" class="flex-1 py-3 bg-[#4A9FCA] text-white rounded-lg font-semibold hover:bg-[#3A8FB0]">
                            <i class="fas fa-save mr-1"></i> Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- LISTA DE VAGAS                             -->
        <!-- ============================================ -->
        <div class="space-y-4">
            <?php foreach ($jobs as $job): ?>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 border-l-4 <?php echo ($job['featured'] ?? false) ? 'border-l-yellow-400' : 'border-l-transparent'; ?> hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <h3 class="text-lg font-bold text-gray-900">
                                    <?php echo htmlspecialchars($job['title']); ?>
                                </h3>
                                <?php if ($job['featured'] ?? false): ?>
                                    <span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 text-xs rounded-full font-bold">⭐ Destaque</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-gray-700 font-medium"><?php echo htmlspecialchars($job['company']); ?></p>
                            <p class="text-sm text-gray-400 mt-0.5">
                                <i class="fas fa-map-marker-alt mr-1"></i><?php echo htmlspecialchars($job['location']); ?>
                                &nbsp;•&nbsp;
                                <i class="fas fa-clock mr-1"></i><?php echo htmlspecialchars($job['type']); ?>
                            </p>
                        </div>
                        <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold flex-shrink-0">
                            <i class="fas fa-circle text-[8px] mr-1"></i><?php echo htmlspecialchars($job['status']); ?>
                        </span>
                    </div>

                    <div class="grid grid-cols-3 gap-4 mb-4 text-sm py-3 border-t border-b border-gray-50">
                        <div>
                            <p class="text-xs text-gray-400 mb-0.5">Bolsa Auxílio</p>
                            <p class="font-bold text-green-600"><?php echo htmlspecialchars($job['salary']); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 mb-0.5">Candidatos</p>
                            <p class="font-bold text-gray-700"><?php echo (int)($job['applicants'] ?? 0); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 mb-0.5">Publicada em</p>
                            <p class="font-bold text-gray-700"><?php echo htmlspecialchars($job['createdAt'] ?? '-'); ?></p>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <!-- Botão Editar -->
                        <button
                            onclick="editJob(
                                <?php echo (int)$job['id']; ?>,
                                '<?php echo addslashes(htmlspecialchars($job['title'])); ?>',
                                '<?php echo addslashes(htmlspecialchars($job['company'])); ?>',
                                '<?php echo addslashes(htmlspecialchars($job['location'])); ?>',
                                '<?php echo addslashes(htmlspecialchars($job['type'])); ?>',
                                '<?php echo addslashes(htmlspecialchars($job['hours'] ?? '')); ?>',
                                '<?php echo addslashes(htmlspecialchars($job['salary'])); ?>',
                                '<?php echo addslashes(htmlspecialchars($job['description'])); ?>',
                                '<?php echo addslashes(htmlspecialchars($job['requirements'])); ?>',
                                '<?php echo addslashes(htmlspecialchars($job['benefits'])); ?>',
                                '<?php echo addslashes(htmlspecialchars($job['courses'] ?? '')); ?>',
                                '<?php echo addslashes(htmlspecialchars($job['applicationLink'] ?? '')); ?>',
                                <?php echo ($job['featured'] ?? false) ? 'true' : 'false'; ?>
                            )"
                            class="px-4 py-2 bg-[#4A9FCA] text-white rounded-lg text-sm hover:bg-[#3A8FB0] flex items-center gap-2 transition-colors">
                            <i class="fas fa-edit"></i> Editar
                        </button>

                        <!-- Botão Excluir -->
                        <button
                            onclick="abrirModalExcluir(<?php echo (int)$job['id']; ?>, '<?php echo htmlspecialchars($job['title'], ENT_QUOTES); ?>')"
                            class="px-4 py-2 border border-red-200 text-red-500 rounded-lg text-sm hover:bg-red-50 flex items-center gap-2 transition-colors">
                            <i class="fas fa-trash-alt"></i> Excluir
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Estado vazio -->
            <?php if (empty($jobs)): ?>
                <div class="text-center py-20 bg-white rounded-2xl border-2 border-dashed border-gray-200">
                    <i class="fas fa-briefcase text-6xl text-gray-200 mb-4"></i>
                    <p class="text-gray-500 font-medium">Nenhuma vaga cadastrada ainda.</p>
                    <button onclick="openCreateModal()" class="mt-4 px-6 py-2 bg-[#4A9FCA] text-white rounded-lg text-sm">
                        Criar primeira vaga
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Form oculto de exclusão -->
        <form id="formExcluir" method="POST" action="admin-jobs.php">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="excluirId" value="">
        </form>

        <!-- ============================================ -->
        <!-- MODAL: CONFIRMAR EXCLUSÃO                  -->
        <!-- ============================================ -->
        <div id="modalExcluir" class="fixed inset-0 z-[999] hidden items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="fecharModalExcluir()"></div>
            <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-sm p-8 z-10">
                <div class="w-16 h-16 bg-red-100 rounded-2xl flex items-center justify-center mx-auto mb-5">
                    <i class="fas fa-trash-alt text-red-500 text-3xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 text-center mb-2">Excluir Vaga?</h3>
                <p class="text-gray-500 text-sm text-center mb-1">Você está prestes a excluir:</p>
                <p id="modalNomeVaga" class="text-[#4A9FCA] font-semibold text-sm text-center mb-3"></p>
                <p class="text-gray-400 text-xs text-center mb-8">Esta ação não pode ser desfeita e a vaga sumirá para todos os alunos imediatamente.</p>
                <div class="flex gap-3">
                    <button onclick="fecharModalExcluir()"
                            class="flex-1 py-3 rounded-2xl border-2 border-gray-200 text-gray-600 font-semibold hover:bg-gray-50 transition-all text-sm">
                        <i class="fas fa-times mr-1"></i> Cancelar
                    </button>
                    <button onclick="confirmarExcluir()"
                            class="flex-1 py-3 rounded-2xl bg-red-500 hover:bg-red-600 text-white font-semibold transition-all text-sm shadow-lg shadow-red-100">
                        <i class="fas fa-trash-alt mr-1"></i> Excluir
                    </button>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
    // =============================
    // Modal Nova Vaga
    // =============================
    function openCreateModal() {
        document.getElementById('jobModal').classList.remove('hidden');
    }
    function closeModal() {
        document.getElementById('jobModal').classList.add('hidden');
    }

    // =============================
    // Modal Editar Vaga
    // =============================
    function editJob(id, title, company, location, type, hours, salary, description, requirements, benefits, courses, applicationLink, featured) {
        document.getElementById('editId').value                = id;
        document.getElementById('editTitle').value             = title;
        document.getElementById('editCompany').value           = company;
        document.getElementById('editLocation').value          = location;
        document.getElementById('editHours').value             = hours;
        document.getElementById('editSalary').value            = salary;
        document.getElementById('editDescription').value       = description;
        document.getElementById('editRequirements').value      = requirements;
        document.getElementById('editBenefits').value          = benefits;
        document.getElementById('editCourses').value           = courses;
        document.getElementById('editApplicationLink').value   = applicationLink;
        document.getElementById('editFeatured').checked        = featured;

        // Seleciona o tipo correto
        const selectType = document.getElementById('editType');
        Array.from(selectType.options).forEach((opt, i) => {
            if (opt.value === type) selectType.selectedIndex = i;
        });

        document.getElementById('editModal').classList.remove('hidden');
    }
    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }

    // =============================
    // Modal Excluir
    // =============================
    function abrirModalExcluir(id, nome) {
        document.getElementById('excluirId').value            = id;
        document.getElementById('modalNomeVaga').textContent  = nome;
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
    // Fechar clicando fora / ESC
    // =============================
    ['jobModal', 'editModal'].forEach(id => {
        document.getElementById(id).addEventListener('click', function(e) {
            if (e.target === this) this.classList.add('hidden');
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeModal();
            closeEditModal();
            fecharModalExcluir();
        }
    });

</script>  <!-- ← ESTAVA FALTANDO ISSO -->

</main>    <!-- ← E ISSO TAMBÉM -->

<?php include 'includes/footer.php'; ?>
