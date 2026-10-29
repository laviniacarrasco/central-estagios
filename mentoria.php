<?php
/**
 * Mentoria: alunos pedem orientacao de carreira a egressos.
 *  - ALUNO:   encontra mentores aprovados, envia pedidos e acompanha as respostas.
 *  - EGRESSO: se cadastra como mentor e responde os pedidos que recebe.
 * Os contatos so sao liberados depois que o mentor aceita o pedido.
 * Depois do aceite, aluno e mentor conversam pelo chat (mentoria-chat.php).
 * Aba "Denúncias" (aluno e mentor): denunciar uma conversa e acompanhar as denúncias.
 * No chat, cada mensagem da outra pessoa tem "Denunciar".
 */
require_once 'includes/config.php';
require_once 'includes/mentoria.php';
checkAuth();

if (!empty($_SESSION['is_admin'])) {
    header('Location: admin-dashboard.php');
    exit;
}

$souEgresso = isEgresso();
$userId     = (int) $_SESSION['user_id'];
$eu         = egressoCarregarUsuario($userId);
if (!$eu) {
    header('Location: index.php?logout=1');
    exit;
}

$pageTitle = 'Mentoria';

$mensagens = [
    'pedido_enviado'      => 'Pedido enviado! Você será avisado por e-mail quando o mentor responder.',
    'denuncia_enviada'    => 'Denúncia enviada. A coordenação foi avisada e vai analisar. A outra pessoa não sabe quem denunciou.',
    'pedido_cancelado'    => 'Pedido cancelado.',
    'mentoria_encerrada'  => 'Mentoria encerrada. Obrigado por participar!',
    'respondido_aceito'   => 'Pedido aceito. Seus contatos já foram liberados para o aluno.',
    'respondido_recusado' => 'Pedido recusado. O aluno foi avisado.',
    'mentor_enviado'      => 'Cadastro enviado para análise. Você será avisado por e-mail quando for aprovado.',
    'mentor_salvo'        => 'Configurações salvas.',
];
$erros = [
    'assunto'             => 'Escolha um assunto para a conversa.',
    'mensagem'            => 'Escreva uma mensagem com pelo menos 20 caracteres (máximo 500).',
    'mentor_indisponivel' => 'Esse mentor não está disponível no momento.',
    'duplicado'           => 'Você já tem um pedido em andamento com esse mentor.',
    'limite'              => 'Você já tem ' . MENTORIA_MAX_PEDIDOS_ALUNO . ' pedidos em andamento. Aguarde uma resposta ou cancele um pedido para fazer outro.',
    'sem_vaga'            => 'Esse mentor está com todas as vagas ocupadas no momento.',
    'fila_cheia'          => 'Esse mentor já tem muitos pedidos aguardando. Tente outro mentor ou volte mais tarde.',
    'nao_encontrado'      => 'Pedido não encontrado.',
    'ja_respondido'       => 'Esse pedido já foi respondido ou alterado.',
    'status'              => 'Essa ação não é possível para esse pedido.',
    'areas'               => 'Informe pelo menos uma área em que você pode ajudar.',
    'perfil_incompleto'   => 'Antes de se cadastrar, preencha o campo "Sobre você" no seu perfil de egresso.',
    'falha'               => 'Não foi possível concluir agora. Tente novamente.',
    'den_nao_encontrado'  => mentoriaDenunciaErro('nao_encontrado'),
    'den_descricao'       => mentoriaDenunciaErro('descricao'),
    'den_mensagem'        => mentoriaDenunciaErro('mensagem'),
    'den_duplicada'       => mentoriaDenunciaErro('duplicada'),
    'den_limite'          => mentoriaDenunciaErro('limite'),
    'den_falha'           => mentoriaDenunciaErro('falha'),
    'justificativa'       => 'Para recusar, escreva para o aluno o motivo (pelo menos 10 caracteres).',
];

function mentoriaIrPara(string $aba, string $chave, bool $erro = false): void {
    header('Location: mentoria.php?aba=' . $aba . '&' . ($erro ? 'err=' : 'msg=') . $chave);
    exit;
}

/* =========================================================================
 * ACOES (POST)
 * ========================================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);

    /* ---- aluno: pedir mentoria ---- */
    if (!$souEgresso && $acao === 'pedir') {
        $mentorId = (int) ($_POST['mentor_id'] ?? 0);
        $assunto  = trim((string) ($_POST['assunto'] ?? ''));
        $mensagem = trim(preg_replace("/\r\n?/", "\n", (string) ($_POST['mensagem'] ?? '')));

        if (!in_array($assunto, mentoriaAssuntos(), true)) mentoriaIrPara('mentores', 'assunto', true);
        $tam = mb_strlen($mensagem);
        if ($tam < 20 || $tam > 500) mentoriaIrPara('mentores', 'mensagem', true);

        $mentor = egressoCarregarUsuario($mentorId);
        if (!$mentor || !mentoriaMentorVisivel($mentor)) mentoriaIrPara('mentores', 'mentor_indisponivel', true);

        $r = mentoriaCriarPedido($eu, $mentor, $assunto, $mensagem);
        if (empty($r['ok'])) mentoriaIrPara('mentores', $r['erro'] ?? 'falha', true);

        mentoriaEmailNovoPedido($mentor, $r['pedido']);
        mentoriaIrPara('pedidos', 'pedido_enviado');
    }

    /* ---- aluno: cancelar pedido que ainda nao foi respondido ---- */
    if (!$souEgresso && $acao === 'cancelar') {
        $r = mentoriaMudarStatus($id, $userId, ['pendente'], 'cancelado', true);
        if (empty($r['ok'])) mentoriaIrPara('pedidos', $r['erro'] ?? 'falha', true);
        mentoriaIrPara('pedidos', 'pedido_cancelado');
    }

    /* ---- aluno ou mentor: encerrar uma mentoria em andamento ---- */
    if ($acao === 'encerrar') {
        $r = mentoriaMudarStatus($id, $userId, ['aceito'], 'concluido');
        if (empty($r['ok'])) mentoriaIrPara('pedidos', $r['erro'] ?? 'falha', true);
        mentoriaIrPara('pedidos', 'mentoria_encerrada');
    }

    /* ---- egresso: aceitar ou recusar ---- */
    /* ---- aluno ou mentor: denunciar uma conversa ---- */
    if ($acao === 'denunciar') {
        $r = mentoriaCriarDenuncia($userId, (int) ($_POST['pedido'] ?? 0), (string) ($_POST['categoria'] ?? 'outro'),
                                   (string) ($_POST['descricao'] ?? ''));
        if (empty($r['ok'])) mentoriaIrPara('denuncias', 'den_' . ($r['erro'] ?? 'falha'), true);
        mentoriaIrPara('denuncias', 'denuncia_enviada');
    }

    if ($souEgresso && $acao === 'responder') {
        $decisao  = (string) ($_POST['decisao'] ?? '');
        $resposta = mb_substr(trim((string) ($_POST['resposta'] ?? '')), 0, 300);
        if (!in_array($decisao, ['aceitar', 'recusar'], true)) mentoriaIrPara('pedidos', 'status', true);
        if ($decisao === 'recusar') {                       // recusar exige justificativa
            $resposta = (string) justificativaPost('resposta', 300);
            if ($resposta === '') mentoriaIrPara('pedidos', 'justificativa', true);
        }
        if (($eu['mentoriaStatus'] ?? '') !== 'aprovado') mentoriaIrPara('pedidos', 'mentor_indisponivel', true);

        $r = mentoriaResponderPedido($id, $userId, $decisao === 'aceitar', $resposta, $eu);
        if (empty($r['ok'])) mentoriaIrPara('pedidos', $r['erro'] ?? 'falha', true);

        mentoriaEmailResposta($r['pedido'], $decisao === 'aceitar', $eu);
        mentoriaIrPara('pedidos', $decisao === 'aceitar' ? 'respondido_aceito' : 'respondido_recusado');
    }

    /* ---- egresso: cadastro / configuracao de mentor ---- */
    if ($souEgresso && $acao === 'salvar_mentor') {
        $statusAtual = (string) ($eu['mentoriaStatus'] ?? '');
        $areas   = mb_substr(trim((string) ($_POST['areas'] ?? '')), 0, 200);
        $max     = max(1, min(5, (int) ($_POST['max'] ?? 2)));
        $contato = in_array($_POST['contato'] ?? '', ['email', 'linkedin', 'ambos'], true) ? $_POST['contato'] : 'ambos';

        if (empty(mentoriaAreasLista($areas))) mentoriaIrPara('config', 'areas', true);

        $campos = [
            'mentoriaAreas'   => $areas,
            'mentoriaMax'     => $max,
            'mentoriaContato' => $contato,
            'aceitaMentoria'  => isset($_POST['disponivel']),
        ];

        $novoCadastro = ($statusAtual === '' || $statusAtual === 'recusado');
        if ($novoCadastro) {
            if (trim((string) ($eu['bio'] ?? '')) === '') mentoriaIrPara('config', 'perfil_incompleto', true);
            $campos['mentoriaStatus']       = 'pendente';
            $campos['mentoriaSolicitadoEm'] = date('Y-m-d H:i');
            $campos['mentoriaMotivo']       = '';
            $campos['aceitaMentoria']       = true;
        }

        egressoAtualizarUsuario($userId, $campos);

        if ($novoCadastro) {
            mentoriaNotificarAdminsNovoMentor(array_merge($eu, $campos));
            mentoriaIrPara('config', 'mentor_enviado');
        }
        mentoriaIrPara('config', 'mentor_salvo');
    }

    mentoriaIrPara($souEgresso ? 'pedidos' : 'mentores', 'falha', true);
}

