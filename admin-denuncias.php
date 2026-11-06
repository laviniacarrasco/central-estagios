<?php
/**
 * =============================================================================
 * ADMIN DE DENÚNCIAS
 * -----------------------------------------------------------------------------
 * Recebe as denúncias do Canal de Denúncias, muda a situação e responde (com
 * anexos). Quem abriu recebe bolinha vermelha e e-mail a cada resposta.
 * Os chats e as denúncias da mentoria ficam em admin-mentoria.php (aba
 * Denúncias), que este perfil também controla.
 * =============================================================================
 */
require_once 'includes/config.php';
require_once 'includes/egressos.php';
require_once 'includes/denuncias.php';
require_once 'includes/melhorias.php';
checkAuth();
if (!denunciaSouAdmin()) { header('Location: ' . perfilHome()); exit; }

$pageTitle = 'Denúncias';
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'responder') {
    $id = (int) ($_POST['id'] ?? 0);
    $r = denunciaResponder($id, (string) ($_POST['texto'] ?? ''), $_FILES['anexos'] ?? null, (string) ($_POST['status'] ?? ''));
    if ($r['ok']) {
        if (function_exists('mhLogAdmin')) mhLogAdmin('Denúncia atualizada', $r['denuncia']['protocolo'] . ' -> ' . $r['denuncia']['status']);
        header('Location: admin-denuncias.php?id=' . $id . '&ok=1'); exit;
    }
    $erro = $r['erro'];
}

$filtro = in_array($_GET['status'] ?? '', ['abertas', 'recebida', 'em_analise', 'concluida', 'arquivada', 'todas'], true) ? $_GET['status'] : 'abertas';
$busca  = trim((string) ($_GET['q'] ?? ''));
$todas  = denunciaTodas();
$cont   = ['recebida' => 0, 'em_analise' => 0, 'concluida' => 0, 'arquivada' => 0, 'novas' => 0];
foreach ($todas as $d) {
    $st = (string) ($d['status'] ?? 'recebida');
    $cont[$st] = ($cont[$st] ?? 0) + 1;
    if (!empty($d['naoLidoAdmin'])) $cont['novas']++;
}
$lista = array_values(array_filter($todas, function ($d) use ($filtro, $busca) {
    $st = $d['status'] ?? 'recebida';
    if ($filtro === 'abertas' && !in_array($st, ['recebida', 'em_analise'], true)) return false;
    if (!in_array($filtro, ['abertas', 'todas'], true) && $st !== $filtro) return false;
    if ($busca !== '' && mb_stripos(($d['protocolo'] ?? '') . ' ' . ($d['assunto'] ?? '') . ' ' . (!empty($d['anonimo']) ? '' : ($d['autorNome'] ?? '')), $busca) === false) return false;
    return true;
}));
usort($lista, fn($a, $b) => (!empty($b['naoLidoAdmin']) <=> !empty($a['naoLidoAdmin'])) ?: (($b['atualizadoTs'] ?? 0) <=> ($a['atualizadoTs'] ?? 0)));

