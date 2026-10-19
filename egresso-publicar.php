<?php
/**
 * Egresso divulga uma vaga de estagio/emprego.
 * Mesma logica das solicitacoes de vaga dos alunos:
 * enviada -> pendente -> o admin de egressos aprova (publica) ou rejeita (com motivo).
 */
require_once 'includes/config.php';
require_once 'includes/egressos.php';
checkAuth();

if (!isEgresso()) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Divulgar Vaga';
$userId    = (int) $_SESSION['user_id'];
$egresso   = egressoCarregarUsuario($userId);
if (!$egresso) {
    header('Location: index.php?logout=1');
    exit;
}

$fsaCourses  = getFsaCourses();
$tiposVaga   = ['Presencial', 'Híbrido', 'Remoto'];
$erro        = '';
$vagas       = egressoVagas();
$camposTexto = ['title', 'company', 'categoria', 'type', 'location', 'hours', 'salary', 'description', 'requirements', 'benefits', 'applicationLink', 'expiresAt'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['action'] ?? '';

    /* ---------- publicar / reenviar ---------- */
    if ($acao === 'publicar') {
        $cursosSel = array_values(array_intersect((array) ($_POST['courses'] ?? []), $fsaCourses));
        $link      = egressoNormalizarLink($_POST['applicationLink'] ?? '');
        $categoria = in_array($_POST['categoria'] ?? '', egressoCategorias(), true) ? $_POST['categoria'] : 'Estágio';
        $tipo      = in_array($_POST['type'] ?? '', $tiposVaga, true) ? $_POST['type'] : 'Presencial';

        $validade  = vagaNormalizarValidade($_POST['expiresAt'] ?? '', 180);

        $dados = [
            'title'           => mb_substr(trim($_POST['title'] ?? ''), 0, 150),
            'company'         => mb_substr(trim($_POST['company'] ?? ''), 0, 150),
            'categoria'       => $categoria,
            'type'            => $tipo,
            'location'        => mb_substr(trim($_POST['location'] ?? ''), 0, 150),
            'hours'           => mb_substr(trim($_POST['hours'] ?? ''), 0, 60),
            'salary'          => mb_substr(trim($_POST['salary'] ?? ''), 0, 60),
            'description'     => mb_substr(trim($_POST['description'] ?? ''), 0, 3000),
            'requirements'    => mb_substr(trim($_POST['requirements'] ?? ''), 0, 2000),
            'benefits'        => mb_substr(trim($_POST['benefits'] ?? ''), 0, 2000),
            'courses'         => implode(', ', $cursosSel),
            'applicationLink' => $link,
            'expiresAt'       => $validade,
        ];

        if ($dados['title'] === '' || $dados['company'] === '') {
            $erro = 'Preencha o título da vaga e o nome da empresa.';
        } elseif ($validade === '') {
            $erro = 'Informe até quando a vaga fica aberta (a partir de hoje, no máximo 180 dias).';
        } elseif ($link === '') {
            $erro = 'Informe um link (https://...) ou um e-mail válido para as candidaturas.';
        } else {
            $editId = (int) ($_POST['edit_id'] ?? 0);
            $editou = false;

            if ($editId > 0) {
                foreach ($vagas as &$v) {
                    if (($v['id'] ?? 0) == $editId && ($v['egressoId'] ?? 0) == $userId && ($v['status'] ?? '') === 'rejeitada') {
                        $v = array_merge($v, $dados, [
                            'status'         => 'pendente',
                            'motivoRejeicao' => '',
                            'createdAt'      => date('d/m/Y H:i'),
                            'createdTs'      => time(),
                        ]);
                        $editou = true;
                        break;
                    }
                }
                unset($v);
            }

            if (!$editou) {
                array_unshift($vagas, array_merge($dados, [
                    'id'             => egressoNovoId(),
                    'egressoId'      => $userId,
                    'egressoName'    => $egresso['nome'] ?? '',
                    'egressoCurso'   => $egresso['curso'] ?? '',
                    'egressoAno'     => $egresso['anoConclusao'] ?? '',
                    'status'         => 'pendente',
                    'motivoRejeicao' => '',
                    'applicants'     => 0,
                    'createdAt'      => date('d/m/Y H:i'),
                    'createdTs'      => time(),
                ]));
            }

            egressoSalvarVagas($vagas);
            notificarAdminsVagaEgresso(array_merge($dados, ['egressoName' => $egresso['nome'] ?? '']));
            header('Location: egresso-publicar.php?enviado=1');
            exit;
        }
    }

    /* ---------- renovar (mais 30 dias) ---------- */
    if ($acao === 'renovar') {
        $id = (int) ($_POST['id'] ?? 0);
        foreach ($vagas as &$v) {
            if (($v['id'] ?? 0) == $id && ($v['egressoId'] ?? 0) == $userId && ($v['status'] ?? '') === 'aprovada') {
                $base  = max(date('Y-m-d'), (string) ($v['expiresAt'] ?? ''));
                $novo  = date('Y-m-d', strtotime($base . ' +30 days'));
                $limite = date('Y-m-d', strtotime('+180 days'));
                $v['expiresAt'] = min($novo, $limite);
                break;
            }
        }
        unset($v);
        egressoSalvarVagas($vagas);
        header('Location: egresso-publicar.php?renovada=1');
        exit;
    }

    /* ---------- encerrar (vaga preenchida) ---------- */
    if ($acao === 'encerrar') {
        $id = (int) ($_POST['id'] ?? 0);
        foreach ($vagas as &$v) {
            if (($v['id'] ?? 0) == $id && ($v['egressoId'] ?? 0) == $userId && ($v['status'] ?? '') === 'aprovada') {
                $v['status']      = 'encerrada';
                $v['encerradaEm'] = date('d/m/Y H:i');
                break;
            }
        }
        unset($v);
        egressoSalvarVagas($vagas);
        header('Location: egresso-publicar.php?encerrada=1');
        exit;
    }

    /* ---------- excluir (so se nao estiver publicada) ---------- */
    if ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        $vagas = array_values(array_filter($vagas, function ($v) use ($id, $userId) {
            $ehEssa = ($v['id'] ?? 0) == $id && ($v['egressoId'] ?? 0) == $userId && ($v['status'] ?? '') !== 'aprovada';
            return !$ehEssa;
        }));
        egressoSalvarVagas($vagas);
        header('Location: egresso-publicar.php?excluida=1');
        exit;
    }
}

