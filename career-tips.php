<?php
require_once 'includes/config.php';
require_once 'includes/perfil_api.php'; // ponte com o app de Perfil por Competências (Flask)
checkAuth();

$pageTitle = 'Dicas de Carreira';

// busca o resultado do Perfil por Competências do aluno logado
$userName        = $_SESSION['user_name'] ?? 'Usuário';
$resultadoPerfil = buscarResultadoPerfil($userName);
$urlFormPerfil   = urlFormularioPerfil($userName);

$tips = [
    [
        'id' => 1,
        'icon' => 'fa-chart-line',
        'title' => 'Desenvolva suas habilidades técnicas',
        'description' => 'Invista em cursos online, certificações e projetos práticos para aprimorar suas competências técnicas.',
        'color' => 'from-[#4A9FCA] to-[#3A8FB0]',
    ],
    [
        'id' => 2,
        'icon' => 'fa-bullseye',
        'title' => 'Defina objetivos claros',
        'description' => 'Estabeleça metas de curto, médio e longo prazo para manter o foco e mensurar seu progresso.',
        'color' => 'from-[#E74C3C] to-[#C0392B]',
    ],
    [
        'id' => 3,
        'icon' => 'fa-lightbulb',
        'title' => 'Construa seu networking',
        'description' => 'Participe de eventos, workshops e comunidades da sua área para abrir portas.',
        'color' => 'from-[#27AE60] to-[#229954]',
    ],
    [
        'id' => 4,
        'icon' => 'fa-users',
        'title' => 'Desenvolva Soft Skills',
        'description' => 'Habilidades interpessoais como comunicação e trabalho em equipe são fundamentais.',
        'color' => 'from-[#9B59B6] to-[#8E44AD]',
    ],
];

$actionSteps = [
    ['icon' => 'fa-file-alt', 'text' => 'Atualize seu currículo regularmente'],
    ['icon' => 'fa-users', 'text' => 'Mantenha seu perfil no LinkedIn ativo'],
    ['icon' => 'fa-comments', 'text' => 'Pratique suas habilidades de comunicação'],
    ['icon' => 'fa-chart-line', 'text' => 'Busque feedback construtivo de mentores'],
];

$interviewTips = [
    [
        'title' => 'Antes da Entrevista',
        'tips' => [
            'Pesquise sobre a empresa e a vaga',
            'Prepare respostas para perguntas comuns',
            'Separe documentos e chegue com antecedência',
            'Vista-se adequadamente para a ocasião',
        ],
    ],
    [
        'title' => 'Durante a Entrevista',
        'tips' => [
            'Demonstre confiança e mantenha contato visual',
            'Seja honesto sobre suas experiências',
            'Faça perguntas sobre a empresa',
            'Mostre entusiasmo pela oportunidade',
        ],
    ],
    [
        'title' => 'Após a Entrevista',
        'tips' => [
            'Envie um e-mail de agradecimento',
            'Reflita sobre pontos de melhoria',
            'Mantenha contato respeitoso',
            'Continue se candidatando',
        ],
    ],
];

