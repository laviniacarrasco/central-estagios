<?php
/**
 * Painel do ADMIN DE EGRESSOS (usuario com is_admin=true e tipo="admin_egressos").
 * Abas: Painel (indicadores) | Vagas (moderacao) | Egressos (lista) | Cadastro
 */
require_once 'includes/config.php';
require_once 'includes/egressos.php';
require_once 'includes/alertas.php';
checkAuth();

if (!isAdminEgressos()) {
    header('Location: admin-dashboard.php');
    exit;
}

$pageTitle    = 'Painel de Egressos';
$fsaCourses   = getFsaCourses();
$opcoesStatus = egressoStatusOptions();

$abasValidas = ['painel', 'vagas', 'egressos', 'cadastro'];
$aba = in_array($_GET['tab'] ?? '', $abasValidas, true) ? $_GET['tab'] : 'painel';

$mensagens = [
    'vaga_aprovada'  => 'Vaga aprovada e publicada no mural.',
    'vaga_rejeitada' => 'Vaga rejeitada. O egresso verá o motivo e poderá corrigir.',
    'vaga_encerrada' => 'Vaga encerrada.',
    'vaga_removida'  => 'Vaga removida.',
    'egresso_criado' => 'Egresso cadastrado.',
    'convertido'     => 'Aluno convertido em egresso.',
    'revertido'      => 'Egresso devolvido para a base de alunos.',
];
$erros = [
    'dados'      => 'Preencha todos os campos obrigatórios com valores válidos.',
    'cpf_existe' => 'Já existe um usuário com esse CPF.',
    'ra_nao'     => 'Não encontrei nenhum aluno com esse RA.',
    'ja_egresso' => 'Esse usuário já é egresso ou é administrador.',
    'senha'      => 'A senha provisória precisa ter ao menos 6 caracteres.',
];

function voltar(string $tab, string $chave, bool $erro = false): void {
    header('Location: admin-egressos.php?tab=' . $tab . '&' . ($erro ? 'err=' : 'msg=') . $chave);
    exit;
}

/* =========================================================================
 * ACOES (POST)
 * ========================================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['action'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);

    /* ---- moderacao de vagas ---- */
    if (in_array($acao, ['aprovar_vaga', 'rejeitar_vaga', 'encerrar_vaga', 'remover_vaga'], true)) {
        $vagas = egressoVagas();
        $vagaAlvo = null;

        if ($acao === 'remover_vaga') {
            $vagas = array_values(array_filter($vagas, fn($v) => ($v['id'] ?? 0) != $id));
            egressoSalvarVagas($vagas);
            voltar('vagas', 'vaga_removida');
        }

        foreach ($vagas as &$v) {
            if (($v['id'] ?? 0) != $id) continue;
            if ($acao === 'aprovar_vaga') {
                $v['status']         = 'aprovada';
                $v['publishedAt']    = date('d/m/Y');
                $v['publishedTs']    = time();
                $v['aprovadoPor']    = $_SESSION['user_name'] ?? '';
                $v['motivoRejeicao'] = '';
            } elseif ($acao === 'rejeitar_vaga') {
                $v['status']         = 'rejeitada';
                $v['motivoRejeicao'] = mb_substr(trim($_POST['motivo'] ?? ''), 0, 500);
            } else {
                $v['status']      = 'encerrada';
                $v['encerradaEm'] = date('d/m/Y H:i');
            }
            $vagaAlvo = $v;
            break;
        }
        unset($v);
        egressoSalvarVagas($vagas);
        if ($vagaAlvo && $acao === 'aprovar_vaga')  { notificarEgressoVaga($vagaAlvo, true); alertasEnfileirarVagaNova($vagaAlvo, 'egresso'); }
        if ($vagaAlvo && $acao === 'rejeitar_vaga') notificarEgressoVaga($vagaAlvo, false);
        $chaves = ['aprovar_vaga' => 'vaga_aprovada', 'rejeitar_vaga' => 'vaga_rejeitada', 'encerrar_vaga' => 'vaga_encerrada'];
        voltar('vagas', $chaves[$acao]);
    }

    /* ---- cadastrar egresso novo ---- */
    if ($acao === 'criar_egresso') {
        $nome  = mb_substr(trim($_POST['nome'] ?? ''), 0, 120);
        $cpf   = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $curso = trim($_POST['curso'] ?? '');
        $ano   = trim($_POST['anoConclusao'] ?? '');
        $senha = (string) ($_POST['senha'] ?? '');

        if ($nome === '' || strlen($cpf) !== 11 || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || !in_array($curso, $fsaCourses, true) || !preg_match('/^\d{4}$/', $ano)) {
            voltar('cadastro', 'dados', true);
        }
        if (strlen($senha) < 6) voltar('cadastro', 'senha', true);

        $usuarios = loadData('usuarios');
        $maxId = 0;
        foreach ($usuarios as $u) {
            if (($u['cpf'] ?? '') === $cpf) voltar('cadastro', 'cpf_existe', true);
            $maxId = max($maxId, (int) ($u['id'] ?? 0));
        }

        $usuarios[] = [
            'id'                 => $maxId + 1,
            'nome'               => $nome,
            'cpf'                => $cpf,
            'senha'              => password_hash($senha, PASSWORD_DEFAULT),
            'is_admin'           => false,
            'tipo'               => 'egresso',
            'email'              => $email,
            'curso'              => $curso,
            'ra'                 => trim($_POST['ra'] ?? '') ?: '—',
            'periodo'            => '—',
            'turno'              => '—',
            'situacao'           => 'Egresso',
            'dataMatricula'      => null,
            'anoConclusao'       => $ano,
            'statusProfissional' => '',
            'cadastradoEm'       => date('Y-m-d'),
        ];
        saveData('usuarios', $usuarios);
        voltar('cadastro', 'egresso_criado');
    }

    /* ---- converter aluno existente em egresso ---- */
    if ($acao === 'converter_aluno') {
        $ra  = trim($_POST['ra'] ?? '');
        $ano = trim($_POST['anoConclusao'] ?? '');
        if ($ra === '' || !preg_match('/^\d{4}$/', $ano)) voltar('cadastro', 'dados', true);

        $usuarios = loadData('usuarios');
        $achou = false;
        $jaEra = false;
        foreach ($usuarios as &$u) {
            if (($u['ra'] ?? '—') !== '—' && trim((string) ($u['ra'] ?? '')) === $ra) {
                if (!empty($u['is_admin']) || ($u['tipo'] ?? '') === 'egresso') {
                    $jaEra = true;
                    break;
                }
                $u['tipo']              = 'egresso';
                $u['situacao']          = 'Egresso';
                $u['anoConclusao']      = $ano;
                $u['convertidoDeAluno'] = true;
                $u['cadastradoEm']      = date('Y-m-d');
                $achou = true;
                break;
            }
        }
        unset($u);
        if ($jaEra) voltar('cadastro', 'ja_egresso', true);
        if (!$achou) voltar('cadastro', 'ra_nao', true);
        saveData('usuarios', $usuarios);
        voltar('cadastro', 'convertido');
    }

    /* ---- devolver para alunos (so quem foi convertido) ---- */
    if ($acao === 'reverter_egresso') {
        $usuarios = loadData('usuarios');
        foreach ($usuarios as &$u) {
            if (($u['id'] ?? 0) == $id && ($u['tipo'] ?? '') === 'egresso' && !empty($u['convertidoDeAluno'])) {
                unset($u['tipo'], $u['convertidoDeAluno'], $u['anoConclusao'], $u['cadastradoEm']);
                $u['situacao'] = 'Regular';
                break;
            }
        }
        unset($u);
        saveData('usuarios', $usuarios);
        voltar('egressos', 'revertido');
    }
}

