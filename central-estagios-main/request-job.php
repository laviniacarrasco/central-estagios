<?php
require_once 'includes/config.php';
checkAuth();

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

/* =========================================================================
 * BLOQUEIO POR MA CONDUTA
 * ========================================================================= */
$isBloqueado = $usuarioLogado['bloqueado'] ?? false;
if ($isBloqueado) {
    header('Location: dashboard.php?bloqueado=1');
    exit;
}

$fsaCourses = getFsaCourses();

$jobRequests = loadData('job_requests');
if (!is_array($jobRequests)) {
    $jobRequests = [];
}

$mensagemSucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'solicitar') {
    $coursesSelected = $_POST['courses'] ?? [];
    $editId = intval($_POST['edit_id'] ?? 0);

    $dadosFormulario = [
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
    ];

    $editouExistente = false;

    if ($editId > 0) {
        foreach ($jobRequests as &$reqExistente) {
            if (($reqExistente['id'] ?? 0) == $editId
                && $reqExistente['studentId'] == $usuarioLogado['id']
                && $reqExistente['status'] === 'rejeitada') {

                $reqExistente = array_merge($reqExistente, $dadosFormulario, [
                    'status'         => 'pendente',
                    'motivoRejeicao' => '',
                    'createdAt'      => date('d/m/Y H:i'),
                ]);
                $editouExistente = true;
                break;
            }
        }
        unset($reqExistente);
    }

    if (!$editouExistente) {
        $novaSolicitacao = array_merge($dadosFormulario, [
            'id'              => time(),
            'studentId'       => $usuarioLogado['id'],
            'studentName'     => $usuarioLogado['nome'] ?? '',
            'studentEmail'    => $usuarioLogado['email'] ?? '',
            'status'          => 'pendente', // pendente | aprovada | rejeitada
            'motivoRejeicao'  => '',
            'createdAt'       => date('d/m/Y H:i'),
        ]);
        array_unshift($jobRequests, $novaSolicitacao);
    }

    saveData('job_requests', $jobRequests);

    $mensagemSucesso = true;
}

/* Solicitacoes do proprio aluno, mais recentes primeiro */
$minhasSolicitacoes = array_values(array_filter($jobRequests, function ($r) use ($usuarioLogado) {
    return $r['studentId'] == $usuarioLogado['id'];
}));
usort($minhasSolicitacoes, fn($a, $b) => ($b['id'] ?? 0) <=> ($a['id'] ?? 0));

/* Contadores para os cards de resumo */
$totaisReq = ['pendente' => 0, 'aprovada' => 0, 'rejeitada' => 0];
foreach ($minhasSolicitacoes as $r) {
    if (isset($totaisReq[$r['status']])) $totaisReq[$r['status']]++;
}

/* Apenas as ativas (pendentes) ficam na tela principal.
   Assim que sao aprovadas ou rejeitadas, saem da tela e passam
   a existir somente dentro da caixinha "Ver todas". */
$solicitacoesAtivas = array_values(array_filter($minhasSolicitacoes, fn($r) => $r['status'] === 'pendente'));

function jobStatusInfo($status) {
    return match ($status) {
        'aprovada'  => ['Aprovada e publicada', 'req-status-aprovada', 'fa-check-circle'],
        'rejeitada' => ['Não aprovada', 'req-status-rejeitada', 'fa-times-circle'],
        default     => ['Aguardando análise', 'req-status-pendente', 'fa-clock'],
    };
}

/* Gera o card completo de uma solicitacao (usado tanto na tela
   principal quanto dentro do modal "Ver todas"). */
