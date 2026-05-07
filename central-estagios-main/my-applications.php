<?php
require_once 'includes/config.php';
checkAuth();

$pageTitle = 'Minhas Candidaturas';

// ✅ Lê as vagas reais do admin-jobs
$jobsFile        = 'data/platform_jobs.json';
$applicationsFile = 'data/userApplications.json';

$allJobs      = file_exists($jobsFile) ? json_decode(file_get_contents($jobsFile), true) : [];
$applications = file_exists($applicationsFile) ? json_decode(file_get_contents($applicationsFile), true) : [];

// ✅ Cancelar candidatura
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $id = $_POST['id'] ?? 0;
    $applications = array_filter($applications, function($app) use ($id) {
        return $app['job_id'] != $id;
    });
    $applications = array_values($applications);
    saveData('userApplications', $applications);
    header('Location: my-applications.php?cancelada=1');
    exit;
}

// ✅ Monta lista de vagas que o usuário se candidatou
$myApplications = [];
foreach ($applications as $app) {
    foreach ($allJobs as $job) {
        if ($job['id'] == $app['job_id']) {
            $myApplications[] = array_merge($job, ['appliedDate' => $app['date']]);
            break;
        }
    }
}

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-7xl mx-auto">

        <!-- Header -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Minhas Candidaturas</h2>
            <p class="text-gray-600">Acompanhe as vagas em que você se inscreveu</p>
        </div>

        <!-- Alerta cancelamento -->
        <?php if (isset($_GET['cancelada'])): ?>
            <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-xl flex items-center gap-3">
                <i class="fas fa-info-circle text-yellow-500"></i>
                <p class="text-yellow-800 font-medium">Candidatura cancelada com sucesso.</p>
            </div>
        <?php endif; ?>

        <!-- Lista -->
        <div class="space-y-4">
            <?php if (!empty($myApplications)): ?>
                <?php foreach ($myApplications as $job): ?>
                    <div class="bg-white p-6 rounded-2xl shadow-sm hover:shadow-md transition-all">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-1">
                                    <?php echo htmlspecialchars($job['title']); ?>
                                    <?php if ($job['featured']): ?>
                                        <span class="ml-2 text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full">Destaque</span>
                                    <?php endif; ?>
                                </h3>
                                <p class="text-gray-700 font-medium"><?php echo htmlspecialchars($job['company']); ?></p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5 text-sm text-gray-600">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-map-marker-alt text-[#4A9FCA]"></i>
                                <span><?php echo htmlspecialchars($job['location']); ?></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fas fa-clock text-[#4A9FCA]"></i>
                                <span><?php echo htmlspecialchars($job['hours']); ?></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fas fa-dollar-sign text-[#27AE60]"></i>
                                <span class="font-semibold text-[#27AE60]"><?php echo htmlspecialchars($job['salary']); ?></span>
                            </div>
                            <div class="flex items-center gap-2 text-gray-400">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Inscrito em <?php echo htmlspecialchars($job['appliedDate']); ?></span>
                            </div>
                        </div>

                        <!-- Botões -->
                        <div class="flex gap-3 pt-4 border-t border-gray-100">
                            <button
                                onclick="abrirDetalhes(<?php echo htmlspecialchars(json_encode($job)); ?>)"
                                class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50 transition-all">
                                <i class="fas fa-eye mr-1"></i> Ver Detalhes
                            </button>
                            <button
                                onclick="abrirModalCancelar(<?php echo $job['id']; ?>, '<?php echo htmlspecialchars(addslashes($job['title'])); ?>')"
                                class="px-4 py-2 border border-red-200 text-red-600 rounded-lg text-sm hover:bg-red-50 transition-all">
                                <i class="fas fa-times mr-1"></i> Cancelar Candidatura
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>

            <?php else: ?>
                <div class="text-center py-20 bg-white rounded-2xl border-2 border-dashed border-gray-200">
                    <i class="fas fa-briefcase text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 font-medium text-lg">Você ainda não se candidatou a nenhuma vaga.</p>
                    <a href="jobs.php" class="inline-block mt-4 px-6 py-3 bg-[#4A9FCA] text-white rounded-xl hover:bg-[#3A8FB0] transition-all text-sm font-semibold">
                        Ver Vagas Disponíveis
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Form oculto cancelar -->
        <form id="formCancelar" method="POST" action="my-applications.php">
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="id" id="cancelarId" value="">
        </form>

        <!-- Modal Cancelar -->
        <div id="modalCancelar" class="fixed inset-0 z-[999] hidden items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="fecharModalCancelar()"></div>
            <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-sm p-8 z-10">

                <div class="w-16 h-16 bg-red-100 rounded-2xl flex items-center justify-center mx-auto mb-5">
                    <i class="fas fa-times-circle text-red-500 text-3xl"></i>
                </div>

                <h3 class="text-xl font-bold text-gray-900 text-center mb-2">Cancelar Candidatura?</h3>
                <p class="text-gray-500 text-sm text-center mb-1 leading-relaxed">Você está prestes a cancelar sua candidatura para:</p>
                <p id="modalNomeCandidatura" class="text-[#4A9FCA] font-semibold text-sm text-center mb-3"></p>
                <p class="text-gray-400 text-xs text-center mb-8">Você poderá se candidatar novamente depois.</p>

                <div class="flex gap-3">
                    <button onclick="fecharModalCancelar()"
                            class="flex-1 py-3 px-4 rounded-2xl border-2 border-gray-200 text-gray-600 font-semibold hover:bg-gray-50 transition-all text-sm">
                        <i class="fas fa-arrow-left mr-1"></i> Voltar
                    </button>
                    <button onclick="confirmarCancelar()"
                            class="flex-1 py-3 px-4 rounded-2xl bg-red-500 hover:bg-red-600 text-white font-semibold transition-all text-sm shadow-lg shadow-red-100">
                        <i class="fas fa-times mr-1"></i> Cancelar
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Ver Detalhes -->
        <div id="modalDetalhes" class="fixed inset-0 z-[999] hidden items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="fecharDetalhes()"></div>
            <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-lg p-8 z-10 max-h-[90vh] overflow-y-auto">

                <div class="flex items-start justify-between mb-6">
                    <div>
                        <h3 id="detalhesTitulo" class="text-xl font-bold text-gray-900 mb-1"></h3>
                        <p id="detalhesEmpresa" class="text-[#4A9FCA] font-medium"></p>
                    </div>
                    <button onclick="fecharDetalhes()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <!-- Infos rápidas -->
                <div class="grid grid-cols-2 gap-3 mb-6">
                    <div class="p-3 bg-gray-50 rounded-xl text-sm">
                        <p class="text-gray-400 text-xs mb-1">Localização</p>
                        <p id="detalhesLocal" class="font-medium text-gray-700"></p>
                    </div>
                    <div class="p-3 bg-gray-50 rounded-xl text-sm">
                        <p class="text-gray-400 text-xs mb-1">Modalidade</p>
                        <p id="detalhesTipo" class="font-medium text-gray-700"></p>
                    </div>
                    <div class="p-3 bg-gray-50 rounded-xl text-sm">
                        <p class="text-gray-400 text-xs mb-1">Carga Horária</p>
                        <p id="detalhesHoras" class="font-medium text-gray-700"></p>
                    </div>
                    <div class="p-3 bg-green-50 rounded-xl text-sm">
                        <p class="text-gray-400 text-xs mb-1">Bolsa Auxílio</p>
                        <p id="detalhesSalario" class="font-semibold text-[#27AE60]"></p>
                    </div>
                </div>

                <!-- Descrição -->
                <div class="mb-4">
                    <h4 class="text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide">Descrição</h4>
                    <p id="detalhesDescricao" class="text-sm text-gray-600 leading-relaxed"></p>
                </div>

                <!-- Requisitos -->
                <div class="mb-4">
                    <h4 class="text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide">Requisitos</h4>
                    <p id="detalhesRequisitos" class="text-sm text-gray-600 leading-relaxed"></p>
                </div>

                <!-- Benefícios -->
                <div class="mb-6">
                    <h4 class="text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide">Benefícios</h4>
                    <p id="detalhesBeneficios" class="text-sm text-gray-600 leading-relaxed"></p>
                </div>

                <button onclick="fecharDetalhes()"
                        class="w-full py-3 rounded-2xl border-2 border-gray-200 text-gray-600 font-semibold hover:bg-gray-50 transition-all text-sm">
                    Fechar
                </button>
            </div>
        </div>

    </div>
</main>

<script>
    // =============================
    // Modal Cancelar
    // =============================
    function abrirModalCancelar(id, nome) {
        document.getElementById('cancelarId').value               = id;
        document.getElementById('modalNomeCandidatura').textContent = nome;
        const modal = document.getElementById('modalCancelar');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    function fecharModalCancelar() {
        const modal = document.getElementById('modalCancelar');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    function confirmarCancelar() {
        document.getElementById('formCancelar').submit();
    }

    // =============================
    // Modal Ver Detalhes
    // =============================
    function abrirDetalhes(job) {
        document.getElementById('detalhesTitulo').textContent    = job.title;
        document.getElementById('detalhesEmpresa').textContent   = job.company;
        document.getElementById('detalhesLocal').textContent     = job.location;
        document.getElementById('detalhesTipo').textContent      = job.type;
        document.getElementById('detalhesHoras').textContent     = job.hours;
        document.getElementById('detalhesSalario').textContent   = job.salary;
        document.getElementById('detalhesDescricao').textContent = job.description;
        document.getElementById('detalhesRequisitos').textContent= job.requirements;
        document.getElementById('detalhesBeneficios').textContent= job.benefits;

        const modal = document.getElementById('modalDetalhes');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    function fecharDetalhes() {
        const modal = document.getElementById('modalDetalhes');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    // Fecha com ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            fecharModalCancelar();
            fecharDetalhes();
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
