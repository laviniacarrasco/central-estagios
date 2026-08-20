<?php
require_once 'includes/config.php';
checkAuth();

$pageTitle = 'Vagas de Estágio';

// Carregar vagas ativas (via config.php, mesma lÃ³gica do resto do sistema)
$allJobs = loadData('platform_jobs');
$allJobs = array_filter($allJobs ?? [], function($job) {
    return ($job['status'] ?? '') === 'Ativa';
});

// Cursos da FSA â€” fonte Ãºnica, vinda do config.php
$fsaCourses = getFsaCourses();

// Filtros
$searchTerm = $_GET['search'] ?? '';
$selectedCourses = $_GET['courses'] ?? [];
$selectedCourses = array_filter((array)$selectedCourses); // remove valores vazios ("Selecione seu curso...")

function normalize($str) {
    return strtolower(preg_replace('/[Ã¡Ã Ã£Ã¢Ã¤Ã©Ã¨ÃªÃ«Ã­Ã¬Ã®Ã¯Ã³Ã²ÃµÃ´Ã¶ÃºÃ¹Ã»Ã¼Ã§]/', '', $str));
}

$filteredJobs = array_filter($allJobs, function($job) use ($searchTerm, $selectedCourses) {
    if ($searchTerm) {
        $found = false;
        $searchNorm = normalize($searchTerm);
        if (strpos(normalize($job['title']), $searchNorm) !== false) $found = true;
        if (strpos(normalize($job['company']), $searchNorm) !== false) $found = true;
        if (strpos(normalize($job['description']), $searchNorm) !== false) $found = true;
        if (!$found) return false;
    }

    // Filtro de curso â€” courses Ã© salvo como string "Curso A, Curso B"
    if (!empty($selectedCourses)) {
        $jobCoursesRaw = trim($job['courses'] ?? '');
        if ($jobCoursesRaw !== '') {
            $jobCoursesList = array_map('trim', explode(',', $jobCoursesRaw));
            $match = false;
            foreach ($selectedCourses as $course) {
                if (in_array($course, $jobCoursesList)) {
                    $match = true;
                    break;
                }
            }
            if (!$match) return false;
        }
        // se courses estiver vazio, a vaga continua aparecendo (tolerante)
    }

    return true;
});

$featuredJobs = array_filter($filteredJobs, function($job) {
    return ($job['featured'] ?? false) === true;
});

$regularJobs = array_filter($filteredJobs, function($job) {
    return ($job['featured'] ?? false) !== true;
});

