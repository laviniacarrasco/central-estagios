<?php
/**
 * =============================================================================
 * DICAS DE CARREIRA
 * -----------------------------------------------------------------------------
 * A tela é dividida em abas (tudo na mesma página, troca sem recarregar):
 *   Meu plano    -> checklist com o progresso do aluno. Alguns itens se marcam
 *                   sozinhos (currículo, foto, candidaturas...); os outros o
 *                   aluno marca. Mostra o "próximo passo" sugerido.
 *   Meu perfil   -> gráfico radar do Perfil por Competências (só quem tem IA)
 *   Trilhas      -> roteiros, dicas do curso e modelos prontos (dicas-extras.php)
 *   Entrevista   -> guia antes / durante / depois + perguntas para treinar
 *   Recursos     -> sites de cursos e portfólio
 *
 * O que o aluno marca fica em data/users/<id>/dicasProgresso.json.
 * =============================================================================
 */
require_once 'includes/config.php';
require_once 'includes/perfil_api.php';   // ponte com o app de Perfil por Competências (Flask)
require_once 'includes/melhorias.php';    // pontuação do currículo
checkAuth();

$pageTitle = 'Dicas de Carreira';
$userId    = (int) $_SESSION['user_id'];
$userName  = $_SESSION['user_name'] ?? 'Usuário';
$restrito  = !perfilPode('ia_perfil');          // sem a parte de IA (aluno restrito, professor)
$ehAluno   = perfilPode('area_aluno');          // professor não tem plano pessoal

/* -----------------------------------------------------------------------------
 * 1) Itens do plano ('auto' = o sistema sabe sozinho se está feito)
 * -------------------------------------------------------------------------- */
$progresso = $ehAluno ? loadData('dicasProgresso', true) : [];   // ['chave' => true, ...]

// Marcar / desmarcar item manual (volta para a aba do plano)
if ($ehAluno && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'marcar') {
    $chave = preg_replace('/[^a-z0-9_]/', '', (string) ($_POST['item'] ?? ''));
    if ($chave !== '') {
        if (!empty($progresso[$chave])) unset($progresso[$chave]); else $progresso[$chave] = true;
        saveData('dicasProgresso', $progresso, true);
    }
    header('Location: career-tips.php#plano');
    exit;
}

$cv        = $ehAluno ? loadData('userCurriculum', true) : [];
$cvPont    = mhCurriculoPontuacao(is_array($cv) ? $cv : []);
$perfilU   = $ehAluno ? loadData('userProfile', true) : [];
$cands     = $ehAluno ? loadData('userCandidaturas', true) : [];
$nCands    = count($cands);
$entrevist = count(array_filter($cands, fn($c) => in_array($c['status'] ?? '', ['Entrevista', 'Aprovado(a)'], true)));
$usuario   = egressoCarregarUsuario($userId) ?: [];
$resultadoPerfil = $restrito ? null : buscarResultadoPerfil($userName);
$urlFormPerfil   = $restrito ? '' : urlFormularioPerfil($userName);

// [chave, grupo, texto, feito?, link, automático?]
$plano = [
    ['cv70',     'Currículo',    'Deixe seu currículo com 70% ou mais', $cvPont['pct'] >= 70, 'curriculum.php', true],
    ['foto',     'Currículo',    'Coloque uma foto no perfil', !empty($perfilU['foto']), 'profile.php', true],
    ['cvpdf',    'Currículo',    'Baixe o currículo em PDF e revise a ortografia', !empty($progresso['cvpdf']), 'curriculum.php', false],
    ['linkedin', 'LinkedIn',     'Crie ou atualize o LinkedIn (foto, título e resumo)', !empty($progresso['linkedin']), 'https://www.linkedin.com/', false],
    ['conexoes', 'LinkedIn',     'Conecte-se com 10 colegas, professores ou egressos', !empty($progresso['conexoes']), '', false],
    ['cand1',    'Candidaturas', 'Candidate-se à sua primeira vaga', $nCands >= 1, 'vagas-para-voce.php', true],
    ['cand5',    'Candidaturas', 'Chegue a 5 candidaturas', $nCands >= 5, 'jobs.php', true],
    ['entrev',   'Entrevista',   'Treine as perguntas da aba Entrevista em voz alta', !empty($progresso['entrev']), '#entrevista', false],
    ['entrev1',  'Entrevista',   'Chegue a uma entrevista (marque em Minhas Vagas)', $entrevist >= 1, 'minhas-vagas.php', true],
];
if (!$restrito) {
    array_splice($plano, 2, 0, [['perfil', 'Currículo', 'Responda o teste de Perfil por Competências', (bool) $resultadoPerfil, $urlFormPerfil ?: 'profile.php', true]]);
    $plano[] = ['alertas', 'Candidaturas', 'Ative os alertas de vagas do seu curso', !empty($usuario['alertas']['vagaNova']) || !empty($usuario['alertas']['resumoSemanal']), 'profile.php?config=alertas', true];
    $plano[] = ['mentor',  'Entrevista',   'Converse com um mentor egresso', !empty($progresso['mentor']), 'mentoria.php', false];
}

