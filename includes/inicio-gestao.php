<?php
/**
 * =============================================================================
 * INÍCIO DE QUEM NÃO É ALUNO
 * -----------------------------------------------------------------------------
 * Incluído pelo dashboard.php. Cada perfil tem o seu Início:
 *   professor        -> vagas que publicou, mentoria e atalhos
 *   egresso          -> suas divulgações de vaga e perfil
 *   admin            -> pendências da Coordenação de Estágios
 *   admin_egressos   -> pendências e números dos egressos
 *   admin_denuncias  -> denúncias e chats da mentoria
 * Monta $numeros (cartões), $pendencias (o que fazer agora) e $atalhos.
 * =============================================================================
 */
require_once __DIR__ . '/mentoria.php';
require_once __DIR__ . '/denuncias.php';

$perfilIni = perfilAtual();
$euId      = (int) $_SESSION['user_id'];
$usuariosIni = array_values(array_filter((array) loadData('usuarios'), 'is_array'));
$posts     = array_slice((array) loadData('platform_posts'), 0, 3);
$vagasAbertas = count(array_filter((array) loadData('platform_jobs'), fn($v) => vagaVigente($v)));
$numeros = []; $pendencias = []; $atalhos = []; $subtitulo = '';

$hora = (int) date('G');
$saudacao = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');