/* =========================================================================
 * INDICADORES
 * ========================================================================= */
$usuarios = loadData('usuarios');
$egressos = array_values(array_filter($usuarios, fn($u) => ($u['tipo'] ?? '') === 'egresso'));
$vagas    = egressoVagas();
usort($vagas, fn($a, $b) => ($b['createdTs'] ?? 0) <=> ($a['createdTs'] ?? 0));

$totalEg  = count($egressos);
$grupos   = ['na_area' => 0, 'fora_area' => 0, 'empreendedor' => 0, 'estudando' => 0, 'buscando' => 0, 'nao_informado' => 0];
$porCurso = [];
$porAno   = [];
$empresas = [];
$somaMeses = 0; $qtdMeses = 0;
$mentores = 0;
$desatualizados = [];
$limiteAtualizacao = strtotime('-12 months');

foreach ($egressos as $e) {
    $g = egressoGrupoStatus($e['statusProfissional'] ?? '');
    $grupos[$g]++;
    $ocupado = in_array($g, ['na_area', 'fora_area', 'empreendedor'], true);

    $curso = trim($e['curso'] ?? '') ?: 'Sem curso';
    if (!isset($porCurso[$curso])) $porCurso[$curso] = ['total' => 0, 'ocupados' => 0, 'na_area' => 0, 'meses' => 0, 'qm' => 0];
    $porCurso[$curso]['total']++;
    if ($ocupado) $porCurso[$curso]['ocupados']++;
    if ($g === 'na_area') $porCurso[$curso]['na_area']++;

    $ano = trim((string) ($e['anoConclusao'] ?? '')) ?: 'N/I';
    $porAno[$ano] = ($porAno[$ano] ?? 0) + 1;

    if ($ocupado && trim($e['empresaAtual'] ?? '') !== '') {
        $chave = mb_strtolower(trim($e['empresaAtual']));
        if (!isset($empresas[$chave])) $empresas[$chave] = ['nome' => trim($e['empresaAtual']), 'qtd' => 0];
        $empresas[$chave]['qtd']++;
    }

    if (isset($e['mesesAteColocacao']) && $e['mesesAteColocacao'] !== null && $e['mesesAteColocacao'] !== '') {
        $somaMeses += (int) $e['mesesAteColocacao'];
        $qtdMeses++;
        $porCurso[$curso]['meses'] += (int) $e['mesesAteColocacao'];
        $porCurso[$curso]['qm']++;
    }

    if (!empty($e['aceitaMentoria'])) $mentores++;

    $atualizadoEm = !empty($e['perfilAtualizadoEm']) ? strtotime($e['perfilAtualizadoEm']) : 0;
    if (!$atualizadoEm || $atualizadoEm < $limiteAtualizacao) $desatualizados[] = $e;
}