/* ---------- minhas divulgacoes ---------- */
$minhas = array_values(array_filter($vagas, fn($v) => ($v['egressoId'] ?? 0) == $userId));
usort($minhas, fn($a, $b) => ($b['createdTs'] ?? 0) <=> ($a['createdTs'] ?? 0));

/* ---------- valores do formulario (novo, edicao ou erro) ---------- */
$form = [
    'edit_id' => 0, 'title' => '', 'company' => '', 'categoria' => 'Estágio', 'type' => 'Presencial',
    'location' => '', 'hours' => '', 'salary' => '', 'description' => '', 'requirements' => '',
    'benefits' => '', 'applicationLink' => '', 'courses' => [], 'expiresAt' => vagaValidadePadrao(45),
];

if ($erro !== '') {
    foreach ($camposTexto as $k) {
        $form[$k] = (string) ($_POST[$k] ?? '');
    }
    $form['courses'] = array_values(array_intersect((array) ($_POST['courses'] ?? []), $fsaCourses));
    $form['edit_id'] = (int) ($_POST['edit_id'] ?? 0);
} elseif (isset($_GET['editar'])) {
    $editar = (int) $_GET['editar'];
    foreach ($minhas as $v) {
        if (($v['id'] ?? 0) == $editar && ($v['status'] ?? '') === 'rejeitada') {
            foreach ($camposTexto as $k) {
                $form[$k] = (string) ($v[$k] ?? $form[$k]);
            }
            $form['courses'] = array_filter(array_map('trim', explode(',', $v['courses'] ?? '')));
            $form['edit_id'] = $editar;
            break;
        }
    }
}