$resources = [
    ['title' => 'LinkedIn Learning', 'description' => 'Cursos online de desenvolvimento profissional', 'link' => 'https://www.linkedin.com/learning/', 'icon' => 'fa-laptop'],
    ['title' => 'Coursera', 'description' => 'Certificações de universidades renomadas', 'link' => 'https://www.coursera.org/', 'icon' => 'fa-award'],
    ['title' => 'GitHub', 'description' => 'Compartilhe projetos de código', 'link' => 'https://github.com/', 'icon' => 'fa-briefcase'],
    ['title' => 'Medium', 'description' => 'Artigos sobre carreira e tecnologia', 'link' => 'https://medium.com/', 'icon' => 'fa-book'],
];

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Dicas para Impulsionar sua Carreira</h2>
            <p class="text-gray-600">Orientações práticas para o seu desenvolvimento profissional</p>
        </div>

        <!-- ============================================================ -->
        <!-- Gráfico de Radar do Perfil por Competências                  -->
        <!-- Só aparece o gráfico se o aluno já respondeu o formulário    -->
        <!-- ============================================================ -->
        <div class="bg-white p-8 rounded-2xl shadow-sm mb-8">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-12 h-12 bg-gradient-to-br from-[#4A9FCA] to-[#3A8FB0] rounded-xl flex items-center justify-center">
                    <i class="fas fa-spider text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Seu Gráfico de Perfil (Radar)</h3>
                    <p class="text-gray-600">Visualize suas competências de Foco Resultado e Foco Relacionamento</p>
                </div>
            </div>

            <?php if ($resultadoPerfil): ?>
                <p class="text-xs text-gray-400 mb-4">
                    <i class="fas fa-clock mr-1"></i>
                    Baseado na sua resposta de <?php echo htmlspecialchars($resultadoPerfil['data_formatada'] ?? '—'); ?>
                </p>
                <div class="flex justify-center bg-gray-50 rounded-2xl p-4">
                    <img src="<?php echo htmlspecialchars(urlRadarPerfil($resultadoPerfil)); ?>"
                         alt="Gráfico de radar do perfil por competências"
                         class="max-w-full rounded-xl"
                         onerror="this.parentElement.innerHTML='<p class=&quot;text-sm text-gray-400 py-8&quot;>Não foi possível carregar o gráfico no momento.</p>'">
                </div>
                <div class="flex flex-wrap gap-3 mt-5">
                    <a href="<?php echo htmlspecialchars(urlPdfPerfil($resultadoPerfil)); ?>" target="_blank"
                       class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl text-sm font-semibold transition-all">
                        <i class="fas fa-file-pdf"></i> Baixar relatório em PDF
                    </a>
                    <a href="profile.php"
                       class="inline-flex items-center gap-2 px-5 py-2.5 border border-gray-200 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-50 transition-all">
                        <i class="fas fa-user"></i> Ver parecer completo no Perfil
                    </a>
                </div>
            <?php else: ?>
                <div class="p-6 bg-blue-50 border border-blue-100 rounded-2xl text-center">
                    <i class="fas fa-clipboard-list text-3xl text-[#4A9FCA] mb-3"></i>
                    <p class="text-gray-700 font-semibold mb-1">Você ainda não respondeu o teste de Perfil por Competências.</p>
                    <p class="text-sm text-gray-500 mb-5">Responda o formulário para desbloquear seu gráfico de radar aqui.</p>
                    <a href="<?php echo htmlspecialchars($urlFormPerfil); ?>" target="_blank"
                       class="inline-flex items-center gap-2 px-6 py-3 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl font-bold transition-all">
                        <i class="fas fa-play"></i> Responder formulário
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Main Tips Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
            <?php foreach ($tips as $index => $tip): ?>
                <div class="p-6 bg-white rounded-2xl shadow-sm hover:shadow-xl transition-all duration-300 hover:scale-105" style="animation-delay: <?php echo $index * 0.1; ?>s">
                    <div class="w-14 h-14 rounded-xl bg-gradient-to-br <?php echo $tip['color']; ?> flex items-center justify-center mb-4">
                        <i class="fas <?php echo $tip['icon']; ?> text-white text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-3"><?php echo $tip['title']; ?></h3>
                    <p class="text-gray-600 text-sm leading-relaxed"><?php echo $tip['description']; ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Action Steps -->
        <div class="bg-white p-8 rounded-2xl shadow-sm mb-8">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-12 h-12 bg-gradient-to-br from-[#4A9FCA] to-[#3A8FB0] rounded-xl flex items-center justify-center">
                    <i class="fas fa-bolt text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Passos Práticos para o Sucesso</h3>
                    <p class="text-gray-600">Ações concretas que você pode começar hoje</p>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <?php foreach ($actionSteps as $step): ?>
                    <div class="flex items-start gap-3 p-4 bg-gray-50 rounded-lg hover:bg-blue-50 transition-colors">
                        <i class="fas <?php echo $step['icon']; ?> text-[#4A9FCA] mt-0.5"></i>
                        <p class="text-sm text-gray-700 leading-relaxed"><?php echo $step['text']; ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Interview Tips -->
        <div class="bg-gradient-to-br from-purple-50 to-blue-50 p-8 rounded-2xl mb-8">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-12 h-12 bg-gradient-to-br from-[#9B59B6] to-[#8E44AD] rounded-xl flex items-center justify-center">
                    <i class="fas fa-comments text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Guia para Entrevistas de Emprego</h3>
                    <p class="text-gray-600">Prepare-se para arrasar na sua próxima entrevista</p>
                </div>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <?php foreach ($interviewTips as $section): ?>
                    <div class="bg-white rounded-lg p-6 shadow-sm">
                        <h4 class="font-bold text-gray-900 mb-4 text-lg"><?php echo $section['title']; ?></h4>
                        <ul class="space-y-3">
                            <?php foreach ($section['tips'] as $tip): ?>
                                <li class="flex items-start gap-2">
                                    <i class="fas fa-check-circle text-[#27AE60] mt-0.5"></i>
                                    <span class="text-sm text-gray-700"><?php echo $tip; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Resources -->
        <div class="bg-white p-8 rounded-2xl shadow-sm mb-8">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-12 h-12 bg-gradient-to-br from-[#27AE60] to-[#229954] rounded-xl flex items-center justify-center">
                    <i class="fas fa-book-open text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Recursos Recomendados</h3>
                    <p class="text-gray-600">Plataformas para aprimorar suas habilidades</p>
                </div>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
                <?php foreach ($resources as $resource): ?>
                    <a href="<?php echo $resource['link']; ?>" target="_blank" rel="noopener noreferrer" class="block p-6 bg-gradient-to-br from-gray-50 to-gray-100 rounded-lg hover:shadow-lg hover:scale-105 transition-all duration-300">
                        <i class="fas <?php echo $resource['icon']; ?> text-3xl text-[#4A9FCA] mb-3"></i>
                        <h4 class="font-bold text-gray-900 mb-2"><?php echo $resource['title']; ?></h4>
                        <p class="text-sm text-gray-600 mb-3"><?php echo $resource['description']; ?></p>
                        <div class="flex items-center text-[#4A9FCA] text-sm font-medium">
                            Acessar <i class="fas fa-arrow-right ml-1"></i>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- CTA Banner -->
        <div class="bg-gradient-to-r from-[#4A9FCA] to-[#2B7FA6] text-white p-8 rounded-2xl text-center mt-8">
            <i class="fas fa-bullseye text-6xl mb-4 text-white/80"></i>
            <h3 class="text-2xl font-bold mb-3">Pronto para dar o próximo passo?</h3>
            <p class="text-white/90 mb-6 max-w-2xl mx-auto">
                Explore as vagas disponíveis e candidate-se às oportunidades que combinam com seu perfil.
            </p>
            <a href="jobs.php" class="inline-block px-8 py-3 bg-white text-[#4A9FCA] rounded-xl font-bold hover:bg-gray-100">
                <i class="fas fa-briefcase mr-2"></i> Ver Vagas Disponíveis
            </a>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
