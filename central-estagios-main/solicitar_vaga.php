<?php
require_once 'includes/config.php';
checkAuth(); // qualquer aluno logado pode acessar

header('Content-Type: text/html; charset=UTF-8');
$pageTitle = 'Solicitar Vaga de Estágio';

$usuarios = loadData('usuarios');
$usuarioLogado = null;
foreach ($usuarios as $u) {
    if ($u['id'] == ($_SESSION['user_id'] ?? 0)) {
        $usuarioLogado = $u;
        break;
    }
}
if (!$usuarioLogado) {
    header('Location: index.php');
    exit;
}

$fsaCourses = getFsaCourses();

/* =========================================================================
 * SOLICITACOES DE VAGA ENVIADAS PELOS ALUNOS
 * -------------------------------------------------------------------------
 * Ficam guardadas separadas das vagas publicadas (platform_jobs), em um
 * arquivo proprio (job_requests), ate que um admin aprove ou rejeite.
 * ========================================================================= */
$jobRequests = loadData('job_requests');
if (!is_array($jobRequests)) {
    $jobRequests = [];
}

$mensagemSucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'solicitar') {
    $coursesSelected = $_POST['courses'] ?? [];

    $novaSolicitacao = [
        'id'              => time(),
        'studentId'       => $usuarioLogado['id'],
        'studentName'     => $usuarioLogado['nome'] ?? '',
        'studentEmail'    => $usuarioLogado['email'] ?? '',
        'title'           => trim($_POST['title'] ?? ''),
        'company'         => trim($_POST['company'] ?? ''),
        'location'        => trim($_POST['location'] ?? ''),
        'type'            => $_POST['type'] ?? 'Presencial',
        'hours'           => trim($_POST['hours'] ?? ''),
        'salary'          => trim($_POST['salary'] ?? ''),
        'description'     => trim($_POST['description'] ?? ''),
        'requirements'    => trim($_POST['requirements'] ?? ''),
        'benefits'        => trim($_POST['benefits'] ?? ''),
        'courses'         => implode(', ', $coursesSelected),
        'applicationLink' => trim($_POST['applicationLink'] ?? ''),
        'featured'        => isset($_POST['featured']),
        'status'          => 'pendente', // pendente | aprovada | rejeitada
        'motivoRejeicao'  => '',
        'createdAt'       => date('d/m/Y H:i'),
    ];

    array_unshift($jobRequests, $novaSolicitacao);
    saveData('job_requests', $jobRequests);

    $mensagemSucesso = true;
}

/* Solicitacoes do proprio aluno, para ele acompanhar o status */
$minhasSolicitacoes = array_values(array_filter($jobRequests, function ($r) use ($usuarioLogado) {
    return $r['studentId'] == $usuarioLogado['id'];
}));

/* Contadores para os cards de resumo do historico */
$totaisReq = ['pendente' => 0, 'aprovada' => 0, 'rejeitada' => 0];
foreach ($minhasSolicitacoes as $r) {
    if (isset($totaisReq[$r['status']])) $totaisReq[$r['status']]++;
}

