<?php
require_once 'includes/config.php';
require_once 'includes/alertas.php';
checkAuth();

if (empty($_SESSION['is_admin'])) { header('Location: dashboard.php'); exit; }

$pageTitle = 'Alertas e E-mails';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['action'] ?? '';
    @set_time_limit(60);

    if ($acao === 'processar') {
        $r = alertasProcessarFila(15, 40);
        header('Location: admin-alertas.php?msg=processado&env=' . (int) $r['enviados'] . '&fal=' . (int) $r['falhas'] . '&sem=' . (!empty($r['semCredencial']) ? 1 : 0));
        exit;
    }
    if ($acao === 'gerar') {
        $r = alertasManutencao(true, 0, 1);
        header('Location: admin-alertas.php?msg=gerado&n=' . (int) ($r['resumoGerado']['enfileirados'] ?? 0));
        exit;
    }
    if ($acao === 'limpar') {
        alertasFilaOperar(function (array &$fila) { $fila = []; return 0; });
        header('Location: admin-alertas.php?msg=limpa');
        exit;
    }
}

$estado = alertasEstado();
$fila   = loadData('fila_emails');
$total  = is_array($fila) ? count($fila) : 0;

$optResumo = 0; $optNova = 0; $totalAlunos = 0; $semEmail = 0;
foreach (loadData('usuarios') as $u) {
    if (!alertasEhAluno($u)) continue;
    $totalAlunos++;
    $p = alertasPrefs($u);
    if (!alertasEmailValido($u)) { if ($p['resumoSemanal'] || $p['vagaNova']) $semEmail++; continue; }
    if ($p['resumoSemanal']) $optResumo++;
    if ($p['vagaNova']) $optNova++;
}

$chave = (string) ($_ENV['ALERTAS_CRON_KEY'] ?? (getenv('ALERTAS_CRON_KEY') ?: ''));
$urlCron = $chave !== '' ? appBaseUrl() . '/resumo-semanal.php?chave=' . urlencode($chave) : '';

