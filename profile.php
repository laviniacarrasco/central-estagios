<?php
/**
 * =============================================================================
 * MEU PERFIL
 * -----------------------------------------------------------------------------
 * Só os dados principais do aluno: foto, dados acadêmicos (vêm do cadastro /
 * TOTVS), contato e habilidades (vêm do currículo).
 * Não mede "perfil completo": quem tem porcentagem é o CURRÍCULO.
 * O Perfil por Competências (IA) fica em Dicas de Carreira > Meu perfil.
 * Alertas e preferências ficam na caixa de Configurações (engrenagem do topo).
 * =============================================================================
 */
require_once 'includes/config.php';
require_once 'includes/melhorias.php';
checkAuth();

$pageTitle = 'Meu Perfil';
$userName = $_SESSION['user_name'] ?? 'Usuário';

function getInitials($name) {
    $parts = explode(' ', trim($name));
    $first = strtoupper(substr($parts[0], 0, 1));
    $last  = count($parts) > 1 ? strtoupper(substr(end($parts), 0, 1)) : '';
    return $first . $last;
}

// Dados cadastrais (fonte da verdade: usuarios.json)
$usuarioAtual = [];
foreach (loadData('usuarios') as $u) {
    if (($u['id'] ?? null) == $_SESSION['user_id']) { $usuarioAtual = $u; break; }
}
$email      = $usuarioAtual['email']    ?? '';
$cursoAtual = $usuarioAtual['curso']    ?? '';
$ra         = $usuarioAtual['ra']       ?? '';
$periodo    = $usuarioAtual['periodo']  ?? '';
$turno      = $usuarioAtual['turno']    ?? '';
$situacao   = $usuarioAtual['situacao'] ?? '';

$userProfile    = loadData('userProfile', true);
$curriculumData = loadData('userCurriculum', true);
$habilidades = $curriculumData['habilidades'] ?? [];
$telefone    = $curriculumData['telefone']    ?? '';
$cidade      = $curriculumData['cidade']      ?? '';
$cvPct       = (int) mhCurriculoPontuacao(is_array($curriculumData) ? $curriculumData : [])['pct'];

// Trocar a foto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto_perfil'])) {
    $uploadDir = "uploads/profile/{$_SESSION['user_id']}/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $file = $_FILES['foto_perfil'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $extValida     = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    $tamanhoValido = $file['size'] <= 5 * 1024 * 1024;                       // até 5 MB
    $isImagemReal  = $extValida && @getimagesize($file['tmp_name']) !== false; // confere o conteúdo

    if ($extValida && $tamanhoValido && $isImagemReal && $file['error'] === 0) {
        if (!empty($userProfile['foto']) && file_exists($userProfile['foto'])) unlink($userProfile['foto']);
        $uploadPath = $uploadDir . 'avatar_' . time() . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $userProfile['foto'] = $uploadPath;
            saveData('userProfile', $userProfile, true);
            header('Location: profile.php?foto=ok');
            exit;
        }
    }
    header('Location: profile.php?foto=erro');
    exit;
}

// Remover a foto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remover_foto'])) {
    if (!empty($userProfile['foto']) && file_exists($userProfile['foto'])) unlink($userProfile['foto']);
    $userProfile['foto'] = null;
    saveData('userProfile', $userProfile, true);
    header('Location: profile.php?removida=1');
    exit;
}

