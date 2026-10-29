<?php
require_once 'includes/config.php';
require_once 'includes/alertas.php';
require_once 'includes/melhorias.php';
require_once 'includes/vagas-externas.php';
checkAuth();

if (empty($_SESSION['is_admin'])) { header('Location: dashboard.php'); exit; }

$pageTitle = 'Vagas Sugeridas';
$fsaCourses = getFsaCourses();

function sugLoad(): array {
    $d = loadData('vagas_sugeridas');
    return is_array($d) ? array_values($d) : [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['action'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);

    if ($acao === 'buscar') {
        $r = vexImportar();
        mhLogAdmin('Busca de vagas externas', 'novas: ' . $r['novas'] . ', repetidas: ' . $r['duplicadas'] . ', descartadas: ' . $r['descartadas']);
        header('Location: admin-sugeridas.php?msg=busca&n=' . (int) $r['novas'] . '&dup=' . (int) $r['duplicadas'] . '&desc=' . (int) $r['descartadas'] . '&erro=' . urlencode((string) $r['erro']));
        exit;
    }

    // (Removido) "Adicionar vaga manualmente": vagas da FSA são cadastradas em Admin Vagas (admin-jobs.php).

    if ($acao === 'aprovar' || $acao === 'rejeitar') {
        // Rejeitar: justificativa obrigatória (fica no histórico e no log)
        $motivoRej = $acao === 'rejeitar' ? justificativaPost('motivo', 300) : null;
        if ($acao === 'rejeitar' && $motivoRej === null) {
            header('Location: admin-sugeridas.php?msg=justificativa');
            exit;
        }
        $fila = sugLoad();
        $achou = false;
        foreach ($fila as &$s) {
            if ((int) ($s['id'] ?? 0) !== $id || ($s['status'] ?? '') !== 'pendente') continue;
            $achou = true;
            if ($acao === 'rejeitar') {
                $s['status']         = 'rejeitada';
                $s['motivoRejeicao'] = $motivoRej;
                $s['rejeitadoPor']   = (string) ($_SESSION['user_name'] ?? '');
                $s['rejeitadoTs']    = time();
                mhLogAdmin('Vaga sugerida rejeitada', (string) $s['title'] . ' - ' . (string) $s['company'] . ' | Motivo: ' . $motivoRej);
                break;
            }
            $jobs = loadData('platform_jobs');
            if (!is_array($jobs)) $jobs = [];
            $fonte = (string) ($s['fonte'] ?? '');
            $novaVaga = [
                'id'              => mhNovoIdVaga($jobs),
                'title'           => (string) $s['title'],
                'company'         => (string) $s['company'],
                'location'        => (string) $s['location'],
                'type'            => (string) ($s['type'] ?? 'Presencial'),
                'hours'           => (string) ($s['hours'] ?? 'A combinar'),
                'salary'          => (string) ($s['salary'] ?? 'A combinar'),
                'description'     => trim((string) ($s['description'] ?? '')) . (vexCreditoFonte($fonte) !== '' ? "\n\nFonte: " . vexCreditoFonte($fonte) . "." : ''),
                'requirements'    => (string) ($s['requirements'] ?? ''),
                'benefits'        => (string) ($s['benefits'] ?? ''),
                'courses'         => (string) ($s['courses'] ?? ''),
                'applicationLink' => (string) ($s['applicationLink'] ?? ''),
                'featured'        => false,
                'expiresAt'       => vagaValidadePadrao(30),
                'status'          => 'Ativa',
                'applicants'      => 0,
                'createdAt'       => date('d/m/Y'),
                'externa'         => true,
                'fonte'           => $fonte,
            ];
            array_unshift($jobs, $novaVaga);
            saveData('platform_jobs', $jobs);
            if (function_exists('alertasEnfileirarVagaNova')) {
                try { alertasEnfileirarVagaNova($novaVaga, 'faculdade'); } catch (Throwable $e) { error_log('sugeridas: ' . $e->getMessage()); }
            }
            $s['status'] = 'aprovada';
            mhLogAdmin('Vaga sugerida aprovada', (string) $s['title'] . ' - ' . (string) $s['company']);
            break;
        }
        unset($s);
        if ($achou) saveData('vagas_sugeridas', $fila);
        header('Location: admin-sugeridas.php?msg=' . ($acao === 'aprovar' ? 'aprovada' : 'rejeitada'));
        exit;
    }

    if ($acao === 'limpar') {
        $fila = array_values(array_filter(sugLoad(), fn($s) => ($s['status'] ?? '') === 'pendente'));
        saveData('vagas_sugeridas', $fila);
        mhLogAdmin('Histórico de vagas sugeridas limpo');
        header('Location: admin-sugeridas.php?msg=limpa');
        exit;
    }
}

$fila = sugLoad();
$pend = array_values(array_filter($fila, fn($s) => ($s['status'] ?? '') === 'pendente'));
usort($pend, fn($a, $b) => ($b['criadoTs'] ?? 0) <=> ($a['criadoTs'] ?? 0));
$nAprov = count(array_filter($fila, fn($s) => ($s['status'] ?? '') === 'aprovada'));
$nRej   = count(array_filter($fila, fn($s) => ($s['status'] ?? '') === 'rejeitada'));

$chaveCron = (string) ($_ENV['ALERTAS_CRON_KEY'] ?? (getenv('ALERTAS_CRON_KEY') ?: ''));
$urlCron   = $chaveCron !== '' ? mhBaseUrl() . '/importar-vagas-cron.php?chave=' . urlencode($chaveCron) : '';

$campo = 'w-full h-11 px-3 border border-gray-200 rounded-xl text-sm bg-gray-50 focus:ring-2 focus:ring-[#4A9FCA]';
include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-5xl mx-auto">

        <div class="mb-8 animate-fade-in">
            <h2 class="text-3xl font-bold text-slate-900 mb-2">Vagas Sugeridas</h2>
            <p class="text-slate-500 font-medium">Vagas de São Paulo e do ABC vindas de fontes externas. Nada aparece para os alunos até você aprovar.</p>
        </div>

        <?php $m = $_GET['msg'] ?? ''; ?>
        <?php if ($m === 'busca'): ?>
            <div class="mb-6 p-4 <?= !empty($_GET['erro']) ? 'bg-red-50 border-red-200 text-red-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700' ?> border rounded-xl text-sm">
                <?php if (!empty($_GET['erro'])): ?>
                    <?= egh($_GET['erro']) ?>
                <?php else: ?>
                    Busca concluída: <?= (int) $_GET['n'] ?> nova(s) na fila, <?= (int) $_GET['dup'] ?> repetida(s) e <?= (int) $_GET['desc'] ?> descartada(s) pelo filtro.
                <?php endif; ?>
            </div>
        <?php elseif ($m === 'aprovada'): ?>
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm">Vaga publicada para os alunos por 30 dias. Você pode editar na tela de Vagas do admin.</div>
        <?php elseif ($m === 'justificativa'): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm"><i class="fas fa-exclamation-circle mr-1"></i> A justificativa é obrigatória (pelo menos 10 caracteres). A vaga continua pendente.</div>
        <?php elseif ($m === 'rejeitada'): ?>
            <div class="mb-6 p-4 bg-gray-100 border border-gray-200 text-gray-700 rounded-xl text-sm">Vaga rejeitada.</div>
        <?php elseif ($m === 'add'): ?>
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm">Vaga adicionada à fila.</div>
        <?php elseif ($m === 'faltam'): ?>
            <div class="mb-6 p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-sm">Informe pelo menos o título e a empresa.</div>
        <?php elseif ($m === 'limpa'): ?>
            <div class="mb-6 p-4 bg-gray-100 border border-gray-200 text-gray-700 rounded-xl text-sm">Histórico de aprovadas e rejeitadas apagado.</div>
        <?php endif; ?>

        <div class="grid grid-cols-3 gap-4 mb-8">
            <div class="p-5 bg-[#F39C12] text-white rounded-2xl"><p class="text-white/90 text-sm">Aguardando</p><p class="text-4xl font-bold"><?= count($pend) ?></p></div>
            <div class="p-5 bg-[#27AE60] text-white rounded-2xl"><p class="text-white/90 text-sm">Aprovadas</p><p class="text-4xl font-bold"><?= $nAprov ?></p></div>
            <div class="p-5 bg-[#E35444] text-white rounded-2xl"><p class="text-white/90 text-sm">Rejeitadas</p><p class="text-4xl font-bold"><?= $nRej ?></p></div>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-gray-100 mb-8">
            <h3 class="font-bold text-slate-900 mb-3">Buscar vagas externas</h3>
            <div class="grid md:grid-cols-2 gap-3 text-sm mb-4">
                <p class="p-3 rounded-xl <?= vexBuscaConfigurada() ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' ?>">
                    <i class="fas <?= vexBuscaConfigurada() ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-1"></i>
                    <?= egh(vexStatusFonte()) ?>
                </p>
                <p class="p-3 rounded-xl <?= vexIaConfigurada() ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-50 text-gray-600' ?>">
                    <i class="fas <?= vexIaConfigurada() ? 'fa-check-circle' : 'fa-info-circle' ?> mr-1"></i>
                    IA para conferir e escolher cursos: <?= vexIaConfigurada() ? 'ativa' : 'opcional (GEMINI_API_KEY); sem ela usamos palavras-chave' ?>
                </p>
            </div>
            <p class="text-xs text-gray-400 mb-4">Busca estágio e trainee em São Paulo, Santo André, São Bernardo, São Caetano, Diadema e Mauá. Também entram vagas remotas. Na Gupy, cada busca faz cerca de 8 consultas, com pausa entre elas. Inscrições já encerradas são ignoradas.</p>
            <div class="flex flex-wrap gap-3">
                <form method="POST" data-confirm="A busca consulta as fontes externas e pode levar até um minuto. As vagas novas ficam na fila, esperando a sua aprovação." data-confirm-titulo="Buscar vagas agora?" data-confirm-botao="Buscar" data-confirm-tipo="aviso" data-confirm-icone="fa-search">
                    <input type="hidden" name="action" value="buscar">
                    <button type="submit" class="px-5 h-11 bg-[#4A9FCA] text-white rounded-xl font-semibold text-sm <?= vexBuscaConfigurada() ? '' : 'opacity-50 cursor-not-allowed' ?>" <?= vexBuscaConfigurada() ? '' : 'disabled' ?>>
                        <i class="fas fa-search mr-1"></i> Buscar vagas agora
                    </button>
                </form>
                <?php if ($nAprov + $nRej > 0): ?>
                    <form method="POST" data-confirm="As vagas aprovadas e rejeitadas saem do histórico. As vagas já publicadas não são afetadas." data-confirm-titulo="Limpar histórico?" data-confirm-botao="Limpar" data-confirm-tipo="perigo" data-confirm-icone="fa-trash-alt">
                        <input type="hidden" name="action" value="limpar">
                        <button type="submit" class="px-5 h-11 border border-gray-200 text-gray-600 rounded-xl font-semibold text-sm hover:bg-gray-50">
                            <i class="fas fa-broom mr-1"></i> Limpar histórico
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <?php if ($urlCron !== ''): ?>
                <p class="text-xs text-gray-400 mt-4">Para buscar sozinho todo dia, agende esta URL em um serviço como o cron-job.org:</p>
                <p class="text-xs font-mono bg-gray-50 border border-gray-100 rounded-lg p-2 mt-1 break-all"><?= egh($urlCron) ?></p>
            <?php else: ?>
                <p class="text-xs text-gray-400 mt-4">Para agendar a busca, defina ALERTAS_CRON_KEY (a mesma do resumo semanal).</p>
            <?php endif; ?>
        </div>

        <h3 class="text-xl font-bold text-slate-900 mb-4">Aguardando aprovação</h3>
        <?php if (empty($pend)): ?>
            <div class="p-16 text-center border-2 border-dashed border-gray-200 rounded-3xl bg-white">
                <i class="fas fa-inbox text-6xl text-gray-200 mb-4"></i>
                <h3 class="text-xl font-bold text-slate-900 mb-2">Nenhuma vaga na fila</h3>
                <p class="text-slate-500">Use "Buscar vagas agora". Para cadastrar uma vaga da FSA, use <a href="admin-jobs.php" class="text-[#2B7FA6] font-semibold underline">Admin Vagas</a>.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($pend as $s): ?>
                    <div class="p-6 bg-white border border-gray-100 rounded-2xl shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                            <div class="min-w-0">
                                <h4 class="text-lg font-bold text-slate-900"><?= egh($s['title'] ?? '') ?></h4>
                                <p class="text-slate-500 font-semibold"><?= egh($s['company'] ?? '') ?></p>
                                <p class="text-xs text-gray-400 mt-1">
                                    <i class="fas fa-map-marker-alt mr-1"></i><?= egh($s['location'] ?? '') ?> &bull;
                                    <?= egh($s['type'] ?? '') ?> &bull; <?= egh($s['salary'] ?? '') ?>
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold border bg-gray-100 text-gray-600 border-gray-200"><?= egh($s['fonte'] ?? '') ?></span>
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold border bg-[#4A9FCA]/10 text-[#2B7FA6] border-[#4A9FCA]/30"><?= egh($s['nota'] ?? '') ?></span>
                            </div>
                        </div>
                        <?php if (!empty($s['description'])): ?>
                            <p class="text-sm text-gray-600 bg-gray-50 rounded-xl p-3 mb-3"><?= egh($s['description']) ?></p>
                        <?php endif; ?>
                        <p class="text-xs text-gray-500 mb-4">
                            <strong>Cursos:</strong> <?= !empty($s['courses']) ? egh($s['courses']) : 'todos os cursos' ?>
                            <?php if (!empty($s['applicationLink'])): ?>
                                &bull; <a href="<?= egh($s['applicationLink']) ?>" target="_blank" rel="noopener" class="text-[#4A9FCA] font-semibold hover:underline">Abrir anúncio original</a>
                            <?php endif; ?>
                        </p>
                        <div class="flex flex-wrap gap-3">
                            <form method="POST" data-confirm="A vaga será publicada para os alunos por 30 dias, com a etiqueta &quot;Vaga externa&quot;." data-confirm-titulo="Aprovar vaga?" data-confirm-botao="Aprovar" data-confirm-tipo="aviso" data-confirm-icone="fa-check-circle">
                                <input type="hidden" name="action" value="aprovar">
                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                <button type="submit" class="px-5 py-2 bg-[#27AE60] hover:bg-[#229954] text-white rounded-xl text-sm font-semibold"><i class="fas fa-check mr-1"></i> Aprovar</button>
                            </form>
                            <form method="POST" data-confirm="A vaga sai da fila e não aparece para os alunos." data-justificativa="motivo" data-justificativa-max="300" data-justificativa-dica="Ex.: estágio de curso técnico, fora do perfil da FSA." data-confirm-titulo="Rejeitar vaga?" data-confirm-botao="Rejeitar" data-confirm-tipo="perigo" data-confirm-icone="fa-times-circle">
                                <input type="hidden" name="action" value="rejeitar">
                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                <button type="submit" class="px-5 py-2 text-red-500 hover:bg-red-50 rounded-xl text-sm font-semibold"><i class="fas fa-times mr-1"></i> Rejeitar</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php include 'includes/footer.php'; ?>
