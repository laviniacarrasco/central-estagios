<?php
require_once 'includes/config.php';
checkAuth();

$pageTitle = 'Assinaturas';
$userId = $_SESSION['user_id'];

$EMAIL_AJUDA = 'coordenacao@fsa.br';
$MAX_SIZE = 5 * 1024 * 1024;

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

$assinaturas = loadData('assinaturas');
if (!is_array($assinaturas)) $assinaturas = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'reenviar') {
    $id = intval($_POST['id'] ?? 0);

    if (empty($_FILES['arquivo_reenvio']['name'])) {
        header('Location: signature.php?erro=arquivo');
        exit;
    }

    $file = $_FILES['arquivo_reenvio'];

    foreach ($assinaturas as &$sol) {
        if (($sol['id'] ?? 0) != $id) continue;
        if ($sol['userId'] != $userId) break;
        if ($sol['status'] !== 'erro') break;

        if ($file['error'] !== 0 || $file['size'] > $MAX_SIZE) {
            header('Location: signature.php?erro=arquivo');
            exit;
        }

        $uploadDir = "uploads/assinaturas/{$userId}/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $nomeSeguro = "{$id}_reenvio_" . uniqid() . ($ext ? ".{$ext}" : '');
        $destino = $uploadDir . $nomeSeguro;

        if (move_uploaded_file($file['tmp_name'], $destino)) {
            $sol['arquivos']        = [$destino];
            $sol['status']          = 'solicitado';
            $sol['mensagemErro']    = null;
            $sol['dataSolicitacao'] = date('Y-m-d H:i:s');
        }
        break;
    }
    unset($sol);

    saveData('assinaturas', $assinaturas);
    header('Location: signature.php?reenvio=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nova_solicitacao'])) {
    $classificacoes = json_decode($_POST['classificacoes'] ?? '[]', true);

    if (!is_array($classificacoes) || empty($_FILES['arquivos']['name'][0])) {
        header('Location: signature.php?erro=arquivo');
        exit;
    }

    $nomes    = $_FILES['arquivos']['name'];
    $tmpNames = $_FILES['arquivos']['tmp_name'];
    $errosUp  = $_FILES['arquivos']['error'];
    $sizes    = $_FILES['arquivos']['size'];

    $maxId = 0;
    foreach ($assinaturas as $a) if (($a['id'] ?? 0) > $maxId) $maxId = $a['id'];

    $uploadDir = "uploads/assinaturas/{$userId}/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $criadas = 0;

    foreach ($nomes as $i => $nomeOriginal) {
        $tipo = $classificacoes[$i]['tipo'] ?? '';
        $area = $classificacoes[$i]['area'] ?? null;

        if (!in_array($tipo, ['contrato_estagio', 'fim_estagio'])) continue;
        if ($tipo !== 'contrato_estagio') $area = null;
        if ($tipo === 'contrato_estagio' && !in_array($area, ['dentro', 'fora'])) continue;
        if (($errosUp[$i] ?? 1) !== 0) continue;
        if (($sizes[$i] ?? 0) > $MAX_SIZE) continue;

        $maxId++;
        $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        $nomeSeguro = "{$maxId}_" . uniqid() . ($ext ? ".{$ext}" : '');
        $destino = $uploadDir . $nomeSeguro;

        if (!move_uploaded_file($tmpNames[$i], $destino)) continue;

        $assinaturas[] = [
            'id' => $maxId,
            'userId' => $userId,
            'tipo' => $tipo,
            'area' => $area,
            'arquivos' => [$destino],
            'arquivoAssinado' => [],
            'status' => 'solicitado',
            'mensagemErro' => null,
            'dataSolicitacao' => date('Y-m-d H:i:s'),
            'dataAnalise' => null,
            'dataAssinatura' => null,
        ];
        $criadas++;
    }

    if ($criadas === 0) {
        header('Location: signature.php?erro=arquivo');
        exit;
    }

    saveData('assinaturas', $assinaturas);
    header('Location: signature.php?ok=1');
    exit;
}

$minhasSolicitacoes = array_values(array_filter($assinaturas, fn($a) => $a['userId'] == $userId));
usort($minhasSolicitacoes, fn($a, $b) => ($b['id'] ?? 0) <=> ($a['id'] ?? 0));

