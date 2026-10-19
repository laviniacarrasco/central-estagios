<?php
/**
 * Cadastro de EMPRESAS PARCEIRAS (admin).
 * Os alunos veem as empresas ativas em empresas.php.
 */
require_once 'includes/config.php';
require_once 'includes/empresas.php';
checkAdmin();

$pageTitle  = 'Empresas Parceiras (Admin)';
$fsaCourses = getFsaCourses();

$mensagens = [
    'criada'      => 'Empresa cadastrada.',
    'salva'       => 'Empresa atualizada.',
    'ativada'     => 'Empresa ativada: os alunos já enxergam.',
    'desativada'  => 'Empresa desativada: os alunos não enxergam mais.',
    'excluida'    => 'Empresa excluída.',
];
$erros = [
    'nome'            => 'Informe o nome da empresa.',
    'site'            => 'O endereço do site não parece válido.',
    'email'           => 'O e-mail de contato não parece válido.',
    'logo'            => 'A logo precisa ser JPG, PNG, WEBP ou GIF de até 2MB.',
    'nao_encontrada'  => 'Empresa não encontrada.',
];

function empresasAdminVoltar(string $chave, bool $erro = false, int $editar = 0): void {
    $url = 'admin-empresas.php?' . ($erro ? 'err=' : 'msg=') . $chave;
    if ($editar > 0) $url .= '&editar=' . $editar;
    header('Location: ' . $url);
    exit;
}

/* =========================================================================
 * ACOES (POST)
 * ========================================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');
    $id   = (int) ($_POST['id'] ?? 0);

    if ($acao === 'salvar') {
        $nome      = mb_substr(trim((string) ($_POST['nome'] ?? '')), 0, 100);
        $setor     = mb_substr(trim((string) ($_POST['setor'] ?? '')), 0, 80);
        $descricao = mb_substr(trim((string) ($_POST['descricao'] ?? '')), 0, 500);
        $siteRaw   = trim((string) ($_POST['site'] ?? ''));
        $emailRh   = trim((string) ($_POST['email'] ?? ''));
        $ativa     = isset($_POST['ativa']);
        $cursosSel = array_values(array_intersect((array) ($_POST['cursos'] ?? []), $fsaCourses));
        $cursos    = implode(', ', $cursosSel);

        if ($nome === '') empresasAdminVoltar('nome', true, $id);

        $site = '';
        if ($siteRaw !== '') {
            $site = egressoNormalizarLink($siteRaw);
            if ($site === '' || stripos($site, 'mailto:') === 0) empresasAdminVoltar('site', true, $id);
        }
        if ($emailRh !== '' && !filter_var($emailRh, FILTER_VALIDATE_EMAIL)) empresasAdminVoltar('email', true, $id);

        /* logo (opcional) */
        $logoNovo = null;
        if (isset($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $arq = $_FILES['logo'];
            $ext = strtolower(pathinfo((string) $arq['name'], PATHINFO_EXTENSION));
            $okExt = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
            if (!$okExt || $arq['size'] > 2 * 1024 * 1024 || @getimagesize($arq['tmp_name']) === false) {
                empresasAdminVoltar('logo', true, $id);
            }
            $dir = 'uploads/empresas/';
            if (!is_dir($dir)) @mkdir($dir, 0777, true);
            $destino = $dir . 'logo_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            if (!move_uploaded_file($arq['tmp_name'], $destino)) empresasAdminVoltar('logo', true, $id);
            $logoNovo = $destino;
        }

        $lista = empresasLista();
        $idx = null;
        if ($id > 0) {
            foreach ($lista as $k => $e) {
                if ((int) ($e['id'] ?? 0) === $id) { $idx = $k; break; }
            }
        }

        $dados = [
            'nome' => $nome, 'setor' => $setor, 'site' => $site, 'descricao' => $descricao,
            'cursos' => $cursos, 'email' => $emailRh, 'ativa' => $ativa,
        ];

        if ($idx !== null) {
            if ($logoNovo !== null) {
                empresasApagarLogo($lista[$idx]['logo'] ?? '');
                $dados['logo'] = $logoNovo;
            }
            $lista[$idx] = array_merge($lista[$idx], $dados);
            $chave = 'salva';
        } else {
            $dados['id']       = egressoNovoId();
            $dados['logo']     = $logoNovo ?? '';
            $dados['criadoEm'] = date('d/m/Y');
            array_unshift($lista, $dados);
            $chave = 'criada';
        }
        empresasSalvar($lista);
        empresasAdminVoltar($chave);
    }

    if ($acao === 'alternar' || $acao === 'excluir') {
        $lista = empresasLista();
        $idx = null;
        foreach ($lista as $k => $e) {
            if ((int) ($e['id'] ?? 0) === $id) { $idx = $k; break; }
        }
        if ($idx === null) empresasAdminVoltar('nao_encontrada', true);

        if ($acao === 'alternar') {
            $lista[$idx]['ativa'] = empty($lista[$idx]['ativa']);
            $chave = $lista[$idx]['ativa'] ? 'ativada' : 'desativada';
            empresasSalvar($lista);
            empresasAdminVoltar($chave);
        }

        empresasApagarLogo($lista[$idx]['logo'] ?? '');
        array_splice($lista, $idx, 1);
        empresasSalvar($lista);
        empresasAdminVoltar('excluida');
    }

    empresasAdminVoltar('nao_encontrada', true);
}

