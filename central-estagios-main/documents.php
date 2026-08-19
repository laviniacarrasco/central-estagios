<?php
require_once 'includes/config.php';
checkAuth();

$pageTitle = 'Documentos';

// Certificados ATIVOS â€” isolado por usuÃ¡rio
$documents = loadData('userCertificates', true);
if (empty($documents)) {
    $documents = [];
}

// Certificados na LIXEIRA â€” isolado por usuÃ¡rio
$trash = loadData('userCertificatesTrash', true);
if (empty($trash)) {
    $trash = [];
}

// âœ… NOVO: Certificados removidos definitivamente, mas mantidos "arquivados"
// Nunca aparecem na tela, mas continuam contando nas horas/total,
// pois o certificado Ã© um registro OFICIAL da faculdade â€” o app sÃ³
// deixa de exibir, nunca invalida o registro de verdade.
$archive = loadData('userCertificatesArchive', true);
if (empty($archive)) {
    $archive = [];
}

// Mover certificado para a lixeira (nÃ£o apaga de verdade)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = $_POST['id'] ?? 0;

    $itemMovido = null;
    $documents = array_filter($documents, function ($doc) use ($id, &$itemMovido) {
        if ($doc['id'] == $id) {
            $itemMovido = $doc;
            return false;
        }
        return true;
    });

    if ($itemMovido) {
        $itemMovido['deletedAt'] = date('d/m/Y H:i');
        $trash[] = $itemMovido;
        saveData('userCertificatesTrash', array_values($trash), true);
    }

    saveData('userCertificates', array_values($documents), true);
    header('Location: documents.php');
    exit;
}

// Restaurar certificado da lixeira
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore') {
    $id = $_POST['id'] ?? 0;

    $itemRestaurado = null;
    $trash = array_filter($trash, function ($doc) use ($id, &$itemRestaurado) {
        if ($doc['id'] == $id) {
            $itemRestaurado = $doc;
            return false;
        }
        return true;
    });

    if ($itemRestaurado) {
        unset($itemRestaurado['deletedAt']);
        $documents[] = $itemRestaurado;
        saveData('userCertificates', array_values($documents), true);
    }

    saveData('userCertificatesTrash', array_values($trash), true);
    header('Location: documents.php?restaurado=1');
    exit;
}

// âœ… ALTERADO: "Excluir definitivamente" agora move o item para o
// ARQUIVO MORTO (userCertificatesArchive.json) em vez de apagar de verdade.
// Assim as horas e a contagem de certificados NUNCA diminuem,
// mesmo removendo da lixeira, pois o registro Ã© oficial da faculdade.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_forever') {
    $id = $_POST['id'] ?? 0;

    $itemArquivado = null;
    $trash = array_filter($trash, function ($doc) use ($id, &$itemArquivado) {
        if ($doc['id'] == $id) {
            $itemArquivado = $doc;
            return false;
        }
        return true;
    });

    if ($itemArquivado) {
        $itemArquivado['archivedAt'] = date('d/m/Y H:i');
        $archive[] = $itemArquivado;
        saveData('userCertificatesArchive', array_values($archive), true);
    }

    saveData('userCertificatesTrash', array_values($trash), true);
    header('Location: documents.php?removido=1');
    exit;
}

// âœ… ALTERADO: total de horas e total de certificados agora somam
// ativos + lixeira + arquivo morto â€” nada reduz o total, mesmo excluindo
// definitivamente, porque o certificado continua valendo na faculdade.
$todosCertificados = array_merge($documents, $trash, $archive);

$totalHours = array_reduce($todosCertificados, function ($sum, $doc) {
    return $sum + intval($doc['hours']);
}, 0);

$totalCertificados = count($todosCertificados);

