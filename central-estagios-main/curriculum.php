<?php
require_once 'includes/config.php';
checkAuth();

$pageTitle      = 'Meu Currículo';
$curriculumFile = 'data/userCurriculum.json';

function fmtData($ymd) {
    if (empty($ymd)) return '';
    $m = ['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
    $p = explode('-', $ymd);
    return ($m[intval($p[1] ?? 0)] ?? '') . '/' . ($p[0] ?? '');
}

$defExp  = ['cargo'=>'Estagiário FCSD','empresa'=>'Ford Motor Company','cidade'=>'Santo André, SP','inicio'=>'2025-08-01','fim'=>'','atual'=>'1','descricao'=>'Desenvolvimento de dashboards e análise de dados para tomada de decisões estratégicas.'];
$defForm = ['curso'=>'Ciência de Dados e IA','instituicao'=>'Fundação Santo André','tipo'=>'Graduação','inicio'=>'2023-02-01','fim'=>'2026-12-01','atual'=>'1'];

$defaultData = [
    'nome'        => 'Lavínia Carrasco',
    'email'       => 'lavinia.769029@graduacao.fsa.br',
    'telefone'    => '(11) 98765-4321',
    'cidade'      => 'Santo André, SP',
    'resumo'      => 'Estudante de Ciência de Dados e Inteligência Artificial, com grande interesse e paixão pela área de tecnologia. Perfil dedicado, proativo e comprometido.',
    'habilidades' => ['Proatividade','Trabalho em equipe','Comunicação clara','Adaptabilidade','Iniciativa','Organização'],
    'experiencias'=> [$defExp],
    'formacoes'   => [$defForm],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $raw = str_replace("\r", "", $_POST['habilidades'] ?? '');
    $hab = array_values(array_filter(array_map('trim', explode("\n", $raw))));

    $expCount    = intval($_POST['exp_count'] ?? 0);
    $experiencias = [];
    for ($i = 0; $i < $expCount; $i++) {
        $cargo = trim($_POST["exp_{$i}_cargo"] ?? '');
        if (empty($cargo)) continue;
        $experiencias[] = [
            'cargo'     => $cargo,
            'empresa'   => trim($_POST["exp_{$i}_empresa"]    ?? ''),
            'cidade'    => trim($_POST["exp_{$i}_cidade"]     ?? ''),
            'inicio'    => $_POST["exp_{$i}_inicio"]          ?? '',
            'fim'       => $_POST["exp_{$i}_fim"]             ?? '',
            'atual'     => (($_POST["exp_{$i}_atual"] ?? '0') === '1') ? '1' : '0',
            'descricao' => trim($_POST["exp_{$i}_descricao"]  ?? ''),
        ];
    }
    if (empty($experiencias)) $experiencias = [$defExp];

    $formCount = intval($_POST['form_count'] ?? 0);
    $formacoes = [];
    for ($i = 0; $i < $formCount; $i++) {
        $curso = trim($_POST["form_{$i}_curso"] ?? '');
        if (empty($curso)) continue;
        $formacoes[] = [
            'curso'       => $curso,
            'instituicao' => trim($_POST["form_{$i}_instituicao"] ?? ''),
            'tipo'        => trim($_POST["form_{$i}_tipo"]         ?? ''),
            'inicio'      => $_POST["form_{$i}_inicio"]            ?? '',
            'fim'         => $_POST["form_{$i}_fim"]               ?? '',
            'atual'       => (($_POST["form_{$i}_atual"] ?? '0') === '1') ? '1' : '0',
        ];
    }
    if (empty($formacoes)) $formacoes = [$defForm];

    $userData = [
        'nome'        => trim($_POST['nome']     ?? ''),
        'email'       => trim($_POST['email']    ?? ''),
        'telefone'    => trim($_POST['telefone'] ?? ''),
        'cidade'      => trim($_POST['cidade']   ?? ''),
        'resumo'      => trim($_POST['resumo']   ?? ''),
        'habilidades' => $hab ?: $defaultData['habilidades'],
        'experiencias'=> $experiencias,
        'formacoes'   => $formacoes,
    ];
    saveData('userCurriculum', $userData);
    header('Location: curriculum.php?saved=1');
    exit;
}

$userData = file_exists($curriculumFile)
    ? json_decode(file_get_contents($curriculumFile), true)
    : $defaultData;

// Migração formato antigo → novo
if (!isset($userData['experiencias'])) {
    $userData['experiencias'] = [[
        'cargo'     => $userData['experiencia_cargo']     ?? $defExp['cargo'],
        'empresa'   => $userData['experiencia_empresa']   ?? $defExp['empresa'],
        'cidade'    => $userData['experiencia_cidade']    ?? $defExp['cidade'],
        'inicio'    => $userData['experiencia_inicio']    ?? $defExp['inicio'],
        'fim'       => $userData['experiencia_fim']       ?? '',
        'atual'     => $userData['experiencia_atual']     ?? '1',
        'descricao' => $userData['experiencia_descricao'] ?? $defExp['descricao'],
    ]];
}
if (!isset($userData['formacoes'])) {
    $userData['formacoes'] = [[
        'curso'       => $userData['formacao_curso']       ?? $defForm['curso'],
        'instituicao' => $userData['formacao_instituicao'] ?? $defForm['instituicao'],
        'tipo'        => $userData['formacao_tipo']        ?? 'Graduação',
        'inicio'      => $userData['formacao_inicio']      ?? $defForm['inicio'],
        'fim'         => $userData['formacao_fim']         ?? $defForm['fim'],
        'atual'       => $userData['formacao_atual']       ?? '1',
    ]];
}
foreach ($defaultData as $k => $v) {
    if (!isset($userData[$k])) $userData[$k] = $v;
}

$initialDocuments = [
    ['id'=>1,'title'=>'Python para Data Science',    'institution'=>'Coursera',   'hours'=>'40h'],
    ['id'=>2,'title'=>'Machine Learning Básico',      'institution'=>'Udemy',      'hours'=>'30h'],
    ['id'=>3,'title'=>'Desenvolvimento Web com React','institution'=>'Rocketseat', 'hours'=>'60h'],
];
$documentsFile = 'data/userCertificates.json';
$certs = file_exists($documentsFile)
    ? json_decode(file_get_contents($documentsFile), true)
    : $initialDocuments;

include 'includes/header.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<style>
.cdp-wrapper{position:relative;width:100%;}
.cdp-display{width:100%;padding:12px 14px;cursor:pointer;background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;color:#374151;font-size:14px;box-sizing:border-box;transition:border-color .2s;user-select:none;min-height:46px;display:flex;align-items:center;}
.cdp-display:hover{border-color:#4A9FCA;}
.cdp-display.active{border-color:#4A9FCA;box-shadow:0 0 0 3px rgba(74,159,202,.15);}
.cdp-display.cdp-disabled{opacity:.4;cursor:not-allowed;pointer-events:none;}
.cdp-dropdown{position:absolute;top:calc(100% + 8px);left:0;z-index:99999;background:#fff;border:1px solid #e5e7eb;border-radius:18px;box-shadow:0 12px 40px rgba(0,0,0,.18);width:300px;display:none;overflow:hidden;animation:cdpFade .15s ease;}
@keyframes cdpFade{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.cdp-dropdown.open{display:block;}
.cdp-header{background:linear-gradient(135deg,#4A9FCA,#2B7FA6);padding:14px 16px;display:flex;align-items:center;justify-content:space-between;color:#fff;gap:8px;}
.cdp-header-info{display:flex;flex-direction:column;align-items:center;gap:2px;cursor:pointer;}
.cdp-header-month{font-weight:800;font-size:15px;padding:3px 10px;border-radius:8px;transition:background .15s;}
.cdp-header-month:hover{background:rgba(255,255,255,.2);}
.cdp-header-year{font-size:11px;opacity:.8;padding:2px 8px;border-radius:6px;transition:background .15s;}
.cdp-header-year:hover{background:rgba(255,255,255,.2);}
.cdp-nav{background:rgba(255,255,255,.15);border:none;color:#fff;font-size:20px;cursor:pointer;width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;transition:background .15s;flex-shrink:0;}
.cdp-nav:hover{background:rgba(255,255,255,.3);}
.cdp-tabs{display:flex;border-bottom:1px solid #f3f4f6;background:#fafafa;}
.cdp-tab{flex:1;padding:9px;text-align:center;font-size:11px;font-weight:700;color:#9ca3af;cursor:pointer;text-transform:uppercase;letter-spacing:.8px;transition:all .15s;border-bottom:2px solid transparent;}
.cdp-tab:hover{color:#4A9FCA;}
.cdp-tab.active{color:#4A9FCA;border-bottom-color:#4A9FCA;background:#fff;}
.cdp-weekdays{display:grid;grid-template-columns:repeat(7,1fr);padding:10px 12px 4px;background:#f0f9ff;}
.cdp-weekday{text-align:center;font-size:10px;font-weight:800;color:#4A9FCA;text-transform:uppercase;padding:4px 0;}
.cdp-days{display:grid;grid-template-columns:repeat(7,1fr);padding:8px 12px 10px;gap:2px;}
.cdp-day{aspect-ratio:1;display:flex;align-items:center;justify-content:center;font-size:13px;border-radius:50%;cursor:pointer;color:#374151;font-weight:500;transition:all .15s;border:2px solid transparent;}
.cdp-day:hover{background:#dbeafe;color:#2563eb;}
.cdp-day.selected{background:#4A9FCA!important;color:#fff!important;font-weight:800;}
.cdp-day.today{border-color:#4A9FCA;color:#4A9FCA;font-weight:800;}
.cdp-day.today.selected{border-color:transparent;}
.cdp-day.other-month{color:#d1d5db;font-weight:400;}
.cdp-months{display:grid;grid-template-columns:repeat(3,1fr);padding:14px;gap:6px;}
.cdp-month-item{padding:11px 6px;text-align:center;border-radius:12px;cursor:pointer;font-size:13px;font-weight:600;color:#374151;transition:all .15s;border:2px solid transparent;}
.cdp-month-item:hover{background:#dbeafe;color:#2563eb;}
.cdp-month-item.selected{background:#4A9FCA;color:#fff;}
.cdp-month-item.cur-month{border-color:#4A9FCA;color:#4A9FCA;}
.cdp-month-item.cur-month.selected{border-color:transparent;color:#fff;}
.cdp-years{display:grid;grid-template-columns:repeat(4,1fr);padding:14px;gap:6px;}
.cdp-year-item{padding:10px 4px;text-align:center;border-radius:10px;cursor:pointer;font-size:13px;font-weight:600;color:#374151;transition:all .15s;border:2px solid transparent;}
.cdp-year-item:hover{background:#dbeafe;color:#2563eb;}
.cdp-year-item.selected{background:#4A9FCA;color:#fff;}
.cdp-year-item.cur-year{border-color:#4A9FCA;color:#4A9FCA;}
.cdp-year-item.cur-year.selected{border-color:transparent;color:#fff;}
.cdp-footer{border-top:1px solid #f3f4f6;padding:9px 14px;display:flex;justify-content:space-between;align-items:center;background:#fafafa;}
.cdp-sel-display{font-size:12px;color:#4A9FCA;font-weight:600;}
.cdp-btn{font-size:12px;font-weight:700;padding:6px 14px;border-radius:8px;border:none;cursor:pointer;transition:all .15s;}
.cdp-btn-clear{background:#fee2e2;color:#ef4444;}
.cdp-btn-clear:hover{background:#ef4444;color:#fff;}
.cdp-btn-today{background:#dbeafe;color:#2563eb;}
.cdp-btn-today:hover{background:#4A9FCA;color:#fff;}
</style>

<main class="ml-16 pt-16 bg-gray-50 min-h-screen">
<div class="p-8 max-w-5xl mx-auto">

    <!-- Ações -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h2 class="text-3xl font-bold text-gray-900">Meu Currículo</h2>
            <p class="text-gray-500">Mantenha suas informações atualizadas</p>
        </div>
        <div class="flex gap-3">
            <button id="btnEditar"   onclick="toggleEdit()"      class="flex items-center gap-2 px-4 py-2 bg-[#4A9FCA] text-white rounded-lg hover:bg-[#3A8FB0] transition-colors shadow-sm"><i class="fas fa-edit"></i> Editar</button>
            <button id="btnSalvar"   onclick="salvarCurriculo()" class="hidden flex items-center gap-2 px-4 py-2 bg-[#4A9FCA] text-white rounded-lg hover:bg-[#229954] transition-colors shadow-sm"><i class="fas fa-save"></i> Salvar</button>
            <button id="btnCancelar" onclick="cancelarEdicao()"  class="hidden flex items-center gap-2 px-4 py-2 bg-gray-400 text-white rounded-lg hover:bg-gray-500 transition-colors shadow-sm"><i class="fas fa-times"></i> Cancelar</button>
            <button id="btnPDF"      onclick="baixarPDF()"       class="flex items-center gap-2 px-4 py-2 bg-[#E74C3C] text-white rounded-lg hover:bg-[#C0392B] transition-colors shadow-sm"><i class="fas fa-file-pdf"></i> Baixar PDF</button>
        </div>
    </div>

    <form id="curriculumForm" method="POST" action="curriculum.php">
        <input type="hidden" name="action"     value="save">
        <input type="hidden" name="exp_count"  id="exp_count"  value="<?php echo count($userData['experiencias']); ?>">
        <input type="hidden" name="form_count" id="form_count" value="<?php echo count($userData['formacoes']); ?>">

        <div class="space-y-6">

            <!-- Informações Pessoais -->
            <section class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-3 h-3 bg-[#4A9FCA] rotate-45 flex-shrink-0"></div>
                    <h3 class="text-sm font-bold text-gray-800 uppercase tracking-widest">Informações Pessoais</h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Nome Completo</label>
                        <input type="text" name="nome" value="<?php echo htmlspecialchars($userData['nome']); ?>" class="curriculum-field w-full p-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-700 focus:outline-none" readonly></div>
                    <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">E-mail</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" class="curriculum-field w-full p-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-700 focus:outline-none" readonly></div>
                    <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Telefone</label>
                        <input type="text" name="telefone" value="<?php echo htmlspecialchars($userData['telefone']); ?>" class="curriculum-field w-full p-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-700 focus:outline-none" readonly></div>
                    <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Cidade</label>
                        <input type="text" name="cidade" value="<?php echo htmlspecialchars($userData['cidade']); ?>" class="curriculum-field w-full p-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-700 focus:outline-none" readonly></div>
                </div>
            </section>

            <!-- Resumo -->
            <section class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-3 h-3 bg-[#4A9FCA] rotate-45 flex-shrink-0"></div>
                    <h3 class="text-sm font-bold text-gray-800 uppercase tracking-widest">Resumo Profissional</h3>
                </div>
                <textarea name="resumo" rows="4" class="curriculum-field w-full p-4 bg-gray-50 border border-gray-200 rounded-xl text-gray-700 focus:outline-none min-h-[100px]" readonly><?php echo htmlspecialchars($userData['resumo']); ?></textarea>
            </section>

            <!-- Histórico Profissional -->
            <section class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-3 h-3 bg-[#4A9FCA] rotate-45 flex-shrink-0"></div>
                        <h3 class="text-sm font-bold text-gray-800 uppercase tracking-widest">Histórico Profissional</h3>
                    </div>
                    <button id="btnAddExp" type="button" onclick="addExperiencia()" style="display:none"
                        class="flex items-center gap-1 px-3 py-1.5 bg-[#4A9FCA] text-white text-xs font-bold rounded-lg hover:bg-[#3A8FB0] transition-colors">
                        <i class="fas fa-plus"></i> Adicionar Experiência
                    </button>
                </div>
                <div id="expViewContainer" class="space-y-5">
                    <?php foreach ($userData['experiencias'] as $i => $exp):
                        $ini = fmtData($exp['inicio']);
                        $fim = ($exp['atual'] ?? '0') === '1' ? 'Atual' : fmtData($exp['fim']);
                        $per = $ini . ($fim ? ' — ' . $fim : '');
                    ?>
                    <div class="<?php echo $i > 0 ? 'pt-5 border-t border-gray-100' : ''; ?>">
                        <div class="border-l-4 border-[#4A9FCA] pl-5">
                            <div class="flex justify-between items-start flex-wrap gap-2">
                                <div>
                                    <span class="font-bold text-gray-900"><?php echo htmlspecialchars($exp['cargo']); ?></span>
                                    <span class="text-gray-500 text-sm"> | <?php echo htmlspecialchars($exp['empresa']); ?> — <?php echo htmlspecialchars($exp['cidade']); ?></span>
                                </div>
                                <span class="text-sm font-semibold text-[#4A9FCA] whitespace-nowrap"><?php echo $per; ?></span>
                            </div>
                            <p class="text-sm text-gray-600 mt-2 leading-relaxed"><?php echo htmlspecialchars($exp['descricao']); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div id="expEditContainer" class="hidden space-y-4"></div>
            </section>

            <!-- Formação Acadêmica -->
            <section class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-3 h-3 bg-[#4A9FCA] rotate-45 flex-shrink-0"></div>
                        <h3 class="text-sm font-bold text-gray-800 uppercase tracking-widest">Formação Acadêmica</h3>
                    </div>
                    <button id="btnAddForm" type="button" onclick="addFormacao()" style="display:none"
                        class="flex items-center gap-1 px-3 py-1.5 bg-[#4A9FCA] text-white text-xs font-bold rounded-lg hover:bg-[#4A9FCA] transition-colors">
                        <i class="fas fa-plus"></i> Adicionar Formação
                    </button>
                </div>
                <div id="formViewContainer" class="space-y-5">
                    <?php foreach ($userData['formacoes'] as $i => $form):
                        $ini       = fmtData($form['inicio']);
                        $fim       = fmtData($form['fim']);
                        $prevLabel = ($form['atual'] ?? '0') === '1' ?  $fim : $fim;
                        $periodo   = $ini . ($fim ? ' — ' . $prevLabel : '');
                    ?>
                    <div class="<?php echo $i > 0 ? 'pt-5 border-t border-gray-100' : ''; ?>">
                        <div class="border-l-4 border-[#4A9FCA] pl-5">
                            <div class="flex justify-between items-start flex-wrap gap-2">
                                <div>
                                    <p class="font-bold text-gray-900"><?php echo htmlspecialchars($form['instituicao']); ?></p>
                                    <p class="text-sm text-gray-600 mt-0.5"><?php echo htmlspecialchars($form['tipo']); ?> : <?php echo htmlspecialchars($form['curso']); ?></p>
                                </div>
                                <span class="text-sm font-semibold text-[#4A9FCA] whitespace-nowrap"><?php echo $periodo; ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div id="formEditContainer" class="hidden space-y-4"></div>
            </section>

            <!-- Habilidades -->
            <section class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-3 h-3 bg-[#4A9FCA] rotate-45 flex-shrink-0"></div>
                    <h3 class="text-sm font-bold text-gray-800 uppercase tracking-widest">Habilidades e Competências</h3>
                </div>
                <div id="skillsView" class="grid grid-cols-2 gap-x-8 gap-y-2.5">
                    <?php foreach ($userData['habilidades'] as $skill): ?>
                    <div class="flex items-start gap-2 text-sm text-gray-700">
                        <span class="mt-1.5 w-2 h-2 rounded-full bg-[#4A9FCA] flex-shrink-0"></span>
                        <?php echo htmlspecialchars(trim($skill)); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div id="skillsEdit" class="hidden">
                    <p class="text-xs text-gray-400 mb-2">Uma habilidade por linha — pressione <kbd class="px-1.5 py-0.5 bg-gray-100 border border-gray-200 rounded text-xs font-mono">Enter</kbd> para a próxima</p>
                    <textarea name="habilidades" rows="8" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-700 focus:outline-none focus:border-[#4A9FCA] text-sm" placeholder="Proatividade&#10;Trabalho em equipe&#10;..."><?php echo htmlspecialchars(implode("\n", $userData['habilidades'])); ?></textarea>
                </div>
            </section>

            <!-- Cursos Complementares -->
            <section class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-3 h-3 bg-[#4A9FCA] rotate-45 flex-shrink-0"></div>
                    <h3 class="text-sm font-bold text-gray-800 uppercase tracking-widest">Cursos Complementares</h3>
                </div>
                <?php if (empty($certs)): ?>
                    <p class="text-gray-400 text-sm italic">Nenhum certificado encontrado.</p>
                <?php else: ?>
                <table class="w-full text-sm">
                    <thead><tr class="border-b-2 border-gray-100">
                        <th class="text-left py-3 px-2 text-xs font-bold text-gray-400 uppercase">Curso</th>
                        <th class="text-left py-3 px-2 text-xs font-bold text-gray-400 uppercase">Instituição</th>
                        <th class="text-center py-3 px-2 text-xs font-bold text-gray-400 uppercase">Carga</th>
                        <th class="text-center py-3 px-2 text-xs font-bold text-gray-400 uppercase">Status</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($certs as $cert): ?>
                    <tr class="border-b border-gray-50 hover:bg-gray-50 transition-colors">
                        <td class="py-4 px-2 font-semibold text-gray-800"><?php echo htmlspecialchars($cert['title']); ?></td>
                        <td class="py-4 px-2 text-gray-500"><?php echo htmlspecialchars($cert['institution']); ?></td>
                        <td class="py-4 px-2 text-center text-[#4A9FCA] font-semibold"><?php echo htmlspecialchars($cert['hours']); ?></td>
                        <td class="py-4 px-2 text-center">
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <i class="fas fa-check text-[9px]"></i> Aprovado
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </section>

        </div>
    </form>
</div>
</main>

<!-- ===== TEMPLATE PDF ===== -->
<div id="curriculumPDF" style="position:fixed;left:-9999px;top:0;width:794px;background:#fff;font-family:Arial,Helvetica,sans-serif;z-index:-1;box-sizing:border-box;">
    <div style="height:5px;background:#4A9FCA;"></div>
    <div style="padding:36px 52px 22px;border-bottom:2px solid #e8e8e8;">
        <div style="font-size:26px;font-weight:900;color:#1a1a1a;margin-bottom:6px;"><?php echo htmlspecialchars($userData['nome']); ?></div>
        <div style="font-size:11px;color:#666;line-height:2;">
            ✉ <?php echo htmlspecialchars($userData['email']); ?> &nbsp;&nbsp;
            ☎ <?php echo htmlspecialchars($userData['telefone']); ?> &nbsp;&nbsp;
            ⚲ <?php echo htmlspecialchars($userData['cidade']); ?>
        </div>
    </div>
    <div style="padding:26px 52px 40px;">

        <!-- Resumo -->
        <div style="margin-bottom:20px;">
            <div style="border-bottom:1.5px solid #4A9FCA;padding-bottom:5px;margin-bottom:9px;">
                <span style="font-size:11px;font-weight:800;color:#4A9FCA;text-transform:uppercase;letter-spacing:1.5px;">◆ &nbsp;Resumo Profissional</span>
            </div>
            <div style="font-size:12px;color:#444;line-height:1.7;padding-left:14px;"><?php echo htmlspecialchars($userData['resumo']); ?></div>
        </div>

        <!-- Histórico Profissional -->
        <div style="margin-bottom:20px;">
            <div style="border-bottom:1.5px solid #4A9FCA;padding-bottom:5px;margin-bottom:9px;">
                <span style="font-size:11px;font-weight:800;color:#4A9FCA;text-transform:uppercase;letter-spacing:1.5px;">◆ &nbsp;Histórico Profissional</span>
            </div>
            <?php foreach ($userData['experiencias'] as $exp):
                $ini = fmtData($exp['inicio']);
                $fim = ($exp['atual'] ?? '0') === '1' ? 'Atual' : fmtData($exp['fim']);
                $per = $ini . ($fim ? ' — ' . $fim : '');
            ?>
            <div style="padding-left:14px;margin-bottom:12px;">
                <table style="width:100%;border-collapse:collapse;margin-bottom:4px;">
                    <tr>
                        <td style="font-size:12px;color:#222;">
                            <span style="font-weight:700;"><?php echo htmlspecialchars($exp['cargo']); ?></span>
                            <span style="color:#555;"> &nbsp;|&nbsp; <?php echo htmlspecialchars($exp['empresa']); ?> — <?php echo htmlspecialchars($exp['cidade']); ?></span>
                        </td>
                        <td style="text-align:right;font-size:11px;color:#4A9FCA;font-weight:600;white-space:nowrap;width:130px;"><?php echo $per; ?></td>
                    </tr>
                </table>
                <div style="font-size:12px;color:#555;line-height:1.6;"><?php echo htmlspecialchars($exp['descricao']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Formação -->
        <div style="margin-bottom:20px;">
            <div style="border-bottom:1.5px solid #4A9FCA;padding-bottom:5px;margin-bottom:9px;">
                <span style="font-size:11px;font-weight:800;color:#4A9FCA;text-transform:uppercase;letter-spacing:1.5px;">◆ &nbsp;Formação Acadêmica</span>
            </div>
            <?php foreach ($userData['formacoes'] as $form):
                $ini       = fmtData($form['inicio']);
                $fim       = fmtData($form['fim']);
                $prevLabel = ($form['atual'] ?? '0') === '1' ? $fim : $fim;
                $periodo   = $ini . ($fim ? ' — ' . $prevLabel : '');
            ?>
            <div style="padding-left:14px;margin-bottom:10px;">
                <table style="width:100%;border-collapse:collapse;">
                    <tr>
                        <td style="vertical-align:top;">
                            <div style="font-size:13px;font-weight:700;color:#4A9FCA;margin-bottom:3px;"><?php echo htmlspecialchars($form['instituicao']); ?></div>
                            <div style="font-size:12px;color:#555;"><?php echo htmlspecialchars($form['tipo']); ?> : <?php echo htmlspecialchars($form['curso']); ?></div>
                        </td>
                        <td style="text-align:right;vertical-align:top;font-size:11px;color:#4A9FCA;font-weight:600;white-space:nowrap;width:160px;"><?php echo $periodo; ?></td>
                    </tr>
                </table>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Habilidades -->
        <div style="margin-bottom:20px;">
            <div style="border-bottom:1.5px solid #4A9FCA;padding-bottom:5px;margin-bottom:9px;">
                <span style="font-size:11px;font-weight:800;color:#4A9FCA;text-transform:uppercase;letter-spacing:1.5px;">◆ &nbsp;Habilidades e Competências</span>
            </div>
            <div style="padding-left:14px;">
                <?php
                $habs = $userData['habilidades'];
                $mid  = ceil(count($habs) / 2);
                $c1   = array_slice($habs, 0, $mid);
                $c2   = array_slice($habs, $mid);
                ?>
                <table style="width:100%;border-collapse:collapse;">
                    <tr>
                        <td style="vertical-align:top;width:50%;padding-right:20px;">
                            <?php foreach ($c1 as $s): ?>
                            <div style="font-size:12px;color:#444;padding:4px 0;"><span style="color:#4A9FCA;font-weight:bold;margin-right:6px;">•</span><?php echo htmlspecialchars(trim($s)); ?></div>
                            <?php endforeach; ?>
                        </td>
                        <td style="vertical-align:top;width:50%;">
                            <?php foreach ($c2 as $s): ?>
                            <div style="font-size:12px;color:#444;padding:4px 0;"><span style="color:#4A9FCA;font-weight:bold;margin-right:6px;">•</span><?php echo htmlspecialchars(trim($s)); ?></div>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Certificados -->
        <?php if (!empty($certs)): ?>
        <div style="margin-bottom:20px;">
            <div style="border-bottom:1.5px solid #4A9FCA;padding-bottom:5px;margin-bottom:9px;">
                <span style="font-size:11px;font-weight:800;color:#4A9FCA;text-transform:uppercase;letter-spacing:1.5px;">◆ &nbsp;Cursos Complementares</span>
            </div>
            <div style="padding-left:14px;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead><tr style="border-bottom:1px solid #e5e7eb;">
                        <th style="text-align:left;padding:6px 4px;font-size:10px;color:#999;font-weight:700;text-transform:uppercase;">Curso</th>
                        <th style="text-align:left;padding:6px 4px;font-size:10px;color:#999;font-weight:700;text-transform:uppercase;">Instituição</th>
                        <th style="text-align:center;padding:6px 4px;font-size:10px;color:#999;font-weight:700;text-transform:uppercase;">Carga</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($certs as $cert): ?>
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:8px 4px;font-size:12px;font-weight:600;color:#1a1a1a;"><?php echo htmlspecialchars($cert['title']); ?></td>
                        <td style="padding:8px 4px;font-size:12px;color:#666;"><?php echo htmlspecialchars($cert['institution']); ?></td>
                        <td style="padding:8px 4px;font-size:12px;color:#4A9FCA;font-weight:600;text-align:center;"><?php echo htmlspecialchars($cert['hours']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
const curriculumData = <?php echo json_encode($userData, JSON_UNESCAPED_UNICODE); ?>;
const TIPOS_FORMACAO = ['Graduação','Técnico','Pós-Graduação','MBA','Mestrado','Doutorado'];

function escHtml(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function mkEl(tag,cls){const e=document.createElement(tag);if(cls)e.className=cls;return e;}

// ===== DATEPICKER =====
const dpRegistry={};

class DatePicker{
    constructor(hiddenEl,disabled=false){
        this.hidden=hiddenEl; this.dis=disabled; this.view='days';
        this.today=new Date();
        this.MONTHS=['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
        this.MONTHS_S=['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
        const raw=hiddenEl.value||'';
        if(raw){const p=raw.split('-');this.sel={y:+p[0],m:+p[1]-1,d:+(p[2]||1)};this.vY=this.sel.y;this.vM=this.sel.m;}
        else{this.sel=null;this.vY=this.today.getFullYear();this.vM=this.today.getMonth();}
        this.build();this.render();
    }
    build(){
        const w=this.hidden.parentNode;
        this.disp=mkEl('div','cdp-display'+(this.dis?' cdp-disabled':''));
        this.disp.innerHTML=this.sel?`<span style="color:#374151">${this.fmtFull(this.sel.d,this.sel.m,this.sel.y)}</span>`:`<span style="color:#9ca3af">Selecionar data...</span>`;
        this.dd=mkEl('div','cdp-dropdown');
        w.appendChild(this.disp);w.appendChild(this.dd);
        if(!this.dis)this.disp.addEventListener('click',e=>{e.stopPropagation();this.toggle();});
        this.dd.addEventListener('click',e=>e.stopPropagation());
        document.addEventListener('click',()=>this.close());
    }
    toggle(){document.querySelectorAll('.cdp-dropdown.open').forEach(el=>{if(el!==this.dd)el.classList.remove('open');});this.dd.classList.toggle('open');this.disp.classList.toggle('active',this.dd.classList.contains('open'));}
    close(){this.dd.classList.remove('open');this.disp.classList.remove('active');}
    setDisabled(v){this.dis=v;this.disp.classList.toggle('cdp-disabled',v);if(v)this.close();}
    render(){
        this.dd.innerHTML='';
        const hdr=mkEl('div','cdp-header');
        const prev=mkEl('button','cdp-nav');prev.innerHTML='&#8249;';prev.type='button';prev.onclick=()=>this.nav(-1);
        const info=mkEl('div','cdp-header-info');
        info.onclick=()=>{if(this.view==='days')this.view='months';else if(this.view==='months')this.view='years';this.render();};
        const mSpan=mkEl('div','cdp-header-month');
        const ySpan=mkEl('div','cdp-header-year');
        if(this.view==='days'){mSpan.textContent=this.MONTHS[this.vM];ySpan.textContent=this.vY+' ▾';}
        else if(this.view==='months'){mSpan.textContent=this.vY;ySpan.textContent='Selecione o mês ▾';}
        else{const s=Math.floor(this.vY/16)*16;mSpan.textContent=`${s} – ${s+15}`;ySpan.textContent='Selecione o ano';}
        info.append(mSpan,ySpan);
        const next=mkEl('button','cdp-nav');next.innerHTML='&#8250;';next.type='button';next.onclick=()=>this.nav(1);
        hdr.append(prev,info,next);this.dd.appendChild(hdr);
        const tabs=mkEl('div','cdp-tabs');
        [['days','Dia'],['months','Mês'],['years','Ano']].forEach(([v,lbl])=>{
            const t=mkEl('div','cdp-tab'+(this.view===v?' active':''));t.textContent=lbl;
            t.onclick=()=>{this.view=v;this.render();};tabs.appendChild(t);
        });this.dd.appendChild(tabs);
        if(this.view==='days')this.renderDays();
        else if(this.view==='months')this.renderMonths();
        else this.renderYears();
        const foot=mkEl('div','cdp-footer');
        const selD=mkEl('div','cdp-sel-display');
        selD.textContent=this.sel?`${String(this.sel.d).padStart(2,'0')}/${String(this.sel.m+1).padStart(2,'0')}/${this.sel.y}`:'';
        const bClr=mkEl('button','cdp-btn cdp-btn-clear');bClr.type='button';bClr.textContent='Limpar';
        bClr.onclick=()=>{this.sel=null;this.hidden.value='';this.disp.innerHTML=`<span style="color:#9ca3af">Selecionar data...</span>`;this.close();};
        const bTod=mkEl('button','cdp-btn cdp-btn-today');bTod.type='button';bTod.textContent='Hoje';
        bTod.onclick=()=>{const t=this.today;this.pick(t.getFullYear(),t.getMonth(),t.getDate());};
        foot.append(bClr,selD,bTod);this.dd.appendChild(foot);
    }
    renderDays(){
        const wk=mkEl('div','cdp-weekdays');
        ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'].forEach(d=>{const e=mkEl('div','cdp-weekday');e.textContent=d;wk.appendChild(e);});
        this.dd.appendChild(wk);
        const grid=mkEl('div','cdp-days');
        const first=new Date(this.vY,this.vM,1).getDay();
        const total=new Date(this.vY,this.vM+1,0).getDate();
        const prev=new Date(this.vY,this.vM,0).getDate();
        for(let i=first-1;i>=0;i--){const pY=this.vM===0?this.vY-1:this.vY,pM=this.vM===0?11:this.vM-1;grid.appendChild(this.dayEl(prev-i,pY,pM,true));}
        for(let d=1;d<=total;d++){
            const e=this.dayEl(d,this.vY,this.vM,false);
            if(d===this.today.getDate()&&this.vM===this.today.getMonth()&&this.vY===this.today.getFullYear())e.classList.add('today');
            if(this.sel&&d===this.sel.d&&this.vM===this.sel.m&&this.vY===this.sel.y)e.classList.add('selected');
            grid.appendChild(e);
        }
        const rem=(first+total)%7;
        if(rem>0){for(let d=1;d<=7-rem;d++){const nY=this.vM===11?this.vY+1:this.vY,nM=this.vM===11?0:this.vM+1;grid.appendChild(this.dayEl(d,nY,nM,true));}}
        this.dd.appendChild(grid);
    }
    dayEl(d,y,m,other){const e=mkEl('div','cdp-day'+(other?' other-month':''));e.textContent=d;e.onclick=()=>this.pick(y,m,d);return e;}
    renderMonths(){
        const grid=mkEl('div','cdp-months');
        this.MONTHS_S.forEach((name,i)=>{
            const e=mkEl('div','cdp-month-item');e.textContent=name;
            if(i===this.today.getMonth()&&this.vY===this.today.getFullYear())e.classList.add('cur-month');
            if(this.sel&&i===this.sel.m&&this.vY===this.sel.y)e.classList.add('selected');
            e.onclick=()=>{this.vM=i;this.view='days';this.render();};grid.appendChild(e);
        });this.dd.appendChild(grid);
    }
    renderYears(){
        const start=Math.floor(this.vY/16)*16;
        const grid=mkEl('div','cdp-years');
        for(let y=start;y<start+16;y++){
            const e=mkEl('div','cdp-year-item');e.textContent=y;
            if(y===this.today.getFullYear())e.classList.add('cur-year');
            if(this.sel&&y===this.sel.y)e.classList.add('selected');
            e.onclick=()=>{this.vY=y;this.view='months';this.render();};grid.appendChild(e);
        }this.dd.appendChild(grid);
    }
    nav(dir){
        if(this.view==='days'){this.vM+=dir;if(this.vM>11){this.vM=0;this.vY++;}if(this.vM<0){this.vM=11;this.vY--;}}
        else if(this.view==='months'){this.vY+=dir;}
        else{this.vY+=dir*16;}
        this.render();
    }
    pick(y,m,d){
        this.sel={y,m,d};this.vY=y;this.vM=m;
        const mm=String(m+1).padStart(2,'0'),dd=String(d).padStart(2,'0');
        this.hidden.value=`${y}-${mm}-${dd}`;
        this.disp.innerHTML=`<span style="color:#374151">${this.fmtFull(d,m,y)}</span>`;
        this.close();this.render();
    }
    fmtFull(d,m,y){return `${String(d).padStart(2,'0')} de ${this.MONTHS[m]} de ${y}`;}
}

function initDP(fieldId,disabled=false){
    const el=document.getElementById(fieldId);
    if(el&&!dpRegistry[fieldId])dpRegistry[fieldId]=new DatePicker(el,disabled);
}

// ===== BUILD EXPERIÊNCIA =====
function buildExpHTML(idx,data={}){
    const isAtual=(data.atual||'0')==='1';
    return `
    <div class="exp-entry bg-gray-50 border-2 border-gray-100 rounded-2xl p-5" data-idx="${idx}">
        <div class="flex justify-between items-center mb-4">
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 bg-[#4A9FCA] rotate-45"></div>
                <span class="text-xs font-bold text-[#4A9FCA] uppercase tracking-wide">Experiência</span>
            </div>
            <button type="button" onclick="removeExp(this)" class="remove-exp-btn flex items-center gap-1 text-xs text-red-400 hover:text-red-600 font-semibold px-2 py-1 rounded-lg hover:bg-red-50 transition-colors">
                <i class="fas fa-trash-alt"></i> Remover
            </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Cargo *</label>
                <input type="text" name="exp_${idx}_cargo" value="${escHtml(data.cargo||'')}" placeholder="Ex: Estagiário de TI" class="w-full p-3 bg-white border border-gray-200 rounded-xl text-gray-700 focus:outline-none focus:border-[#4A9FCA]"></div>
            <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Empresa</label>
                <input type="text" name="exp_${idx}_empresa" value="${escHtml(data.empresa||'')}" placeholder="Nome da empresa" class="w-full p-3 bg-white border border-gray-200 rounded-xl text-gray-700 focus:outline-none focus:border-[#4A9FCA]"></div>
            <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Cidade</label>
                <input type="text" name="exp_${idx}_cidade" value="${escHtml(data.cidade||'')}" placeholder="Cidade, Estado" class="w-full p-3 bg-white border border-gray-200 rounded-xl text-gray-700 focus:outline-none focus:border-[#4A9FCA]"></div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4 items-end">
            <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Data de Início</label>
                <div class="cdp-wrapper"><input type="hidden" name="exp_${idx}_inicio" id="dp_exp_${idx}_inicio" value="${escHtml(data.inicio||'')}"></div></div>
            <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Data de Término</label>
                <div class="cdp-wrapper"><input type="hidden" name="exp_${idx}_fim" id="dp_exp_${idx}_fim" value="${escHtml(!isAtual?(data.fim||''):'')}"></div></div>
            <div class="flex items-center gap-2 pb-1">
                <input type="hidden" name="exp_${idx}_atual" id="exp_${idx}_atual_val" value="${isAtual?'1':'0'}">
                <input type="checkbox" id="exp_${idx}_atual_cb" ${isAtual?'checked':''} onchange="toggleExpAtual(${idx})" class="w-4 h-4 accent-[#4A9FCA] cursor-pointer">
                <label for="exp_${idx}_atual_cb" class="text-sm text-gray-600 font-medium cursor-pointer">Emprego atual</label>
            </div>
        </div>
        <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Descrição das Atividades</label>
            <textarea name="exp_${idx}_descricao" rows="3" placeholder="Descreva suas principais atividades..." class="w-full p-3 bg-white border border-gray-200 rounded-xl text-gray-700 focus:outline-none focus:border-[#4A9FCA]">${escHtml(data.descricao||'')}</textarea></div>
    </div>`;
}

// ===== BUILD FORMAÇÃO =====
function buildFormHTML(idx,data={}){
    const isAtual=(data.atual||'0')==='1';
    const tipoOpts=TIPOS_FORMACAO.map(t=>`<option value="${t}" ${(data.tipo||'')===t?'selected':''}>${t}</option>`).join('');
    return `
    <div class="form-entry bg-gray-50 border-2 border-gray-100 rounded-2xl p-5" data-idx="${idx}">
        <div class="flex justify-between items-center mb-4">
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 bg-[#4A9FCA] rotate-45"></div>
                <span class="text-xs font-bold text-[#4A9FCA] uppercase tracking-wide">Formação</span>
            </div>
            <button type="button" onclick="removeForm(this)" class="remove-form-btn flex items-center gap-1 text-xs text-red-400 hover:text-red-600 font-semibold px-2 py-1 rounded-lg hover:bg-red-50 transition-colors">
                <i class="fas fa-trash-alt"></i> Remover
            </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Curso *</label>
                <input type="text" name="form_${idx}_curso" value="${escHtml(data.curso||'')}" placeholder="Ex: Ciência de Dados" class="w-full p-3 bg-white border border-gray-200 rounded-xl text-gray-700 focus:outline-none focus:border-[#4A9FCA]"></div>
            <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Instituição</label>
                <input type="text" name="form_${idx}_instituicao" value="${escHtml(data.instituicao||'')}" placeholder="Nome da instituição" class="w-full p-3 bg-white border border-gray-200 rounded-xl text-gray-700 focus:outline-none focus:border-[#4A9FCA]"></div>
            <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Tipo</label>
                <select name="form_${idx}_tipo" class="w-full p-3 bg-white border border-gray-200 rounded-xl text-gray-700 focus:outline-none focus:border-[#4A9FCA]">${tipoOpts}</select></div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Data de Início</label>
                <div class="cdp-wrapper"><input type="hidden" name="form_${idx}_inicio" id="dp_form_${idx}_inicio" value="${escHtml(data.inicio||'')}"></div></div>
            <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Previsão de Conclusão</label>
                <div class="cdp-wrapper"><input type="hidden" name="form_${idx}_fim" id="dp_form_${idx}_fim" value="${escHtml(data.fim||'')}"></div></div>
            <div class="flex items-center gap-2 pb-1">
                <input type="hidden" name="form_${idx}_atual" id="form_${idx}_atual_val" value="${isAtual?'1':'0'}">
                <input type="checkbox" id="form_${idx}_atual_cb" ${isAtual?'checked':''} onchange="toggleFormAtual(${idx})" class="w-4 h-4 accent-[#27AE60] cursor-pointer">
                <label for="form_${idx}_atual_cb" class="text-sm text-gray-600 font-medium cursor-pointer">Cursando atualmente</label>
            </div>
        </div>
    </div>`;
}

function initExpDPs(idx,data={}){
    initDP(`dp_exp_${idx}_inicio`,false);
    initDP(`dp_exp_${idx}_fim`,(data.atual||'0')==='1');
}
function initFormDPs(idx){
    initDP(`dp_form_${idx}_inicio`,false);
    initDP(`dp_form_${idx}_fim`,false);
}

function toggleExpAtual(idx){
    const cb=document.getElementById(`exp_${idx}_atual_cb`);
    const val=document.getElementById(`exp_${idx}_atual_val`);
    val.value=cb.checked?'1':'0';
    const dp=dpRegistry[`dp_exp_${idx}_fim`];
    if(dp){dp.setDisabled(cb.checked);if(cb.checked){dp.sel=null;dp.hidden.value='';dp.disp.innerHTML=`<span style="color:#9ca3af">Selecionar data...</span>`;}}
}
function toggleFormAtual(idx){
    const cb=document.getElementById(`form_${idx}_atual_cb`);
    document.getElementById(`form_${idx}_atual_val`).value=cb.checked?'1':'0';
}

function removeExp(btn){
    const entry=btn.closest('.exp-entry');
    entry.querySelectorAll('input,select,textarea').forEach(el=>el.disabled=true);
    entry.style.display='none';
    updateRemoveExpBtns();
}
function removeForm(btn){
    const entry=btn.closest('.form-entry');
    entry.querySelectorAll('input,select,textarea').forEach(el=>el.disabled=true);
    entry.style.display='none';
    updateRemoveFormBtns();
}
function updateRemoveExpBtns(){
    const vis=[...document.querySelectorAll('#expEditContainer .exp-entry')].filter(e=>e.style.display!=='none');
    vis.forEach(e=>{const b=e.querySelector('.remove-exp-btn');if(b)b.style.display=vis.length<=1?'none':'flex';});
}
function updateRemoveFormBtns(){
    const vis=[...document.querySelectorAll('#formEditContainer .form-entry')].filter(e=>e.style.display!=='none');
    vis.forEach(e=>{const b=e.querySelector('.remove-form-btn');if(b)b.style.display=vis.length<=1?'none':'flex';});
}

let expCounter=0, formCounter=0, modoEdicao=false;

function addExperiencia(){
    const idx=expCounter++;
    document.getElementById('expEditContainer').insertAdjacentHTML('beforeend',buildExpHTML(idx));
    document.getElementById('exp_count').value=expCounter;
    initExpDPs(idx);
    updateRemoveExpBtns();
    document.getElementById('expEditContainer').lastElementChild.scrollIntoView({behavior:'smooth',block:'nearest'});
}
function addFormacao(){
    const idx=formCounter++;
    document.getElementById('formEditContainer').insertAdjacentHTML('beforeend',buildFormHTML(idx));
    document.getElementById('form_count').value=formCounter;
    initFormDPs(idx);
    updateRemoveFormBtns();
    document.getElementById('formEditContainer').lastElementChild.scrollIntoView({behavior:'smooth',block:'nearest'});
}

function toggleEdit(){
    modoEdicao=true;
    document.querySelectorAll('.curriculum-field').forEach(f=>{f.removeAttribute('readonly');f.classList.add('border-[#4A9FCA]','bg-white');f.classList.remove('bg-gray-50');});
    document.getElementById('skillsView').classList.add('hidden');
    document.getElementById('skillsEdit').classList.remove('hidden');

    const expCont=document.getElementById('expEditContainer');
    expCont.innerHTML=''; expCounter=0;
    Object.keys(dpRegistry).forEach(k=>delete dpRegistry[k]);
    curriculumData.experiencias.forEach((exp,i)=>{expCont.insertAdjacentHTML('beforeend',buildExpHTML(i,exp));expCounter++;});
    document.getElementById('exp_count').value=expCounter;
    curriculumData.experiencias.forEach((exp,i)=>initExpDPs(i,exp));
    expCont.classList.remove('hidden');
    document.getElementById('expViewContainer').classList.add('hidden');
    document.getElementById('btnAddExp').style.display='flex';
    updateRemoveExpBtns();

    const formCont=document.getElementById('formEditContainer');
    formCont.innerHTML=''; formCounter=0;
    curriculumData.formacoes.forEach((form,i)=>{formCont.insertAdjacentHTML('beforeend',buildFormHTML(i,form));formCounter++;});
    document.getElementById('form_count').value=formCounter;
    curriculumData.formacoes.forEach((_,i)=>initFormDPs(i));
    formCont.classList.remove('hidden');
    document.getElementById('formViewContainer').classList.add('hidden');
    document.getElementById('btnAddForm').style.display='flex';
    updateRemoveFormBtns();

    document.getElementById('btnEditar').classList.add('hidden');
    document.getElementById('btnSalvar').classList.remove('hidden');
    document.getElementById('btnCancelar').classList.remove('hidden');
}

function cancelarEdicao(){
    modoEdicao=false;
    document.querySelectorAll('.curriculum-field').forEach(f=>{f.setAttribute('readonly',true);f.classList.remove('border-[#4A9FCA]','bg-white');f.classList.add('bg-gray-50');});
    document.getElementById('skillsView').classList.remove('hidden');
    document.getElementById('skillsEdit').classList.add('hidden');
    document.getElementById('expViewContainer').classList.remove('hidden');
    document.getElementById('expEditContainer').classList.add('hidden');
    document.getElementById('formViewContainer').classList.remove('hidden');
    document.getElementById('formEditContainer').classList.add('hidden');
    document.getElementById('btnAddExp').style.display='none';
    document.getElementById('btnAddForm').style.display='none';
    document.getElementById('btnEditar').classList.remove('hidden');
    document.getElementById('btnSalvar').classList.add('hidden');
    document.getElementById('btnCancelar').classList.add('hidden');
    Object.keys(dpRegistry).forEach(k=>delete dpRegistry[k]);
}

function salvarCurriculo(){document.getElementById('curriculumForm').submit();}

async function baixarPDF(){
    const btn=document.getElementById('btnPDF');
    btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Gerando...';
    btn.disabled=true;
    const el=document.getElementById('curriculumPDF');
    el.style.left='0px';el.style.zIndex='9999';el.style.top='0px';
    try{
        const canvas=await html2canvas(el,{scale:2,useCORS:true,allowTaint:true,backgroundColor:'#ffffff',logging:false,windowWidth:794,width:794,height:el.scrollHeight});
        const{jsPDF}=window.jspdf;
        const pdf=new jsPDF('p','mm','a4');
        const pageW=210,pageH=297,imgW=pageW;
        const imgH=(canvas.height*imgW)/canvas.width;
        if(imgH<=pageH){pdf.addImage(canvas.toDataURL('image/jpeg',1.0),'JPEG',0,0,imgW,imgH);}
        else{let y=0;while(y<imgH){if(y>0)pdf.addPage();pdf.addImage(canvas.toDataURL('image/jpeg',1.0),'JPEG',0,-y,imgW,imgH);y+=pageH;}}
        pdf.save('curriculo-<?php echo strtolower(str_replace(' ','-',$userData['nome'])); ?>.pdf');
    }catch(err){console.error(err);alert('Erro ao gerar PDF.');}
    el.style.left='-9999px';el.style.zIndex='-1';
    btn.innerHTML='<i class="fas fa-file-pdf"></i> Baixar PDF';
    btn.disabled=false;
}

document.addEventListener('keydown',e=>{if(e.key==='Escape'&&modoEdicao)cancelarEdicao();});
</script>

<?php include 'includes/footer.php'; ?>
