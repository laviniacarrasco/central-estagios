<?php
require_once 'includes/config.php';
checkAuth();

$pageTitle = 'Olá, Lavínia Carrasco! 👋';

$posts = loadData('platform_posts');
if (!$posts) {
    $posts = [];
    saveData('platform_posts', $posts);
}

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-7xl mx-auto">
        
        <!-- Banner de Boas-vindas -->
        <div class="bg-gradient-to-r from-[#4A9FCA] to-[#2B7FA6] text-white p-10 rounded-[24px] mb-12 relative overflow-hidden">
            <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div>
                    <h2 class="text-3xl font-bold mb-2">
                        Bem-vindo, <?php echo $_SESSION['user_name'] ?? 'Lavínia Carrasco'; ?>! 👋
                    </h2>
                    <p class="text-white/90 text-lg">Confira as novidades e oportunidades da Central de Carreiras</p>
                </div>
                <a href="https://www.fsa.br" target="_blank" class="inline-flex items-center gap-2 bg-white text-[#2B7FA6] px-8 py-4 rounded-xl font-bold text-lg hover:bg-gray-100 transition-all whitespace-nowrap">
                    <i class="fas fa-graduation-cap"></i> Ir para Portal do Aluno
                </a>
            </div>
            <div class="absolute right-0 top-0 w-40 h-full bg-white/10 rounded-l-full"></div>
        </div>

        <?php if (!empty($posts)): ?>
    <div class="relative mb-12">
        <?php foreach (array_slice($posts, 0, 1) as $post): ?>
            <div class="bg-white rounded-[24px] shadow-sm border border-gray-100 overflow-hidden">
                
<?php if (!empty($post['images'])): ?>
    <div class="w-full relative" style="height: 260px; overflow: hidden; background: #000;">
        <img 
            src="<?php echo htmlspecialchars($post['images'][0]); ?>" 
            alt="<?php echo htmlspecialchars($post['title']); ?>"
            style="
                width: 100%;
                height: 100%;
                object-fit: cover;
                object-position: center;
                display: block;
                image-rendering: high-quality;
                image-rendering: -webkit-optimize-contrast;
                -ms-interpolation-mode: bicubic;
                filter: contrast(1.08) saturate(1.15) sharpen(1);
                transform: translateZ(0);
                will-change: transform;
                backface-visibility: hidden;
                -webkit-backface-visibility: hidden;
            "
            onerror="this.parentElement.style.display='none'"
        >
    </div>
<?php endif; ?>


                <div class="p-10">
                    <span class="px-3 py-1 bg-[#58A1D4] text-white rounded-full text-xs font-bold uppercase mb-4 inline-block">Destaque</span>
                    <p class="text-xs text-gray-400 mb-2">Publicado em <?php echo $post['createdAt']; ?></p>
                    <h3 class="text-3xl font-bold text-slate-900 mb-4"><?php echo htmlspecialchars($post['title']); ?></h3>
                    <p class="text-slate-600 text-lg mb-6"><?php echo htmlspecialchars(substr($post['content'], 0, 200)); ?></p>
                    <?php if (!empty($post['hashtags'])): ?>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($post['hashtags'] as $tag): ?>
                                <span class="text-[#58A1D4] font-semibold text-sm">#<?php echo htmlspecialchars(trim($tag)); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

        <!-- Stats Grid - Fundo branco, números coloridos -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
            <!-- Candidaturas -->
            <div class="bg-white p-8 rounded-[24px] shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-sm text-slate-400 uppercase tracking-widest font-bold">Candidaturas Ativas</h4>
                    <i class="fas fa-clipboard-list text-[#9B59B6] text-xl"></i>
                </div>
                <p class="text-5xl font-bold text-[#9B59B6]">03</p>
                <div class="mt-6 h-2 bg-gray-100 rounded-full">
                    <div class="h-full bg-[#9B59B6] rounded-full w-3/5"></div>
                </div>
            </div>

            <!-- Vagas -->
            <div class="bg-white p-8 rounded-[24px] shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-sm text-slate-400 uppercase tracking-widest font-bold">Vagas do Perfil</h4>
                    <i class="fas fa-briefcase text-[#F39C12] text-xl"></i>
                </div>
                <p class="text-5xl font-bold text-[#F39C12]">12</p>
                <div class="mt-6 h-2 bg-gray-100 rounded-full">
                    <div class="h-full bg-[#F39C12] rounded-full w-4/5"></div>
                </div>
            </div>

            <!-- Perfil -->
            <div class="bg-white p-8 rounded-[24px] shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-sm text-slate-400 uppercase tracking-widest font-bold">Perfil Completo</h4>
                    <i class="fas fa-user-check text-[#27AE60] text-xl"></i>
                </div>
                <p class="text-5xl font-bold text-[#27AE60]">85%</p>
                <div class="mt-6 h-2 bg-gray-100 rounded-full">
                    <div class="h-full bg-[#27AE60] rounded-full w-[85%]"></div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Grid - Fundo colorido -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Currículo - Roxo -->
            <a href="curriculum.php" class="bg-gradient-to-br from-[#9B59B6] to-[#8E44AD] text-white p-8 rounded-[24px] hover:shadow-xl transition-all hover:-translate-y-1">
                <i class="fas fa-file-alt text-2xl mb-4 block"></i>
                <h3 class="text-xl font-bold mb-2">Currículo</h3>
                <p class="text-white/90 text-sm mb-6">Mantenha seus dados prontos</p>
                <span class="inline-block w-full bg-white/20 border border-white/30 py-3 rounded-xl text-center font-semibold hover:bg-white/30 transition-all">Atualizar Perfil</span>
            </a>

            <!-- Vagas - Laranja -->
            <a href="jobs.php" class="bg-gradient-to-br from-[#F39C12] to-[#E67E22] text-white p-8 rounded-[24px] hover:shadow-xl transition-all hover:-translate-y-1">
                <i class="fas fa-briefcase text-2xl mb-4 block"></i>
                <h3 class="text-xl font-bold mb-2">Vagas</h3>
                <p class="text-white/90 text-sm mb-6">Busque seu estágio ideal</p>
                <span class="inline-block w-full bg-white/20 border border-white/30 py-3 rounded-xl text-center font-semibold hover:bg-white/30 transition-all">Ver Oportunidades</span>
            </a>

            <!-- Certificados - Verde -->
            <a href="documents.php" class="bg-gradient-to-br from-[#27AE60] to-[#229954] text-white p-8 rounded-[24px] hover:shadow-xl transition-all hover:-translate-y-1">
                <i class="fas fa-award text-2xl mb-4 block"></i>
                <h3 class="text-xl font-bold mb-2">Certificados</h3>
                <p class="text-white/90 text-sm mb-6">Envie suas horas complementares</p>
                <span class="inline-block w-full bg-white/20 border border-white/30 py-3 rounded-xl text-center font-semibold hover:bg-white/30 transition-all">Acessar Documentos</span>
            </a>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>