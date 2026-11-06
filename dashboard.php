<?php
/**
 * =============================================================================
 * INÍCIO
 * -----------------------------------------------------------------------------
 * Aluno: números, vagas que combinam (IA do Gemini quando já analisadas,
 * senão a comparação por regras), primeiros passos, o que fazer agora e avisos.
 * Professor e funcionários: números, avisos do mural e atalhos.
 * A porcentagem que aparece é a do CURRÍCULO (o perfil não tem porcentagem).
 * =============================================================================
 */
require_once 'includes/config.php';
require_once 'includes/vagas.php';
require_once 'includes/egressos.php';
require_once 'includes/melhorias.php';
checkAuth();

$userName  = $_SESSION['user_name'] ?? 'Usuário';
$primeiro  = explode(' ', trim($userName))[0];
$pageTitle = 'Início';
$ehAluno   = perfilPode('area_aluno');
$restrito  = perfilAtual() === 'aluno_restrito';

// Professores, egressos e coordenações têm um Início próprio
if (!$ehAluno) { require __DIR__ . '/includes/inicio-gestao.php'; return; }

$posts = loadData('platform_posts');
$postsCarrossel = array_slice($posts, 0, 3);
$totalSlides = count($postsCarrossel);
$totalVagasAtivas = count(array_filter(loadData('platform_jobs'), fn($v) => vagaVigente($v)));

$hora = (int) date('G');
$saudacao = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');
$meses  = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
$semana = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
$hoje   = $semana[(int) date('w')] . ', ' . date('j') . ' de ' . $meses[(int) date('n') - 1];