switch ($perfilIni) {
    case 'professor':
        $subtitulo = 'Publique vagas e oriente os alunos na mentoria.';
        $minhas = array_filter((array) loadData('job_requests'), fn($r) => (int) ($r['studentId'] ?? 0) === $euId);
        $st = ['pendente' => 0, 'aprovada' => 0, 'rejeitada' => 0];
        foreach ($minhas as $r) $st[$r['status'] ?? 'pendente'] = ($st[$r['status'] ?? 'pendente'] ?? 0) + 1;
        $eu = egressoCarregarUsuario($euId) ?: [];
        $pedidos = mentoriaPendentesDoMentor($euId, mentoriaPedidos());
        $msgs = mentoriaNaoLidasTotal($euId);
        $numeros = [
            ['Vagas abertas na Central', $vagasAbertas, 'fa-briefcase', '#F39C12', 'jobs.php'],
            ['Minhas vagas em análise', $st['pendente'], 'fa-clock', '#4A9FCA', 'request-job.php'],
            ['Minhas vagas publicadas', $st['aprovada'], 'fa-check-circle', '#27AE60', 'request-job.php'],
            ['Pedidos de mentoria', $pedidos, 'fa-hands-helping', '#9B59B6', 'mentoria.php?aba=pedidos'],
        ];
        if ($pedidos) $pendencias[] = ['Responder ' . $pedidos . ' pedido(s) de mentoria', 'Os alunos esperam a sua resposta.', 'mentoria.php?aba=pedidos', 'fa-inbox', 'red'];
        if ($msgs)    $pendencias[] = [$msgs . ' mensagem(ns) nova(s) na mentoria', 'Abra a conversa para responder.', 'mentoria.php?aba=pedidos', 'fa-comments', 'blue'];
        if ($st['rejeitada']) $pendencias[] = [$st['rejeitada'] . ' vaga(s) não aprovada(s)', 'Veja o motivo, corrija e envie de novo.', 'request-job.php', 'fa-times-circle', 'amber'];
        if (($eu['mentoriaStatus'] ?? '') === '') $pendencias[] = ['Seja mentor(a) dos alunos', 'Cadastre as áreas em que pode ajudar.', 'mentoria.php?aba=config', 'fa-user-plus', 'green'];
        $atalhos = [['request-job.php', 'Publicar vaga', 'Vai para aprovação', 'fa-plus-circle', '#4A9FCA'], ['mentoria.php', 'Mentoria', 'Pedidos e conversas', 'fa-hands-helping', '#9B59B6'],
                    ['jobs.php', 'Vagas', 'Tudo o que está aberto', 'fa-briefcase', '#F39C12'], ['career-tips.php', 'Dicas de Carreira', 'Para indicar aos alunos', 'fa-lightbulb', '#27AE60']];
        break;

    case 'egresso':
        $subtitulo = 'Divulgue vagas para os alunos da FSA e mantenha seu perfil em dia.';
        $minhas = array_filter(egressoVagas(), fn($v) => (int) ($v['egressoId'] ?? 0) === $euId);
        $st = ['pendente' => 0, 'aprovada' => 0, 'rejeitada' => 0, 'expirada' => 0, 'encerrada' => 0];
        foreach ($minhas as $v) { $s = egressoVagaStatusEfetivo($v); $st[$s] = ($st[$s] ?? 0) + 1; }
        $eu = egressoCarregarUsuario($euId) ?: [];
        $numeros = [
            ['Vagas em análise', $st['pendente'], 'fa-clock', '#4A9FCA', 'egresso-publicar.php'],
            ['Vagas publicadas', $st['aprovada'], 'fa-check-circle', '#27AE60', 'egresso-publicar.php'],
            ['Não aprovadas', $st['rejeitada'], 'fa-times-circle', '#E35444', 'egresso-publicar.php'],
            ['Expiradas ou encerradas', $st['expirada'] + $st['encerrada'], 'fa-hourglass-end', '#64748b', 'egresso-publicar.php'],
        ];
        if ($st['rejeitada']) $pendencias[] = ['Corrigir vaga(s) não aprovada(s)', 'Veja o motivo e envie de novo.', 'egresso-publicar.php', 'fa-pen', 'amber'];
        if ($st['expirada'])  $pendencias[] = ['Renovar vaga(s) expirada(s)', 'Se a vaga ainda estiver aberta, renove a validade.', 'egresso-publicar.php', 'fa-redo', 'blue'];
        if (empty($eu['curso']) || empty($eu['anoConclusao'])) $pendencias[] = ['Complete seu perfil de egresso', 'Curso e ano de conclusão ajudam a coordenação.', 'egresso-perfil.php', 'fa-user-graduate', 'green'];
        $atalhos = [['egresso-publicar.php', 'Divulgar vaga', 'Vai para aprovação', 'fa-plus-circle', '#4A9FCA'], ['egresso-perfil.php', 'Meu perfil', 'Curso, carreira e contato', 'fa-user-graduate', '#9B59B6']];
        break;

    case 'admin':
        $subtitulo = 'O que precisa da sua atenção na Coordenação de Estágios.';
        $sol = count(array_filter((array) loadData('job_requests'), fn($r) => ($r['status'] ?? '') === 'pendente'));
        $ass = count(array_filter((array) loadData('assinaturas'), fn($s) => ($s['status'] ?? '') === 'solicitado'));
        $alunos = count(array_filter($usuariosIni, fn($u) => in_array(perfilDoUsuario($u), ['aluno', 'aluno_restrito'], true)));
        $mentPend = count(array_filter($usuariosIni, fn($u) => ($u['tipo'] ?? '') === MENTORIA_TIPO_MENTOR && ($u['mentoriaStatus'] ?? '') === 'pendente'));
        $sug = count(array_filter((array) loadData('vagas_sugeridas'), fn($v) => ($v['status'] ?? 'pendente') === 'pendente'));
        $numeros = [
            ['Vagas abertas', $vagasAbertas, 'fa-briefcase', '#F39C12', 'jobs.php'],
            ['Solicitações de vaga', $sol, 'fa-inbox', '#E35444', 'admin-jobs.php'],
            ['Assinaturas pendentes', $ass, 'fa-file-signature', '#4A9FCA', 'admin-signature.php'],
            ['Alunos cadastrados', $alunos, 'fa-user-graduate', '#27AE60', 'admin-paineis.php'],
        ];
        if ($sol)      $pendencias[] = ['Analisar ' . $sol . ' solicitação(ões) de vaga', 'Alunos e professores aguardam aprovação.', 'admin-jobs.php', 'fa-inbox', 'red'];
        if ($ass)      $pendencias[] = ['Atender ' . $ass . ' assinatura(s)', 'Documentos esperando assinatura.', 'admin-signature.php', 'fa-file-signature', 'amber'];
        if ($mentPend) $pendencias[] = ['Aprovar ' . $mentPend . ' mentor(es)', 'Professores que querem ser mentores.', 'admin-mentoria.php', 'fa-user-check', 'blue'];
        if ($sug)      $pendencias[] = ['Revisar ' . $sug . ' vaga(s) sugerida(s)', 'Vagas externas encontradas automaticamente.', 'admin-sugeridas.php', 'fa-inbox', 'green'];
        $atalhos = [['admin-jobs.php', 'Admin Vagas', 'Publicar e aprovar', 'fa-cog', '#4A9FCA'], ['admin-paineis.php', 'Painéis', 'Indicadores e relatórios', 'fa-chart-pie', '#9B59B6'],
                    ['posts.php', 'Posts', 'Avisos do mural', 'fa-newspaper', '#F39C12'], ['admin-mentoria.php', 'Mentoria', 'Mentores e pedidos', 'fa-hands-helping', '#27AE60']];
        break;

    case 'admin_egressos':
        $subtitulo = 'Acompanhe os egressos e as vagas que eles divulgam.';
        $egressos = array_filter($usuariosIni, fn($u) => perfilDoUsuario($u) === 'egresso');
        $vPend = count(array_filter(egressoVagas(), fn($v) => egressoVagaStatusEfetivo($v) === 'pendente'));
        $vPub  = count(array_filter(egressoVagas(), fn($v) => egressoVagaStatusEfetivo($v) === 'aprovada'));
        $desat = count(array_filter($egressos, fn($u) => empty($u['perfilAtualizadoEm']) || strtotime((string) $u['perfilAtualizadoEm']) < strtotime('-12 months')));
        $naArea = count(array_filter($egressos, fn($u) => in_array($u['statusProfissional'] ?? '', ['Empregado na area', 'Estagiando na area', 'Empreendedor / autonomo'], true)));
        $numeros = [
            ['Egressos cadastrados', count($egressos), 'fa-users', '#4A9FCA', 'admin-egressos.php'],
            ['Vagas para analisar', $vPend, 'fa-inbox', '#E35444', 'admin-egressos.php'],
            ['Vagas de egressos no ar', $vPub, 'fa-briefcase', '#27AE60', 'jobs.php?origem=egresso'],
            ['Atuando na área', $naArea, 'fa-chart-line', '#9B59B6', 'admin-relatorios.php'],
        ];
        if ($vPend) $pendencias[] = ['Analisar ' . $vPend . ' vaga(s) de egressos', 'Elas só aparecem para os alunos depois de aprovadas.', 'admin-egressos.php', 'fa-inbox', 'red'];
        if ($desat) $pendencias[] = [$desat . ' perfil(is) sem atualização há mais de 1 ano', 'Vale pedir que atualizem a situação profissional.', 'admin-egressos.php', 'fa-user-clock', 'amber'];
        $atalhos = [['admin-egressos.php', 'Gestão de Egressos', 'Cadastros e vagas', 'fa-users', '#4A9FCA'], ['admin-paineis.php', 'Painéis', 'Indicadores', 'fa-chart-pie', '#9B59B6'],
                    ['admin-relatorios.php', 'Relatórios', 'Exportar em Excel', 'fa-file-excel', '#27AE60'], ['jobs.php', 'Vagas', 'Tudo o que está aberto', 'fa-briefcase', '#F39C12']];
        break;

    case 'admin_denuncias':
        $subtitulo = 'Denúncias recebidas e conversas da mentoria.';
        $den = denunciaTodas();
        $novas = count(array_filter($den, fn($d) => !empty($d['naoLidoAdmin'])));
        $rec   = count(array_filter($den, fn($d) => ($d['status'] ?? '') === 'recebida'));
        $ana   = count(array_filter($den, fn($d) => ($d['status'] ?? '') === 'em_analise'));
        $ment  = mentoriaDenunciasAbertas();
        $numeros = [
            ['Com novidade', $novas, 'fa-bell', '#E35444', 'admin-denuncias.php'],
            ['Recebidas', $rec, 'fa-inbox', '#4A9FCA', 'admin-denuncias.php?status=recebida'],
            ['Em análise', $ana, 'fa-search', '#F39C12', 'admin-denuncias.php?status=em_analise'],
            ['Denúncias da mentoria', $ment, 'fa-comments', '#9B59B6', 'admin-mentoria.php?tab=denuncias'],
        ];
        if ($novas) $pendencias[] = [$novas . ' denúncia(s) com novidade', 'Leia e responda quem abriu.', 'admin-denuncias.php', 'fa-bell', 'red'];
        if ($ment)  $pendencias[] = [$ment . ' denúncia(s) da mentoria abertas', 'Veja a conversa e decida.', 'admin-mentoria.php?tab=denuncias', 'fa-comments', 'amber'];
        $atalhos = [['admin-denuncias.php', 'Denúncias', 'Canal de Denúncias', 'fa-shield-alt', '#E35444'], ['admin-mentoria.php?tab=denuncias', 'Chats da mentoria', 'Conversas e denúncias', 'fa-comments', '#9B59B6']];
        break;
}
$coresP = ['amber' => 'bg-amber-50 text-amber-600', 'red' => 'bg-red-50 text-red-600', 'green' => 'bg-emerald-50 text-emerald-600', 'blue' => 'bg-[#e8f3fa] text-[#2B7FA6]'];