/* =========================================================================
 * DADOS DA TELA
 * ========================================================================= */
$lista    = empresasLista();
$contagem = empresasContarVagas($lista);

$editarId = (int) ($_GET['editar'] ?? 0);
$f = [];
if ($editarId > 0) {
    foreach ($lista as $e) {
        if ((int) ($e['id'] ?? 0) === $editarId) { $f = $e; break; }
    }
}
$editando   = !empty($f);
$cursosForm = empresasCursosLista($f['cursos'] ?? '');

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-7xl mx-auto">

        <div class="mb-8 animate-fade-in">
            <h2 class="text-3xl font-bold text-slate-900 mb-2">Empresas Parceiras</h2>
            <p class="text-slate-500 font-medium">Cadastre as empresas que os alunos podem consultar. As vagas abertas são encontradas pelo nome da empresa.</p>
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

        <!-- Formulario: cadastrar / editar -->
        <div class="bg-white p-8 rounded-2xl border border-gray-100 shadow-sm mb-10">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-slate-900">
                    <i class="fas fa-handshake text-[#4A9FCA] mr-2"></i>
                    <?= $editando ? 'Editar empresa' : 'Nova empresa' ?>
                </h3>
                <?php if ($editando): ?>
                    <a href="admin-empresas.php" class="text-sm text-gray-400 hover:text-gray-600"><i class="fas fa-times mr-1"></i>Cancelar edição</a>
                <?php endif; ?>
            </div>

            <form method="POST" action="admin-empresas.php" enctype="multipart/form-data" class="space-y-5">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" value="<?= $editando ? (int) $f['id'] : 0 ?>">

                <div class="grid md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nome da empresa *</label>
                        <input type="text" name="nome" required maxlength="100" value="<?= egh($f['nome'] ?? '') ?>"
                               class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Setor</label>
                        <input type="text" name="setor" maxlength="80" value="<?= egh($f['setor'] ?? '') ?>" placeholder="Ex: Automotivo, Tecnologia, Varejo"
                               class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Site</label>
                        <input type="text" name="site" maxlength="200" value="<?= egh($f['site'] ?? '') ?>" placeholder="www.empresa.com.br"
                               class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">E-mail de contato (RH) <span class="text-xs text-gray-400 font-normal">(aparece para os alunos)</span></label>
                        <input type="email" name="email" maxlength="120" value="<?= egh($f['email'] ?? '') ?>"
                               class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Descrição <span class="text-xs text-gray-400 font-normal">(até 500 caracteres)</span></label>
                    <textarea name="descricao" rows="3" maxlength="500" class="w-full px-3 py-2 border border-gray-200 rounded-lg"><?= egh($f['descricao'] ?? '') ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Cursos de interesse <span class="text-xs text-gray-400 font-normal">(nenhum marcado = todos os cursos)</span>
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                        <?php foreach ($fsaCourses as $c): ?>
                            <label class="flex items-center gap-2 text-sm text-gray-700 p-2 border border-gray-100 rounded-lg">
                                <input type="checkbox" name="cursos[]" value="<?= egh($c) ?>" <?= in_array($c, $cursosForm, true) ? 'checked' : '' ?>>
                                <span><?= egh($c) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-5 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Logo <span class="text-xs text-gray-400 font-normal">(opcional, até 2MB)<?= !empty($f['logo']) ? ' &mdash; enviar outra substitui a atual' : '' ?></span></label>
                        <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp,.gif" class="text-sm">
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="ativa" <?= (!$editando || !empty($f['ativa'])) ? 'checked' : '' ?>>
                        <span>Ativa (visível para os alunos)</span>
                    </label>
                </div>

                <button type="submit" class="px-8 h-12 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl font-semibold">
                    <i class="fas fa-save mr-1"></i> <?= $editando ? 'Salvar alterações' : 'Cadastrar empresa' ?>
                </button>
            </form>
        </div>

        <!-- Lista -->
        <h3 class="text-lg font-bold text-slate-900 mb-3">Empresas cadastradas (<?= count($lista) ?>)</h3>

        <?php if (empty($lista)): ?>
            <div class="p-12 text-center border-2 border-dashed border-gray-200 rounded-3xl bg-white">
                <i class="fas fa-handshake text-5xl text-gray-200 mb-3"></i>
                <p class="text-slate-500">Nenhuma empresa cadastrada ainda.</p>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="p-3">Empresa</th>
                            <th class="p-3">Setor</th>
                            <th class="p-3">Vagas abertas</th>
                            <th class="p-3">Status</th>
                            <th class="p-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lista as $e):
                            $eid = (int) ($e['id'] ?? 0);
                            $vg  = $contagem[$eid] ?? ['fsa' => 0, 'egresso' => 0, 'total' => 0];
                            $ativa = !empty($e['ativa']);
                            $logo = (string) ($e['logo'] ?? '');
                            $temLogo = $logo !== '' && is_file(__DIR__ . '/' . $logo);
                        ?>
                            <tr class="border-t border-gray-100">
                                <td class="p-3">
                                    <div class="flex items-center gap-3">
                                        <?php if ($temLogo): ?>
                                            <img src="<?= egh($logo) ?>" alt="" class="w-10 h-10 rounded-lg object-contain border border-gray-100 bg-white p-0.5">
                                        <?php else: ?>
                                            <div class="w-10 h-10 bg-gradient-to-br from-[#4A9FCA] to-[#3A8FB0] rounded-lg flex items-center justify-center text-white font-bold">
                                                <?= egh(mb_strtoupper(mb_substr((string) ($e['nome'] ?? '?'), 0, 1))) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <p class="font-semibold text-slate-800"><?= egh($e['nome'] ?? '') ?></p>
                                            <?php if (!empty($e['site'])): ?>
                                                <a href="<?= egh($e['site']) ?>" target="_blank" rel="noopener" class="text-xs text-[#4A9FCA] hover:underline"><?= egh($e['site']) ?></a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-3 text-gray-600"><?= egh($e['setor'] ?? '') ?></td>
                                <td class="p-3">
                                    <strong><?= (int) $vg['total'] ?></strong>
                                    <?php if ($vg['egresso'] > 0): ?>
                                        <span class="text-xs text-gray-400">(<?= (int) $vg['egresso'] ?> de egressos)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3">
                                    <span class="px-3 py-1 rounded-full text-xs font-bold <?= $ativa ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' ?>">
                                        <?= $ativa ? 'Ativa' : 'Inativa' ?>
                                    </span>
                                </td>
                                <td class="p-3">
                                    <div class="flex flex-wrap justify-end items-center gap-2">
                                        <a href="admin-empresas.php?editar=<?= $eid ?>" class="px-3 py-1.5 border border-gray-200 text-gray-600 rounded-lg text-xs font-semibold hover:bg-gray-50">
                                            <i class="fas fa-pen mr-1"></i> Editar
                                        </a>
                                        <form method="POST" action="admin-empresas.php" class="inline">
                                            <input type="hidden" name="acao" value="alternar">
                                            <input type="hidden" name="id" value="<?= $eid ?>">
                                            <button type="submit" class="px-3 py-1.5 border border-gray-200 text-gray-600 rounded-lg text-xs font-semibold hover:bg-gray-50">
                                                <i class="fas <?= $ativa ? 'fa-eye-slash' : 'fa-eye' ?> mr-1"></i> <?= $ativa ? 'Desativar' : 'Ativar' ?>
                                            </button>
                                        </form>
                                        <form method="POST" action="admin-empresas.php" class="inline"
                                              data-confirm="A empresa e a logo serão removidas. Esta ação não pode ser desfeita."
                                              data-confirm-titulo="Excluir empresa?" data-confirm-botao="Excluir"
                                              data-confirm-tipo="perigo" data-confirm-icone="fa-trash-alt">
                                            <input type="hidden" name="acao" value="excluir">
                                            <input type="hidden" name="id" value="<?= $eid ?>">
                                            <button type="submit" class="px-3 py-1.5 border border-red-200 text-red-600 rounded-lg text-xs font-semibold hover:bg-red-50">
                                                <i class="fas fa-trash-alt mr-1"></i> Excluir
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php include 'includes/footer.php'; ?>