$ocupadosTotal = $grupos['na_area'] + $grupos['fora_area'] + $grupos['empreendedor'];
$taxaEmpreg    = $totalEg ? (int) round($ocupadosTotal / $totalEg * 100) : 0;
$taxaNaArea    = $ocupadosTotal ? (int) round($grupos['na_area'] / $ocupadosTotal * 100) : 0;
$mediaMeses    = $qtdMeses ? round($somaMeses / $qtdMeses, 1) : null;

uasort($porCurso, fn($a, $b) => $b['total'] <=> $a['total']);
ksort($porAno);
uasort($empresas, fn($a, $b) => $b['qtd'] <=> $a['qtd']);
$topEmpresas = array_slice(array_values($empresas), 0, 8);

/* vagas */
$contVagas = ['pendente' => 0, 'aprovada' => 0, 'rejeitada' => 0, 'encerrada' => 0, 'expirada' => 0];
$candidatosGerados = 0;
$divulgadores = [];
foreach ($vagas as $v) {
    $s = egressoVagaStatusEfetivo($v);
    if (isset($contVagas[$s])) $contVagas[$s]++;
    $candidatosGerados += (int) ($v['applicants'] ?? 0);
    if (in_array($s, ['aprovada', 'encerrada', 'expirada'], true)) {
        $k = (int) ($v['egressoId'] ?? 0);
        if (!isset($divulgadores[$k])) $divulgadores[$k] = ['nome' => $v['egressoName'] ?? '—', 'qtd' => 0, 'cands' => 0];
        $divulgadores[$k]['qtd']++;
        $divulgadores[$k]['cands'] += (int) ($v['applicants'] ?? 0);
    }
}
uasort($divulgadores, fn($a, $b) => $b['qtd'] <=> $a['qtd']);
$topDivulgadores = array_slice(array_values($divulgadores), 0, 5);
$pendentes = array_values(array_filter($vagas, fn($v) => ($v['status'] ?? '') === 'pendente'));

/* dados para os graficos */
$dadosGraficos = [
    'status' => [
        'labels'  => ['Atuando na área', 'Fora da área', 'Empreendedor(a)', 'Estudando', 'Buscando', 'Não informado'],
        'valores' => array_values($grupos),
    ],
    'cursos' => [
        'labels'   => array_keys($porCurso),
        'total'    => array_column(array_values($porCurso), 'total'),
        'ocupados' => array_column(array_values($porCurso), 'ocupados'),
    ],
    'anos' => ['labels' => array_keys($porAno), 'valores' => array_values($porAno)],
];

/* linhas da tabela de egressos (busca/filtro/CSV no navegador) */
$linhasEgressos = array_map(function ($e) {
    return [
        'id'         => (int) $e['id'],
        'nome'       => $e['nome'] ?? '',
        'email'      => $e['email'] ?? '',
        'curso'      => $e['curso'] ?? '',
        'ano'        => (string) ($e['anoConclusao'] ?? ''),
        'status'     => egressoStatusLabel($e['statusProfissional'] ?? ''),
        'grupo'      => egressoGrupoStatus($e['statusProfissional'] ?? ''),
        'empresa'    => $e['empresaAtual'] ?? '',
        'cargo'      => $e['cargoAtual'] ?? '',
        'cidade'     => $e['cidade'] ?? '',
        'linkedin'   => $e['linkedin'] ?? '',
        'mentoria'   => !empty($e['aceitaMentoria']) ? 'Sim' : 'Não',
        'meses'      => isset($e['mesesAteColocacao']) ? (string) $e['mesesAteColocacao'] : '',
        'atualizado' => !empty($e['perfilAtualizadoEm']) ? date('d/m/Y', strtotime($e['perfilAtualizadoEm'])) : '—',
        'reverter'   => !empty($e['convertidoDeAluno']),
    ];
}, $egressos);
usort($linhasEgressos, fn($a, $b) => strcasecmp($a['nome'], $b['nome']));

