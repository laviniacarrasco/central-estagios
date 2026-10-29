<?php
/**
 * =============================================================================
 * TELA: INTEGRAÇÃO TOTVS (só funcionários)
 * -----------------------------------------------------------------------------
 * Esta página faz duas coisas:
 *
 * 1) TELA PARA O ADMIN (com login)
 *    - mostra se a integração está configurada
 *    - botão "Simular": mostra o que mudaria, sem gravar
 *    - botão "Sincronizar agora": grava as mudanças
 *    - mostra o relatório da última execução
 *
 * 2) ROTINA AUTOMÁTICA (sem login, protegida por chave)
 *    Um agendador (ex.: cron-job.org) chama, uma vez por dia:
 *        /admin-totvs.php?chave=SUA_CHAVE
 *    A chave é a mesma do resumo semanal: ALERTAS_CRON_KEY.
 *
 * Quem pode abrir a tela está definido em includes/perfis.php.
 * =============================================================================
 */

require_once 'includes/config.php';
require_once 'includes/integracoes/totvs-sincronizar.php';

/* -----------------------------------------------------------------------------
 * PARTE 2: ROTINA AUTOMÁTICA (vem antes do login, de propósito)
 * -------------------------------------------------------------------------- */
if (isset($_GET['chave'])) {
    header('Content-Type: text/plain; charset=utf-8');

    $chaveCerta = totvsEnv('ALERTAS_CRON_KEY');

    // hash_equals compara as chaves de um jeito seguro
    if ($chaveCerta === '' || !hash_equals($chaveCerta, (string) $_GET['chave'])) {
        http_response_code(403);
        echo "Chave inválida.\n";
        exit;
    }

    $r = totvsSincronizar(false);   // false = grava de verdade
    echo $r['ok']
        ? "OK. Lidos: {$r['lidos']} | Criados: {$r['criados']} | Atualizados: {$r['atualizados']} | Ignorados: {$r['ignorados']}\n"
        : "ERRO: {$r['erro']}\n";
    exit;
}

/* -----------------------------------------------------------------------------
 * PARTE 1: TELA DO ADMIN
 * -------------------------------------------------------------------------- */
checkAuth();   // exige login e confere se o perfil pode abrir esta página
$pageTitle = 'Cadastro de Alunos (TOTVS / banco de dados)';

// Ao clicar num botão: roda, guarda o relatório na sessão e recarrega a página.
// (Recarregar evita que o F5 rode a sincronização de novo.)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['action'] ?? '';
    if (in_array($acao, ['simular', 'sincronizar'], true)) {
        $_SESSION['totvs_relatorio'] = totvsSincronizar($acao === 'simular');
    }
    header('Location: admin-totvs.php');
    exit;
}

// Relatório da execução que acabou de rodar (mostrado uma vez só)
$relatorio = $_SESSION['totvs_relatorio'] ?? null;
unset($_SESSION['totvs_relatorio']);

// Se não acabou de rodar nada, mostra a última execução gravada
if ($relatorio === null) {
    $ultima = loadData('totvs_ultima_sincronizacao');
    $relatorio = !empty($ultima) ? $ultima : null;
}

$modo      = totvsModo();
$faltando  = in_array($modo, ['real', 'banco'], true) ? totvsProblemasConfig() : [];
$podeRodar = $modo !== 'desligado' && empty($faltando);