// CÃ¡lculo real de "Novas Esta Semana", baseado em createdAt
$novasEstaSemana = array_filter($allJobs, function($job) {
    if (empty($job['createdAt'])) return false;
    $data = strtotime($job['createdAt']);
    if (!$data) return false;
    return $data >= strtotime('-7 days');
});
$totalNovasSemana = count($novasEstaSemana);

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-7xl mx-auto">

        <!-- Header -->
        <div class="mb-8 animate-fade-in">
            <h2 class="text-3xl font-bold text-slate-900 mb-2">Vagas de Estágio</h2>
            <p class="text-slate-500 font-medium">Encontre oportunidades que combinam com seu perfil</p>
        </div>

        <!-- Search and Filter -->
        <div class="flex gap-4 mb-8">
            <form method="GET" action="jobs.php" class="flex-1 flex gap-4">
                <div class="flex-1 relative">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input
                        type="text"
                        name="search"
                        placeholder="Buscar por vaga, empresa ou curso..."
                        value="<?php echo htmlspecialchars($searchTerm); ?>"
                        class="w-full h-14 pl-12 bg-white border border-gray-200 rounded-xl text-base focus:ring-2 focus:ring-[#4A9FCA]"
                    />
                </div>
                <button type="button" onclick="toggleFilters()" class="h-14 px-6 bg-white border border-gray-200 rounded-xl font-semibold text-slate-700">
                    <i class="fas fa-filter mr-1"></i> Filtros
                </button>
                <button type="submit" class="h-14 px-6 bg-[#4A9FCA] text-white rounded-xl">Buscar</button>
            </form>
        </div>

        <!-- Filtros de Cursos -->
        <div id="filtersSection" class="mb-8 p-6 bg-white rounded-2xl border border-gray-100 <?php echo $selectedCourses ? '' : 'hidden'; ?>">
            <div class="flex justify-between items-center mb-4">
                <h4 class="font-bold text-slate-800">Seu Curso na FSA</h4>
                <a href="jobs.php" class="text-red-500 text-sm">Limpar Filtros</a>
            </div>
            <form method="GET" action="jobs.php">
                <?php if ($searchTerm): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>">
                <?php endif; ?>
                <select name="courses[]" class="w-full h-12 px-4 rounded-xl border border-gray-200 bg-gray-50 mb-4" onchange="this.form.submit()">
                    <option value="">Selecione seu curso...</option>
                    <?php foreach ($fsaCourses as $course): ?>
                        <option value="<?php echo $course; ?>" <?php echo in_array($course, (array)$selectedCourses) ? 'selected' : ''; ?>>
                            <?php echo $course; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php if ($selectedCourses): ?>
                <div class="flex flex-wrap gap-3">
                    <?php foreach ((array)$selectedCourses as $course): ?>
                        <span class="flex items-center gap-2 px-5 py-2.5 bg-[#58A1D4] text-white rounded-xl font-medium">
                            <?php echo htmlspecialchars($course); ?>
                            <a href="jobs.php?search=<?php echo urlencode($searchTerm); ?>" class="hover:text-red-200">âœ•</a>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="p-6 bg-[#58A1D4] text-white rounded-2xl relative overflow-hidden h-32 shadow-sm">
                <p class="text-white/90 text-sm font-medium mb-1">Total de Vagas</p>
                <p class="text-5xl font-bold"><?php echo count($filteredJobs); ?></p>
                <i class="fas fa-briefcase absolute -right-4 -bottom-4 text-6xl text-white/10 transform rotate-12"></i>
            </div>
            <div class="p-6 bg-[#E35444] text-white rounded-2xl relative overflow-hidden h-32 shadow-sm">
                <p class="text-white/90 text-sm font-medium mb-1">Vagas em Destaque</p>
                <p class="text-5xl font-bold"><?php echo count($featuredJobs); ?></p>
                <i class="fas fa-star absolute -right-4 -bottom-4 text-6xl text-white/10 transform rotate-12"></i>
            </div>
            <div class="p-6 bg-[#27AE60] text-white rounded-2xl relative overflow-hidden h-32 shadow-sm">
                <p class="text-white/90 text-sm font-medium mb-1">Novas Esta Semana</p>
                <p class="text-5xl font-bold"><?php echo $totalNovasSemana; ?></p>
                <i class="fas fa-chart-line absolute -right-4 -bottom-4 text-6xl text-white/10 transform rotate-12"></i>
            </div>
        </div>

        <!-- Vagas em Destaque -->
        <?php if (!empty($featuredJobs)): ?>
            <div class="mb-10">
                <h3 class="text-xl font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <i class="fas fa-star text-yellow-400"></i> Vagas em Destaque
                </h3>
                <div class="space-y-4">
                    <?php foreach ($featuredJobs as $job): ?>
                        <div class="p-6 border-2 border-yellow-200 bg-yellow-50/30 rounded-2xl shadow-sm">
                            <div class="flex items-start gap-6">
                                <div class="w-16 h-16 bg-[#58A1D4] rounded-xl flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-building text-2xl text-white"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="flex justify-between items-start mb-3">
                                        <div>
                                            <h3 class="text-xl font-bold text-slate-900">
                                                <?php echo htmlspecialchars($job['title']); ?>
                                                <i class="fas fa-star text-yellow-400 text-base ml-1"></i>
                                            </h3>
                                            <p class="text-slate-600 font-semibold text-lg"><?php echo htmlspecialchars($job['company']); ?></p>
                                        </div>
                                        <span class="px-3 py-1 bg-[#58A1D4] text-white rounded-full font-bold">
                                            <?php echo htmlspecialchars($job['type']); ?>
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5 text-sm font-medium">
                                        <div class="flex items-center gap-2 text-slate-500">
                                            <i class="fas fa-map-marker-alt text-[#E35444]"></i>
                                            <?php echo htmlspecialchars($job['location']); ?>
                                        </div>
                                        <div class="flex items-center gap-2 text-slate-500">
                                            <i class="fas fa-clock text-[#58A1D4]"></i>
                                            <?php echo htmlspecialchars($job['hours']); ?>
                                        </div>
                                        <div class="flex items-center gap-2 font-bold text-[#27AE60]">
                                            <i class="fas fa-dollar-sign"></i>
                                            <?php echo htmlspecialchars($job['salary']); ?>
                                        </div>
                                        <div class="text-slate-400">
                                            <?php echo htmlspecialchars($job['createdAt'] ?? 'Recente'); ?>
                                        </div>
                                    </div>
                                    <div class="flex gap-3">
                                        <!-- âœ… Ver Detalhes -->
                                        <button
                                            onclick="showDetails(<?php echo $job['id']; ?>)"
                                            class="px-6 py-2 border border-gray-200 text-gray-600 rounded-xl hover:bg-gray-50 transition-all text-sm">
                                            <i class="fas fa-eye mr-1"></i> Ver Detalhes
                                        </button>
                                        <!-- âœ… Candidatar-se: leva ao link cadastrado pelo admin -->
                                        <a href="<?php echo htmlspecialchars($job['applicationLink'] ?? '#'); ?>"
                                           target="_blank"
                                           class="px-6 py-2 bg-[#58A1D4] hover:bg-[#4A8FBA] text-white rounded-xl transition-all text-sm">
                                            <i class="fas fa-paper-plane mr-1"></i> Candidatar-se
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Vagas Regulares -->
        <section>
            <h3 class="text-xl font-bold text-slate-900 mb-4">Todas as Vagas</h3>
            <div class="space-y-4">
                <?php foreach ($regularJobs as $job): ?>
                    <div class="p-6 border border-gray-100 bg-white rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-start gap-6">
                            <div class="w-14 h-14 bg-slate-50 rounded-xl flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-building text-slate-400 text-xl"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h3 class="text-lg font-bold text-slate-900"><?php echo htmlspecialchars($job['title']); ?></h3>
                                        <p class="text-slate-500 font-semibold"><?php echo htmlspecialchars($job['company']); ?></p>
                                    </div>
                                    <span class="px-3 py-1 border border-slate-200 text-slate-400 rounded-full font-bold">
                                        <?php echo htmlspecialchars($job['type']); ?>
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5 text-sm font-medium">
                                    <div class="flex items-center gap-2 text-slate-500">
                                        <i class="fas fa-map-marker-alt text-[#E35444]"></i>
                                        <?php echo htmlspecialchars($job['location']); ?>
                                    </div>
                                    <div class="flex items-center gap-2 text-slate-500">
                                        <i class="fas fa-clock text-[#58A1D4]"></i>
                                        <?php echo htmlspecialchars($job['hours']); ?>
                                    </div>
                                    <div class="flex items-center gap-2 font-bold text-[#27AE60]">
                                        <i class="fas fa-dollar-sign"></i>
                                        <?php echo htmlspecialchars($job['salary']); ?>
                                    </div>
                                    <div class="text-slate-400">
                                        <?php echo htmlspecialchars($job['createdAt'] ?? 'Recente'); ?>
                                    </div>
                                </div>
                                <div class="flex gap-3">
                                    <!-- âœ… Ver Detalhes -->
                                    <button
                                        onclick="showDetails(<?php echo $job['id']; ?>)"
                                        class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 transition-all text-sm">
                                        <i class="fas fa-eye mr-1"></i> Ver Detalhes
                                    </button>
                                    <!-- âœ… Candidatar-se: leva ao link cadastrado pelo admin -->
                                    <a href="<?php echo htmlspecialchars($job['applicationLink'] ?? '#'); ?>"
                                       target="_blank"
                                       class="px-4 py-2 bg-[#58A1D4] hover:bg-[#4A8FBA] text-white rounded-lg transition-all text-sm">
                                        <i class="fas fa-paper-plane mr-1"></i> Candidatar-se
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Empty State -->
        <?php if (empty($filteredJobs)): ?>
            <div class="p-16 text-center border-2 border-dashed border-gray-200 rounded-3xl">
                <i class="fas fa-briefcase text-6xl text-gray-200 mb-4"></i>
                <h3 class="text-xl font-bold text-slate-900 mb-2">Nenhuma vaga encontrada</h3>
                <p class="text-slate-500 font-medium">Tente ajustar seus filtros ou termos de busca.</p>
            </div>
        <?php endif; ?>

    </div>
