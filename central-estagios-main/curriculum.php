<?php
require_once 'includes/config.php';
checkAuth();

$pageTitle = 'Meu Currículo';

$curriculumFile = 'data/userCurriculum.json';

$defaultData = [
    'nome' => 'Lavínia Carrasco',
    'email' => 'lavinia.769029@graduacao.fsa.br',
    'telefone' => '(11) 98765-4321',
    'cidade' => 'Santo André, SP',
    'objetivo' => 'Busco oportunidade de estágio na área de Ciência de Dados e Inteligência Artificial para aplicar conhecimentos teóricos e desenvolver habilidades práticas em projetos desafiadores.',
    'habilidades' => ['Python', 'JavaScript', 'React', 'SQL', 'Data Analysis', 'Machine Learning'],
    'experiencia_cargo' => 'Estagiário de TI',
    'experiencia_empresa' => 'Tech Solutions',
    'experiencia_periodo' => 'Jan 2025 - Atual',
    'experiencia_descricao' => 'Desenvolvimento de dashboards e análise de dados para tomada de decisões estratégicas.',
    'formacao_curso' => 'Ciência de Dados e IA',
    'formacao_instituicao' => 'Fundação Santo André',
    'formacao_periodo' => '2026 - Cursando',
];

// Salvar dados editados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $userData = [
        'nome'                   => $_POST['nome'] ?? $defaultData['nome'],
        'email'                  => $_POST['email'] ?? $defaultData['email'],
        'telefone'               => $_POST['telefone'] ?? $defaultData['telefone'],
        'cidade'                 => $_POST['cidade'] ?? $defaultData['cidade'],
        'objetivo'               => $_POST['objetivo'] ?? $defaultData['objetivo'],
        'habilidades'            => array_filter(array_map('trim', explode(',', $_POST['habilidades'] ?? ''))),
        'experiencia_cargo'      => $_POST['experiencia_cargo'] ?? $defaultData['experiencia_cargo'],
        'experiencia_empresa'    => $_POST['experiencia_empresa'] ?? $defaultData['experiencia_empresa'],
        'experiencia_periodo'    => $_POST['experiencia_periodo'] ?? $defaultData['experiencia_periodo'],
        'experiencia_descricao'  => $_POST['experiencia_descricao'] ?? $defaultData['experiencia_descricao'],
        'formacao_curso'         => $_POST['formacao_curso'] ?? $defaultData['formacao_curso'],
        'formacao_instituicao'   => $_POST['formacao_instituicao'] ?? $defaultData['formacao_instituicao'],
        'formacao_periodo'       => $_POST['formacao_periodo'] ?? $defaultData['formacao_periodo'],
    ];
    saveData('userCurriculum', $userData);
    header('Location: curriculum.php?saved=1');
    exit;
}

// Carregar dados salvos ou usar padrão
$userData = file_exists($curriculumFile)
    ? json_decode(file_get_contents($curriculumFile), true)
    : $defaultData;

// Certificados sincronizados com documents.php
$initialDocuments = [
    ['id' => 1, 'title' => 'Python para Data Science',       'institution' => 'Coursera',    'hours' => '40h', 'status' => 'approved'],
    ['id' => 2, 'title' => 'Machine Learning Básico',         'institution' => 'Udemy',       'hours' => '30h', 'status' => 'approved'],
    ['id' => 3, 'title' => 'Desenvolvimento Web com React',   'institution' => 'Rocketseat',  'hours' => '60h', 'status' => 'approved'],
];

$documentsFile = 'data/userCertificates.json';
$certificadosSincronizados = file_exists($documentsFile)
    ? json_decode(file_get_contents($documentsFile), true)
    : $initialDocuments;

include 'includes/header.php';
?>