/** Atalho para escrever texto com segurança no HTML */
function tv($texto): string {
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

// Cores e nomes de cada modo (só para a tela)
$infoModo = [
    'desligado' => ['Desligada',              'bg-gray-100 text-gray-700'],
    'teste'     => ['Modo teste (arquivo de exemplo)', 'bg-amber-100 text-amber-800'],
    'real'      => ['Conectada à API do TOTVS', 'bg-emerald-100 text-emerald-800'],
    'banco'     => ['Conectada ao banco de dados', 'bg-emerald-100 text-emerald-800'],
][$modo];

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-5xl mx-auto">

        <!-- Título -->
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-slate-900 mb-2">Cadastro de Alunos</h2>
            <p class="text-slate-500">O cadastro em massa é feito direto do sistema acadêmico (TOTVS), pela API ou pelo banco de dados. Cria os alunos novos e atualiza RA, curso, período, turno e situação. Não usa planilha.</p>
        </div>

        <!-- Situação da integração -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mb-6">
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <span class="text-sm font-bold text-slate-700">Situação:</span>
                <span class="px-3 py-1 rounded-full text-xs font-bold <?= $infoModo[1] ?>"><?= tv($infoModo[0]) ?></span>
            </div>

            <?php if ($modo === 'desligado'): ?>
                <p class="text-sm text-gray-600">
                    Para ligar, crie a variável <code class="bg-gray-100 px-1 rounded">TOTVS_MODO</code>
                    com o valor <strong>teste</strong> (usa o arquivo de exemplo), <strong>real</strong> (API do TOTVS) ou <strong>banco</strong> (lê direto do banco de dados do TOTVS).
                </p>
            <?php elseif ($faltando): ?>
                <p class="text-sm text-red-600">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    Faltam as variáveis: <strong><?= tv(implode(', ', $faltando)) ?></strong>.
                </p>
            <?php elseif ($modo === 'teste'): ?>
                <p class="text-sm text-amber-700">
                    <i class="fas fa-flask mr-1"></i>
                    Os dados vêm de <code>data/totvs_exemplo.json</code>. Use <strong>Simular</strong>:
                    "Sincronizar" grava os alunos de exemplo no cadastro de verdade.
                </p>
            <?php endif; ?>

            <!-- Botões -->
            <div class="flex flex-wrap gap-3 mt-5">
                <form method="POST">
                    <input type="hidden" name="action" value="simular">
                    <button type="submit" <?= $podeRodar ? '' : 'disabled' ?>
                        class="px-5 py-2.5 border border-[#4A9FCA] text-[#2B7FA6] rounded-xl text-sm font-semibold hover:bg-blue-50 disabled:opacity-40 disabled:cursor-not-allowed">
                        <i class="fas fa-eye mr-1"></i> Simular (não grava nada)
                    </button>
                </form>

                <!-- data-confirm usa a caixa de confirmação padrão do site (header.php) -->
                <form method="POST"
                      data-confirm="Os dados dos alunos serão atualizados com o que veio do TOTVS. Uma cópia de segurança é guardada antes."
                      data-confirm-titulo="Sincronizar agora?"
                      data-confirm-botao="Sincronizar"
                      data-confirm-tipo="aviso"
                      data-confirm-icone="fa-sync-alt">
                    <input type="hidden" name="action" value="sincronizar">
                    <button type="submit" <?= $podeRodar ? '' : 'disabled' ?>
                        class="px-5 py-2.5 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl text-sm font-semibold disabled:opacity-40 disabled:cursor-not-allowed">
                        <i class="fas fa-sync-alt mr-1"></i> Sincronizar agora
                    </button>
                </form>
            </div>
        </div>

        <!-- Relatório -->
        <?php if ($relatorio): ?>
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-5">
                    <h3 class="text-xl font-bold text-slate-900">
                        <?= !empty($relatorio['simulacao']) ? 'Resultado da simulação' : 'Última sincronização' ?>
                    </h3>
                    <span class="text-xs text-gray-400">
                        <i class="fas fa-clock mr-1"></i><?= tv($relatorio['quando'] ?? '') ?> · modo <?= tv($relatorio['modo'] ?? '') ?>
                    </span>
                </div>

                <?php if (empty($relatorio['ok'])): ?>
                    <div class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
                        <i class="fas fa-times-circle mr-1"></i><?= tv($relatorio['erro'] ?? 'Erro desconhecido.') ?>
                    </div>
                <?php else: ?>

                    <?php if (!empty($relatorio['simulacao'])): ?>
                        <p class="mb-4 text-sm text-[#2B7FA6]"><i class="fas fa-info-circle mr-1"></i>Simulação: nada foi gravado.</p>
                    <?php endif; ?>

                    <!-- Totais -->
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
                        <?php
                        $totais = [
                            ['Lidos',       $relatorio['lidos'] ?? 0,       'text-slate-700'],
                            ['Novos',       $relatorio['criados'] ?? 0,     'text-emerald-600'],
                            ['Atualizados', $relatorio['atualizados'] ?? 0, 'text-[#2B7FA6]'],
                            ['Sem mudança', $relatorio['sem_mudanca'] ?? 0, 'text-gray-500'],
                            ['Ignorados',   $relatorio['ignorados'] ?? 0,   'text-amber-600'],
                        ];
                        foreach ($totais as [$rotulo, $valor, $cor]): ?>
                            <div class="p-4 bg-gray-50 rounded-xl text-center">
                                <p class="text-xs text-gray-500 uppercase font-bold"><?= tv($rotulo) ?></p>
                                <p class="text-3xl font-bold <?= $cor ?>"><?= (int) $valor ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Cursos que não existem na Central -->
                    <?php if (!empty($relatorio['cursosDesconhecidos'])): ?>
                        <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-800">
                            <p class="font-bold mb-1"><i class="fas fa-exclamation-triangle mr-1"></i>Cursos que não estão na lista da Central:</p>
                            <p><?= tv(implode(' · ', $relatorio['cursosDesconhecidos'])) ?></p>
                            <p class="mt-2 text-xs">Para corrigir, inclua o nome em <code>totvsMapaCursos()</code>, no arquivo <code>includes/integracoes/totvs-mapeamento.php</code>.</p>
                        </div>
                    <?php endif; ?>

                    <!-- Lista do que aconteceu -->
                    <?php if (!empty($relatorio['detalhes'])): ?>
                        <h4 class="font-bold text-slate-800 mb-3">O que aconteceu</h4>
                        <ul class="space-y-2 text-sm">
                            <?php
                            $icones = [
                                'criado'     => 'fa-user-plus text-emerald-500',
                                'atualizado' => 'fa-pen text-[#4A9FCA]',
                                'ignorado'   => 'fa-minus-circle text-amber-500',
                            ];
                            foreach ($relatorio['detalhes'] as $d): ?>
                                <li class="flex gap-2 p-2 border-b border-gray-100">
                                    <i class="fas <?= $icones[$d['tipo']] ?? 'fa-circle text-gray-300' ?> mt-1"></i>
                                    <span class="text-gray-700"><?= tv($d['texto']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php include 'includes/footer.php'; ?>