$fmtData = fn($ts) => $ts ? date('d/m/Y H:i', (int) $ts) : '—';

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-5xl mx-auto">

        <div class="mb-8 animate-fade-in">
            <h2 class="text-3xl font-bold text-slate-900 mb-2">Alertas e E-mails</h2>
            <p class="text-slate-500 font-medium">Fila de envio, resumo semanal e agendamento.</p>
        </div>

        <?php $m = $_GET['msg'] ?? ''; ?>
        <?php if ($m === 'processado'): ?>
            <div class="mb-6 p-4 <?= !empty($_GET['sem']) ? 'bg-red-50 border-red-200 text-red-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700' ?> border rounded-xl text-sm">
                <?= !empty($_GET['sem']) ? 'Credenciais do Gmail não configuradas: nada foi enviado.' : 'Enviados: ' . (int) $_GET['env'] . ' &bull; falhas: ' . (int) $_GET['fal'] . '.' ?>
            </div>
        <?php elseif ($m === 'gerado'): ?>
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm">Resumo semanal gerado: <?= (int) $_GET['n'] ?> e-mail(s) entraram na fila. Clique em "Enviar parte da fila" para enviar.</div>
        <?php elseif ($m === 'limpa'): ?>
            <div class="mb-6 p-4 bg-gray-100 border border-gray-200 text-gray-700 rounded-xl text-sm">Fila esvaziada.</div>
        <?php endif; ?>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="p-5 bg-[#58A1D4] text-white rounded-2xl"><p class="text-white/90 text-sm">Na fila</p><p class="text-4xl font-bold"><?= $total ?></p></div>
            <div class="p-5 bg-[#27AE60] text-white rounded-2xl"><p class="text-white/90 text-sm">Com resumo semanal</p><p class="text-4xl font-bold"><?= $optResumo ?></p></div>
            <div class="p-5 bg-purple-500 text-white rounded-2xl"><p class="text-white/90 text-sm">Com aviso na hora</p><p class="text-4xl font-bold"><?= $optNova ?></p></div>
            <div class="p-5 bg-[#E35444] text-white rounded-2xl"><p class="text-white/90 text-sm">Ativaram sem e-mail</p><p class="text-4xl font-bold"><?= $semEmail ?></p></div>
        </div>
        <p class="text-xs text-gray-400 mb-8">De <?= $totalAlunos ?> aluno(s). Os alertas são opt-in: o aluno ativa em "Meus Alertas".</p>

        <div class="bg-white p-6 rounded-2xl border border-gray-100 mb-8">
            <h3 class="font-bold text-slate-900 mb-3">Estado do resumo semanal</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-gray-600">
                <p><strong>Última geração:</strong><br><?= $fmtData($estado['ultimoResumoTs'] ?? 0) ?> (semana <?= egh($estado['semanaResumo'] ?? '—') ?>)</p>
                <p><strong>E-mails gerados na última vez:</strong><br><?= (int) ($estado['ultimoResumoTotal'] ?? 0) ?></p>
                <p><strong>Último envio da fila:</strong><br><?= $fmtData($estado['ultimoEnvioTs'] ?? 0) ?></p>
            </div>
            <p class="text-xs text-gray-400 mt-4">O resumo é gerado sozinho uma vez por semana (a partir de segunda, 08:00), quando alguém abre o portal ou quando o agendador chama a URL abaixo.</p>
            <div class="flex flex-wrap gap-3 mt-4">
                <form method="POST"><input type="hidden" name="action" value="processar">
                    <button class="px-5 h-11 bg-[#4A9FCA] text-white rounded-xl font-semibold text-sm"><i class="fas fa-paper-plane mr-1"></i> Enviar parte da fila (15)</button></form>
                <form method="POST" data-confirm="Quem já recebeu o resumo desta semana receberá outro e-mail." data-confirm-titulo="Gerar o resumo de novo?" data-confirm-botao="Gerar resumo" data-confirm-tipo="aviso" data-confirm-icone="fa-redo">
                    <input type="hidden" name="action" value="gerar">
                    <button class="px-5 h-11 border border-gray-200 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-50">Gerar resumo agora</button></form>
                <form method="POST" data-confirm="Todos os e-mails que ainda não foram enviados serão apagados. Esta ação não pode ser desfeita." data-confirm-titulo="Esvaziar a fila?" data-confirm-botao="Esvaziar" data-confirm-tipo="perigo" data-confirm-icone="fa-trash-alt">
                    <input type="hidden" name="action" value="limpar">
                    <button class="px-5 h-11 text-red-500 hover:bg-red-50 rounded-xl text-sm">Esvaziar fila</button></form>
            </div>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-gray-100 mb-8">
            <h3 class="font-bold text-slate-900 mb-2">Agendamento automático</h3>
            <?php if ($urlCron === ''): ?>
                <p class="text-sm text-amber-700 bg-amber-50 p-3 rounded-lg">A variável <strong>ALERTAS_CRON_KEY</strong> não está definida. Crie uma chave longa e aleatória no <code>.env</code> (local) ou em Environment (Render) para ativar a URL de agendamento.</p>
            <?php else: ?>
                <p class="text-sm text-gray-600 mb-2">Configure um agendador externo gratuito (ex: cron-job.org) para abrir esta URL a cada 15 a 30 minutos. Ela gera o resumo da semana (uma única vez) e envia a fila aos poucos.</p>
                <input type="text" readonly value="<?= egh($urlCron) ?>" onclick="this.select()" class="w-full h-11 px-3 border border-gray-200 rounded-lg text-xs bg-gray-50">
                <p class="text-xs text-gray-400 mt-2">Quem tiver essa URL consegue disparar os envios. Não compartilhe.</p>
            <?php endif; ?>
        </div>

        <div class="bg-white border border-gray-100 rounded-2xl overflow-x-auto">
            <div class="p-4 border-b font-bold text-slate-900">Próximos da fila (até 15)</div>
            <table class="w-full text-sm">
                <thead><tr class="text-left text-gray-500 border-b bg-gray-50"><th class="p-3">Para</th><th class="p-3">Assunto</th><th class="p-3">Tentativas</th></tr></thead>
                <tbody>
                <?php if ($total === 0): ?><tr><td colspan="3" class="p-6 text-center text-gray-400">Fila vazia.</td></tr><?php endif; ?>
                <?php foreach (array_slice(is_array($fila) ? $fila : [], 0, 15) as $it): ?>
                    <tr class="border-b last:border-0"><td class="p-3"><?= egh($it['to'] ?? '') ?></td><td class="p-3"><?= egh($it['assunto'] ?? '') ?></td><td class="p-3"><?= (int) ($it['tent'] ?? 0) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
