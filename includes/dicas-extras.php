<?php
/** Dicas de Carreira: trilhas, dicas do curso e modelos prontos (sem IA) */
require_once __DIR__ . '/melhorias.php';

$dxUsuario = egressoCarregarUsuario((int) ($_SESSION['user_id'] ?? 0)) ?: [];
$dxCurso   = trim((string) ($dxUsuario['curso'] ?? ''));
$dxN       = mhSemAcento($dxCurso);

$dxPorArea = [
    ['/sistema|computa|dados|software|inteligencia/', 'Tecnologia e dados', [
        'Publique projetos no GitHub, mesmo pequenos, com um README explicando o que fazem.',
        'Treine SQL e uma linguagem (Python, JavaScript ou Java): é o que mais aparece nas vagas.',
        'Monte um dashboard ou app simples e coloque o link no currículo.',
    ]],
    ['/engenharia/', 'Engenharias', [
        'Destaque projetos de fábrica, laboratório e iniciação científica, com números quando puder.',
        'Inglês técnico e Excel avançado costumam ser requisitos de estágio em indústria.',
        'Conheça o básico de Lean, Six Sigma e segurança do trabalho.',
    ]],
    ['/administra|contabe|economia|gestao/', 'Gestão e negócios', [
        'Domine Excel (tabela dinâmica, PROCV) e mostre resultados que você ajudou a gerar.',
        'Aprenda o básico de Power BI: é um diferencial em quase toda área administrativa.',
        'Mostre iniciativa: organização de eventos, liderança em atlética ou empresa júnior.',
    ]],
    ['/direito/', 'Direito', [
        'Inclua a OAB como meta e cursos de extensão em sua área de interesse.',
        'Capriche na redação: o texto do currículo e da carta já é uma amostra do seu trabalho.',
        'Procure escritórios e departamentos jurídicos de empresas do ABC e de São Paulo.',
    ]],
    ['/psicologia|pedagogia/', 'Humanas', [
        'Registre estágios observacionais, projetos de extensão e atividades com a comunidade.',
        'Mostre comunicação e escuta: descreva casos (sem expor pessoas) em que você ajudou alguém.',
        'Áreas de RH, T&D e educação corporativa contratam muito estagiário dessas áreas.',
    ]],
    ['/arquitetura|urbanismo/', 'Arquitetura', [
        'Monte um portfólio enxuto (5 a 8 projetos) em PDF e no Behance.',
        'Liste os softwares que você usa: AutoCAD, Revit, SketchUp, Photoshop.',
        'Mostre o processo do projeto, não só a imagem final.',
    ]],
    ['/biolog|biomed|saude/', 'Saúde e biológicas', [
        'Destaque aulas práticas, técnicas de laboratório e equipamentos que você já usou.',
        'Inclua cursos de biossegurança e boas práticas de laboratório.',
        'Procure laboratórios, hospitais e indústrias farmacêuticas da região.',
    ]],
];
$dxArea = ['Para todos os cursos', [
    'Tenha um currículo de uma página, com resumo curto e resultados em vez de só tarefas.',
    'Use um e-mail profissional e um LinkedIn com foto e resumo.',
    'Candidate-se a várias vagas por semana e anote tudo em Minhas Vagas.',
]];
foreach ($dxPorArea as $a) {
    if (preg_match($a[0], $dxN)) { $dxArea = [$a[1], $a[2]]; break; }
}

$dxTrilhas = [
    ['fa-seedling', 'Primeiro estágio', ['Monte o currículo com projetos da faculdade', 'Peça indicação a professores e veteranos', 'Cadastre-se nos alertas de vagas do seu curso', 'Candidate-se a 5 vagas por semana']],
    ['fa-comments', 'Entrevista', ['Pesquise a empresa e a vaga', 'Treine as perguntas "fale sobre você" e "por que esta vaga?"', 'Prepare 2 exemplos de situações reais', 'Envie um agradecimento no mesmo dia']],
    ['fa-linkedin-in', 'LinkedIn', ['Foto simples e título com seu curso e área', 'Resumo de 3 linhas e habilidades principais', 'Conecte-se com colegas, professores e egressos', 'Siga as empresas que você quer']],
    ['fa-language', 'Inglês', ['Faça um teste de nível gratuito', 'Estude 20 minutos por dia com podcasts ou vídeos', 'Treine apresentar-se em inglês em voz alta', 'Coloque o nível real no currículo']],
];