include 'includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .req-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 0.3rem 0.85rem;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .req-status-pendente  { background: #fef3c7; color: #92400e; }
    .req-status-aprovada  { background: #d1fae5; color: #065f46; }
    .req-status-rejeitada { background: #fee4e2; color: #b42318; }

    .req-card {
        background: #ffffff;
        border: 1px solid #eef0f2;
        border-radius: 1.25rem;
        padding: 1.5rem;
        box-shadow: 0 1px 3px rgba(16, 24, 40, 0.05);
    }

    /* ===================== Campos do formulario ===================== */
    .req-field-label {
        display: block;
        font-size: 0.82rem;
        font-weight: 600;
        color: #344054;
        margin-bottom: 0.4rem;
    }
    .req-field-label .req-required { color: #d92d20; }
    .req-field-hint {
        font-size: 0.72rem;
        font-weight: 400;
        color: #98a2b3;
        margin-left: 4px;
    }
    .req-input,
    .req-select,
    .req-textarea {
        width: 100%;
        border: 1.5px solid #e4e7ec;
        border-radius: 0.85rem;
        padding: 0.6rem 0.9rem;
        font-size: 0.875rem;
        color: #101828;
        background: #fff;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .req-input:focus,
    .req-select:focus,
    .req-textarea:focus {
        outline: none;
        border-color: #4A9FCA;
        box-shadow: 0 0 0 3px rgba(74, 159, 202, 0.15);
    }
    .req-input { height: 42px; }
    .req-select[multiple] {
        height: auto;
        padding: 0.5rem;
    }
    .req-select[multiple] option {
        padding: 0.4rem 0.6rem;
        border-radius: 0.5rem;
    }
    .req-select[multiple] option:checked {
        background: #4A9FCA linear-gradient(0deg, #4A9FCA 0%, #4A9FCA 100%);
        color: #fff;
    }

    /* ===================== Secoes do formulario ===================== */
    .req-section-title {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        font-size: 0.85rem;
        font-weight: 700;
        color: #1d2939;
        margin-bottom: 1rem;
        padding-bottom: 0.6rem;
        border-bottom: 1px solid #f2f4f7;
    }
    .req-section-icon {
        width: 30px;
        height: 30px;
        border-radius: 0.6rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        flex-shrink: 0;
    }

    .req-featured-box {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        border: 1.5px dashed #fde68a;
        background: #fffbeb;
        border-radius: 0.9rem;
        padding: 0.9rem 1rem;
    }
    .req-featured-box input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: #d97706;
        cursor: pointer;
    }
</style>

<main class="ml-16 pt-16 bg-gray-50 min-h-screen">
<div class="p-8 max-w-4xl mx-auto">

    <div class="mb-8">
        <h2 class="text-2xl font-bold text-gray-900 mb-2">Solicitar Vaga de Estágio</h2>
        <p class="text-gray-600">Encontrou uma vaga que não está na plataforma? Envie os dados para a coordenação avaliar e publicar.</p>
    </div>

    <?php if ($mensagemSucesso): ?>
        <div class="mb-6 p-4 rounded-xl bg-green-50 border border-green-200 text-green-800 flex items-center gap-3">
            <i class="fas fa-check-circle text-xl"></i>
            <div>
                <p class="font-semibold">Solicitação enviada com sucesso!</p>
                <p class="text-sm">A coordenação vai revisar os dados. Você pode acompanhar o status logo abaixo.</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- ===================== FORMULARIO DE SOLICITACAO ===================== -->
    <div class="req-card mb-10">
        <div class="flex items-start gap-4 mb-6">
            <div class="w-12 h-12 rounded-2xl bg-blue-100 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-plus-circle text-[#2B7FA6] text-xl"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900">Nova Solicitação</h3>
                <p class="text-sm text-gray-500">Preencha o máximo de informações possível — os mesmos dados que aparecem em uma vaga publicada.</p>
            </div>
        </div>

        <form method="POST" action="solicitar_vaga.php" accept-charset="UTF-8" class="space-y-8">
            <input type="hidden" name="action" value="solicitar">

            <!-- ===== Secao 1: Dados basicos ===== -->
            <div>
                <div class="req-section-title">
                    <div class="req-section-icon bg-blue-100 text-[#2B7FA6]">
                        <i class="fas fa-building"></i>
                    </div>
                    Dados da Vaga e Empresa
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="req-field-label">Título da Vaga <span class="req-required">*</span></label>
                        <input type="text" name="title" class="req-input" placeholder="Ex: Estágio em Desenvolvimento de Software" required>
                    </div>
                    <div>
                        <label class="req-field-label">Empresa <span class="req-required">*</span></label>
                        <input type="text" name="company" class="req-input" placeholder="Nome da empresa" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="req-field-label">Localização<span class="req-required">*</span></label>
                        <input type="text" name="location" class="req-input" placeholder="Ex: Santo André, SP">
                    </div>
                    <div>
                        <label class="req-field-label">Tipo</label>
                        <select name="type" class="req-select req-input">
                            <option value="Presencial">Presencial</option>
                            <option value="Remoto">Remoto</option>
                            <option value="Hibrido">Híbrido</option>
                        </select>
                    </div>
                    <div>
                        <label class="req-field-label">Bolsa Auxílio</label>
                        <input type="text" name="salary" class="req-input" placeholder="Ex: R$ 1.200,00">
                    </div>
                </div>
            </div>

            <!-- ===== Secao 2: Condicoes ===== -->
            <div>
                <div class="req-section-title">
                    <div class="req-section-icon bg-purple-100 text-purple-600">
                        <i class="fas fa-clock"></i>
                    </div>
                    Condições e Candidatura
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="req-field-label">Carga Horária</label>
                        <input type="text" name="hours" placeholder="Ex: 30h semanais" class="req-input">
                    </div>
                    <div>
                        <label class="req-field-label">
                            Link de Candidatura <span class="req-required">*</span>
                            <span class="req-field-hint">(site da empresa, formulário, etc.)</span>
                        </label>
                        <input type="url" name="applicationLink" placeholder="https://..." class="req-input" required>
                    </div>
                </div>
            </div>

            <!-- ===== Secao 3: Cursos ===== -->
            <div>
                <div class="req-section-title">
                    <div class="req-section-icon bg-teal-100 text-teal-600">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    Cursos Relacionados
                </div>

                <select name="courses[]" multiple class="req-select req-input" style="height: 8.5rem;">
                    <?php foreach ($fsaCourses as $course): ?>
                        <option value="<?php echo htmlspecialchars($course); ?>"><?php echo htmlspecialchars($course); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-400 mt-2"><i class="fas fa-circle-info mr-1"></i>Segure Ctrl (ou Cmd no Mac) para selecionar mais de um curso.</p>
            </div>

            <!-- ===== Secao 4: Descricao completa ===== -->
            <div>
                <div class="req-section-title">
                    <div class="req-section-icon bg-orange-100 text-orange-600">
                        <i class="fas fa-align-left"></i>
                    </div>
                    Descrição Completa
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="req-field-label">Descrição <span class="req-required">*</span></label>
                        <textarea name="description" rows="3" class="req-textarea" placeholder="Descreva as atividades e o dia a dia do estágio..." required></textarea>
                    </div>
                    <div>
                        <label class="req-field-label">Requisitos</label>
                        <textarea name="requirements" rows="3" class="req-textarea" placeholder="Ex: cursando a partir do 3º semestre, conhecimento em..."></textarea>
                    </div>
                    <div>
                        <label class="req-field-label">Benefícios</label>
                        <textarea name="benefits" rows="2" class="req-textarea" placeholder="Ex: vale-transporte, vale-refeição, plano de saúde..."></textarea>
                    </div>
                </div>
            </div>

            <!-- ===== Destaque ===== -->
            <div class="req-featured-box">
                <input type="checkbox" name="featured" id="featuredCheck">
                <label for="featuredCheck" class="text-sm text-yellow-800 font-medium cursor-pointer flex items-center gap-2">
                    <i class="fas fa-star text-yellow-500"></i> Sugerir como Vaga em Destaque
                </label>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full py-3 bg-[#4A9FCA] text-white rounded-xl font-semibold hover:bg-[#3A8FB0] transition-all shadow-sm">
                    <i class="fas fa-paper-plane mr-2"></i>Enviar Solicitação para Aprovação
                </button>
            </div>
        </form>
    </div>

    <!-- ===================== HISTORICO DE SOLICITACOES DO ALUNO ===================== -->
    <div>
        <h3 class="text-lg font-bold text-gray-900 mb-4"><i class="fas fa-history text-gray-400 mr-2"></i>Minhas Solicitações</h3>

        <?php if (!empty($minhasSolicitacoes)): ?>
        <!-- Cards de resumo -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-2xl shadow-sm p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-yellow-100 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-clock text-yellow-600"></i>
                </div>
                <div>
                    <p class="text-xl font-bold text-gray-900"><?php echo $totaisReq['pendente']; ?></p>
                    <p class="text-xs text-gray-500">Em análise</p>
                </div>
            </div>
            <div class="bg-white rounded-2xl shadow-sm p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-check-circle text-green-600"></i>
                </div>
                <div>
                    <p class="text-xl font-bold text-gray-900"><?php echo $totaisReq['aprovada']; ?></p>
                    <p class="text-xs text-gray-500">Aprovadas</p>
                </div>
            </div>
            <div class="bg-white rounded-2xl shadow-sm p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-times-circle text-red-600"></i>
                </div>
                <div>
                    <p class="text-xl font-bold text-gray-900"><?php echo $totaisReq['rejeitada']; ?></p>
                    <p class="text-xs text-gray-500">Não aprovadas</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($minhasSolicitacoes)): ?>
            <div class="text-center py-12 bg-white rounded-2xl border-2 border-dashed border-gray-200">
                <i class="fas fa-inbox text-4xl text-gray-300 mb-3"></i>
                <p class="text-gray-500 font-medium">Você ainda não enviou nenhuma solicitação.</p>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($minhasSolicitacoes as $req):
                    $statusInfo = match ($req['status']) {
                        'aprovada'  => ['Aprovada e publicada', 'req-status-aprovada', 'fa-check-circle'],
                        'rejeitada' => ['Não aprovada', 'req-status-rejeitada', 'fa-times-circle'],
                        default     => ['Aguardando análise', 'req-status-pendente', 'fa-clock'],
                    };
                ?>
                <div class="req-card flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-11 h-11 shrink-0 rounded-xl bg-gray-100 flex items-center justify-center">
                            <i class="fas fa-briefcase text-gray-400"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($req['title']); ?></p>
                            <p class="text-sm text-gray-500 truncate"><?php echo htmlspecialchars($req['company']); ?> &bull; Enviada em <?php echo htmlspecialchars($req['createdAt']); ?></p>
                            <?php if ($req['status'] === 'rejeitada' && !empty($req['motivoRejeicao'])): ?>
                                <p class="text-xs text-red-500 mt-1"><i class="fas fa-exclamation-circle mr-1"></i>Motivo: <?php echo htmlspecialchars($req['motivoRejeicao']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="req-status-pill <?php echo $statusInfo[1]; ?>">
                        <i class="fas <?php echo $statusInfo[2]; ?>"></i> <?php echo $statusInfo[0]; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>
</main>

<?php include 'includes/footer.php'; ?>