/* =========================================================================
 * DADOS DA TELA
 * ========================================================================= */
$abasValidas = $souEgresso ? ['pedidos', 'config', 'denuncias'] : ['mentores', 'pedidos', 'denuncias'];
$abaPadrao   = $souEgresso ? ((($eu['mentoriaStatus'] ?? '') === 'aprovado') ? 'pedidos' : 'config') : 'mentores';
$aba = in_array($_GET['aba'] ?? '', $abasValidas, true) ? $_GET['aba'] : $abaPadrao;

$todosPedidos = mentoriaPedidos();
$meusPedidos  = array_values(array_filter($todosPedidos, function ($p) use ($souEgresso, $userId) {
    $dono = $souEgresso ? ($p['mentorId'] ?? 0) : ($p['alunoId'] ?? 0);
    return (int) $dono === $userId;
}));
usort($meusPedidos, function ($a, $b) {
    return ($b['criadoTs'] ?? 0) <=> ($a['criadoTs'] ?? 0);
});

$emAndamento = 0;
$pedidoPorMentor = [];
$pendentesRecebidos = 0;
foreach ($meusPedidos as $p) {
    $st = $p['status'] ?? '';
    if (!$souEgresso && mentoriaEmAndamento($st)) {
        $emAndamento++;
        $pedidoPorMentor[(int) ($p['mentorId'] ?? 0)] = $st;
    }
    if ($souEgresso && $st === 'pendente') $pendentesRecebidos++;
}

/* dados de contato da outra parte (so usados em pedidos aceitos) */
$idsNecessarios = [];
foreach ($meusPedidos as $p) {
    $idsNecessarios[(int) ($souEgresso ? ($p['alunoId'] ?? 0) : ($p['mentorId'] ?? 0))] = true;
}
$outraParte = [];
if (!empty($idsNecessarios)) {
    foreach (loadData('usuarios') as $u) {
        $uid = (int) ($u['id'] ?? 0);
        if (isset($idsNecessarios[$uid])) $outraParte[$uid] = $u;
    }
}

/* aluno: lista de mentores com filtros */
$busca      = trim((string) ($_GET['search'] ?? ''));
$cursoSel   = trim((string) ($_GET['curso'] ?? ''));
$fsaCourses = getFsaCourses();
$mentores   = [];
if (!$souEgresso && $aba === 'mentores') {
    $mentores = array_values(array_filter(mentoriaMentores(), function ($m) use ($busca, $cursoSel) {
        if ($cursoSel !== '' && ($m['curso'] ?? '') !== $cursoSel) return false;
        if ($busca !== '') {
            $alvo = implode(' ', [
                $m['nome'] ?? '', $m['mentoriaAreas'] ?? '', $m['cargoAtual'] ?? '',
                $m['empresaAtual'] ?? '', $m['areaAtuacao'] ?? '', $m['bio'] ?? '',
            ]);
            if (mb_stripos($alvo, $busca) === false) return false;
        }
        return true;
    }));
}

/* egresso: situacao do cadastro de mentor */
$mStatus = (string) ($eu['mentoriaStatus'] ?? '');
[$mStatusTxt, $mStatusCls, $mStatusIco] = mentoriaMentorStatusInfo($mStatus);
$aceitosAgora = $souEgresso ? mentoriaAceitosDoMentor($userId, $todosPedidos) : 0;
$maxMentor    = $souEgresso ? mentoriaMax($eu) : 0;

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-7xl mx-auto">

        <div class="mb-8 animate-fade-in">
            <h2 class="text-3xl font-bold text-slate-900 mb-2">Mentoria</h2>
            <p class="text-slate-500 font-medium">
                <?= $souEgresso
                    ? 'Ajude alunos da FSA com a sua experiência de carreira.'
                    : 'Converse com egressos da FSA que já passaram pelo caminho que você está começando.' ?>
            </p>
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

        <!-- Abas -->
        <div class="flex flex-wrap gap-2 mb-8">
            <?php if ($souEgresso): ?>
                <a href="mentoria.php?aba=pedidos" class="px-5 py-2.5 rounded-xl text-sm font-semibold <?= $aba === 'pedidos' ? 'bg-[#4A9FCA] text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
                    <i class="fas fa-inbox mr-1"></i> Pedidos recebidos
                    <?php if ($pendentesRecebidos > 0): ?>
                        <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-bold <?= $aba === 'pedidos' ? 'bg-white text-[#4A9FCA]' : 'bg-red-500 text-white' ?>"><?= $pendentesRecebidos ?></span>
                    <?php endif; ?>
                </a>
                <a href="mentoria.php?aba=config" class="px-5 py-2.5 rounded-xl text-sm font-semibold <?= $aba === 'config' ? 'bg-[#4A9FCA] text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
                    <i class="fas fa-sliders-h mr-1"></i> Minha mentoria
                </a>
            <?php else: ?>
                <a href="mentoria.php?aba=mentores" class="px-5 py-2.5 rounded-xl text-sm font-semibold <?= $aba === 'mentores' ? 'bg-[#4A9FCA] text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
                    <i class="fas fa-search mr-1"></i> Encontrar mentores
                </a>
                <a href="mentoria.php?aba=pedidos" class="px-5 py-2.5 rounded-xl text-sm font-semibold <?= $aba === 'pedidos' ? 'bg-[#4A9FCA] text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
                    <i class="fas fa-clipboard-list mr-1"></i> Meus pedidos
                    <?php if ($emAndamento > 0): ?>
                        <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-bold <?= $aba === 'pedidos' ? 'bg-white text-[#4A9FCA]' : 'bg-[#4A9FCA] text-white' ?>"><?= $emAndamento ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
            <a href="mentoria.php?aba=denuncias" class="px-5 py-2.5 rounded-xl text-sm font-semibold <?= $aba === 'denuncias' ? 'bg-red-500 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
                <i class="fas fa-flag mr-1"></i> Denúncias
            </a>
        </div>