function renderJobCard(array $req, array $fsaCourses): string {
    $isAprovada  = $req['status'] === 'aprovada';
    $isRejeitada = $req['status'] === 'rejeitada';

    $textosEtapas = $isRejeitada
        ? ['Solicitação enviada', 'Em análise', 'Não aprovada']
        : ['Solicitação enviada', 'Em análise', 'Aprovada'];

    $etapaAtualExibicao = $isAprovada || $isRejeitada ? 2 : 1;
    $statusInfo = jobStatusInfo($req['status']);
    $cursosDoReq = array_map('trim', explode(',', $req['courses'] ?? ''));

    ob_start();
    ?>
    <div class="req-card">
        <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 shrink-0 rounded-xl bg-gray-100 flex items-center justify-center">
                    <i class="fas fa-briefcase text-gray-400"></i>
                </div>
                <div class="min-w-0">
                    <p class="font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($req['title']); ?></p>
                    <p class="text-sm text-gray-500 truncate"><?php echo htmlspecialchars($req['company']); ?> &bull; Enviada em <?php echo htmlspecialchars($req['createdAt']); ?></p>
                </div>
            </div>
            <span class="req-status-pill <?php echo $statusInfo[1]; ?>">
                <i class="fas <?php echo $statusInfo[2]; ?>"></i> <?php echo $statusInfo[0]; ?>
            </span>
        </div>

        <div class="flex items-center mb-2">
            <?php foreach ($textosEtapas as $i => $texto): ?>
                <?php
                    $ehErroAqui = $isRejeitada && $i === 2;
                    $completo   = $i < $etapaAtualExibicao;
                    $atual      = $i === $etapaAtualExibicao;
                    $circCls = $ehErroAqui ? 'erro' : (($completo || $atual) ? 'ativo' : '');
                    $txtCls  = $ehErroAqui ? 'erro' : (($completo || $atual) ? 'ativo' : '');
                ?>
                <div class="flex-1 flex flex-col items-center relative">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold z-10 req-step-circle <?php echo $circCls; ?>">
                        <?php if ($ehErroAqui): ?>
                            <i class="fas fa-times"></i>
                        <?php elseif ($completo): ?>
                            <i class="fas fa-check"></i>
                        <?php else: ?>
                            <?php echo $i + 1; ?>
                        <?php endif; ?>
                    </div>
                    <span class="text-[11px] text-center mt-1 px-1 req-step-text <?php echo $txtCls; ?>"><?php echo $texto; ?></span>
                </div>
                <?php if ($i < 2): ?>
                    <?php $linhaCls = $isRejeitada ? ($i === 0 ? 'ativa' : 'erro') : ($i < $etapaAtualExibicao ? 'ativa' : ''); ?>
                    <div class="flex-1 h-0.5 -mt-4 req-step-line <?php echo $linhaCls; ?>"></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php if ($isRejeitada): ?>
            <div class="bg-red-50 border border-red-100 text-red-700 text-sm rounded-xl p-3 mt-3 mb-3">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                <?php echo htmlspecialchars($req['motivoRejeicao'] ?: 'A coordenação não aprovou esta solicitação.'); ?>
            </div>

            <button type="button" onclick="toggleReenvio(<?php echo (int) $req['id']; ?>)"
                class="px-4 py-2 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white text-sm font-semibold rounded-xl transition-all">
                <i class="fas fa-redo mr-1.5"></i> Editar e reenviar
            </button>

            <div id="reenvioBox_<?php echo (int) $req['id']; ?>" class="req-reenvio-box hidden">
                <p class="text-sm font-semibold text-gray-700 mb-3">
                    <i class="fas fa-edit text-[#4A9FCA] mr-1"></i> Corrija os dados e reenvie para nova análise
                </p>

                <form method="POST" action="request-job.php" accept-charset="UTF-8" class="space-y-4">
                    <input type="hidden" name="action" value="solicitar">
                    <input type="hidden" name="edit_id" value="<?php echo (int) $req['id']; ?>">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="req-field-label">Título da Vaga <span class="req-required">*</span></label>
                            <input type="text" name="title" class="req-input" value="<?php echo htmlspecialchars($req['title']); ?>" required>
                        </div>
                        <div>
                            <label class="req-field-label">Empresa <span class="req-required">*</span></label>
                            <input type="text" name="company" class="req-input" value="<?php echo htmlspecialchars($req['company']); ?>" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="req-field-label">Localização</label>
                            <input type="text" name="location" class="req-input" value="<?php echo htmlspecialchars($req['location']); ?>">
                        </div>
                        <div>
                            <label class="req-field-label">Tipo</label>
                            <select name="type" class="req-select req-input">
                                <?php foreach (['Presencial', 'Remoto', 'Hibrido'] as $tipoOpt): ?>
                                    <option value="<?php echo $tipoOpt; ?>" <?php echo ($req['type'] ?? '') === $tipoOpt ? 'selected' : ''; ?>>
                                        <?php echo $tipoOpt === 'Hibrido' ? 'Híbrido' : $tipoOpt; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="req-field-label">Bolsa Auxílio</label>
                            <input type="text" name="salary" class="req-input" value="<?php echo htmlspecialchars($req['salary']); ?>">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="req-field-label">Carga Horária</label>
                            <input type="text" name="hours" class="req-input" value="<?php echo htmlspecialchars($req['hours']); ?>">
                        </div>
                        <div>
                            <label class="req-field-label">Link de Candidatura <span class="req-required">*</span></label>
                            <input type="url" name="applicationLink" class="req-input" value="<?php echo htmlspecialchars($req['applicationLink']); ?>" required>
                        </div>
                    </div>

                    <div>
                        <label class="req-field-label">Cursos Relacionados</label>
                        <select name="courses[]" multiple class="req-select req-input" style="height: 7rem;">
                            <?php foreach ($fsaCourses as $course): ?>
                                <option value="<?php echo htmlspecialchars($course); ?>" <?php echo in_array($course, $cursosDoReq) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="req-field-label">Descrição <span class="req-required">*</span></label>
                        <textarea name="description" rows="3" class="req-textarea" required><?php echo htmlspecialchars($req['description']); ?></textarea>
                    </div>
                    <div>
                        <label class="req-field-label">Requisitos</label>
                        <textarea name="requirements" rows="2" class="req-textarea"><?php echo htmlspecialchars($req['requirements']); ?></textarea>
                    </div>
                    <div>
                        <label class="req-field-label">Benefícios</label>
                        <textarea name="benefits" rows="2" class="req-textarea"><?php echo htmlspecialchars($req['benefits']); ?></textarea>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" name="featured" <?php echo !empty($req['featured']) ? 'checked' : ''; ?> class="w-4 h-4 accent-[#d97706]">
                        <i class="fas fa-star text-yellow-500"></i> Sugerir como Vaga em Destaque
                    </label>

                    <div class="flex items-center gap-3 pt-1">
                        <button type="submit"
                            class="px-4 py-2 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white text-sm font-semibold rounded-xl transition-all">
                            <i class="fas fa-paper-plane mr-1.5"></i> Reenviar solicitação
                        </button>
                        <button type="button" onclick="toggleReenvio(<?php echo (int) $req['id']; ?>)"
                            class="px-4 py-2 border border-gray-300 text-gray-600 text-sm font-medium rounded-xl hover:bg-gray-50">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
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

    .req-step-circle { background: #e5e7eb; color: #6b7280; }
    .req-step-circle.ativo { background: #4A9FCA; color: #fff; }
    .req-step-circle.erro  { background: #ef4444; color: #fff; }
    .req-step-text { color: #98a2b3; }
    .req-step-text.ativo { color: #1d2939; font-weight: 700; }
    .req-step-text.erro  { color: #dc2626; font-weight: 700; }
    .req-step-line { background: #e5e7eb; }
    .req-step-line.ativa { background: #4A9FCA; }
    .req-step-line.erro  { background: #f87171; }

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
    .req-select[multiple] { height: auto; padding: 0.5rem; }
    .req-select[multiple] option { padding: 0.4rem 0.6rem; border-radius: 0.5rem; }
    .req-select[multiple] option:checked {
        background: #4A9FCA linear-gradient(0deg, #4A9FCA 0%, #4A9FCA 100%);
        color: #fff;
    }

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

    .req-reenvio-box {
        border: 1px solid #e4e7ec;
        border-radius: 0.9rem;
        padding: 1rem;
        margin-top: 0.75rem;
    }

    .modal-filtro-btn {
        padding: 0.4rem 0.9rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 700;
        border: 1px solid #e4e7ec;
        background: #fff;
        color: #667085;
        cursor: pointer;
        white-space: nowrap;
    }
    .modal-filtro-btn.ativo {
        background: #4A9FCA;
        color: #fff;
        border-color: #4A9FCA;
    }
</style>

<main class="ml-16 pt-16 bg-gray-50 min-h-screen">
<div class="p-8 max-w-4xl mx-auto">

    <div class="mb-8 flex items-center justify-between flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Solicitar Vaga de Estágio</h2>
            <p class="text-gray-600">Encontrou uma vaga que não está na plataforma? Envie os dados para a coordenação avaliar e publicar.</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" onclick="abrirModalTodasSolicitacoes()"
                class="relative w-11 h-11 flex items-center justify-center rounded-lg border border-gray-200 bg-white hover:bg-gray-50 transition-all"
                title="Ver todas as solicitações">
                <i class="fas fa-layer-group text-gray-500 text-lg"></i>
                <?php if (count($minhasSolicitacoes) > 0): ?>
                    <span class="absolute -top-1.5 -right-1.5 bg-[#4A9FCA] text-white text-xs font-bold w-5 h-5 flex items-center justify-center rounded-full border-2 border-white">
                        <?php echo count($minhasSolicitacoes); ?>
                    </span>
                <?php endif; ?>
            </button>
        </div>
    </div>

    <?php if ($mensagemSucesso): ?>
        <div class="mb-6 p-4 rounded-xl bg-green-50 border border-green-200 text-green-800 flex items-center gap-3">
            <i class="fas fa-check-circle text-xl"></i>
            <div>
                <p class="font-semibold">Solicitação enviada com sucesso!</p>
                <p class="text-sm">A coordenação vai revisar os dados. Você pode acompanhar o status na caixinha "Ver todas as solicitações".</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="mb-10">
        <h3 class="text-lg font-bold text-gray-900 mb-4"><i class="fas fa-hourglass-half text-gray-400 mr-2"></i>Solicitações em andamento</h3>

        <?php if (!empty($minhasSolicitacoes)): ?>
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

        <?php if (empty($solicitacoesAtivas)): ?>
            <div class="text-center py-12 bg-white rounded-2xl border-2 border-dashed border-gray-200">
                <i class="fas fa-inbox text-4xl text-gray-300 mb-3"></i>
                <p class="text-gray-500 font-medium">
                    <?php echo empty($minhasSolicitacoes) ? 'Você ainda não enviou nenhuma solicitação.' : 'Nenhuma solicitação em andamento no momento.'; ?>
                </p>
                <?php if (!empty($minhasSolicitacoes)): ?>
                    <button type="button" onclick="abrirModalTodasSolicitacoes()" class="text-[#4A9FCA] font-semibold hover:underline text-sm mt-2">Ver histórico completo</button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($solicitacoesAtivas as $req) echo renderJobCard($req, $fsaCourses); ?>
            </div>
        <?php endif; ?>
    </div>

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

        <form method="POST" action="request-job.php" accept-charset="UTF-8" class="space-y-8">
            <input type="hidden" name="action" value="solicitar">

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

</div>
</main>

<div id="modalTodasSolicitacoesJob" class="hidden fixed inset-0 z-[9999] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="fecharModalTodasSolicitacoes()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-6 max-h-[85vh] flex flex-col">
        <div class="flex items-center justify-between mb-4 flex-shrink-0">
            <h3 class="text-lg font-bold text-gray-900"><i class="fas fa-layer-group text-[#4A9FCA] mr-2"></i>Todas as minhas solicitações</h3>
            <button type="button" onclick="fecharModalTodasSolicitacoes()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <div class="flex flex-wrap gap-2 mb-4 flex-shrink-0">
            <button class="modal-filtro-btn ativo" data-filtro-job="todos" onclick="filtrarModalJobs(this,'todos')">Todas</button>
            <button class="modal-filtro-btn" data-filtro-job="pendente" onclick="filtrarModalJobs(this,'pendente')">Solicitado</button>
            <button class="modal-filtro-btn" data-filtro-job="aprovada" onclick="filtrarModalJobs(this,'aprovada')">Aprovado</button>
            <button class="modal-filtro-btn" data-filtro-job="rejeitada" onclick="filtrarModalJobs(this,'rejeitada')">Recusado</button>
        </div>

        <div class="overflow-y-auto pr-1 space-y-4">
            <?php if (empty($minhasSolicitacoes)): ?>
                <p class="text-gray-400 text-sm italic text-center py-6">Nenhuma solicitação enviada ainda.</p>
            <?php else: ?>
                <?php foreach ($minhasSolicitacoes as $req): ?>
                    <div class="job-modal-item" data-status-job="<?php echo htmlspecialchars($req['status']); ?>">
                        <?php echo renderJobCard($req, $fsaCourses); ?>
                    </div>
                <?php endforeach; ?>
                <p id="msgVazioModalJob" class="hidden text-center text-gray-400 italic py-6">Nenhuma solicitação nesse status.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleReenvio(id) {
    const box = document.getElementById('reenvioBox_' + id);
    if (box) box.classList.toggle('hidden');
}
function abrirModalTodasSolicitacoes() {
    document.getElementById('modalTodasSolicitacoesJob').classList.remove('hidden');
}
function fecharModalTodasSolicitacoes() {
    document.getElementById('modalTodasSolicitacoesJob').classList.add('hidden');
}
function filtrarModalJobs(btn, filtro) {
    document.querySelectorAll('[data-filtro-job]').forEach(b => b.classList.remove('ativo'));
    btn.classList.add('ativo');

    const itens = document.querySelectorAll('.job-modal-item');
    let visiveis = 0;
    itens.forEach(item => {
        const mostrar = filtro === 'todos' || item.dataset.statusJob === filtro;
        item.classList.toggle('hidden', !mostrar);
        if (mostrar) visiveis++;
    });
    const msgVazio = document.getElementById('msgVazioModalJob');
    if (msgVazio) msgVazio.classList.toggle('hidden', visiveis > 0);
}

function abrirModalAjuda() { document.getElementById('modalAjuda').classList.remove('hidden'); }
function fecharModalAjuda() { document.getElementById('modalAjuda').classList.add('hidden'); }

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        fecharModalTodasSolicitacoes();
        fecharModalAjuda();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
