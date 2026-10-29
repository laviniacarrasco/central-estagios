<?php
/**
 * Painel de MENTORIA para o admin de egressos.
 * Abas: Mentores (aprovar, recusar, suspender) | Pedidos (acompanhamento)
 * Recusar e suspender exigem justificativa (vai no e-mail para o egresso).
 * Em cada mentor: "Conversas e log" (ver e baixar em TXT, por conversa, por mentor ou por aluno).
 * Aba Denúncias: denúncias de alunos e mentores (bolinha vermelha no menu e e-mail para a coordenação).
 * Na aba Pedidos, a coordenação abre e baixa as conversas (admin-mentoria-conversa.php);
 * cada consulta e download fica registrado no log de ações dos funcionários.
 */
require_once 'includes/config.php';
require_once 'includes/mentoria.php';
require_once 'includes/melhorias.php';
checkAuth();

if (!isAdminEgressos()) {
    header('Location: admin-dashboard.php');
    exit;
}

$pageTitle = 'Admin Mentoria';

$aba = in_array($_GET['tab'] ?? '', ['pedidos', 'denuncias'], true) ? $_GET['tab'] : 'mentores';

$mensagens = [
    'aprovado'  => 'Mentor aprovado. Ele já aparece para os alunos e foi avisado por e-mail.',
    'recusado'  => 'Cadastro recusado. O egresso foi avisado e pode corrigir e enviar de novo.',
    'suspenso'  => 'Mentor suspenso. Os pedidos que aguardavam resposta foram cancelados.',
    'reativado' => 'Mentor reativado.',
    'den_analise'   => 'Denúncia marcada como em análise.',
    'den_concluida' => 'Denúncia concluída. Quem denunciou foi avisado por e-mail com a sua resposta.',
];
$erros = [
    'egresso' => 'Egresso não encontrado.',
    'motivo'  => 'A justificativa é obrigatória (pelo menos 10 caracteres). Nada foi alterado.',
    'denuncia' => 'Denúncia não encontrada.',
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

    /* ---- denúncias: em análise, resolver ou arquivar (resolver e arquivar exigem resposta) ---- */
    if (in_array($acao, ['denuncia_analisar', 'denuncia_resolver', 'denuncia_arquivar'], true)) {
        $did   = (int) ($_POST['denuncia_id'] ?? 0);
        $novo  = ['denuncia_analisar' => 'em_analise', 'denuncia_resolver' => 'resolvida', 'denuncia_arquivar' => 'arquivada'][$acao];
        $texto = '';
        if ($novo !== 'em_analise') {
            $texto = (string) justificativaPost('resposta', 1000);
            if ($texto === '') mentoriaAdminVoltar('denuncias', 'motivo', true);
        }
        $quem = (string) ($_SESSION['user_name'] ?? '');
        $den = dadosOperar('mentoria_denuncias', function (array &$lista) use ($did, $novo, $texto, $quem) {
            foreach ($lista as $i => $d) {
                if ((int) ($d['id'] ?? 0) !== $did) continue;
                $lista[$i]['status'] = $novo;
                if ($texto !== '') $lista[$i]['resposta'] = $texto;
                $lista[$i]['atualizadoPor'] = $quem;
                $lista[$i]['atualizadoTs']  = time();
                $hist = (array) ($d['historico'] ?? []);
                $hist[] = ['ts' => time(), 'por' => $quem, 'status' => $novo, 'texto' => $texto];
                $lista[$i]['historico'] = $hist;
                return $lista[$i];
            }
            return null;
        });
        if (!is_array($den)) mentoriaAdminVoltar('denuncias', 'denuncia', true);
        [$stTxt] = mentoriaDenunciaStatusInfo($novo);
        mhLogAdmin('Denúncia de mentoria: ' . $stTxt, '#' . $did . ' contra ' . ($den['denunciadoNome'] ?? '') . ($texto !== '' ? ' | ' . $texto : ''));
        if ($novo !== 'em_analise') mentoriaEmailDenunciaConcluida($den);
        mentoriaAdminVoltar('denuncias', $novo === 'em_analise' ? 'den_analise' : 'den_concluida');
    }

    $alvo = egressoCarregarUsuario($uid);
    if (!$alvo || ($alvo['tipo'] ?? '') !== 'egresso') mentoriaAdminVoltar('mentores', 'egresso', true);

    // Recusar e suspender: justificativa obrigatória
    if (in_array($acao, ['recusar', 'suspender'], true)) {
        $motivo = justificativaPost('motivo', 300);
        if ($motivo === null) mentoriaAdminVoltar('mentores', 'motivo', true);
        mhLogAdmin($acao === 'recusar' ? 'Mentor recusado' : 'Mentor suspenso', (string) ($alvo['nome'] ?? '') . ' | Motivo: ' . $motivo);
    }

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

$pedidosPorMentor = [];
foreach ($todosPedidos as $p) $pedidosPorMentor[(int) ($p['mentorId'] ?? 0)][] = $p;

$todasDenuncias = mentoriaDenuncias();
usort($todasDenuncias, fn($a, $b) => ($b['criadoTs'] ?? 0) <=> ($a['criadoTs'] ?? 0));
$nDenNovas = 0; $denPorPessoa = [];
foreach ($todasDenuncias as $d) {
    if (($d['status'] ?? 'aberta') === 'aberta') $nDenNovas++;
    $denPorPessoa[(int) ($d['denunciadoId'] ?? 0)] = ($denPorPessoa[(int) ($d['denunciadoId'] ?? 0)] ?? 0) + 1;
}
$denStatusFiltro = (string) ($_GET['dstatus'] ?? 'ativas');
$denPessoa       = (int) ($_GET['pessoa'] ?? 0);

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
            <a href="admin-mentoria.php?tab=denuncias" class="px-5 py-2.5 rounded-xl text-sm font-semibold <?= $aba === 'denuncias' ? 'bg-red-500 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
                <i class="fas fa-flag mr-1"></i> Denúncias
                <?php if ($nDenNovas > 0): ?>
                    <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-bold <?= $aba === 'denuncias' ? 'bg-white text-red-500' : 'bg-red-500 text-white' ?>"><?= $nDenNovas ?></span>
                <?php endif; ?>
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
                            <div class="flex flex-wrap items-center gap-2">
                                <?php if (!empty($denPorPessoa[$uidAtual])): ?>
                                    <a href="admin-mentoria.php?tab=denuncias&amp;dstatus=todas&amp;pessoa=<?= $uidAtual ?>"
                                       class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold border bg-red-50 text-red-700 border-red-200">
                                        <i class="fas fa-flag"></i> <?= (int) $denPorPessoa[$uidAtual] ?> denúncia(s)
                                    </a>
                                <?php endif; ?>
                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold border <?= egh($sCls) ?>">
                                    <i class="fas <?= egh($sIco) ?>"></i> <?= egh($sTxt) ?>
                                </span>
                            </div>
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

                        <?php $convs = $pedidosPorMentor[$uidAtual] ?? []; ?>
                        <details class="mb-4 border border-gray-100 rounded-xl bg-gray-50/50">
                            <summary class="px-4 py-3 cursor-pointer text-sm font-semibold text-[#2B7FA6] flex flex-wrap items-center gap-2">
                                <i class="fas fa-comments"></i> Conversas e log (<?= count($convs) ?>)
                                <?php if ($convs): ?>
                                    <a href="admin-mentoria-conversa.php?mentor=<?= $uidAtual ?>&amp;baixar=txt" onclick="event.stopPropagation()"
                                       class="ml-auto px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-xs text-gray-600 hover:bg-gray-50 font-normal">
                                        <i class="fas fa-download mr-1"></i>Baixar todas deste mentor (TXT)
                                    </a>
                                <?php endif; ?>
                            </summary>
                            <?php if (!$convs): ?>
                                <p class="px-4 pb-4 text-xs text-gray-400">Este mentor ainda não tem conversas.</p>
                            <?php else: ?>
                                <div class="px-4 pb-4 overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead><tr class="text-left text-xs uppercase text-gray-400">
                                            <th class="py-2">Aluno</th><th class="py-2">Assunto</th><th class="py-2">Status</th><th class="py-2">Mensagens</th><th class="py-2 text-right">Conversa</th>
                                        </tr></thead>
                                        <tbody>
                                            <?php foreach ($convs as $cv):
                                                [$cTxt, $cCls] = mentoriaPedidoStatusInfo($cv['status'] ?? '');
                                                $cid = (int) ($cv['id'] ?? 0);
                                                $aid = (int) ($cv['alunoId'] ?? 0);
                                            ?>
                                                <tr class="border-t border-gray-100">
                                                    <td class="py-2">
                                                        <p class="font-medium text-slate-800"><?= egh($cv['alunoNome'] ?? '') ?>
                                                            <?php if (!empty($denPorPessoa[$aid])): ?><i class="fas fa-flag text-red-500 text-xs ml-1" title="Aluno com denúncia"></i><?php endif; ?></p>
                                                        <p class="text-xs text-gray-400"><?= egh($cv['criadoEm'] ?? '') ?></p>
                                                    </td>
                                                    <td class="py-2 text-gray-600"><?= egh($cv['assunto'] ?? '') ?></td>
                                                    <td class="py-2"><span class="px-2 py-0.5 rounded-full text-xs border <?= egh($cCls) ?>"><?= egh($cTxt) ?></span></td>
                                                    <td class="py-2 text-gray-500"><?= count((array) ($cv['mensagens'] ?? [])) ?></td>
                                                    <td class="py-2 text-right whitespace-nowrap">
                                                        <button type="button" onclick="convAbrir(<?= $cid ?>)" class="px-3 py-1.5 border border-gray-200 bg-white rounded-lg text-xs text-[#2B7FA6] hover:bg-gray-50"><i class="fas fa-eye mr-1"></i>Ver log</button>
                                                        <a href="admin-mentoria-conversa.php?pedido=<?= $cid ?>&amp;baixar=txt" class="px-3 py-1.5 border border-gray-200 bg-white rounded-lg text-xs text-gray-600 hover:bg-gray-50"><i class="fas fa-download mr-1"></i>TXT</a>
                                                        <a href="admin-mentoria-conversa.php?aluno=<?= $aid ?>&amp;baixar=txt" class="px-3 py-1.5 border border-gray-200 bg-white rounded-lg text-xs text-gray-600 hover:bg-gray-50" title="Todas as conversas deste aluno"><i class="fas fa-user mr-1"></i>Aluno (TXT)</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </details>

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
                                      data-confirm="O egresso recebe um e-mail com a justificativa e pode corrigir o cadastro e enviar de novo."
                                      data-justificativa="motivo" data-justificativa-max="300"
                                      data-justificativa-dica="Ex.: faltou descrever as áreas em que você pode ajudar."
                                      data-confirm-titulo="Recusar este cadastro?" data-confirm-botao="Recusar"
                                      data-confirm-tipo="perigo" data-confirm-icone="fa-times">
                                    <input type="hidden" name="acao" value="recusar">
                                    <input type="hidden" name="user_id" value="<?= $uidAtual ?>">
                                    <button type="submit" class="px-4 py-2.5 border border-red-200 text-red-600 rounded-xl text-sm font-semibold hover:bg-red-50 transition-all">
                                        <i class="fas fa-times mr-1"></i> Recusar
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($st === 'aprovado'): ?>
                                <form method="POST" action="admin-mentoria.php" class="flex flex-wrap items-center gap-2"
                                      data-confirm="Ele deixa de aparecer para os alunos e os pedidos que aguardavam resposta são cancelados. A justificativa vai no e-mail para ele."
                                      data-justificativa="motivo" data-justificativa-max="300"
                                      data-justificativa-dica="Ex.: denúncia de um aluno em análise pela coordenação."
                                      data-confirm-titulo="Suspender este mentor?" data-confirm-botao="Suspender"
                                      data-confirm-tipo="perigo" data-confirm-icone="fa-pause">
                                    <input type="hidden" name="acao" value="suspender">
                                    <input type="hidden" name="user_id" value="<?= $uidAtual ?>">
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

<?php elseif ($aba === 'pedidos'): ?>

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
            <a href="admin-mentoria-conversa.php?todas=1&amp;status=<?= urlencode($statusFiltro) ?>&amp;baixar=csv"
               class="ml-auto h-11 px-4 inline-flex items-center gap-2 bg-white border border-gray-200 rounded-xl text-sm text-gray-600 hover:bg-gray-50">
                <i class="fas fa-file-csv text-[#27AE60]"></i> Baixar todas as conversas (CSV)
            </a>
        </form>
        <p class="text-xs text-gray-400 -mt-3 mb-6"><i class="fas fa-shield-alt mr-1"></i>
            As conversas são dados pessoais. Abra ou baixe só quando for necessário (denúncia, segurança, acompanhamento).
            Cada consulta e download fica registrado no log de ações dos funcionários, com o seu nome.</p>

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
                            <th class="p-3">Conversa</th>
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
                                <td class="p-3 whitespace-nowrap">
                                    <?php $pidConv = (int) ($p['id'] ?? 0); $nMsg = count((array) ($p['mensagens'] ?? [])); ?>
                                    <span class="text-xs text-gray-400 mr-2"><?= $nMsg ?> msg</span>
                                    <button type="button" onclick="convAbrir(<?= $pidConv ?>)"
                                            class="px-3 py-1.5 border border-gray-200 rounded-lg text-xs text-[#2B7FA6] hover:bg-gray-50"><i class="fas fa-eye mr-1"></i>Ver</button>
                                    <a href="admin-mentoria-conversa.php?pedido=<?= $pidConv ?>&amp;baixar=txt"
                                       class="px-3 py-1.5 border border-gray-200 rounded-lg text-xs text-gray-600 hover:bg-gray-50" title="Baixar em texto"><i class="fas fa-download mr-1"></i>TXT</a>
                                    <a href="admin-mentoria-conversa.php?pedido=<?= $pidConv ?>&amp;baixar=csv"
                                       class="px-3 py-1.5 border border-gray-200 rounded-lg text-xs text-gray-600 hover:bg-gray-50" title="Baixar em planilha"><i class="fas fa-file-csv mr-1"></i>CSV</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

<?php else: /* ===================== DENÚNCIAS ===================== */
    $catsDen = mentoriaDenunciaCategorias();
    $denLista = array_values(array_filter($todasDenuncias, function ($d) use ($denStatusFiltro, $denPessoa) {
        $st = $d['status'] ?? 'aberta';
        if ($denStatusFiltro === 'ativas' && !in_array($st, ['aberta', 'em_analise'], true)) return false;
        if (!in_array($denStatusFiltro, ['ativas', 'todas'], true) && $st !== $denStatusFiltro) return false;
        if ($denPessoa && (int) ($d['denunciadoId'] ?? 0) !== $denPessoa && (int) ($d['denuncianteId'] ?? 0) !== $denPessoa) return false;
        return true;
    }));
?>

        <form method="GET" action="admin-mentoria.php" class="mb-4 flex flex-wrap items-center gap-3">
            <input type="hidden" name="tab" value="denuncias">
            <?php if ($denPessoa): ?><input type="hidden" name="pessoa" value="<?= $denPessoa ?>"><?php endif; ?>
            <select name="dstatus" class="h-11 px-4 bg-white border border-gray-200 rounded-xl" onchange="this.form.submit()">
                <?php foreach (['ativas' => 'Novas e em análise', 'aberta' => 'Só as novas', 'em_analise' => 'Em análise', 'resolvida' => 'Resolvidas', 'arquivada' => 'Arquivadas', 'todas' => 'Todas'] as $k => $rot): ?>
                    <option value="<?= egh($k) ?>" <?= $denStatusFiltro === $k ? 'selected' : '' ?>><?= egh($rot) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="text-sm text-gray-400"><?= count($denLista) ?> denúncia(s)</span>
            <?php if ($denPessoa): ?>
                <a href="admin-mentoria.php?tab=denuncias" class="text-sm text-red-500">Tirar filtro da pessoa</a>
            <?php endif; ?>
        </form>
        <p class="text-xs text-gray-400 mb-6"><i class="fas fa-user-secret mr-1"></i>
            A pessoa denunciada não é avisada e não sabe quem denunciou. Ao <strong>resolver</strong> ou <strong>arquivar</strong>,
            a sua resposta (obrigatória) vai por e-mail para quem denunciou. Toda ação fica no log.</p>

        <?php if (empty($denLista)): ?>
            <div class="p-16 text-center border-2 border-dashed border-gray-200 rounded-3xl bg-white">
                <i class="fas fa-shield-alt text-6xl text-gray-200 mb-4"></i>
                <h3 class="text-xl font-bold text-slate-900 mb-2">Nenhuma denúncia aqui</h3>
                <p class="text-slate-500">Quando um aluno ou mentor denunciar algo, aparece aqui e você recebe um e-mail.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($denLista as $d):
                    [$dTxt, $dCls, $dIco] = mentoriaDenunciaStatusInfo($d['status'] ?? '');
                    $did = (int) ($d['id'] ?? 0);
                    $pid = (int) ($d['pedidoId'] ?? 0);
                    $st  = (string) ($d['status'] ?? 'aberta');
                ?>
                    <div class="p-6 bg-white rounded-2xl shadow-sm <?= $st === 'aberta' ? 'border-2 border-red-200' : 'border border-gray-100' ?>">
                        <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                            <div>
                                <h3 class="text-lg font-bold text-slate-900">#<?= $did ?> &middot; <?= egh($catsDen[$d['categoria'] ?? 'outro'] ?? 'Outro motivo') ?></h3>
                                <p class="text-sm text-gray-500">
                                    <strong><?= egh($d['denuncianteNome'] ?? '') ?></strong> (<?= egh($d['denunciantePapel'] ?? '') ?>) denunciou
                                    <strong><?= egh($d['denunciadoNome'] ?? '') ?></strong> (<?= egh($d['denunciadoPapel'] ?? '') ?>)
                                </p>
                                <p class="text-xs text-gray-400 mt-1">Pedido #<?= $pid ?> &bull; <?= egh($d['assunto'] ?? '') ?> &bull; <?= egh($d['criadoEm'] ?? '') ?>
                                    <?= empty($d['mensagemId']) ? '&bull; sobre a conversa inteira' : '' ?></p>
                            </div>
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold border <?= egh($dCls) ?>"><i class="fas <?= egh($dIco) ?>"></i> <?= egh($dTxt) ?></span>
                        </div>

                        <?php if (!empty($d['mensagemTexto'])): ?>
                            <p class="text-xs font-bold text-gray-500 uppercase mb-1">Mensagem denunciada
                                <?= !empty($d['mensagemTs']) ? '(' . egh(date('d/m/Y H:i', (int) $d['mensagemTs'])) . ')' : '' ?></p>
                            <p class="text-sm text-gray-800 bg-red-50 border-l-4 border-red-400 p-3 rounded-r-xl mb-3 whitespace-pre-line"><?= egh($d['mensagemTexto']) ?></p>
                        <?php endif; ?>
                        <p class="text-xs font-bold text-gray-500 uppercase mb-1">Relato</p>
                        <p class="text-sm text-gray-700 bg-gray-50 p-3 rounded-xl mb-3 whitespace-pre-line"><?= egh($d['descricao'] ?? '') ?></p>

                        <?php if (!empty($d['historico'])): ?>
                            <div class="text-xs text-gray-500 mb-3 space-y-1">
                                <?php foreach ((array) $d['historico'] as $h):
                                    [$hTxt] = mentoriaDenunciaStatusInfo($h['status'] ?? ''); ?>
                                    <p><i class="fas fa-history mr-1"></i><?= egh(date('d/m/Y H:i', (int) ($h['ts'] ?? 0))) ?> &middot; <?= egh($h['por'] ?? '') ?>:
                                        <strong><?= egh($hTxt) ?></strong><?= !empty($h['texto']) ? ' &mdash; ' . egh($h['texto']) : '' ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" onclick="convAbrir(<?= $pid ?>)" class="px-4 py-2 border border-gray-200 rounded-xl text-sm text-[#2B7FA6] hover:bg-gray-50"><i class="fas fa-eye mr-1"></i>Ver log da conversa</button>
                            <a href="admin-mentoria-conversa.php?pedido=<?= $pid ?>&amp;baixar=txt" class="px-4 py-2 border border-gray-200 rounded-xl text-sm text-gray-600 hover:bg-gray-50"><i class="fas fa-download mr-1"></i>TXT</a>

                            <?php if (in_array($st, ['aberta', 'em_analise'], true)): ?>
                                <?php if ($st === 'aberta'): ?>
                                    <form method="POST" action="admin-mentoria.php" class="inline">
                                        <input type="hidden" name="acao" value="denuncia_analisar">
                                        <input type="hidden" name="denuncia_id" value="<?= $did ?>">
                                        <button type="submit" class="px-4 py-2 bg-amber-50 border border-amber-200 text-amber-700 rounded-xl text-sm font-semibold hover:bg-amber-100"><i class="fas fa-search mr-1"></i>Marcar em análise</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" action="admin-mentoria.php" class="inline"
                                      data-confirm="Quem denunciou recebe a sua resposta por e-mail."
                                      data-justificativa="resposta" data-justificativa-max="1000"
                                      data-justificativa-dica="Ex.: conversamos com o mentor e ele foi suspenso da mentoria."
                                      data-confirm-titulo="Resolver a denúncia?" data-confirm-botao="Resolver" data-confirm-tipo="aviso" data-confirm-icone="fa-check">
                                    <input type="hidden" name="acao" value="denuncia_resolver">
                                    <input type="hidden" name="denuncia_id" value="<?= $did ?>">
                                    <button type="submit" class="px-4 py-2 bg-[#27AE60] hover:bg-[#229954] text-white rounded-xl text-sm font-semibold"><i class="fas fa-check mr-1"></i>Resolver</button>
                                </form>
                                <form method="POST" action="admin-mentoria.php" class="inline"
                                      data-confirm="Use quando a denúncia não procede. Quem denunciou recebe a sua justificativa por e-mail."
                                      data-justificativa="resposta" data-justificativa-max="1000"
                                      data-justificativa-dica="Ex.: analisamos a conversa e não encontramos irregularidade."
                                      data-confirm-titulo="Arquivar a denúncia?" data-confirm-botao="Arquivar" data-confirm-tipo="perigo" data-confirm-icone="fa-archive">
                                    <input type="hidden" name="acao" value="denuncia_arquivar">
                                    <input type="hidden" name="denuncia_id" value="<?= $did ?>">
                                    <button type="submit" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-xl text-sm hover:bg-gray-50"><i class="fas fa-archive mr-1"></i>Arquivar</button>
                                </form>
                                <?php if (($d['denunciadoPapel'] ?? '') === 'Mentor'):
                                    $mAlvo = egressoCarregarUsuario((int) ($d['denunciadoId'] ?? 0));
                                    if ($mAlvo && ($mAlvo['mentoriaStatus'] ?? '') === 'aprovado'): ?>
                                    <form method="POST" action="admin-mentoria.php" class="inline"
                                          data-confirm="Ele deixa de aparecer para os alunos e os pedidos que aguardavam resposta são cancelados. A justificativa vai no e-mail para ele (sem dizer quem denunciou)."
                                          data-justificativa="motivo" data-justificativa-max="300"
                                          data-justificativa-dica="Ex.: conduta inadequada com aluno, em análise pela coordenação."
                                          data-confirm-titulo="Suspender este mentor?" data-confirm-botao="Suspender" data-confirm-tipo="perigo" data-confirm-icone="fa-pause">
                                        <input type="hidden" name="acao" value="suspender">
                                        <input type="hidden" name="user_id" value="<?= (int) $d['denunciadoId'] ?>">
                                        <button type="submit" class="px-4 py-2 border border-red-200 text-red-600 rounded-xl text-sm font-semibold hover:bg-red-50"><i class="fas fa-pause mr-1"></i>Suspender mentor</button>
                                    </form>
                                <?php endif; endif; ?>
                            <?php elseif (!empty($d['resposta'])): ?>
                                <p class="text-sm text-slate-700 bg-[#eaf4fa] p-3 rounded-xl w-full mt-2"><strong>Resposta enviada:</strong> <?= egh($d['resposta']) ?>
                                    <span class="text-xs text-gray-400">(<?= egh($d['atualizadoPor'] ?? '') ?>)</span></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

<?php endif; ?>

    </div>
</main>

<!-- Janela: ver a conversa (só leitura) -->
<div id="convModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-2xl flex flex-col overflow-hidden" style="max-height: 85vh;">
        <div class="flex items-start justify-between gap-3 px-6 py-4 border-b border-gray-100">
            <div class="min-w-0">
                <p id="convTitulo" class="font-bold text-slate-900"></p>
                <p id="convSub" class="text-xs text-gray-400"></p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <a id="convTxt" href="#" class="px-3 py-1.5 border border-gray-200 rounded-lg text-xs text-gray-600 hover:bg-gray-50"><i class="fas fa-download mr-1"></i>TXT</a>
                <a id="convCsv" href="#" class="px-3 py-1.5 border border-gray-200 rounded-lg text-xs text-gray-600 hover:bg-gray-50"><i class="fas fa-file-csv mr-1"></i>CSV</a>
                <button type="button" onclick="convFechar()" class="w-9 h-9 rounded-full hover:bg-gray-100 text-gray-500"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div id="convLista" class="flex-1 overflow-y-auto px-5 py-4 space-y-3 bg-gray-50"></div>
        <p class="px-6 py-3 text-xs text-gray-400 border-t border-gray-100"><i class="fas fa-eye mr-1"></i>Só leitura. Esta consulta foi registrada no log.</p>
    </div>
</div>

<script>
function convFechar() { const m = document.getElementById('convModal'); m.classList.add('hidden'); m.classList.remove('flex'); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') convFechar(); });
document.getElementById('convModal').addEventListener('click', e => { if (e.target.id === 'convModal') convFechar(); });

function convAbrir(id) {
    const lista = document.getElementById('convLista');
    lista.textContent = 'Carregando...';
    document.getElementById('convTxt').href = 'admin-mentoria-conversa.php?pedido=' + id + '&baixar=txt';
    document.getElementById('convCsv').href = 'admin-mentoria-conversa.php?pedido=' + id + '&baixar=csv';
    const m = document.getElementById('convModal'); m.classList.remove('hidden'); m.classList.add('flex');

    fetch('admin-mentoria-conversa.php?pedido=' + id, { headers: { 'X-Requested-With': 'fetch' }, cache: 'no-store' })
        .then(r => r.json())
        .then(d => {
            lista.textContent = '';
            if (!d.ok) { lista.textContent = d.erro || 'Não foi possível abrir a conversa.'; return; }
            document.getElementById('convTitulo').textContent = d.aluno + '  ×  ' + d.mentor;
            document.getElementById('convSub').textContent = d.assunto + ' · ' + d.status + ' · pedido em ' + d.criadoEm;
            if (!d.linhas.length) { lista.textContent = 'Nenhuma mensagem nesta conversa.'; return; }
            d.linhas.forEach(l => {
                const aluno = l.papel === 'Aluno', sistema = l.papel === 'Sistema';
                const caixa = document.createElement('div');
                const denunciada = l.tipo === 'Mensagem DENUNCIADA';
                caixa.className = 'max-w-[85%] p-3 rounded-2xl text-sm ' + (sistema ? 'mx-auto bg-amber-50 text-amber-800 text-center'
                    : aluno ? 'bg-white border border-gray-100' : 'ml-auto bg-[#4A9FCA]/10') + (denunciada ? ' ring-2 ring-red-400' : '');
                const cab = document.createElement('p');
                cab.className = 'text-[11px] text-gray-400 mb-1';
                cab.textContent = l.autor + ' (' + l.papel + ') · ' + l.data + (l.tipo !== 'Mensagem' ? ' · ' + l.tipo : '');
                const txt = document.createElement('p');
                txt.className = 'whitespace-pre-line text-gray-800';
                txt.textContent = l.texto;                 // sem HTML: texto puro
                caixa.append(cab, txt);
                lista.appendChild(caixa);
            });
        })
        .catch(() => { lista.textContent = 'Não foi possível abrir a conversa.'; });
}
</script>

<?php include 'includes/footer.php'; ?>