include __DIR__ . '/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-7xl mx-auto animate-fade-in">

        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#4A9FCA] via-[#3A8FB0] to-[#2B7FA6] text-white p-8 sm:p-10 mb-8">
            <div class="absolute -right-16 -top-16 w-64 h-64 rounded-full bg-white/10"></div>
            <div class="relative">
                <p class="text-white/75 text-sm font-medium mb-1"><?= htmlspecialchars(perfilRotulo()) ?></p>
                <h2 class="text-3xl sm:text-4xl font-bold mb-2"><?= $saudacao ?>, <?= htmlspecialchars(explode(' ', trim((string) ($_SESSION['user_name'] ?? '')))[0]) ?>!</h2>
                <p class="text-white/90 text-lg"><?= htmlspecialchars($subtitulo) ?></p>
            </div>
        </section>

        <section class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <?php foreach ($numeros as [$rot, $num, $ic, $cor, $link]): ?>
                <a href="<?= htmlspecialchars($link) ?>" class="bg-white rounded-2xl p-5 flex items-center gap-4 hover:-translate-y-0.5">
                    <span class="w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0" style="background: <?= $cor ?>1a; color: <?= $cor ?>;"><i class="fas <?= $ic ?> text-lg"></i></span>
                    <div class="min-w-0"><p class="text-2xl font-bold text-slate-900 leading-tight"><?= (int) $num ?></p><p class="text-sm text-slate-500 truncate"><?= htmlspecialchars($rot) ?></p></div>
                </a>
            <?php endforeach; ?>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <section class="lg:col-span-2">
                <h3 class="font-bold text-slate-900 text-lg mb-4">Para fazer agora</h3>
                <?php if (!$pendencias): ?>
                    <div class="bg-white rounded-2xl p-8 text-center"><i class="fas fa-check-circle text-3xl text-emerald-500 mb-2"></i><p class="font-semibold text-slate-700">Tudo em dia!</p></div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($pendencias as [$tit, $txt, $link, $ic, $cor]): ?>
                            <a href="<?= htmlspecialchars($link) ?>" class="bg-white rounded-2xl p-5 flex items-start gap-4 hover:-translate-y-0.5">
                                <span class="w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0 <?= $coresP[$cor] ?>"><i class="fas <?= $ic ?>"></i></span>
                                <div class="min-w-0"><p class="font-semibold text-slate-900"><?= htmlspecialchars($tit) ?></p><p class="text-sm text-slate-500 mt-0.5"><?= htmlspecialchars($txt) ?></p></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
            <section>
                <h3 class="font-bold text-slate-900 text-lg mb-4">Avisos da Central</h3>
                <div class="bg-white rounded-2xl p-5 space-y-4">
                    <?php if (!$posts): ?><p class="text-sm text-slate-400">Nenhum aviso no mural.</p><?php endif; ?>
                    <?php foreach ($posts as $p): ?>
                        <div>
                            <p class="text-xs text-slate-400"><?= htmlspecialchars($p['createdAt'] ?? '') ?></p>
                            <p class="font-semibold text-slate-800"><?= htmlspecialchars($p['title'] ?? '') ?></p>
                            <p class="text-sm text-slate-500"><?= htmlspecialchars(mb_strimwidth((string) ($p['content'] ?? ''), 0, 110, '...')) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <section>
            <h3 class="font-bold text-slate-900 text-lg mb-4">Atalhos</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach ($atalhos as [$link, $tit, $sub, $ic, $cor]): if (!perfilPaginaPermitida(strtok($link, '?'))) continue; ?>
                    <a href="<?= htmlspecialchars($link) ?>" class="bg-white rounded-2xl p-5 hover:-translate-y-0.5">
                        <span class="w-11 h-11 rounded-2xl flex items-center justify-center mb-4" style="background: <?= $cor ?>1a; color: <?= $cor ?>;"><i class="fas <?= $ic ?>"></i></span>
                        <p class="font-bold text-slate-900"><?= htmlspecialchars($tit) ?></p>
                        <p class="text-sm text-slate-500"><?= htmlspecialchars($sub) ?></p>
                    </a>
                <?php endforeach; ?>
                <?php if (perfilPaginaPermitida('denuncias.php')): ?>
                    <a href="denuncias.php" class="bg-white rounded-2xl p-5 hover:-translate-y-0.5">
                        <span class="w-11 h-11 rounded-2xl flex items-center justify-center mb-4 bg-red-50 text-red-600"><i class="fas fa-shield-alt"></i></span>
                        <p class="font-bold text-slate-900">Canal de Denúncias</p>
                        <p class="text-sm text-slate-500">Relate com sigilo</p>
                    </a>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>
