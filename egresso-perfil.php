<?php
/**
 * Perfil do egresso: curso, ano de conclusao e situacao profissional atual.
 * Esses dados alimentam o painel do admin de egressos.
 */
require_once 'includes/config.php';
require_once 'includes/egressos.php';
checkAuth();

if (!isEgresso()) {
    header('Location: profile.php');
    exit;
}

$pageTitle    = 'Meu Perfil de Egresso';
$userId       = (int) $_SESSION['user_id'];
$egresso      = egressoCarregarUsuario($userId);
if (!$egresso) {
    header('Location: index.php?logout=1');
    exit;
}

$fsaCourses   = getFsaCourses();
$opcoesStatus = egressoStatusOptions();
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $curso    = trim($_POST['curso'] ?? '');
    $ano      = trim($_POST['anoConclusao'] ?? '');
    $status   = trim($_POST['statusProfissional'] ?? '');
    $linkedin = trim($_POST['linkedin'] ?? '');
    $meses    = trim($_POST['mesesAteColocacao'] ?? '');

    if (!in_array($curso, $fsaCourses, true)) {
        $erro = 'Selecione o curso que você fez na FSA.';
    } elseif (!preg_match('/^\d{4}$/', $ano) || (int) $ano < 1950 || (int) $ano > (int) date('Y') + 1) {
        $erro = 'Informe um ano de conclusão válido (ex: 2022).';
    } elseif ($status !== '' && !isset($opcoesStatus[$status])) {
        $erro = 'Situação profissional inválida.';
    } elseif ($linkedin !== '') {
        $linkedin = egressoNormalizarLink($linkedin);
        if ($linkedin === '' || stripos($linkedin, 'mailto:') === 0) {
            $erro = 'O link do LinkedIn não parece válido.';
        }
    }

    /* foto de perfil (opcional) */
    $novaFoto = null;
    if ($erro === '' && isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        $arq = $_FILES['foto_perfil'];
        $ext = strtolower(pathinfo($arq['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $erro = 'A foto precisa ser JPG, PNG, WEBP ou GIF.';
        } elseif ($arq['size'] > 5 * 1024 * 1024) {
            $erro = 'A foto pode ter no máximo 5MB.';
        } elseif (@getimagesize($arq['tmp_name']) === false) {
            $erro = 'O arquivo enviado não é uma imagem válida.';
        } else {
            $dir = "uploads/profile/{$userId}/";
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $destino = $dir . 'foto_' . time() . '.' . $ext;
            if (move_uploaded_file($arq['tmp_name'], $destino)) {
                $novaFoto = $destino;
            } else {
                $erro = 'Não foi possível salvar a foto. Tente novamente.';
            }
        }
    }

    if ($erro === '') {
        $campos = [
            'curso'              => $curso,
            'anoConclusao'       => $ano,
            'statusProfissional' => $status,
            'empresaAtual'       => mb_substr(trim($_POST['empresaAtual'] ?? ''), 0, 120),
            'cargoAtual'         => mb_substr(trim($_POST['cargoAtual'] ?? ''), 0, 120),
            'areaAtuacao'        => mb_substr(trim($_POST['areaAtuacao'] ?? ''), 0, 120),
            'cidade'             => mb_substr(trim($_POST['cidade'] ?? ''), 0, 120),
            'telefone'           => mb_substr(trim($_POST['telefone'] ?? ''), 0, 30),
            'linkedin'           => $linkedin,
            'mesesAteColocacao'  => $meses === '' ? null : max(0, min(240, (int) $meses)),
            'aceitaMentoria'     => isset($_POST['aceitaMentoria']),
            'bio'                => mb_substr(trim($_POST['bio'] ?? ''), 0, 600),
            'perfilAtualizadoEm' => date('Y-m-d'),
        ];
        egressoAtualizarUsuario($userId, $campos);

        if ($novaFoto) {
            $up = loadData('userProfile', true);
            $up['foto'] = $novaFoto;
            saveData('userProfile', $up, true);
        }
        header('Location: egresso-perfil.php?salvo=1');
        exit;
    }

    /* erro: mantem o que a pessoa digitou */
    $egresso = array_merge($egresso, [
        'curso' => $curso, 'anoConclusao' => $ano, 'statusProfissional' => $status,
        'empresaAtual' => $_POST['empresaAtual'] ?? '', 'cargoAtual' => $_POST['cargoAtual'] ?? '',
        'areaAtuacao' => $_POST['areaAtuacao'] ?? '', 'cidade' => $_POST['cidade'] ?? '',
        'telefone' => $_POST['telefone'] ?? '', 'linkedin' => $_POST['linkedin'] ?? '',
        'mesesAteColocacao' => $meses === '' ? null : $meses,
        'aceitaMentoria' => isset($_POST['aceitaMentoria']), 'bio' => $_POST['bio'] ?? '',
    ]);
}

/* foto e iniciais */
$userProfile = loadData('userProfile', true);
$foto     = $userProfile['foto'] ?? null;
$temFoto  = $foto && file_exists(__DIR__ . '/' . $foto);
$nome     = $egresso['nome'] ?? 'Egresso';
$partes   = explode(' ', trim($nome));
$iniciais = strtoupper(substr($partes[0], 0, 1)) . (count($partes) > 1 ? strtoupper(substr(end($partes), 0, 1)) : '');

/* completude do perfil */
$camposCompletude = ['curso', 'anoConclusao', 'statusProfissional', 'empresaAtual', 'cargoAtual', 'areaAtuacao', 'cidade', 'linkedin', 'bio'];
$preenchidos = 0;
foreach ($camposCompletude as $k) {
    if (!empty($egresso[$k])) $preenchidos++;
}
$pctPerfil = (int) round($preenchidos / count($camposCompletude) * 100);

/* resumo das minhas divulgacoes */
$minhasVagas = array_filter(egressoVagas(), fn($v) => ($v['egressoId'] ?? 0) == $userId);
$vagasAtivas = count(array_filter($minhasVagas, fn($v) => ($v['status'] ?? '') === 'aprovada'));
$candidatos  = array_sum(array_map(fn($v) => (int) ($v['applicants'] ?? 0), $minhasVagas));

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-7xl mx-auto">

        <?php if (isset($_GET['salvo'])): ?>
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm">
                <i class="fas fa-check-circle mr-1"></i> Perfil atualizado. Obrigado por manter seus dados em dia!
            </div>
        <?php endif; ?>
        <?php if ($erro !== ''): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
                <i class="fas fa-exclamation-circle mr-1"></i> <?= egh($erro) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <div class="space-y-6">
                <div class="bg-white p-8 rounded-2xl border border-gray-100 shadow-sm text-center">
                    <?php if ($temFoto): ?>
                        <img src="<?= egh($foto) ?>" alt="Foto" class="w-28 h-28 rounded-full object-cover border-4 border-[#4A9FCA] mx-auto mb-4">
                    <?php else: ?>
                        <div class="w-28 h-28 bg-gradient-to-br from-[#4A9FCA] to-[#3A8FB0] rounded-full flex items-center justify-center text-white font-bold text-3xl mx-auto mb-4">
                            <?= egh($iniciais) ?>
                        </div>
                    <?php endif; ?>
                    <h2 class="text-xl font-bold text-slate-900"><?= egh($nome) ?></h2>
                    <p class="text-sm text-gray-500 mb-3"><?= egh($egresso['email'] ?? '') ?></p>
                    <span class="inline-flex items-center gap-1 px-3 py-1 bg-purple-50 text-purple-700 rounded-full text-xs font-bold">
                        <i class="fas fa-user-graduate"></i> Egresso(a) FSA
                    </span>
                    <?php if (!empty($egresso['curso'])): ?>
                        <p class="mt-4 text-sm text-slate-700 font-semibold"><?= egh($egresso['curso']) ?></p>
                        <?php if (!empty($egresso['anoConclusao'])): ?>
                            <p class="text-xs text-gray-500">Turma de <?= egh($egresso['anoConclusao']) ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <div class="flex justify-between text-sm mb-2">
                        <span class="font-semibold text-gray-700">Perfil completo</span>
                        <span class="font-bold text-[#4A9FCA]"><?= $pctPerfil ?>%</span>
                    </div>
                    <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-2 bg-[#4A9FCA] rounded-full" style="width: <?= $pctPerfil ?>%"></div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <h3 class="font-bold text-slate-900 mb-3">Minhas divulgações</h3>
                    <div class="flex justify-between text-sm py-1"><span class="text-gray-500">Vagas no ar</span><strong><?= $vagasAtivas ?></strong></div>
                    <div class="flex justify-between text-sm py-1"><span class="text-gray-500">Candidaturas de alunos</span><strong><?= $candidatos ?></strong></div>
                    <a href="egresso-publicar.php" class="mt-4 block text-center px-4 py-2 bg-[#4A9FCA] text-white rounded-xl text-sm font-semibold">
                        <i class="fas fa-plus-circle mr-1"></i> Divulgar uma vaga
                    </a>
                </div>
            </div>

            <div class="lg:col-span-2 bg-white p-8 rounded-2xl border border-gray-100 shadow-sm">
                <h3 class="text-xl font-bold text-slate-900 mb-1">Dados de egresso</h3>
                <p class="text-sm text-slate-500 mb-6">A FSA usa essas informações para acompanhar a trajetória dos ex-alunos.</p>

                <form method="POST" enctype="multipart/form-data" class="space-y-5">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Curso feito na FSA *</label>
                            <select name="curso" required class="w-full h-11 px-3 border border-gray-200 rounded-lg bg-white">
                                <option value="">Selecione...</option>
                                <?php foreach ($fsaCourses as $c): ?>
                                    <option value="<?= egh($c) ?>" <?= ($egresso['curso'] ?? '') === $c ? 'selected' : '' ?>><?= egh($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ano de conclusão *</label>
                            <input type="text" name="anoConclusao" required maxlength="4" inputmode="numeric" placeholder="2022"
                                   value="<?= egh($egresso['anoConclusao'] ?? '') ?>" class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Situação profissional atual</label>
                        <select name="statusProfissional" class="w-full h-11 px-3 border border-gray-200 rounded-lg bg-white">
                            <option value="">Prefiro não informar</option>
                            <?php foreach ($opcoesStatus as $valor => $rotulo): ?>
                                <option value="<?= egh($valor) ?>" <?= ($egresso['statusProfissional'] ?? '') === $valor ? 'selected' : '' ?>><?= egh($rotulo) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Empresa atual</label>
                            <input type="text" name="empresaAtual" maxlength="120" value="<?= egh($egresso['empresaAtual'] ?? '') ?>" class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cargo atual</label>
                            <input type="text" name="cargoAtual" maxlength="120" value="<?= egh($egresso['cargoAtual'] ?? '') ?>" class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Área de atuação</label>
                            <input type="text" name="areaAtuacao" maxlength="120" placeholder="Ex: Dados, Manufatura, RH" value="<?= egh($egresso['areaAtuacao'] ?? '') ?>" class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cidade</label>
                            <input type="text" name="cidade" maxlength="120" value="<?= egh($egresso['cidade'] ?? '') ?>" class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">LinkedIn</label>
                            <input type="text" name="linkedin" placeholder="linkedin.com/in/seu-perfil" value="<?= egh($egresso['linkedin'] ?? '') ?>" class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
                            <input type="text" name="telefone" maxlength="30" value="<?= egh($egresso['telefone'] ?? '') ?>" class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Quantos meses levou até a primeira colocação na área depois de formado?
                                <span class="text-xs text-gray-400 font-normal">(opcional)</span>
                            </label>
                            <input type="number" name="mesesAteColocacao" min="0" max="240"
                                   value="<?= egh($egresso['mesesAteColocacao'] ?? '') ?>" class="w-full md:w-48 h-11 px-3 border border-gray-200 rounded-lg">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sobre você <span class="text-xs text-gray-400 font-normal">(até 600 caracteres)</span></label>
                        <textarea name="bio" rows="3" maxlength="600" class="w-full px-3 py-2 border border-gray-200 rounded-lg"><?= egh($egresso['bio'] ?? '') ?></textarea>
                    </div>

                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="aceitaMentoria" class="mt-1" <?= !empty($egresso['aceitaMentoria']) ? 'checked' : '' ?>>
                        <span>Aceito conversar com alunos atuais sobre carreira (mentoria). Para aparecer para os alunos, faça o cadastro em <a href="mentoria.php?aba=config" class="text-[#4A9FCA] underline">Mentoria</a>.</span>
                    </label>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Foto de perfil <span class="text-xs text-gray-400 font-normal">(opcional, até 5MB)</span></label>
                        <input type="file" name="foto_perfil" accept=".jpg,.jpeg,.png,.webp,.gif" class="text-sm">
                    </div>

                    <button type="submit" class="px-8 h-12 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl font-semibold">
                        <i class="fas fa-save mr-1"></i> Salvar perfil
                    </button>
                </form>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
