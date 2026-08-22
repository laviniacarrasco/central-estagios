<?php
require_once 'includes/config.php';
checkAuth();

$userName = $_SESSION['user_name'] ?? 'Usuário';
$pageTitle = "Olá, {$userName}!";

// Posts globais (Central de Estágios) — fixo, gerenciado só pelo admin em posts.php
$posts = loadData('platform_posts');

// Dados do usuário logado
$curriculo     = loadData('userCurriculum', true);
$certificados  = loadData('userCertificates', true);
$vagas         = loadData('platform_jobs');

// --- Cálculo real: Vagas ativas na plataforma ---
$vagasAtivas = array_filter($vagas, fn($v) => ($v['status'] ?? '') === 'Ativa');
$totalVagasAtivas = count($vagasAtivas);

// --- Cálculo real: Certificados enviados ---
$totalCertificados = count($certificados);

// --- Cálculo real: % de perfil completo ---
function calcularPerfilCompleto($curriculo) {
    if (empty($curriculo)) return 0;

    $campos = ['nome', 'email', 'telefone', 'cidade', 'resumo', 'habilidades', 'experiencias', 'formacoes'];
    $preenchidos = 0;

    foreach ($campos as $campo) {
        if (!empty($curriculo[$campo])) {
            $preenchidos++;
        }
    }

    return round(($preenchidos / count($campos)) * 100);
}

$percentualPerfil = calcularPerfilCompleto($curriculo);