if ($form['expiresAt'] === '' || $form['expiresAt'] < date('Y-m-d')) $form['expiresAt'] = vagaValidadePadrao(45);

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-7xl mx-auto">

        <div class="mb-8 animate-fade-in">
            <h2 class="text-3xl font-bold text-slate-900 mb-2">Divulgar uma vaga</h2>
            <p class="text-slate-500 font-medium">Sua vaga é analisada pela coordenação e, depois de aprovada, aparece para todos os alunos.</p>
        </div>

        <?php if (isset($_GET['enviado'])): ?>
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm">
                <i class="fas fa-check-circle mr-1"></i> Vaga enviada! Ela aparece no mural assim que for aprovada.
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['encerrada'])): ?>
            <div class="mb-6 p-4 bg-gray-100 border border-gray-200 text-gray-700 rounded-xl text-sm">Vaga encerrada. Ela saiu do mural.</div>
        <?php endif; ?>
        <?php if (isset($_GET['renovada'])): ?>
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm">Vaga renovada por mais 30 dias.</div>
        <?php endif; ?>
        <?php if (isset($_GET['excluida'])): ?>
            <div class="mb-6 p-4 bg-gray-100 border border-gray-200 text-gray-700 rounded-xl text-sm">Divulgação excluída.</div>
        <?php endif; ?>
        <?php if ($erro !== ''): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
                <i class="fas fa-exclamation-circle mr-1"></i> <?= egh($erro) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">

            <div class="lg:col-span-3 bg-white p-8 rounded-2xl border border-gray-100 shadow-sm">
                <h3 class="text-xl font-bold text-slate-900 mb-6">
                    <?= $form['edit_id'] ? 'Corrigir e reenviar vaga' : 'Nova vaga' ?>
                </h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="publicar">
                    <input type="hidden" name="edit_id" value="<?= (int) $form['edit_id'] ?>">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Título da vaga *</label>
                            <input type="text" name="title" required maxlength="150" value="<?= egh($form['title']) ?>" class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Empresa *</label>
                            <input type="text" name="company" required maxlength="150" value="<?= egh($form['company']) ?>" class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de oportunidade</label>
                            <select name="categoria" class="w-full h-11 px-3 border border-gray-200 rounded-lg bg-white">
                                <?php foreach (egressoCategorias() as $cat): ?>
                                    <option value="<?= egh($cat) ?>" <?= $cat === $form['categoria'] ? 'selected' : '' ?>><?= egh($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Modalidade</label>
                            <select name="type" class="w-full h-11 px-3 border border-gray-200 rounded-lg bg-white">
                                <?php foreach ($tiposVaga as $t): ?>
                                    <option value="<?= egh($t) ?>" <?= $t === $form['type'] ? 'selected' : '' ?>><?= egh($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Local</label>
                            <input type="text" name="location" maxlength="150" placeholder="Ex: São Paulo, SP" value="<?= egh($form['location']) ?>" class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Carga horária</label>
                            <input type="text" name="hours" maxlength="60" placeholder="Ex: 6h/dia" value="<?= egh($form['hours']) ?>" class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Remuneração</label>
                            <input type="text" name="salary" maxlength="60" placeholder="Ex: R$ 2.000,00 ou A combinar" value="<?= egh($form['salary']) ?>" class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vaga aberta até *</label>
                        <input type="date" name="expiresAt" required min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+180 days')) ?>"
                               value="<?= egh($form['expiresAt']) ?>" class="w-full md:w-60 h-11 px-3 border border-gray-200 rounded-lg">
                        <p class="text-xs text-gray-400 mt-1">Depois dessa data a vaga sai do mural sozinha (máximo 180 dias). Você pode renovar depois.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                        <textarea name="description" rows="3" maxlength="3000" class="w-full px-3 py-2 border border-gray-200 rounded-lg"><?= egh($form['description']) ?></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Requisitos</label>
                            <textarea name="requirements" rows="3" maxlength="2000" class="w-full px-3 py-2 border border-gray-200 rounded-lg"><?= egh($form['requirements']) ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Benefícios</label>
                            <textarea name="benefits" rows="3" maxlength="2000" class="w-full px-3 py-2 border border-gray-200 rounded-lg"><?= egh($form['benefits']) ?></textarea>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Cursos indicados <span class="text-xs text-gray-400 font-normal">(deixe em branco para todos)</span>
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-1 max-h-48 overflow-y-auto p-3 bg-gray-50 rounded-lg">
                            <?php foreach ($fsaCourses as $c): ?>
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="courses[]" value="<?= egh($c) ?>" <?= in_array($c, (array) $form['courses'], true) ? 'checked' : '' ?>>
                                    <?= egh($c) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Link ou e-mail para candidatura *</label>
                        <input type="text" name="applicationLink" required placeholder="https://empresa.com/vaga ou rh@empresa.com" value="<?= egh($form['applicationLink']) ?>" class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="px-8 h-12 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl font-semibold">
                            <i class="fas fa-paper-plane mr-1"></i> <?= $form['edit_id'] ? 'Reenviar para análise' : 'Enviar para análise' ?>
                        </button>
                        <?php if ($form['edit_id']): ?>
                            <a href="egresso-publicar.php" class="px-6 h-12 inline-flex items-center border border-gray-200 text-gray-600 rounded-xl hover:bg-gray-50">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="lg:col-span-2">
                <h3 class="text-xl font-bold text-slate-900 mb-4">Minhas divulgações</h3>

                <?php if (empty($minhas)): ?>
                    <div class="p-8 text-center border-2 border-dashed border-gray-200 rounded-2xl bg-white text-slate-500 text-sm">
                        Você ainda não divulgou nenhuma vaga.
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($minhas as $v):
                            $st   = egressoVagaStatusEfetivo($v);
                            $info = egressoVagaStatusInfo($st);
                        ?>
                            <div class="p-5 bg-white border border-gray-100 rounded-2xl shadow-sm">
                                <div class="flex items-start justify-between gap-3 mb-2">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-800 truncate"><?= egh($v['title'] ?? '') ?></p>
                                        <p class="text-sm text-gray-500 truncate"><?= egh($v['company'] ?? '') ?> &bull; <?= egh($v['createdAt'] ?? '') ?></p>
                                        <?= vagaValidadeHtml($v) ?>
                                    </div>
                                    <span class="shrink-0 px-3 py-1 rounded-full text-xs font-bold border <?= $info[1] ?>">
                                        <i class="fas <?= $info[2] ?> mr-1"></i><?= $info[0] ?>
                                    </span>
                                </div>

                                <?php if ($st === 'rejeitada' && !empty($v['motivoRejeicao'])): ?>
                                    <p class="text-sm text-red-600 bg-red-50 p-3 rounded-lg mb-3">
                                        <strong>Motivo:</strong> <?= egh($v['motivoRejeicao']) ?>
                                    </p>
                                <?php endif; ?>

                                <?php if ($st === 'aprovada'): ?>
                                    <p class="text-xs text-gray-500 mb-3"><i class="fas fa-users mr-1"></i> <?= (int) ($v['applicants'] ?? 0) ?> candidatura(s) de alunos</p>
                                <?php endif; ?>

                                <div class="flex flex-wrap gap-2">
                                    <?php if ($st === 'rejeitada'): ?>
                                        <a href="egresso-publicar.php?editar=<?= (int) $v['id'] ?>" class="px-3 py-1.5 bg-[#4A9FCA] text-white rounded-lg text-sm">
                                            <i class="fas fa-edit mr-1"></i> Corrigir e reenviar
                                        </a>
                                    <?php endif; ?>

                                    <?php if (in_array($st, ['aprovada', 'expirada'], true)): ?>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="renovar">
                                            <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                                            <button class="px-3 py-1.5 bg-[#4A9FCA] text-white rounded-lg text-sm">
                                                <i class="fas fa-redo mr-1"></i> Renovar +30 dias
                                            </button>
                                        </form>
                                        <form method="POST" data-confirm="A vaga sai do mural e deixa de aparecer para os alunos." data-confirm-titulo="Encerrar esta vaga?" data-confirm-botao="Encerrar" data-confirm-tipo="aviso" data-confirm-icone="fa-ban">
                                            <input type="hidden" name="action" value="encerrar">
                                            <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                                            <button class="px-3 py-1.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">
                                                <i class="fas fa-lock mr-1"></i> Encerrar (vaga preenchida)
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" data-confirm="A divulgação será excluída. Esta ação não pode ser desfeita." data-confirm-titulo="Excluir esta divulgação?" data-confirm-botao="Excluir" data-confirm-tipo="perigo" data-confirm-icone="fa-trash-alt">
                                            <input type="hidden" name="action" value="excluir">
                                            <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                                            <button class="px-3 py-1.5 text-red-500 hover:bg-red-50 rounded-lg text-sm">
                                                <i class="fas fa-trash mr-1"></i> Excluir
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