</main>

<!-- âœ… Modal de Detalhes (mesmo estilo do my-applications) -->
<div id="detailsModal" class="fixed inset-0 z-[999] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeDetails()"></div>
    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-lg p-8 z-10 max-h-[90vh] overflow-y-auto">

        <!-- CabeÃ§alho -->
        <div class="flex items-start justify-between mb-6">
            <div>
                <h3 id="modalTitle" class="text-xl font-bold text-gray-900 mb-1"></h3>
                <p id="modalCompany" class="text-[#58A1D4] font-medium"></p>
            </div>
            <button onclick="closeDetails()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <!-- Infos rÃ¡pidas -->
        <div class="grid grid-cols-2 gap-3 mb-6">
            <div class="p-3 bg-gray-50 rounded-xl text-sm">
                <p class="text-gray-400 text-xs mb-1">LocalizaÃ§Ã£o</p>
                <p id="modalLocation" class="font-medium text-gray-700"></p>
            </div>
            <div class="p-3 bg-gray-50 rounded-xl text-sm">
                <p class="text-gray-400 text-xs mb-1">Modalidade</p>
                <p id="modalType" class="font-medium text-gray-700"></p>
            </div>
            <div class="p-3 bg-gray-50 rounded-xl text-sm">
                <p class="text-gray-400 text-xs mb-1">Carga HorÃ¡ria</p>
                <p id="modalHours" class="font-medium text-gray-700"></p>
            </div>
            <div class="p-3 bg-green-50 rounded-xl text-sm">
                <p class="text-gray-400 text-xs mb-1">Bolsa AuxÃ­lio</p>
                <p id="modalSalary" class="font-bold text-[#27AE60]"></p>
            </div>
        </div>

        <!-- DescriÃ§Ã£o -->
        <div class="mb-4">
            <h4 class="text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide">DescriÃ§Ã£o</h4>
            <p id="modalDescription" class="text-sm text-gray-600 leading-relaxed bg-gray-50 p-3 rounded-xl"></p>
        </div>

        <!-- Requisitos -->
        <div class="mb-4">
            <h4 class="text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide">Requisitos</h4>
            <p id="modalRequirements" class="text-sm text-gray-600 leading-relaxed bg-gray-50 p-3 rounded-xl"></p>
        </div>

        <!-- BenefÃ­cios -->
        <div class="mb-6">
            <h4 class="text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide">BenefÃ­cios</h4>
            <p id="modalBenefits" class="text-sm text-gray-600 leading-relaxed bg-gray-50 p-3 rounded-xl"></p>
        </div>

        <!-- BotÃ£o Candidatar -->
        <a id="modalApplyBtn"
           href="#"
           target="_blank"
           class="flex items-center justify-center gap-2 w-full py-3 bg-[#58A1D4] hover:bg-[#4A8FBA] text-white font-semibold rounded-2xl transition-all text-sm mb-3">
            <i class="fas fa-paper-plane"></i> Candidatar-se Agora
        </a>

        <!-- Fechar -->
        <button onclick="closeDetails()"
                class="w-full py-3 rounded-2xl border-2 border-gray-200 text-gray-600 font-semibold hover:bg-gray-50 transition-all text-sm">
            Fechar
        </button>
    </div>
