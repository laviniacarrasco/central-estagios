<?php
/**
 * Painel de MENTORIA para o admin de egressos.
 * Abas: Mentores (aprovar, recusar, suspender) | Pedidos (acompanhamento)
 * O admin ve so o assunto dos pedidos, nunca a mensagem do aluno.
 */
require_once 'includes/config.php';
require_once 'includes/mentoria.php';
checkAuth();

if (!isAdminEgressos()) {
    header('Location: admin-dashboard.php');
    exit;
}

$pageTitle = 'Admin Mentoria';

$aba = (($_GET['tab'] ?? '') === 'pedidos') ? 'pedidos' : 'mentores';

$mensagens = [
    'aprovado'  => 'Mentor aprovado. Ele já aparece para os alunos e foi avisado por e-mail.',
    'recusado'  => 'Cadastro recusado. O egresso foi avisado e pode corrigir e enviar de novo.',
    'suspenso'  => 'Mentor suspenso. Os pedidos que aguardavam resposta foram cancelados.',
    'reativado' => 'Mentor reativado.',
];
$erros = [
    'egresso' => 'Egresso não encontrado.',
];

function mentoriaAdminVoltar(string $tab, string $chave, bool $erro = false): void {
    header('Location: admin-mentoria.php?tab=' . $tab . '&' . ($erro ? 'err=' : 'msg=') . $chave);
    exit;
}

/* =========================================================================
 * ACOES (POST)
 * ========================================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao   = (string) ($_POST['acao'] ?? '');
    $uid    = (int) ($_POST['user_id'] ?? 0);
    $motivo = mb_substr(trim((string) ($_POST['motivo'] ?? '')), 0, 300);

    $alvo = egressoCarregarUsuario($uid);
    if (!$alvo || ($alvo['tipo'] ?? '') !== 'egresso') mentoriaAdminVoltar('mentores', 'egresso', true);

    if ($acao === 'aprovar') {
        egressoAtualizarUsuario($uid, ['mentoriaStatus' => 'aprovado', 'mentoriaMotivo' => '']);
        mentoriaEmailMentorStatus($alvo, 'aprovado');
        mentoriaAdminVoltar('mentores', 'aprovado');
    }
    if ($acao === 'recusar') {
        egressoAtualizarUsuario($uid, ['mentoriaStatus' => 'recusado', 'mentoriaMotivo' => $motivo]);
        mentoriaEmailMentorStatus($alvo, 'recusado', $motivo);
        mentoriaAdminVoltar('mentores', 'recusado');
    }
    if ($acao === 'suspender') {
        egressoAtualizarUsuario($uid, ['mentoriaStatus' => 'suspenso', 'mentoriaMotivo' => $motivo]);
        mentoriaCancelarPendentesDoMentor($uid, $motivo);
        mentoriaEmailMentorStatus($alvo, 'suspenso', $motivo);
        mentoriaAdminVoltar('mentores', 'suspenso');
    }
    if ($acao === 'reativar') {
        egressoAtualizarUsuario($uid, ['mentoriaStatus' => 'aprovado', 'mentoriaMotivo' => '']);
        mentoriaAdminVoltar('mentores', 'reativado');
    }

    mentoriaAdminVoltar('mentores', 'egresso', true);
}

/* =========================================================================
 * DADOS DA TELA
 * ========================================================================= */
$todosPedidos = mentoriaPedidos();
$contagem = ['pendente' => 0, 'aceito' => 0, 'recusado' => 0, 'cancelado' => 0, 'concluido' => 0];
foreach ($todosPedidos as $p) {
    $s = $p['status'] ?? '';
    if (isset($contagem[$s])) $contagem[$s]++;
}

$mentoresCad = [];
foreach (loadData('usuarios') as $u) {
    if (($u['tipo'] ?? '') !== 'egresso') continue;
    if (($u['mentoriaStatus'] ?? '') === '') continue;
    $u['_aceitos'] = mentoriaAceitosDoMentor((int) ($u['id'] ?? 0), $todosPedidos);
    $mentoresCad[] = $u;
}
$ordem = ['pendente' => 0, 'aprovado' => 1, 'suspenso' => 2, 'recusado' => 3];
usort($mentoresCad, function ($a, $b) use ($ordem) {
    $oa = $ordem[$a['mentoriaStatus'] ?? ''] ?? 9;
    $ob = $ordem[$b['mentoriaStatus'] ?? ''] ?? 9;
    if ($oa !== $ob) return $oa <=> $ob;
    return strcmp((string) ($b['mentoriaSolicitadoEm'] ?? ''), (string) ($a['mentoriaSolicitadoEm'] ?? ''));
});