<!-- Estilos para impressão/PDF -->
<style>
    @media print {
        .no-print { display: none !important; }
        main { margin-left: 0 !important; padding-top: 0 !important; }
        .print-header { display: block !important; }
        body { background: white !important; }
        section { break-inside: avoid; box-shadow: none !important; border: 1px solid #e5e7eb !important; }
        input, textarea { border: none !important; background: transparent !important; padding: 0 !important; }
    }
    .print-header { display: none; }
</style>

<main class="ml-16 pt-16 bg-gray-50 min-h-screen">
    <div class="p-8 max-w-5xl mx-auto">

        <!-- Header de Ações -->
        <div class="flex justify-between items-center mb-8 no-print">
            <div>
                <h2 class="text-3xl font-bold text-gray-900">Meu Currículo</h2>
                <p class="text-gray-500">Mantenha suas informações atualizadas</p>
            </div>
            <div class="flex gap-3">
                <button id="btnEditar" onclick="toggleEdit()" class="flex items-center gap-2 px-4 py-2 bg-[#4A9FCA] text-white rounded-lg hover:bg-[#3A8FB0] transition-colors shadow-sm">
                    <i class="fas fa-edit"></i> Editar Currículo
                </button>
                <button id="btnSalvar" onclick="salvarCurriculo()" class="hidden flex items-center gap-2 px-4 py-2 bg-[#27AE60] text-white rounded-lg hover:bg-[#229954] transition-colors shadow-sm">
                    <i class="fas fa-save"></i> Salvar
                </button>
                <button id="btnCancelar" onclick="cancelarEdicao()" class="hidden flex items-center gap-2 px-4 py-2 bg-gray-400 text-white rounded-lg hover:bg-gray-500 transition-colors shadow-sm">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button onclick="baixarPDF()" class="flex items-center gap-2 px-4 py-2 bg-[#E74C3C] text-white rounded-lg hover:bg-[#C0392B] transition-colors shadow-sm">
                    <i class="fas fa-file-pdf"></i> Baixar PDF
                </button>
            </div>
        </div>

        <!-- Formulário de edição -->
        <form id="curriculumForm" method="POST" action="curriculum.php">
            <input type="hidden" name="action" value="save">

            <div class="space-y-6" id="curriculumContent">

                <!-- Informações Pessoais -->
                <section class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-gray-800 mb-6">Informações Pessoais</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Nome Completo</label>
                            <input type="text" name="nome" value="<?php echo htmlspecialchars($userData['nome']); ?>"
                                class="curriculum-field w-full p-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-700 focus:outline-none" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">E-mail</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>"
                                class="curriculum-field w-full p-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-700 focus:outline-none" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Telefone</label>
                            <input type="text" name="telefone" value="<?php echo htmlspecialchars($userData['telefone']); ?>"
                                class="curriculum-field w-full p-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-700 focus:outline-none" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Cidade</label>
                            <input type="text" name="cidade" value="<?php echo htmlspecialchars($userData['cidade']); ?>"
                                class="curriculum-field w-full p-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-700 focus:outline-none" readonly>
                        </div>
                    </div>
                </section>

                <!-- Objetivos Profissionais -->
                <section class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Objetivos Profissionais</h3>
                    <textarea name="objetivo"
                        class="curriculum-field w-full p-4 bg-gray-50 border border-gray-200 rounded-xl text-gray-700 focus:outline-none min-h-[100px]"
                        readonly><?php echo htmlspecialchars($userData['objetivo']); ?></textarea>
                </section>

                <!-- Experiência Profissional -->
                <section class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Experiência Profissional</h3>
                    <div class="bg-blue-50/30 border-l-4 border-[#4A9FCA] p-6 rounded-r-xl space-y-3">
                        <input type="text" name="experiencia_cargo"
                            value="<?php echo htmlspecialchars($userData['experiencia_cargo']); ?>"
                            class="curriculum-field w-full p-2 bg-transparent font-bold text-gray-900 border-b border-transparent focus:outline-none" readonly>
                        <input type="text" name="experiencia_empresa"
                            value="<?php echo htmlspecialchars($userData['experiencia_empresa']); ?>"
                            class="curriculum-field w-full p-2 bg-transparent text-sm text-gray-600 border-b border-transparent focus:outline-none" readonly>
                        <input type="text" name="experiencia_periodo"
                            value="<?php echo htmlspecialchars($userData['experiencia_periodo']); ?>"
                            class="curriculum-field w-full p-2 bg-transparent text-xs text-gray-400 border-b border-transparent focus:outline-none" readonly>
                        <textarea name="experiencia_descricao"
                            class="curriculum-field w-full p-2 bg-transparent text-sm text-gray-700 border-b border-transparent focus:outline-none min-h-[60px]"
                            readonly><?php echo htmlspecialchars($userData['experiencia_descricao']); ?></textarea>
                    </div>
                </section>

                <!-- Formação Acadêmica -->
                <section class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Formação Acadêmica</h3>
                    <div class="bg-green-50/30 border-l-4 border-[#27AE60] p-6 rounded-r-xl space-y-3">
                        <input type="text" name="formacao_curso"
                            value="<?php echo htmlspecialchars($userData['formacao_curso']); ?>"
                            class="curriculum-field w-full p-2 bg-transparent font-bold text-gray-900 border-b border-transparent focus:outline-none" readonly>
                        <input type="text" name="formacao_instituicao"
                            value="<?php echo htmlspecialchars($userData['formacao_instituicao']); ?>"
                            class="curriculum-field w-full p-2 bg-transparent text-sm text-gray-600 border-b border-transparent focus:outline-none" readonly>
                        <input type="text" name="formacao_periodo"
                            value="<?php echo htmlspecialchars($userData['formacao_periodo']); ?>"
                            class="curriculum-field w-full p-2 bg-transparent text-xs text-gray-400 border-b border-transparent focus:outline-none" readonly>
                    </div>
                </section>

                <!-- Habilidades -->
                <section class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Habilidades</h3>

                    <!-- Preview das habilidades (modo leitura) -->
                    <div id="skillsPreview" class="flex flex-wrap gap-2 mb-3">
                        <?php foreach ($userData['habilidades'] as $skill): ?>
                            <span class="px-4 py-1.5 bg-[#4A9FCA] text-white text-sm rounded-full font-medium shadow-sm">
                                <?php echo htmlspecialchars(trim($skill)); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>

                    <!-- Campo de edição (modo edição) -->
                    <div id="skillsEdit" class="hidden">
                        <p class="text-xs text-gray-400 mb-2">Separe as habilidades por vírgula</p>
                        <input type="text" name="habilidades"
                            value="<?php echo htmlspecialchars(implode(', ', $userData['habilidades'])); ?>"
                            class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-700 focus:outline-none focus:border-[#4A9FCA]"
                            placeholder="Python, JavaScript, React...">
                    </div>
                </section>

                <!-- Cursos Complementares (Sincronizados) -->
                <section class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex items-center gap-3 mb-6">
                        <h3 class="text-lg font-bold text-gray-800">Cursos Complementares</h3>
                        <span class="px-2 py-0.5 bg-blue-100 text-[#4A9FCA] text-[10px] font-bold rounded uppercase tracking-wider flex items-center gap-1">
                            <span class="w-1.5 h-1.5 bg-[#4A9FCA] rounded-full animate-pulse"></span> Sincronizado
                        </span>
                    </div>

                    <div class="bg-yellow-50 border border-yellow-100 p-4 rounded-xl mb-6 flex items-start gap-3 no-print">
                        <i class="fas fa-lightbulb text-yellow-500 mt-1"></i>
                        <p class="text-sm text-yellow-800">
                            <strong>Atenção:</strong> Os cursos complementares são gerenciados na aba <strong>Documentos</strong>.
                        </p>
                    </div>

                    <div class="space-y-3">
                        <?php if (empty($certificadosSincronizados)): ?>
                            <p class="text-gray-400 text-sm italic">Nenhum certificado aprovado encontrado em documentos.</p>
                        <?php else: ?>
                            <?php foreach ($certificadosSincronizados as $cert): ?>
                                <div class="flex justify-between items-center p-4 bg-blue-50/50 border border-blue-100 rounded-xl">
                                    <div>
                                        <div class="flex items-center gap-2 mb-1">
                                            <h4 class="font-bold text-gray-800"><?php echo htmlspecialchars($cert['title']); ?></h4>
                                            <span class="px-2 py-0.5 bg-[#27AE60] text-white text-[10px] rounded-full font-bold flex items-center gap-1">
                                                <i class="fas fa-check text-[8px]"></i> Aprovado
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($cert['institution']); ?> —
                                            <?php echo htmlspecialchars($cert['hours']); ?>
                                        </p>
                                    </div>
                                    <i class="fas fa-certificate text-blue-200 text-2xl"></i>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

            </div>
        </form>

    </div>
</main>

<script>
    let modoEdicao = false;
    let valoresOriginais = {};

    function toggleEdit() {
        modoEdicao = true;
        const campos = document.querySelectorAll('.curriculum-field');

        // Salvar valores originais para cancelar depois
        campos.forEach(campo => {
            valoresOriginais[campo.name] = campo.value;
            campo.removeAttribute('readonly');
            campo.classList.add('border-[#4A9FCA]', 'bg-white', 'focus:ring-2', 'focus:ring-blue-100');
            campo.classList.remove('bg-gray-50');
        });

        // Habilidades
        document.getElementById('skillsPreview').classList.add('hidden');
        document.getElementById('skillsEdit').classList.remove('hidden');

        // Botões
        document.getElementById('btnEditar').classList.add('hidden');
        document.getElementById('btnSalvar').classList.remove('hidden');
        document.getElementById('btnCancelar').classList.remove('hidden');
    }

    function cancelarEdicao() {
        modoEdicao = false;
        const campos = document.querySelectorAll('.curriculum-field');

        // Restaurar valores originais
        campos.forEach(campo => {
            campo.value = valoresOriginais[campo.name] ?? campo.value;
            campo.setAttribute('readonly', true);
            campo.classList.remove('border-[#4A9FCA]', 'bg-white', 'focus:ring-2', 'focus:ring-blue-100');
            campo.classList.add('bg-gray-50');
        });

        // Habilidades
        document.getElementById('skillsPreview').classList.remove('hidden');
        document.getElementById('skillsEdit').classList.add('hidden');

        // Botões
        document.getElementById('btnEditar').classList.remove('hidden');
        document.getElementById('btnSalvar').classList.add('hidden');
        document.getElementById('btnCancelar').classList.add('hidden');
    }

    function salvarCurriculo() {
        document.getElementById('curriculumForm').submit();
    }

    function baixarPDF() {
        // Esconde elementos desnecessários e imprime
        const nooPrint = document.querySelectorAll('.no-print');
        nooPrint.forEach(el => el.style.display = 'none');

        window.print();

        // Restaura após impressão
        setTimeout(() => {
            nooPrint.forEach(el => el.style.display = '');
        }, 1000);
    }
</script>

<?php include 'includes/footer.php'; ?>