<?php /* ===================== ALUNO: ENCONTRAR MENTORES ===================== */ ?>
<?php if (!$souEgresso && $aba === 'mentores'): ?>

        <div class="mb-6 p-4 bg-blue-50 border border-blue-100 text-slate-600 rounded-xl text-sm flex items-start gap-3">
            <i class="fas fa-info-circle text-[#4A9FCA] mt-0.5"></i>
            <div>
                Você pode ter até <strong><?= MENTORIA_MAX_PEDIDOS_ALUNO ?> pedidos em andamento</strong> ao mesmo tempo
                (você tem <strong><?= $emAndamento ?></strong>).
                O contato do mentor só aparece depois que ele aceitar o seu pedido.
            </div>
        </div>

        <form method="GET" action="mentoria.php" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-8">
            <input type="hidden" name="aba" value="mentores">
            <div class="md:col-span-2 relative">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="search" value="<?= egh($busca) ?>" placeholder="Buscar por nome, área, cargo ou empresa..."
                       class="w-full h-12 pl-12 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#4A9FCA]">
            </div>
            <select name="curso" class="h-12 px-4 bg-white border border-gray-200 rounded-xl" onchange="this.form.submit()">
                <option value="">Todos os cursos</option>
                <?php foreach ($fsaCourses as $c): ?>
                    <option value="<?= egh($c) ?>" <?= $c === $cursoSel ? 'selected' : '' ?>><?= egh($c) ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if (empty($mentores)): ?>
            <div class="p-16 text-center border-2 border-dashed border-gray-200 rounded-3xl bg-white">
                <i class="fas fa-hands-helping text-6xl text-gray-200 mb-4"></i>
                <h3 class="text-xl font-bold text-slate-900 mb-2">Nenhum mentor encontrado</h3>
                <p class="text-slate-500">
                    <?= ($busca !== '' || $cursoSel !== '') ? 'Tente ajustar a busca ou o curso.' : 'Ainda não há mentores disponíveis. Volte em breve!' ?>
                </p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <?php foreach ($mentores as $m):
                    $mid     = (int) $m['id'];
                    $areas   = mentoriaAreasLista($m['mentoriaAreas'] ?? '');
                    $foto    = mentoriaFotoUsuario($mid);
                    $estado  = $pedidoPorMentor[$mid] ?? '';
                    $livres  = (int) $m['_livres'];
                    $cargo   = implode(' · ', array_filter([trim((string) ($m['cargoAtual'] ?? '')), trim((string) ($m['empresaAtual'] ?? ''))]));
                    $bio     = trim((string) ($m['bio'] ?? ''));
                ?>
                    <div class="p-6 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md transition-shadow flex flex-col">
                        <div class="flex items-start gap-4 mb-4">
                            <?php if ($foto): ?>
                                <img src="<?= egh($foto) ?>" alt="" class="w-16 h-16 rounded-full object-cover border-2 border-[#4A9FCA] flex-shrink-0">
                            <?php else: ?>
                                <div class="w-16 h-16 bg-gradient-to-br from-[#4A9FCA] to-[#3A8FB0] rounded-full flex items-center justify-center text-white font-bold text-xl flex-shrink-0">
                                    <?= egh(mentoriaIniciais($m['nome'] ?? '')) ?>
                                </div>
                            <?php endif; ?>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-lg font-bold text-slate-900"><?= egh($m['nome'] ?? '') ?></h3>
                                <p class="text-sm text-slate-600"><?= egh($m['curso'] ?? '') ?><?= !empty($m['anoConclusao']) ? ' &bull; turma de ' . egh($m['anoConclusao']) : '' ?></p>
                                <?php if ($cargo !== ''): ?>
                                    <p class="text-sm text-[#2B7FA6] font-semibold mt-1"><i class="fas fa-briefcase mr-1"></i><?= egh($cargo) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($m['cidade'])): ?>
                                    <p class="text-xs text-gray-400 mt-0.5"><i class="fas fa-map-marker-alt mr-1"></i><?= egh($m['cidade']) ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-bold flex-shrink-0 <?= $livres > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' ?>">
                                <?= $livres > 0 ? $livres . ' vaga' . ($livres > 1 ? 's' : '') : 'Sem vagas' ?>
                            </span>
                        </div>

                        <?php if (!empty($areas)): ?>
                            <div class="flex flex-wrap gap-2 mb-3">
                                <?php foreach ($areas as $a): ?>
                                    <span class="px-3 py-1 bg-[#eaf4fa] text-[#2B7FA6] rounded-full text-xs font-semibold"><?= egh($a) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($bio !== ''): ?>
                            <p class="text-sm text-gray-600 leading-relaxed mb-4"><?= egh(mb_strimwidth($bio, 0, 240, '…')) ?></p>
                        <?php endif; ?>

                        <div class="mt-auto pt-2">
                            <?php if ($estado === 'aceito'): ?>
                                <a href="mentoria.php?aba=pedidos" class="block text-center px-5 py-3 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-xl text-sm font-semibold">
                                    <i class="fas fa-handshake mr-1"></i> Mentoria ativa &mdash; ver contato
                                </a>
                            <?php elseif ($estado === 'pendente'): ?>
                                <div class="text-center px-5 py-3 bg-amber-50 text-amber-700 border border-amber-200 rounded-xl text-sm font-semibold">
                                    <i class="fas fa-clock mr-1"></i> Pedido enviado, aguardando resposta
                                </div>
                            <?php elseif ($livres <= 0): ?>
                                <div class="text-center px-5 py-3 bg-gray-100 text-gray-500 rounded-xl text-sm font-semibold">
                                    <i class="fas fa-lock mr-1"></i> Sem vagas no momento
                                </div>
                            <?php elseif ($emAndamento >= MENTORIA_MAX_PEDIDOS_ALUNO): ?>
                                <div class="text-center px-5 py-3 bg-gray-100 text-gray-500 rounded-xl text-sm font-semibold">
                                    <i class="fas fa-hourglass-half mr-1"></i> Limite de pedidos atingido
                                </div>
                            <?php else: ?>
                                <button type="button" onclick="abrirPedido(this)" data-id="<?= $mid ?>" data-nome="<?= egh($m['nome'] ?? '') ?>"
                                        class="w-full px-5 py-3 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl text-sm font-semibold transition-all">
                                    <i class="fas fa-hands-helping mr-1"></i> Pedir mentoria
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Modal: pedir mentoria -->
        <div id="modalPedido" class="fixed inset-0 z-[999] hidden items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="fecharPedido()"></div>
            <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-lg p-8 z-10">
                <div class="w-16 h-16 bg-[#e8f3fa] rounded-2xl flex items-center justify-center mx-auto mb-5">
                    <i class="fas fa-hands-helping text-[#4A9FCA] text-3xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 text-center mb-1">Pedir mentoria</h3>
                <p id="pedidoNomeMentor" class="text-[#4A9FCA] font-semibold text-sm text-center mb-5"></p>

                <form method="POST" action="mentoria.php" id="formPedido" class="space-y-4">
                    <input type="hidden" name="acao" value="pedir">
                    <input type="hidden" name="mentor_id" id="pedidoMentorId" value="">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sobre o que você quer conversar?</label>
                        <select name="assunto" required class="w-full h-11 px-3 border border-gray-200 rounded-lg bg-white">
                            <option value="">Selecione...</option>
                            <?php foreach (mentoriaAssuntos() as $a): ?>
                                <option value="<?= egh($a) ?>"><?= egh($a) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Sua mensagem <span class="text-xs text-gray-400 font-normal">(mínimo 20 caracteres)</span>
                        </label>
                        <textarea name="mensagem" id="pedidoMensagem" rows="4" maxlength="500" required
                                  placeholder="Conte rapidamente quem você é e o que gostaria de aprender."
                                  class="w-full px-3 py-2 border border-gray-200 rounded-lg"></textarea>
                        <p class="text-xs text-gray-400 text-right mt-1"><span id="pedidoContador">0</span>/500</p>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="fecharPedido()"
                                class="flex-1 py-3 px-4 rounded-2xl border-2 border-gray-200 text-gray-600 font-semibold hover:bg-gray-50 transition-all text-sm">
                            <i class="fas fa-times mr-1"></i> Cancelar
                        </button>
                        <button type="submit"
                                class="flex-1 py-3 px-4 rounded-2xl bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white font-semibold transition-all text-sm shadow-lg">
                            <i class="fas fa-paper-plane mr-1"></i> Enviar pedido
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function abrirPedido(btn) {
            document.getElementById('pedidoMentorId').value = btn.dataset.id;
            document.getElementById('pedidoNomeMentor').textContent = btn.dataset.nome;
            var modal = document.getElementById('modalPedido');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
        function fecharPedido() {
            var modal = document.getElementById('modalPedido');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
        document.getElementById('pedidoMensagem').addEventListener('input', function () {
            document.getElementById('pedidoContador').textContent = this.value.length;
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') fecharPedido();
        });
        </script>

<?php /* ===================== ALUNO: MEUS PEDIDOS ===================== */ ?>
<?php elseif (!$souEgresso && $aba === 'pedidos'): ?>

        <?php if (empty($meusPedidos)): ?>
            <div class="p-16 text-center border-2 border-dashed border-gray-200 rounded-3xl bg-white">
                <i class="fas fa-clipboard-list text-6xl text-gray-200 mb-4"></i>
                <h3 class="text-xl font-bold text-slate-900 mb-2">Você ainda não fez nenhum pedido</h3>
                <p class="text-slate-500 mb-6">Escolha um mentor e conte o que você gostaria de aprender.</p>
                <a href="mentoria.php?aba=mentores" class="inline-block px-6 py-3 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl font-semibold text-sm">
                    <i class="fas fa-search mr-1"></i> Encontrar mentores
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($meusPedidos as $p):
                    $st = $p['status'] ?? '';
                    [$sTxt, $sCls, $sIco] = mentoriaPedidoStatusInfo($st);
                    $mentorU = $outraParte[(int) ($p['mentorId'] ?? 0)] ?? [];
                    $contato = $mentorU ? mentoriaContatoMentor($mentorU) : ['email' => '', 'linkedin' => ''];
                    $linkLk  = $contato['linkedin'] !== '' ? egressoNormalizarLink($contato['linkedin']) : '';
                ?>
                    <div class="p-6 bg-white border border-gray-100 rounded-2xl shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                            <div>
                                <h3 class="text-lg font-bold text-slate-900"><?= egh($p['mentorNome'] ?? '') ?></h3>
                                <p class="text-sm text-gray-500">Assunto: <strong class="text-slate-700"><?= egh($p['assunto'] ?? '') ?></strong> &bull; <?= egh($p['criadoEm'] ?? '') ?></p>
                            </div>
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold border <?= egh($sCls) ?>">
                                <i class="fas <?= egh($sIco) ?>"></i> <?= egh($sTxt) ?>
                            </span>
                        </div>

                        <p class="text-sm text-gray-600 bg-gray-50 p-3 rounded-xl whitespace-pre-line"><?= egh($p['mensagem'] ?? '') ?></p>

                        <?php if (!empty($p['resposta'])): ?>
                            <p class="mt-3 text-sm text-slate-700 bg-blue-50 border border-blue-100 p-3 rounded-xl">
                                <strong>Resposta do mentor:</strong> <?= egh($p['resposta']) ?>
                            </p>
                        <?php endif; ?>



                        <?php if (mentoriaChatVisivel($p)): $naoLidas = mentoriaNaoLidasPedido($p, $userId); ?>
                            <button type="button" onclick="abrirChat(this)"
                                    data-pedido="<?= (int) $p['id'] ?>"
                                    data-nome="<?= egh($p['mentorNome'] ?? 'Mentor') ?>"
                                    class="mt-4 mr-2 px-4 py-2 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl text-sm font-semibold transition-all inline-flex items-center gap-2">
                                <i class="fas fa-comments"></i> <?= ($p['status'] ?? '') === 'aceito' ? 'Abrir conversa' : 'Ver conversa' ?>
                                <?php if ($naoLidas > 0): ?>
                                    <span class="chat-badge min-w-[20px] h-5 px-1.5 rounded-full bg-red-500 text-white text-xs font-bold flex items-center justify-center"><?= $naoLidas ?></span>
                                <?php endif; ?>
                            </button>
                        <?php endif; ?>
                        <?php if ($st === 'pendente'): ?>
                            <form method="POST" action="mentoria.php" class="mt-4"
                                  data-confirm="O mentor não será mais avisado deste pedido e você libera uma vaga na sua lista."
                                  data-confirm-titulo="Cancelar pedido?" data-confirm-botao="Cancelar pedido"
                                  data-confirm-tipo="perigo" data-confirm-icone="fa-ban">
                                <input type="hidden" name="acao" value="cancelar">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button type="submit" class="px-4 py-2 border border-red-200 text-red-600 rounded-xl text-sm hover:bg-red-50 transition-all">
                                    <i class="fas fa-ban mr-1"></i> Cancelar pedido
                                </button>
                            </form>
                        <?php elseif ($st === 'aceito'): ?>
                            <form method="POST" action="mentoria.php" class="mt-4"
                                  data-confirm="Use isto quando a conversa terminar. Você libera uma vaga na sua lista de pedidos."
                                  data-confirm-titulo="Encerrar esta mentoria?" data-confirm-botao="Encerrar"
                                  data-confirm-tipo="aviso" data-confirm-icone="fa-flag-checkered">
                                <input type="hidden" name="acao" value="encerrar">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button type="submit" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-xl text-sm hover:bg-gray-50 transition-all">
                                    <i class="fas fa-flag-checkered mr-1"></i> Encerrar mentoria
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

<?php /* ===================== EGRESSO: PEDIDOS RECEBIDOS ===================== */ ?>
<?php elseif ($souEgresso && $aba === 'pedidos'):
    $listaPend = array_values(array_filter($meusPedidos, function ($p) { return ($p['status'] ?? '') === 'pendente'; }));
    $listaAtiv = array_values(array_filter($meusPedidos, function ($p) { return ($p['status'] ?? '') === 'aceito'; }));
    $listaHist = array_values(array_filter($meusPedidos, function ($p) { return !in_array($p['status'] ?? '', ['pendente', 'aceito'], true); }));
?>

        <?php if ($mStatus !== 'aprovado'): ?>
            <div class="mb-6 p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-sm flex items-start gap-3">
                <i class="fas fa-info-circle mt-0.5"></i>
                <div>
                    Você ainda não aparece para os alunos (<?= egh(mb_strtolower($mStatusTxt)) ?>).
                    <a href="mentoria.php?aba=config" class="font-semibold underline">Ver minha mentoria</a>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="p-6 bg-[#E35444] text-white rounded-2xl h-28">
                <p class="text-white/90 text-sm font-medium mb-1">Aguardando sua resposta</p>
                <p class="text-4xl font-bold"><?= count($listaPend) ?></p>
            </div>
            <div class="p-6 bg-[#27AE60] text-white rounded-2xl h-28">
                <p class="text-white/90 text-sm font-medium mb-1">Mentorias em andamento</p>
                <p class="text-4xl font-bold"><?= count($listaAtiv) ?> <span class="text-lg font-medium">de <?= $maxMentor ?></span></p>
            </div>
            <div class="p-6 bg-[#58A1D4] text-white rounded-2xl h-28">
                <p class="text-white/90 text-sm font-medium mb-1">Total de pedidos</p>
                <p class="text-4xl font-bold"><?= count($meusPedidos) ?></p>
            </div>
        </div>

        <?php if (empty($meusPedidos)): ?>
            <div class="p-16 text-center border-2 border-dashed border-gray-200 rounded-3xl bg-white">
                <i class="fas fa-inbox text-6xl text-gray-200 mb-4"></i>
                <h3 class="text-xl font-bold text-slate-900 mb-2">Nenhum pedido ainda</h3>
                <p class="text-slate-500">Quando um aluno pedir mentoria, você recebe um e-mail e o pedido aparece aqui.</p>
            </div>
        <?php endif; ?>

        <?php if (!empty($listaPend)): ?>
            <h3 class="text-lg font-bold text-slate-900 mb-3 flex items-center gap-2"><i class="fas fa-clock text-amber-500"></i> Aguardando sua resposta</h3>
            <div class="space-y-4 mb-10">
                <?php foreach ($listaPend as $p): ?>
                    <div class="p-6 bg-white border-2 border-amber-200 rounded-2xl shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                            <div>
                                <h4 class="text-lg font-bold text-slate-900"><?= egh($p['alunoNome'] ?? '') ?></h4>
                                <p class="text-sm text-gray-500">
                                    <?= egh($p['alunoCurso'] ?? '') ?><?= (!empty($p['alunoPeriodo']) && $p['alunoPeriodo'] !== '—') ? ' &bull; ' . egh($p['alunoPeriodo']) : '' ?>
                                    &bull; <?= egh($p['criadoEm'] ?? '') ?>
                                </p>
                            </div>
                            <span class="px-3 py-1 bg-[#eaf4fa] text-[#2B7FA6] rounded-full text-xs font-bold"><?= egh($p['assunto'] ?? '') ?></span>
                        </div>
                        <p class="text-sm text-gray-700 bg-gray-50 p-3 rounded-xl whitespace-pre-line"><?= egh($p['mensagem'] ?? '') ?></p>

                        <form method="POST" action="mentoria.php" class="mt-4">
                            <input type="hidden" name="acao" value="responder">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <input type="hidden" name="decisao" value="">
                            <textarea name="resposta" rows="2" maxlength="300" placeholder="Mensagem para o aluno (opcional ao aceitar, obrigatória ao recusar)"
                                      class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm"></textarea>
                            <div class="flex flex-wrap gap-3 mt-3">
                                <button type="button" onclick="responderPedido(this, 'aceitar')"
                                        class="px-5 py-2.5 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl text-sm font-semibold transition-all">
                                    <i class="fas fa-handshake mr-1"></i> Aceitar
                                </button>
                                <button type="button" onclick="responderPedido(this, 'recusar')"
                                        class="px-5 py-2.5 border border-red-200 text-red-600 rounded-xl text-sm font-semibold hover:bg-red-50 transition-all">
                                    <i class="fas fa-times mr-1"></i> Recusar
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($listaAtiv)): ?>
            <h3 class="text-lg font-bold text-slate-900 mb-3 flex items-center gap-2"><i class="fas fa-handshake text-emerald-500"></i> Em andamento</h3>
            <div class="space-y-4 mb-10">
                <?php foreach ($listaAtiv as $p): ?>
                    <div class="p-6 bg-white border border-gray-100 rounded-2xl shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                            <div>
                                <h4 class="text-lg font-bold text-slate-900"><?= egh($p['alunoNome'] ?? '') ?></h4>
                                <p class="text-sm text-gray-500"><?= egh($p['alunoCurso'] ?? '') ?> &bull; Assunto: <strong class="text-slate-700"><?= egh($p['assunto'] ?? '') ?></strong></p>
                            </div>
                            <?php if (!empty($p['alunoEmail'])): ?>
                                <a href="mailto:<?= egh($p['alunoEmail']) ?>" class="text-sm text-[#2B7FA6] font-semibold hover:underline">
                                    <i class="fas fa-envelope mr-1"></i><?= egh($p['alunoEmail']) ?>
                                </a>
                            <?php endif; ?>
                        </div>
                        <p class="text-sm text-gray-600 bg-gray-50 p-3 rounded-xl whitespace-pre-line"><?= egh($p['mensagem'] ?? '') ?></p>
                        <?php if (mentoriaChatVisivel($p)): $naoLidas = mentoriaNaoLidasPedido($p, $userId); ?>
                            <button type="button" onclick="abrirChat(this)"
                                    data-pedido="<?= (int) $p['id'] ?>"
                                    data-nome="<?= egh($p['alunoNome'] ?? 'Aluno') ?>"
                                    class="mt-4 mr-2 px-4 py-2 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl text-sm font-semibold transition-all inline-flex items-center gap-2">
                                <i class="fas fa-comments"></i> <?= ($p['status'] ?? '') === 'aceito' ? 'Abrir conversa' : 'Ver conversa' ?>
                                <?php if ($naoLidas > 0): ?>
                                    <span class="chat-badge min-w-[20px] h-5 px-1.5 rounded-full bg-red-500 text-white text-xs font-bold flex items-center justify-center"><?= $naoLidas ?></span>
                                <?php endif; ?>
                            </button>
                        <?php endif; ?>
                        <form method="POST" action="mentoria.php" class="mt-4"
                              data-confirm="Use isto quando a conversa terminar. Você libera uma vaga para outro aluno."
                              data-confirm-titulo="Encerrar esta mentoria?" data-confirm-botao="Encerrar"
                              data-confirm-tipo="aviso" data-confirm-icone="fa-flag-checkered">
                            <input type="hidden" name="acao" value="encerrar">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button type="submit" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-xl text-sm hover:bg-gray-50 transition-all">
                                <i class="fas fa-flag-checkered mr-1"></i> Encerrar mentoria
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($listaHist)): ?>
            <h3 class="text-lg font-bold text-slate-900 mb-3 flex items-center gap-2"><i class="fas fa-history text-gray-400"></i> Histórico</h3>
            <div class="space-y-3">
                <?php foreach ($listaHist as $p):
                    [$sTxt, $sCls, $sIco] = mentoriaPedidoStatusInfo($p['status'] ?? '');
                ?>
                    <div class="p-4 bg-white border border-gray-100 rounded-2xl flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold text-slate-800"><?= egh($p['alunoNome'] ?? '') ?></p>
                            <p class="text-xs text-gray-400"><?= egh($p['assunto'] ?? '') ?> &bull; <?= egh($p['criadoEm'] ?? '') ?></p>
                        </div>
                        <div class="flex items-center gap-2">
                            <?php if (mentoriaChatVisivel($p) && !empty($p['mensagens'])): ?>
                                <button type="button" onclick="abrirChat(this)" data-pedido="<?= (int) $p['id'] ?>" data-nome="<?= egh($p['alunoNome'] ?? 'Aluno') ?>"
                                        class="px-3 py-1 border border-gray-200 text-gray-600 rounded-full text-xs font-semibold hover:bg-gray-50">
                                    <i class="fas fa-comments mr-1"></i> Ver conversa
                                </button>
                            <?php endif; ?>
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold border <?= egh($sCls) ?>">
                                <i class="fas <?= egh($sIco) ?>"></i> <?= egh($sTxt) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <script>
        function responderPedido(btn, decisao) {
            var f = btn.closest('form');
            var aceitar = decisao === 'aceitar';
            fsaConfirmar({
                titulo: aceitar ? 'Aceitar este pedido?' : 'Recusar este pedido?',
                texto: aceitar
                    ? 'Seu e-mail e/ou LinkedIn será compartilhado com o aluno para vocês combinarem a conversa.'
                    : 'O aluno será avisado e verá a sua justificativa.',
                botao: aceitar ? 'Aceitar' : 'Recusar',
                tipo: aceitar ? 'aviso' : 'perigo',
                icone: aceitar ? 'fa-handshake' : 'fa-times',
                justificativa: !aceitar,                     // recusar exige justificativa
                justificativaValor: f.querySelector('[name=resposta]').value,
                justificativaDica: 'Ex.: no momento não tenho experiência nesse assunto; sugiro procurar outro mentor.',
                justificativaMax: 300
            }, function (texto) {
                f.querySelector('[name=decisao]').value = decisao;
                if (!aceitar) f.querySelector('[name=resposta]').value = texto;
                f.submit();
            });
        }
        </script>