$dxNome = trim((string) ($_SESSION['user_name'] ?? ''));
$dxModelos = [
    ['E-mail de agradecimento após a entrevista',
        "Assunto: Agradecimento pela entrevista\n\nOlá, [nome do entrevistador],\n\nObrigado(a) pela conversa de hoje sobre a vaga de [nome da vaga]. Fiquei ainda mais interessado(a) em contribuir com [área/projeto citado na entrevista].\n\nFico à disposição para qualquer informação adicional.\n\nAtenciosamente,\n" . $dxNome],
    ['Carta de apresentação curta',
        "Olá, [nome],\n\nSou estudante de " . ($dxCurso !== '' ? $dxCurso : '[seu curso]') . " na Fundação Santo André e vi a vaga de [nome da vaga] na [empresa]. Tenho experiência com [habilidade 1] e [habilidade 2], adquiridas em [projeto/atividade].\n\nGostaria de contribuir com a equipe e aprender com o time. Meu currículo segue em anexo.\n\nAtenciosamente,\n" . $dxNome],
    ['Mensagem para pedir indicação (networking)',
        "Olá, [nome]! Tudo bem?\n\nSou " . ($dxNome !== '' ? $dxNome : '[seu nome]') . ", estudante de " . ($dxCurso !== '' ? $dxCurso : '[seu curso]') . " da FSA. Vi que você trabalha na [empresa] e queria entender melhor como é a área de [área]. Você teria 15 minutos para uma conversa rápida?\n\nObrigado(a) desde já!"],
];
?>
<!-- Trilhas -->
<div class="bg-white p-8 rounded-2xl shadow-sm mb-8">
    <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 bg-gradient-to-br from-[#4A9FCA] to-[#3A8FB0] rounded-xl flex items-center justify-center">
            <i class="fas fa-route text-white text-xl"></i>
        </div>
        <div>
            <h3 class="text-2xl font-bold text-gray-900">Trilhas de Carreira</h3>
            <p class="text-gray-600">Roteiros curtos para cada momento</p>
        </div>
    </div>
    <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
        <?php foreach ($dxTrilhas as $t): ?>
            <div class="p-5 bg-gray-50 rounded-xl">
                <div class="w-10 h-10 rounded-lg bg-[#4A9FCA]/10 text-[#2B7FA6] flex items-center justify-center mb-3">
                    <i class="<?= $t[0] === 'fa-linkedin-in' ? 'fab' : 'fas' ?> <?= egh($t[0]) ?>"></i>
                </div>
                <h4 class="font-bold text-gray-900 mb-3"><?= egh($t[1]) ?></h4>
                <ul class="space-y-2">
                    <?php foreach ($t[2] as $i => $passo): ?>
                        <li class="flex items-start gap-2 text-sm text-gray-600">
                            <span class="w-5 h-5 rounded-full bg-white border border-gray-200 text-[10px] font-bold text-gray-400 flex items-center justify-center flex-shrink-0 mt-0.5"><?= $i + 1 ?></span>
                            <?= egh($passo) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Dicas para o curso -->
<div class="bg-white p-8 rounded-2xl shadow-sm mb-8">
    <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 bg-gradient-to-br from-[#F39C12] to-[#E67E22] rounded-xl flex items-center justify-center">
            <i class="fas fa-graduation-cap text-white text-xl"></i>
        </div>
        <div>
            <h3 class="text-2xl font-bold text-gray-900">Dicas para o seu curso</h3>
            <p class="text-gray-600"><?= egh($dxArea[0]) ?><?= $dxCurso !== '' ? ' · ' . egh($dxCurso) : '' ?></p>
        </div>
    </div>
    <div class="grid md:grid-cols-3 gap-4">
        <?php foreach ($dxArea[1] as $d): ?>
            <div class="flex items-start gap-3 p-4 bg-gray-50 rounded-lg">
                <i class="fas fa-check-circle text-[#27AE60] mt-0.5"></i>
                <p class="text-sm text-gray-700 leading-relaxed"><?= egh($d) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modelos prontos -->
<div class="bg-white p-8 rounded-2xl shadow-sm mb-8">
    <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 bg-gradient-to-br from-[#27AE60] to-[#229954] rounded-xl flex items-center justify-center">
            <i class="fas fa-envelope-open-text text-white text-xl"></i>
        </div>
        <div>
            <h3 class="text-2xl font-bold text-gray-900">Modelos Prontos</h3>
            <p class="text-gray-600">Copie, troque o que está entre colchetes e envie</p>
        </div>
    </div>
    <div class="grid lg:grid-cols-3 gap-4">
        <?php foreach ($dxModelos as $m): ?>
            <div class="p-5 border border-gray-100 rounded-xl flex flex-col">
                <h4 class="font-bold text-gray-900 mb-3"><?= egh($m[0]) ?></h4>
                <pre class="text-xs text-gray-600 bg-gray-50 rounded-lg p-3 whitespace-pre-wrap flex-1 mb-4" style="font-family: inherit;"><?= egh($m[1]) ?></pre>
                <button type="button" class="dx-copiar px-4 py-2 border border-gray-200 text-gray-600 rounded-xl hover:bg-gray-50 transition-all text-sm font-semibold" data-texto="<?= egh($m[1]) ?>">
                    <i class="fas fa-copy mr-1"></i> Copiar modelo
                </button>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
(function () {
    function avisar() { if (window.fsaAviso) fsaAviso('O modelo foi copiado. Cole no seu e-mail ou mensagem.', 'Copiado!'); }
    function copiar(texto) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(texto).then(avisar, function () { alternativa(texto); });
        } else { alternativa(texto); }
    }
    function alternativa(texto) {
        var ta = document.createElement('textarea');
        ta.value = texto; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); avisar(); } catch (e) {}
        document.body.removeChild(ta);
    }
    document.querySelectorAll('.dx-copiar').forEach(function (b) {
        b.addEventListener('click', function () { copiar(b.getAttribute('data-texto') || ''); });
    });
})();
</script>