$feitos   = count(array_filter($plano, fn($i) => $i[3]));
$pctPlano = $plano ? (int) round($feitos / count($plano) * 100) : 0;
$proximo  = null;
foreach ($plano as $i) { if (!$i[3]) { $proximo = $i; break; } }
$grupos = [];
foreach ($plano as $i) $grupos[$i[1]][] = $i;
$iconeGrupo = ['Currículo' => 'fa-file-alt', 'LinkedIn' => 'fa-linkedin-in', 'Candidaturas' => 'fa-paper-plane', 'Entrevista' => 'fa-comments'];

/* -----------------------------------------------------------------------------
 * 2) Conteúdo das outras abas
 * -------------------------------------------------------------------------- */
$interviewTips = [
    ['Antes', 'fa-search', ['Pesquise a empresa, os produtos e a vaga', 'Releia o seu currículo: tudo nele pode virar pergunta', 'Teste câmera e microfone se for online', 'Separe documentos e chegue 10 minutos antes']],
    ['Durante', 'fa-user-tie', ['Responda com exemplos reais (situação, ação e resultado)', 'Seja honesto: "ainda não sei, mas aprendo rápido" vale mais que inventar', 'Mostre interesse com perguntas sobre a equipe e o dia a dia', 'Fale com calma e olhe para quem pergunta']],
    ['Depois', 'fa-envelope', ['Envie um agradecimento no mesmo dia (modelo na aba Trilhas)', 'Anote o que perguntaram e como você foi', 'Atualize o andamento em Minhas Vagas', 'Continue se candidatando enquanto espera']],
];
$perguntas = [
    ['Fale um pouco sobre você.', 'Curso, momento atual, um interesse ligado à vaga e o que você busca. Até 1 minuto.'],
    ['Por que você quer esta vaga?', 'Ligue algo da empresa ou da vaga com o que você gosta e quer aprender.'],
    ['Qual é o seu maior defeito?', 'Um ponto real, que não seja essencial para a vaga, e o que você já faz para melhorar.'],
    ['Conte uma situação difícil e como resolveu.', 'Use o método STAR: situação, tarefa, ação e resultado. Pode ser da faculdade.'],
    ['Onde você se vê daqui a alguns anos?', 'Mostre vontade de crescer na área, sem prometer ficar para sempre.'],
    ['Você tem alguma pergunta?', 'Sempre tenha: como é a rotina, quem acompanha o estagiário, como medem o desempenho.'],
];
$resources = [
    ['LinkedIn Learning', 'Cursos de desenvolvimento profissional', 'https://www.linkedin.com/learning/', 'fa-laptop'],
    ['Coursera', 'Certificados de universidades (muitos gratuitos)', 'https://www.coursera.org/', 'fa-award'],
    ['Fundação Bradesco (Escola Virtual)', 'Cursos gratuitos de tecnologia e gestão', 'https://www.ev.org.br/', 'fa-graduation-cap'],
    ['GitHub', 'Mostre seus projetos de código', 'https://github.com/', 'fa-code-branch'],
    ['Behance', 'Portfólio para design e arquitetura', 'https://www.behance.net/', 'fa-palette'],
    ['Duolingo', 'Pratique inglês todo dia', 'https://www.duolingo.com/', 'fa-language'],
];