<?php /* ===================== EGRESSO: MINHA MENTORIA ===================== */ ?>
<?php elseif ($souEgresso && $aba === 'config'):
    $areasAtuais  = (string) ($eu['mentoriaAreas'] ?? '');
    $contatoAtual = (string) ($eu['mentoriaContato'] ?? 'ambos');
    $novoCadastro = ($mStatus === '' || $mStatus === 'recusado');
    $perfilSemBio = trim((string) ($eu['bio'] ?? '')) === '';
?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="space-y-6">
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <p class="text-xs text-gray-500 mb-2">Situação do seu cadastro</p>
                    <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-sm font-bold border <?= egh($mStatusCls) ?>">
                        <i class="fas <?= egh($mStatusIco) ?>"></i> <?= egh($mStatusTxt) ?>
                    </span>
                    <?php if ($mStatus === 'pendente'): ?>
                        <p class="text-sm text-gray-500 mt-3">A coordenação está analisando. Você recebe um e-mail quando for aprovado.</p>
                    <?php elseif ($mStatus === 'aprovado'): ?>
                        <p class="text-sm text-gray-500 mt-3">
                            <?= !empty($eu['aceitaMentoria'])
                                ? 'Os alunos já podem pedir mentoria para você.'
                                : 'Você está pausado: não aparece para os alunos.' ?>
                        </p>
                        <div class="flex justify-between text-sm py-1 mt-3 border-t pt-3"><span class="text-gray-500">Vagas ocupadas</span><strong><?= $aceitosAgora ?> de <?= $maxMentor ?></strong></div>
                    <?php elseif ($mStatus === 'recusado' || $mStatus === 'suspenso'): ?>
                        <?php if (!empty($eu['mentoriaMotivo'])): ?>
                            <p class="text-sm text-gray-600 mt-3 bg-gray-50 p-3 rounded-xl"><strong>Motivo:</strong> <?= egh($eu['mentoriaMotivo']) ?></p>
                        <?php endif; ?>
                        <?php if ($mStatus === 'suspenso'): ?>
                            <p class="text-sm text-gray-500 mt-3">Em caso de dúvidas, fale com a coordenação.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="bg-blue-50 border border-blue-100 p-6 rounded-2xl text-sm text-slate-600">
                    <p class="font-bold text-slate-800 mb-2"><i class="fas fa-shield-alt text-[#4A9FCA] mr-1"></i> Como funciona</p>
                    <ul class="space-y-2 list-disc pl-5">
                        <li>Os alunos mandam um pedido com uma mensagem curta.</li>
                        <li>Você escolhe aceitar ou recusar.</li>
                        <li>Seu e-mail e LinkedIn só aparecem para o aluno <strong>depois que você aceitar</strong>.</li>
                        <li>Seu telefone nunca é compartilhado.</li>
                    </ul>
                </div>
            </div>

            <div class="lg:col-span-2 bg-white p-8 rounded-2xl border border-gray-100 shadow-sm">
                <h3 class="text-xl font-bold text-slate-900 mb-6">
                    <i class="fas fa-sliders-h text-[#4A9FCA] mr-2"></i>
                    <?= $novoCadastro ? 'Quero ser mentor(a)' : 'Configurações de mentoria' ?>
                </h3>

                <?php if ($novoCadastro && $perfilSemBio): ?>
                    <div class="mb-6 p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-sm">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Para os alunos conhecerem você, preencha o campo <strong>"Sobre você"</strong> no
                        <a href="egresso-perfil.php" class="underline font-semibold">seu perfil de egresso</a> antes de enviar o cadastro.
                    </div>
                <?php endif; ?>

                <form method="POST" action="mentoria.php" class="space-y-6">
                    <input type="hidden" name="acao" value="salvar_mentor">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Em quais áreas você pode ajudar? <span class="text-xs text-gray-400 font-normal">(separe por vírgula)</span>
                        </label>
                        <input type="text" name="areas" required maxlength="200" value="<?= egh($areasAtuais) ?>"
                               placeholder="Ex: carreira em dados, entrevistas técnicas, transição de área"
                               class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                    </div>

                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quantos alunos ao mesmo tempo?</label>
                            <select name="max" class="w-full h-11 px-3 border border-gray-200 rounded-lg bg-white">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?= $i ?>" <?= mentoriaMax($eu) === $i ? 'selected' : '' ?>><?= $i ?> aluno<?= $i > 1 ? 's' : '' ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Como prefere ser contatado(a)?</label>
                            <select name="contato" class="w-full h-11 px-3 border border-gray-200 rounded-lg bg-white">
                                <option value="ambos" <?= $contatoAtual === 'ambos' ? 'selected' : '' ?>>E-mail e LinkedIn</option>
                                <option value="email" <?= $contatoAtual === 'email' ? 'selected' : '' ?>>Só e-mail</option>
                                <option value="linkedin" <?= $contatoAtual === 'linkedin' ? 'selected' : '' ?>>Só LinkedIn</option>
                            </select>
                        </div>
                    </div>

                    <p class="text-xs text-gray-400">
                        E-mail usado: <strong><?= egh($eu['email'] ?? '—') ?></strong> &bull;
                        LinkedIn: <strong><?= !empty($eu['linkedin']) ? 'cadastrado' : 'não cadastrado' ?></strong>.
                        Altere em <a href="egresso-perfil.php" class="text-[#4A9FCA] underline">Meu perfil de egresso</a>.
                    </p>

                    <?php if (!$novoCadastro): ?>
                        <label class="flex items-start gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="disponivel" class="mt-1" <?= !empty($eu['aceitaMentoria']) ? 'checked' : '' ?>>
                            <span>Aparecer para os alunos (desmarque para pausar novos pedidos)</span>
                        </label>
                    <?php endif; ?>

                    <button type="submit" class="px-8 h-12 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl font-semibold">
                        <i class="fas <?= $novoCadastro ? 'fa-paper-plane' : 'fa-save' ?> mr-1"></i>
                        <?= $novoCadastro ? 'Enviar para aprovação' : 'Salvar' ?>
                    </button>
                </form>
            </div>
        </div>

