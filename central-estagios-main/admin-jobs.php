<?php
require_once 'includes/config.php';
checkAuth();

$pageTitle = 'Gerenciar Vagas';

if (!function_exists('getInitialJobs')) {
    function getInitialJobs() {
        return [
            [
                'id' => 1,
                'title' => 'Estagiário de Desenvolvimento Web',
                'company' => 'Tech Solutions',
                'location' => 'Santo André, SP',
                'type' => 'Híbrido',
                'hours' => '30h semanais',
                'salary' => 'R$ 1.200,00',
                'description' => 'Desenvolvimento de sistemas web com PHP e JavaScript.',
                'requirements' => 'Conhecimentos em HTML, CSS, JavaScript e PHP.',
                'benefits' => 'Vale transporte, Vale refeição',
                'courses' => 'Ciência da Computação, Sistemas de Informação',
                'applicationLink' => '',
                'featured' => true,
                'status' => 'Ativa',
                'applicants' => 12,
                'createdAt' => '01/05/2026'
            ],
            [
                'id' => 2,
                'title' => 'Estagiário de Data Science',
                'company' => 'DataCorp',
                'location' => 'São Paulo, SP',
                'type' => 'Remoto',
                'hours' => '20h semanais',
                'salary' => 'R$ 1.500,00',
                'description' => 'Análise de dados e criação de modelos preditivos.',
                'requirements' => 'Python, Pandas, Machine Learning básico.',
                'benefits' => 'Vale transporte, Plano de saúde',
                'courses' => 'Ciência de Dados, Engenharia de Computação',
                'applicationLink' => '',
                'featured' => true,
                'status' => 'Ativa',
                'applicants' => 8,
                'createdAt' => '03/05/2026'
            ],
            [
                'id' => 3,
                'title' => 'Estagiário de Marketing Digital',
                'company' => 'AgênciaX',
                'location' => 'ABC Paulista, SP',
                'type' => 'Presencial',
                'hours' => '30h semanais',
                'salary' => 'R$ 900,00',
                'description' => 'Criação de conteúdo e gestão de redes sociais.',
                'requirements' => 'Canva, Noções de SEO, Boa comunicação.',
                'benefits' => 'Vale transporte',
                'courses' => 'Marketing, Publicidade e Propaganda',
                'applicationLink' => '',
                'featured' => false,
                'status' => 'Ativa',
                'applicants' => 5,
                'createdAt' => '05/05/2026'
            ],
        ];
    }
}

$jobsFile = 'data/platform_jobs.json';

if (isset($_GET['reset'])) {
    $jobs = getInitialJobs();
    saveData('platform_jobs', $jobs);
    header('Location: admin-jobs.php');
    exit;
}

