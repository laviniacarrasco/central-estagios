<?php
/**
 * =============================================================================
 * BANCO DE DADOS — tela de controle (só funcionários)
 * -----------------------------------------------------------------------------
 * Mostra se a Central está usando o banco ou os arquivos JSON, em qual tabela
 * fica cada aba, o que ficou pendente enquanto o banco estava fora do ar e o
 * estado das integrações (TOTVS, Painéis/Relatórios por SQL).
 *
 * Botões: testar conexão, criar tabelas, migrar JSON -> banco, sincronizar
 * pendentes, exportar banco -> JSON, baixar cópia (zip), conferir os dois
 * lados e proteger as senhas que ainda estão em texto puro.
 * Toda ação fica registrada no log de ações dos funcionários.
 * Documentação: docs/BANCO-DE-DADOS.md
 * =============================================================================
 */
require_once 'includes/config.php';
require_once 'includes/melhorias.php';
require_once 'includes/integracoes/totvs-config.php';
checkAuth();
if (!perfilPode('gestao')) { header('Location: dashboard.php'); exit; }

$pageTitle = 'Banco de dados';
if (empty($_SESSION['csrf_banco'])) $_SESSION['csrf_banco'] = bin2hex(random_bytes(16));

$msg = ''; $erro = ''; $conferencia = null; $tabelasCriadas = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_banco'], (string) ($_POST['csrf'] ?? ''))) {
        $erro = 'A página expirou. Recarregue e tente de novo.';
    } else {
        $acao = (string) ($_POST['acao'] ?? '');
        @set_time_limit(600);
        try {
            $precisaBanco = !in_array($acao, ['baixar_zip', 'proteger_senhas'], true);
            if ($precisaBanco && !dadosBancoDisponivel(true)) {
                throw new RuntimeException('O banco não está disponível: ' . (dadosUltimoErro() ?: 'confira DADOS_DB_DSN, usuário e senha.'));
            }
            switch ($acao) {
                case 'testar':
                    $msg = 'Conexão OK com o banco (' . dadosBancoDriverNome() . ').';
                    break;
                case 'criar_tabelas':
                    $tabelasCriadas = dadosCriarTodasTabelas();
                    $msg = 'Tabelas conferidas: ' . count(array_filter($tabelasCriadas, fn($s) => $s === 'criada')) . ' criada(s).';
                    mhLogAdmin('Banco: criar tabelas', $msg);
                    break;
                case 'migrar':
                    $r = dadosMigrarJsonParaBanco(false);
                    $msg = "Migração (só o que faltava): {$r['copiadas']} copiada(s), {$r['puladas']} já estavam no banco, {$r['erros']} erro(s).";
                    mhLogAdmin('Banco: migrar JSON para o banco', $msg);
                    break;
                case 'migrar_sobrescrever':
                    $r = dadosMigrarJsonParaBanco(true);
                    $msg = "Migração (JSON substituindo o banco): {$r['copiadas']} copiada(s), {$r['erros']} erro(s).";
                    mhLogAdmin('Banco: JSON sobrescreveu o banco', $msg);
                    break;
                case 'sincronizar':
                    $r = dadosSincronizarPendentes();
                    $msg = "Sincronização: {$r['enviadas']} enviada(s), {$r['conflitos']} conflito(s) (guardados em data/_sistema/conflitos), {$r['erros']} erro(s).";
                    mhLogAdmin('Banco: sincronizar pendentes', $msg);
                    break;
                case 'exportar_json':
                    $r = dadosExportarBancoParaJson();
                    $msg = "Banco copiado para data/: {$r['copiadas']} arquivo(s), {$r['erros']} erro(s). No seu computador, faça o git push para levar ao Render.";
                    mhLogAdmin('Banco: exportar para JSON', $msg);
                    break;
                case 'conferir':
                    $conferencia = dadosConferir();
                    $msg = $conferencia ? count($conferencia) . ' chave(s) diferente(s) entre o banco e os JSON.' : 'Banco e JSON estão iguais.';
                    break;
                case 'baixar_zip':
                    if (!class_exists('ZipArchive')) throw new RuntimeException('O PHP deste servidor não tem a extensão zip.');
                    $chaves = dadosBancoDisponivel() ? dadosBancoListarChaves() : dadosJsonListarChaves();
                    $tmp = tempnam(sys_get_temp_dir(), 'central');
                    $zip = new ZipArchive();
                    $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
                    foreach ($chaves as $c) $zip->addFromString('data/' . $c . '.json', json_encode(dadosLer($c), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    $zip->close();
                    mhLogAdmin('Banco: cópia dos dados baixada (zip)', count($chaves) . ' arquivo(s)');
                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="central-dados-' . date('Y-m-d_His') . '.zip"');
                    header('Content-Length: ' . filesize($tmp));
                    readfile($tmp);
                    @unlink($tmp);
                    exit;
                case 'proteger_senhas':
                    $n = dadosOperar('usuarios', function (array &$lista) {
                        $c = 0;
                        foreach ($lista as $i => $u) {
                            $s = (string) ($u['senha'] ?? '');
                            if ($s !== '' && !preg_match('/^\$2[axy]\$/', $s)) { $lista[$i]['senha'] = password_hash($s, PASSWORD_DEFAULT); $c++; }
                        }
                        return $c;
                    });
                    $msg = (int) $n . ' senha(s) protegida(s) com hash. Ninguém perdeu o acesso: as senhas continuam as mesmas, só não aparecem mais no arquivo.';
                    mhLogAdmin('Banco: senhas protegidas com hash', (int) $n . ' senha(s)');
                    break;
                default:
                    $erro = 'Ação desconhecida.';
            }
        } catch (Throwable $e) {
            $erro = $e->getMessage();
        }
    }
}

$st = dadosStatus();

/* ---------- integrações ---------- */
function bancoTestar(string $prefixo): array {
    if (!bancoConfigurado($prefixo)) return ['nao', 'Não configurado'];
    try { bancoConectar($prefixo); return ['ok', 'Conectado']; }
    catch (Throwable $e) { return ['erro', $e->getMessage()]; }
}
$totvsModo = totvsModo();
$totvsProb = in_array($totvsModo, ['real', 'banco'], true) ? totvsProblemasConfig() : [];
$totvsDb   = $totvsModo === 'banco' ? bancoTestar('TOTVS_DB') : ['nao', 'Só é usado no modo "banco"'];
$biSql     = bancoTestar('BI_SQL');

function bdBadge(string $tipo, string $txt): string {
    $cls = ['ok' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'erro' => 'bg-red-50 text-red-700 border-red-200',
            'aviso' => 'bg-amber-50 text-amber-700 border-amber-200', 'nao' => 'bg-gray-100 text-gray-600 border-gray-200'][$tipo] ?? '';
    return '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border ' . $cls . '">' . htmlspecialchars($txt) . '</span>';
}

include 'includes/header.php';
$csrf = htmlspecialchars($_SESSION['csrf_banco']);
?>
<main class="ml-16 pt-16">
<div class="p-8 max-w-7xl mx-auto">

    <div class="mb-6">
        <h2 class="text-3xl font-bold text-slate-900 mb-1">Banco de dados</h2>
        <p class="text-slate-500">Onde a Central guarda os dados de cada aba, e o que fazer quando o banco cai. Detalhes em <code>docs/BANCO-DE-DADOS.md</code>.</p>
    </div>

    <?php if ($msg): ?><div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm"><i class="fas fa-check-circle mr-1"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm"><i class="fas fa-exclamation-circle mr-1"></i> <?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <!-- Situação -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="p-5 bg-white border border-gray-100 rounded-2xl">
            <p class="text-xs text-gray-500 uppercase font-bold mb-2">Onde os dados estão</p>
            <?php if ($st['driver'] === 'json'): ?>
                <?= bdBadge('aviso', 'Só arquivos JSON') ?>
                <p class="text-xs text-gray-400 mt-2"><?= $st['configurado'] ? 'DADOS_DRIVER=json força o modo JSON.' : 'Preencha DADOS_DB_DSN para ligar o banco.' ?></p>
            <?php elseif ($st['conectado']): ?>
                <?= bdBadge('ok', 'Banco de dados') ?>
                <p class="text-xs text-gray-400 mt-2">Com cópia nos JSON<?= dadosEspelhar() ? '' : ' (espelho desligado)' ?>.</p>
            <?php else: ?>
                <?= bdBadge('erro', 'Banco fora do ar: usando JSON') ?>
                <p class="text-xs text-red-500 mt-2"><?= htmlspecialchars($st['erro']) ?></p>
            <?php endif; ?>
        </div>
        <div class="p-5 bg-white border border-gray-100 rounded-2xl">
            <p class="text-xs text-gray-500 uppercase font-bold mb-2">Banco</p>
            <p class="text-2xl font-bold text-slate-900"><?= htmlspecialchars($st['banco'] ?: '—') ?></p>
            <p class="text-xs text-gray-400">mysql, pgsql, sqlite ou sqlsrv</p>
        </div>
        <div class="p-5 bg-white border border-gray-100 rounded-2xl">
            <p class="text-xs text-gray-500 uppercase font-bold mb-2">Pendentes de enviar ao banco</p>
            <p class="text-2xl font-bold <?= $st['pendentes'] ? 'text-amber-600' : 'text-slate-900' ?>"><?= (int) $st['pendentes'] ?></p>
            <p class="text-xs text-gray-400">Gravados no JSON com o banco fora do ar</p>
        </div>
        <div class="p-5 bg-white border border-gray-100 rounded-2xl">
            <p class="text-xs text-gray-500 uppercase font-bold mb-2">Última sincronização</p>
            <p class="text-sm font-semibold text-slate-900"><?= htmlspecialchars($st['sincronizacao']['quando'] ?? '—') ?></p>
            <?php if (!empty($st['sincronizacao'])): ?>
                <p class="text-xs text-gray-400"><?= (int) ($st['sincronizacao']['enviadas'] ?? 0) ?> enviada(s), <?= (int) ($st['sincronizacao']['conflitos'] ?? 0) ?> conflito(s)</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Ações -->
    <div class="bg-white border border-gray-100 rounded-2xl p-6 mb-8">
        <h3 class="font-bold text-slate-900 mb-4"><i class="fas fa-tools mr-2 text-[#4A9FCA]"></i>Ações</h3>
        <div class="flex flex-wrap gap-2">
            <?php
            $botoes = [
                ['testar', 'fa-plug', 'Testar conexão', '', ''],
                ['criar_tabelas', 'fa-table', 'Criar tabelas que faltam', '', ''],
                ['migrar', 'fa-upload', 'Migrar JSON → banco (só o que falta)', '', ''],
                ['sincronizar', 'fa-sync', 'Sincronizar pendentes', '', ''],
                ['conferir', 'fa-balance-scale', 'Conferir banco x JSON', '', ''],
                ['exportar_json', 'fa-download', 'Exportar banco → JSON (data/)', 'Os arquivos de data/ serão substituídos pelo conteúdo do banco.', 'aviso'],
                ['baixar_zip', 'fa-file-archive', 'Baixar cópia de todos os dados (zip)', 'O arquivo tem dados pessoais (CPF, e-mail, conversas). Guarde com sigilo. O download fica no log.', 'aviso'],
                ['migrar_sobrescrever', 'fa-exclamation-triangle', 'JSON substitui o banco', 'O conteúdo do banco será trocado pelo dos arquivos JSON. Use só se tiver certeza de que os JSON estão mais novos.', 'perigo'],
                ['proteger_senhas', 'fa-lock', 'Proteger senhas (hash)', 'As senhas em texto puro viram hash. Ninguém perde o acesso. Pode levar até 1 minuto.', 'aviso'],
            ];
            foreach ($botoes as [$acao, $ic, $rot, $conf, $tipo]): ?>
                <form method="POST" <?= $conf ? 'data-confirm="' . htmlspecialchars($conf) . '" data-confirm-titulo="' . htmlspecialchars($rot) . '?" data-confirm-botao="Continuar" data-confirm-tipo="' . $tipo . '" data-confirm-icone="' . $ic . '"' : '' ?>>
                    <input type="hidden" name="csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="acao" value="<?= $acao ?>">
                    <button type="submit" class="h-10 px-4 inline-flex items-center gap-2 border rounded-xl text-sm <?= $tipo === 'perigo' ? 'border-red-200 text-red-600 hover:bg-red-50' : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                        <i class="fas <?= $ic ?>"></i><?= htmlspecialchars($rot) ?>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
        <p class="text-xs text-gray-400 mt-4"><i class="fas fa-info-circle mr-1"></i>
            O normal é não precisar de nenhum botão: a migração, o espelho nos JSON e a sincronização acontecem sozinhos.
            No Render, o disco é apagado a cada deploy: "Exportar banco → JSON" serve para o seu computador (depois faça o <code>git push</code>).</p>

        <?php if ($tabelasCriadas): ?>
            <div class="mt-4 text-sm"><?php foreach ($tabelasCriadas as $t => $s): ?><p><code><?= htmlspecialchars($t) ?></code>: <?= htmlspecialchars($s) ?></p><?php endforeach; ?></div>
        <?php endif; ?>
        <?php if (is_array($conferencia) && $conferencia): ?>
            <div class="mt-4 max-h-64 overflow-y-auto text-sm border border-gray-100 rounded-xl p-3">
                <?php foreach ($conferencia as $c => $s): ?><p><code><?= htmlspecialchars($c) ?></code> — <?= htmlspecialchars($s) ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tabelas por aba -->
    <div class="bg-white border border-gray-100 rounded-2xl p-6 mb-8 panel-card" data-exportar="Tabelas por aba">
        <h3 class="font-bold text-slate-900 mb-1"><i class="fas fa-th-list mr-2 text-[#4A9FCA]"></i>Tabela de cada aba</h3>
        <p class="text-xs text-gray-400 mb-4">Para trocar o nome: edite <code>includes/banco-tabelas.php</code> ou crie a variável <code>DADOS_TABELA_&lt;GRUPO&gt;</code> (ex.: <code>DADOS_TABELA_USUARIOS</code>).</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-gray-400 border-b">
                    <th class="py-2 pr-3">Grupo</th><th class="py-2 pr-3">Aba / uso</th><th class="py-2 pr-3">Dados (chaves)</th><th class="py-2 pr-3">Tabela</th><th class="py-2 text-right">Linhas</th>
                </tr></thead>
                <tbody>
                <?php foreach ($st['tabelas'] as $t): ?>
                    <tr class="border-b border-gray-50 align-top">
                        <td class="py-2 pr-3 font-mono text-xs"><?= htmlspecialchars($t['grupo']) ?></td>
                        <td class="py-2 pr-3 text-gray-700"><?= htmlspecialchars($t['aba']) ?></td>
                        <td class="py-2 pr-3 font-mono text-xs text-gray-500"><?= htmlspecialchars(implode(', ', $t['chaves']) ?: '(o resto)') ?></td>
                        <td class="py-2 pr-3"><code><?= htmlspecialchars($t['tabela']) ?></code><?= $t['daEnv'] ? ' <span class="text-[10px] text-[#2B7FA6]">(variável)</span>' : '' ?></td>
                        <td class="py-2 text-right"><?= $t['linhas'] === null ? '—' : ($t['linhas'] < 0 ? '<span class="text-amber-600 text-xs">não existe</span>' : (int) $t['linhas']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Integrações -->
    <div class="bg-white border border-gray-100 rounded-2xl p-6 mb-8">
        <h3 class="font-bold text-slate-900 mb-4"><i class="fas fa-plug mr-2 text-[#4A9FCA]"></i>Integrações</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-gray-400 border-b"><th class="py-2 pr-3">Integração</th><th class="py-2 pr-3">Variáveis</th><th class="py-2 pr-3">Situação</th></tr></thead>
                <tbody>
                    <tr class="border-b border-gray-50 align-top">
                        <td class="py-2 pr-3"><strong>TOTVS</strong><p class="text-xs text-gray-400">Cadastro de Alunos, tabela "TOTVS: alunos" nos Painéis e origem de Relatórios</p></td>
                        <td class="py-2 pr-3 font-mono text-xs text-gray-500">TOTVS_MODO=<?= htmlspecialchars($totvsModo) ?><br>banco: TOTVS_DB_DSN, TOTVS_DB_USUARIO, TOTVS_DB_SENHA, TOTVS_DB_TABELA ou TOTVS_DB_CONSULTA</td>
                        <td class="py-2 pr-3">
                            <?= $totvsModo === 'desligado' ? bdBadge('nao', 'Desligado') : ($totvsModo === 'teste' ? bdBadge('aviso', 'Teste (data/totvs_exemplo.json)') : ($totvsProb ? bdBadge('erro', 'Falta: ' . implode(', ', $totvsProb)) : bdBadge('ok', 'Configurado (' . $totvsModo . ')'))) ?>
                            <?php if ($totvsModo === 'banco'): ?><p class="text-xs mt-1"><?= bdBadge($totvsDb[0], $totvsDb[1]) ?></p><?php endif; ?>
                        </td>
                    </tr>
                    <tr class="border-b border-gray-50 align-top">
                        <td class="py-2 pr-3"><strong>Banco / data lake (SQL)</strong><p class="text-xs text-gray-400">Consultas SQL nos Painéis e origem "Banco" dos Relatórios</p></td>
                        <td class="py-2 pr-3 font-mono text-xs text-gray-500">BI_SQL_DSN, BI_SQL_USUARIO, BI_SQL_SENHA</td>
                        <td class="py-2 pr-3"><?= bdBadge($biSql[0], $biSql[1]) ?></td>
                    </tr>
                    <tr class="border-b border-gray-50 align-top">
                        <td class="py-2 pr-3"><strong>Vagas externas</strong><p class="text-xs text-gray-400">Gupy / Adzuna (API, sem banco)</p></td>
                        <td class="py-2 pr-3 font-mono text-xs text-gray-500">VAGAS_FONTE=<?= htmlspecialchars(bancoEnv('VAGAS_FONTE', 'gupy')) ?></td>
                        <td class="py-2 pr-3"><?= bdBadge('ok', 'Guardadas na tabela ' . dadosTabelas()['vagas_sugeridas']) ?></td>
                    </tr>
                    <tr class="align-top">
                        <td class="py-2 pr-3"><strong>E-mails (Gmail)</strong><p class="text-xs text-gray-400">API, sem banco</p></td>
                        <td class="py-2 pr-3 font-mono text-xs text-gray-500">GMAIL_*</td>
                        <td class="py-2 pr-3"><?= bancoEnv('GMAIL_REFRESH_TOKEN') !== '' ? bdBadge('ok', 'Configurado') : bdBadge('nao', 'Não configurado') ?> <span class="text-xs text-gray-400">fila na tabela <?= htmlspecialchars(dadosTabelas()['alertas']) ?></span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
</main>
<?php include 'includes/exportar-excel.php'; ?>
<?php include 'includes/footer.php'; ?>
