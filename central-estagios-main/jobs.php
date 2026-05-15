<?php
require_once 'includes/config.php';
checkAuth();

$pageTitle = 'Vagas de Estágio';

// ================================================
// CARREGAR VAGAS DO JSON (mesmo arquivo do admin)
// Filtra apenas vagas com status 'Ativa'
// ================================================
$allJobsRaw = loadData('platform_jobs');

$allJobs = array_values(array_filter($allJobsRaw, function($job) {
    return ($job['status'] ?? '') === 'Ativa';
}));

// Cursos da FSA
$fsaCourses = [
    "Ciência de Dados e Inteligência Artificial",
    "Análise e Desenvolvimento de Sistemas",
    "Engenharia Mecânica",
    "Engenharia Elétrica",
    "Engenharia de Produção",
    "Engenharia Civil",
    "Administração",
    "Psicologia",
    "Arquitetura e Urbanismo",
    "Ciências Biológicas",
    "Direito",
    "Pedagogia",
    "Ciência da Computação",
    "Sistemas de Informação"
];

// Filtros via GET
$searchTerm      = trim($_GET['search'] ?? '');
$selectedCourses = (array)($_GET['courses'] ?? []);

// Normaliza texto para busca sem acento
function normalize(string $str): string {
    $str = mb_strtolower($str, 'UTF-8');
    $str = preg_replace('/[áàãâä]/u', 'a', $str);
    $str = preg_replace('/[éèêë]/u',  'e', $str);
    $str = preg_replace('/[íìîï]/u',  'i', $str);
    $str = preg_replace('/[óòõôö]/u', 'o', $str);
    $str = preg_replace('/[úùûü]/u',  'u', $str);
    $str = preg_replace('/[ç]/u',     'c', $str);
    return $str;
}

// Aplicar filtros
$filteredJobs = array_values(array_filter($allJobs, function($job) use ($searchTerm, $selectedCourses) {

    // Filtro por texto
    if ($searchTerm) {
        $norm = normalize($searchTerm);
        $found = (
            strpos(normalize($job['title']       ?? ''), $norm) !== false ||
            strpos(normalize($job['company']     ?? ''), $norm) !== false ||
            strpos(normalize($job['description'] ?? ''), $norm) !== false ||
            strpos(normalize($job['location']    ?? ''), $norm) !== false
        );
        if (!$found) return false;
    }

    // Filtro por curso
    if (!empty($selectedCourses)) {
        $jobCourses = normalize($job['courses'] ?? '');
        $matched = false;
        foreach ($selectedCourses as $course) {
            if (strpos($jobCourses, normalize($course)) !== false) {
                $matched = true;
                break;
            }
        }
        if (!$matched) return false;
    }

    return true;
}));

// Separar destacadas e regulares
$featuredJobs = array_values(array_filter($filteredJobs, fn($j) => ($j['featured'] ?? false) === true));
$regularJobs  = array_values(array_filter($filteredJobs, fn($j) => ($j['featured'] ?? false) !== true));