$remainingHours      = max(0, 200 - $totalHours);
$progressPercentage  = min(100, ($totalHours / 200) * 100);

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-7xl mx-auto">

        <!-- Header -->
        <div class="mb-8 flex items-start justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-3xl font-bold text-gray-900 mb-2">Atividades Complementares</h2>
                <p class="text-gray-600">Visualize e gerencie seus certificados aprovados</p>
            </div>

            <!-- BotÃ£o Lixeira -->
            <button onclick="abrirLixeira()"
                class="relative flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-50 transition-all shadow-sm">
                <i class="fas fa-trash-alt"></i> Lixeira
                <?php if (!empty($trash)): ?>
                    <span class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center">
                        <?php echo count($trash); ?>
                    </span>
                <?php endif; ?>
            </button>
        </div>

        <!-- Aviso: restaurado -->
        <?php if (isset($_GET['restaurado'])): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl flex items-center gap-3">
                <i class="fas fa-check-circle text-green-500"></i>
                <p class="text-green-800 font-medium">Certificado restaurado com sucesso.</p>
            </div>
        <?php endif; ?>

        <!-- Aviso: removido definitivamente -->
        <?php if (isset($_GET['removido'])): ?>
            <div class="mb-6 p-4 bg-gray-50 border border-gray-200 rounded-xl flex items-center gap-3">
                <i class="fas fa-info-circle text-gray-500"></i>
                <p class="text-gray-700 font-medium">Certificado removido da lixeira. O registro oficial continua computado nas suas horas.</p>
            </div>
        <?php endif; ?>

        <!-- Info Card -->
        <div class="p-6 mb-8 bg-gradient-to-r from-blue-50 to-purple-50 border border-blue-200 rounded-2xl">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 bg-[#4A9FCA] rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-info-circle text-white text-xl"></i>
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-900 mb-2">Importante</h3>
                    <p class="text-sm text-gray-700">
                        Todos os certificados aqui foram <strong>aprovados pela coordenação</strong> e aparecem automaticamente no seu currÃ­culo.
                    </p>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="p-6 bg-gradient-to-br from-[#27AE60] to-[#229954] text-white rounded-2xl">
                <p class="text-white/80 text-sm mb-1">Horas Aprovadas</p>
                <p class="text-4xl font-bold"><?php echo $totalHours; ?>h</p>
                <div class="mt-4 pt-4 border-t border-white/20">
                    <div class="w-full h-2 bg-white/20 rounded-full overflow-hidden mt-2">
                        <div class="h-full bg-white rounded-full transition-all duration-500" style="width: <?php echo $progressPercentage; ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="p-6 bg-gradient-to-br from-[#4A9FCA] to-[#3A8FB0] text-white rounded-2xl">
                <p class="text-white/80 text-sm mb-1">Certificados</p>
                <p class="text-4xl font-bold"><?php echo $totalCertificados; ?></p>
                <p class="text-xs text-white/80 mt-4 pt-4 border-t border-white/20">Aprovados pela coordenação</p>
            </div>

            <div class="p-6 bg-gradient-to-br from-[#E74C3C] to-[#C0392B] text-white rounded-2xl">
                <p class="text-white/80 text-sm mb-1">Faltam</p>
                <p class="text-4xl font-bold"><?php echo $remainingHours; ?>h</p>
                <p class="text-xs text-white/80 mt-4 pt-4 border-t border-white/20">Para completar 200h</p>
            </div>
        </div>

        <!-- Documents List -->
        <div class="space-y-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-semibold text-gray-900">Certificados Aprovados</h3>
                <span class="px-3 py-1 bg-[#27AE60] text-white rounded-full text-sm">
                    <?php echo count($documents); ?> Certificados
                </span>
            </div>

            <?php foreach ($documents as $doc): ?>
                <div class="bg-white p-6 rounded-2xl shadow-sm hover:shadow-lg transition-all">
                    <div class="flex items-start gap-4">

                        <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-[#27AE60] to-[#229954] flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-certificate text-white text-xl"></i>
                        </div>

                        <div class="flex-1">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <div class="flex items-center gap-2 mb-2">
                                        <h4 class="font-semibold text-gray-900 text-lg"><?php echo htmlspecialchars($doc['title']); ?></h4>
                                        <span class="px-2 py-1 bg-[#27AE60] text-white text-xs rounded-full">Aprovado</span>
                                    </div>
                                    <p class="text-sm text-gray-600 font-medium"><?php echo htmlspecialchars($doc['institution']); ?></p>
                                </div>
                            </div>

                            <div class="flex items-center gap-4 text-sm text-gray-600 mb-4">
                                <span class="flex items-center gap-1 font-semibold text-[#4A9FCA]">
                                    <i class="fas fa-clock"></i> <?php echo $doc['hours']; ?>
                                </span>
                                <span>Concluído em:  <?php echo $doc['date']; ?></span>
                                <span class="px-2 py-1 border rounded-full text-xs"><?php echo $doc['category']; ?></span>
                            </div>

                            <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4 flex items-center gap-2">
                                <i class="fas fa-check-circle text-green-600"></i>
                                <p class="text-sm text-green-800">Certificado aprovado e computado nas suas horas complementares.</p>
                            </div>

                            <div class="flex gap-3">
                                <!-- Excluir (vai para a lixeira) -->
                                <button type="button"
                                    onclick="abrirModalExcluir(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['title'], ENT_QUOTES); ?>')"
                                    class="px-4 py-2 border border-red-200 text-red-600 rounded-lg text-sm hover:bg-red-50 transition-all flex items-center gap-2">
                                    <i class="fas fa-trash-alt"></i> Excluir
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($documents)): ?>
                <div class="text-center py-20 bg-white rounded-xl border-2 border-dashed border-gray-200">
                    <i class="fas fa-file-alt text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 font-medium">Nenhum certificado aprovado encontrado.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Progress Card -->
        <div class="bg-white p-6 rounded-2xl shadow-sm mt-8">
            <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <i class="fas fa-trophy text-yellow-400"></i> Progresso das Atividades Complementares
            </h3>
            <div class="mb-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700"><?php echo $totalHours; ?>h de 200h necessÃ¡rias</span>
                    <span class="text-sm font-bold text-[#27AE60]"><?php echo round($progressPercentage); ?>%</span>
                </div>
                <div class="w-full h-4 bg-gray-200 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-[#27AE60] to-[#229954] rounded-full transition-all duration-1000"
                         style="width: <?php echo $progressPercentage; ?>%"></div>
                </div>
            </div>
            <p class="text-sm text-gray-600">
                <?php if ($remainingHours > 0): ?>
                    Continue participando de cursos e eventos para completar as <?php echo $remainingHours; ?>h restantes!
                <?php else: ?>
                    ParabÃ©ns! VocÃª completou todas as horas complementares necessÃ¡rias.
                <?php endif; ?>
            </p>
        </div>

        <!-- Form oculto: mover para lixeira -->
        <form id="formExcluir" method="POST" action="documents.php">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="excluirId" value="">
        </form>

        <!-- Form oculto: restaurar -->
        <form id="formRestaurar" method="POST" action="documents.php">
            <input type="hidden" name="action" value="restore">
            <input type="hidden" name="id" id="restaurarId" value="">
        </form>

        <!-- Form oculto: excluir definitivamente -->
        <form id="formExcluirDefinitivo" method="POST" action="documents.php">
            <input type="hidden" name="action" value="delete_forever">
            <input type="hidden" name="id" id="excluirDefinitivoId" value="">
        </form>

        <!-- Modal Excluir (mover para lixeira) -->
        <div id="modalExcluir" class="fixed inset-0 z-[999] hidden items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="fecharModalExcluir()"></div>
            <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-sm p-8 z-10">

                <div class="w-16 h-16 bg-red-100 rounded-2xl flex items-center justify-center mx-auto mb-5">
                    <i class="fas fa-trash-alt text-red-500 text-3xl"></i>
                </div>

                <h3 class="text-xl font-bold text-gray-900 text-center mb-2">Excluir Certificado?</h3>
                <p class="text-gray-500 text-sm text-center mb-1 leading-relaxed">VocÃª estÃ¡ prestes a excluir:</p>
                <p id="modalNomeCertificado" class="text-[#4A9FCA] font-semibold text-sm text-center mb-3 leading-relaxed"></p>
                <p class="text-gray-400 text-xs text-center mb-8">O certificado irÃ¡ para a lixeira e poderÃ¡ ser restaurado depois. As horas continuam sendo computadas, pois o registro Ã© oficial da faculdade.</p>

                <div class="flex gap-3">
                    <button onclick="fecharModalExcluir()"
                            class="flex-1 py-3 px-4 rounded-2xl border-2 border-gray-200 text-gray-600 font-semibold hover:bg-gray-50 transition-all text-sm">
                        <i class="fas fa-times mr-1"></i> Cancelar
                    </button>
                    <button onclick="confirmarExcluir()"
                            class="flex-1 py-3 px-4 rounded-2xl bg-red-500 hover:bg-red-600 text-white font-semibold transition-all text-sm shadow-lg shadow-red-100">
                        <i class="fas fa-trash-alt mr-1"></i> Excluir
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Lixeira -->
        <div id="modalLixeira" class="fixed inset-0 z-[999] hidden items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="fecharLixeira()"></div>
            <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-lg p-8 z-10 max-h-[80vh] overflow-y-auto">

                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-gray-100 rounded-2xl flex items-center justify-center">
                            <i class="fas fa-trash-alt text-gray-500 text-xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900">Lixeira</h3>
                    </div>
                    <button onclick="fecharLixeira()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>

                <?php if (empty($trash)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-box-open text-5xl text-gray-200 mb-4"></i>
                        <p class="text-gray-400 text-sm">A lixeira estÃ¡ vazia.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($trash as $doc): ?>
                            <div class="flex items-center justify-between gap-4 p-4 bg-gray-50 rounded-xl border border-gray-100">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($doc['title']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($doc['institution']); ?> Â· <?php echo htmlspecialchars($doc['hours']); ?></p>
                                    <p class="text-xs text-gray-400 mt-1">ExcluÃ­do em <?php echo htmlspecialchars($doc['deletedAt'] ?? ''); ?></p>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <button type="button" onclick="restaurarCertificado(<?php echo $doc['id']; ?>)"
                                        class="px-3 py-2 bg-[#4A9FCA] text-white text-xs font-semibold rounded-lg hover:bg-[#3A8FB0] transition-all flex items-center gap-1">
                                        <i class="fas fa-undo"></i> Restaurar
                                    </button>
                                    <button type="button" onclick="abrirModalExcluirDefinitivo(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['title'], ENT_QUOTES); ?>')"
                                        class="px-3 py-2 border border-red-200 text-red-500 text-xs font-semibold rounded-lg hover:bg-red-50 transition-all flex items-center gap-1">
                                        <i class="fas fa-times"></i> Remover
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modal Excluir Definitivamente -->
        <div id="modalExcluirDefinitivo" class="fixed inset-0 z-[1000] hidden items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="fecharModalExcluirDefinitivo()"></div>
            <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-sm p-8 z-10">

                <div class="w-16 h-16 bg-red-100 rounded-2xl flex items-center justify-center mx-auto mb-5">
                    <i class="fas fa-exclamation-triangle text-red-500 text-3xl"></i>
                </div>

                <h3 class="text-xl font-bold text-gray-900 text-center mb-2">Remover da Lixeira?</h3>
                <p class="text-gray-500 text-sm text-center mb-1 leading-relaxed">VocÃª estÃ¡ prestes a remover da lixeira:</p>
                <p id="modalNomeExcluirDefinitivo" class="text-[#4A9FCA] font-semibold text-sm text-center mb-3 leading-relaxed"></p>
                <p class="text-gray-400 text-xs text-center mb-8">O certificado deixarÃ¡ de aparecer aqui, mas continuarÃ¡ computado nas suas horas, pois Ã© um registro oficial da faculdade.</p>

                <div class="flex gap-3">
                    <button onclick="fecharModalExcluirDefinitivo()"
                            class="flex-1 py-3 px-4 rounded-2xl border-2 border-gray-200 text-gray-600 font-semibold hover:bg-gray-50 transition-all text-sm">
                        <i class="fas fa-times mr-1"></i> Cancelar
                    </button>
                    <button onclick="confirmarExcluirDefinitivo()"
                            class="flex-1 py-3 px-4 rounded-2xl bg-red-500 hover:bg-red-600 text-white font-semibold transition-all text-sm shadow-lg shadow-red-100">
                        <i class="fas fa-trash-alt mr-1"></i> Remover
                    </button>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
    // =============================
    // Modal Excluir (mover para lixeira)
    // =============================
    function abrirModalExcluir(id, nome) {
        document.getElementById('excluirId').value                  = id;
        document.getElementById('modalNomeCertificado').textContent = nome;
        const modal = document.getElementById('modalExcluir');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    function fecharModalExcluir() {
        const modal = document.getElementById('modalExcluir');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    function confirmarExcluir() {
        document.getElementById('formExcluir').submit();
    }

    // =============================
    // Modal Lixeira
    // =============================
    function abrirLixeira() {
        const modal = document.getElementById('modalLixeira');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    function fecharLixeira() {
        const modal = document.getElementById('modalLixeira');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    function restaurarCertificado(id) {
        document.getElementById('restaurarId').value = id;
        document.getElementById('formRestaurar').submit();
    }

    // =============================
    // Modal Excluir Definitivamente
    // =============================
    function abrirModalExcluirDefinitivo(id, nome) {
        document.getElementById('excluirDefinitivoId').value = id;
        document.getElementById('modalNomeExcluirDefinitivo').textContent = nome;
        const modal = document.getElementById('modalExcluirDefinitivo');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    function fecharModalExcluirDefinitivo() {
        const modal = document.getElementById('modalExcluirDefinitivo');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    function confirmarExcluirDefinitivo() {
        document.getElementById('formExcluirDefinitivo').submit();
    }

    // Fecha com ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            fecharModalExcluir();
            fecharLixeira();
            fecharModalExcluirDefinitivo();
        }
    });
</script>

<?php include 'includes/footer.php'; ?>