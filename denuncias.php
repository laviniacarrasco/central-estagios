<?php
/**
 * =============================================================================
 * CANAL DE DENÚNCIAS (quem denuncia)
 * -----------------------------------------------------------------------------
 * Abrir uma denúncia (com anexos ou imagem colada), acompanhar as respostas
 * e responder. Quem avalia é o Admin de Denúncias (admin-denuncias.php).
 * Lógica em includes/denuncias.php.
 * =============================================================================
 */
require_once 'includes/config.php';
require_once 'includes/egressos.php';
require_once 'includes/denuncias.php';
checkAuth();

if (denunciaSouAdmin()) { header('Location: admin-denuncias.php'); exit; }

$pageTitle = 'Canal de Denúncias';
$userId = (int) $_SESSION['user_id'];
$eu = egressoCarregarUsuario($userId) ?: ['id' => $userId, 'nome' => $_SESSION['user_name'] ?? ''];
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');
    if ($acao === 'abrir') {
        $r = denunciaCriar($eu, (string) ($_POST['categoria'] ?? ''), (string) ($_POST['assunto'] ?? ''), (string) ($_POST['texto'] ?? ''), !empty($_POST['anonimo']), $_FILES['anexos'] ?? null);
        if ($r['ok']) { header('Location: denuncias.php?id=' . (int) $r['denuncia']['id'] . '&enviada=1'); exit; }
        $erro = $r['erro'];
    }
    if ($acao === 'responder') {
        $id = (int) ($_POST['id'] ?? 0);
        $r = denunciaResponder($id, (string) ($_POST['texto'] ?? ''), $_FILES['anexos'] ?? null);
        if ($r['ok']) { header('Location: denuncias.php?id=' . $id . '&respondida=1'); exit; }
        $erro = $r['erro'];
    }
}

