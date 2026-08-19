<?php
require_once 'includes/config.php';
checkAdmin(); // ja bloqueia nao-logados e nao-admins (via URL direta inclusive)

header('Content-Type: text/html; charset=UTF-8');

$pageTitle = 'Gerenciar Vagas';

if (!function_exists('getInitialJobs')) {
    function getInitialJobs() {
        return [
            [
                'id' => 1,
                'title' => 'Estagiario de Desenvolvimento Web',
                'company' => 'Tech Solutions',
                'location' => 'Santo Andre, SP',
                'type' => 'Hibrido',
                'hours' => '30h semanais',
                'salary' => 'R$ 1.200,00',
                'description' => 'Desenvolvimento de sistemas web com PHP e JavaScript.',
                'requirements' => 'Conhecimentos em HTML, CSS, JavaScript e PHP.',
                'benefits' => 'Vale transporte, Vale refeicao',
                'courses' => 'Ciencia da Computacao, Sistemas de Informacao',
                'applicationLink' => '',
                'featured' => true,
                'status' => 'Ativa',
                'applicants' => 12,
                'createdAt' => '01/05/2026'
            ],
            [
                'id' => 2,
                'title' => 'Estagiario de Data Science',
                'company' => 'DataCorp',
                'location' => 'Sao Paulo, SP',
                'type' => 'Remoto',
                'hours' => '20h semanais',
                'salary' => 'R$ 1.500,00',
                'description' => 'Analise de dados e criacao de modelos preditivos.',
                'requirements' => 'Python, Pandas, Machine Learning basico.',
                'benefits' => 'Vale transporte, Plano de saude',
                'courses' => 'Ciencia de Dados e Inteligencia Artificial',
                'applicationLink' => '',
                'featured' => true,
                'status' => 'Ativa',
                'applicants' => 8,
                'createdAt' => '03/05/2026'
            ],
            [
                'id' => 3,
                'title' => 'Estagiario de Marketing Digital',
                'company' => 'AgenciaX',
                'location' => 'ABC Paulista, SP',
                'type' => 'Presencial',
                'hours' => '30h semanais',
                'salary' => 'R$ 900,00',
                'description' => 'Criacao de conteudo e gestao de redes sociais.',
                'requirements' => 'Canva, Nocoes de SEO, Boa comunicacao.',
                'benefits' => 'Vale transporte',
                'courses' => 'Administracao',
                'applicationLink' => '',
                'featured' => false,
                'status' => 'Ativa',
                'applicants' => 5,
                'createdAt' => '05/05/2026'
            ],
        ];
    }
}

$fsaCourses = getFsaCourses();

if (isset($_GET['reset'])) {
    $jobs = getInitialJobs();
    saveData('platform_jobs', $jobs);
    header('Location: admin-jobs.php');
    exit;
}

$jobs = loadData('platform_jobs');
if (empty($jobs)) {
    $jobs = getInitialJobs();
}

/* ============================= NOVO ============================= *
 * SOLICITACOES DE VAGA ENVIADAS PELOS ALUNOS (job_requests.json)
 * ================================================================= */