// Calcular vagas novas nesta semana
$newThisWeek = count(array_filter($allJobs, function($job) {
    if (empty($job['createdAt'])) return false;
    $parts = explode('/', $job['createdAt']);
    if (count($parts) !== 3) return false;
    $jobDate = mktime(0, 0, 0, (int)$parts[1], (int)$parts[0], (int)$parts[2]);
    return $jobDate >= strtotime('-7 days');
}));

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-7xl mx-auto">

        <!-- Header -->
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-slate-900 mb-2">Vagas de Estágio</h2>
            <p class="text-slate-500 font-medium">Encontre oportunidades que combinam com seu perfil</p>
        </div>

        <!-- Busca e Filtros -->
        <div class="flex gap-4 mb-6">
            <form method="GET" action="jobs.php" class="flex-1 flex gap-4">
                <div class="flex-1 relative">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input
                        type="text"
                        name="search"
                        placeholder="Buscar por vaga, empresa, localização..."
                        value="<?php echo htmlspecialchars($searchTerm); ?>"
                        class="w-full h-14 pl-12 pr-4 bg-white border border-gray-200 rounded-xl text-base focus:ring-2 focus:ring-[#4A9FCA] focus:outline-none shadow-sm"
                    />
                </div>
                <button type="button" onclick="toggleFilters()"
                        class="h-14 px-6 bg-white border border-gray-200 rounded-xl font-semibold text-slate-700 hover:bg-gray-50 flex items-center gap-2 shadow-sm">
                    <i class="fas fa-filter text-[#4A9FCA]"></i> Filtros
                    <?php if (!empty($selectedCourses)): ?>
                        <span class="w-5 h-5 bg-[#4A9FCA] text-white text-xs rounded-full flex items-center justify-center">
                            <?php echo count($selectedCourses); ?>
                        </span>
                    <?php endif; ?>
                </button>
                <button type="submit"
                        class="h-14 px-6 bg-[#4A9FCA] text-white rounded-xl font-semibold hover:bg-[#3A8FB0] transition-colors shadow-sm">
                    Buscar
                </button>
            </form>
        </div>

        <!-- Painel de Filtros por Curso -->
        <div id="filtersSection" class="mb-6 p-6 bg-white rounded-2xl border border-gray-100 shadow-sm <?php echo !empty($selectedCourses) ? '' : 'hidden'; ?>">
            <div class="flex justify-between items-center mb-4">
                <h4 class="font-bold text-slate-800 flex items-center gap-2">
                    <i class="fas fa-graduation-cap text-[#4A9FCA]"></i> Filtrar por Curso
                </h4>
                <a href="jobs.php<?php echo $searchTerm ? '?search=' . urlencode($searchTerm) : ''; ?>"
                   class="text-red-400 text-sm hover:text-red-600 flex items-center gap-1">
                    <i class="fas fa-times"></i> Limpar Filtros
                </a>
            </div>
            <form method="GET" action="jobs.php">
                <?php if ($searchTerm): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>">
                <?php endif; ?>
                <select name="courses[]" class="w-full h-12 px-4 rounded-xl border border-gray-200 bg-gray-50 mb-4 focus:ring-2 focus:ring-[#4A9FCA] focus:outline-none" onchange="this.form.submit()">
                    <option value="">Selecione seu curso...</option>
                    <?php foreach ($fsaCourses as $course): ?>
                        <option value="<?php echo htmlspecialchars($course); ?>"
                                <?php echo in_array($course, $selectedCourses) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($course); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php if (!empty($selectedCourses)): ?>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($selectedCourses as $course): ?>
                        <span class="flex items-center gap-2 px-4 py-2 bg-[#4A9FCA] text-white rounded-xl text-sm font-medium">
                            <i class="fas fa-graduation-cap text-xs"></i>
                            <?php echo htmlspecialchars($course); ?>
                            <a href="jobs.php<?php echo $searchTerm ? '?search=' . urlencode($searchTerm) : ''; ?>"
                               class="hover:text-red-200 ml-1">✕</a>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Cards de Estatísticas -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="p-6 bg-[#4A9FCA] text-white rounded-2xl relative overflow-hidden h-32 shadow-sm">
                <p class="text-white/80 text-sm font-medium mb-1">Total de Vagas</p>
                <p class="text-5xl font-bold"><?php echo count($filteredJobs); ?></p>
                <i class="fas fa-briefcase absolute -right-4 -bottom-4 text-7xl text-white/10 rotate-12"></i>
            </div>
            <div class="p-6 bg-[#E35444] text-white rounded-2xl relative overflow-hidden h-32 shadow-sm">
                <p class="text-white/80 text-sm font-medium mb-1">Vagas em Destaque</p>
                <p class="text-5xl font-bold"><?php echo count($featuredJobs); ?></p>
                <i class="fas fa-star absolute -right-4 -bottom-4 text-7xl text-white/10 rotate-12"></i>
            </div>
            <div class="p-6 bg-[#27AE60] text-white rounded-2xl relative overflow-hidden h-32 shadow-sm">
                <p class="text-white/80 text-sm font-medium mb-1">Novas Esta Semana</p>
                <p class="text-5xl font-bold"><?php echo $newThisWeek; ?></p>
                <i class="fas fa-chart-line absolute -right-4 -bottom-4 text-7xl text-white/10 rotate-12"></i>
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
                        <div class="p-6 border-2 border-yellow-200 bg-yellow-50/20 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                            <div class="flex items-start gap-6">
                                <div class="w-16 h-16 bg-[#4A9FCA] rounded-xl flex items-center justify-center flex-shrink-0 shadow-sm">
                                    <i class="fas fa-building text-2xl text-white"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="flex justify-between items-start mb-3">
                                        <div>
                                            <h3 class="text-xl font-bold text-slate-900">
                                                <?php echo htmlspecialchars($job['title']); ?>
                                                <i class="fas fa-star text-yellow-400 text-sm ml-1"></i>
                                            </h3>
                                            <p class="text-slate-600 font-semibold"><?php echo htmlspecialchars($job['company']); ?></p>
                                        </div>
                                        <span class="px-3 py-1 bg-[#4A9FCA] text-white rounded-full text-sm font-bold flex-shrink-0">
                                            <?php echo htmlspecialchars($job['type']); ?>
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5 text-sm">
                                        <div class="flex items-center gap-2 text-slate-500">
                                            <i class="fas fa-map-marker-alt text-[#E35444]"></i>
                                            <?php echo htmlspecialchars($job['location']); ?>
                                        </div>
                                        <div class="flex items-center gap-2 text-slate-500">
                                            <i class="fas fa-clock text-[#4A9FCA]"></i>
                                            <?php echo htmlspecialchars($job['hours'] ?? 'A combinar'); ?>
                                        </div>
                                        <div class="flex items-center gap-2 font-bold text-[#27AE60]">
                                            <i class="fas fa-dollar-sign"></i>
                                            <?php echo htmlspecialchars($job['salary'] ?: 'A combinar'); ?>
                                        </div>
                                        <div class="flex items-center gap-1 text-slate-400 text-xs">
                                            <i class="fas fa-calendar-alt"></i>
                                            <?php echo htmlspecialchars($job['createdAt'] ?? 'Recente'); ?>
                                        </div>
                                    </div>
                                    <div class="flex gap-3">
                                        <button onclick="showDetails(<?php echo (int)$job['id']; ?>)"
                                                class="px-5 py-2 border border-gray-200 text-gray-600 rounded-xl hover:bg-gray-50 transition-all text-sm flex items-center gap-1">
                                            <i class="fas fa-eye"></i> Ver Detalhes
                                        </button>
                                        <?php if (!empty($job['applicationLink'])): ?>
                                            <a href="<?php echo htmlspecialchars($job['applicationLink']); ?>"
                                               target="_blank"
                                               class="px-5 py-2 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl transition-all text-sm flex items-center gap-1">
                                                <i class="fas fa-paper-plane"></i> Candidatar-se
                                            </a>
                                        <?php else: ?>
                                            <button onclick="showDetails(<?php echo (int)$job['id']; ?>)"
                                                    class="px-5 py-2 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl transition-all text-sm flex items-center gap-1">
                                                <i class="fas fa-paper-plane"></i> Ver Detalhes
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Todas as Vagas (Regulares) -->
        <?php if (!empty($regularJobs)): ?>
            <section>
                <h3 class="text-xl font-bold text-slate-900 mb-4">
                    <?php echo !empty($featuredJobs) ? 'Outras Vagas' : 'Todas as Vagas'; ?>
                </h3>
                <div class="space-y-4">
                    <?php foreach ($regularJobs as $job): ?>
                        <div class="p-6 border border-gray-100 bg-white rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                            <div class="flex items-start gap-5">
                                <div class="w-14 h-14 bg-slate-50 border border-gray-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-building text-slate-400 text-xl"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <h3 class="text-lg font-bold text-slate-900"><?php echo htmlspecialchars($job['title']); ?></h3>
                                            <p class="text-slate-500 font-medium"><?php echo htmlspecialchars($job['company']); ?></p>
                                        </div>
                                        <span class="px-3 py-1 border border-slate-200 text-slate-500 rounded-full text-sm font-medium flex-shrink-0">
                                            <?php echo htmlspecialchars($job['type']); ?>
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4 text-sm">
                                        <div class="flex items-center gap-2 text-slate-500">
                                            <i class="fas fa-map-marker-alt text-[#E35444]"></i>
                                            <?php echo htmlspecialchars($job['location']); ?>
                                        </div>
                                        <div class="flex items-center gap-2 text-slate-500">
                                            <i class="fas fa-clock text-[#4A9FCA]"></i>
                                            <?php echo htmlspecialchars($job['hours'] ?? 'A combinar'); ?>
                                        </div>
                                        <div class="flex items-center gap-2 font-bold text-[#27AE60]">
                                            <i class="fas fa-dollar-sign"></i>
                                            <?php echo htmlspecialchars($job['salary'] ?: 'A combinar'); ?>
                                        </div>
                                        <div class="flex items-center gap-1 text-slate-400 text-xs">
                                            <i class="fas fa-calendar-alt"></i>
                                            <?php echo htmlspecialchars($job['createdAt'] ?? 'Recente'); ?>
                                        </div>
                                    </div>
                                    <div class="flex gap-3">
                                        <button onclick="showDetails(<?php echo (int)$job['id']; ?>)"
                                                class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 transition-all text-sm flex items-center gap-1">
                                            <i class="fas fa-eye"></i> Ver Detalhes
                                        </button>
                                        <?php if (!empty($job['applicationLink'])): ?>
                                            <a href="<?php echo htmlspecialchars($job['applicationLink']); ?>"
                                               target="_blank"
                                               class="px-4 py-2 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-lg transition-all text-sm flex items-center gap-1">
                                                <i class="fas fa-paper-plane"></i> Candidatar-se
                                            </a>
                                        <?php else: ?>
                                            <button onclick="showDetails(<?php echo (int)$job['id']; ?>)"
                                                    class="px-4 py-2 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-lg transition-all text-sm flex items-center gap-1">
                                                <i class="fas fa-paper-plane"></i> Ver Detalhes
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Estado Vazio -->
        <?php if (empty($filteredJobs)): ?>
            <div class="p-16 text-center border-2 border-dashed border-gray-200 rounded-3xl">
                <i class="fas fa-briefcase text-6xl text-gray-200 mb-4"></i>
                <h3 class="text-xl font-bold text-slate-900 mb-2">Nenhuma vaga encontrada</h3>
                <p class="text-slate-400 mb-4">
                    <?php if ($searchTerm || !empty($selectedCourses)): ?>
                        Tente ajustar seus filtros ou termos de busca.
                    <?php else: ?>
                        Ainda não há vagas cadastradas. Fique de olho!
                    <?php endif; ?>
                </p>
                <?php if ($searchTerm || !empty($selectedCourses)): ?>
                    <a href="jobs.php" class="px-6 py-2 bg-[#4A9FCA] text-white rounded-lg text-sm">
                        Ver todas as vagas
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</main>

