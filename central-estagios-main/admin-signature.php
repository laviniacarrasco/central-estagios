<?php
require_once 'includes/config.php';
checkAuth();

$usuarios = loadData('usuarios');
$usuarioLogado = null;
foreach ($usuarios as $u) {
    if ($u['id'] == ($_SESSION['user_id'] ?? 0)) { $usuarioLogado = $u; break; }
}
if (($usuarioLogado['is_admin'] ?? false) !== true) {
    header('Location: index.php?erro=acesso_negado');
    exit;
}

$pageTitle = 'Assinaturas - Admin';
$MAX_SIZE = 5 * 1024 * 1024;

$mapaUsuarios = [];
foreach ($usuarios as $u) $mapaUsuarios[$u['id']] = $u;

function getLabelTipo($tipo, $area = null) {
    if ($tipo === 'contrato_estagio') {
        $areaTxt = $area === 'dentro' ? ' (dentro da área)' : ($area === 'fora' ? ' (fora da área)' : '');
        return 'Contrato de Estágio' . $areaTxt;
    }
    if ($tipo === 'fim_estagio') return 'Fim de Estágio';
    return $tipo;
}

function getLabelStatus($status) {
    return match($status) {
        'solicitado'  => ['Solicitação enviada', 'bg-yellow-100 text-yellow-700'],
        'em_analise'  => ['Em análise', 'bg-blue-100 text-blue-700'],
        'assinado'    => ['Assinado', 'bg-green-100 text-green-700'],
        'erro'        => ['Erro', 'bg-red-100 text-red-700'],
        default       => ['—', 'bg-gray-100 text-gray-600'],
    };
}

function getInitialsAdmin($name) {
    $parts = explode(' ', trim($name ?: '?'));
    $first = strtoupper(substr($parts[0], 0, 1));
    $last  = count($parts) > 1 ? strtoupper(substr(end($parts), 0, 1)) : '';
    return $first . $last;
}

$assinaturas = loadData('assinaturas');
if (!is_array($assinaturas)) $assinaturas = [];

// ===== Ações do admin =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $acao = $_POST['acao'] ?? '';

    foreach ($assinaturas as &$sol) {
        if (($sol['id'] ?? 0) != $id) continue;

        if ($acao === 'marcar_analise') {
            $sol['status'] = 'em_analise';
            $sol['dataAnalise'] = date('Y-m-d H:i:s');
        }

        if ($acao === 'marcar_erro') {
            $sol['status'] = 'erro';
            $sol['mensagemErro'] = trim($_POST['mensagem'] ?? '') ?: 'Houve um problema com os arquivos enviados.';
        }

        if ($acao === 'enviar_assinado') {
            $uploadDir = "uploads/assinaturas/{$sol['userId']}/assinado/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $arquivosAssinados = $sol['arquivoAssinado'] ?? [];
            foreach (['assinado1', 'assinado2'] as $campo) {
                if (empty($_FILES[$campo]['name'])) continue;
                $file = $_FILES[$campo];
                if ($file['error'] !== 0 || $file['size'] > $MAX_SIZE) continue;

                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $nomeSeguro = "{$id}_assinado_" . uniqid() . ($ext ? ".{$ext}" : '');
                $destino = $uploadDir . $nomeSeguro;

                if (move_uploaded_file($file['tmp_name'], $destino)) {
                    $arquivosAssinados[] = $destino;
                }
            }

            if (!empty($arquivosAssinados)) {
                $sol['arquivoAssinado'] = $arquivosAssinados;
                $sol['status'] = 'assinado';
                $sol['mensagemErro'] = null;
                $sol['dataAssinatura'] = date('Y-m-d H:i:s');
            }
        }

        break;
    }
    unset($sol);

    saveData('assinaturas', $assinaturas);
    header('Location: admin-signature.php');
    exit;
}

usort($assinaturas, fn($a, $b) => ($b['id'] ?? 0) <=> ($a['id'] ?? 0));

// Contadores para os cards de resumo
$totais = ['solicitado' => 0, 'em_analise' => 0, 'assinado' => 0, 'erro' => 0];
foreach ($assinaturas as $s) {
    if (isset($totais[$s['status']])) $totais[$s['status']]++;
}

