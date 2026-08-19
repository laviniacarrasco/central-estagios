<?php
require_once 'includes/config.php';
checkAuth();

$pageTitle = 'Assinaturas';
$userId = $_SESSION['user_id'];

$EMAIL_AJUDA = 'coordenacao@fsa.br';
$MAX_SIZE = 5 * 1024 * 1024; // 5MB

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

// ===== POST: nova(s) solicitação(ões) — uma por arquivo classificado =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nova_solicitacao'])) {
    $classificacoes = json_decode($_POST['classificacoes'] ?? '[]', true);

    if (!is_array($classificacoes) || empty($_FILES['arquivos']['name'][0])) {
        header('Location: assinaturas.php?erro=arquivo');
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
        header('Location: assinaturas.php?erro=arquivo');
        exit;
    }

    saveData('assinaturas', $assinaturas);
    header('Location: assinaturas.php?ok=1');
    exit;
}

// Solicitações do aluno logado, mais recentes primeiro
$minhasSolicitacoes = array_values(array_filter($assinaturas, fn($a) => $a['userId'] == $userId));
usort($minhasSolicitacoes, fn($a, $b) => ($b['id'] ?? 0) <=> ($a['id'] ?? 0));

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
<div class="p-8 max-w-5xl mx-auto">

    <div class="mb-8 flex items-center justify-between">
        <div>
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Assinaturas</h2>
            <p class="text-gray-600">Envie contratos de estágio ou fim de estágio para assinatura da coordenação</p>
        </div>
        <button type="button" onclick="abrirModalAjuda()"
            class="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-xl text-sm text-gray-700 hover:bg-gray-50">
            <i class="fas fa-question-circle text-[#4A9FCA]"></i> Precisa de ajuda?
        </button>
    </div>

    <?php if (isset($_GET['ok'])): ?>
        <div class="mb-6 bg-green-50 border border-green-100 text-green-700 text-sm rounded-xl p-4 flex items-center gap-2">
            <i class="fas fa-check-circle"></i> Solicitação(ões) enviada(s) com sucesso!
        </div>
    <?php elseif (isset($_GET['erro'])): ?>
        <div class="mb-6 bg-red-50 border border-red-100 text-red-700 text-sm rounded-xl p-4 flex items-center gap-2">
            <i class="fas fa-exclamation-triangle"></i> Não foi possível enviar. Verifique os arquivos e tente novamente.
        </div>
    <?php endif; ?>

    <!-- Minhas solicitações (topo) -->
    <h3 class="text-lg font-bold text-gray-900 mb-4">Minhas solicitações</h3>

    <?php if (empty($minhasSolicitacoes)): ?>
        <p class="text-gray-400 text-sm italic mb-8">Nenhuma solicitação enviada ainda.</p>
    <?php endif; ?>

    <div class="space-y-5 mb-10">
        <?php foreach ($minhasSolicitacoes as $sol): ?>
        <?php
            [$labelStatus, $corStatus] = getLabelStatus($sol['status']);
            $etapas = ['solicitado', 'em_analise', 'assinado'];
            $etapaAtual = array_search($sol['status'], $etapas);
            if ($etapaAtual === false) $etapaAtual = -1; // erro
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

            <?php if ($sol['status'] === 'erro'): ?>
                <div class="bg-red-50 border border-red-100 text-red-700 text-sm rounded-xl p-3 mb-4">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <?php echo htmlspecialchars($sol['mensagemErro'] ?: 'Ocorreu um problema com seus arquivos.'); ?>
                    — entre em contato: <strong><?php echo htmlspecialchars($EMAIL_AJUDA); ?></strong>
                </div>
            <?php else: ?>
                <!-- Stepper -->
                <div class="flex items-center mb-4">
                    <?php foreach (['Solicitação enviada', 'Recebido para análise', 'Assinado'] as $i => $texto): ?>
                        <div class="flex-1 flex flex-col items-center relative">
                            <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold z-10
                                <?php echo $i <= $etapaAtual ? 'bg-[#4A9FCA] text-white' : 'bg-gray-200 text-gray-500'; ?>">
                                <?php echo $i < $etapaAtual ? '<i class="fas fa-check"></i>' : $i + 1; ?>
                            </div>
                            <span class="text-[11px] text-center text-gray-500 mt-1 px-1"><?php echo $texto; ?></span>
                        </div>
                        <?php if ($i < 2): ?>
                            <div class="flex-1 h-0.5 -mt-4 <?php echo $i < $etapaAtual ? 'bg-[#4A9FCA]' : 'bg-gray-200'; ?>"></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="flex flex-wrap gap-2 text-sm">
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
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Nova solicitação -->
    <div class="bg-white p-6 rounded-2xl shadow-sm mb-8">
        <h3 class="text-lg font-bold text-gray-900 mb-1">
            <i class="fas fa-file-signature text-[#4A9FCA] mr-2"></i>Nova solicitação
        </h3>
        <p class="text-sm text-gray-500 mb-5">Envie um ou mais arquivos. Você vai classificar cada um logo em seguida.</p>

        <!-- Dropzone -->
        <div id="dropZone"
             class="relative flex flex-col items-center justify-center gap-3 border-2 border-dashed border-gray-300 rounded-2xl py-14 px-6 cursor-pointer transition-all hover:border-[#4A9FCA] hover:bg-blue-50/40">
            <div class="w-16 h-16 rounded-2xl bg-blue-50 flex items-center justify-center">
                <i class="fas fa-cloud-upload-alt text-3xl text-[#4A9FCA]"></i>
            </div>
            <p class="text-gray-700 font-semibold text-center">
                Clique para escolher arquivos <span class="text-gray-400 font-normal">ou arraste aqui</span>
            </p>
            <p class="text-xs text-gray-400">Qualquer tipo de arquivo, até 5MB cada</p>
            <input type="file" id="fileInput" multiple class="hidden">
        </div>

        <!-- Lista dos arquivos já classificados, aguardando envio -->
        <div id="listaArquivosPendentes" class="mt-5 space-y-2 hidden"></div>

        <div class="flex justify-end mt-5">
            <button type="button" id="btnEnviarAssinatura" disabled
                class="opacity-50 pointer-events-none px-6 py-3 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white font-semibold rounded-xl transition-all">
                <i class="fas fa-paper-plane mr-2"></i>Enviar para assinatura
            </button>
        </div>
    </div>