</div>

<script>
    const allJobsData = <?php echo json_encode(array_values($allJobs)); ?>;

    function toggleFilters() {
        document.getElementById('filtersSection').classList.toggle('hidden');
    }

    // =============================
    // Modal Detalhes
    // =============================
    function showDetails(jobId) {
        const job = allJobsData.find(j => j.id == jobId);
        if (!job) return;

        document.getElementById('modalTitle').textContent        = job.title;
        document.getElementById('modalCompany').textContent      = job.company;
        document.getElementById('modalLocation').textContent     = job.location;
        document.getElementById('modalType').textContent         = job.type;
        document.getElementById('modalHours').textContent        = job.hours;
        document.getElementById('modalSalary').textContent       = job.salary;
        document.getElementById('modalDescription').textContent  = job.description || 'NÃ£o informado';
        document.getElementById('modalRequirements').textContent = job.requirements || 'NÃ£o informado';
        document.getElementById('modalBenefits').textContent     = job.benefits || 'NÃ£o informado';

        // BotÃ£o candidatar â€” usa o link de candidatura cadastrado pelo admin
        const applyBtn = document.getElementById('modalApplyBtn');
        if (job.applicationLink) {
            applyBtn.href = job.applicationLink;
            applyBtn.classList.remove('hidden');
        } else {
            applyBtn.classList.add('hidden');
        }

        const modal = document.getElementById('detailsModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeDetails() {
        const modal = document.getElementById('detailsModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    // Fecha com ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeDetails();
    });
</script>

<?php include 'includes/footer.php'; ?>