// --- Carrossel: sempre os 3 posts mais recentes (posts.php usa array_unshift, então o mais novo já fica no início) ---
$postsCarrossel = array_slice($posts, 0, 3);
$totalSlides = count($postsCarrossel);

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-7xl mx-auto">

        <!-- Banner de Boas-vindas -->
        <div class="bg-gradient-to-r from-[#4A9FCA] to-[#2B7FA6] text-white p-10 rounded-[24px] mb-12 relative overflow-hidden">
            <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div>
                    <h2 class="text-3xl font-bold mb-2">
                        Bem-vindo, <?php echo htmlspecialchars($userName); ?>!
                    </h2>
                    <p class="text-white/90 text-lg">Confira as novidades e oportunidades da Central de Carreiras</p>
                </div>
                <a href="https://www.fsa.br" target="_blank" class="inline-flex items-center gap-2 bg-white text-[#2B7FA6] px-8 py-4 rounded-xl font-bold text-lg hover:bg-gray-100 transition-all whitespace-nowrap">
                    <i class="fas fa-graduation-cap"></i> Ir para Portal do Aluno
                </a>
            </div>
            <div class="absolute right-0 top-0 w-40 h-full bg-white/10 rounded-l-full"></div>
        </div>

        <!-- Carrossel de Posts (máx. 3 mais recentes) -->
        <?php if (!empty($postsCarrossel)): ?>
        <div id="postCarouselWrapper" class="relative mb-12 group">

            <div id="postCarousel" class="overflow-hidden rounded-[24px]">
                <div id="postCarouselTrack" class="flex transition-transform duration-500 ease-in-out">
                    <?php foreach ($postsCarrossel as $post): ?>
                        <div class="w-full flex-shrink-0">
                            <div class="bg-white rounded-[24px] shadow-sm border border-gray-100 overflow-hidden">

                                <?php if (!empty($post['images'])): ?>
                                    <div class="w-full relative" style="height: 260px; overflow: hidden; background: #000;">
                                        <img
                                            src="<?php echo htmlspecialchars($post['images'][0]); ?>"
                                            alt="<?php echo htmlspecialchars($post['title']); ?>"
                                            style="width: 100%; height: 100%; object-fit: cover; object-position: center; display: block;"
                                            onerror="this.parentElement.style.display='none'"
                                        >
                                    </div>
                                <?php endif; ?>

                                <div class="p-10">
                                    <span class="px-3 py-1 bg-[#58A1D4] text-white rounded-full text-xs font-bold uppercase mb-4 inline-block">Destaque</span>
                                    <p class="text-xs text-gray-400 mb-2">Publicado em <?php echo htmlspecialchars($post['createdAt']); ?></p>
                                    <h3 class="text-3xl font-bold text-slate-900 mb-4"><?php echo htmlspecialchars($post['title']); ?></h3>
                                    <p class="text-slate-600 text-lg mb-6"><?php echo htmlspecialchars(substr($post['content'], 0, 200)); ?></p>
                                    <?php if (!empty($post['hashtags'])): ?>
                                        <div class="flex flex-wrap gap-2">
                                            <?php foreach ($post['hashtags'] as $tag): ?>
                                                <span class="text-[#58A1D4] font-semibold text-sm">#<?php echo htmlspecialchars(trim($tag, '# ')); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($totalSlides > 1): ?>
                <!-- Setas de navegação — visíveis apenas ao passar o mouse sobre o carrossel -->
                <button onclick="moverCarrossel(-1)"
                    class="absolute left-2 top-1/2 -translate-y-1/2 w-10 h-10 bg-white/90 hover:bg-white shadow-md rounded-full flex items-center justify-center text-[#2B7FA6] transition-all z-10 opacity-0 group-hover:opacity-100">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button onclick="moverCarrossel(1)"
                    class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-10 bg-white/90 hover:bg-white shadow-md rounded-full flex items-center justify-center text-[#2B7FA6] transition-all z-10 opacity-0 group-hover:opacity-100">
                    <i class="fas fa-chevron-right"></i>
                </button>

                <!-- Bolinhas indicadoras -->
                <div class="flex justify-center gap-2 mt-4">
                    <?php for ($i = 0; $i < $totalSlides; $i++): ?>
                        <button onclick="irParaSlide(<?php echo $i; ?>)" data-dot="<?php echo $i; ?>" class="w-2.5 h-2.5 rounded-full bg-gray-300 transition-all"></button>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

        </div>
        <?php endif; ?>

        <!-- Stats Grid - dados reais, sem candidaturas -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">

            <!-- Vagas Ativas -->
            <div class="bg-white p-8 rounded-[24px] shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-sm text-slate-400 uppercase tracking-widest font-bold">Vagas Disponí­veis</h4>
                    <i class="fas fa-briefcase text-[#F39C12] text-xl"></i>
                </div>
                <p class="text-5xl font-bold text-[#F39C12]"><?php echo str_pad($totalVagasAtivas, 2, '0', STR_PAD_LEFT); ?></p>
                <div class="mt-6 h-2 bg-gray-100 rounded-full">
                    <div class="h-full bg-[#F39C12] rounded-full" style="width: <?php echo min($totalVagasAtivas * 10, 100); ?>%"></div>
                </div>
            </div>

            <!-- Certificados -->
            <div class="bg-white p-8 rounded-[24px] shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-sm text-slate-400 uppercase tracking-widest font-bold">Certificados Enviados</h4>
                    <i class="fas fa-award text-[#27AE60] text-xl"></i>
                </div>
                <p class="text-5xl font-bold text-[#27AE60]"><?php echo str_pad($totalCertificados, 2, '0', STR_PAD_LEFT); ?></p>
                <div class="mt-6 h-2 bg-gray-100 rounded-full">
                    <div class="h-full bg-[#27AE60] rounded-full" style="width: <?php echo min($totalCertificados * 20, 100); ?>%"></div>
                </div>
            </div>

            <!-- Perfil -->
            <div class="bg-white p-8 rounded-[24px] shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-sm text-slate-400 uppercase tracking-widest font-bold">Perfil Completo</h4>
                    <i class="fas fa-user-check text-[#9B59B6] text-xl"></i>
                </div>
                <p class="text-5xl font-bold text-[#9B59B6]"><?php echo $percentualPerfil; ?>%</p>
                <div class="mt-6 h-2 bg-gray-100 rounded-full">
                    <div class="h-full bg-[#9B59B6] rounded-full" style="width: <?php echo $percentualPerfil; ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
         
            <a href="jobs.php" class="bg-gradient-to-br from-[#F39C12] to-[#E67E22] text-white p-8 rounded-[24px] hover:shadow-xl transition-all hover:-translate-y-1">
                <i class="fas fa-briefcase text-2xl mb-4 block"></i>
                <h3 class="text-xl font-bold mb-2">Vagas</h3>
                <p class="text-white/90 text-sm mb-6">Busque seu estágio ideal</p>
                <span class="inline-block w-full bg-white/20 border border-white/30 py-3 rounded-xl text-center font-semibold hover:bg-white/30 transition-all">Ver Oportunidades</span>
            </a>

            <a href="documents.php" class="bg-gradient-to-br from-[#27AE60] to-[#229954] text-white p-8 rounded-[24px] hover:shadow-xl transition-all hover:-translate-y-1">
                <i class="fas fa-award text-2xl mb-4 block"></i>
                <h3 class="text-xl font-bold mb-2">Certificados</h3>
                <p class="text-white/90 text-sm mb-6">Envie suas horas complementares</p>
                <span class="inline-block w-full bg-white/20 border border-white/30 py-3 rounded-xl text-center font-semibold hover:bg-white/30 transition-all">Acessar Documentos</span>
            </a>

               <a href="curriculum.php" class="bg-gradient-to-br from-[#9B59B6] to-[#8E44AD] text-white p-8 rounded-[24px] hover:shadow-xl transition-all hover:-translate-y-1">
                <i class="fas fa-file-alt text-2xl mb-4 block"></i>
                <h3 class="text-xl font-bold mb-2">Currículo</h3>
                <p class="text-white/90 text-sm mb-6">Mantenha seus dados prontos</p>
                <span class="inline-block w-full bg-white/20 border border-white/30 py-3 rounded-xl text-center font-semibold hover:bg-white/30 transition-all">Atualizar Perfil</span>
            </a>
        </div>
    </div>
</main>

<script>
    // =============================
    // Carrossel de Posts — navegação manual, sem autoplay
    // =============================
    let slideAtual = 0;
    const totalSlides = <?php echo $totalSlides; ?>;

    function atualizarCarrossel() {
        const track = document.getElementById('postCarouselTrack');
        if (!track) return;
        track.style.transform = `translateX(-${slideAtual * 100}%)`;

        document.querySelectorAll('[data-dot]').forEach(dot => {
            const idx = parseInt(dot.dataset.dot);
            dot.classList.toggle('bg-[#2B7FA6]', idx === slideAtual);
            dot.classList.toggle('bg-gray-300', idx !== slideAtual);
        });
    }

    function moverCarrossel(direcao) {
        slideAtual = (slideAtual + direcao + totalSlides) % totalSlides;
        atualizarCarrossel();
    }

    function irParaSlide(indice) {
        slideAtual = indice;
        atualizarCarrossel();
    }

    if (totalSlides > 0) {
        atualizarCarrossel();
    }
</script>

<?php include 'includes/footer.php'; ?>