<!-- ============================================ -->
<!-- MODAL: DETALHES DA VAGA                    -->
<!-- ============================================ -->
<div id="detailsModal" class="fixed inset-0 z-[999] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeDetails()"></div>
    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-lg p-8 z-10 max-h-[90vh] overflow-y-auto">

        <!-- Cabeçalho -->
        <div class="flex items-start justify-between mb-6">
            <div class="flex-1">
                <div class="flex items-center gap-2 mb-1">
                    <h3 id="modalTitle" class="text-xl font-bold text-gray-900"></h3>
                    <span id="modalFeaturedBadge" class="hidden text-yellow-400 text-sm">⭐</span>
                </div>
                <p id="modalCompany" class="text-[#4A9FCA] font-semibold"></p>
            </div>
            <button onclick="closeDetails()" class="text-gray-300 hover:text-gray-600 transition-colors ml-4">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <!-- Badge de tipo -->
        <div class="flex gap-2 mb-6">
            <span id="modalType" class="px-3 py-1 bg-[#4A9FCA] text-white rounded-full text-sm font-bold"></span>
            <span id="modalStatus" class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-bold">Ativa</span>
        </div>

        <!-- Infos rápidas -->
        <div class="grid grid-cols-2 gap-3 mb-6">
            <div class="p-3 bg-gray-50 rounded-xl">
                <p class="text-xs text-gray-400 mb-0.5">Localização</p>
                <p id="modalLocation" class="text-sm font-semibold text-gray-700"></p>
            </div>
            <div class="p-3 bg-gray-50 rounded-xl">
                <p class="text-xs text-gray-400 mb-0.5">Carga Horária</p>
                <p id="modalHours" class="text-sm font-semibold text-gray-700"></p>
            </div>
            <div class="p-3 bg-green-50 rounded-xl col-span-2">
                <p class="text-xs text-gray-400 mb-0.5">Bolsa Auxílio</p>
                <p id="modalSalary" class="text-base font-bold text-[#27AE60]"></p>
            </div>
        </div>

        <!-- Descrição -->
        <div class="mb-4">
            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Sobre a Vaga</h4>
            <p id="modalDescription" class="text-sm text-gray-600 leading-relaxed bg-gray-50 p-4 rounded-xl"></p>
        </div>

        <!-- Requisitos -->
        <div class="mb-4">
            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Requisitos</h4>
            <p id="modalRequirements" class="text-sm text-gray-600 leading-relaxed bg-gray-50 p-4 rounded-xl"></p>
        </div>

        <!-- Benefícios -->
        <div class="mb-6">
            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Benefícios</h4>
            <p id="modalBenefits" class="text-sm text-gray-600 leading-relaxed bg-gray-50 p-4 rounded-xl"></p>
        </div>

        <!-- Data de publicação -->
        <p id="modalCreatedAt" class="text-xs text-gray-300 text-center mb-4"></p>

        <!-- Botão Candidatar -->
        <a id="modalApplyBtn"
           href="#"
           target="_blank"
           class="flex items-center justify-center gap-2 w-full py-3 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white font-bold rounded-2xl transition-all text-sm mb-3">
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
    // ================================================
    // Dados das vagas injetados pelo PHP (JSON seguro)
    // ================================================
    const allJobsData = <?php echo json_encode(array_values($allJobs), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;

    // ================================================
    // Toggle painel de filtros
    // ================================================
    function toggleFilters() {
        document.getElementById('filtersSection').classList.toggle('hidden');
    }

    // ================================================
    // Modal Detalhes
    // ================================================
    function showDetails(jobId) {
        const job = allJobsData.find(j => j.id == jobId);
        if (!job) return;

        document.getElementById('modalTitle').textContent        = job.title        || '';
        document.getElementById('modalCompany').textContent      = job.company      || '';
        document.getElementById('modalType').textContent         = job.type         || '';
        document.getElementById('modalLocation').textContent     = job.location     || 'Não informado';
        document.getElementById('modalHours').textContent        = job.hours        || 'A combinar';
        document.getElementById('modalSalary').textContent       = job.salary       || 'A combinar';
        document.getElementById('modalDescription').textContent  = job.description  || 'Não informado';
        document.getElementById('modalRequirements').textContent = job.requirements || 'Não informado';
        document.getElementById('modalBenefits').textContent     = job.benefits     || 'Não informado';
        document.getElementById('modalCreatedAt').textContent    = job.createdAt ? `Publicada em ${job.createdAt}` : '';

        // Badge destaque
        const featuredBadge = document.getElementById('modalFeaturedBadge');
        job.featured ? featuredBadge.classList.remove('hidden') : featuredBadge.classList.add('hidden');

        // Botão candidatura
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

    // Fechar com ESC
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeDetails();
    });
</script>

<?php include 'includes/footer.php'; ?>