$jobs = file_exists($jobsFile) ? json_decode(file_get_contents($jobsFile), true) : getInitialJobs();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'save') {
        $newJob = [
            'id' => time(),
            'title' => $_POST['title'] ?? '',
            'company' => $_POST['company'] ?? '',
            'location' => $_POST['location'] ?? '',
            'type' => $_POST['type'] ?? 'Presencial',
            'hours' => $_POST['hours'] ?? '',
            'salary' => $_POST['salary'] ?? '',
            'description' => $_POST['description'] ?? '',
            'requirements' => $_POST['requirements'] ?? '',
            'benefits' => $_POST['benefits'] ?? '',
            'courses' => $_POST['courses'] ?? '',
            'applicationLink' => $_POST['applicationLink'] ?? '',
            'featured' => isset($_POST['featured']),
            'status' => 'Ativa',
            'applicants' => 0,
            'createdAt' => date('d/m/Y')
        ];
        array_unshift($jobs, $newJob);
        saveData('platform_jobs', $jobs);
        header('Location: admin-jobs.php');
        exit;
    }

    if ($_POST['action'] === 'edit') {
        $id = $_POST['id'] ?? 0;
        foreach ($jobs as &$job) {
            if ($job['id'] == $id) {
                $job['title']           = $_POST['title'] ?? $job['title'];
                $job['company']         = $_POST['company'] ?? $job['company'];
                $job['location']        = $_POST['location'] ?? $job['location'];
                $job['type']            = $_POST['type'] ?? $job['type'];
                $job['hours']           = $_POST['hours'] ?? $job['hours'];
                $job['salary']          = $_POST['salary'] ?? $job['salary'];
                $job['description']     = $_POST['description'] ?? $job['description'];
                $job['requirements']    = $_POST['requirements'] ?? $job['requirements'];
                $job['benefits']        = $_POST['benefits'] ?? $job['benefits'];
                $job['courses']         = $_POST['courses'] ?? $job['courses'];
                $job['applicationLink'] = $_POST['applicationLink'] ?? $job['applicationLink'];
                $job['featured']        = isset($_POST['featured']);
                break;
            }
        }
        saveData('platform_jobs', $jobs);
        header('Location: admin-jobs.php');
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $id = $_POST['id'] ?? 0;
        $jobs = array_filter($jobs, function($job) use ($id) {
            return $job['id'] != $id;
        });
        saveData('platform_jobs', $jobs);
        header('Location: admin-jobs.php');
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
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Gerenciamento de Vagas</h2>
                <p class="text-gray-600">Crie e gerencie oportunidades de estágio</p>
            </div>
            <button onclick="openCreateModal()" class="px-4 py-2 bg-[#4A9FCA] text-white rounded-lg hover:bg-[#3A8FB0]">
                + Nova Vaga
            </button>
        </div>

        <!-- Modal Criar Vaga -->
        <div id="jobModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
            <div class="bg-white rounded-2xl p-8 max-w-3xl w-full max-h-[90vh] overflow-y-auto">
                <h3 class="text-2xl font-bold mb-6">Nova Vaga</h3>
                <form method="POST" action="admin-jobs.php" class="space-y-4">
                    <input type="hidden" name="action" value="save">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Título da Vaga *</label>
                            <input type="text" name="title" class="w-full h-10 px-3 border rounded-lg" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Empresa *</label>
                            <input type="text" name="company" class="w-full h-10 px-3 border rounded-lg" required>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Localização</label>
                            <input type="text" name="location" class="w-full h-10 px-3 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Tipo</label>
                            <select name="type" class="w-full h-10 px-3 border rounded-lg">
                                <option value="Presencial">Presencial</option>
                                <option value="Remoto">Remoto</option>
                                <option value="Híbrido">Híbrido</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Bolsa Auxílio</label>
                            <input type="text" name="salary" class="w-full h-10 px-3 border rounded-lg">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Descrição *</label>
                        <textarea name="description" rows="3" class="w-full px-3 py-2 border rounded-lg"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Requisitos</label>
                        <textarea name="requirements" rows="3" class="w-full px-3 py-2 border rounded-lg"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Benefícios</label>
                        <textarea name="benefits" rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea>
                    </div>
                    <div>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="featured" class="rounded">
                            <span class="text-yellow-800">⭐ Marcar como Vaga em Destaque</span>
                        </label>
                    </div>
                    <div class="flex gap-4 pt-4">
                        <button type="button" onclick="closeModal()" class="flex-1 py-3 border rounded-lg">Cancelar</button>
                        <button type="submit" class="flex-1 py-3 bg-[#4A9FCA] text-white rounded-lg">Salvar Vaga</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Editar Vaga -->
        <div id="editModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
            <div class="bg-white rounded-2xl p-8 max-w-3xl w-full max-h-[90vh] overflow-y-auto">
                <h3 class="text-2xl font-bold mb-6">Editar Vaga</h3>
                <form method="POST" action="admin-jobs.php" class="space-y-4">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="editId">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Título da Vaga *</label>
                            <input type="text" name="title" id="editTitle" class="w-full h-10 px-3 border rounded-lg" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Empresa *</label>
                            <input type="text" name="company" id="editCompany" class="w-full h-10 px-3 border rounded-lg" required>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Localização</label>
                            <input type="text" name="location" id="editLocation" class="w-full h-10 px-3 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Tipo</label>
                            <select name="type" id="editType" class="w-full h-10 px-3 border rounded-lg">
                                <option value="Presencial">Presencial</option>
                                <option value="Remoto">Remoto</option>
                                <option value="Híbrido">Híbrido</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Bolsa Auxílio</label>
                            <input type="text" name="salary" id="editSalary" class="w-full h-10 px-3 border rounded-lg">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Descrição</label>
                        <textarea name="description" id="editDescription" rows="3" class="w-full px-3 py-2 border rounded-lg"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Requisitos</label>
                        <textarea name="requirements" id="editRequirements" rows="3" class="w-full px-3 py-2 border rounded-lg"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Benefícios</label>
                        <textarea name="benefits" id="editBenefits" rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea>
                    </div>
                    <div>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="featured" id="editFeatured" class="rounded">
                            <span class="text-yellow-800">⭐ Marcar como Vaga em Destaque</span>
                        </label>
                    </div>
                    <div class="flex gap-4 pt-4">
                        <button type="button" onclick="closeEditModal()" class="flex-1 py-3 border rounded-lg">Cancelar</button>
                        <button type="submit" class="flex-1 py-3 bg-[#4A9FCA] text-white rounded-lg">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de Vagas -->
        <div class="space-y-4">
            <?php foreach ($jobs as $job): ?>
                <div class="bg-white p-6 rounded-2xl shadow-sm border-l-4 <?php echo $job['featured'] ? 'border-l-yellow-400' : 'border-l-transparent'; ?>">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">
                                <?php echo htmlspecialchars($job['title']); ?>
                            </h3>
                            <p class="text-gray-700 font-medium"><?php echo htmlspecialchars($job['company']); ?></p>
                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($job['location']); ?> • <?php echo htmlspecialchars($job['type']); ?></p>
                        </div>
                        <span class="px-3 py-1 bg-green-500 text-white rounded-full text-sm">
                            <?php echo $job['status']; ?>
                        </span>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4 text-sm">
                        <div>
                            <span class="text-gray-500">Bolsa:</span>
                            <p class="font-medium text-green-600"><?php echo htmlspecialchars($job['salary']); ?></p>
                        </div>
                        <div>
                            <span class="text-gray-500">Candidatos:</span>
                            <p class="font-medium"><?php echo $job['applicants']; ?></p>
                        </div>
                        <div>
                            <span class="text-gray-500">Criada em:</span>
                            <p class="font-medium"><?php echo $job['createdAt']; ?></p>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-4 border-t">

                        <!-- Editar -->
                        <button
                            onclick="editJob(
                                <?php echo $job['id']; ?>,
                                '<?php echo addslashes($job['title']); ?>',
                                '<?php echo addslashes($job['company']); ?>',
                                '<?php echo addslashes($job['location']); ?>',
                                '<?php echo addslashes($job['type']); ?>',
                                '<?php echo addslashes($job['salary']); ?>',
                                '<?php echo addslashes($job['description']); ?>',
                                '<?php echo addslashes($job['requirements']); ?>',
                                '<?php echo addslashes($job['benefits']); ?>',
                                <?php echo $job['featured'] ? 'true' : 'false'; ?>
                            )"
                            class="px-4 py-2 bg-[#4A9FCA] text-white rounded-lg text-sm hover:bg-[#3A8FB0] flex items-center gap-2">
                            <i class="fas fa-edit"></i> Editar
                        </button>

                        <!-- ✅ Excluir — mesmo estilo do documents.php -->
                        <button type="button"
                            onclick="abrirModalExcluir(<?php echo $job['id']; ?>, '<?php echo htmlspecialchars($job['title'], ENT_QUOTES); ?>')"
                            class="px-4 py-2 border border-red-200 text-red-600 rounded-lg text-sm hover:bg-red-50 transition-all flex items-center gap-2">
                            <i class="fas fa-trash-alt"></i> Excluir
                        </button>

                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($jobs)): ?>
                <div class="text-center py-20 bg-white rounded-xl border-2 border-dashed border-gray-200">
                    <p class="text-gray-500 font-medium">Nenhuma vaga cadastrada.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Form oculto de exclusão -->
        <form id="formExcluir" method="POST" action="admin-jobs.php">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="excluirId" value="">
        </form>

        <!-- Modal Excluir Vaga -->
        <div id="modalExcluir" class="fixed inset-0 z-[999] hidden items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="fecharModalExcluir()"></div>
            <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-sm p-8 z-10">

                <div class="w-16 h-16 bg-red-100 rounded-2xl flex items-center justify-center mx-auto mb-5">
                    <i class="fas fa-trash-alt text-red-500 text-3xl"></i>
                </div>

                <h3 class="text-xl font-bold text-gray-900 text-center mb-2">Excluir Vaga?</h3>
                <p class="text-gray-500 text-sm text-center mb-1 leading-relaxed">Você está prestes a excluir:</p>
                <p id="modalNomeVaga" class="text-[#4A9FCA] font-semibold text-sm text-center mb-3 leading-relaxed"></p>
                <p class="text-gray-400 text-xs text-center mb-8">Esta ação não pode ser desfeita e a vaga sumirá para todos os alunos.</p>

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
    function editJob(id, title, company, location, type, salary, description, requirements, benefits, featured) {
        document.getElementById('editId').value           = id;
        document.getElementById('editTitle').value        = title;
        document.getElementById('editCompany').value      = company;
        document.getElementById('editLocation').value     = location;
        document.getElementById('editSalary').value       = salary;
        document.getElementById('editDescription').value  = description;
        document.getElementById('editRequirements').value = requirements;
        document.getElementById('editBenefits').value     = benefits;
        document.getElementById('editFeatured').checked   = featured;

        const selectType = document.getElementById('editType');
        for (let i = 0; i < selectType.options.length; i++) {
            if (selectType.options[i].value === type) {
                selectType.selectedIndex = i;
                break;
            }
        }
        document.getElementById('editModal').classList.remove('hidden');
    }
    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }

    // =============================
    // Modal Excluir
    // =============================
    function abrirModalExcluir(id, nome) {
        document.getElementById('excluirId').value           = id;
        document.getElementById('modalNomeVaga').textContent = nome;
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
    // Fechar modais clicando fora
    // =============================
    document.getElementById('jobModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });

    // Fecha com ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            fecharModalExcluir();
            closeModal();
            closeEditModal();
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