/* Apenas as ativas (solicitado/em_analise) ficam na tela principal.
   Assinado/erro saem da tela e vao para a caixinha "Ver todas". */
$assinaturasAtivas = array_values(array_filter($assinaturas, fn($s) => in_array($s['status'], ['solicitado', 'em_analise'])));

function renderAdminSigCard(array $sol, array $mapaUsuarios): string {
    $aluno = $mapaUsuarios[$sol['userId']] ?? null;
    $nomeAluno = $aluno['nome'] ?? ('Aluno #' . $sol['userId']);
    [$labelStatus, $corStatus] = getLabelStatus($sol['status']);

    ob_start();
    ?>
    <div class="card-assinatura bg-white p-6 rounded-2xl shadow-sm hover:shadow-md transition-shadow" data-status="<?php echo htmlspecialchars($sol['status']); ?>">
        <div class="flex items-start justify-between mb-4 gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 shrink-0 rounded-full bg-gradient-to-br from-[#4A9FCA] to-[#3A8FB0] flex items-center justify-center text-white font-bold text-sm">
                    <?php echo htmlspecialchars(getInitialsAdmin($nomeAluno)); ?>
                </div>
                <div class="min-w-0">
                    <p class="font-bold text-gray-900 truncate"><?php echo htmlspecialchars($nomeAluno); ?></p>
                    <p class="text-xs text-gray-400 truncate"><?php echo htmlspecialchars($aluno['email'] ?? ''); ?></p>
                </div>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap <?php echo $corStatus; ?>">
                <?php echo htmlspecialchars($labelStatus); ?>
            </span>
        </div>

        <div class="flex flex-wrap items-center gap-x-6 gap-y-1 mb-4 text-sm">
            <p class="text-gray-700"><i class="fas fa-file-alt text-gray-400 mr-1.5"></i><strong><?php echo htmlspecialchars(getLabelTipo($sol['tipo'], $sol['area'])); ?></strong></p>
            <p class="text-xs text-gray-400"><i class="fas fa-clock mr-1"></i>Enviado em <?php echo htmlspecialchars($sol['dataSolicitacao']); ?></p>
        </div>

        <div class="flex flex-wrap gap-2 text-sm mb-4">
            <?php foreach ($sol['arquivos'] as $i => $arq): ?>
                <a href="<?php echo htmlspecialchars($arq); ?>" target="_blank"
                    class="px-3 py-1.5 bg-gray-100 rounded-lg text-gray-700 hover:bg-gray-200">
                    <i class="fas fa-paperclip mr-1"></i> Arquivo <?php echo $i + 1; ?>
                </a>
            <?php endforeach; ?>
            <?php foreach (($sol['arquivoAssinado'] ?? []) as $i => $arq): ?>
                <a href="<?php echo htmlspecialchars($arq); ?>" target="_blank"
                    class="px-3 py-1.5 bg-green-100 text-green-700 rounded-lg hover:bg-green-200">
                    <i class="fas fa-file-signature mr-1"></i> Assinado <?php echo $i + 1; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($sol['status'] === 'erro'): ?>
            <div class="bg-red-50 border border-red-100 text-red-700 text-sm rounded-xl p-3 mb-4">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                <?php echo htmlspecialchars($sol['mensagemErro']); ?>
            </div>
        <?php endif; ?>

        <div class="flex flex-wrap gap-2 pt-1 border-t border-gray-100 mt-1 pt-4">
            <?php if ($sol['status'] === 'solicitado'): ?>
                <form method="POST">
                    <input type="hidden" name="id" value="<?php echo $sol['id']; ?>">
                    <input type="hidden" name="acao" value="marcar_analise">
                    <button class="px-4 py-2 bg-blue-500 text-white text-sm font-medium rounded-xl hover:bg-blue-600 transition-all">
                        <i class="fas fa-search mr-1.5"></i> Marcar em análise
                    </button>
                </form>
            <?php endif; ?>

            <?php if (in_array($sol['status'], ['solicitado', 'em_analise'])): ?>
                <button type="button" onclick="abrirModalAssinado(<?php echo $sol['id']; ?>)"
                    class="px-4 py-2 bg-green-500 text-white text-sm font-medium rounded-xl hover:bg-green-600 transition-all">
                    <i class="fas fa-upload mr-1.5"></i> Enviar assinado
                </button>

                <button type="button" onclick="abrirModalErro(<?php echo $sol['id']; ?>)"
                    class="px-4 py-2 border border-red-300 text-red-600 text-sm font-medium rounded-xl hover:bg-red-50 transition-all">
                    <i class="fas fa-exclamation-triangle mr-1.5"></i> Marcar erro
                </button>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

include 'includes/header.php';
?>

<style>
    .upload-zone {
        position: relative;
        border: 2px dashed #d1d5db;
        border-radius: 1rem;
        padding: 1.75rem 1rem;
        text-align: center;
        cursor: pointer;
        transition: border-color .15s ease, background .15s ease;
        background: #fff;
    }
    .upload-zone:hover,
    .upload-zone.drag-over {
        border-color: #4A9FCA;
        background: #f5fbfd;
    }
    .upload-zone-input {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }
    .upload-zone-icon {
        width: 48px;
        height: 48px;
        border-radius: 0.85rem;
        background: #e6f2f8;
        color: #2B7FA6;
        font-size: 1.35rem;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 0.75rem;
    }
    .upload-zone-text { font-size: 0.9rem; margin: 0; }
    .upload-zone-text-strong { color: #2B7FA6; font-weight: 700; }
    .upload-zone-text-muted { color: #98a2b3; margin-left: 4px; }
    .upload-zone-hint { font-size: 0.75rem; color: #98a2b3; margin-top: 0.35rem; }

    .upload-file-list {
        margin-top: 0.85rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .upload-file-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        border: 1px solid #eef0f2;
        border-radius: 0.85rem;
        padding: 0.6rem 0.75rem;
        background: #fff;
    }
    .upload-file-icon {
        width: 38px;
        height: 38px;
        flex-shrink: 0;
        border-radius: 0.65rem;
        background: #d1fae5;
        color: #065f46;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }
    .upload-file-info { min-width: 0; flex: 1; text-align: left; }
    .upload-file-name {
        font-size: 0.83rem;
        font-weight: 700;
        color: #101828;
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .upload-file-size { font-size: 0.7rem; color: #98a2b3; margin: 0; }
    .upload-file-remove {
        flex-shrink: 0;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        border: none;
        background: #fee4e2;
        color: #b42318;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }
    .upload-file-remove:hover { background: #fecdca; }

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
    .modal-filtro-btn.ativo { background: #4A9FCA; color: #fff; border-color: #4A9FCA; }
</style>

<main class="ml-16 pt-16 bg-gray-50 min-h-screen">
<div class="p-8 max-w-6xl mx-auto">

    <div class="mb-8 flex items-center justify-between flex-wrap gap-4">
        <div>
            <h2 class="text-3xl font-bold text-gray-900 mb-1">Assinaturas</h2>
            <p class="text-gray-500">Contratos de estágio e fim de estágio enviados pelos alunos</p>
        </div>
        <button type="button" onclick="abrirModalTodasAdmin()"
            class="relative w-11 h-11 flex items-center justify-center rounded-lg border border-gray-200 bg-white hover:bg-gray-50 transition-all"
            title="Ver todas as solicitações">
            <i class="fas fa-layer-group text-gray-500 text-lg"></i>
            <?php if (count($assinaturas) > 0): ?>
                <span class="absolute -top-1.5 -right-1.5 bg-[#4A9FCA] text-white text-xs font-bold w-5 h-5 flex items-center justify-center rounded-full border-2 border-white">
                    <?php echo count($assinaturas); ?>
                </span>
            <?php endif; ?>
        </button>
    </div>

    <!-- Cards de resumo -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-yellow-100 flex items-center justify-center">
                <i class="fas fa-paper-plane text-yellow-600"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-900"><?php echo $totais['solicitado']; ?></p>
                <p class="text-xs text-gray-500">Solicitadas</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-blue-100 flex items-center justify-center">
                <i class="fas fa-search text-blue-600"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-900"><?php echo $totais['em_analise']; ?></p>
                <p class="text-xs text-gray-500">Em análise</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-green-100 flex items-center justify-center">
                <i class="fas fa-file-signature text-green-600"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-900"><?php echo $totais['assinado']; ?></p>
                <p class="text-xs text-gray-500">Assinadas</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-red-100 flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-red-600"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-900"><?php echo $totais['erro']; ?></p>
                <p class="text-xs text-gray-500">Com erro</p>
            </div>
        </div>
    </div>

    <?php if (empty($assinaturas)): ?>
        <div class="bg-white rounded-2xl shadow-sm p-10 text-center">
            <i class="fas fa-inbox text-4xl text-gray-300 mb-3"></i>
            <p class="text-gray-400 italic">Nenhuma solicitação recebida ainda.</p>
        </div>
    <?php endif; ?>

    <h3 class="text-lg font-bold text-gray-900 mb-4">Solicitações que precisam de ação</h3>

    <?php if (empty($assinaturasAtivas) && !empty($assinaturas)): ?>
        <div class="bg-white rounded-2xl shadow-sm p-10 text-center mb-5">
            <i class="fas fa-check-circle text-4xl text-green-300 mb-3"></i>
            <p class="text-gray-400 italic">Nenhuma solicitação pendente de ação no momento.</p>
            <button type="button" onclick="abrirModalTodasAdmin()" class="text-[#4A9FCA] font-semibold hover:underline text-sm mt-2">Ver histórico completo</button>
        </div>
    <?php else: ?>
        <div class="space-y-5">
            <?php foreach ($assinaturasAtivas as $sol) echo renderAdminSigCard($sol, $mapaUsuarios); ?>
        </div>
    <?php endif; ?>

</div>
</main>

<!-- Modal: Enviar assinado -->
<div id="modalAssinado" class="hidden fixed inset-0 z-[9999] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="fecharModalAssinado()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 max-h-[90vh] overflow-y-auto">
        <div class="w-14 h-14 bg-green-100 rounded-2xl flex items-center justify-center mb-4">
            <i class="fas fa-file-signature text-green-600 text-2xl"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-1">Enviar documento assinado</h3>
        <p class="text-gray-500 text-sm mb-5">Anexe o(s) arquivo(s) já assinado(s) pela coordenação.</p>

        <form method="POST" enctype="multipart/form-data" class="space-y-5" id="formAssinado">
            <input type="hidden" name="id" id="assinadoId">
            <input type="hidden" name="acao" value="enviar_assinado">

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Documento(s) assinado(s)</label>

                <div class="upload-zone" id="uploadZoneAssinado" onclick="document.getElementById('assinadoMulti').click()">
                    <input type="file" id="assinadoMulti" class="upload-zone-input" multiple>
                    <div class="upload-zone-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <p class="upload-zone-text">
                        <span class="upload-zone-text-strong">Clique para escolher arquivos</span>
                        <span class="upload-zone-text-muted">ou arraste aqui</span>
                    </p>
                    <p class="upload-zone-hint">Até 2 arquivos, 5MB cada</p>
                </div>

                <div id="uploadFileList" class="upload-file-list"></div>

                <p id="uploadErro" class="hidden text-red-600 text-xs mt-2">
                    <i class="fas fa-exclamation-circle mr-1"></i>Selecione pelo menos 1 arquivo (máximo 2).
                </p>

                <input type="file" name="assinado1" id="assinado1" class="hidden">
                <input type="file" name="assinado2" id="assinado2" class="hidden">
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="fecharModalAssinado()"
                    class="flex-1 py-2.5 border border-gray-300 text-gray-600 font-medium rounded-xl hover:bg-gray-50">
                    Cancelar
                </button>
                <button type="submit"
                    class="flex-1 py-2.5 bg-green-500 hover:bg-green-600 text-white font-semibold rounded-xl transition-all">
                    <i class="fas fa-upload mr-1.5"></i> Enviar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Marcar erro -->
<div id="modalErro" class="hidden fixed inset-0 z-[9999] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="fecharModalErro()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
        <div class="w-14 h-14 bg-red-100 rounded-2xl flex items-center justify-center mb-4">
            <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-1">Marcar como erro</h3>
        <p class="text-gray-500 text-sm mb-5">Descreva o problema encontrado nos arquivos. O aluno verá esta mensagem.</p>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="id" id="erroId">
            <input type="hidden" name="acao" value="marcar_erro">

            <textarea name="mensagem" rows="3" required placeholder="Ex.: O arquivo enviado está ilegível, favor reenviar."
                class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-red-400"></textarea>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="fecharModalErro()"
                    class="flex-1 py-2.5 border border-gray-300 text-gray-600 font-medium rounded-xl hover:bg-gray-50">
                    Cancelar
                </button>
                <button type="submit"
                    class="flex-1 py-2.5 bg-red-500 hover:bg-red-600 text-white font-semibold rounded-xl transition-all">
                    Confirmar erro
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Ver todas as solicitacoes (admin) -->
<div id="modalTodasAdmin" class="hidden fixed inset-0 z-[9999] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="fecharModalTodasAdmin()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-6 max-h-[85vh] flex flex-col">
        <div class="flex items-center justify-between mb-4 flex-shrink-0">
            <h3 class="text-lg font-bold text-gray-900"><i class="fas fa-layer-group text-[#4A9FCA] mr-2"></i>Todas as solicitações recebidas</h3>
            <button type="button" onclick="fecharModalTodasAdmin()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <div class="flex flex-wrap gap-2 mb-4 flex-shrink-0">
            <button class="modal-filtro-btn ativo" data-filtro-admin="todos" onclick="filtrarModalAdmin(this,'todos')">Todas</button>
            <button class="modal-filtro-btn" data-filtro-admin="solicitado" onclick="filtrarModalAdmin(this,'solicitado')">Solicitado</button>
            <button class="modal-filtro-btn" data-filtro-admin="em_analise" onclick="filtrarModalAdmin(this,'em_analise')">Em análise</button>
            <button class="modal-filtro-btn" data-filtro-admin="assinado" onclick="filtrarModalAdmin(this,'assinado')">Assinado</button>
            <button class="modal-filtro-btn" data-filtro-admin="erro" onclick="filtrarModalAdmin(this,'erro')">Erro</button>
        </div>

        <div class="overflow-y-auto pr-1 space-y-4">
            <?php if (empty($assinaturas)): ?>
                <p class="text-gray-400 text-sm italic text-center py-6">Nenhuma solicitação recebida ainda.</p>
            <?php else: ?>
                <?php foreach ($assinaturas as $sol): ?>
                    <div class="admin-modal-item" data-status-admin="<?php echo htmlspecialchars($sol['status']); ?>">
                        <?php echo renderAdminSigCard($sol, $mapaUsuarios); ?>
                    </div>
                <?php endforeach; ?>
                <p id="msgVazioModalAdmin" class="hidden text-center text-gray-400 italic py-6">Nenhuma solicitação nesse status.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function abrirModalAssinado(id) {
    document.getElementById('assinadoId').value = id;
    resetarUploadZonesAssinado();
    document.getElementById('modalAssinado').classList.remove('hidden');
}
function fecharModalAssinado() {
    document.getElementById('modalAssinado').classList.add('hidden');
}
function abrirModalErro(id) {
    document.getElementById('erroId').value = id;
    document.getElementById('modalErro').classList.remove('hidden');
}
function fecharModalErro() {
    document.getElementById('modalErro').classList.add('hidden');
}
function abrirModalTodasAdmin() {
    document.getElementById('modalTodasAdmin').classList.remove('hidden');
}
function fecharModalTodasAdmin() {
    document.getElementById('modalTodasAdmin').classList.add('hidden');
}
function filtrarModalAdmin(btn, filtro) {
    document.querySelectorAll('[data-filtro-admin]').forEach(b => b.classList.remove('ativo'));
    btn.classList.add('ativo');

    const itens = document.querySelectorAll('.admin-modal-item');
    let visiveis = 0;
    itens.forEach(item => {
        const mostrar = filtro === 'todos' || item.dataset.statusAdmin === filtro;
        item.classList.toggle('hidden', !mostrar);
        if (mostrar) visiveis++;
    });
    const msgVazio = document.getElementById('msgVazioModalAdmin');
    if (msgVazio) msgVazio.classList.toggle('hidden', visiveis > 0);
}

const MAX_ARQUIVOS_ASSINADO = 2;
let arquivosSelecionadosAssinado = [];

function formatarTamanhoArquivo(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function renderizarListaArquivosAssinado() {
    const lista = document.getElementById('uploadFileList');
    lista.innerHTML = '';

    arquivosSelecionadosAssinado.forEach((file, index) => {
        const item = document.createElement('div');
        item.className = 'upload-file-item';
        item.innerHTML = `
            <div class="upload-file-icon"><i class="fas fa-file-alt"></i></div>
            <div class="upload-file-info">
                <p class="upload-file-name">${file.name}</p>
                <p class="upload-file-size">${formatarTamanhoArquivo(file.size)}</p>
            </div>
            <button type="button" class="upload-file-remove" data-index="${index}" title="Remover">
                <i class="fas fa-times"></i>
            </button>
        `;
        lista.appendChild(item);
    });

    lista.querySelectorAll('.upload-file-remove').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const idx = parseInt(btn.dataset.index, 10);
            arquivosSelecionadosAssinado.splice(idx, 1);
            sincronizarInputsAssinado();
        });
    });

    document.getElementById('uploadErro').classList.add('hidden');
}

function sincronizarInputsAssinado() {
    if (arquivosSelecionadosAssinado.length > MAX_ARQUIVOS_ASSINADO) {
        arquivosSelecionadosAssinado = arquivosSelecionadosAssinado.slice(0, MAX_ARQUIVOS_ASSINADO);
    }

    const dt1 = new DataTransfer();
    const dt2 = new DataTransfer();

    if (arquivosSelecionadosAssinado[0]) dt1.items.add(arquivosSelecionadosAssinado[0]);
    if (arquivosSelecionadosAssinado[1]) dt2.items.add(arquivosSelecionadosAssinado[1]);

    document.getElementById('assinado1').files = dt1.files;
    document.getElementById('assinado2').files = dt2.files;

    renderizarListaArquivosAssinado();
}

function adicionarArquivosAssinado(fileList) {
    const novos = Array.from(fileList);
    arquivosSelecionadosAssinado = [...arquivosSelecionadosAssinado, ...novos].slice(0, MAX_ARQUIVOS_ASSINADO);
    sincronizarInputsAssinado();
}

const zonaAssinado = document.getElementById('uploadZoneAssinado');
const inputMultiAssinado = document.getElementById('assinadoMulti');

inputMultiAssinado.addEventListener('change', () => {
    adicionarArquivosAssinado(inputMultiAssinado.files);
    inputMultiAssinado.value = '';
});

['dragenter', 'dragover'].forEach(evento => {
    zonaAssinado.addEventListener(evento, (e) => {
        e.preventDefault();
        e.stopPropagation();
        zonaAssinado.classList.add('drag-over');
    });
});
['dragleave', 'drop'].forEach(evento => {
    zonaAssinado.addEventListener(evento, (e) => {
        e.preventDefault();
        e.stopPropagation();
        zonaAssinado.classList.remove('drag-over');
    });
});
zonaAssinado.addEventListener('drop', (e) => {
    if (e.dataTransfer.files && e.dataTransfer.files.length) {
        adicionarArquivosAssinado(e.dataTransfer.files);
    }
});

document.getElementById('formAssinado').addEventListener('submit', (e) => {
    if (arquivosSelecionadosAssinado.length === 0) {
        e.preventDefault();
        document.getElementById('uploadErro').classList.remove('hidden');
    }
});

function resetarUploadZonesAssinado() {
    arquivosSelecionadosAssinado = [];
    document.getElementById('assinado1').value = '';
    document.getElementById('assinado2').value = '';
    document.getElementById('uploadFileList').innerHTML = '';
    document.getElementById('uploadErro').classList.add('hidden');
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        fecharModalAssinado();
        fecharModalErro();
        fecharModalTodasAdmin();
    }
});
</script>

<?php include 'includes/footer.php'; ?>