$fotoSalva = $userProfile['foto'] ?? null;
$temFoto = $fotoSalva && file_exists($fotoSalva);
$v = fn($x) => trim((string) $x) !== '' && $x !== '—' ? htmlspecialchars((string) $x) : '<span class="text-slate-300">Não informado</span>';
$corCv = $cvPct >= 80 ? '#27AE60' : ($cvPct >= 50 ? '#F39C12' : '#E35444');

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-6xl mx-auto animate-fade-in">

        <?php if (isset($_GET['foto']) || isset($_GET['removida'])): $okFoto = ($_GET['foto'] ?? '') === 'ok' || isset($_GET['removida']); ?>
            <div class="mb-6 px-4 py-3 rounded-xl text-sm <?= $okFoto ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
                <i class="fas <?= $okFoto ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
                <?= isset($_GET['removida']) ? 'Foto removida.' : ($okFoto ? 'Foto atualizada.' : 'Não foi possível usar essa imagem. Envie JPG, PNG, WEBP ou GIF de até 5 MB.') ?>
            </div>
        <?php endif; ?>

        <!-- Cabeçalho do perfil -->
        <section class="bg-white rounded-3xl overflow-hidden mb-6">
            <div class="h-24 bg-gradient-to-r from-[#4A9FCA] via-[#3A8FB0] to-[#2B7FA6] relative">
                <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 20% 20%, #fff 0, transparent 40%), radial-gradient(circle at 85% 0%, #fff 0, transparent 35%);"></div>
            </div>
            <!-- Só a foto sobe sobre a faixa azul; nome e botões ficam abaixo dela -->
            <div class="px-6 sm:px-8 pt-3 pb-6 flex flex-col sm:flex-row sm:items-end gap-5">
                <div class="relative flex-shrink-0 w-28 -mt-16">
                    <?php if ($temFoto): ?>
                        <img src="<?= htmlspecialchars($fotoSalva) ?>" alt="Foto de perfil" class="w-28 h-28 rounded-3xl object-cover border-4 border-white shadow-lg bg-white">
                    <?php else: ?>
                        <div class="w-28 h-28 rounded-3xl bg-gradient-to-br from-[#4A9FCA] to-[#2B7FA6] border-4 border-white shadow-lg flex items-center justify-center">
                            <span class="text-white text-4xl font-bold"><?= getInitials($userName) ?></span>
                        </div>
                    <?php endif; ?>
                    <label for="uploadFoto" class="absolute -bottom-1 -right-1 w-9 h-9 bg-white text-[#2B7FA6] border border-slate-200 rounded-full flex items-center justify-center cursor-pointer shadow hover:bg-[#e8f3fa]" title="Alterar foto">
                        <i class="fas fa-camera text-sm"></i>
                    </label>
                    <form id="fotoForm" method="POST" action="profile.php" enctype="multipart/form-data">
                        <input type="file" id="uploadFoto" name="foto_perfil" accept="image/*" class="hidden" onchange="this.form.submit()">
                    </form>
                </div>
                <div class="flex-1 min-w-0 sm:pb-1">
                    <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 truncate"><?= htmlspecialchars($userName) ?></h2>
                    <div class="flex flex-wrap items-center gap-2 mt-2">
                        <?php if ($cursoAtual): ?><span class="fsa-chip"><i class="fas fa-graduation-cap"></i><?= htmlspecialchars($cursoAtual) ?></span><?php endif; ?>
                        <span class="fsa-chip" style="background:#f1f5f9;color:#475569;"><?= htmlspecialchars(perfilRotulo()) ?></span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 sm:pb-1">
                    <a href="curriculum.php" class="fsa-btn fsa-btn-primario"><i class="fas fa-file-alt mr-2"></i>Meu currículo</a>
                    <?php if ($temFoto): ?>
                        <form method="POST" action="profile.php" data-confirm="Suas iniciais voltam a aparecer no lugar da foto. Você pode enviar outra quando quiser."
                              data-confirm-titulo="Remover foto?" data-confirm-botao="Remover" data-confirm-tipo="perigo">
                            <input type="hidden" name="remover_foto" value="1">
                            <button type="submit" class="fsa-btn fsa-btn-perigo"><i class="fas fa-trash-alt mr-2"></i>Remover foto</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <div class="lg:col-span-2 space-y-6">
                <section class="bg-white rounded-2xl p-6">
                    <h3 class="font-bold text-slate-900 mb-4 flex items-center gap-2"><i class="fas fa-id-card text-[#4A9FCA]"></i> Dados acadêmicos</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <?php foreach ([['RA', $ra, 'fa-hashtag'], ['Período', $periodo, 'fa-layer-group'], ['Turno', $turno, 'fa-clock'], ['Situação', $situacao, 'fa-circle-check']] as [$rot, $val, $ic]): ?>
                            <div class="p-4 rounded-2xl bg-slate-50">
                                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-1"><i class="fas <?= $ic ?> mr-1"></i><?= $rot ?></p>
                                <p class="font-semibold text-slate-800"><?= $v($val) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-xs text-slate-400 mt-3"><i class="fas fa-info-circle mr-1"></i>Esses dados vêm do cadastro acadêmico. Algo errado? Fale com a coordenação.</p>
                </section>

                <section class="bg-white rounded-2xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-slate-900 flex items-center gap-2"><i class="fas fa-star text-[#4A9FCA]"></i> Habilidades</h3>
                        <a href="curriculum.php" class="text-sm text-[#2B7FA6] font-semibold hover:underline">Editar no currículo</a>
                    </div>
                    <?php if (!empty($habilidades)): ?>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($habilidades as $skill): ?>
                                <span class="fsa-chip text-sm px-3 py-1.5"><?= htmlspecialchars(trim((string) $skill)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-slate-400">Nenhuma habilidade ainda. Elas aparecem aqui quando você preenche o currículo.</p>
                    <?php endif; ?>
                </section>

                <a href="curriculum.php" class="fsa-card flex items-center gap-5 p-6">
                    <div class="relative w-16 h-16 flex-shrink-0">
                        <svg viewBox="0 0 36 36" class="w-16 h-16 -rotate-90">
                            <circle cx="18" cy="18" r="15.9" fill="none" stroke="#e2e8f0" stroke-width="3.5"></circle>
                            <circle cx="18" cy="18" r="15.9" fill="none" stroke="<?= $corCv ?>" stroke-width="3.5" stroke-linecap="round" stroke-dasharray="<?= $cvPct ?> 100"></circle>
                        </svg>
                        <span class="absolute inset-0 flex items-center justify-center text-sm font-bold text-slate-800"><?= $cvPct ?>%</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-slate-900">Seu currículo está <?= $cvPct ?>% completo</p>
                        <p class="text-sm text-slate-500"><?= $cvPct >= 100 ? 'Ótimo! Peça uma avaliação da IA para deixá-lo ainda melhor.' : 'É o currículo que conta para as vagas e para a IA.' ?></p>
                    </div>
                    <i class="fas fa-chevron-right text-slate-300"></i>
                </a>
            </div>

            <div class="space-y-6">
                <section class="bg-white rounded-2xl p-6">
                    <h3 class="font-bold text-slate-900 mb-4 flex items-center gap-2"><i class="fas fa-address-book text-[#4A9FCA]"></i> Contato</h3>
                    <ul class="space-y-4">
                        <?php foreach ([['fa-envelope', 'E-mail institucional', $email], ['fa-phone', 'Telefone', $telefone], ['fa-map-marker-alt', 'Cidade', $cidade]] as [$ic, $rot, $val]): ?>
                            <li class="flex items-start gap-3">
                                <span class="w-9 h-9 rounded-xl bg-[#e8f3fa] text-[#2B7FA6] flex items-center justify-center flex-shrink-0"><i class="fas <?= $ic ?> text-sm"></i></span>
                                <div class="min-w-0"><p class="text-xs text-slate-400"><?= $rot ?></p><p class="text-sm font-medium text-slate-800 break-words"><?= $v($val) ?></p></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="text-xs text-slate-400 mt-4">Telefone e cidade vêm do currículo.</p>
                </section>

                <a href="https://www.fsa.br/noticias" target="_blank" rel="noopener" class="block p-6 rounded-2xl text-white bg-gradient-to-br from-[#E74C3C] to-[#C0392B] hover:shadow-lg">
                    <p class="font-bold text-lg">Magazine FSA</p>
                    <p class="text-sm text-white/85 mt-1 mb-3">Notícias e oportunidades para alunos.</p>
                    <span class="text-sm font-semibold">Acessar <i class="fas fa-arrow-right ml-1"></i></span>
                </a>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