/* Apenas as ativas (solicitado/em_analise) ficam na tela principal.
   Assinado/erro saem da tela e vao para a caixinha "Ver todas". */
$solicitacoesAtivasSig = array_values(array_filter($minhasSolicitacoes, fn($s) => in_array($s['status'], ['solicitado', 'em_analise'])));

function renderSignatureCard(array $sol, string $EMAIL_AJUDA): string {
    [$labelStatus, $corStatus] = getLabelStatus($sol['status']);
    $isErro = $sol['status'] === 'erro';

    $etapas = ['solicitado', 'em_analise', 'assinado'];
    $etapaAtual = array_search($sol['status'], $etapas);
    if ($etapaAtual === false) $etapaAtual = -1;

    $textosEtapas = $isErro
        ? ['Solicitação enviada', 'Recebido para análise', 'Erro encontrado']
        : ['Solicitação enviada', 'Recebido para análise', 'Assinado'];

    $etapaAtualExibicao = $isErro ? 2 : $etapaAtual;

    ob_start();
    ?>
    <div class="bg-white p-6 rounded-2xl shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="font-bold text-gray-900"><?php echo htmlspecialchars(getLabelTipo($sol['tipo'], $sol['area'])); ?></p>
                <p class="text-xs text-gray-400">Enviado em <?php echo htmlspecialchars($sol['dataSolicitacao']); ?></p>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $corStatus; ?>">
                <?php echo htmlspecialchars($labelStatus); ?>
            </span>
        </div>

        <div class="flex items-center mb-4">
            <?php foreach ($textosEtapas as $i => $texto): ?>
                <?php
                    $ehNoErro = $isErro && $i === 2;
                    $completo = $i < $etapaAtualExibicao;
                    $atual    = $i === $etapaAtualExibicao;
                    $circuloClasses = $ehNoErro
                        ? 'step-erro-circle'
                        : (($completo || $atual) ? 'bg-[#4A9FCA] text-white' : 'bg-gray-200 text-gray-500');
                    $textoClasses = $ehNoErro ? 'step-erro-text' : 'text-gray-500';
                ?>
                <div class="flex-1 flex flex-col items-center relative">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold z-10 <?php echo $circuloClasses; ?>">
                        <?php if ($ehNoErro): ?>
                            <i class="fas fa-times"></i>
                        <?php elseif ($completo): ?>
                            <i class="fas fa-check"></i>
                        <?php else: ?>
                            <?php echo $i + 1; ?>
                        <?php endif; ?>
                    </div>
                    <span class="text-[11px] text-center mt-1 px-1 <?php echo $textoClasses; ?>"><?php echo $texto; ?></span>
                </div>
                <?php if ($i < 2): ?>
                    <?php $linhaCompleta = $isErro ? true : ($i < $etapaAtual); ?>
                    <div class="flex-1 h-0.5 -mt-4 <?php echo $linhaCompleta ? ($isErro && $i === 1 ? 'bg-red-400' : 'bg-[#4A9FCA]') : 'bg-gray-200'; ?>"></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div class="flex flex-wrap gap-2 text-sm mb-4">
            <?php foreach ($sol['arquivos'] as $i => $arq): ?>
                <a href="<?php echo htmlspecialchars($arq); ?>" target="_blank"
                    class="px-3 py-1.5 bg-gray-100 rounded-lg text-gray-700 hover:bg-gray-200">
                    <i class="fas fa-paperclip mr-1"></i> Arquivo enviado <?php echo $i + 1; ?>
                </a>
            <?php endforeach; ?>

            <?php foreach (($sol['arquivoAssinado'] ?? []) as $i => $arq): ?>
                <a href="<?php echo htmlspecialchars($arq); ?>" target="_blank"
                    class="px-3 py-1.5 bg-green-100 text-green-700 rounded-lg hover:bg-green-200">
                    <i class="fas fa-file-signature mr-1"></i> Documento assinado <?php echo $i + 1; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($isErro): ?>
            <div class="bg-red-50 border border-red-100 text-red-700 text-sm rounded-xl p-3 mb-4">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                <?php echo htmlspecialchars($sol['mensagemErro'] ?: 'Ocorreu um problema com seus arquivos.'); ?>
                — entre em contato: <strong><?php echo htmlspecialchars($EMAIL_AJUDA); ?></strong>
            </div>

            <div class="border border-gray-200 rounded-xl p-4">
                <p class="text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-redo text-[#4A9FCA] mr-1"></i> Reenviar arquivo corrigido
                </p>
                <form method="POST" action="signature.php" enctype="multipart/form-data" class="flex items-center gap-3 flex-wrap">
                    <input type="hidden" name="acao" value="reenviar">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($sol['id']); ?>">
                    <input type="file" name="arquivo_reenvio" required
                        class="flex-1 min-w-[200px] text-sm text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 cursor-pointer">
                    <button type="submit"
                        class="px-4 py-2 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white text-sm font-semibold rounded-xl transition-all">
                        <i class="fas fa-paper-plane mr-1.5"></i> Reenviar
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

include 'includes/header.php';
?>

<style>
    .upload-card {
        border: 2px dashed #d1d5db;
        border-radius: 1rem;
        transition: border-color .15s ease, background .15s ease;
        background: #fff;
        overflow: hidden;
    }
    .upload-card.drag-over,
    .upload-card:hover {
        border-color: #4A9FCA;
        background: #f5fbfd;
    }
    .upload-card.tem-arquivos { border-style: solid; border-color: #bfe3f2; }
    #dropZone {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        padding: 3rem 1.5rem;
        cursor: pointer;
        text-align: center;
    }
    #dropZone.compacto { padding: 1.25rem 1.5rem; }
    .upload-card-icon {
        width: 64px;
        height: 64px;
        border-radius: 1rem;
        background: #e6f2f8;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: #4A9FCA;
        transition: all .15s ease;
    }
    .upload-card-icon.sucesso {
        width: 44px;
        height: 44px;
        background: #d1fae5;
        color: #065f46;
        font-size: 1.1rem;
    }
    #listaArquivosPendentes {
        border-top: 1px dashed #d7dee3;
        background: #fbfdfe;
        padding: 1rem 1.25rem;
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
    .upload-file-tag { font-size: 0.7rem; color: #4A9FCA; margin: 0; font-weight: 600; }
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

    .step-erro-circle { background: #ef4444 !important; color: #fff !important; }
    .step-erro-text { color: #dc2626 !important; font-weight: 700; }

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

<main class="ml-16 pt-16">
<div class="p-8 max-w-5xl mx-auto">

    <div class="mb-8 flex items-center justify-between flex-wrap gap-4">
        <div>
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Assinaturas</h2>
            <p class="text-gray-600">Envie contratos de estágio ou fim de estágio para assinatura da coordenação</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" onclick="abrirModalTodasAssinaturas()"
                class="relative w-11 h-11 flex items-center justify-center rounded-lg border border-gray-200 bg-white hover:bg-gray-50 transition-all"
                title="Ver todas as solicitações">
                <i class="fas fa-layer-group text-gray-500 text-lg"></i>
                <?php if (count($minhasSolicitacoes) > 0): ?>
                    <span class="absolute -top-1.5 -right-1.5 bg-[#4A9FCA] text-white text-xs font-bold w-5 h-5 flex items-center justify-center rounded-full border-2 border-white">
                        <?php echo count($minhasSolicitacoes); ?>
                    </span>
                <?php endif; ?>
            </button>
            <button type="button" onclick="abrirModalAjuda()"
                class="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-xl text-sm text-gray-700 hover:bg-gray-50">
                <i class="fas fa-question-circle text-[#4A9FCA]"></i> Precisa de ajuda?
            </button>
        </div>
    </div>

    <?php if (isset($_GET['ok'])): ?>
        <div class="mb-6 bg-green-50 border border-green-100 text-green-700 text-sm rounded-xl p-4 flex items-center gap-2">
            <i class="fas fa-check-circle"></i> Solicitação(ões) enviada(s) com sucesso!
        </div>
    <?php elseif (isset($_GET['reenvio'])): ?>
        <div class="mb-6 bg-green-50 border border-green-100 text-green-700 text-sm rounded-xl p-4 flex items-center gap-2">
            <i class="fas fa-check-circle"></i> Arquivo reenviado com sucesso! Sua solicitação voltou para análise.
        </div>
    <?php elseif (isset($_GET['erro'])): ?>
        <div class="mb-6 bg-red-50 border border-red-100 text-red-700 text-sm rounded-xl p-4 flex items-center gap-2">
            <i class="fas fa-exclamation-triangle"></i> Não foi possível enviar. Verifique os arquivos e tente novamente.
        </div>
    <?php endif; ?>

    <h3 class="text-lg font-bold text-gray-900 mb-4">Solicitações em andamento</h3>

    <?php if (empty($solicitacoesAtivasSig)): ?>
        <div class="text-center py-10 bg-white rounded-2xl border-2 border-dashed border-gray-200 mb-10">
            <i class="fas fa-inbox text-4xl text-gray-300 mb-3"></i>
            <p class="text-gray-500 font-medium">
                <?php echo empty($minhasSolicitacoes) ? 'Nenhuma solicitação enviada ainda.' : 'Nenhuma solicitação em andamento no momento.'; ?>
            </p>
            <?php if (!empty($minhasSolicitacoes)): ?>
                <button type="button" onclick="abrirModalTodasAssinaturas()" class="text-[#4A9FCA] font-semibold hover:underline text-sm mt-2">Ver histórico completo</button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="space-y-5 mb-10">
            <?php foreach ($solicitacoesAtivasSig as $sol) echo renderSignatureCard($sol, $EMAIL_AJUDA); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-2xl shadow-sm mb-8">
        <h3 class="text-lg font-bold text-gray-900 mb-1">
            <i class="fas fa-file-signature text-[#4A9FCA] mr-2"></i>Nova solicitação
        </h3>
        <p class="text-sm text-gray-500 mb-5">Envie um ou mais arquivos. Você vai classificar cada um logo em seguida.</p>

        <div id="uploadCard" class="upload-card">
            <div id="dropZone">
                <input type="file" id="fileInput" multiple class="hidden">
                <div class="upload-card-icon" id="dropZoneIcon">
                    <i class="fas fa-cloud-upload-alt"></i>
                </div>
                <p class="text-gray-700 font-semibold text-center" id="dropZoneTexto">
                    Clique para escolher arquivos <span class="text-gray-400 font-normal">ou arraste aqui</span>
                </p>
                <p class="text-xs text-gray-400" id="dropZoneHint">Qualquer tipo de arquivo, até 5MB cada</p>
            </div>
            <div id="listaArquivosPendentes" class="hidden"></div>
        </div>

        <div class="flex justify-end mt-5">
            <button type="button" id="btnEnviarAssinatura" disabled
                class="opacity-50 pointer-events-none px-6 py-3 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white font-semibold rounded-xl transition-all">
                <i class="fas fa-paper-plane mr-2"></i>Enviar para assinatura
            </button>
        </div>
    </div>

</div>
</main>

<form id="formEnvioFinal" method="POST" action="signature.php" enctype="multipart/form-data" class="hidden">
    <input type="hidden" name="nova_solicitacao" value="1">
    <input type="hidden" name="classificacoes" id="classificacoesInput">
    <input type="file" name="arquivos[]" id="arquivosFinal" multiple>
</form>

<!-- Modal de Classificação -->
<div id="modalClassificacao" class="hidden fixed inset-0 z-[9999] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900">Classificar arquivo</h3>
            <span id="modalProgresso" class="text-xs font-semibold text-white bg-[#4A9FCA] rounded-full px-3 py-1"></span>
        </div>

        <div id="modalListaArquivos" class="flex flex-wrap gap-2 mb-5"></div>

        <div class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-3 mb-5 flex items-center gap-3">
            <i class="fas fa-file text-[#4A9FCA]"></i>
            <span id="modalNomeArquivo" class="text-sm font-semibold text-gray-800 truncate"></span>
        </div>

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Tipo de documento</label>
                <select id="modalTipo" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-[#4A9FCA]">
                    <option value="">Selecione...</option>
                    <option value="contrato_estagio">Contrato de Estágio</option>
                    <option value="fim_estagio">Fim de Estágio</option>
                </select>
            </div>

            <div id="modalCampoArea" class="hidden">
                <label class="block text-sm font-semibold text-gray-700 mb-2">O estágio é dentro ou fora da área do seu curso?</label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="modalArea" value="dentro"> Dentro da área
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="modalArea" value="fora"> Fora da área
                    </label>
                </div>
            </div>
        </div>

        <div class="flex gap-3 mt-6">
            <button type="button" id="btnCancelarArquivo"
                class="flex-1 py-2.5 border border-gray-300 text-gray-600 font-medium rounded-xl hover:bg-gray-50">
                Remover este arquivo
            </button>
            <button type="button" id="btnOkClassificacao" disabled
                class="flex-1 py-2.5 bg-gray-300 text-white font-semibold rounded-xl transition-all cursor-not-allowed">
                OK
            </button>
        </div>
    </div>
</div>

<!-- Modal de Ajuda -->
<div id="modalAjuda" class="hidden fixed inset-0 z-[9999] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="fecharModalAjuda()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 text-center">
        <div class="w-14 h-14 bg-blue-100 rounded-2xl flex items-center justify-center mb-4 mx-auto">
            <i class="fas fa-question-circle text-[#4A9FCA] text-2xl"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-2">Precisa de ajuda?</h3>
        <p class="text-gray-500 text-sm mb-6">
            Em caso de dúvidas ou erro no envio, entre em contato pelo e-mail
            <strong><?php echo htmlspecialchars($EMAIL_AJUDA); ?></strong>
            ou compareça pessoalmente à coordenação da faculdade.
        </p>
        <button type="button" onclick="fecharModalAjuda()"
            class="w-full py-2.5 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white font-medium rounded-xl transition-all">
            Entendi
        </button>
    </div>
</div>

<!-- Modal: Ver todas as solicitacoes -->
<div id="modalTodasAssinaturas" class="hidden fixed inset-0 z-[9999] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="fecharModalTodasAssinaturas()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-6 max-h-[85vh] flex flex-col">
        <div class="flex items-center justify-between mb-4 flex-shrink-0">
            <h3 class="text-lg font-bold text-gray-900"><i class="fas fa-layer-group text-[#4A9FCA] mr-2"></i>Todas as minhas solicitações</h3>
            <button type="button" onclick="fecharModalTodasAssinaturas()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <div class="flex flex-wrap gap-2 mb-4 flex-shrink-0">
            <button class="modal-filtro-btn ativo" data-filtro-sig="todos" onclick="filtrarModalSig(this,'todos')">Todas</button>
            <button class="modal-filtro-btn" data-filtro-sig="solicitado" onclick="filtrarModalSig(this,'solicitado')">Solicitado</button>
            <button class="modal-filtro-btn" data-filtro-sig="em_analise" onclick="filtrarModalSig(this,'em_analise')">Em análise</button>
            <button class="modal-filtro-btn" data-filtro-sig="assinado" onclick="filtrarModalSig(this,'assinado')">Assinado</button>
            <button class="modal-filtro-btn" data-filtro-sig="erro" onclick="filtrarModalSig(this,'erro')">Erro</button>
        </div>

        <div class="overflow-y-auto pr-1 space-y-4">
            <?php if (empty($minhasSolicitacoes)): ?>
                <p class="text-gray-400 text-sm italic text-center py-6">Nenhuma solicitação enviada ainda.</p>
            <?php else: ?>
                <?php foreach ($minhasSolicitacoes as $sol): ?>
                    <div class="sig-modal-item" data-status-sig="<?php echo htmlspecialchars($sol['status']); ?>">
                        <?php echo renderSignatureCard($sol, $EMAIL_AJUDA); ?>
                    </div>
                <?php endforeach; ?>
                <p id="msgVazioModalSig" class="hidden text-center text-gray-400 italic py-6">Nenhuma solicitação nesse status.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function abrirModalAjuda() { document.getElementById('modalAjuda').classList.remove('hidden'); }
function fecharModalAjuda() { document.getElementById('modalAjuda').classList.add('hidden'); }
function abrirModalTodasAssinaturas() { document.getElementById('modalTodasAssinaturas').classList.remove('hidden'); }
function fecharModalTodasAssinaturas() { document.getElementById('modalTodasAssinaturas').classList.add('hidden'); }
function filtrarModalSig(btn, filtro) {
    document.querySelectorAll('[data-filtro-sig]').forEach(b => b.classList.remove('ativo'));
    btn.classList.add('ativo');

    const itens = document.querySelectorAll('.sig-modal-item');
    let visiveis = 0;
    itens.forEach(item => {
        const mostrar = filtro === 'todos' || item.dataset.statusSig === filtro;
        item.classList.toggle('hidden', !mostrar);
        if (mostrar) visiveis++;
    });
    const msgVazio = document.getElementById('msgVazioModalSig');
    if (msgVazio) msgVazio.classList.toggle('hidden', visiveis > 0);
}

const uploadCard = document.getElementById('uploadCard');
const dropZone   = document.getElementById('dropZone');
const dropZoneIcon  = document.getElementById('dropZoneIcon');
const dropZoneTexto = document.getElementById('dropZoneTexto');
const dropZoneHint  = document.getElementById('dropZoneHint');
const fileInput  = document.getElementById('fileInput');
const listaPendentesEl = document.getElementById('listaArquivosPendentes');
const btnEnviar  = document.getElementById('btnEnviarAssinatura');

const modal          = document.getElementById('modalClassificacao');
const modalProgresso = document.getElementById('modalProgresso');
const modalListaArquivos = document.getElementById('modalListaArquivos');
const modalNomeArquivo   = document.getElementById('modalNomeArquivo');
const modalTipo      = document.getElementById('modalTipo');
const modalCampoArea = document.getElementById('modalCampoArea');
const btnOk          = document.getElementById('btnOkClassificacao');
const btnCancelarArquivo = document.getElementById('btnCancelarArquivo');

let arquivosClassificados = [];
let filaAtual = [];
let indiceFila = 0;
let totalFilaOriginal = 0;

function nomesLabel(tipo, area) {
    if (tipo === 'contrato_estagio') {
        return 'Contrato de Estágio' + (area === 'dentro' ? ' (dentro da área)' : area === 'fora' ? ' (fora da área)' : '');
    }
    if (tipo === 'fim_estagio') return 'Fim de Estágio';
    return '';
}

dropZone.addEventListener('click', () => fileInput.click());

uploadCard.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadCard.classList.add('drag-over');
});
uploadCard.addEventListener('dragleave', () => {
    uploadCard.classList.remove('drag-over');
});
uploadCard.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadCard.classList.remove('drag-over');
    if (e.dataTransfer.files.length) iniciarNovaLeva(e.dataTransfer.files);
});
fileInput.addEventListener('change', (e) => {
    if (e.target.files.length) iniciarNovaLeva(e.target.files);
    fileInput.value = '';
});

function iniciarNovaLeva(fileList) {
    filaAtual = Array.from(fileList);
    totalFilaOriginal = filaAtual.length;
    indiceFila = 0;
    abrirModalParaIndice();
}

function abrirModalParaIndice() {
    if (indiceFila >= filaAtual.length) {
        modal.classList.add('hidden');
        return;
    }
    const arquivo = filaAtual[indiceFila];

    modalProgresso.textContent = `Arquivo ${indiceFila + 1} de ${totalFilaOriginal}`;
    modalNomeArquivo.textContent = arquivo.name;
    modalTipo.value = '';
    modalCampoArea.classList.add('hidden');
    document.querySelectorAll('input[name="modalArea"]').forEach(r => r.checked = false);
    atualizarBotaoOk();

    modalListaArquivos.innerHTML = '';
    filaAtual.forEach((f, i) => {
        const span = document.createElement('span');
        span.textContent = f.name;
        span.className = i === indiceFila
            ? 'px-3 py-1 rounded-full text-xs font-semibold bg-[#4A9FCA] text-white'
            : (i < indiceFila
                ? 'px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700'
                : 'px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-500');
        modalListaArquivos.appendChild(span);
    });

    modal.classList.remove('hidden');
}

modalTipo.addEventListener('change', () => {
    modalCampoArea.classList.toggle('hidden', modalTipo.value !== 'contrato_estagio');
    atualizarBotaoOk();
});
document.querySelectorAll('input[name="modalArea"]').forEach(r => {
    r.addEventListener('change', atualizarBotaoOk);
});

function atualizarBotaoOk() {
    let valido = modalTipo.value !== '';
    if (modalTipo.value === 'contrato_estagio') {
        valido = valido && document.querySelector('input[name="modalArea"]:checked') !== null;
    }
    btnOk.disabled = !valido;
    btnOk.className = valido
        ? 'flex-1 py-2.5 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white font-semibold rounded-xl transition-all'
        : 'flex-1 py-2.5 bg-gray-300 text-white font-semibold rounded-xl transition-all cursor-not-allowed';
}

btnOk.addEventListener('click', () => {
    if (btnOk.disabled) return;
    const arquivo = filaAtual[indiceFila];
    const tipo = modalTipo.value;
    const areaSel = document.querySelector('input[name="modalArea"]:checked');
    const area = tipo === 'contrato_estagio' ? (areaSel ? areaSel.value : null) : null;

    arquivosClassificados.push({ file: arquivo, tipo, area });
    indiceFila++;
    renderListaPendentes();
    abrirModalParaIndice();
});

btnCancelarArquivo.addEventListener('click', () => {
    indiceFila++;
    abrirModalParaIndice();
});

function atualizarVisualCaixaUpload() {
    const qtd = arquivosClassificados.length;

    if (qtd === 0) {
        uploadCard.classList.remove('tem-arquivos');
        dropZone.classList.remove('compacto');
        dropZoneIcon.classList.remove('sucesso');
        dropZoneIcon.innerHTML = '<i class="fas fa-cloud-upload-alt"></i>';
        dropZoneTexto.innerHTML = 'Clique para escolher arquivos <span class="text-gray-400 font-normal">ou arraste aqui</span>';
        dropZoneHint.textContent = 'Qualquer tipo de arquivo, até 5MB cada';
        return;
    }

    uploadCard.classList.add('tem-arquivos');
    dropZone.classList.add('compacto');
    dropZoneIcon.classList.add('sucesso');
    dropZoneIcon.innerHTML = '<i class="fas fa-check"></i>';
    dropZoneTexto.innerHTML = `<strong>${qtd} arquivo${qtd > 1 ? 's' : ''} adicionado${qtd > 1 ? 's' : ''}</strong> <span class="text-gray-400 font-normal">— clique para adicionar mais</span>`;
    dropZoneHint.textContent = 'Confira a lista abaixo antes de enviar';
}

function renderListaPendentes() {
    atualizarVisualCaixaUpload();

    if (arquivosClassificados.length === 0) {
        listaPendentesEl.classList.add('hidden');
        listaPendentesEl.innerHTML = '';
        btnEnviar.disabled = true;
        btnEnviar.classList.add('opacity-50', 'pointer-events-none');
        return;
    }

    listaPendentesEl.classList.remove('hidden');
    listaPendentesEl.innerHTML = '';

    arquivosClassificados.forEach((item, idx) => {
        const div = document.createElement('div');
        div.className = 'upload-file-item';
        div.innerHTML = `
            <div class="upload-file-icon"><i class="fas fa-file-alt"></i></div>
            <div class="upload-file-info">
                <p class="upload-file-name">${item.file.name}</p>
                <p class="upload-file-tag">${nomesLabel(item.tipo, item.area)}</p>
            </div>
            <button type="button" data-idx="${idx}" class="upload-file-remove btnRemoverPendente" title="Remover">
                <i class="fas fa-times"></i>
            </button>
        `;
        listaPendentesEl.appendChild(div);
    });

    document.querySelectorAll('.btnRemoverPendente').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            arquivosClassificados.splice(parseInt(btn.dataset.idx), 1);
            renderListaPendentes();
        });
    });

    btnEnviar.disabled = false;
    btnEnviar.classList.remove('opacity-50', 'pointer-events-none');
}

btnEnviar.addEventListener('click', () => {
    if (arquivosClassificados.length === 0) return;

    const dt = new DataTransfer();
    const classificacoes = [];

    arquivosClassificados.forEach(item => {
        dt.items.add(item.file);
        classificacoes.push({ tipo: item.tipo, area: item.area });
    });

    document.getElementById('arquivosFinal').files = dt.files;
    document.getElementById('classificacoesInput').value = JSON.stringify(classificacoes);
    document.getElementById('formEnvioFinal').submit();
});
</script>

<?php include 'includes/footer.php'; ?>