$nPendentes = 0;
$nAtivos = 0;
foreach ($mentoresCad as $u) {
    if (($u['mentoriaStatus'] ?? '') === 'pendente') $nPendentes++;
    if (($u['mentoriaStatus'] ?? '') === 'aprovado') $nAtivos++;
}

$statusFiltro = (string) ($_GET['status'] ?? '');
$pedidosLista = array_values(array_filter($todosPedidos, function ($p) use ($statusFiltro) {
    return $statusFiltro === '' || ($p['status'] ?? '') === $statusFiltro;
}));
usort($pedidosLista, function ($a, $b) {
    return ($b['criadoTs'] ?? 0) <=> ($a['criadoTs'] ?? 0);
});

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-7xl mx-auto">

        <div class="mb-8 animate-fade-in">
            <h2 class="text-3xl font-bold text-slate-900 mb-2">Mentoria</h2>
            <p class="text-slate-500 font-medium">Aprove os egressos que querem ser mentores e acompanhe os pedidos dos alunos.</p>
        </div>

        <?php if (isset($_GET['msg']) && isset($mensagens[$_GET['msg']])): ?>
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm">
                <i class="fas fa-check-circle mr-1"></i> <?= egh($mensagens[$_GET['msg']]) ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['err']) && isset($erros[$_GET['err']])): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
                <i class="fas fa-exclamation-circle mr-1"></i> <?= egh($erros[$_GET['err']]) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            <div class="p-5 bg-[#58A1D4] text-white rounded-2xl">
                <p class="text-white/90 text-xs font-medium mb-1">Mentores ativos</p>
                <p class="text-3xl font-bold"><?= $nAtivos ?></p>
            </div>
            <div class="p-5 bg-[#E35444] text-white rounded-2xl">
                <p class="text-white/90 text-xs font-medium mb-1">Aguardando aprovação</p>
                <p class="text-3xl font-bold"><?= $nPendentes ?></p>
            </div>
            <div class="p-5 bg-[#F59E0B] text-white rounded-2xl">
                <p class="text-white/90 text-xs font-medium mb-1">Pedidos sem resposta</p>
                <p class="text-3xl font-bold"><?= $contagem['pendente'] ?></p>
            </div>
            <div class="p-5 bg-[#27AE60] text-white rounded-2xl">
                <p class="text-white/90 text-xs font-medium mb-1">Mentorias em andamento</p>
                <p class="text-3xl font-bold"><?= $contagem['aceito'] ?></p>
            </div>
            <div class="p-5 bg-[#7C3AED] text-white rounded-2xl">
                <p class="text-white/90 text-xs font-medium mb-1">Concluídas</p>
                <p class="text-3xl font-bold"><?= $contagem['concluido'] ?></p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 mb-8">
            <a href="admin-mentoria.php?tab=mentores" class="px-5 py-2.5 rounded-xl text-sm font-semibold <?= $aba === 'mentores' ? 'bg-[#4A9FCA] text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
                <i class="fas fa-user-check mr-1"></i> Mentores
                <?php if ($nPendentes > 0): ?>
                    <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-bold <?= $aba === 'mentores' ? 'bg-white text-[#4A9FCA]' : 'bg-red-500 text-white' ?>"><?= $nPendentes ?></span>
                <?php endif; ?>
            </a>
            <a href="admin-mentoria.php?tab=pedidos" class="px-5 py-2.5 rounded-xl text-sm font-semibold <?= $aba === 'pedidos' ? 'bg-[#4A9FCA] text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
                <i class="fas fa-clipboard-list mr-1"></i> Pedidos (<?= count($todosPedidos) ?>)
            </a>
        </div>