$abrir = null;
if (isset($_GET['id'])) {
    $abrir = denunciaPorId((int) $_GET['id']);
    if ($abrir && (int) ($abrir['autorId'] ?? 0) !== $userId) $abrir = null;
    if ($abrir) { denunciaMarcarLida((int) $abrir['id']); $abrir = denunciaPorId((int) $abrir['id']); }
}
$minhas = array_values(array_filter(denunciaTodas(), fn($d) => (int) ($d['autorId'] ?? 0) === $userId));
usort($minhas, fn($a, $b) => ($b['atualizadoTs'] ?? 0) <=> ($a['atualizadoTs'] ?? 0));

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-6xl mx-auto animate-fade-in">

        <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
            <div>
                <h2 class="text-3xl font-extrabold text-slate-900 mb-1">Canal de Denúncias</h2>
                <p class="text-slate-500">Conte com segurança o que aconteceu. A equipe analisa com sigilo e responde por aqui.</p>
            </div>
            <?php if ($abrir): ?>
                <a href="denuncias.php" data-parcial class="fsa-btn fsa-btn-secundario"><i class="fas fa-plus mr-2"></i>Nova denúncia</a>
            <?php endif; ?>
        </div>

        <?php if ($erro): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm"><i class="fas fa-exclamation-circle mr-2"></i><?= egh($erro) ?></div>
        <?php elseif (isset($_GET['enviada'])): ?>
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm"><i class="fas fa-check-circle mr-2"></i>Denúncia enviada. Guarde o protocolo. Quando houver resposta, você recebe um e-mail e vê a bolinha vermelha no menu.</div>
        <?php elseif (isset($_GET['respondida'])): ?>
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm"><i class="fas fa-check-circle mr-2"></i>Mensagem enviada.</div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
            <?php if ($abrir): [$stTxt, $stCls, $stIco] = denunciaStatusInfo((string) $abrir['status']); $encerrada = in_array($abrir['status'], ['concluida', 'arquivada'], true); ?>
                <section class="bg-white rounded-2xl p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wide"><?= egh($abrir['protocolo']) ?> · <?= egh(denunciaCategorias()[$abrir['categoria']] ?? '') ?></p>
                            <h3 class="text-xl font-bold text-slate-900"><?= egh($abrir['assunto']) ?></h3>
                            <p class="text-xs text-slate-400 mt-1">Aberta em <?= egh($abrir['criadoEm']) ?><?= !empty($abrir['anonimo']) ? ' · enviada como anônima' : '' ?></p>
                        </div>
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold border <?= $stCls ?>"><i class="fas <?= $stIco ?>"></i><?= $stTxt ?></span>
                    </div>
                    <div class="space-y-4">
                        <?php foreach ((array) $abrir['mensagens'] as $m): $doAdmin = ($m['de'] ?? '') === 'admin'; ?>
                            <div class="flex <?= $doAdmin ? '' : 'justify-end' ?>">
                                <div class="max-w-[85%] rounded-2xl px-4 py-3 <?= $doAdmin ? 'bg-[#e8f3fa]' : 'bg-slate-100' ?> text-slate-800">
                                    <p class="text-xs font-bold mb-1 <?= $doAdmin ? 'text-[#2B7FA6]' : 'text-slate-500' ?>"><?= $doAdmin ? 'Equipe de Denúncias' : 'Você' ?> · <?= date('d/m/Y H:i', (int) $m['ts']) ?></p>
                                    <?php if (trim((string) $m['texto']) !== ''): ?><p class="text-sm whitespace-pre-line"><?= egh($m['texto']) ?></p><?php endif; ?>
                                    <?= denunciaHtmlAnexos($abrir, (array) ($m['anexos'] ?? [])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!$encerrada): ?>
                        <form method="POST" action="denuncias.php" enctype="multipart/form-data" data-parcial class="mt-6 pt-5 border-t border-slate-100">
                            <input type="hidden" name="acao" value="responder">
                            <input type="hidden" name="id" value="<?= (int) $abrir['id'] ?>">
                            <?= denunciaHtmlCampoMensagem('texto', 'Escreva uma mensagem para a equipe...', false, 3) ?>
                            <button type="submit" class="fsa-btn fsa-btn-primario mt-3"><i class="fas fa-paper-plane mr-2"></i>Enviar</button>
                        </form>
                    <?php else: ?>
                        <p class="mt-6 pt-5 border-t border-slate-100 text-sm text-slate-500"><i class="fas fa-lock mr-1"></i>Esta denúncia foi encerrada. Se precisar, abra uma nova.</p>
                    <?php endif; ?>
                </section>
            <?php else: ?>
                <section class="bg-white rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-slate-900 mb-4"><i class="fas fa-shield-alt text-[#4A9FCA] mr-2"></i>Nova denúncia</h3>
                    <form method="POST" action="denuncias.php" enctype="multipart/form-data" data-parcial class="space-y-4">
                        <input type="hidden" name="acao" value="abrir">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <label class="block">
                                <span class="block text-sm font-semibold text-slate-700 mb-1.5">Tipo</span>
                                <select name="categoria" required class="fsa-input">
                                    <option value="">Escolha...</option>
                                    <?php foreach (denunciaCategorias() as $k => $rot): ?><option value="<?= $k ?>" <?= ($_POST['categoria'] ?? '') === $k ? 'selected' : '' ?>><?= egh($rot) ?></option><?php endforeach; ?>
                                </select>
                            </label>
                            <label class="block">
                                <span class="block text-sm font-semibold text-slate-700 mb-1.5">Assunto</span>
                                <input type="text" name="assunto" required minlength="5" maxlength="120" value="<?= egh($_POST['assunto'] ?? '') ?>" placeholder="Resumo em poucas palavras" class="fsa-input">
                            </label>
                        </div>
                        <div>
                            <span class="block text-sm font-semibold text-slate-700 mb-1.5">O que aconteceu?</span>
                            <?= denunciaHtmlCampoMensagem('texto', 'Conte com detalhes: o que aconteceu, quando, onde e quem estava envolvido.') ?>
                        </div>
                        <label class="flex items-start gap-3 p-4 rounded-xl bg-slate-50 text-sm text-slate-600 cursor-pointer">
                            <input type="checkbox" name="anonimo" value="1" class="mt-0.5 w-4 h-4">
                            <span><strong class="text-slate-800">Enviar como anônimo.</strong> A equipe não vê o seu nome. Você continua recebendo as respostas aqui e por e-mail.</span>
                        </label>
                        <button type="submit" class="fsa-btn fsa-btn-primario"><i class="fas fa-paper-plane mr-2"></i>Enviar denúncia</button>
                    </form>
                </section>
            <?php endif; ?>
            </div>

            <aside class="space-y-4">
                <section class="bg-white rounded-2xl p-5">
                    <h3 class="font-bold text-slate-900 mb-3">Minhas denúncias</h3>
                    <?php if (!$minhas): ?>
                        <p class="text-sm text-slate-400">Você ainda não abriu nenhuma denúncia.</p>
                    <?php else: ?>
                        <ul class="space-y-2">
                            <?php foreach ($minhas as $d): [$t, $cls] = denunciaStatusInfo((string) $d['status']); ?>
                                <li>
                                    <a href="denuncias.php?id=<?= (int) $d['id'] ?>" data-parcial class="block p-3 rounded-xl border <?= $abrir && (int) $abrir['id'] === (int) $d['id'] ? 'border-[#4A9FCA] bg-[#f4f9fc]' : 'border-slate-100 hover:bg-slate-50' ?>">
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="text-sm font-semibold text-slate-800 truncate"><?= egh($d['assunto']) ?></p>
                                            <?php if (!empty($d['naoLidoAutor'])): ?><span class="w-2.5 h-2.5 rounded-full bg-red-500 flex-shrink-0" title="Resposta nova"></span><?php endif; ?>
                                        </div>
                                        <p class="text-xs text-slate-400 mt-0.5"><?= egh($d['protocolo']) ?> · <span class="px-1.5 py-0.5 rounded-full border <?= $cls ?>"><?= $t ?></span></p>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
                <section class="p-5 rounded-2xl bg-[#eef7fc] border border-[#d6ebf7] text-sm text-slate-600">
                    <p class="font-bold text-slate-800 mb-2"><i class="fas fa-lock text-[#4A9FCA] mr-1"></i>Sigilo</p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Só a equipe de denúncias vê o seu relato e os anexos.</li>
                        <li>A pessoa denunciada não é avisada por aqui.</li>
                        <li>Em caso de risco imediato, procure também a segurança da FSA ou a polícia (190).</li>
                    </ul>
                </section>
            </aside>
        </div>
        <?= denunciaJsCampoMensagem() ?>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