if ($ehAluno) {
    $painel = mhDashboardAcoes($restrito);
    $cvPct  = (int) $painel['pontuacao']['pct'];
    $nCands = count((array) loadData('userCandidaturas', true));
    $nCerts = count((array) loadData('userCertificates', true));
    $passos = $painel['passos'];
    $feitos = count(array_filter($passos, fn($p) => $p[1]));
    $rec    = ['fonte' => 'regras', 'lista' => $painel['rec']];
    if (perfilPode('ia_curriculo')) {
        try {
            require_once 'includes/ia-gemini.php';
            $rec = iaVagasParaTela(egressoCarregarUsuario((int) $_SESSION['user_id']) ?: [], 3);
        } catch (Throwable $e) { error_log('Início/IA: ' . $e->getMessage()); }
    }
    $corCv = $cvPct >= 80 ? '#27AE60' : ($cvPct >= 50 ? '#F39C12' : '#E35444');
}
$coresAcao = ['amber' => 'bg-amber-50 text-amber-600', 'red' => 'bg-red-50 text-red-600', 'green' => 'bg-emerald-50 text-emerald-600', 'blue' => 'bg-[#e8f3fa] text-[#2B7FA6]'];

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-7xl mx-auto animate-fade-in">

        <!-- Boas-vindas -->
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#4A9FCA] via-[#3A8FB0] to-[#2B7FA6] text-white p-8 sm:p-10 mb-8">
            <div class="absolute -right-16 -top-16 w-64 h-64 rounded-full bg-white/10"></div>
            <div class="absolute right-24 -bottom-20 w-48 h-48 rounded-full bg-white/10"></div>
            <div class="relative flex flex-col lg:flex-row lg:items-center gap-8">
                <div class="flex-1">
                    <p class="text-white/75 text-sm font-medium mb-1"><?= htmlspecialchars($hoje) ?></p>
                    <h2 class="text-3xl sm:text-4xl font-bold mb-2"><?= $saudacao ?>, <?= htmlspecialchars($primeiro) ?>!</h2>
                    <p class="text-white/90 text-lg"><?= $ehAluno ? 'Veja o que fazer hoje para chegar ao seu estágio.' : 'Confira as novidades da Central de Carreiras.' ?></p>
                    <div class="flex flex-wrap gap-3 mt-6">
                        <a href="jobs.php" class="inline-flex items-center gap-2 bg-white text-[#2B7FA6] px-5 py-3 rounded-xl font-bold hover:bg-blue-50"><i class="fas fa-briefcase"></i> Ver vagas</a>
                        <a href="https://www.fsa.br" target="_blank" rel="noopener" class="inline-flex items-center gap-2 bg-white/15 border border-white/30 px-5 py-3 rounded-xl font-semibold hover:bg-white/25"><i class="fas fa-graduation-cap"></i> Portal do Aluno</a>
                    </div>
                </div>
                <?php if ($ehAluno): ?>
                <a href="curriculum.php" class="flex items-center gap-5 bg-white/10 border border-white/20 rounded-2xl p-5 hover:bg-white/15 lg:w-80">
                    <div class="relative w-20 h-20 flex-shrink-0">
                        <svg viewBox="0 0 36 36" class="w-20 h-20 -rotate-90">
                            <circle cx="18" cy="18" r="15.9" fill="none" stroke="rgba(255,255,255,.25)" stroke-width="3.5"></circle>
                            <circle cx="18" cy="18" r="15.9" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-dasharray="<?= $cvPct ?> 100"></circle>
                        </svg>
                        <span class="absolute inset-0 flex items-center justify-center text-xl font-bold"><?= $cvPct ?>%</span>
                    </div>
                    <div>
                        <p class="font-bold">Seu currículo</p>
                        <p class="text-sm text-white/80"><?= $cvPct >= 100 ? 'Completo! Peça a avaliação da IA.' : 'Complete para combinar com mais vagas.' ?></p>
                    </div>
                </a>
                <?php endif; ?>
            </div>
        </section>

        <!-- Números -->
        <section class="grid grid-cols-2 <?= $ehAluno ? 'lg:grid-cols-4' : 'lg:grid-cols-2' ?> gap-4 mb-8">
            <?php
            $numeros = [['Vagas abertas', $totalVagasAtivas, 'fa-briefcase', '#F39C12', 'jobs.php']];
            if ($ehAluno) {
                $numeros[] = ['Candidaturas', $nCands, 'fa-paper-plane', '#4A9FCA', 'minhas-vagas.php?aba=candidaturas'];
                $numeros[] = ['Certificados', $nCerts, 'fa-award', '#27AE60', 'documents.php'];
                $numeros[] = ['Currículo', $cvPct . '%', 'fa-file-alt', $corCv, 'curriculum.php'];
            } else {
                $numeros[] = ['Avisos no mural', count($posts), 'fa-newspaper', '#9B59B6', perfilPaginaPermitida('posts.php') ? 'posts.php' : '#avisos'];
            }
            foreach ($numeros as [$rot, $num, $ic, $cor, $link]): ?>
                <a href="<?= htmlspecialchars($link) ?>" class="bg-white rounded-2xl p-5 flex items-center gap-4 hover:-translate-y-0.5">
                    <span class="w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0" style="background: <?= $cor ?>1a; color: <?= $cor ?>;"><i class="fas <?= $ic ?> text-lg"></i></span>
                    <div class="min-w-0">
                        <p class="text-2xl font-bold text-slate-900 leading-tight"><?= htmlspecialchars((string) $num) ?></p>
                        <p class="text-sm text-slate-500 truncate"><?= $rot ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </section>

        <?php if ($ehAluno): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

            <!-- Vagas que combinam -->
            <section class="lg:col-span-2 bg-white rounded-2xl p-6">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                    <div>
                        <h3 class="font-bold text-slate-900 text-lg flex items-center gap-2">
                            Vagas que combinam com você
                            <?php if ($rec['fonte'] === 'ia'): ?><span class="fsa-chip"><i class="fas fa-wand-magic-sparkles"></i> IA</span><?php endif; ?>
                        </h3>
                        <p class="text-sm text-slate-500"><?= $rec['fonte'] === 'ia' ? 'Analisadas pela IA com base no seu currículo.' : 'Pelo seu curso e pelo que está no seu currículo.' ?></p>
                    </div>
                    <a href="vagas-para-voce.php" class="text-sm font-semibold text-[#2B7FA6] hover:underline">Ver todas <i class="fas fa-arrow-right ml-1"></i></a>
                </div>
                <?php if (empty($rec['lista'])): ?>
                    <div class="py-10 text-center">
                        <div class="w-14 h-14 rounded-2xl bg-slate-50 text-slate-300 flex items-center justify-center mx-auto mb-3"><i class="fas fa-star text-2xl"></i></div>
                        <p class="font-semibold text-slate-700">Nenhuma vaga combina por enquanto</p>
                        <p class="text-sm text-slate-500 mt-1">Complete o currículo para receber sugestões melhores.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach (array_slice($rec['lista'], 0, 3) as $m): $vg = $m['vaga']; $s = (int) $m['score']; ?>
                            <div class="flex items-center gap-4 p-4 rounded-2xl border border-slate-100 hover:border-[#b9dbee]">
                                <div class="w-14 h-14 rounded-2xl flex items-center justify-center flex-shrink-0 <?= $s >= 70 ? 'bg-emerald-50 text-emerald-700' : ($s >= 45 ? 'bg-[#e8f3fa] text-[#2B7FA6]' : 'bg-amber-50 text-amber-700') ?>">
                                    <span class="text-lg font-bold"><?= $s ?>%</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-slate-900 truncate"><?= egh($vg['title'] ?? '') ?></p>
                                    <p class="text-sm text-slate-500 truncate"><?= egh($vg['company'] ?? '') ?><?= !empty($vg['location']) ? ' · ' . egh($vg['location']) : '' ?></p>
                                    <?php if (!empty($m['motivos'][0])): ?><p class="text-xs text-slate-400 truncate mt-0.5"><?= egh($m['motivos'][0]) ?></p><?php endif; ?>
                                </div>
                                <a href="candidatar.php?origem=<?= egh($m['origem']) ?>&amp;job_id=<?= (int) $vg['id'] ?>" target="_blank" class="fsa-btn fsa-btn-secundario h-10 px-4 hidden sm:inline-flex">Candidatar</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($rec['fonte'] !== 'ia' && perfilPode('ia_curriculo')): ?>
                        <a href="vagas-para-voce.php" class="mt-4 flex items-center gap-3 p-4 rounded-2xl bg-gradient-to-r from-[#eef7fc] to-white border border-[#d6ebf7] text-sm text-[#2B7FA6] hover:border-[#4A9FCA]">
                            <i class="fas fa-wand-magic-sparkles"></i>
                            <span class="flex-1"><strong>Quer uma análise mais precisa?</strong> A IA compara seu currículo com todas as vagas abertas.</span>
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <!-- Primeiros passos -->
            <section class="bg-white rounded-2xl p-6">
                <div class="flex items-center justify-between mb-1">
                    <h3 class="font-bold text-slate-900 text-lg">Primeiros passos</h3>
                    <span class="text-sm font-semibold text-slate-400"><?= $feitos ?>/<?= count($passos) ?></span>
                </div>
                <div class="h-1.5 bg-slate-100 rounded-full mb-5"><div class="h-full bg-[#27AE60] rounded-full" style="width: <?= count($passos) ? round($feitos / count($passos) * 100) : 0 ?>%"></div></div>
                <ul class="space-y-1">
                    <?php foreach ($passos as [$txt, $ok, $link, $ic]): ?>
                        <li>
                            <a href="<?= htmlspecialchars($link) ?>" class="flex items-center gap-3 p-3 rounded-xl <?= $ok ? 'text-slate-400' : 'hover:bg-slate-50 text-slate-700' ?>">
                                <span class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 <?= $ok ? 'bg-[#27AE60] text-white' : 'border-2 border-slate-200 text-slate-300' ?>">
                                    <i class="fas <?= $ok ? 'fa-check' : htmlspecialchars($ic) ?> text-[11px]"></i>
                                </span>
                                <span class="text-sm font-medium <?= $ok ? 'line-through' : '' ?>"><?= htmlspecialchars($txt) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </div>

        <?php if (!empty($painel['acoes'])): ?>
        <section class="mb-8">
            <h3 class="font-bold text-slate-900 text-lg mb-4">Para fazer agora</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <?php foreach ($painel['acoes'] as $a): ?>
                    <a href="<?= htmlspecialchars($a['link']) ?>" class="bg-white rounded-2xl p-5 flex items-start gap-4 hover:-translate-y-0.5">
                        <span class="w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0 <?= $coresAcao[$a['cor']] ?? $coresAcao['blue'] ?>"><i class="fas <?= htmlspecialchars($a['icone']) ?>"></i></span>
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-900"><?= htmlspecialchars($a['titulo']) ?></p>
                            <p class="text-sm text-slate-500 mt-0.5"><?= htmlspecialchars($a['texto']) ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Avisos do mural -->
        <?php if (!empty($postsCarrossel)): ?>
        <section id="avisos" class="mb-8">
            <h3 class="font-bold text-slate-900 text-lg mb-4">Avisos da Central</h3>
            <div id="postCarouselWrapper" class="relative group">
                <div id="postCarousel" class="overflow-hidden rounded-3xl">
                    <div id="postCarouselTrack" class="flex transition-transform duration-500 ease-in-out">
                        <?php foreach ($postsCarrossel as $post): $temImg = !empty($post['images']); ?>
                            <div class="w-full flex-shrink-0">
                                <article class="bg-white rounded-3xl overflow-hidden grid <?= $temImg ? 'md:grid-cols-5' : '' ?>">
                                    <?php if ($temImg): ?>
                                        <!-- A foto PREENCHE o quadro inteiro (object-cover). Clique para ver a imagem original. -->
                                        <a href="<?= htmlspecialchars($post['images'][0]) ?>" target="_blank" rel="noopener" title="Ver a imagem inteira"
                                           class="md:col-span-2 block relative h-64 md:h-auto md:min-h-[300px] bg-slate-100 overflow-hidden">
                                            <img src="<?= htmlspecialchars($post['images'][0]) ?>" alt="<?= htmlspecialchars($post['title'] ?? '') ?>" decoding="async"
                                                 class="absolute inset-0 w-full h-full object-cover" onerror="this.parentElement.style.display='none'">
                                        </a>
                                    <?php endif; ?>
                                    <div class="<?= $temImg ? 'md:col-span-3' : '' ?> p-8">
                                        <div class="flex items-center gap-3 mb-3">
                                            <span class="fsa-chip">Destaque</span>
                                            <span class="text-xs text-slate-400"><?= htmlspecialchars($post['createdAt'] ?? '') ?></span>
                                        </div>
                                        <h4 class="text-2xl font-bold text-slate-900 mb-3"><?= htmlspecialchars($post['title'] ?? '') ?></h4>
                                        <p class="text-slate-600 mb-5 leading-relaxed"><?= htmlspecialchars(mb_strimwidth((string) ($post['content'] ?? ''), 0, 260, '...')) ?></p>
                                        <?php if (!empty($post['hashtags'])): ?>
                                            <div class="flex flex-wrap gap-2">
                                                <?php foreach ($post['hashtags'] as $tag): ?>
                                                    <span class="text-[#2B7FA6] font-semibold text-sm">#<?= htmlspecialchars(trim($tag, '# ')) ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if ($totalSlides > 1): ?>
                    <button onclick="moverCarrossel(-1)" aria-label="Anterior" class="absolute left-3 top-1/2 -translate-y-1/2 w-10 h-10 bg-white shadow-md rounded-full flex items-center justify-center text-[#2B7FA6] opacity-0 group-hover:opacity-100"><i class="fas fa-chevron-left"></i></button>
                    <button onclick="moverCarrossel(1)" aria-label="Próximo" class="absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 bg-white shadow-md rounded-full flex items-center justify-center text-[#2B7FA6] opacity-0 group-hover:opacity-100"><i class="fas fa-chevron-right"></i></button>
                    <div class="flex justify-center gap-2 mt-4">
                        <?php for ($i = 0; $i < $totalSlides; $i++): ?>
                            <button onclick="irParaSlide(<?= $i ?>)" data-dot="<?= $i ?>" aria-label="Aviso <?= $i + 1 ?>" class="h-2.5 w-2.5 rounded-full bg-gray-300 transition-all"></button>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Atalhos -->
        <section>
            <h3 class="font-bold text-slate-900 text-lg mb-4">Atalhos</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php
                $atalhos = [['jobs.php', 'Vagas', 'Busque seu estágio', 'fa-briefcase', '#F39C12']];
                if ($ehAluno) {
                    $atalhos[] = ['curriculum.php', 'Currículo', 'Edite e baixe em PDF', 'fa-file-alt', '#9B59B6'];
                    $atalhos[] = ['documents.php', 'Documentos', 'Certificados e horas', 'fa-folder', '#27AE60'];
                    $atalhos[] = ['career-tips.php', 'Dicas de Carreira', 'Plano e entrevistas', 'fa-lightbulb', '#4A9FCA'];
                } else {
                    foreach ([['admin-jobs.php', 'Admin Vagas', 'Publicar e aprovar', 'fa-cog', '#4A9FCA'], ['admin-paineis.php', 'Painéis', 'Indicadores', 'fa-chart-pie', '#9B59B6'],
                              ['request-job.php', 'Publicar vaga', 'Enviar para aprovação', 'fa-plus-circle', '#4A9FCA'], ['mentoria.php', 'Mentoria', 'Orientar alunos', 'fa-hands-helping', '#9B59B6'],
                              ['career-tips.php', 'Dicas de Carreira', 'Orientações', 'fa-lightbulb', '#27AE60']] as $a) {
                        if (perfilPaginaPermitida($a[0])) $atalhos[] = $a;
                    }
                }
                foreach ($atalhos as [$link, $tit, $sub, $ic, $cor]): ?>
                    <a href="<?= $link ?>" class="bg-white rounded-2xl p-5 hover:-translate-y-0.5">
                        <span class="w-11 h-11 rounded-2xl flex items-center justify-center mb-4" style="background: <?= $cor ?>1a; color: <?= $cor ?>;"><i class="fas <?= $ic ?>"></i></span>
                        <p class="font-bold text-slate-900"><?= $tit ?></p>
                        <p class="text-sm text-slate-500"><?= $sub ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
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
            dot.classList.toggle('bg-[#2B7FA6]', idx === slideAtual); dot.style.width = idx === slideAtual ? '24px' : '10px';
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