<?php if ($aba === 'mentores'): ?>

        <?php if (empty($mentoresCad)): ?>
            <div class="p-16 text-center border-2 border-dashed border-gray-200 rounded-3xl bg-white">
                <i class="fas fa-user-check text-6xl text-gray-200 mb-4"></i>
                <h3 class="text-xl font-bold text-slate-900 mb-2">Nenhum cadastro ainda</h3>
                <p class="text-slate-500">Quando um egresso pedir para ser mentor, ele aparece aqui para você aprovar.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($mentoresCad as $u):
                    $st = (string) ($u['mentoriaStatus'] ?? '');
                    [$sTxt, $sCls, $sIco] = mentoriaMentorStatusInfo($st);
                    $areas = mentoriaAreasLista($u['mentoriaAreas'] ?? '');
                    $cargo = implode(' · ', array_filter([trim((string) ($u['cargoAtual'] ?? '')), trim((string) ($u['empresaAtual'] ?? ''))]));
                    $lk    = !empty($u['linkedin']) ? egressoNormalizarLink((string) $u['linkedin']) : '';
                    $uidAtual = (int) ($u['id'] ?? 0);
                ?>
                    <div class="p-6 bg-white border <?= $st === 'pendente' ? 'border-2 border-amber-200' : 'border-gray-100' ?> rounded-2xl shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                            <div>
                                <h3 class="text-lg font-bold text-slate-900"><?= egh($u['nome'] ?? '') ?></h3>
                                <p class="text-sm text-gray-500">
                                    <?= egh($u['curso'] ?? '') ?><?= !empty($u['anoConclusao']) ? ' &bull; turma de ' . egh($u['anoConclusao']) : '' ?>
                                    <?php if ($cargo !== ''): ?>&bull; <?= egh($cargo) ?><?php endif; ?>
                                </p>
                                <p class="text-xs text-gray-400 mt-1">
                                    <?= egh($u['email'] ?? '') ?>
                                    <?php if ($lk !== ''): ?> &bull; <a href="<?= egh($lk) ?>" target="_blank" rel="noopener" class="text-[#4A9FCA] hover:underline">LinkedIn</a><?php endif; ?>
                                    <?php if (!empty($u['mentoriaSolicitadoEm'])): ?> &bull; pediu em <?= egh($u['mentoriaSolicitadoEm']) ?><?php endif; ?>
                                </p>
                            </div>
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold border <?= egh($sCls) ?>">
                                <i class="fas <?= egh($sIco) ?>"></i> <?= egh($sTxt) ?>
                            </span>
                        </div>

                        <?php if (!empty($areas)): ?>
                            <div class="flex flex-wrap gap-2 mb-3">
                                <?php foreach ($areas as $a): ?>
                                    <span class="px-3 py-1 bg-[#eaf4fa] text-[#2B7FA6] rounded-full text-xs font-semibold"><?= egh($a) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($u['bio'])): ?>
                            <p class="text-sm text-gray-600 bg-gray-50 p-3 rounded-xl mb-3 whitespace-pre-line"><?= egh($u['bio']) ?></p>
                        <?php endif; ?>

                        <?php if ($st === 'aprovado'): ?>
                            <p class="text-xs text-gray-500 mb-3">
                                Vagas: <strong><?= (int) $u['_aceitos'] ?> de <?= mentoriaMax($u) ?></strong> ocupadas
                                &bull; <?= !empty($u['aceitaMentoria']) ? 'visível para os alunos' : 'pausado pelo egresso' ?>
                            </p>
                        <?php endif; ?>
                        <?php if (($st === 'recusado' || $st === 'suspenso') && !empty($u['mentoriaMotivo'])): ?>
                            <p class="text-xs text-gray-500 mb-3"><strong>Motivo informado:</strong> <?= egh($u['mentoriaMotivo']) ?></p>
                        <?php endif; ?>

                        <div class="flex flex-wrap items-center gap-3">
                            <?php if ($st === 'pendente' || $st === 'recusado'): ?>
                                <form method="POST" action="admin-mentoria.php"
                                      data-confirm="Ele passa a aparecer para os alunos e recebe um e-mail avisando."
                                      data-confirm-titulo="Aprovar este mentor?" data-confirm-botao="Aprovar"
                                      data-confirm-tipo="aviso" data-confirm-icone="fa-check">
                                    <input type="hidden" name="acao" value="aprovar">
                                    <input type="hidden" name="user_id" value="<?= $uidAtual ?>">
                                    <button type="submit" class="px-5 py-2.5 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl text-sm font-semibold transition-all">
                                        <i class="fas fa-check mr-1"></i> Aprovar
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($st === 'pendente'): ?>
                                <form method="POST" action="admin-mentoria.php" class="flex flex-wrap items-center gap-2"
                                      data-confirm="O egresso recebe um e-mail e pode corrigir o cadastro e enviar de novo."
                                      data-confirm-titulo="Recusar este cadastro?" data-confirm-botao="Recusar"
                                      data-confirm-tipo="perigo" data-confirm-icone="fa-times">
                                    <input type="hidden" name="acao" value="recusar">
                                    <input type="hidden" name="user_id" value="<?= $uidAtual ?>">
                                    <input type="text" name="motivo" maxlength="300" placeholder="Motivo (opcional)"
                                           class="h-10 px-3 border border-gray-200 rounded-lg text-sm w-56">
                                    <button type="submit" class="px-4 py-2.5 border border-red-200 text-red-600 rounded-xl text-sm font-semibold hover:bg-red-50 transition-all">
                                        <i class="fas fa-times mr-1"></i> Recusar
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($st === 'aprovado'): ?>
                                <form method="POST" action="admin-mentoria.php" class="flex flex-wrap items-center gap-2"
                                      data-confirm="Ele deixa de aparecer para os alunos e os pedidos que aguardavam resposta são cancelados."
                                      data-confirm-titulo="Suspender este mentor?" data-confirm-botao="Suspender"
                                      data-confirm-tipo="perigo" data-confirm-icone="fa-pause">
                                    <input type="hidden" name="acao" value="suspender">
                                    <input type="hidden" name="user_id" value="<?= $uidAtual ?>">
                                    <input type="text" name="motivo" maxlength="300" placeholder="Motivo (opcional)"
                                           class="h-10 px-3 border border-gray-200 rounded-lg text-sm w-56">
                                    <button type="submit" class="px-4 py-2.5 border border-red-200 text-red-600 rounded-xl text-sm font-semibold hover:bg-red-50 transition-all">
                                        <i class="fas fa-pause mr-1"></i> Suspender
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($st === 'suspenso'): ?>
                                <form method="POST" action="admin-mentoria.php"
                                      data-confirm="Ele volta a ficar disponível para os alunos."
                                      data-confirm-titulo="Reativar este mentor?" data-confirm-botao="Reativar"
                                      data-confirm-tipo="aviso" data-confirm-icone="fa-play">
                                    <input type="hidden" name="acao" value="reativar">
                                    <input type="hidden" name="user_id" value="<?= $uidAtual ?>">
                                    <button type="submit" class="px-5 py-2.5 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl text-sm font-semibold transition-all">
                                        <i class="fas fa-play mr-1"></i> Reativar
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

