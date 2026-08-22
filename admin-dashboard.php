<?php
require_once 'includes/config.php';
require_once 'includes/course.php';
require_once 'includes/acesso_log.php';

if (function_exists('checkAuth')) {
    checkAuth();
} elseif (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

/* =========================================================================
 * PROTECAO DE ACESSO
 * -------------------------------------------------------------------------
 * Somente usuarios com is_admin === true (booleano) podem acessar esta
 * pagina. Qualquer outro tipo de usuario e redirecionado para a tela
 * inicial com uma mensagem de erro de acesso negado.
 * ========================================================================= */
$usuarios = loadData('usuarios');
$usuarioLogado = null;
foreach ($usuarios as $u) {
    if ($u['id'] == ($_SESSION['user_id'] ?? 0)) {
        $usuarioLogado = $u;
        break;
    }
}
$isAdmin = ($usuarioLogado['is_admin'] ?? false) === true;
if (!$isAdmin) {
    header('Location: index.php?erro=acesso_negado');
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
$pageTitle = 'Dashboard Administrativo';

/* =========================================================================
 * FUNCOES AUXILIARES DE LEITURA DE DADOS DO ALUNO
 * -------------------------------------------------------------------------
 * Cada aluno possui uma pasta propria dentro de data/users/{id}/ contendo
 * arquivos JSON individuais de curriculo e certificados. Essas funcoes
 * carregam esses arquivos com seguranca (retornando null/array vazio caso
 * o arquivo nao exista ou esteja corrompido).
 * ========================================================================= */
function getCurriculumDoAluno($userId) {
    $caminho = DATA_PATH . "users/{$userId}/userCurriculum.json";
    if (file_exists($caminho)) {
        $conteudo = json_decode(file_get_contents($caminho), true);
        if (is_array($conteudo)) return $conteudo;
    }
    return null;
}

function getCertificadosDoAluno($userId) {
    $caminho = DATA_PATH . "users/{$userId}/userCertificates.json";
    if (file_exists($caminho)) {
        $conteudo = json_decode(file_get_contents($caminho), true);
        if (is_array($conteudo)) return $conteudo;
    }
    return [];
}

/**
 * Retorna a solicitacao de assinatura mais recente de um aluno especifico,
 * ou null caso ele nunca tenha enviado nenhuma solicitacao de assinatura.
 * A ordenacao e feita pelo campo 'id', assumindo que IDs maiores
 * representam registros mais recentes.
 */
function getUltimaAssinaturaDoAluno($userId, $assinaturas) {
    $doAluno = array_values(array_filter($assinaturas, fn($a) => $a['userId'] == $userId));
    if (empty($doAluno)) return null;
    usort($doAluno, fn($a, $b) => ($b['id'] ?? 0) <=> ($a['id'] ?? 0));
    return $doAluno[0];
}

/**
 * Normaliza o status profissional do aluno para apenas 3 estados reais:
 * 'na_area', 'fora_area' ou 'buscando'. Estagio e emprego contam da mesma
 * forma para fins de empregabilidade -- a unica coisa que importa aqui e
 * se a atividade e dentro ou fora da area de formacao do aluno.
 *
 * Mantemos compatibilidade com os valores antigos gravados no cadastro
 * ('Estagiando na area', 'Empregado na area', etc.) para nao perder o
 * historico ja preenchido.
 */
function statusProfissionalNormalizado($aluno) {
    $status = trim($aluno['statusProfissional'] ?? '');
    if ($status === '' || $status === 'NÃ£o definido') return 'NÃ£o definido';
    if ($status === 'Estagiando') return 'Estagiando na area'; // valor legado
    return $status;
}

/**
 * Classifica o status em um dos 3 grupos usados nos cards do dashboard:
 * 'na_area', 'fora_area' ou 'buscando'.
 */
function grupoEmpregabilidade($statusNormalizado) {
    if (in_array($statusNormalizado, ['Estagiando na area', 'Empregado na area'])) {
        return 'na_area';
    }
    if (in_array($statusNormalizado, ['Estagiando fora da area', 'Empregado fora da area'])) {
        return 'fora_area';
    }
    return 'buscando';
}

/**
 * Formata a data/hora do ultimo acesso do aluno para exibicao na tabela.
 * Retorna  caso o aluno nunca tenha efetuado login desde que este
 * recurso foi implementado.
 */
function formatarUltimoAcesso($aluno) {
    $bruto = $aluno['ultimoAcesso'] ?? null;
    if (!$bruto) return '—';
    try {
        return (new DateTime($bruto))->format('d/m/Y H:i');
    } catch (Exception $e) {
        return '—';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_aluno') {
    $alunoId = intval($_POST['user_id'] ?? 0);
    $novoStatus = trim($_POST['statusProfissional'] ?? '');
    foreach ($usuarios as &$u) {
        if ($u['id'] == $alunoId) {
            $u['statusProfissional'] = $novoStatus;
            break;
        }
    }
    unset($u);
    saveData('usuarios', $usuarios);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true]);
    exit;
}

/**
 * Handler AJAX para o admin atualizar as horas de estagio obrigatorio
 * cumpridas por um aluno (usado na nova tabela "Estagio Obrigatorio").
 * O status "concluido"/"nao concluido" e calculado automaticamente
 * comparando as horas cumpridas com as horas exigidas pelo curso do
 * aluno (definidas em data/cursos_config.json).
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_horas_estagio') {
    $alunoId = intval($_POST['user_id'] ?? 0);
    $horas = max(0, intval($_POST['horasEstagioCumpridas'] ?? 0));
    foreach ($usuarios as &$u) {
        if ($u['id'] == $alunoId) {
            $u['horasEstagioCumpridas'] = $horas;
            break;
        }
    }
    unset($u);
    saveData('usuarios', $usuarios);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true]);
    exit;
}

/**
 * Handler AJAX: envia e-mail de aviso ao aluno que ainda nao concluiu
 * as horas de estagio obrigatorio, disparado pelo botao "Notificar"
 * na tabela de Estagio Obrigatorio.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'enviar_email_estagio') {
    require_once 'includes/email_config.php';

    $alunoId = intval($_POST['user_id'] ?? 0);
    $alunoAlvo = null;
    foreach ($usuarios as $u) {
        if ($u['id'] == $alunoId) {
            $alunoAlvo = $u;
            break;
        }
    }

    header('Content-Type: application/json; charset=UTF-8');

    if (!$alunoAlvo || empty($alunoAlvo['email'])) {
        echo json_encode(['ok' => false, 'erro' => 'Aluno sem e-mail cadastrado']);
        exit;
    }

    $nomeAluno = $alunoAlvo['nome'] ?? 'Aluno(a)';
    $cursoAluno = $alunoAlvo['curso'] ?? '';
    $cfgCursoTmp = getCursosConfig();
    $cfgCurso = $cfgCursoTmp[$cursoAluno] ?? ['horasEstagio' => 0];
    $horasNecessarias = intval($cfgCurso['horasEstagio'] ?? 0);
    $horasCumpridas = intval($alunoAlvo['horasEstagioCumpridas'] ?? 0);
    $horasFaltantes = max($horasNecessarias - $horasCumpridas, 0);

    $assunto = 'Pendência: Horas de Estágio Obrigatório';
    $corpo = "
        <div style='font-family:Arial,sans-serif;font-size:14px;color:#333;'>
            <p>Olá, <strong>{$nomeAluno}</strong>,</p>
            <p>Identificamos que você ainda não concluiu a carga horária de estágio obrigatório do seu curso.</p>
            <ul>
                <li><strong>Horas necessárias:</strong> {$horasNecessarias}h</li>
                <li><strong>Horas cumpridas até o momento:</strong> {$horasCumpridas}h</li>
                <li><strong>Horas faltantes:</strong> {$horasFaltantes}h</li>
            </ul>
            <p>Regularize sua situação o quanto antes para não comprometer sua formação. Em caso de dúvidas, procure a coordenação do curso.</p>
            <p>Atenciosamente,<br>Central de Estágios  Fundação Santo André</p>
        </div>
    ";

    $enviado = enviarEmail($alunoAlvo['email'], $nomeAluno, $assunto, $corpo);

    if ($enviado) {
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'erro' => 'Falha ao enviar e-mail. Verifique as credenciais SMTP.']);
    }
    exit;
}

function getStatusOptions() {
    return [
        'Buscando oportunidade',
        'Estagiando na area',
        'Estagiando fora da area',
        'Empregado na area',
        'Empregado fora da area',
    ];
}

const STATUS_EMPREGABILIDADE = [
    'Estagiando na area', 'Estagiando fora da area',
    'Empregado na area', 'Empregado fora da area',
];

/* =========================================================================
 * BASE DE ALUNOS (SEM FILTROS APLICADOS AINDA)
 * -------------------------------------------------------------------------
 * Removemos da base qualquer usuario marcado como administrador, pois o
 * dashboard trata apenas de metricas relacionadas aos alunos.
 * ========================================================================= */
$todosAlunos = array_values(array_filter($usuarios, function ($u) {
    return ($u['is_admin'] ?? false) !== true;
}));

$assinaturas = loadData('assinaturas');
if (!is_array($assinaturas)) {
    $assinaturas = [];
}

/* =========================================================================
 * OPCOES DE FILTRO - CURSOS (CALCULADO A PARTIR DA BASE COMPLETA)
 * ========================================================================= */
$cursosDisponiveis = [];
foreach ($todosAlunos as $a) {
    $curso = trim($a['curso'] ?? '');
    if ($curso !== '' && $curso !== '—') {
        $cursosDisponiveis[$curso] = true;
    }
}
$cursosDisponiveis = array_keys($cursosDisponiveis);
sort($cursosDisponiveis);

/* =========================================================================
 * ANOS E SEMESTRES: FILTROS DE CALENDARIO (NAO DEPENDEM DE MATRICULA)
 * ========================================================================= */
$anoAtual = intval(date('Y'));
$anosDisponiveis = [];
for ($ano = $anoAtual + 1; $ano >= $anoAtual - 5; $ano--) {
    $anosDisponiveis[] = (string) $ano;
}

$statusDisponiveis = getStatusOptions();

$mesesLabelsPt = [
    '01' => 'Janeiro',  '02' => 'Fevereiro', '03' => 'Março',
    '04' => 'Abril',    '05' => 'Maio',      '06' => 'Junho',
    '07' => 'Julho',    '08' => 'Agosto',    '09' => 'Setembro',
    '10' => 'Outubro',  '11' => 'Novembro',  '12' => 'Dezembro',
];
/* =========================================================================
 * APLICACAO DOS FILTROS RECEBIDOS VIA GET (MULTISSELECAO ESTILO POWER BI)
 * ========================================================================= */
function paramArray($chave) {
    $valor = $_GET[$chave] ?? [];
    if (!is_array($valor)) {
        $valor = [$valor];
    }
    return array_values(array_filter(array_map('trim', $valor), fn($v) => $v !== ''));
}

$filtroCursos    = paramArray('curso');
$filtroAnos      = paramArray('ano');
$filtroMeses     = paramArray('mes');
$filtroSemestres = paramArray('semestre');
$filtroStatuses  = paramArray('status');

$alunos = array_values(array_filter($todosAlunos, function ($a) use (
    $filtroCursos, $filtroAnos, $filtroMeses, $filtroSemestres, $filtroStatuses
) {
    if (!empty($filtroCursos) && !in_array(trim($a['curso'] ?? ''), $filtroCursos)) {
        return false;
    }

    if (!empty($filtroAnos) || !empty($filtroMeses) || !empty($filtroSemestres)) {
        $dm = $a['dataMatricula'] ?? null;
        if (!$dm) {
            return false;
        }
        try {
            $dt = new DateTime($dm);
        } catch (Exception $e) {
            return false;
        }
        if (!empty($filtroAnos) && !in_array($dt->format('Y'), $filtroAnos)) {
            return false;
        }
        if (!empty($filtroMeses) && !in_array($dt->format('m'), $filtroMeses)) {
            return false;
        }
        if (!empty($filtroSemestres)) {
            $semestreCalculado = intval($dt->format('n')) <= 6 ? '1' : '2';
            if (!in_array($semestreCalculado, $filtroSemestres)) {
                return false;
            }
        }
    }

    if (!empty($filtroStatuses) && !in_array(statusProfissionalNormalizado($a), $filtroStatuses)) {
        return false;
    }

    return true;
}));

$totalAlunos      = count($alunos);
$totalAlunosGeral = count($todosAlunos);
$filtrosAtivos    = (
    !empty($filtroCursos) || !empty($filtroAnos) || !empty($filtroMeses) ||
    !empty($filtroSemestres) || !empty($filtroStatuses)
);

$idsFiltrados         = array_column($alunos, 'id');
$assinaturasFiltradas = array_values(array_filter(
    $assinaturas,
    fn($a) => in_array($a['userId'], $idsFiltrados)
));

/* =========================================================================
 * CALCULO DAS METRICAS PRINCIPAIS (VERSAO SIMPLIFICADA)
 * -------------------------------------------------------------------------
 * Estagio e emprego contam da mesma forma para empregabilidade -- a
 * unica distincao que realmente importa e se a atividade e dentro ou
 * fora da area de formacao do aluno.
 * ========================================================================= */
$alunosComCurriculo   = 0;
$topCertificados      = [];
$totalCertificadosContagem = 0;
$totalNaArea    = 0; // estagiando OU empregado, dentro da area
$totalForaArea  = 0; // estagiando OU empregado, fora da area

foreach ($alunos as $aluno) {
    $curriculo = getCurriculumDoAluno($aluno['id']);
    if ($curriculo && (
        !empty(trim($curriculo['resumo'] ?? '')) ||
        !empty($curriculo['experiencias'] ?? []) ||
        !empty($curriculo['formacoes'] ?? [])
    )) {
        $alunosComCurriculo++;
    }

    $status = statusProfissionalNormalizado($aluno);
    $grupo  = grupoEmpregabilidade($status);
    if ($grupo === 'na_area')   $totalNaArea++;
    if ($grupo === 'fora_area') $totalForaArea++;

    $certificados = getCertificadosDoAluno($aluno['id']);
    $totalCertificadosContagem += count($certificados);
    foreach ($certificados as $certificado) {
        $titulo = trim($certificado['title'] ?? '');
        if ($titulo === '') continue;
        if (!isset($topCertificados[$titulo])) $topCertificados[$titulo] = 0;
        $topCertificados[$titulo]++;
    }
}

$totalEmpregabilidade = $totalNaArea + $totalForaArea;
$totalBuscando         = max($totalAlunos - $totalEmpregabilidade, 0);
$taxaEmpregabilidade       = $totalAlunos > 0 ? round(($totalEmpregabilidade / $totalAlunos) * 100, 1) : 0;
$mediaCertificadosPorAluno = $totalAlunos > 0 ? round($totalCertificadosContagem / $totalAlunos, 1) : 0;

arsort($topCertificados);
$topCertificados = array_slice($topCertificados, 0, 5, true);

$distribuicaoCursos = [];
foreach ($alunos as $aluno) {
    $curso = trim($aluno['curso'] ?? '') ?: 'Não informado';
    if ($curso === '—') $curso = 'Não informado';
    if (!isset($distribuicaoCursos[$curso])) $distribuicaoCursos[$curso] = 0;
    $distribuicaoCursos[$curso]++;
}
arsort($distribuicaoCursos);

/* Mantido para o grafico de barras (detalhamento estagio vs emprego) */
$totalEstagiandoNaArea    = 0;
$totalEstagiandoForaArea  = 0;
$totalEmpregadoNaArea     = 0;
$totalEmpregadoForaAreaQt = 0;
foreach ($alunos as $aluno) {
    $status = statusProfissionalNormalizado($aluno);
    if ($status === 'Estagiando na area')      $totalEstagiandoNaArea++;
    if ($status === 'Estagiando fora da area') $totalEstagiandoForaArea++;
    if ($status === 'Empregado na area')       $totalEmpregadoNaArea++;
    if ($status === 'Empregado fora da area')  $totalEmpregadoForaAreaQt++;
}
/* =========================================================================
 * METRICAS DE ASSINATURAS / CONTRATOS DE ESTAGIO
 * ========================================================================= */
$totalSolicitacoesAssin = count($assinaturasFiltradas);
$totalAssinadas         = count(array_filter($assinaturasFiltradas, fn($a) => $a['status'] === 'assinado'));
$totalPendentes         = count(array_filter($assinaturasFiltradas, fn($a) => in_array($a['status'], ['solicitado', 'em_analise'])));
$taxaConclusaoAssin     = $totalSolicitacoesAssin > 0 ? round(($totalAssinadas / $totalSolicitacoesAssin) * 100, 1) : 0;

$alunosComContratoAssinado = [];
$alunosComFimAssinado      = [];
foreach ($assinaturasFiltradas as $a) {
    if ($a['tipo'] === 'contrato_estagio' && $a['status'] === 'assinado') {
        $alunosComContratoAssinado[$a['userId']] = true;
    }
    if ($a['tipo'] === 'fim_estagio' && $a['status'] === 'assinado') {
        $alunosComFimAssinado[$a['userId']] = true;
    }
}
$totalContratoAssinado = count($alunosComContratoAssinado);
$totalFimAssinado      = count($alunosComFimAssinado);

$mapaAlunosFiltrados = [];
foreach ($alunos as $a) {
    $mapaAlunosFiltrados[$a['id']] = $a;
}

$divergencias = 0;
foreach (array_keys($alunosComContratoAssinado) as $uid) {
    $alunoRef = $mapaAlunosFiltrados[$uid] ?? null;
    if ($alunoRef) {
        $statusRef = statusProfissionalNormalizado($alunoRef);
        if (!in_array($statusRef, ['Estagiando na area', 'Estagiando fora da area'])) {
            $divergencias++;
        }
    }
}

/* =========================================================================
 * WIDGET: TEMPO MEDIO DE RESPOSTA DAS ASSINATURAS
 * ========================================================================= */
$diasParaAnalise    = [];
$diasParaAssinatura = [];
foreach ($assinaturasFiltradas as $a) {
    $dataSolicitacao = $a['dataSolicitacao'] ?? null;
    $dataAnalise     = $a['dataAnalise'] ?? null;
    $dataAssinatura  = $a['dataAssinatura'] ?? null;

    if ($dataSolicitacao && $dataAnalise) {
        try {
            $diasParaAnalise[] = (new DateTime($dataSolicitacao))->diff(new DateTime($dataAnalise))->days;
        } catch (Exception $e) {}
    }
    if ($dataSolicitacao && $dataAssinatura) {
        try {
            $diasParaAssinatura[] = (new DateTime($dataSolicitacao))->diff(new DateTime($dataAssinatura))->days;
        } catch (Exception $e) {}
    }
}
$mediaDiasAnalise = count($diasParaAnalise) > 0
    ? round(array_sum($diasParaAnalise) / count($diasParaAnalise), 1) : null;
$mediaDiasAssinatura = count($diasParaAssinatura) > 0
    ? round(array_sum($diasParaAssinatura) / count($diasParaAssinatura), 1) : null;

$alunosOrdenadosPorMatricula = $alunos;
usort($alunosOrdenadosPorMatricula, function ($a, $b) {
    $da = $a['dataMatricula'] ?? '';
    $db = $b['dataMatricula'] ?? '';
    return strcmp($db, $da);
});
$ultimosMatriculados = array_slice($alunosOrdenadosPorMatricula, 0, 5);

$statusOptions = getStatusOptions();

/* =========================================================================
 * ESTAGIO OBRIGATORIO POR CURSO
 * -------------------------------------------------------------------------
 * So faz sentido para alunos cujo curso exige estagio obrigatorio (ver
 * includes/cursos_config.php). O progresso e medido comparando o campo
 * 'horasEstagioCumpridas' do aluno (editavel pelo admin na tabela) com
 * a carga horaria minima definida na configuracao do curso.
 * ========================================================================= */
$cursosConfig             = getCursosConfig();
$cursosComEstagioObrig    = getCursosComEstagioObrigatorio();

$filtroCursoObrigatorio   = paramArray('curso_obrigatorio');
$filtroStatusObrigatorio  = paramArray('status_obrigatorio'); // 'concluido' | 'nao_concluido'

$alunosEstagioObrigatorio = array_values(array_filter($alunos, function ($a) {
    return cursoTemEstagioObrigatorio(trim($a['curso'] ?? ''));
}));

$alunosEstagioObrigatorio = array_map(function ($a) use ($cursosConfig) {
    $curso = trim($a['curso'] ?? '');
    $cfg = $cursosConfig[$curso] ?? ['horasEstagio' => 0, 'periodo_inicio' => null, 'observacao' => ''];
    $horasNecessarias = intval($cfg['horasEstagio'] ?? 0);
    $horasCumpridas   = intval($a['horasEstagioCumpridas'] ?? 0);
    $a['_horasNecessarias'] = $horasNecessarias;
    $a['_horasCumpridas']   = $horasCumpridas;
    $a['_concluido']        = $horasNecessarias > 0 && $horasCumpridas >= $horasNecessarias;
    $a['_periodoAtual']     = $a['periodo'] ?? '—';
    $a['_observacao']       = trim($cfg['observacao'] ?? '');
    return $a;
}, $alunosEstagioObrigatorio);

if (!empty($filtroCursoObrigatorio)) {
    $alunosEstagioObrigatorio = array_values(array_filter(
        $alunosEstagioObrigatorio,
        fn($a) => in_array(trim($a['curso'] ?? ''), $filtroCursoObrigatorio)
    ));
}
if (!empty($filtroStatusObrigatorio)) {
    $alunosEstagioObrigatorio = array_values(array_filter($alunosEstagioObrigatorio, function ($a) use ($filtroStatusObrigatorio) {
        $chave = $a['_concluido'] ? 'concluido' : 'nao_concluido';
        return in_array($chave, $filtroStatusObrigatorio);
    }));
}

$totalEstagioObrigatorioConcluido = count(array_filter($alunosEstagioObrigatorio, fn($a) => $a['_concluido']));
$totalEstagioObrigatorioPendente  = count($alunosEstagioObrigatorio) - $totalEstagioObrigatorioConcluido;

/* =========================================================================
 * ENGAJAMENTO NA PLATAFORMA (DADO REAL, A PARTIR DO LOG DE ACESSOS)
 * ========================================================================= */
$acessosUltimos7Dias = getAcessosUltimos7Dias();

include 'includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<style>
    .stat-card {
        background: #ffffff;
        border: 1px solid #eef0f2;
        border-radius: 1rem;
        padding: 1.25rem 1.5rem;
        box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: box-shadow .15s ease, transform .15s ease;
    }
    .stat-card:hover {
        box-shadow: 0 4px 14px rgba(16, 24, 40, 0.08);
        transform: translateY(-1px);
    }
    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 0.85rem;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    .stat-value {
        font-size: 1.5rem;
        font-weight: 800;
        color: #101828;
        line-height: 1.15;
    }
    .stat-label {
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #667085;
    }

    .panel-card {
        background: #ffffff;
        border: 1px solid #eef0f2;
        border-radius: 1.25rem;
        padding: 1.5rem;
        box-shadow: 0 1px 3px rgba(16, 24, 40, 0.05);
        position: relative;
        overflow: hidden;
    }
    .panel-accent::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 5px;
        border-radius: 1.25rem 0 0 1.25rem;
        background: linear-gradient(180deg, #4A9FCA 0%, #2B7FA6 100%);
    }
    .panel-title {
        font-size: 0.95rem;
        font-weight: 800;
        color: #101828;
        display: flex;
        align-items: center;
        gap: 0.55rem;
        flex-wrap: wrap;
        margin-bottom: 0.35rem;
    }
    .panel-title i { color: #2B7FA6; }
    .panel-subtitle { font-size: 0.78rem; color: #98a2b3; margin-bottom: 1.1rem; }

    .chart-box { position: relative; height: 270px; }
    .chart-box-sm { position: relative; height: 210px; }

    .fake-data-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #fef3c7;
        color: #92400e;
        font-size: 0.62rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .03em;
        padding: 2px 10px;
        border-radius: 999px;
    }
    .real-data-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #d1fae5;
        color: #065f46;
        font-size: 0.62rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .03em;
        padding: 2px 10px;
        border-radius: 999px;
    }

    .filter-bar {
        background: #ffffff;
        border: 1px solid #eef0f2;
        border-radius: 1.25rem;
        padding: 1.1rem 1.4rem;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.7rem;
        margin-bottom: 1.75rem;
        box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
        position: relative;
    }
    .filter-active-pill {
        background: linear-gradient(90deg, #4A9FCA 0%, #2B7FA6 100%);
        color: #fff;
        font-size: 0.72rem;
        font-weight: 700;
        padding: 0.3rem 0.85rem;
        border-radius: 999px;
    }
    .filter-clear-link {
        font-size: 0.8rem;
        color: #b42318;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }
    .filter-clear-link:hover { text-decoration: underline; }

    .msel { position: relative; }
    .msel-trigger {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.55rem 0.9rem;
        border: 1px solid #e4e7ec;
        border-radius: 0.7rem;
        font-size: 0.83rem;
        color: #344054;
        background: #fff;
        cursor: pointer;
        transition: border-color .15s ease;
        white-space: nowrap;
    }
    .msel-trigger:hover,
    .msel.open .msel-trigger { border-color: #4A9FCA; }
    .msel-trigger .msel-count {
        background: linear-gradient(90deg, #4A9FCA 0%, #2B7FA6 100%);
        color: #fff;
        font-size: 0.68rem;
        font-weight: 800;
        padding: 1px 7px;
        border-radius: 999px;
    }
    .msel-panel {
        display: none;
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        min-width: 230px;
        max-height: 280px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid #e4e7ec;
        border-radius: 0.85rem;
        box-shadow: 0 10px 30px rgba(16,24,40,.12);
        z-index: 60;
        padding: 0.5rem;
    }
    .msel.open .msel-panel { display: block; }
    .msel-panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.3rem 0.4rem 0.6rem;
        border-bottom: 1px solid #f2f4f7;
        margin-bottom: 0.4rem;
    }
    .msel-panel-header span {
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #98a2b3;
    }
    .msel-clear-btn {
        font-size: 0.72rem;
        font-weight: 700;
        color: #2B7FA6;
        background: none;
        border: none;
        cursor: pointer;
        padding: 0;
    }
    .msel-clear-btn:hover { text-decoration: underline; }
    .msel-option {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.45rem 0.5rem;
        border-radius: 0.55rem;
        cursor: pointer;
        font-size: 0.83rem;
        color: #344054;
    }
    .msel-option:hover { background: #f9fafb; }
    .msel-option input[type="checkbox"] {
        width: 15px;
        height: 15px;
        accent-color: #2B7FA6;
        cursor: pointer;
    }
    .msel-empty {
        font-size: 0.78rem;
        color: #98a2b3;
        padding: 0.6rem 0.5rem;
        font-style: italic;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        padding: 0.3rem 0.85rem;
        border-radius: 999px;
        font-size: 0.7rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .status-pill-green  { background: #d1fae5; color: #065f46; }
    .status-pill-blue   { background: #dbeafe; color: #1e40af; }
    .status-pill-amber  { background: #fef3c7; color: #92400e; }
    .status-pill-gray   { background: #f2f4f7; color: #667085; }
    .status-pill-red    { background: #fee4e2; color: #b42318; }
    .status-pill-purple { background: #ede9fe; color: #5b21b6; }
    .status-pill-teal   { background: #ccfbf1; color: #115e59; }

    .data-table { width: 100%; border-collapse: collapse; font-size: 0.86rem; }
    .data-table thead th {
        text-align: left;
        padding: 0.75rem 0.9rem;
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #98a2b3;
        background: #f9fafb;
        border-bottom: 1px solid #eef0f2;
    }
    .data-table tbody td {
        padding: 0.75rem 0.9rem;
        border-bottom: 1px solid #f2f4f7;
        color: #344054;
        vertical-align: middle;
    }
    .data-table tbody tr:hover td { background: #f9fafb; }

    .toast-box {
        position: fixed;
        bottom: 1.5rem;
        right: 1.5rem;
        background: #101828;
        color: #fff;
        font-size: 0.83rem;
        font-weight: 600;
        padding: 0.75rem 1.15rem;
        border-radius: 0.75rem;
        box-shadow: 0 8px 24px rgba(0,0,0,.18);
        z-index: 999;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .mini-progress-track { background: #f2f4f7; border-radius: 999px; height: 8px; overflow: hidden; }
    .mini-progress-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #4A9FCA 0%, #2B7FA6 100%); }

    .horas-input {
        width: 80px;
        border: 1.5px solid #e4e7ec;
        border-radius: 0.6rem;
        padding: 0.3rem 0.5rem;
        font-size: 0.8rem;
        text-align: center;
    }
    .horas-input:focus { outline: none; border-color: #4A9FCA; }
    .btn-salvar-horas {
        background: #4A9FCA;
        color: #fff;
        border: none;
        border-radius: 0.5rem;
        width: 28px;
        height: 28px;
        font-size: 0.75rem;
        cursor: pointer;
    }
    .btn-salvar-horas:hover { background: #3A8FB0; }

    .acesso-recente { color: #22a06b; font-weight: 700; }
    .acesso-antigo { color: #d13438; font-weight: 700; }
</style>
<main class="ml-16 pt-16 bg-gray-50 min-h-screen">
<div class="p-8 max-w-7xl mx-auto">

    <!-- ===================== CABECALHO ===================== -->
    <div class="mb-6 flex items-center justify-between flex-wrap gap-3">
        <div>
            <h2 class="text-3xl font-bold text-gray-900">Dashboard Administrativo</h2>
            <p class="text-gray-500">VisÃ£o geral do desempenho e empregabilidade dos alunos</p>
        </div>
        <?php if ($filtrosAtivos): ?>
            <span class="filter-active-pill">
                <i class="fas fa-filter mr-1"></i>
                Filtro ativo mostrando <?php echo $totalAlunos; ?> de <?php echo $totalAlunosGeral; ?> alunos
            </span>
        <?php endif; ?>
    </div>

    <!-- ===================== BARRA DE FILTROS GERAIS ===================== -->
    <form method="GET" class="filter-bar" id="formFiltros">

        <i class="fas fa-graduation-cap text-gray-400"></i>
        <div class="msel" data-msel="curso">
            <button type="button" class="msel-trigger" onclick="toggleMsel(this)">
                <span class="msel-label">Todos os cursos</span>
                <span class="msel-count" style="display:none;">0</span>
                <i class="fas fa-chevron-down text-xs text-gray-400"></i>
            </button>
            <div class="msel-panel">
                <div class="msel-panel-header">
                    <span>Cursos</span>
                    <button type="button" class="msel-clear-btn" onclick="clearMsel(this)">Limpar</button>
                </div>
                <?php if (empty($cursosDisponiveis)): ?>
                    <div class="msel-empty">Nenhum curso disponÃ­vel</div>
                <?php endif; ?>
                <?php foreach ($cursosDisponiveis as $c): ?>
                    <label class="msel-option">
                        <input type="checkbox" name="curso[]" value="<?php echo htmlspecialchars($c); ?>"
                            <?php echo in_array($c, $filtroCursos) ? 'checked' : ''; ?>
                            onchange="onMselChange(this)">
                        <?php echo htmlspecialchars($c); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <i class="fas fa-calendar text-gray-400"></i>
        <div class="msel" data-msel="ano">
            <button type="button" class="msel-trigger" onclick="toggleMsel(this)">
                <span class="msel-label">Todos os anos</span>
                <span class="msel-count" style="display:none;">0</span>
                <i class="fas fa-chevron-down text-xs text-gray-400"></i>
            </button>
            <div class="msel-panel">
                <div class="msel-panel-header">
                    <span>Anos</span>
                    <button type="button" class="msel-clear-btn" onclick="clearMsel(this)">Limpar</button>
                </div>
                <?php foreach ($anosDisponiveis as $a): ?>
                    <label class="msel-option">
                        <input type="checkbox" name="ano[]" value="<?php echo htmlspecialchars($a); ?>"
                            <?php echo in_array($a, $filtroAnos) ? 'checked' : ''; ?>
                            onchange="onMselChange(this)">
                        <?php echo htmlspecialchars($a); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <i class="fas fa-calendar-week text-gray-400"></i>
        <div class="msel" data-msel="semestre">
            <button type="button" class="msel-trigger" onclick="toggleMsel(this)">
                <span class="msel-label">Todos os semestres</span>
                <span class="msel-count" style="display:none;">0</span>
                <i class="fas fa-chevron-down text-xs text-gray-400"></i>
            </button>
            <div class="msel-panel">
                <div class="msel-panel-header">
                    <span>Semestres</span>
                    <button type="button" class="msel-clear-btn" onclick="clearMsel(this)">Limpar</button>
                </div>
                <label class="msel-option">
                    <input type="checkbox" name="semestre[]" value="1"
                        <?php echo in_array('1', $filtroSemestres) ? 'checked' : ''; ?>
                        onchange="onMselChange(this)">
                    1º Semestre
                </label>
                <label class="msel-option">
                    <input type="checkbox" name="semestre[]" value="2"
                        <?php echo in_array('2', $filtroSemestres) ? 'checked' : ''; ?>
                        onchange="onMselChange(this)">
                    2º Semestre
                </label>
            </div>
        </div>

        <i class="fas fa-calendar-day text-gray-400"></i>
        <div class="msel" data-msel="mes">
            <button type="button" class="msel-trigger" onclick="toggleMsel(this)">
                <span class="msel-label">Todos os meses</span>
                <span class="msel-count" style="display:none;">0</span>
                <i class="fas fa-chevron-down text-xs text-gray-400"></i>
            </button>
            <div class="msel-panel">
                <div class="msel-panel-header">
                    <span>Meses</span>
                    <button type="button" class="msel-clear-btn" onclick="clearMsel(this)">Limpar</button>
                </div>
                <?php foreach ($mesesLabelsPt as $num => $label): ?>
                    <label class="msel-option">
                        <input type="checkbox" name="mes[]" value="<?php echo $num; ?>"
                            <?php echo in_array($num, $filtroMeses) ? 'checked' : ''; ?>
                            onchange="onMselChange(this)">
                        <?php echo $label; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <i class="fas fa-briefcase text-gray-400"></i>
        <div class="msel" data-msel="status">
            <button type="button" class="msel-trigger" onclick="toggleMsel(this)">
                <span class="msel-label">Todos os status</span>
                <span class="msel-count" style="display:none;">0</span>
                <i class="fas fa-chevron-down text-xs text-gray-400"></i>
            </button>
            <div class="msel-panel">
                <div class="msel-panel-header">
                    <span>Status Profissional</span>
                    <button type="button" class="msel-clear-btn" onclick="clearMsel(this)">Limpar</button>
                </div>
                <?php foreach ($statusDisponiveis as $s): ?>
                    <label class="msel-option">
                        <input type="checkbox" name="status[]" value="<?php echo htmlspecialchars($s); ?>"
                            <?php echo in_array($s, $filtroStatuses) ? 'checked' : ''; ?>
                            onchange="onMselChange(this)">
                        <?php echo htmlspecialchars($s); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($filtrosAtivos): ?>
            <a href="admin-dashboard.php" class="filter-clear-link">
                <i class="fas fa-times-circle"></i> Limpar todos os filtros
            </a>
        <?php endif; ?>
    </form>

    <!-- ===================== METRICAS PRINCIPAIS (SIMPLIFICADO) ===================== -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 mb-6">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #4A9FCA 0%, #2B7FA6 100%);">
                <i class="fas fa-users"></i>
            </div>
            <div>
                <div class="stat-value"><?php echo $totalAlunos; ?></div>
                <div class="stat-label">Total de Alunos</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#d4a24c;">
                <i class="fas fa-file-alt"></i>
            </div>
            <div>
                <div class="stat-value"><?php echo $alunosComCurriculo; ?> / <?php echo $totalAlunos; ?></div>
                <div class="stat-label">Com Currículo Preenchido</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#22a06b;">
                <i class="fas fa-briefcase"></i>
            </div>
            <div>
                <div class="stat-value"><?php echo $taxaEmpregabilidade; ?>%</div>
                <div class="stat-label">Taxa de Empregabilidade</div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
        <div class="stat-card">
            <div class="stat-icon" style="background:#2f855a;">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <div>
                <div class="stat-value"><?php echo $totalNaArea; ?></div>
                <div class="stat-label">Empregado Dentro da Área</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#8a4b6b;">
                <i class="fas fa-route"></i>
            </div>
            <div>
                <div class="stat-value"><?php echo $totalForaArea; ?></div>
                <div class="stat-label">Empregado Fora da Área</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#98a2b3;">
                <i class="fas fa-search"></i>
            </div>
            <div>
                <div class="stat-value"><?php echo $totalBuscando; ?></div>
                <div class="stat-label">Buscando Oportunidade</div>
            </div>
        </div>
    </div>

    <!-- ===================== METRICAS DE ASSINATURAS/CONTRATOS ===================== -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        <div class="stat-card">
            <div class="stat-icon" style="background:#2b6ea6;">
                <i class="fas fa-file-signature"></i>
            </div>
            <div>
                <div class="stat-value"><?php echo $totalContratoAssinado; ?></div>
                <div class="stat-label">Contratos Assinados</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#6b3fa0;">
                <i class="fas fa-flag-checkered"></i>
            </div>
            <div>
                <div class="stat-value"><?php echo $totalFimAssinado; ?></div>
                <div class="stat-label">Fim de Estágio Assinados</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#d4a24c;">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div>
                <div class="stat-value"><?php echo $totalPendentes; ?></div>
                <div class="stat-label">Assinaturas Pendentes</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#22a06b;">
                <i class="fas fa-check-double"></i>
            </div>
            <div>
                <div class="stat-value"><?php echo $taxaConclusaoAssin; ?>%</div>
                <div class="stat-label">Taxa de Conclusão</div>
            </div>
        </div>
    </div>

    <?php if ($divergencias > 0): ?>
    <div class="panel-card mb-8" style="border-color:#fde68a;background:#fffbeb;">
        <div class="flex items-center gap-3">
            <i class="fas fa-exclamation-triangle text-yellow-600 text-xl"></i>
            <div>
                <p class="font-bold text-gray-800 text-sm">
                    <?php echo $divergencias; ?> aluno(s) com contrato de estágio assinado mas status profissional desatualizado
                </p>
                <p class="text-xs text-gray-500">
                    Esses alunos possuem contrato assinado, porém o cadastro não está marcado como "Estagiando na área" ou "Estagiando fora da área".
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <!-- ===================== WIDGETS COM DADOS REAIS ===================== -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-8">
        <div class="panel-card panel-accent">
            <div class="panel-title"><i class="fas fa-stopwatch"></i> Tempo Médio de Resposta das Assinaturas</div>
            <p class="panel-subtitle">Calculado a partir das datas reais de solicitação, análise e assinatura</p>
            <div class="grid grid-cols-2 gap-4">
                <div class="text-center bg-gray-50 rounded-xl py-4">
                    <div class="stat-value" style="font-size:1.4rem;">
                        <?php echo $mediaDiasAnalise !== null ? $mediaDiasAnalise . ' dias' : '—'; ?>
                    </div>
                    <div class="stat-label" style="text-transform:none;letter-spacing:normal;">até entrar em análise</div>
                </div>
                <div class="text-center bg-gray-50 rounded-xl py-4">
                    <div class="stat-value" style="font-size:1.4rem;">
                        <?php echo $mediaDiasAssinatura !== null ? $mediaDiasAssinatura . ' dias' : '—'; ?>
                    </div>
                    <div class="stat-label" style="text-transform:none;letter-spacing:normal;">até ser assinado</div>
                </div>
            </div>
        </div>

        <div class="panel-card panel-accent">
            <div class="panel-title"><i class="fas fa-certificate"></i> Certificados por Aluno</div>
            <p class="panel-subtitle">Média de cursos complementares concluídos por aluno filtrado</p>
            <div class="text-center bg-gray-50 rounded-xl py-6">
                <div class="stat-value" style="font-size:1.9rem;"><?php echo $mediaCertificadosPorAluno; ?></div>
                <div class="stat-label" style="text-transform:none;letter-spacing:normal;">
                    certificados / aluno em média (<?php echo $totalCertificadosContagem; ?> no total)
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== GRAFICOS PRINCIPAIS ===================== -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-8">
        <div class="panel-card panel-accent">
            <div class="panel-title">
                <i class="fas fa-chart-pie"></i> Distribuição por Curso/Formação
                <button type="button" onclick="exportarDistribuicaoCursos()"
                    class="ml-auto text-xs bg-green-600 text-white px-3 py-1.5 rounded-lg hover:bg-green-700 transition-all">
                    <i class="fas fa-file-excel mr-1"></i> Exportar Excel
                </button>
            </div>
            <div class="chart-box">
                <canvas id="chartCursos"></canvas>
            </div>
        </div>
        <div class="panel-card panel-accent">
            <div class="panel-title"><i class="fas fa-chart-bar"></i> Detalhamento: Estágio vs. Emprego</div>
            <div class="chart-box">
                <canvas id="chartStatus"></canvas>
            </div>
        </div>
    </div>

    <!-- ===================== TOP CERTIFICADOS + ULTIMOS MATRICULADOS ===================== -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-8">
        <div class="panel-card panel-accent">
            <div class="panel-title">
                <i class="fas fa-medal"></i> Cursos Complementares Mais Concluídos
                <button type="button" onclick="exportarTopCertificados()"
                    class="ml-auto text-xs bg-green-600 text-white px-3 py-1.5 rounded-lg hover:bg-green-700 transition-all">
                    <i class="fas fa-file-excel mr-1"></i> Exportar Excel
                </button>
            </div>
            <?php if (empty($topCertificados)): ?>
                <p class="text-gray-400 text-sm italic">Nenhum certificado registrado ainda.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php
                    $maxCert = max($topCertificados);
                    foreach ($topCertificados as $titulo => $qtd):
                        $pct = $maxCert > 0 ? round(($qtd / $maxCert) * 100) : 0;
                    ?>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($titulo); ?></span>
                            <span class="text-gray-500"><?php echo $qtd; ?> aluno(s)</span>
                        </div>
                        <div class="mini-progress-track">
                            <div class="mini-progress-fill" style="width:<?php echo $pct; ?>%;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel-card panel-accent">
            <div class="panel-title">
                <i class="fas fa-user-clock"></i> Últimos Alunos Matriculados
                <button type="button" onclick="exportarUltimosMatriculados()"
                    class="ml-auto text-xs bg-green-600 text-white px-3 py-1.5 rounded-lg hover:bg-green-700 transition-all">
                    <i class="fas fa-file-excel mr-1"></i> Exportar Excel
                </button>
            </div>
            <?php if (empty($ultimosMatriculados)): ?>
                <p class="text-gray-400 text-sm italic">Nenhum aluno encontrado com esses filtros.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($ultimosMatriculados as $recente):
                        $dataFmt = '—';
                        if (!empty($recente['dataMatricula'])) {
                            try {
                                $dataFmt = (new DateTime($recente['dataMatricula']))->format('d/m/Y');
                            } catch (Exception $e) {}
                        }
                    ?>
                    <div class="flex items-center justify-between border-b border-gray-100 pb-2 last:border-0 last:pb-0">
                        <div>
                            <div class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($recente['nome'] ?? ''); ?></div>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($recente['curso'] ?? '—'); ?></div>
                        </div>
                        <span class="text-xs text-gray-400"><?php echo $dataFmt; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===================== WIDGET DE ENGAJAMENTO (AGORA COM DADOS REAIS) ===================== -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-8">
        <div class="panel-card panel-accent">
            <div class="panel-title">
                <i class="fas fa-face-smile"></i> Satisfação com o Suporte da Coordenação
                <span class="fake-data-tag"><i class="fas fa-flask"></i> dados ilustrativos</span>
            </div>
            <p class="panel-subtitle">Exemplo de widget — conecte a uma pesquisa de satisfação real quando disponível</p>
            <div class="chart-box-sm">
                <canvas id="chartSatisfacao"></canvas>
            </div>
        </div>

        <div class="panel-card panel-accent">
            <div class="panel-title">
                <i class="fas fa-signal"></i> Engajamento na Plataforma (últimos 7 dias)
                <span class="real-data-tag"><i class="fas fa-check"></i> dado real</span>
            </div>
            <p class="panel-subtitle">Quantidade de alunos únicos que fizeram login em cada um dos últimos 7 dias</p>
            <div class="chart-box-sm">
                <canvas id="chartEngajamento"></canvas>
            </div>
        </div>
    </div>

    <!-- ===================== ESTAGIO OBRIGATORIO POR CURSO ===================== -->
    <div class="panel-card panel-accent mb-8">
        <div class="panel-title">
            <i class="fas fa-graduation-cap"></i> Estágio Obrigatório por Curso
            <button type="button" onclick="exportarTabelaHTML('tabelaEstagioObrigatorio', 'estagio_obrigatorio')"
                class="ml-auto text-xs bg-green-600 text-white px-3 py-1.5 rounded-lg hover:bg-green-700 transition-all">
                <i class="fas fa-file-excel mr-1"></i> Exportar Excel
            </button>
        </div>
        <p class="panel-subtitle">
            Cursos e cargas horárias definidos em <code>data/cursos_config.json</code> — ajuste conforme a grade real de cada curso.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-gray-50 rounded-xl p-4 text-center">
                <div class="stat-value" style="font-size:1.4rem;"><?php echo count($alunosEstagioObrigatorio); ?></div>
                <div class="stat-label" style="text-transform:none;letter-spacing:normal;">alunos em cursos com estágio obrigatório</div>
            </div>
            <div class="bg-green-50 rounded-xl p-4 text-center">
                <div class="stat-value" style="font-size:1.4rem;color:#065f46;"><?php echo $totalEstagioObrigatorioConcluido; ?></div>
                <div class="stat-label" style="text-transform:none;letter-spacing:normal;">concluíram a carga horária</div>
            </div>
            <div class="rounded-xl p-4 text-center" style="background:#fffbeb;">
                <div class="stat-value" style="font-size:1.4rem;color:#92400e;"><?php echo $totalEstagioObrigatorioPendente; ?></div>
                <div class="stat-label" style="text-transform:none;letter-spacing:normal;">ainda não concluíram</div>
            </div>
        </div>

        <form method="GET" class="filter-bar" style="margin-bottom:1.25rem;" id="formFiltrosEstagio">
            <?php foreach ($filtroCursos as $v): ?><input type="hidden" name="curso[]" value="<?php echo htmlspecialchars($v); ?>"><?php endforeach; ?>
            <?php foreach ($filtroAnos as $v): ?><input type="hidden" name="ano[]" value="<?php echo htmlspecialchars($v); ?>"><?php endforeach; ?>
            <?php foreach ($filtroMeses as $v): ?><input type="hidden" name="mes[]" value="<?php echo htmlspecialchars($v); ?>"><?php endforeach; ?>
            <?php foreach ($filtroSemestres as $v): ?><input type="hidden" name="semestre[]" value="<?php echo htmlspecialchars($v); ?>"><?php endforeach; ?>
            <?php foreach ($filtroStatuses as $v): ?><input type="hidden" name="status[]" value="<?php echo htmlspecialchars($v); ?>"><?php endforeach; ?>

            <i class="fas fa-graduation-cap text-gray-400"></i>
            <div class="msel" data-msel="curso_obrigatorio">
                <button type="button" class="msel-trigger" onclick="toggleMsel(this)">
                    <span class="msel-label">Todos os cursos (obrigatório)</span>
                    <span class="msel-count" style="display:none;">0</span>
                    <i class="fas fa-chevron-down text-xs text-gray-400"></i>
                </button>
                <div class="msel-panel">
                    <div class="msel-panel-header">
                        <span>Cursos com estágio obrigatório</span>
                        <button type="button" class="msel-clear-btn" onclick="clearMsel(this)">Limpar</button>
                    </div>
                    <?php if (empty($cursosComEstagioObrig)): ?>
                        <div class="msel-empty">Nenhum curso configurado ainda</div>
                    <?php endif; ?>
                    <?php foreach ($cursosComEstagioObrig as $c): ?>
                        <label class="msel-option">
                            <input type="checkbox" name="curso_obrigatorio[]" value="<?php echo htmlspecialchars($c); ?>"
                                <?php echo in_array($c, $filtroCursoObrigatorio) ? 'checked' : ''; ?>
                                onchange="onMselChange(this)">
                            <?php echo htmlspecialchars($c); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <i class="fas fa-check-double text-gray-400"></i>
            <div class="msel" data-msel="status_obrigatorio">
                <button type="button" class="msel-trigger" onclick="toggleMsel(this)">
                    <span class="msel-label">Concluído e Não concluído</span>
                    <span class="msel-count" style="display:none;">0</span>
                    <i class="fas fa-chevron-down text-xs text-gray-400"></i>
                </button>
                <div class="msel-panel">
                    <div class="msel-panel-header">
                        <span>Status do Estágio Obrigatório</span>
                        <button type="button" class="msel-clear-btn" onclick="clearMsel(this)">Limpar</button>
                    </div>
                    <label class="msel-option">
                        <input type="checkbox" name="status_obrigatorio[]" value="concluido"
                            <?php echo in_array('concluido', $filtroStatusObrigatorio) ? 'checked' : ''; ?>
                            onchange="onMselChange(this)">
                        Concluído
                    </label>
                    <label class="msel-option">
                        <input type="checkbox" name="status_obrigatorio[]" value="nao_concluido"
                            <?php echo in_array('nao_concluido', $filtroStatusObrigatorio) ? 'checked' : ''; ?>
                            onchange="onMselChange(this)">
                        Não concluído
                    </label>
                </div>
            </div>

            <?php if (!empty($filtroCursoObrigatorio) || !empty($filtroStatusObrigatorio)): ?>
                <a href="admin-dashboard.php<?php echo $filtrosAtivos ? '?' . http_build_query(array_filter([
                    'curso' => $filtroCursos, 'ano' => $filtroAnos, 'mes' => $filtroMeses,
                    'semestre' => $filtroSemestres, 'status' => $filtroStatuses,
                ])) : ''; ?>" class="filter-clear-link">
                    <i class="fas fa-times-circle"></i> Limpar filtros de estágio obrigatório
                </a>
            <?php endif; ?>
        </form>

        <div style="overflow-x:auto;">
        <table class="data-table" id="tabelaEstagioObrigatorio">
    <thead>
        <tr>
            <th>Nome</th>
            <th>Email</th>
            <th>Curso</th>
            <th>Perí­odo Atual</th>
            <th>Horas Necessárias</th>
            <th>Horas Cumpridas</th>
            <th>Status</th>
            <th>Ação</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($alunosEstagioObrigatorio as $aluno): ?>
        <tr>
            <td class="font-semibold text-gray-800">
                <?php echo htmlspecialchars($aluno['nome'] ?? ''); ?>
                <?php if (!empty($aluno['_observacao'])): ?>
                    <i class="fas fa-triangle-exclamation text-amber-500 ml-1"
                       title="<?php echo htmlspecialchars($aluno['_observacao']); ?>"></i>
                <?php endif; ?>
            </td>
            <td class="text-gray-600"><?php echo htmlspecialchars($aluno['email'] ?? '—'); ?></td>
            <td class="text-gray-600"><?php echo htmlspecialchars($aluno['curso'] ?? '—'); ?></td>
            <td class="text-gray-600 text-center"><?php echo htmlspecialchars($aluno['_periodoAtual']); ?></td>
            <td class="text-gray-600"><?php echo $aluno['_horasNecessarias']; ?>h</td>
            <td class="text-gray-600 font-semibold"><?php echo $aluno['_horasCumpridas']; ?>h</td>
            <td>
                <?php if ($aluno['_concluido']): ?>
                    <span class="status-pill status-pill-green"><i class="fas fa-check-circle mr-1"></i>Concluído</span>
                <?php else: ?>
                    <span class="status-pill status-pill-amber"><i class="fas fa-clock mr-1"></i>Não concluído</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if (!$aluno['_concluido'] && !empty($aluno['email'])): ?>
                    <button type="button" id="btnEmail_<?php echo $aluno['id']; ?>"
                        onclick="enviarLembreteEstagio(<?php echo $aluno['id']; ?>)"
                        class="bg-red-500 hover:bg-red-600 text-white text-xs px-3 py-1.5 rounded-lg transition-all">
                        <i class="fas fa-envelope mr-1"></i> Notificar
                    </button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($alunosEstagioObrigatorio)): ?>
        <tr>
            <td colspan="8" class="text-center text-gray-400 italic py-6">
                Nenhum aluno encontrado verifique se há cursos configurados com estágio obrigatório em <code>course.json</code>.
            </td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>
        </div>
    </div>



</div>
</main>

<div id="dashToast" class="toast-box hidden">
    <i class="fas fa-check-circle text-green-400"></i> <span id="dashToastText">Salvo</span>
</div>
<script>
const dadosCursos = <?php echo json_encode($distribuicaoCursos, JSON_UNESCAPED_UNICODE); ?>;
const dadosTopCertificados = <?php echo json_encode($topCertificados, JSON_UNESCAPED_UNICODE); ?>;
const dadosUltimosMatriculados = <?php echo json_encode(array_map(function($a) {
    $dataFmt = '—';
    if (!empty($a['dataMatricula'])) {
        try { $dataFmt = (new DateTime($a['dataMatricula']))->format('d/m/Y'); }
        catch (Exception $e) { $dataFmt = '—'; }
    }
    return [
        'Nome' => $a['nome'] ?? '',
        'Curso' => $a['curso'] ?? '',
        'Data Matrícula' => $dataFmt,
    ];
}, $ultimosMatriculados), JSON_UNESCAPED_UNICODE); ?>;
const totalEstagiandoNaArea    = <?php echo intval($totalEstagiandoNaArea); ?>;
const totalEstagiandoForaArea  = <?php echo intval($totalEstagiandoForaArea); ?>;
const totalEmpregadoNaArea     = <?php echo intval($totalEmpregadoNaArea); ?>;
const totalEmpregadoForaAreaQt = <?php echo intval($totalEmpregadoForaAreaQt); ?>;
const totalBuscando            = <?php echo intval($totalBuscando); ?>;
const acessosUltimos7Dias      = <?php echo json_encode($acessosUltimos7Dias, JSON_UNESCAPED_UNICODE); ?>;

let dashToastTimer = null;
function mostrarDashToast(texto, erro = false) {
    const toast = document.getElementById('dashToast');
    document.getElementById('dashToastText').textContent = texto;
    toast.classList.remove('hidden');
    toast.style.background = erro ? '#b42318' : '#101828';
    clearTimeout(dashToastTimer);
    dashToastTimer = setTimeout(() => toast.classList.add('hidden'), 2200);
}

const coresPalette = ['#4A9FCA', '#2B7FA6', '#22a06b', '#d4a24c', '#6b3fa0', '#d13438', '#5B6572', '#1C4A4A'];

new Chart(document.getElementById('chartCursos'), {
    type: 'doughnut',
    data: {
        labels: Object.keys(dadosCursos),
        datasets: [{
            data: Object.values(dadosCursos),
            backgroundColor: coresPalette,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
    }
});

new Chart(document.getElementById('chartStatus'), {
    type: 'bar',
    data: {
        labels: ['Estágio (área)', 'Estágio (fora)', 'Emprego (área)', 'Emprego (fora)', 'Buscando'],
        datasets: [{
            data: [totalEstagiandoNaArea, totalEstagiandoForaArea, totalEmpregadoNaArea, totalEmpregadoForaAreaQt, totalBuscando],
            backgroundColor: ['#22a06b', '#0d9488', '#2b6ea6', '#6b3fa0', '#98a2b3'],
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
});

/* ---------------------------------------------------------------------
 * Widget ilustrativo: satisfacao (dados ficticios sem fonte real ainda)
 * ------------------------------------------------------------------- */
new Chart(document.getElementById('chartSatisfacao'), {
    type: 'doughnut',
    data: {
        labels: ['Muito satisfeito', 'Satisfeito', 'Neutro', 'Insatisfeito'],
        datasets: [{
            data: [42, 33, 18, 7],
            backgroundColor: ['#22a06b', '#4A9FCA', '#98a2b3', '#d13438'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
    }
});

/* ---------------------------------------------------------------------
 * Widget de engajamento: AGORA COM DADOS REAIS, vindos do log de acessos
 * (data/acessos_log.json), contando alunos unicos que logaram por dia.
 * ------------------------------------------------------------------- */
new Chart(document.getElementById('chartEngajamento'), {
    type: 'line',
    data: {
        labels: Object.keys(acessosUltimos7Dias),
        datasets: [{
            label: 'Alunos com login no dia',
            data: Object.values(acessosUltimos7Dias),
            borderColor: '#2B7FA6',
            backgroundColor: 'rgba(43,127,166,0.08)',
            fill: true,
            tension: 0.35,
            pointRadius: 3,
            pointBackgroundColor: '#2B7FA6'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
});

/* =====================================================================
 * DROPDOWNS DE MULTISSELECAO (funciona para os dois formularios da pagina)
 * ===================================================================== */
const LABELS_BASE = {
    curso: 'Todos os cursos',
    ano: 'Todos os anos',
    semestre: 'Todos os semestres',
    mes: 'Todos os meses',
    status: 'Todos os status',
    curso_obrigatorio: 'Todos os cursos (obrigatório)',
    status_obrigatorio: 'Concluído e Não concluído'
};

function toggleMsel(btn) {
    const msel = btn.closest('.msel');
    const jaAberto = msel.classList.contains('open');
    document.querySelectorAll('.msel.open').forEach(m => m.classList.remove('open'));
    if (!jaAberto) msel.classList.add('open');
}

function clearMsel(btn) {
    const msel = btn.closest('.msel');
    const form = msel.closest('form');
    msel.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
    atualizarLabelMsel(msel);
    form.submit();
}

function onMselChange(checkbox) {
    const msel = checkbox.closest('.msel');
    atualizarLabelMsel(msel);
    msel.closest('form').submit(); // aplica o filtro imediatamente ao clicar
}

function atualizarLabelMsel(msel) {
    const chave = msel.dataset.msel;
    const marcados = msel.querySelectorAll('input[type="checkbox"]:checked');
    const labelEl = msel.querySelector('.msel-label');
    const countEl = msel.querySelector('.msel-count');

    if (marcados.length === 0) {
        labelEl.textContent = LABELS_BASE[chave];
        countEl.style.display = 'none';
    } else if (marcados.length === 1) {
        labelEl.textContent = marcados[0].parentElement.textContent.trim();
        countEl.style.display = 'none';
    } else {
        labelEl.textContent = LABELS_BASE[chave].replace(/^Todos os/, 'Selecionados:');
        countEl.textContent = marcados.length;
        countEl.style.display = 'inline-block';
    }
}

document.addEventListener('click', function (e) {
    document.querySelectorAll('.msel.open').forEach(msel => {
        if (!msel.contains(e.target)) {
            msel.classList.remove('open'); // agora sÃ³ fecha; o submit jÃ¡ aconteceu no onMselChange
        }
    });
});

document.querySelectorAll('.msel').forEach(msel => atualizarLabelMsel(msel));

/* =====================================================================
 * SALVAR HORAS DE ESTAGIO OBRIGATORIO (AJAX)
 * ===================================================================== */
function salvarHorasEstagio(userId) {
    const input = document.getElementById('horasInput_' + userId);
    const horas = parseInt(input.value, 10) || 0;

    fetch('admin-dashboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update_horas_estagio&user_id=${userId}&horasEstagioCumpridas=${horas}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            mostrarDashToast('Horas atualizadas com sucesso!');
            setTimeout(() => window.location.reload(), 700);
        } else {
            mostrarDashToast('Erro ao salvar', true);
        }
    })
    .catch(() => mostrarDashToast('Erro ao salvar', true));
}

/* =====================================================================
 * ENVIO DE E-MAIL AUTOMATICO (AJAX) botao "Notificar" da tabela
 * de Estagio Obrigatorio
 * ===================================================================== */
function enviarLembreteEstagio(userId) {
    const btn = document.getElementById('btnEmail_' + userId);
    const textoOriginal = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Enviando...';

    fetch('admin-dashboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=enviar_email_estagio&user_id=${userId}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            mostrarDashToast('E-mail enviado com sucesso!');
            btn.innerHTML = '<i class="fas fa-check mr-1"></i> Enviado';
            setTimeout(() => { btn.disabled = false; btn.innerHTML = textoOriginal; }, 3000);
        } else {
            mostrarDashToast(data.erro || 'Erro ao enviar e-mail', true);
            btn.disabled = false;
            btn.innerHTML = textoOriginal;
        }
    })
    .catch(() => {
        mostrarDashToast('Erro ao enviar e-mail', true);
        btn.disabled = false;
        btn.innerHTML = textoOriginal;
    });
}

/* =====================================================================
 * EXPORTACAO PARA EXCEL (SheetJS)
 * ===================================================================== */
function exportarExcel(dados, nomeArquivo, nomeAba = 'Dados') {
    const ws = XLSX.utils.json_to_sheet(dados);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, nomeAba);
    XLSX.writeFile(wb, nomeArquivo + '.xlsx');
}

function exportarTabelaHTML(tableId, nomeArquivo, nomeAba = 'Dados') {
    const tabela = document.getElementById(tableId);
    const wb = XLSX.utils.table_to_book(tabela, { sheet: nomeAba });
    XLSX.writeFile(wb, nomeArquivo + '.xlsx');
}

function exportarDistribuicaoCursos() {
    const linhas = Object.entries(dadosCursos).map(([curso, qtd]) => ({ Curso: curso, 'Qtd. Alunos': qtd }));
    exportarExcel(linhas, 'distribuicao_cursos');
}

function exportarTopCertificados() {
    const linhas = Object.entries(dadosTopCertificados).map(([titulo, qtd]) => ({ Certificado: titulo, 'Alunos Concluíram': qtd }));
    exportarExcel(linhas, 'top_certificados');
}

function exportarUltimosMatriculados() {
    exportarExcel(dadosUltimosMatriculados, 'ultimos_matriculados');
}
</script>

<?php include 'includes/footer.php'; ?>