include 'includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-7xl mx-auto">

        <div class="mb-6 animate-fade-in">
            <h2 class="text-3xl font-bold text-slate-900 mb-2">Painel de Egressos</h2>
            <p class="text-slate-500 font-medium">Acompanhe a trajetória dos ex-alunos e modere as vagas que eles divulgam.</p>
        </div>

        <?php if (!empty($_GET['msg']) && isset($mensagens[$_GET['msg']])): ?>
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm">
                <i class="fas fa-check-circle mr-1"></i> <?= egh($mensagens[$_GET['msg']]) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($_GET['err']) && isset($erros[$_GET['err']])): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
                <i class="fas fa-exclamation-circle mr-1"></i> <?= egh($erros[$_GET['err']]) ?>
            </div>
        <?php endif; ?>

        <div class="flex flex-wrap gap-2 mb-8 border-b border-gray-200">
            <?php
            $rotulosAbas = ['painel' => ['fa-chart-pie', 'Painel'], 'vagas' => ['fa-briefcase', 'Vagas'], 'egressos' => ['fa-users', 'Egressos'], 'cadastro' => ['fa-user-plus', 'Cadastro']];
            foreach ($rotulosAbas as $chave => $info): ?>
                <button type="button" data-tab="<?= $chave ?>"
                        class="tab-btn px-5 py-3 text-sm font-semibold border-b-2 -mb-px <?= $aba === $chave ? 'border-[#4A9FCA] text-[#4A9FCA]' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                    <i class="fas <?= $info[0] ?> mr-1"></i> <?= $info[1] ?>
                    <?php if ($chave === 'vagas' && $contVagas['pendente'] > 0): ?>
                        <span class="ml-1 px-2 py-0.5 bg-red-500 text-white rounded-full text-xs"><?= $contVagas['pendente'] ?></span>
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- ===================== PAINEL ===================== -->
        <section id="tab-painel" class="tab-panel <?= $aba === 'painel' ? '' : 'hidden' ?>">

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                <div class="p-5 bg-[#58A1D4] text-white rounded-2xl">
                    <p class="text-white/90 text-sm font-medium">Egressos cadastrados</p>
                    <p class="text-4xl font-bold"><?= $totalEg ?></p>
                </div>
                <div class="p-5 bg-[#27AE60] text-white rounded-2xl">
                    <p class="text-white/90 text-sm font-medium">Taxa de empregabilidade</p>
                    <p class="text-4xl font-bold"><?= $taxaEmpreg ?>%</p>
                </div>
                <div class="p-5 bg-purple-500 text-white rounded-2xl">
                    <p class="text-white/90 text-sm font-medium">Atuando na área (dos ocupados)</p>
                    <p class="text-4xl font-bold"><?= $taxaNaArea ?>%</p>
                </div>
                <div class="p-5 bg-[#E35444] text-white rounded-2xl">
                    <p class="text-white/90 text-sm font-medium">Buscando oportunidade</p>
                    <p class="text-4xl font-bold"><?= $grupos['buscando'] ?></p>
                </div>
            </div>
            <p class="text-xs text-gray-400 mb-8">
                Empregabilidade = egressos empregados, estagiando ou empreendendo ÷ total de egressos.
                Quem não informou a situação conta no total.
            </p>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="p-5 bg-white border border-gray-100 rounded-2xl">
                    <p class="text-gray-500 text-sm">Vagas no mural</p>
                    <p class="text-3xl font-bold text-slate-900"><?= $contVagas['aprovada'] ?></p>
                </div>
                <div class="p-5 bg-white border border-gray-100 rounded-2xl">
                    <p class="text-gray-500 text-sm">Aguardando moderação</p>
                    <p class="text-3xl font-bold <?= $contVagas['pendente'] ? 'text-red-500' : 'text-slate-900' ?>"><?= $contVagas['pendente'] ?></p>
                </div>
                <div class="p-5 bg-white border border-gray-100 rounded-2xl">
                    <p class="text-gray-500 text-sm">Candidaturas de alunos geradas</p>
                    <p class="text-3xl font-bold text-slate-900"><?= $candidatosGerados ?></p>
                </div>
                <div class="p-5 bg-white border border-gray-100 rounded-2xl">
                    <p class="text-gray-500 text-sm">Tempo médio até 1ª colocação</p>
                    <p class="text-3xl font-bold text-slate-900"><?= $mediaMeses !== null ? str_replace('.', ',', (string) $mediaMeses) . ' meses' : '—' ?></p>
                    <p class="text-xs text-gray-400"><?= $qtdMeses ?> resposta(s)</p>
                </div>
            </div>

            <?php if ($totalEg === 0): ?>
                <div class="p-12 text-center border-2 border-dashed border-gray-200 rounded-3xl bg-white mb-8">
                    <i class="fas fa-user-graduate text-5xl text-gray-200 mb-3"></i>
                    <p class="text-slate-600 font-semibold mb-1">Ainda não há egressos cadastrados</p>
                    <p class="text-sm text-gray-500">Use a aba Cadastro para criar um egresso ou converter um aluno formado.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-2xl border border-gray-100">
                        <h3 class="font-bold text-slate-900 mb-4">Situação profissional</h3>
                        <div style="height:260px"><canvas id="chartStatus"></canvas></div>
                    </div>
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 lg:col-span-2">
                        <h3 class="font-bold text-slate-900 mb-4">Egressos por curso (total x ocupados)</h3>
                        <div style="height:260px"><canvas id="chartCursos"></canvas></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-2xl border border-gray-100">
                        <h3 class="font-bold text-slate-900 mb-4">Egressos por ano de conclusão</h3>
                        <div style="height:240px"><canvas id="chartAnos"></canvas></div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl border border-gray-100 lg:col-span-2 overflow-x-auto">
                        <h3 class="font-bold text-slate-900 mb-4">Empregabilidade por curso</h3>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500 border-b">
                                    <th class="py-2 pr-3">Curso</th><th class="py-2 pr-3">Egressos</th>
                                    <th class="py-2 pr-3">Empregabilidade</th><th class="py-2 pr-3">Na área</th><th class="py-2">Meses p/ 1ª colocação</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($porCurso as $nomeCurso => $c): ?>
                                <tr class="border-b last:border-0">
                                    <td class="py-2 pr-3 font-medium text-gray-800"><?= egh($nomeCurso) ?></td>
                                    <td class="py-2 pr-3"><?= $c['total'] ?></td>
                                    <td class="py-2 pr-3"><?= (int) round($c['ocupados'] / $c['total'] * 100) ?>%</td>
                                    <td class="py-2 pr-3"><?= $c['ocupados'] ? (int) round($c['na_area'] / $c['ocupados'] * 100) . '%' : '—' ?></td>
                                    <td class="py-2"><?= $c['qm'] ? str_replace('.', ',', (string) round($c['meses'] / $c['qm'], 1)) : '—' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="bg-white p-6 rounded-2xl border border-gray-100">
                        <h3 class="font-bold text-slate-900 mb-1">Onde nossos egressos trabalham</h3>
                        <p class="text-xs text-gray-400 mb-4">Útil para buscar parcerias de estágio</p>
                        <?php if (empty($topEmpresas)): ?>
                            <p class="text-sm text-gray-400">Nenhum egresso informou a empresa ainda.</p>
                        <?php else: foreach ($topEmpresas as $emp): ?>
                            <div class="flex justify-between text-sm py-1.5 border-b last:border-0">
                                <span class="text-gray-700"><?= egh($emp['nome']) ?></span><strong><?= $emp['qtd'] ?></strong>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>

                    <div class="bg-white p-6 rounded-2xl border border-gray-100">
                        <h3 class="font-bold text-slate-900 mb-1">Egressos que mais divulgam vagas</h3>
                        <p class="text-xs text-gray-400 mb-4">Quem vale a pena reconhecer</p>
                        <?php if (empty($topDivulgadores)): ?>
                            <p class="text-sm text-gray-400">Nenhuma vaga publicada ainda.</p>
                        <?php else: foreach ($topDivulgadores as $d): ?>
                            <div class="flex justify-between text-sm py-1.5 border-b last:border-0">
                                <span class="text-gray-700"><?= egh($d['nome']) ?></span>
                                <span class="text-gray-500"><strong><?= $d['qtd'] ?></strong> vaga(s) &bull; <?= $d['cands'] ?> cand.</span>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>

                    <div class="bg-white p-6 rounded-2xl border border-gray-100">
                        <h3 class="font-bold text-slate-900 mb-1">Engajamento</h3>
                        <p class="text-xs text-gray-400 mb-4">Relacionamento com a base de egressos</p>
                        <div class="flex justify-between text-sm py-1.5 border-b"><span class="text-gray-700">Aceitam mentoria</span><strong><?= $mentores ?></strong></div>
                        <div class="flex justify-between text-sm py-1.5 border-b"><span class="text-gray-700">Perfil desatualizado (+12 meses)</span><strong class="<?= count($desatualizados) ? 'text-amber-600' : '' ?>"><?= count($desatualizados) ?></strong></div>
                        <div class="flex justify-between text-sm py-1.5"><span class="text-gray-700">Situação não informada</span><strong><?= $grupos['nao_informado'] ?></strong></div>
                        <button type="button" data-tab="egressos" class="mt-4 w-full px-4 py-2 border border-gray-200 text-gray-600 rounded-xl text-sm hover:bg-gray-50">
                            Ver lista de egressos
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <!-- ===================== VAGAS ===================== -->
        <section id="tab-vagas" class="tab-panel <?= $aba === 'vagas' ? '' : 'hidden' ?>">

            <h3 class="text-xl font-bold text-slate-900 mb-4">Aguardando análise (<?= count($pendentes) ?>)</h3>
            <?php if (empty($pendentes)): ?>
                <div class="p-8 text-center border-2 border-dashed border-gray-200 rounded-2xl bg-white text-slate-500 text-sm mb-10">
                    Nenhuma vaga pendente. Tudo em dia!
                </div>
            <?php else: ?>
                <div class="space-y-4 mb-10">
                    <?php foreach ($pendentes as $v): $lk = egressoNormalizarLink($v['applicationLink'] ?? ''); ?>
                        <div class="p-6 bg-white border-2 border-amber-200 rounded-2xl">
                            <div class="flex flex-wrap justify-between gap-3 mb-3">
                                <div>
                                    <h4 class="text-lg font-bold text-slate-900"><?= egh($v['title'] ?? '') ?></h4>
                                    <p class="text-slate-500 font-semibold"><?= egh($v['company'] ?? '') ?> &bull; <?= egh($v['categoria'] ?? '') ?> &bull; <?= egh($v['type'] ?? '') ?></p>
                                </div>
                                <p class="text-xs text-gray-400">Enviada em <?= egh($v['createdAt'] ?? '') ?></p>
                            </div>
                            <p class="text-xs text-gray-500 mb-3">
                                <i class="fas fa-user-graduate mr-1"></i> <?= egh($v['egressoName'] ?? '') ?> &bull; <?= egh($v['egressoCurso'] ?? '') ?> <?= !empty($v['egressoAno']) ? '(' . egh($v['egressoAno']) . ')' : '' ?>
                            </p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm text-gray-600 mb-4">
                                <p><strong>Local:</strong> <?= egh($v['location'] ?? '—') ?></p>
                                <p><strong>Carga:</strong> <?= egh($v['hours'] ?? '—') ?> &bull; <strong>Remuneração:</strong> <?= egh($v['salary'] ?? '—') ?></p>
                                <p class="md:col-span-2"><strong>Cursos:</strong> <?= egh(($v['courses'] ?? '') !== '' ? $v['courses'] : 'Todos') ?></p>
                                <p class="md:col-span-2 whitespace-pre-line"><strong>Descrição:</strong> <?= egh($v['description'] ?? '') ?></p>
                                <p class="md:col-span-2"><strong>Candidatura:</strong>
                                    <?php if ($lk !== ''): ?>
                                        <a href="<?= egh($lk) ?>" target="_blank" rel="noopener" class="text-[#4A9FCA] hover:underline break-all"><?= egh($lk) ?></a>
                                    <?php else: ?>—<?php endif; ?>
                                </p>
                            </div>
                            <div class="flex gap-3">
                                <form method="POST">
                                    <input type="hidden" name="action" value="aprovar_vaga">
                                    <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                                    <button class="px-5 py-2 bg-[#27AE60] text-white rounded-xl text-sm font-semibold"><i class="fas fa-check mr-1"></i> Aprovar e publicar</button>
                                </form>
                                <button type="button" data-id="<?= (int) $v['id'] ?>" onclick="abrirRejeicao(this.dataset.id)"
                                        class="px-5 py-2 border border-red-200 text-red-600 rounded-xl text-sm font-semibold hover:bg-red-50">
                                    <i class="fas fa-times mr-1"></i> Rejeitar
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h3 class="text-xl font-bold text-slate-900 mb-4">Todas as vagas de egressos</h3>
            <div class="bg-white border border-gray-100 rounded-2xl overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b bg-gray-50">
                            <th class="p-3">Vaga</th><th class="p-3">Egresso</th><th class="p-3">Status</th><th class="p-3">Cand.</th><th class="p-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($vagas)): ?>
                        <tr><td colspan="5" class="p-8 text-center text-gray-400">Nenhuma vaga divulgada ainda.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($vagas as $v): $info = egressoVagaStatusInfo(egressoVagaStatusEfetivo($v)); ?>
                        <tr class="border-b last:border-0">
                            <td class="p-3">
                                <p class="font-medium text-gray-800"><?= egh($v['title'] ?? '') ?></p>
                                <p class="text-xs text-gray-500"><?= egh($v['company'] ?? '') ?> &bull; <?= egh($v['createdAt'] ?? '') ?></p>
                                <?= vagaValidadeHtml($v) ?>
                            </td>
                            <td class="p-3 text-gray-600"><?= egh($v['egressoName'] ?? '') ?></td>
                            <td class="p-3">
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold border <?= $info[1] ?>"><?= $info[0] ?></span>
                                <?php if (($v['status'] ?? '') === 'rejeitada' && !empty($v['motivoRejeicao'])): ?>
                                    <p class="text-xs text-red-500 mt-1 max-w-xs"><?= egh($v['motivoRejeicao']) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="p-3"><?= (int) ($v['applicants'] ?? 0) ?></td>
                            <td class="p-3">
                                <div class="flex justify-end gap-2">
                                    <?php if (($v['status'] ?? '') === 'aprovada'): ?>
                                        <form method="POST" data-confirm="A vaga sai do mural e deixa de aparecer para os alunos." data-confirm-titulo="Encerrar esta vaga?" data-confirm-botao="Encerrar" data-confirm-tipo="aviso" data-confirm-icone="fa-ban">
                                            <input type="hidden" name="action" value="encerrar_vaga"><input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                                            <button class="px-3 py-1.5 border border-gray-200 text-gray-600 rounded-lg text-xs hover:bg-gray-50">Encerrar</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" data-confirm="A vaga será removida definitivamente. Esta ação não pode ser desfeita." data-confirm-titulo="Remover esta vaga?" data-confirm-botao="Remover" data-confirm-tipo="perigo" data-confirm-icone="fa-trash-alt">
                                        <input type="hidden" name="action" value="remover_vaga"><input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                                        <button class="px-3 py-1.5 text-red-500 hover:bg-red-50 rounded-lg text-xs">Remover</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ===================== EGRESSOS ===================== -->
        <section id="tab-egressos" class="tab-panel <?= $aba === 'egressos' ? '' : 'hidden' ?>">
            <div class="flex flex-wrap gap-3 mb-4">
                <input type="text" id="fBusca" placeholder="Buscar por nome, empresa ou e-mail..." class="flex-1 min-w-[220px] h-11 px-4 bg-white border border-gray-200 rounded-xl">
                <select id="fCurso" class="h-11 px-3 bg-white border border-gray-200 rounded-xl">
                    <option value="">Todos os cursos</option>
                    <?php foreach ($fsaCourses as $c): ?><option value="<?= egh($c) ?>"><?= egh($c) ?></option><?php endforeach; ?>
                </select>
                <select id="fStatus" class="h-11 px-3 bg-white border border-gray-200 rounded-xl">
                    <option value="">Todas as situações</option>
                    <option value="na_area">Atuando na área</option>
                    <option value="fora_area">Fora da área</option>
                    <option value="empreendedor">Empreendedor(a)</option>
                    <option value="estudando">Estudando</option>
                    <option value="buscando">Buscando oportunidade</option>
                    <option value="nao_informado">Não informado</option>
                </select>
                <button type="button" onclick="exportarCsv()" class="h-11 px-5 bg-white border border-gray-200 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-50">
                    <i class="fas fa-file-csv mr-1"></i> Exportar CSV
                </button>
            </div>
            <p class="text-xs text-gray-400 mb-3"><span id="contEg"><?= $totalEg ?></span> egresso(s) na lista</p>

            <div class="bg-white border border-gray-100 rounded-2xl overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b bg-gray-50">
                            <th class="p-3">Nome</th><th class="p-3">Curso / Turma</th><th class="p-3">Situação</th>
                            <th class="p-3">Empresa / Cargo</th><th class="p-3">Atualizado</th><th class="p-3"></th>
                        </tr>
                    </thead>
                    <tbody id="tbEgressos"></tbody>
                </table>
            </div>
        </section>

        <!-- ===================== CADASTRO ===================== -->
        <section id="tab-cadastro" class="tab-panel <?= $aba === 'cadastro' ? '' : 'hidden' ?>">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                <div class="bg-white p-8 rounded-2xl border border-gray-100">
                    <h3 class="text-xl font-bold text-slate-900 mb-1">Converter aluno formado</h3>
                    <p class="text-sm text-slate-500 mb-6">O aluno mantém login e senha e passa a ver só a área de egressos. Ele precisa entrar de novo.</p>
                    <form method="POST" class="space-y-4" data-confirm="Confirme para converter este aluno em egresso." data-confirm-titulo="Converter em egresso?" data-confirm-botao="Converter" data-confirm-tipo="aviso" data-confirm-icone="fa-user-graduate">
                        <input type="hidden" name="action" value="converter_aluno">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">RA do aluno *</label>
                            <input type="text" name="ra" required class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ano de conclusão *</label>
                            <input type="text" name="anoConclusao" required maxlength="4" inputmode="numeric" placeholder="<?= date('Y') ?>" class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                        </div>
                        <button class="px-6 h-11 bg-[#4A9FCA] text-white rounded-xl font-semibold">
                            <i class="fas fa-exchange-alt mr-1"></i> Converter em egresso
                        </button>
                    </form>
                </div>

                <div class="bg-white p-8 rounded-2xl border border-gray-100">
                    <h3 class="text-xl font-bold text-slate-900 mb-1">Cadastrar egresso novo</h3>
                    <p class="text-sm text-slate-500 mb-6">Para ex-alunos que ainda não estão na base. Passe a senha provisória a ele; depois ele pode trocar em "Esqueci minha senha".</p>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="criar_egresso">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nome completo *</label>
                            <input type="text" name="nome" required maxlength="120" class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">CPF *</label>
                                <input type="text" name="cpf" required maxlength="14" inputmode="numeric" class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">RA <span class="text-xs text-gray-400 font-normal">(se tiver)</span></label>
                                <input type="text" name="ra" class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">E-mail *</label>
                            <input type="email" name="email" required class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Curso *</label>
                                <select name="curso" required class="w-full h-11 px-3 border border-gray-200 rounded-lg bg-white">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($fsaCourses as $c): ?><option value="<?= egh($c) ?>"><?= egh($c) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ano *</label>
                                <input type="text" name="anoConclusao" required maxlength="4" inputmode="numeric" class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Senha provisória * <span class="text-xs text-gray-400 font-normal">(mín. 6 caracteres)</span></label>
                            <input type="text" name="senha" required minlength="6" autocomplete="off" class="w-full h-11 px-3 border border-gray-200 rounded-lg">
                        </div>
                        <button class="px-6 h-11 bg-[#27AE60] text-white rounded-xl font-semibold">
                            <i class="fas fa-user-plus mr-1"></i> Cadastrar egresso
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </div>
</main>