$abrir = isset($_GET['id']) ? denunciaPorId((int) $_GET['id']) : null;
if ($abrir) { denunciaMarcarLida((int) $abrir['id']); $abrir = denunciaPorId((int) $abrir['id']); }

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-7xl mx-auto animate-fade-in">

        <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
            <div>
                <h2 class="text-3xl font-extrabold text-slate-900 mb-1">Denúncias</h2>
                <p class="text-slate-500">Avalie, responda e encerre. Quem abriu é avisado por e-mail e pela bolinha vermelha.</p>
            </div>
            <a href="admin-mentoria.php?tab=denuncias" class="fsa-btn fsa-btn-secundario"><i class="fas fa-comments mr-2"></i>Chats e denúncias da mentoria</a>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <?php foreach ([['Com novidade', $cont['novas'], '#E35444', 'fa-bell'], ['Recebidas', $cont['recebida'], '#4A9FCA', 'fa-inbox'], ['Em análise', $cont['em_analise'], '#F39C12', 'fa-search'], ['Concluídas', $cont['concluida'], '#27AE60', 'fa-check-circle']] as [$rot, $n, $cor, $ic]): ?>
                <div class="bg-white rounded-2xl p-5 flex items-center gap-4">
                    <span class="w-11 h-11 rounded-2xl flex items-center justify-center" style="background: <?= $cor ?>1a; color: <?= $cor ?>;"><i class="fas <?= $ic ?>"></i></span>
                    <div><p class="text-2xl font-bold text-slate-900"><?= (int) $n ?></p><p class="text-sm text-slate-500"><?= $rot ?></p></div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($erro): ?><div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm"><?= egh($erro) ?></div><?php endif; ?>
        <?php if (isset($_GET['ok'])): ?><div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm"><i class="fas fa-check-circle mr-2"></i>Salvo. A pessoa foi avisada.</div><?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            <section class="lg:col-span-2 bg-white rounded-2xl p-5">
                <form method="GET" action="admin-denuncias.php" class="flex flex-wrap gap-2 mb-4">
                    <input type="text" name="q" value="<?= egh($busca) ?>" placeholder="Protocolo, assunto ou nome" class="fsa-input flex-1 min-w-[160px]">
                    <select name="status" onchange="this.form.requestSubmit()" class="fsa-input w-auto">
                        <?php foreach (['abertas' => 'Abertas', 'recebida' => 'Recebidas', 'em_analise' => 'Em análise', 'concluida' => 'Concluídas', 'arquivada' => 'Arquivadas', 'todas' => 'Todas'] as $k => $rot): ?>
                            <option value="<?= $k ?>" <?= $filtro === $k ? 'selected' : '' ?>><?= $rot ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php if (!$lista): ?>
                    <p class="py-10 text-center text-sm text-slate-400">Nenhuma denúncia aqui.</p>
                <?php else: ?>
                    <ul class="space-y-2 max-h-[70vh] overflow-y-auto pr-1">
                        <?php foreach ($lista as $d): [$t, $cls] = denunciaStatusInfo((string) $d['status']); ?>
                            <li>
                                <a href="admin-denuncias.php?id=<?= (int) $d['id'] ?>&amp;status=<?= $filtro ?>" data-parcial class="block p-3 rounded-xl border <?= $abrir && (int) $abrir['id'] === (int) $d['id'] ? 'border-[#4A9FCA] bg-[#f4f9fc]' : 'border-slate-100 hover:bg-slate-50' ?>">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-sm font-semibold text-slate-800 truncate"><?= egh($d['assunto']) ?></p>
                                        <?php if (!empty($d['naoLidoAdmin'])): ?><span class="w-2.5 h-2.5 rounded-full bg-red-500 flex-shrink-0" title="Novidade"></span><?php endif; ?>
                                    </div>
                                    <p class="text-xs text-slate-400 mt-0.5"><?= egh($d['protocolo']) ?> · <?= !empty($d['anonimo']) ? 'Anônimo' : egh($d['autorNome'] . ' (' . $d['autorPerfil'] . ')') ?></p>
                                    <p class="text-xs mt-1"><span class="px-1.5 py-0.5 rounded-full border <?= $cls ?>"><?= $t ?></span> <span class="text-slate-400"><?= date('d/m H:i', (int) ($d['atualizadoTs'] ?? 0)) ?></span></p>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="lg:col-span-3 bg-white rounded-2xl p-6">
                <?php if (!$abrir): ?>
                    <div class="py-20 text-center text-slate-400"><i class="fas fa-shield-alt text-4xl mb-3"></i><p>Escolha uma denúncia na lista.</p></div>
                <?php else: [$stTxt, $stCls, $stIco] = denunciaStatusInfo((string) $abrir['status']); ?>
                    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wide"><?= egh($abrir['protocolo']) ?> · <?= egh(denunciaCategorias()[$abrir['categoria']] ?? '') ?></p>
                            <h3 class="text-xl font-bold text-slate-900"><?= egh($abrir['assunto']) ?></h3>
                            <p class="text-xs text-slate-500 mt-1">
                                <?= !empty($abrir['anonimo']) ? '<i class="fas fa-user-secret mr-1"></i>Enviada como anônima' : 'Por <strong>' . egh($abrir['autorNome']) . '</strong> (' . egh($abrir['autorPerfil']) . ') · ' . egh($abrir['autorEmail']) ?>
                                · aberta em <?= egh($abrir['criadoEm']) ?>
                            </p>
                        </div>
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold border <?= $stCls ?>"><i class="fas <?= $stIco ?>"></i><?= $stTxt ?></span>
                    </div>
                    <div class="space-y-4 max-h-[50vh] overflow-y-auto pr-1">
                        <?php foreach ((array) $abrir['mensagens'] as $m): $doAdmin = ($m['de'] ?? '') === 'admin'; ?>
                            <div class="flex <?= $doAdmin ? 'justify-end' : '' ?>">
                                <div class="max-w-[85%] rounded-2xl px-4 py-3 <?= $doAdmin ? 'bg-[#e8f3fa]' : 'bg-slate-100' ?> text-slate-800">
                                    <p class="text-xs font-bold mb-1 <?= $doAdmin ? 'text-[#2B7FA6]' : 'text-slate-500' ?>"><?= $doAdmin ? 'Equipe' : (!empty($abrir['anonimo']) ? 'Anônimo' : egh($m['nome'])) ?> · <?= date('d/m/Y H:i', (int) $m['ts']) ?></p>
                                    <?php if (trim((string) $m['texto']) !== ''): ?><p class="text-sm whitespace-pre-line"><?= egh($m['texto']) ?></p><?php endif; ?>
                                    <?= denunciaHtmlAnexos($abrir, (array) ($m['anexos'] ?? [])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <form method="POST" action="admin-denuncias.php" enctype="multipart/form-data" data-parcial class="mt-6 pt-5 border-t border-slate-100 space-y-3">
                        <input type="hidden" name="acao" value="responder">
                        <input type="hidden" name="id" value="<?= (int) $abrir['id'] ?>">
                        <?= denunciaHtmlCampoMensagem('texto', 'Resposta para quem abriu a denúncia (ela vê esta mensagem)...', false, 4) ?>
                        <div class="flex flex-wrap items-center gap-3">
                            <label class="text-sm text-slate-600">Situação:
                                <select name="status" class="fsa-input w-auto inline-block ml-1">
                                    <?php foreach (['recebida' => 'Recebida', 'em_analise' => 'Em análise', 'concluida' => 'Concluída', 'arquivada' => 'Arquivada'] as $k => $rot): ?>
                                        <option value="<?= $k ?>" <?= $abrir['status'] === $k ? 'selected' : '' ?>><?= $rot ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <button type="submit" class="fsa-btn fsa-btn-primario"><i class="fas fa-paper-plane mr-2"></i>Enviar e salvar</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        </div>
        <?= denunciaJsCampoMensagem() ?>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