<?php else: ?>

        <form method="GET" action="admin-mentoria.php" class="mb-6 flex flex-wrap items-center gap-3">
            <input type="hidden" name="tab" value="pedidos">
            <select name="status" class="h-11 px-4 bg-white border border-gray-200 rounded-xl" onchange="this.form.submit()">
                <option value="">Todos os status</option>
                <?php foreach (['pendente', 'aceito', 'concluido', 'recusado', 'cancelado'] as $opt):
                    [$oTxt] = mentoriaPedidoStatusInfo($opt);
                ?>
                    <option value="<?= egh($opt) ?>" <?= $statusFiltro === $opt ? 'selected' : '' ?>><?= egh($oTxt) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="text-sm text-gray-400"><?= count($pedidosLista) ?> pedido(s)</span>
        </form>

        <?php if (empty($pedidosLista)): ?>
            <div class="p-16 text-center border-2 border-dashed border-gray-200 rounded-3xl bg-white">
                <i class="fas fa-clipboard-list text-6xl text-gray-200 mb-4"></i>
                <h3 class="text-xl font-bold text-slate-900 mb-2">Nenhum pedido</h3>
                <p class="text-slate-500">Os pedidos dos alunos aparecem aqui.</p>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="p-3">Data</th>
                            <th class="p-3">Aluno</th>
                            <th class="p-3">Mentor</th>
                            <th class="p-3">Assunto</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidosLista as $p):
                            [$sTxt, $sCls, $sIco] = mentoriaPedidoStatusInfo($p['status'] ?? '');
                        ?>
                            <tr class="border-t border-gray-100">
                                <td class="p-3 text-gray-500 whitespace-nowrap"><?= egh($p['criadoEm'] ?? '') ?></td>
                                <td class="p-3">
                                    <p class="font-semibold text-slate-800"><?= egh($p['alunoNome'] ?? '') ?></p>
                                    <p class="text-xs text-gray-400"><?= egh($p['alunoCurso'] ?? '') ?></p>
                                </td>
                                <td class="p-3 text-slate-700"><?= egh($p['mentorNome'] ?? '') ?></td>
                                <td class="p-3 text-gray-600"><?= egh($p['assunto'] ?? '') ?></td>
                                <td class="p-3">
                                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold border <?= egh($sCls) ?>">
                                        <i class="fas <?= egh($sIco) ?>"></i> <?= egh($sTxt) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

<?php endif; ?>

    </div>
</main>

<?php include 'includes/footer.php'; ?>