<?php /* ===================== ALUNO E MENTOR: DENÚNCIAS ===================== */ ?>
<?php elseif ($aba === 'denuncias'):
    $minhasDen = array_values(array_filter(mentoriaDenuncias(), fn($d) => (int) ($d['denuncianteId'] ?? 0) === $userId));
    usort($minhasDen, fn($a, $b) => ($b['criadoTs'] ?? 0) <=> ($a['criadoTs'] ?? 0));
    $catsDen = mentoriaDenunciaCategorias();
?>

        <div class="mb-6 p-4 bg-red-50 border border-red-100 text-slate-700 rounded-xl text-sm flex items-start gap-3">
            <i class="fas fa-shield-alt text-red-500 mt-0.5"></i>
            <div>
                Algo na mentoria te deixou desconfortável? Assédio, ofensa, pedido de dinheiro ou de dados pessoais: <strong>denuncie</strong>.
                A Coordenação de Egressos recebe na hora e analisa com sigilo. <strong>A outra pessoa não fica sabendo quem denunciou.</strong>
                Para denunciar uma mensagem específica, abra a conversa e clique em <i class="fas fa-flag"></i> <strong>Denunciar</strong> embaixo dela.
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
            <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-gray-100 shadow-sm h-fit">
                <h3 class="text-lg font-bold text-slate-900 mb-4"><i class="fas fa-flag text-red-500 mr-2"></i>Fazer uma denúncia</h3>
                <?php if (empty($meusPedidos)): ?>
                    <p class="text-sm text-gray-500">Você ainda não tem nenhuma conversa de mentoria para denunciar.</p>
                <?php else: ?>
                    <form method="POST" action="mentoria.php" class="space-y-4"
                          data-confirm="A Coordenação de Egressos vai receber a denúncia por e-mail. A outra pessoa não será avisada."
                          data-confirm-titulo="Enviar a denúncia?" data-confirm-botao="Enviar denúncia" data-confirm-tipo="perigo" data-confirm-icone="fa-flag">
                        <input type="hidden" name="acao" value="denunciar">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Conversa</label>
                            <select name="pedido" required class="w-full h-11 px-3 border border-gray-200 rounded-xl text-sm bg-white">
                                <?php foreach ($meusPedidos as $p):
                                    [$pTxt] = mentoriaPedidoStatusInfo($p['status'] ?? '');
                                    $outro = $souEgresso ? ($p['alunoNome'] ?? 'Aluno') : ($p['mentorNome'] ?? 'Mentor');
                                ?>
                                    <option value="<?= (int) $p['id'] ?>"><?= egh($outro . ' — ' . ($p['assunto'] ?? '') . ' (' . $pTxt . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Motivo</label>
                            <select name="categoria" required class="w-full h-11 px-3 border border-gray-200 rounded-xl text-sm bg-white">
                                <?php foreach ($catsDen as $k => $rot): ?>
                                    <option value="<?= egh($k) ?>"><?= egh($rot) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">O que aconteceu? (obrigatório)</label>
                            <textarea name="descricao" rows="5" required minlength="10" maxlength="1000"
                                      placeholder="Conte com suas palavras. Se puder, diga quando aconteceu."
                                      class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm"></textarea>
                        </div>
                        <button type="submit" class="w-full h-11 bg-red-500 hover:bg-red-600 text-white rounded-xl text-sm font-semibold">
                            <i class="fas fa-paper-plane mr-1"></i> Enviar denúncia
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="lg:col-span-3">
                <h3 class="text-lg font-bold text-slate-900 mb-4">Minhas denúncias</h3>
                <?php if (empty($minhasDen)): ?>
                    <div class="p-10 text-center border-2 border-dashed border-gray-200 rounded-2xl bg-white text-sm text-gray-400">Você não fez nenhuma denúncia.</div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($minhasDen as $d):
                            [$dTxt, $dCls, $dIco] = mentoriaDenunciaStatusInfo($d['status'] ?? '');
                        ?>
                            <div class="p-5 bg-white border border-gray-100 rounded-2xl shadow-sm">
                                <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                                    <div>
                                        <p class="font-semibold text-slate-800"><?= egh($catsDen[$d['categoria'] ?? 'outro'] ?? 'Outro motivo') ?></p>
                                        <p class="text-xs text-gray-400">Contra <?= egh($d['denunciadoNome'] ?? '') ?> &bull; <?= egh($d['assunto'] ?? '') ?> &bull; <?= egh($d['criadoEm'] ?? '') ?></p>
                                    </div>
                                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold border <?= egh($dCls) ?>"><i class="fas <?= egh($dIco) ?>"></i> <?= egh($dTxt) ?></span>
                                </div>
                                <?php if (!empty($d['mensagemTexto'])): ?>
                                    <p class="text-xs text-gray-500 mb-1">Mensagem denunciada:</p>
                                    <p class="text-sm text-gray-700 bg-red-50 p-3 rounded-xl mb-2 whitespace-pre-line"><?= egh($d['mensagemTexto']) ?></p>
                                <?php endif; ?>
                                <p class="text-sm text-gray-600 whitespace-pre-line"><?= egh($d['descricao'] ?? '') ?></p>
                                <?php if (!empty($d['resposta']) && in_array($d['status'] ?? '', ['resolvida', 'arquivada'], true)): ?>
                                    <p class="text-sm text-slate-700 bg-[#eaf4fa] p-3 rounded-xl mt-3"><strong>Resposta da coordenação:</strong> <?= egh($d['resposta']) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

<?php endif; ?>

    </div>
</main>

<?php /* ===================== CHAT (aluno e mentor) ===================== */ ?>
<div id="modalChat" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg flex flex-col overflow-hidden" style="height: 80vh;">

        <!-- Topo: com quem é a conversa -->
        <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gray-100">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-full bg-[#4A9FCA]/10 text-[#2B7FA6] flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-comments"></i>
                </div>
                <div class="min-w-0">
                    <p id="chatNome" class="font-bold text-slate-900 truncate"></p>
                    <p id="chatAssunto" class="text-xs text-gray-400 truncate"></p>
                </div>
            </div>
            <button type="button" onclick="denunciarMensagem('')" class="h-9 px-3 rounded-full hover:bg-red-50 text-red-500 text-xs font-semibold flex-shrink-0" title="Denunciar esta conversa">
                <i class="fas fa-flag mr-1"></i>Denunciar
            </button>
            <button type="button" onclick="fecharChat()" class="w-9 h-9 rounded-full hover:bg-gray-100 text-gray-500" title="Fechar">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <p class="px-5 py-2 text-[11px] text-gray-400 border-b border-gray-100">
            <i class="fas fa-shield-alt mr-1"></i> Para a segurança de todos, a Coordenação de Egressos pode consultar esta conversa.
        </p>

        <!-- Mensagens -->
        <div id="chatLista" class="flex-1 overflow-y-auto px-4 py-4 space-y-2 bg-gray-50"></div>

        <!-- Escrever -->
        <form id="chatForm" class="flex items-end gap-2 p-3 border-t border-gray-100">
            <textarea id="chatTexto" rows="1" maxlength="1000" placeholder="Escreva uma mensagem (Enter envia, Shift+Enter pula linha)"
                      class="flex-1 px-3 py-2 border border-gray-200 rounded-xl text-sm resize-none focus:ring-2 focus:ring-[#4A9FCA]" style="max-height: 120px;"></textarea>
            <button type="submit" class="h-10 px-4 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl text-sm font-semibold" title="Enviar">
                <i class="fas fa-paper-plane"></i>
            </button>
        </form>
        <p id="chatFechado" class="hidden p-3 border-t border-gray-100 text-center text-xs text-gray-500">
            <i class="fas fa-lock mr-1"></i> Mentoria encerrada: a conversa ficou só para leitura.
        </p>
    </div>
</div>

<script>
/* Chat da mentoria: busca as mensagens a cada 4 segundos enquanto a janela está aberta. */
(function () {
    var pedidoAtual = 0, timer = null, ultimoTs = -1, enviando = false;
    var lista = document.getElementById('chatLista');
    var form  = document.getElementById('chatForm');
    var caixa = document.getElementById('chatTexto');
    var cab   = { 'X-Requested-With': 'fetch' };

    // Desenha as mensagens (texto com textContent: nada de HTML vindo do usuário)
    function desenhar(d) {
        lista.innerHTML = '';
        if (!d.mensagens.length) {
            var vazio = document.createElement('p');
            vazio.className = 'text-center text-sm text-gray-400 py-10';
            vazio.textContent = d.aberto ? 'Nenhuma mensagem ainda. Que tal se apresentar?' : 'Esta conversa não tem mensagens.';
            lista.appendChild(vazio);
            return;
        }
        d.mensagens.forEach(function (m) {
            var linha = document.createElement('div');
            linha.className = 'flex ' + (m.minha ? 'justify-end' : 'justify-start');
            var balao = document.createElement('div');
            balao.className = 'max-w-[80%] px-3 py-2 rounded-2xl text-sm shadow-sm whitespace-pre-line break-words ' +
                (m.minha ? 'bg-[#4A9FCA] text-white rounded-br-md' : 'bg-white text-slate-800 border border-gray-100 rounded-bl-md');
            balao.textContent = m.texto;
            var hora = document.createElement('span');
            hora.className = 'block text-[10px] mt-1 ' + (m.minha ? 'text-white/70 text-right' : 'text-gray-400');
            hora.textContent = m.hora;
            balao.appendChild(hora);
            if (!m.minha && m.id) {                     // denunciar mensagem da outra pessoa
                var den = document.createElement('button');
                den.type = 'button';
                den.className = 'block text-[10px] mt-1 text-gray-400 hover:text-red-500';
                den.innerHTML = '<i class="fas fa-flag mr-1"></i>Denunciar';
                den.onclick = function () { denunciarMensagem(m.id, m.texto); };
                balao.appendChild(den);
            }
            linha.appendChild(balao);
            lista.appendChild(linha);
        });
        lista.scrollTop = lista.scrollHeight;   // rola para a última mensagem
    }

    function mostrar(d) {
        document.getElementById('chatAssunto').textContent = d.assunto ? 'Assunto: ' + d.assunto : '';
        form.classList.toggle('hidden', !d.aberto);
        document.getElementById('chatFechado').classList.toggle('hidden', d.aberto);
        if (d.ultimoTs !== ultimoTs) { ultimoTs = d.ultimoTs; desenhar(d); }   // só redesenha se mudou
    }

    function carregar() {
        if (!pedidoAtual) return;
        fetch('mentoria-chat.php?pedido=' + encodeURIComponent(pedidoAtual), { headers: cab, cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (d) { if (d.ok) mostrar(d); })
            .catch(function () { /* sem internet: tenta de novo no próximo ciclo */ });
    }

    window.abrirChat = function (btn) {
        pedidoAtual = btn.getAttribute('data-pedido');
        ultimoTs = -1;
        document.getElementById('chatNome').textContent = btn.getAttribute('data-nome') || '';
        lista.innerHTML = '<p class="text-center text-sm text-gray-400 py-10">Carregando...</p>';
        var modal = document.getElementById('modalChat');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        var b = btn.querySelector('.chat-badge');       // leu: some a bolinha do botão
        if (b) b.remove();
        carregar();
        clearInterval(timer);
        timer = setInterval(carregar, 4000);
        setTimeout(function () { caixa.focus(); }, 50);
    };

    window.fecharChat = function () {
        var modal = document.getElementById('modalChat');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        clearInterval(timer);
        pedidoAtual = 0;
        if (window.fsaAtualizarBolinhaMentoria) window.fsaAtualizarBolinhaMentoria();
    };

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var texto = caixa.value.trim();
        if (!texto || enviando) return;
        enviando = true;
        var dados = new FormData();
        dados.append('pedido', pedidoAtual);
        dados.append('texto', texto);
        fetch('mentoria-chat.php', { method: 'POST', headers: cab, body: dados })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) { if (window.fsaAviso) fsaAviso(d.erro || 'Não foi possível enviar.', 'Ops'); return; }
                caixa.value = '';
                ultimoTs = -1;
                mostrar(d);
            })
            .catch(function () { if (window.fsaAviso) fsaAviso('Sem conexão. Tente de novo.', 'Ops'); })
            .then(function () { enviando = false; caixa.focus(); });
    });

    // Enter envia; Shift+Enter pula linha
    caixa.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (form.requestSubmit) form.requestSubmit(); else form.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (!document.getElementById('modalDenuncia').classList.contains('hidden')) { fecharDenuncia(); return; }
        if (pedidoAtual) fecharChat();
    });

    /* ---------- Denúncia (mensagem ou conversa inteira) ---------- */
    var denMensagem = '';
    window.denunciarMensagem = function (idMensagem, texto) {
        denMensagem = idMensagem || '';
        document.getElementById('denTitulo').textContent = denMensagem ? 'Denunciar mensagem' : 'Denunciar esta conversa';
        var cit = document.getElementById('denCitacao');
        cit.classList.toggle('hidden', !denMensagem);
        cit.textContent = texto || '';
        document.getElementById('denDescricao').value = '';
        document.getElementById('denErro').classList.add('hidden');
        var m = document.getElementById('modalDenuncia'); m.classList.remove('hidden'); m.classList.add('flex');
        setTimeout(function () { document.getElementById('denDescricao').focus(); }, 50);
    };
    window.fecharDenuncia = function () {
        var m = document.getElementById('modalDenuncia'); m.classList.add('hidden'); m.classList.remove('flex');
    };
    window.enviarDenuncia = function () {
        var desc = document.getElementById('denDescricao').value.trim();
        var erro = document.getElementById('denErro');
        if (desc.length < 10) { erro.textContent = 'Conte o que aconteceu (pelo menos 10 caracteres).'; erro.classList.remove('hidden'); return; }
        var dados = new FormData();
        dados.append('acao', 'denunciar');
        dados.append('pedido', pedidoAtual);
        dados.append('mensagem', denMensagem);
        dados.append('categoria', document.getElementById('denCategoria').value);
        dados.append('descricao', desc);
        fetch('mentoria-chat.php', { method: 'POST', headers: cab, body: dados })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) { erro.textContent = d.erro || 'Não foi possível enviar.'; erro.classList.remove('hidden'); return; }
                fecharDenuncia();
                if (window.fsaAviso) fsaAviso('A coordenação foi avisada e vai analisar. A outra pessoa não sabe quem denunciou. Acompanhe na aba Denúncias.', 'Denúncia enviada');
            })
            .catch(function () { erro.textContent = 'Sem conexão. Tente de novo.'; erro.classList.remove('hidden'); });
    };
})();
</script>