</div>
</main>

<!-- Form real, enviado via JS quando o usuário clica em "Enviar" -->
<form id="formEnvioFinal" method="POST" action="assinaturas.php" enctype="multipart/form-data" class="hidden">
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

        <!-- Lista de todos os arquivos da leva atual, destacando o atual -->
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

<script>
function abrirModalAjuda() { document.getElementById('modalAjuda').classList.remove('hidden'); }
function fecharModalAjuda() { document.getElementById('modalAjuda').classList.add('hidden'); }

// ===================== NOVO FLUXO DE UPLOAD + CLASSIFICAÇÃO =====================
const dropZone   = document.getElementById('dropZone');
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

let arquivosClassificados = []; // { file, tipo, area }
let filaAtual = [];             // arquivos da leva sendo classificada agora (File[])
let indiceFila = 0;
let totalFilaOriginal = 0;

function nomesLabel(tipo, area) {
    if (tipo === 'contrato_estagio') {
        return 'Contrato de Estágio' + (area === 'dentro' ? ' (dentro da área)' : area === 'fora' ? ' (fora da área)' : '');
    }
    if (tipo === 'fim_estagio') return 'Fim de Estágio';
    return '';
}

// --- Drag & drop / clique ---
dropZone.addEventListener('click', () => fileInput.click());

dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.classList.add('border-[#4A9FCA]', 'bg-blue-50/40');
});
dropZone.addEventListener('dragleave', () => {
    dropZone.classList.remove('border-[#4A9FCA]', 'bg-blue-50/40');
});
dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('border-[#4A9FCA]', 'bg-blue-50/40');
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

    // Lista de nomes da leva, destacando o atual
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
    // simplesmente pula este arquivo sem classificar
    indiceFila++;
    abrirModalParaIndice();
});

function renderListaPendentes() {
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
        div.className = 'flex items-center justify-between bg-gray-50 border border-gray-200 rounded-xl px-4 py-3';
        div.innerHTML = `
            <div class="flex items-center gap-3 min-w-0">
                <i class="fas fa-file-alt text-[#4A9FCA]"></i>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate">${item.file.name}</p>
                    <p class="text-xs text-gray-500">${nomesLabel(item.tipo, item.area)}</p>
                </div>
            </div>
            <button type="button" data-idx="${idx}" class="btnRemoverPendente text-gray-400 hover:text-red-500 px-2">
                <i class="fas fa-times"></i>
            </button>
        `;
        listaPendentesEl.appendChild(div);
    });

    document.querySelectorAll('.btnRemoverPendente').forEach(btn => {
        btn.addEventListener('click', () => {
            arquivosClassificados.splice(parseInt(btn.dataset.idx), 1);
            renderListaPendentes();
        });
    });

    btnEnviar.disabled = false;
    btnEnviar.classList.remove('opacity-50', 'pointer-events-none');
}

// --- Envio final ---
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