$jobRequests = loadData('job_requests');
if (!is_array($jobRequests)) {
    $jobRequests = [];
}
$solicitacoesPendentes = array_values(array_filter($jobRequests, function ($r) {
    return ($r['status'] ?? 'pendente') === 'pendente';
}));
$totalPendentes = count($solicitacoesPendentes);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'save') {
        $coursesSelected = $_POST['courses'] ?? [];
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
            'courses' => implode(', ', $coursesSelected),
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
        $coursesSelected = $_POST['courses'] ?? [];
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
                $job['courses']         = implode(', ', $coursesSelected);
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

    /* ============================= NOVO ============================= *
     * APROVAR SOLICITACAO: vira uma vaga de verdade em platform_jobs
     * ================================================================= */
    if ($_POST['action'] === 'approve_request') {
        $reqId = $_POST['request_id'] ?? 0;
        foreach ($jobRequests as &$req) {
            if ($req['id'] == $reqId) {
                $newJob = [
                    'id'              => time(),
                    'title'           => $req['title'],
                    'company'         => $req['company'],
                    'location'        => $req['location'],
                    'type'            => $req['type'],
                    'hours'           => $req['hours'],
                    'salary'          => $req['salary'],
                    'description'     => $req['description'],
                    'requirements'    => $req['requirements'],
                    'benefits'        => $req['benefits'],
                    'courses'         => $req['courses'],
                    'applicationLink' => $req['applicationLink'],
                    'featured'        => $req['featured'],
                    'status'          => 'Ativa',
                    'applicants'      => 0,
                    'createdAt'       => date('d/m/Y'),
                ];
                array_unshift($jobs, $newJob);
                saveData('platform_jobs', $jobs);

                $req['status'] = 'aprovada';
                break;
            }
        }
        unset($req);
        saveData('job_requests', $jobRequests);
        header('Location: admin-jobs.php');
        exit;
    }

    /* ============================= NOVO ============================= *
     * REJEITAR SOLICITACAO: fica marcada como rejeitada, com motivo
     * ================================================================= */
    if ($_POST['action'] === 'reject_request') {
        $reqId = $_POST['request_id'] ?? 0;
        $motivo = trim($_POST['motivo'] ?? '');
        foreach ($jobRequests as &$req) {
            if ($req['id'] == $reqId) {
                $req['status'] = 'rejeitada';
                $req['motivoRejeicao'] = $motivo !== '' ? $motivo : 'Não especificado pela coordenação.';
                break;
            }
        }
        unset($req);
        saveData('job_requests', $jobRequests);
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
                <p class="text-gray-600">Crie e gerencie oportunidades de estagio</p>
            </div>
            <div class="flex items-center gap-3">
                <!-- ============================= NOVO ============================= -->
                <!-- Sino de notificacao de solicitacoes pendentes -->
                <button onclick="openSolicitacoesModal()" class="relative w-11 h-11 flex items-center justify-center rounded-lg border border-gray-200 bg-white hover:bg-gray-50 transition-all" title="Solicitações de vaga dos alunos">
                    <i class="fas fa-bell text-gray-500 text-lg"></i>
                    <?php if ($totalPendentes > 0): ?>
                        <span class="absolute -top-1.5 -right-1.5 bg-red-500 text-white text-xs font-bold w-5 h-5 flex items-center justify-center rounded-full border-2 border-white">
                            <?php echo $totalPendentes; ?>
                        </span>
                    <?php endif; ?>
                </button>
                <!-- ================================================================= -->

                <button onclick="openCreateModal()" class="px-4 py-2 bg-[#4A9FCA] text-white rounded-lg hover:bg-[#3A8FB0]">
                    + Nova Vaga
                </button>
            </div>
        </div>

        <!-- ============================= NOVO ============================= -->
        <!-- Modal: Lista de Solicitacoes Pendentes -->
        <div id="solicitacoesModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl p-8 max-w-3xl w-full max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-2xl font-bold text-gray-900">
                        <i class="fas fa-bell text-[#4A9FCA] mr-2"></i>Solicitações de Vaga
                    </h3>
                    <button onclick="closeSolicitacoesModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <?php if (empty($solicitacoesPendentes)): ?>
                    <div class="text-center py-16 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200">
                        <i class="fas fa-inbox text-3xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500 font-medium">Nenhuma solicitação pendente no momento.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($solicitacoesPendentes as $req): ?>
                        <div class="border border-gray-200 rounded-xl p-5">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($req['title']); ?></h4>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($req['company']); ?> &bull; <?php echo htmlspecialchars($req['location'] ?: 'Local não informado'); ?></p>
                                    <p class="text-xs text-gray-400 mt-1">
                                        Enviada por <?php echo htmlspecialchars($req['studentName']); ?> (<?php echo htmlspecialchars($req['studentEmail']); ?>) em <?php echo htmlspecialchars($req['createdAt']); ?>
                                    </p>
                                </div>
                                <span class="px-3 py-1 bg-amber-100 text-amber-800 rounded-full text-xs font-semibold whitespace-nowrap">Pendente</span>
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3 text-sm">
                                <div><span class="text-gray-500">Tipo:</span> <p class="font-medium"><?php echo htmlspecialchars($req['type']); ?></p></div>
                                <div><span class="text-gray-500">Carga horária:</span> <p class="font-medium"><?php echo htmlspecialchars($req['hours'] ?: '—'); ?></p></div>
                                <div><span class="text-gray-500">Bolsa:</span> <p class="font-medium text-green-600"><?php echo htmlspecialchars($req['salary'] ?: '—'); ?></p></div>
                                <div><span class="text-gray-500">Destaque:</span> <p class="font-medium"><?php echo $req['featured'] ? 'Sim' : 'Não'; ?></p></div>
                            </div>

                            <div class="text-sm mb-2">
                                <span class="text-gray-500">Descrição:</span>
                                <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($req['description'])); ?></p>
                            </div>
                            <?php if (!empty($req['requirements'])): ?>
                            <div class="text-sm mb-2">
                                <span class="text-gray-500">Requisitos:</span>
                                <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($req['requirements'])); ?></p>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($req['benefits'])): ?>
                            <div class="text-sm mb-2">
                                <span class="text-gray-500">Benefícios:</span>
                                <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($req['benefits'])); ?></p>
                            </div>
                            <?php endif; ?>
                            <div class="text-sm mb-4">
                                <span class="text-gray-500">Cursos relacionados:</span>
                                <p class="text-gray-700"><?php echo htmlspecialchars($req['courses'] ?: 'Não especificado'); ?></p>
                            </div>
                            <?php if (!empty($req['applicationLink'])): ?>
                            <div class="text-sm mb-4">
                                <span class="text-gray-500">Link de candidatura:</span>
                                <a href="<?php echo htmlspecialchars($req['applicationLink']); ?>" target="_blank" class="text-[#4A9FCA] hover:underline break-all"><?php echo htmlspecialchars($req['applicationLink']); ?></a>
                            </div>
                            <?php endif; ?>

                            <div class="flex gap-3 pt-3 border-t">
                                <form method="POST" action="admin-jobs.php" class="flex-1">
                                    <input type="hidden" name="action" value="approve_request">
                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                    <button type="submit" class="w-full py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-semibold flex items-center justify-center gap-2">
                                        <i class="fas fa-check"></i> Aprovar e Publicar
                                    </button>
                                </form>
                                <button type="button" onclick="abrirModalRejeitar(<?php echo $req['id']; ?>)" class="flex-1 py-2 border border-red-200 text-red-600 hover:bg-red-50 rounded-lg text-sm font-semibold flex items-center justify-center gap-2">
                                    <i class="fas fa-times"></i> Rejeitar
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modal: Motivo de Rejeicao -->
        <div id="rejeitarModal" class="hidden fixed inset-0 bg-black/50 z-[60] flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl p-6 max-w-md w-full">
                <h3 class="text-lg font-bold text-gray-900 mb-3">Rejeitar Solicitação</h3>
                <p class="text-sm text-gray-500 mb-4">Explique brevemente o motivo (o aluno verá essa mensagem).</p>
                <form method="POST" action="admin-jobs.php">
                    <input type="hidden" name="action" value="reject_request">
                    <input type="hidden" name="request_id" id="rejeitarRequestId">
                    <textarea name="motivo" rows="3" class="w-full px-3 py-2 border rounded-lg mb-4" placeholder="Ex: Dados incompletos, empresa não confere, etc."></textarea>
                    <div class="flex gap-3">
                        <button type="button" onclick="fecharModalRejeitar()" class="flex-1 py-2 border rounded-lg text-sm">Cancelar</button>
                        <button type="submit" class="flex-1 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm font-semibold">Confirmar Rejeição</button>
                    </div>
                </form>
            </div>
        </div>
        <!-- ================================================================= -->

        <!-- Modal Criar Vaga -->
        <div id="jobModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
            <div class="bg-white rounded-2xl p-8 max-w-3xl w-full max-h-[90vh] overflow-y-auto">
                <h3 class="text-2xl font-bold mb-6">Nova Vaga</h3>
                <form method="POST" action="admin-jobs.php" accept-charset="UTF-8" class="space-y-4">
                    <input type="hidden" name="action" value="save">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Titulo da Vaga *</label>
                            <input type="text" name="title" class="w-full h-10 px-3 border rounded-lg" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Empresa *</label>
                            <input type="text" name="company" class="w-full h-10 px-3 border rounded-lg" required>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Localizacao</label>
                            <input type="text" name="location" class="w-full h-10 px-3 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Tipo</label>
                            <select name="type" class="w-full h-10 px-3 border rounded-lg">
                                <option value="Presencial">Presencial</option>
                                <option value="Remoto">Remoto</option>
                                <option value="Hibrido">Hibrido</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Bolsa Auxilio</label>
                            <input type="text" name="salary" class="w-full h-10 px-3 border rounded-lg">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Carga Horaria</label>
                            <input type="text" name="hours" placeholder="Ex: 30h semanais" class="w-full h-10 px-3 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">
                                Link de Candidatura
                                <span class="text-xs text-gray-400 font-normal">(para onde o botao "Candidatar-se" vai levar)</span>
                            </label>
                            <input type="url" name="applicationLink" placeholder="https://..." class="w-full h-10 px-3 border rounded-lg">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Cursos Relacionados</label>
                        <select name="courses[]" multiple class="w-full h-32 px-3 py-2 border rounded-lg">
                            <?php foreach ($fsaCourses as $course): ?>
                                <option value="<?php echo htmlspecialchars($course); ?>"><?php echo htmlspecialchars($course); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Segure Ctrl (ou Cmd no Mac) para selecionar mais de um curso.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Descricao *</label>
                        <textarea name="description" rows="3" class="w-full px-3 py-2 border rounded-lg"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Requisitos</label>
                        <textarea name="requirements" rows="3" class="w-full px-3 py-2 border rounded-lg"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Beneficios</label>
                        <textarea name="benefits" rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea>
                    </div>
                    <div>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="featured" class="rounded">
                            <span class="text-yellow-800"><i class="fas fa-star"></i> Marcar como Vaga em Destaque</span>
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
                <form method="POST" action="admin-jobs.php" accept-charset="UTF-8" class="space-y-4">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="editId">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Titulo da Vaga *</label>
                            <input type="text" name="title" id="editTitle" class="w-full h-10 px-3 border rounded-lg" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Empresa *</label>
                            <input type="text" name="company" id="editCompany" class="w-full h-10 px-3 border rounded-lg" required>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Localizacao</label>
                            <input type="text" name="location" id="editLocation" class="w-full h-10 px-3 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Tipo</label>
                            <select name="type" id="editType" class="w-full h-10 px-3 border rounded-lg">
                                <option value="Presencial">Presencial</option>
                                <option value="Remoto">Remoto</option>
                                <option value="Hibrido">Hibrido</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Bolsa Auxilio</label>
                            <input type="text" name="salary" id="editSalary" class="w-full h-10 px-3 border rounded-lg">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Carga Horaria</label>
                            <input type="text" name="hours" id="editHours" class="w-full h-10 px-3 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">
                                Link de Candidatura
                                <span class="text-xs text-gray-400 font-normal">(para onde o botao "Candidatar-se" vai levar)</span>
                            </label>
                            <input type="url" name="applicationLink" id="editApplicationLink" class="w-full h-10 px-3 border rounded-lg">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Cursos Relacionados</label>
                        <select name="courses[]" id="editCourses" multiple class="w-full h-32 px-3 py-2 border rounded-lg">
                            <?php foreach ($fsaCourses as $course): ?>
                                <option value="<?php echo htmlspecialchars($course); ?>"><?php echo htmlspecialchars($course); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Segure Ctrl (ou Cmd no Mac) para selecionar mais de um curso.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Descricao</label>
                        <textarea name="description" id="editDescription" rows="3" class="w-full px-3 py-2 border rounded-lg"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Requisitos</label>
                        <textarea name="requirements" id="editRequirements" rows="3" class="w-full px-3 py-2 border rounded-lg"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Beneficios</label>
                        <textarea name="benefits" id="editBenefits" rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea>
                    </div>
                    <div>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="featured" id="editFeatured" class="rounded">
                            <span class="text-yellow-800"><i class="fas fa-star"></i> Marcar como Vaga em Destaque</span>
                        </label>
                    </div>
                    <div class="flex gap-4 pt-4">
                        <button type="button" onclick="closeEditModal()" class="flex-1 py-3 border rounded-lg">Cancelar</button>
                        <button type="submit" class="flex-1 py-3 bg-[#4A9FCA] text-white rounded-lg">Salvar Alteracoes</button>
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
                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($job['location']); ?> &bull; <?php echo htmlspecialchars($job['type']); ?></p>
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
                        <div>
                            <span class="text-gray-500">Cursos:</span>
                            <p class="font-medium"><?php echo htmlspecialchars($job['courses'] ?: 'Todos'); ?></p>
                        </div>
                    </div>

                    <?php if (!empty($job['applicationLink'])): ?>
                        <div class="mb-4 text-sm">
                            <span class="text-gray-500">Link de candidatura:</span>
                            <a href="<?php echo htmlspecialchars($job['applicationLink']); ?>" target="_blank" class="text-[#4A9FCA] font-medium hover:underline break-all">
                                <?php echo htmlspecialchars($job['applicationLink']); ?>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="mb-4 text-sm">
                            <span class="text-yellow-600 font-medium"><i class="fas fa-exclamation-triangle mr-1"></i> Nenhum link de candidatura cadastrado</span>
                        </div>
                    <?php endif; ?>

                    <div class="flex gap-3 pt-4 border-t">

                        <!-- Editar: agora usa data-job (JSON seguro) em vez de onclick com addslashes -->
                        <button type="button"
                            class="btn-editar-vaga px-4 py-2 bg-[#4A9FCA] text-white rounded-lg text-sm hover:bg-[#3A8FB0] flex items-center gap-2"
                            data-job='<?php echo htmlspecialchars(json_encode([
                                "id"              => $job["id"],
                                "title"           => $job["title"],
                                "company"         => $job["company"],
                                "location"        => $job["location"],
                                "type"            => $job["type"],
                                "salary"          => $job["salary"],
                                "hours"           => $job["hours"] ?? "",
                                "applicationLink" => $job["applicationLink"] ?? "",
                                "courses"         => array_values(array_filter(array_map('trim', explode(',', $job['courses'] ?? '')))),
                                "description"     => $job["description"],
                                "requirements"    => $job["requirements"],
                                "benefits"        => $job["benefits"],
                                "featured"        => $job["featured"] ? true : false,
                            ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS), ENT_QUOTES, 'UTF-8'); ?>'>
                            <i class="fas fa-edit"></i> Editar
                        </button>

                        <!-- Excluir -->
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

        <!-- Form oculto de exclusao -->
        <form id="formExcluir" method="POST" action="admin-jobs.php" accept-charset="UTF-8">
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
                <p class="text-gray-500 text-sm text-center mb-1 leading-relaxed">Voce esta prestes a excluir:</p>
                <p id="modalNomeVaga" class="text-[#4A9FCA] font-semibold text-sm text-center mb-3 leading-relaxed"></p>
                <p class="text-gray-400 text-xs text-center mb-8">Esta acao nao pode ser desfeita e a vaga sumira para todos os alunos.</p>

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
    function editJob(job) {
        document.getElementById('editId').value               = job.id;
        document.getElementById('editTitle').value            = job.title;
        document.getElementById('editCompany').value          = job.company;
        document.getElementById('editLocation').value         = job.location;
        document.getElementById('editSalary').value           = job.salary;
        document.getElementById('editHours').value            = job.hours;
        document.getElementById('editApplicationLink').value  = job.applicationLink;
        document.getElementById('editDescription').value      = job.description;
        document.getElementById('editRequirements').value     = job.requirements;
        document.getElementById('editBenefits').value          = job.benefits;
        document.getElementById('editFeatured').checked       = job.featured;

        // Marca as opcoes de curso ja selecionadas para essa vaga
        const coursesSelect = document.getElementById('editCourses');
        for (let opt of coursesSelect.options) {
            opt.selected = job.courses.includes(opt.value);
        }

        const selectType = document.getElementById('editType');
        for (let i = 0; i < selectType.options.length; i++) {
            if (selectType.options[i].value === job.type) {
                selectType.selectedIndex = i;
                break;
            }
        }
        document.getElementById('editModal').classList.remove('hidden');
    }
    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }

    // Liga o clique de cada botao "Editar" ao seu data-job (JSON seguro, sem onclick inline)
    document.querySelectorAll('.btn-editar-vaga').forEach(function (btn) {
        btn.addEventListener('click', function () {
            try {
                const job = JSON.parse(this.dataset.job);
                editJob(job);
            } catch (e) {
                console.error('Erro ao ler dados da vaga:', e);
                alert('Nao foi possivel abrir a edicao desta vaga.');
            }
        });
    });

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

    /* ============================= NOVO ============================= */
    // Modal Solicitacoes
    function openSolicitacoesModal() {
        document.getElementById('solicitacoesModal').classList.remove('hidden');
    }
    function closeSolicitacoesModal() {
        document.getElementById('solicitacoesModal').classList.add('hidden');
    }
    // Modal Rejeitar
    function abrirModalRejeitar(id) {
        document.getElementById('rejeitarRequestId').value = id;
        document.getElementById('rejeitarModal').classList.remove('hidden');
    }
    function fecharModalRejeitar() {
        document.getElementById('rejeitarModal').classList.add('hidden');
    }
    document.getElementById('solicitacoesModal').addEventListener('click', function(e) {
        if (e.target === this) closeSolicitacoesModal();
    });
    document.getElementById('rejeitarModal').addEventListener('click', function(e) {
        if (e.target === this) fecharModalRejeitar();
    });
    /* ================================================================= */

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
            closeSolicitacoesModal();
            fecharModalRejeitar();
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