<!-- Modal de rejeicao -->
<div id="modalRejeicao" class="fixed inset-0 z-[999] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40" onclick="fecharRejeicao()"></div>
    <form method="POST" class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 z-10">
        <h3 class="text-lg font-bold text-slate-900 mb-1">Rejeitar vaga</h3>
        <p class="text-sm text-gray-500 mb-4">O egresso verá o motivo e poderá corrigir e reenviar.</p>
        <input type="hidden" name="action" value="rejeitar_vaga">
        <input type="hidden" name="id" id="rejId">
        <textarea name="motivo" rows="4" maxlength="500" required placeholder="Ex: faltou informar a remuneração e o link de candidatura."
                  class="w-full px-3 py-2 border border-gray-200 rounded-lg mb-4"></textarea>
        <div class="flex justify-end gap-3">
            <button type="button" onclick="fecharRejeicao()" class="px-5 py-2 border border-gray-200 text-gray-600 rounded-xl text-sm">Cancelar</button>
            <button class="px-5 py-2 bg-red-500 text-white rounded-xl text-sm font-semibold">Rejeitar</button>
        </div>
    </form>
</div>

<script>
    const dadosGraficos = <?= egJson($dadosGraficos) ?>;
    const egressosData  = <?= egJson($linhasEgressos) ?>;

    /* ---------- abas ---------- */
    function abrirAba(nome) {
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.toggle('hidden', p.id !== 'tab-' + nome));
        document.querySelectorAll('.tab-btn').forEach(b => {
            const ativo = b.dataset.tab === nome;
            b.classList.toggle('border-[#4A9FCA]', ativo);
            b.classList.toggle('text-[#4A9FCA]', ativo);
            b.classList.toggle('border-transparent', !ativo);
            b.classList.toggle('text-gray-500', !ativo);
        });
        history.replaceState(null, '', '?tab=' + nome);
    }
    document.querySelectorAll('[data-tab]').forEach(b => b.addEventListener('click', () => abrirAba(b.dataset.tab)));

    /* ---------- rejeicao ---------- */
    function abrirRejeicao(id) {
        document.getElementById('rejId').value = id;
        const m = document.getElementById('modalRejeicao');
        m.classList.remove('hidden'); m.classList.add('flex');
    }
    function fecharRejeicao() {
        const m = document.getElementById('modalRejeicao');
        m.classList.add('hidden'); m.classList.remove('flex');
    }
    document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharRejeicao(); });

    /* ---------- lista de egressos ---------- */
    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    function linkSeguro(u) { return /^https?:\/\//i.test(u || '') ? u : ''; }

    function filtrarEgressos() {
        const q = document.getElementById('fBusca').value.trim().toLowerCase();
        const curso = document.getElementById('fCurso').value;
        const grupo = document.getElementById('fStatus').value;
        return egressosData.filter(e =>
            (!curso || e.curso === curso) &&
            (!grupo || e.grupo === grupo) &&
            (!q || (e.nome + ' ' + e.empresa + ' ' + e.email).toLowerCase().includes(q))
        );
    }

    function renderEgressos() {
        const lista = filtrarEgressos();
        document.getElementById('contEg').textContent = lista.length;
        const tb = document.getElementById('tbEgressos');
        if (!lista.length) {
            tb.innerHTML = '<tr><td colspan="6" class="p-8 text-center text-gray-400">Nenhum egresso encontrado.</td></tr>';
            return;
        }
        tb.innerHTML = lista.map(e => {
            const li = linkSeguro(e.linkedin);
            return `<tr class="border-b last:border-0">
                <td class="p-3"><p class="font-medium text-gray-800">${esc(e.nome)}</p><p class="text-xs text-gray-500">${esc(e.email)}</p></td>
                <td class="p-3 text-gray-600">${esc(e.curso)}<p class="text-xs text-gray-400">${e.ano ? 'Turma ' + esc(e.ano) : ''}</p></td>
                <td class="p-3 text-gray-700">${esc(e.status)}${e.mentoria === 'Sim' ? '<p class="text-xs text-purple-600">Aceita mentoria</p>' : ''}</td>
                <td class="p-3 text-gray-600">${esc(e.empresa) || '—'}<p class="text-xs text-gray-400">${esc(e.cargo)}</p></td>
                <td class="p-3 text-gray-500">${esc(e.atualizado)}</td>
                <td class="p-3 text-right whitespace-nowrap">
                    ${li ? `<a href="${esc(li)}" target="_blank" rel="noopener" class="text-[#4A9FCA] mr-3" title="LinkedIn"><i class="fab fa-linkedin"></i></a>` : ''}
                    ${e.reverter ? `<form method="POST" class="inline" data-confirm="Este egresso voltará para a base de alunos." data-confirm-titulo="Devolver para os alunos?" data-confirm-botao="Devolver" data-confirm-tipo="aviso" data-confirm-icone="fa-undo">
                        <input type="hidden" name="action" value="reverter_egresso"><input type="hidden" name="id" value="${e.id}">
                        <button class="text-xs text-gray-400 hover:text-red-500">Voltar p/ aluno</button></form>` : ''}
                </td></tr>`;
        }).join('');
    }
    ['fBusca', 'fCurso', 'fStatus'].forEach(id => document.getElementById(id).addEventListener('input', renderEgressos));
    renderEgressos();

    function exportarCsv() {
        const lista = filtrarEgressos();
        const cab = ['Nome', 'E-mail', 'Curso', 'Ano de conclusão', 'Situação', 'Empresa', 'Cargo', 'Cidade', 'LinkedIn', 'Aceita mentoria', 'Meses até 1ª colocação', 'Perfil atualizado em'];
        const celula = v => '"' + String(v ?? '').replace(/"/g, '""') + '"';
        const linhas = [cab.map(celula).join(';')].concat(lista.map(e =>
            [e.nome, e.email, e.curso, e.ano, e.status, e.empresa, e.cargo, e.cidade, e.linkedin, e.mentoria, e.meses, e.atualizado].map(celula).join(';')
        ));
        const blob = new Blob(['\ufeff' + linhas.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'egressos.csv';
        a.click();
        URL.revokeObjectURL(a.href);
    }

    /* ---------- graficos ---------- */
    const cores = ['#22a06b', '#d4a24c', '#6b3fa0', '#4A9FCA', '#d13438', '#9aa4b2'];
    if (document.getElementById('chartStatus')) {
        new Chart(document.getElementById('chartStatus'), {
            type: 'doughnut',
            data: { labels: dadosGraficos.status.labels, datasets: [{ data: dadosGraficos.status.valores, backgroundColor: cores, borderWidth: 2, borderColor: '#fff' }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } } }
        });
        new Chart(document.getElementById('chartCursos'), {
            type: 'bar',
            data: { labels: dadosGraficos.cursos.labels, datasets: [
                { label: 'Egressos', data: dadosGraficos.cursos.total, backgroundColor: '#4A9FCA', borderRadius: 6 },
                { label: 'Ocupados', data: dadosGraficos.cursos.ocupados, backgroundColor: '#22a06b', borderRadius: 6 }
            ] },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { ticks: { font: { size: 10 } } } }, plugins: { legend: { position: 'bottom' } } }
        });
        new Chart(document.getElementById('chartAnos'), {
            type: 'bar',
            data: { labels: dadosGraficos.anos.labels, datasets: [{ label: 'Egressos', data: dadosGraficos.anos.valores, backgroundColor: '#6b3fa0', borderRadius: 6 }] },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }, plugins: { legend: { display: false } } }
        });
    }
</script>

<?php include 'includes/footer.php'; ?>