$abas = $ehAluno ? ['plano' => ['Meu plano', 'fa-list-check']] : [];
if (!$restrito) $abas['perfil'] = ['Meu perfil', 'fa-chart-pie'];
$abas['trilhas']    = ['Trilhas e modelos', 'fa-route'];
$abas['entrevista'] = ['Entrevista', 'fa-comments'];
$abas['recursos']   = ['Recursos', 'fa-book-open'];

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-7xl mx-auto">

        <!-- Topo com o progresso -->
        <div class="bg-gradient-to-r from-[#4A9FCA] to-[#2B7FA6] text-white rounded-3xl p-8 mb-6 flex flex-wrap items-center justify-between gap-6">
            <div>
                <p class="text-white/80 text-sm mb-1">Dicas de Carreira</p>
                <h2 class="text-3xl font-bold mb-2">Olá, <?= egh(explode(' ', trim($userName))[0]) ?>!</h2>
                <p class="text-white/90"><?= $ehAluno ? 'Siga o seu plano e acompanhe o que já fez.' : 'Orientações práticas para os alunos da FSA.' ?></p>
                <?php if ($ehAluno && $proximo): ?>
                    <a href="<?= egh($proximo[4] ?: '#plano') ?>" <?= preg_match('#^https?://#', $proximo[4]) ? 'target="_blank" rel="noopener"' : '' ?>
                       class="inline-flex items-center gap-2 mt-4 px-5 py-2.5 bg-white text-[#2B7FA6] rounded-xl text-sm font-bold hover:bg-blue-50">
                        <i class="fas fa-arrow-right"></i> Próximo passo: <?= egh($proximo[2]) ?>
                    </a>
                <?php elseif ($ehAluno): ?>
                    <p class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-white/15 rounded-xl text-sm font-semibold"><i class="fas fa-trophy"></i> Você concluiu todo o plano. Parabéns!</p>
                <?php endif; ?>
            </div>
            <?php if ($ehAluno): ?>
                <!-- Anel de progresso -->
                <div class="relative w-32 h-32 flex-shrink-0">
                    <svg viewBox="0 0 36 36" class="w-32 h-32 -rotate-90">
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="rgba(255,255,255,.25)" stroke-width="3"></circle>
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-dasharray="<?= $pctPlano ?> 100"></circle>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-3xl font-bold"><?= $pctPlano ?>%</span>
                        <span class="text-xs text-white/80"><?= $feitos ?> de <?= count($plano) ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Abas -->
        <div class="flex flex-wrap gap-2 mb-6">
            <?php foreach ($abas as $k => [$rot, $ic]): ?>
                <button type="button" data-aba="<?= $k ?>" class="aba-dica px-5 py-2.5 rounded-xl text-sm font-semibold border bg-white text-gray-600 border-gray-200">
                    <i class="fas <?= $ic ?> mr-1"></i> <?= egh($rot) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <?php if ($ehAluno): ?>
        <!-- ===================== ABA: MEU PLANO ===================== -->
        <section data-painel="plano" class="painel-dica hidden">
            <div class="grid lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 grid md:grid-cols-2 gap-6">
                    <?php foreach ($grupos as $g => $itens): ?>
                        <div class="bg-white p-6 rounded-2xl shadow-sm">
                            <h3 class="font-bold text-slate-900 mb-4 flex items-center gap-2">
                                <span class="w-9 h-9 rounded-lg bg-[#4A9FCA]/10 text-[#2B7FA6] flex items-center justify-center">
                                    <i class="<?= ($iconeGrupo[$g] ?? '') === 'fa-linkedin-in' ? 'fab' : 'fas' ?> <?= $iconeGrupo[$g] ?? 'fa-check' ?>"></i>
                                </span>
                                <?= egh($g) ?>
                            </h3>
                            <ul class="space-y-2">
                                <?php foreach ($itens as [$chave, $grupo, $texto, $feito, $link, $auto]): ?>
                                    <li class="flex items-start gap-3 p-3 rounded-xl <?= $feito ? 'bg-emerald-50' : 'bg-gray-50' ?>">
                                        <?php if ($auto): ?>
                                            <!-- Automático: o sistema marca sozinho -->
                                            <span class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 <?= $feito ? 'bg-[#27AE60] text-white' : 'border-2 border-gray-300' ?>" title="Marcado automaticamente">
                                                <?php if ($feito): ?><i class="fas fa-check text-xs"></i><?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <!-- Manual: o aluno marca -->
                                            <form method="POST" class="flex-shrink-0">
                                                <input type="hidden" name="action" value="marcar">
                                                <input type="hidden" name="item" value="<?= egh($chave) ?>">
                                                <button type="submit" title="<?= $feito ? 'Desmarcar' : 'Marcar como feito' ?>"
                                                        class="w-6 h-6 rounded-full flex items-center justify-center <?= $feito ? 'bg-[#27AE60] text-white' : 'border-2 border-gray-300 hover:border-[#27AE60]' ?>">
                                                    <?php if ($feito): ?><i class="fas fa-check text-xs"></i><?php endif; ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <div class="flex-1 text-sm">
                                            <p class="<?= $feito ? 'text-gray-500 line-through' : 'text-slate-800 font-medium' ?>"><?= egh($texto) ?></p>
                                            <?php if (!$feito && $link !== ''): ?>
                                                <a href="<?= egh($link) ?>" <?= preg_match('#^https?://#', $link) ? 'target="_blank" rel="noopener"' : '' ?>
                                                   class="text-xs text-[#2B7FA6] font-semibold hover:underline"><?= $link[0] === '#' ? 'Ver' : 'Fazer agora' ?> <i class="fas fa-arrow-right text-[10px]"></i></a>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Lateral: currículo e números -->
                <div class="space-y-6">
                    <div class="bg-white p-6 rounded-2xl shadow-sm">
                        <h3 class="font-bold text-slate-900 mb-3">Seu currículo</h3>
                        <div class="flex items-end gap-2 mb-2">
                            <span class="text-4xl font-bold <?= $cvPont['pct'] >= 70 ? 'text-[#27AE60]' : 'text-[#F39C12]' ?>"><?= (int) $cvPont['pct'] ?>%</span>
                            <span class="text-sm text-gray-400 mb-1">completo</span>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden mb-4">
                            <div class="h-full <?= $cvPont['pct'] >= 70 ? 'bg-[#27AE60]' : 'bg-[#F39C12]' ?>" style="width: <?= (int) $cvPont['pct'] ?>%"></div>
                        </div>
                        <?php if ($cvPont['faltando']): ?>
                            <p class="text-xs font-bold text-gray-500 uppercase mb-2">O que falta</p>
                            <ul class="space-y-2 mb-4">
                                <?php foreach (array_slice($cvPont['faltando'], 0, 4) as $f): ?>
                                    <li class="text-sm text-gray-600"><i class="fas fa-plus-circle text-[#4A9FCA] mr-1"></i><strong><?= egh($f['label']) ?>:</strong> <?= egh($f['dica']) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <a href="curriculum.php" class="block text-center px-4 py-2.5 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl text-sm font-semibold">Editar currículo</a>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-5 bg-[#58A1D4] text-white rounded-2xl"><p class="text-white/90 text-xs">Candidaturas</p><p class="text-3xl font-bold"><?= $nCands ?></p></div>
                        <div class="p-5 bg-[#27AE60] text-white rounded-2xl"><p class="text-white/90 text-xs">Entrevistas</p><p class="text-3xl font-bold"><?= $entrevist ?></p></div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if (!$restrito): ?>
        <!-- ===================== ABA: MEU PERFIL (radar) ===================== -->
        <section data-painel="perfil" class="painel-dica hidden">

        <!-- ============================================================ -->
        <!-- Gráfico de Radar do Perfil por Competências                  -->
        <!-- Só aparece o gráfico se o aluno já respondeu o formulário    -->
        <!-- ============================================================ -->
        <div class="bg-white p-8 rounded-2xl shadow-sm">
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

        
        </section>
        <?php endif; ?>

        <!-- ===================== ABA: TRILHAS E MODELOS ===================== -->
        <section data-painel="trilhas" class="painel-dica hidden">
            <?php include 'includes/dicas-extras.php'; ?>
        </section>

        <!-- ===================== ABA: ENTREVISTA ===================== -->
        <section data-painel="entrevista" class="painel-dica hidden">
            <div class="grid md:grid-cols-3 gap-6 mb-8">
                <?php foreach ($interviewTips as [$titulo, $ic, $itens]): ?>
                    <div class="bg-white rounded-2xl p-6 shadow-sm">
                        <h4 class="font-bold text-gray-900 mb-4 text-lg flex items-center gap-2">
                            <span class="w-9 h-9 rounded-lg bg-[#4A9FCA]/10 text-[#2B7FA6] flex items-center justify-center"><i class="fas <?= $ic ?>"></i></span>
                            <?= egh($titulo) ?>
                        </h4>
                        <ul class="space-y-3">
                            <?php foreach ($itens as $t): ?>
                                <li class="flex items-start gap-2 text-sm text-gray-700"><i class="fas fa-check-circle text-[#27AE60] mt-0.5"></i><?= egh($t) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="bg-white p-8 rounded-2xl shadow-sm">
                <h3 class="text-2xl font-bold text-gray-900 mb-1">Perguntas para treinar</h3>
                <p class="text-gray-600 mb-6">Responda em voz alta antes de ver a dica. Clique na pergunta para abrir.</p>
                <div class="space-y-3">
                    <?php foreach ($perguntas as $i => [$q, $dica]): ?>
                        <details class="group border border-gray-100 rounded-xl">
                            <summary class="cursor-pointer list-none flex items-center justify-between gap-3 p-4 font-semibold text-slate-800">
                                <span><span class="text-[#4A9FCA] mr-2"><?= $i + 1 ?>.</span><?= egh($q) ?></span>
                                <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform"></i>
                            </summary>
                            <p class="px-4 pb-4 text-sm text-gray-600"><i class="fas fa-lightbulb text-[#F39C12] mr-1"></i><?= egh($dica) ?></p>
                        </details>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ===================== ABA: RECURSOS ===================== -->
        <section data-painel="recursos" class="painel-dica hidden">
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($resources as [$nome, $desc, $link, $ic]): ?>
                    <a href="<?= egh($link) ?>" target="_blank" rel="noopener" class="flex items-start gap-4 p-5 bg-white rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                        <span class="w-12 h-12 rounded-xl bg-[#4A9FCA]/10 text-[#2B7FA6] flex items-center justify-center flex-shrink-0"><i class="fas <?= $ic ?> text-lg"></i></span>
                        <span>
                            <span class="block font-bold text-slate-900"><?= egh($nome) ?> <i class="fas fa-external-link-alt text-xs text-gray-400 ml-1"></i></span>
                            <span class="block text-sm text-gray-500"><?= egh($desc) ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

    </div>
</main>

<script>
/* Troca de abas sem recarregar. O endereço guarda a aba (#plano, #trilhas...). */
(function () {
    var botoes  = document.querySelectorAll('.aba-dica');
    var paineis = document.querySelectorAll('.painel-dica');
    var ATIVO = 'bg-[#4A9FCA] text-white border-[#4A9FCA]', INATIVO = 'bg-white text-gray-600 border-gray-200';
    function abrir(nome) {
        if (!document.querySelector('[data-painel="' + nome + '"]') && botoes.length) nome = botoes[0].getAttribute('data-aba');
        paineis.forEach(function (p) { p.classList.toggle('hidden', p.getAttribute('data-painel') !== nome); });
        botoes.forEach(function (b) {
            var ativo = b.getAttribute('data-aba') === nome;
            (ativo ? INATIVO : ATIVO).split(' ').forEach(function (c) { b.classList.remove(c); });
            (ativo ? ATIVO : INATIVO).split(' ').forEach(function (c) { b.classList.add(c); });
        });
    }
    function ir(nome) { history.replaceState(null, '', '#' + nome); abrir(nome); }
    botoes.forEach(function (b) { b.addEventListener('click', function () { ir(b.getAttribute('data-aba')); }); });
    // links internos como "#entrevista" dentro do plano
    document.querySelectorAll('a[href^="#"]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            var n = a.getAttribute('href').slice(1);
            if (document.querySelector('[data-painel="' + n + '"]')) { e.preventDefault(); ir(n); window.scrollTo(0, 0); }
        });
    });
    abrir((location.hash || '').slice(1));
})();
</script>

<?php include 'includes/footer.php'; ?>