<?php /* ===================== DENÚNCIA (aberta pelo chat) ===================== */ ?>
<div id="modalDenuncia" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md p-7">
        <div class="flex items-center justify-between mb-4">
            <h3 id="denTitulo" class="text-lg font-bold text-slate-900"><i class="fas fa-flag text-red-500 mr-2"></i>Denunciar</h3>
            <button type="button" onclick="fecharDenuncia()" class="w-9 h-9 rounded-full hover:bg-gray-100 text-gray-500"><i class="fas fa-times"></i></button>
        </div>
        <p id="denCitacao" class="hidden text-sm text-gray-700 bg-red-50 p-3 rounded-xl mb-4 whitespace-pre-line max-h-32 overflow-y-auto"></p>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Motivo</label>
        <select id="denCategoria" class="w-full h-11 px-3 border border-gray-200 rounded-xl text-sm bg-white mb-4">
            <?php foreach (mentoriaDenunciaCategorias() as $k => $rot): ?>
                <option value="<?= egh($k) ?>"><?= egh($rot) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">O que aconteceu? (obrigatório)</label>
        <textarea id="denDescricao" rows="4" maxlength="1000" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm"
                  placeholder="Conte com suas palavras. A outra pessoa não fica sabendo quem denunciou."></textarea>
        <p id="denErro" class="hidden text-xs text-red-600 mt-1"></p>
        <div class="flex gap-3 mt-5">
            <button type="button" onclick="fecharDenuncia()" class="flex-1 h-11 border border-gray-200 rounded-xl text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
            <button type="button" onclick="enviarDenuncia()" class="flex-1 h-11 bg-red-500 hover:bg-red-600 text-white rounded-xl text-sm font-semibold"><i class="fas fa-flag mr-1"></i>Enviar denúncia</button>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
