<?php
/**
 * =============================================================================
 * PAINÉIS BI — fontes de dados
 * -----------------------------------------------------------------------------
 * Cada FONTE devolve uma tabela simples (lista de linhas) e a descrição dos
 * campos. A tela admin-paineis.php agrupa, conta e desenha no navegador,
 * do mesmo jeito que o Power BI faz com uma tabela.
 *
 * Fontes prontas (dados da própria Central, SEM nome, CPF ou e-mail de alunos):
 *   alunos, candidaturas, vagas, assinaturas, certificados, acessos, mentoria,
 *   acoes_admin e, só para a Coordenação de Egressos, egressos e vagas_egressos
 *
 * Painéis e pastas (estilo Salesforce), compartilhados entre os funcionários:
 *   data/bi/pastas.json   -> pastas (Alunos, Vagas e Funil, Egressos + as criadas)
 *   data/bi/paineis.json  -> painéis (nome, pasta, autor, datas e os visuais)
 *
 * Fontes que o funcionário cria (guardadas em data/bi/fontes.json):
 *   excel -> planilha .xlsx ou .csv enviada pela tela (até 5000 linhas)
 *   sql   -> um SELECT num banco ou data lake configurado em BI_SQL_DSN
 *            (MySQL, PostgreSQL, SQL Server, ou ODBC para Databricks, Synapse...)
 * =============================================================================
 */

require_once __DIR__ . '/egressos.php';
require_once __DIR__ . '/vagas.php';
require_once __DIR__ . '/melhorias.php';
require_once __DIR__ . '/integracoes/banco-conexao.php';
require_once __DIR__ . '/integracoes/totvs-cliente.php';
require_once __DIR__ . '/integracoes/totvs-mapeamento.php';

const BI_MAX_LINHAS = 5000;

/** Lista das fontes para o menu da tela (prontas + criadas) */
function biListaFontes(): array {
    $lista = [
        'alunos'       => 'Alunos',
        'candidaturas' => 'Candidaturas',
        'vagas'        => 'Vagas',
        'assinaturas'  => 'Assinaturas',
        'certificados' => 'Certificados',
        'acessos'      => 'Acessos (logins)',
        'mentoria'     => 'Mentoria',
        'acoes_admin'  => 'Ações dos funcionários',
        'totvs'        => 'TOTVS: alunos (sistema acadêmico)',   // tabela direta do TOTVS (segue TOTVS_MODO)
    ];
    if (biPodeEgressos()) {
        $lista['egressos']       = 'Egressos';
        $lista['vagas_egressos'] = 'Vagas divulgadas por egressos';
    }
    foreach (biFontesCriadas() as $f) {
        $lista['custom:' . $f['id']] = ($f['tipo'] === 'sql' ? 'SQL: ' : 'Planilha: ') . $f['nome'];
    }
    // Relatórios salvos (TOTVS, planilha ou banco/data lake) também viram fonte de painel
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    $pastas = biPastas();
    foreach (biRelatorios() as $r) {
        if (biPodeVerRelatorio($r, $uid, $pastas)) $lista['rel:' . $r['id']] = 'Relatório: ' . $r['nome'];
    }
    return $lista;
}

function biFontesCriadas(): array {
    return array_values(dadosLer('bi/fontes'));
}

/** Mês "2026-10" a partir de várias formas de data */
function biMes($valor): string {
    if (is_numeric($valor) && $valor > 0) return date('Y-m', (int) $valor);
    $v = trim((string) $valor);
    if ($v === '') return '';
    foreach (['d/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $f) {
        $d = DateTime::createFromFormat($f, $v);
        if ($d) return $d->format('Y-m');
    }
    $t = strtotime($v);
    return $t ? date('Y-m', $t) : '';
}

/** Situação profissional no mesmo padrão do Dashboard Admin */
function biEmpregabilidade(string $status): string {
    if ($status === 'Estagiando') $status = 'Estagiando na area';
    if (in_array($status, ['Estagiando na area', 'Empregado na area'], true))           return 'Na área';
    if (in_array($status, ['Estagiando fora da area', 'Empregado fora da area'], true)) return 'Fora da área';
    return 'Buscando';
}

/** Alunos (sem funcionários, egressos e professores) */
function biAlunos(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    foreach (loadData('usuarios') as $u) {
        if (!empty($u['is_admin']) && $u['is_admin'] !== 'False' && $u['is_admin'] !== 'false') continue;
        if (in_array($u['tipo'] ?? '', ['egresso', 'professor'], true)) continue;
        $cache[] = $u;
    }
    return $cache;
}

function biCurso(array $u): string {
    $c = trim((string) ($u['curso'] ?? ''));
    return ($c === '' || $c === '—') ? 'Não informado' : $c;
}

/* ===================== FONTES PRONTAS ===================== */

function biFonteAlunos(): array {
    $linhas = [];
    foreach (biAlunos() as $u) {
        $id = (int) ($u['id'] ?? 0);
        $cv = dadosLer("users/{$id}/userCurriculum");
        $temCv = !empty(trim((string) ($cv['resumo'] ?? ''))) || !empty($cv['experiencias']) || !empty($cv['formacoes']);
        $status = trim((string) ($u['statusProfissional'] ?? '')) ?: 'Não definido';
        $linhas[] = [
            'curso'           => biCurso($u),
            'periodo'         => trim((string) ($u['periodo'] ?? '')) ?: 'Não informado',
            'turno'           => trim((string) ($u['turno'] ?? '')) ?: 'Não informado',
            'situacao'        => trim((string) ($u['situacao'] ?? '')) ?: 'Não informado',
            'status'          => $status === 'Estagiando' ? 'Estagiando na area' : $status,
            'empregabilidade' => biEmpregabilidade($status),
            'temCurriculo'    => $temCv ? 'Sim' : 'Não',
            'certificados'    => count(dadosLer("users/{$id}/userCertificates")),
            'perfil'          => ($u['tipo'] ?? '') === 'aluno_restrito' ? 'Restrito' : 'Aluno',
            'mesMatricula'    => biMes($u['dataMatricula'] ?? ''),
        ];
    }
    return ['nome' => 'Alunos', 'campos' => [
        'curso' => ['Curso', 'texto'], 'periodo' => ['Período', 'texto'], 'turno' => ['Turno', 'texto'],
        'situacao' => ['Situação da matrícula', 'texto'], 'status' => ['Status profissional', 'texto'],
        'empregabilidade' => ['Empregabilidade', 'texto'], 'temCurriculo' => ['Tem currículo', 'texto'],
        'certificados' => ['Nº de certificados', 'numero'], 'perfil' => ['Perfil', 'texto'], 'mesMatricula' => ['Mês da matrícula', 'data'],
    ], 'linhas' => $linhas];
}

function biFonteCandidaturas(): array {
    $linhas = [];
    foreach (biAlunos() as $u) {
        $id = (int) ($u['id'] ?? 0);
        foreach (dadosLer("users/{$id}/userCandidaturas") as $c) {
            $linhas[] = [
                'curso'   => biCurso($u),
                'empresa' => trim((string) ($c['company'] ?? '')) ?: 'Sem nome',
                'status'  => (string) ($c['status'] ?? 'Enviada'),
                'origem'  => ($c['origem'] ?? '') === 'egresso' ? 'Egresso' : 'FSA',
                'etapa'   => biEtapaCandidatura((string) ($c['status'] ?? 'Enviada')),
                'semRetorno' => (($c['status'] ?? 'Enviada') === 'Enviada' && (int) ($c['ts'] ?? 0) > 0
                                 && (time() - (int) ($c['statusTs'] ?? $c['ts'])) >= 14 * 86400) ? 'Sim' : 'Não',
                'mes'     => biMes($c['ts'] ?? ''),
            ];
        }
    }
    return ['nome' => 'Candidaturas', 'campos' => [
        'curso' => ['Curso do aluno', 'texto'], 'empresa' => ['Empresa', 'texto'], 'status' => ['Andamento', 'texto'],
        'origem' => ['Origem da vaga', 'texto'], 'etapa' => ['Etapa do funil', 'texto'],
        'semRetorno' => ['Sem retorno há 14+ dias', 'texto'], 'mes' => ['Mês', 'data'],
    ], 'linhas' => $linhas];
}

function biFonteVagas(): array {
    $linhas  = [];
    $cidades = mhCidadesFiltro();
    $add = function (array $v, string $origem, string $situacao) use (&$linhas, $cidades) {
        $g = mhGrupoCidade($v);
        $linhas[] = [
            'origem'     => $origem,
            'empresa'    => trim((string) ($v['company'] ?? '')) ?: 'Sem nome',
            'cidade'     => $cidades[$g] ?? 'Outras',
            'modalidade' => ucfirst(mhModalidade($v)),
            'situacao'   => $situacao,
            'bolsa'      => mhBolsaValor($v['salary'] ?? ''),
            'mes'        => biMes(mhVagaTs($v)),
        ];
    };
    foreach (loadData('platform_jobs') as $j) {
        if (!is_array($j)) continue;
        $add($j, !empty($j['externa']) ? 'Externa' : 'FSA', vagaVigente($j) ? 'Aberta' : 'Encerrada');
    }
    foreach (egressoVagas() as $v) {
        $st = egressoVagaStatusEfetivo($v);
        $add($v, 'Egresso', $st === 'aprovada' ? 'Aberta' : ucfirst((string) $st));
    }
    return ['nome' => 'Vagas', 'campos' => [
        'origem' => ['Origem', 'texto'], 'empresa' => ['Empresa', 'texto'], 'cidade' => ['Cidade', 'texto'],
        'modalidade' => ['Modalidade', 'texto'], 'situacao' => ['Situação', 'texto'], 'bolsa' => ['Bolsa (R$)', 'numero'], 'mes' => ['Mês', 'data'],
    ], 'linhas' => $linhas];
}

function biFonteAssinaturas(): array {
    $cursoPorId = [];
    foreach (biAlunos() as $u) $cursoPorId[(int) ($u['id'] ?? 0)] = biCurso($u);
    $nomes  = ['contrato_estagio' => 'Contrato de estágio', 'fim_estagio' => 'Fim de estágio'];
    $status = ['solicitado' => 'Solicitado', 'em_analise' => 'Em análise', 'assinado' => 'Assinado', 'erro' => 'Com erro'];
    $linhas = [];
    foreach (loadData('assinaturas') as $a) {
        $ini = strtotime((string) ($a['dataSolicitacao'] ?? '')) ?: 0;
        $fim = strtotime((string) ($a['dataAssinatura'] ?? '')) ?: 0;
        $linhas[] = [
            'tipo'   => $nomes[$a['tipo'] ?? ''] ?? (string) ($a['tipo'] ?? ''),
            'status' => $status[$a['status'] ?? ''] ?? (string) ($a['status'] ?? ''),
            'area'   => ($a['area'] ?? '') === 'dentro' ? 'Dentro da área' : (($a['area'] ?? '') === 'fora' ? 'Fora da área' : '—'),
            'curso'  => $cursoPorId[(int) ($a['userId'] ?? 0)] ?? 'Não informado',
            'dias'   => ($ini && $fim) ? round(($fim - $ini) / 86400, 1) : null,
            'mes'    => biMes($a['dataSolicitacao'] ?? ''),
        ];
    }
    return ['nome' => 'Assinaturas', 'campos' => [
        'tipo' => ['Documento', 'texto'], 'status' => ['Situação', 'texto'], 'area' => ['Área', 'texto'],
        'curso' => ['Curso', 'texto'], 'dias' => ['Dias até assinar', 'numero'], 'mes' => ['Mês da solicitação', 'data'],
    ], 'linhas' => $linhas];
}

function biFonteCertificados(): array {
    $linhas = [];
    foreach (biAlunos() as $u) {
        $id = (int) ($u['id'] ?? 0);
        foreach (dadosLer("users/{$id}/userCertificates") as $c) {
            $linhas[] = [
                'titulo'    => trim((string) ($c['title'] ?? '')) ?: 'Sem título',
                'categoria' => trim((string) ($c['category'] ?? '')) ?: 'Sem categoria',
                'curso'     => biCurso($u),
                'horas'     => (float) ($c['hours'] ?? 0),
                'status'    => (string) ($c['status'] ?? ''),
            ];
        }
    }
    return ['nome' => 'Certificados', 'campos' => [
        'titulo' => ['Certificado', 'texto'], 'categoria' => ['Categoria', 'texto'], 'curso' => ['Curso', 'texto'],
        'horas' => ['Horas', 'numero'], 'status' => ['Situação', 'texto'],
    ], 'linhas' => $linhas];
}

function biFonteAcessos(): array {
    $linhas = [];
    $desde  = strtotime('-90 days');
    foreach (dadosLer('acessos_log') as $a) {
        $t = strtotime((string) ($a['data'] ?? ''));
        if (!$t || $t < $desde) continue;
        $linhas[] = ['dia' => date('Y-m-d', $t), 'semana' => ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'][(int) date('w', $t)], 'usuario' => (int) ($a['userId'] ?? 0)];
    }
    return ['nome' => 'Acessos (últimos 90 dias)', 'campos' => [
        'dia' => ['Dia', 'data'], 'semana' => ['Dia da semana', 'texto'], 'usuario' => ['Usuário (código)', 'numero'],
    ], 'linhas' => $linhas];
}

function biFonteMentoria(): array {
    $st = ['pendente' => 'Aguardando', 'aceito' => 'Em andamento', 'recusado' => 'Recusado', 'cancelado' => 'Cancelado', 'concluido' => 'Concluída'];
    $linhas = [];
    foreach (dadosLer('mentoria_pedidos') as $p) {
        $linhas[] = [
            'assunto'   => (string) ($p['assunto'] ?? ''),
            'status'    => $st[$p['status'] ?? ''] ?? (string) ($p['status'] ?? ''),
            'curso'     => trim((string) ($p['alunoCurso'] ?? '')) ?: 'Não informado',
            'mensagens' => count((array) ($p['mensagens'] ?? [])),
            'mes'       => biMes($p['criadoTs'] ?? ''),
        ];
    }
    return ['nome' => 'Mentoria', 'campos' => [
        'assunto' => ['Assunto', 'texto'], 'status' => ['Situação', 'texto'], 'curso' => ['Curso do aluno', 'texto'],
        'mensagens' => ['Mensagens no chat', 'numero'], 'mes' => ['Mês do pedido', 'data'],
    ], 'linhas' => $linhas];
}

/** Etapa mais avançada da candidatura (para o visual de funil) */
function biEtapaCandidatura(string $status): string {
    switch ($status) {
        case 'Aprovado(a)': return '4. Contratação';
        case 'Entrevista':  return '3. Entrevista';
        case 'Em processo': return '2. Em análise';
        default:            return '1. Candidatura';
    }
}

/** Quem pode ver os dados dos egressos (Coordenação de Egressos) */
function biPodeEgressos(): bool {
    return function_exists('perfilPode') && perfilPode('gestao_egressos');
}

function biFonteEgressos(): array {
    $grupos = ['na_area' => 'Na área', 'fora_area' => 'Fora da área', 'empreendedor' => 'Empreendedor(a)',
               'estudando' => 'Estudando', 'buscando' => 'Buscando', 'nao_informado' => 'Não informado'];
    $limite = strtotime('-12 months');
    $linhas = [];
    foreach (loadData('usuarios') as $e) {
        if (($e['tipo'] ?? '') !== 'egresso') continue;
        $g = egressoGrupoStatus($e['statusProfissional'] ?? '');
        $ocupado = in_array($g, ['na_area', 'fora_area', 'empreendedor'], true);
        $empresa = trim((string) ($e['empresaAtual'] ?? ''));
        $meses   = (isset($e['mesesAteColocacao']) && $e['mesesAteColocacao'] !== '' && $e['mesesAteColocacao'] !== null) ? (int) $e['mesesAteColocacao'] : null;
        $atual   = !empty($e['perfilAtualizadoEm']) ? strtotime((string) $e['perfilAtualizadoEm']) : 0;
        $linhas[] = [
            'curso'         => biCurso($e),
            'ano'           => trim((string) ($e['anoConclusao'] ?? '')) ?: 'N/I',
            'situacao'      => egressoStatusLabel($e['statusProfissional'] ?? ''),
            'grupo'         => $grupos[$g] ?? 'Não informado',
            'ocupado'       => $ocupado ? 'Sim' : 'Não',
            'empresa'       => ($ocupado && $empresa !== '') ? $empresa : 'Não informada',
            'temEmpresa'    => ($ocupado && $empresa !== '') ? 'Sim' : 'Não',
            'meses'         => $meses,
            'mentoria'      => !empty($e['aceitaMentoria']) ? 'Sim' : 'Não',
            'desatualizado' => (!$atual || $atual < $limite) ? 'Sim' : 'Não',
        ];
    }
    return ['nome' => 'Egressos', 'campos' => [
        'curso' => ['Curso', 'texto'], 'ano' => ['Ano de conclusão', 'texto'], 'situacao' => ['Situação profissional', 'texto'],
        'grupo' => ['Grupo da situação', 'texto'], 'ocupado' => ['Ocupado (empregado, estagiando ou empreendendo)', 'texto'],
        'empresa' => ['Empresa atual', 'texto'], 'temEmpresa' => ['Informou a empresa', 'texto'],
        'meses' => ['Meses até a 1ª colocação', 'numero'], 'mentoria' => ['Aceita mentoria', 'texto'],
        'desatualizado' => ['Perfil desatualizado (+12 meses)', 'texto'],
    ], 'linhas' => $linhas];
}

function biFonteVagasEgressos(): array {
    $rot = ['pendente' => 'Aguardando moderação', 'aprovada' => 'Publicada', 'rejeitada' => 'Rejeitada', 'encerrada' => 'Encerrada', 'expirada' => 'Expirada'];
    $linhas = [];
    foreach (egressoVagas() as $v) {
        $s = egressoVagaStatusEfetivo($v);
        $linhas[] = [
            'situacao'     => $rot[$s] ?? ucfirst((string) $s),
            'categoria'    => trim((string) ($v['categoria'] ?? '')) ?: 'Não informada',
            'divulgador'   => trim((string) ($v['egressoName'] ?? '')) ?: 'Sem nome',
            'empresa'      => trim((string) ($v['company'] ?? '')) ?: 'Sem nome',
            'candidaturas' => (int) ($v['applicants'] ?? 0),
            'mes'          => biMes($v['createdTs'] ?? ($v['publishedTs'] ?? '')),
        ];
    }
    return ['nome' => 'Vagas de egressos', 'campos' => [
        'situacao' => ['Situação', 'texto'], 'categoria' => ['Categoria', 'texto'], 'divulgador' => ['Egresso que divulgou', 'texto'],
        'empresa' => ['Empresa', 'texto'], 'candidaturas' => ['Candidaturas geradas', 'numero'], 'mes' => ['Mês', 'data'],
    ], 'linhas' => $linhas];
}

function biFonteAcoesAdmin(): array {
    $linhas = [];
    foreach ((array) loadData('admin_log') as $l) {
        $ts = (int) ($l['ts'] ?? 0);
        $linhas[] = [
            'funcionario' => trim((string) ($l['admin'] ?? '')) ?: 'Sem nome',
            'acao'        => trim((string) ($l['acao'] ?? '')) ?: 'Sem descrição',
            'dia'         => $ts ? date('Y-m-d', $ts) : '',
            'mes'         => $ts ? date('Y-m', $ts) : '',
        ];
    }
    return ['nome' => 'Ações dos funcionários', 'campos' => [
        'funcionario' => ['Funcionário', 'texto'], 'acao' => ['Ação', 'texto'], 'dia' => ['Dia', 'data'], 'mes' => ['Mês', 'data'],
    ], 'linhas' => $linhas];
}

/* ===================== FONTES CRIADAS (Excel / SQL) ===================== */

/** Descobre os campos pela primeira linha (é "número" se todos os valores forem números) */
function biCamposDe(array $linhas): array {
    $campos = [];
    foreach (array_keys($linhas[0] ?? []) as $k) {
        $num = true;
        foreach (array_slice($linhas, 0, 200) as $l) {
            $v = $l[$k] ?? '';
            if ($v !== '' && $v !== null && !is_numeric($v)) { $num = false; break; }
        }
        $campos[(string) $k] = [(string) $k, $num ? 'numero' : 'texto'];
    }
    return $campos;
}

function biFonteCriada(string $id): array {
    foreach (biFontesCriadas() as $f) {
        if ((string) $f['id'] !== $id) continue;
        $linhas = $f['tipo'] === 'sql'
            ? bancoSelecionar('BI_SQL', (string) $f['sql'], BI_MAX_LINHAS)   // roda a consulta na hora
            : (array) ($f['linhas'] ?? []);                                   // planilha guardada
        return ['nome' => $f['nome'], 'campos' => biCamposDe($linhas), 'linhas' => $linhas];
    }
    throw new RuntimeException('Fonte não encontrada.');
}

/* ===================== ENTRADA ÚNICA ===================== */

function biCarregarFonte(string $fonte): array {
    $prontas = [
        'alunos' => 'biFonteAlunos', 'candidaturas' => 'biFonteCandidaturas', 'vagas' => 'biFonteVagas',
        'assinaturas' => 'biFonteAssinaturas', 'certificados' => 'biFonteCertificados',
        'acessos' => 'biFonteAcessos', 'mentoria' => 'biFonteMentoria', 'acoes_admin' => 'biFonteAcoesAdmin',
        'egressos' => 'biFonteEgressos', 'vagas_egressos' => 'biFonteVagasEgressos',
    ];
    if (in_array($fonte, ['egressos', 'vagas_egressos'], true) && !biPodeEgressos()) {
        throw new RuntimeException('Só a Coordenação de Egressos vê os dados dos egressos.');
    }
    if (isset($prontas[$fonte]))              $d = $prontas[$fonte]();
    elseif ($fonte === 'totvs')               $d = biFonteTotvs();
    elseif (strpos($fonte, 'custom:') === 0)  $d = biFonteCriada(substr($fonte, 7));
    elseif (strpos($fonte, 'rel:') === 0)     $d = biFonteRelatorio(substr($fonte, 4));
    else throw new RuntimeException('Fonte desconhecida.');

    $campos = [];
    foreach ($d['campos'] as $k => [$rot, $tipo]) $campos[$k] = ['rotulo' => $rot, 'tipo' => $tipo];
    return ['nome' => $d['nome'], 'campos' => $campos, 'linhas' => array_slice($d['linhas'], 0, 50000)];
}

/* ===================== PAINEL PADRÃO ===================== */

/**
 * Painel da primeira vez (e de "Restaurar padrão"): os MESMOS indicadores do
 * Dashboard Admin, agora editáveis. Cada visual tem: título, fonte, tipo, eixo,
 * agregação, campo, valor, filtros, largura (1 a 4), ordem, limite e cor.
 */
function biPainelPadrao(): array {
    $n = 0;
    $v = function ($titulo, $fonte, $tipo, array $extra = []) use (&$n) {
        $n++;
        return array_merge(['id' => 'p' . $n, 'titulo' => $titulo, 'fonte' => $fonte, 'tipo' => $tipo,
            'eixo' => '', 'agg' => 'contagem', 'campo' => '', 'valor' => '', 'filtros' => [], 'largura' => 1,
            'ordem' => 'valor', 'limite' => 0, 'cor' => '#4A9FCA'], $extra);
    };
    return [
        $v('Total de alunos', 'alunos', 'cartao'),
        $v('Com currículo preenchido', 'alunos', 'cartao', ['filtros' => [['campo' => 'temCurriculo', 'valor' => 'Sim']], 'cor' => '#D4A24C']),
        $v('Taxa de empregabilidade', 'alunos', 'cartao', ['agg' => 'pct', 'campo' => 'empregabilidade', 'valor' => 'Na área, Fora da área', 'cor' => '#22A06B']),
        $v('Buscando oportunidade', 'alunos', 'cartao', ['filtros' => [['campo' => 'empregabilidade', 'valor' => 'Buscando']], 'cor' => '#98A2B3']),
        $v('Empregado dentro da área', 'alunos', 'cartao', ['filtros' => [['campo' => 'empregabilidade', 'valor' => 'Na área']], 'cor' => '#2F855A']),
        $v('Empregado fora da área', 'alunos', 'cartao', ['filtros' => [['campo' => 'empregabilidade', 'valor' => 'Fora da área']], 'cor' => '#8A4B6B']),
        $v('Contratos assinados', 'assinaturas', 'cartao', ['filtros' => [['campo' => 'tipo', 'valor' => 'Contrato de estágio'], ['campo' => 'status', 'valor' => 'Assinado']], 'cor' => '#2B6EA6']),
        $v('Assinaturas pendentes', 'assinaturas', 'cartao', ['filtros' => [['campo' => 'status', 'valor' => 'Solicitado, Em análise']], 'cor' => '#D4A24C']),
        $v('Alunos por curso', 'alunos', 'barras', ['eixo' => 'curso', 'largura' => 2]),
        $v('Status profissional', 'alunos', 'rosca', ['eixo' => 'status', 'largura' => 2]),
        $v('Taxa de conclusão das assinaturas', 'assinaturas', 'cartao', ['agg' => 'pct', 'campo' => 'status', 'valor' => 'Assinado', 'cor' => '#22A06B']),
        $v('Tempo médio até assinar (dias)', 'assinaturas', 'cartao', ['agg' => 'media', 'campo' => 'dias', 'cor' => '#6B3FA0']),
        $v('Média de certificados por aluno', 'alunos', 'cartao', ['agg' => 'media', 'campo' => 'certificados', 'cor' => '#E67E22']),
        $v('Fim de estágio assinados', 'assinaturas', 'cartao', ['filtros' => [['campo' => 'tipo', 'valor' => 'Fim de estágio'], ['campo' => 'status', 'valor' => 'Assinado']], 'cor' => '#6B3FA0']),
        $v('Top 5 certificados', 'certificados', 'barras', ['eixo' => 'titulo', 'limite' => 5, 'largura' => 2]),
        $v('Acessos por dia (pessoas)', 'acessos', 'linha', ['eixo' => 'dia', 'agg' => 'distinto', 'campo' => 'usuario', 'ordem' => 'rotulo', 'largura' => 2]),
        $v('Candidaturas por andamento', 'candidaturas', 'colunas', ['eixo' => 'status', 'largura' => 2, 'cor' => '#E35444']),
        $v('Vagas por origem', 'vagas', 'pizza', ['eixo' => 'origem', 'largura' => 2]),
    ];
}

/* ===================== MODELOS PRONTOS ===================== */

/** Monta um visual com os valores padrão */
function biVisual(string $id, string $titulo, string $fonte, string $tipo, array $extra = []): array {
    return array_merge(['id' => $id, 'titulo' => $titulo, 'fonte' => $fonte, 'tipo' => $tipo,
        'eixo' => '', 'agg' => 'contagem', 'campo' => '', 'valor' => '', 'filtros' => [], 'largura' => 1,
        'ordem' => 'valor', 'limite' => 0, 'cor' => '#4A9FCA'], $extra);
}

/** Antigo "Funil e Indicadores" (admin-funil.php), agora editável */
function biModeloFunil(): array {
    return [
        biVisual('f1', 'Vagas publicadas', 'vagas', 'cartao', ['cor' => '#58A1D4']),
        biVisual('f2', 'Candidaturas', 'candidaturas', 'cartao', ['cor' => '#4A9FCA']),
        biVisual('f3', 'Entrevistas', 'candidaturas', 'cartao', ['filtros' => [['campo' => 'etapa', 'valor' => '3. Entrevista, 4. Contratação']], 'cor' => '#E35444']),
        biVisual('f4', 'Conversão em contratação', 'candidaturas', 'cartao', ['agg' => 'pct', 'campo' => 'etapa', 'valor' => '4. Contratação', 'cor' => '#27AE60']),
        biVisual('f5', 'Funil de candidaturas', 'candidaturas', 'funil', ['eixo' => 'etapa', 'ordem' => 'rotulo', 'largura' => 4]),
        biVisual('f6', 'Candidaturas por mês', 'candidaturas', 'linha', ['eixo' => 'mes', 'ordem' => 'rotulo', 'largura' => 2]),
        biVisual('f7', 'Candidaturas por curso', 'candidaturas', 'barras', ['eixo' => 'curso', 'limite' => 15, 'largura' => 2]),
        biVisual('f8', 'Empresas que mais contratam', 'candidaturas', 'barras', ['eixo' => 'empresa', 'limite' => 8, 'largura' => 2, 'cor' => '#27AE60',
            'filtros' => [['campo' => 'etapa', 'valor' => '4. Contratação']]]),
        biVisual('f9', 'Empresas sem retorno (14+ dias)', 'candidaturas', 'barras', ['eixo' => 'empresa', 'limite' => 8, 'largura' => 2, 'cor' => '#E35444',
            'filtros' => [['campo' => 'semRetorno', 'valor' => 'Sim']]]),
        biVisual('f10', 'Taxa de colocação por curso (%)', 'alunos', 'tabela', ['eixo' => 'curso', 'agg' => 'pct', 'campo' => 'empregabilidade', 'valor' => 'Na área, Fora da área', 'largura' => 2]),
        biVisual('f11', 'Ações dos funcionários por mês', 'acoes_admin', 'colunas', ['eixo' => 'mes', 'ordem' => 'rotulo', 'largura' => 2, 'cor' => '#7F8C8D']),
    ];
}

/** Antiga aba "Painel" do Painel de Egressos, agora editável */
function biModeloEgressos(): array {
    return [
        biVisual('e1', 'Egressos cadastrados', 'egressos', 'cartao', ['cor' => '#58A1D4']),
        biVisual('e2', 'Taxa de empregabilidade', 'egressos', 'cartao', ['agg' => 'pct', 'campo' => 'ocupado', 'valor' => 'Sim', 'cor' => '#27AE60']),
        biVisual('e3', 'Atuando na área (dos ocupados)', 'egressos', 'cartao', ['agg' => 'pct', 'campo' => 'grupo', 'valor' => 'Na área',
            'filtros' => [['campo' => 'ocupado', 'valor' => 'Sim']], 'cor' => '#4A9FCA']),
        biVisual('e4', 'Buscando oportunidade', 'egressos', 'cartao', ['filtros' => [['campo' => 'grupo', 'valor' => 'Buscando']], 'cor' => '#E35444']),
        biVisual('e5', 'Vagas no mural', 'vagas_egressos', 'cartao', ['filtros' => [['campo' => 'situacao', 'valor' => 'Publicada']], 'cor' => '#2B7FA6']),
        biVisual('e6', 'Aguardando moderação', 'vagas_egressos', 'cartao', ['filtros' => [['campo' => 'situacao', 'valor' => 'Aguardando moderação']], 'cor' => '#D4A24C']),
        biVisual('e7', 'Candidaturas de alunos geradas', 'vagas_egressos', 'cartao', ['agg' => 'soma', 'campo' => 'candidaturas', 'cor' => '#6B3FA0']),
        biVisual('e8', 'Tempo médio até a 1ª colocação (meses)', 'egressos', 'cartao', ['agg' => 'media', 'campo' => 'meses', 'cor' => '#E67E22']),
        biVisual('e9', 'Situação profissional', 'egressos', 'rosca', ['eixo' => 'grupo', 'largura' => 2]),
        biVisual('e10', 'Egressos por curso', 'egressos', 'colunas', ['eixo' => 'curso', 'largura' => 2]),
        biVisual('e11', 'Egressos por ano de conclusão', 'egressos', 'colunas', ['eixo' => 'ano', 'ordem' => 'rotulo', 'largura' => 2, 'cor' => '#6B3FA0']),
        biVisual('e12', 'Empregabilidade por curso (%)', 'egressos', 'tabela', ['eixo' => 'curso', 'agg' => 'pct', 'campo' => 'ocupado', 'valor' => 'Sim', 'largura' => 2]),
        biVisual('e13', 'Onde nossos egressos trabalham', 'egressos', 'barras', ['eixo' => 'empresa', 'limite' => 8, 'largura' => 2,
            'filtros' => [['campo' => 'temEmpresa', 'valor' => 'Sim']]]),
        biVisual('e14', 'Egressos que mais divulgam vagas', 'vagas_egressos', 'barras', ['eixo' => 'divulgador', 'limite' => 5, 'largura' => 2,
            'filtros' => [['campo' => 'situacao', 'valor' => 'Publicada, Encerrada, Expirada']], 'cor' => '#27AE60']),
        biVisual('e15', 'Aceitam mentoria', 'egressos', 'cartao', ['filtros' => [['campo' => 'mentoria', 'valor' => 'Sim']], 'cor' => '#3A8FB0']),
        biVisual('e16', 'Perfil desatualizado (+12 meses)', 'egressos', 'cartao', ['filtros' => [['campo' => 'desatualizado', 'valor' => 'Sim']], 'cor' => '#D4A24C']),
        biVisual('e17', 'Situação não informada', 'egressos', 'cartao', ['filtros' => [['campo' => 'grupo', 'valor' => 'Não informado']], 'cor' => '#98A2B3']),
    ];
}

/** Modelos que dá para usar ao criar um painel (e no "Restaurar padrão") */
function biModelos(): array {
    $m = [
        'alunos' => ['Visão geral dos alunos', 'Os indicadores do antigo Dashboard Admin.'],
        'funil'  => ['Funil de candidaturas', 'Do anúncio da vaga à contratação (antigo Funil e Indicadores).'],
    ];
    if (biPodeEgressos()) $m['egressos'] = ['Empregabilidade dos egressos', 'A antiga aba Painel do Painel de Egressos.'];
    return $m;
}

function biModeloVisuais(string $modelo): array {
    if ($modelo === 'alunos')   return biPainelPadrao();
    if ($modelo === 'funil')    return biModeloFunil();
    if ($modelo === 'egressos') return biModeloEgressos();
    return [];
}

/* ===================== PASTAS E PAINÉIS ===================== */

const BI_PASTA_PARTICULAR = 'particular';

/** Cria as pastas e os painéis prontos na primeira vez */
function biGarantirEstrutura(): void {
    if (dadosExiste('bi/pastas')) return;
    $agora = date('Y-m-d H:i');
    dadosGravar('bi/pastas', [
        ['id' => 'alunos',   'nome' => 'Alunos',        'acesso' => 'todos',    'fixa' => true, 'criadoEm' => $agora],
        ['id' => 'vagas',    'nome' => 'Vagas e Funil', 'acesso' => 'todos',    'fixa' => true, 'criadoEm' => $agora],
        ['id' => 'egressos', 'nome' => 'Egressos',      'acesso' => 'egressos', 'fixa' => true, 'criadoEm' => $agora],
    ]);
    $base = ['criadoPor' => 0, 'criadoPorNome' => 'Central de Estágios', 'criadoEm' => $agora, 'modificadoEm' => $agora, 'modificadoPorNome' => 'Central de Estágios'];
    dadosOperar('bi/paineis', function (array &$lista) use ($base) {
        $lista[] = $base + ['id' => 'alunos', 'nome' => 'Visão geral dos alunos', 'descricao' => 'Indicadores, assinaturas, estágio obrigatório e engajamento dos alunos.', 'pasta' => 'alunos', 'modelo' => 'alunos', 'pagina' => 'admin-dashboard.php', 'visuais' => biPainelPadrao()];
        $lista[] = $base + ['id' => 'funil', 'nome' => 'Funil de candidaturas', 'descricao' => 'Do anúncio da vaga à contratação.', 'pasta' => 'vagas', 'modelo' => 'funil', 'visuais' => biModeloFunil()];
        $lista[] = $base + ['id' => 'egressos', 'nome' => 'Empregabilidade dos egressos', 'descricao' => 'Situação profissional, cursos, empresas e vagas dos egressos.', 'pasta' => 'egressos', 'modelo' => 'egressos', 'visuais' => biModeloEgressos()];
    });
}

/**
 * Painéis com tela própria: abrem uma página pronta, exatamente como ela é,
 * em vez do editor. Hoje: "Visão geral dos alunos" -> admin-dashboard.php.
 * (Corrige também quem já tinha criado os painéis numa versão anterior.)
 */
function biPaginasFixas(): array {
    return ['alunos' => 'admin-dashboard.php'];
}
function biGarantirPaginas(): void {
    $fixas = biPaginasFixas();
    $falta = false;
    foreach (dadosLer('bi/paineis') as $p) {
        if (isset($fixas[$p['id'] ?? '']) && ($p['pagina'] ?? '') !== $fixas[$p['id']]) $falta = true;
    }
    if (!$falta) return;
    dadosOperar('bi/paineis', function (array &$lista) use ($fixas) {
        foreach ($lista as $i => $p) {
            if (isset($fixas[$p['id'] ?? ''])) $lista[$i]['pagina'] = $fixas[$p['id']];
        }
    });
}

/** Leva o painel antigo de cada funcionário (bi/painel_<id>) para a pasta particular dele */
function biMigrarPainelAntigo(int $userId, string $nomeUsuario): void {
    $chave = 'bi/painel_' . $userId;
    if (!dadosExiste($chave)) return;
    foreach (dadosLer('bi/paineis') as $p) if (($p['migradoDe'] ?? '') === $chave) return;
    $antigo = dadosLer($chave);
    if (!$antigo) return;
    $agora = date('Y-m-d H:i');
    $novo = ['id' => uniqid('p'), 'nome' => 'Meu painel (versão anterior)', 'descricao' => 'O painel que você tinha montado nos Painéis BI.',
        'pasta' => BI_PASTA_PARTICULAR, 'modelo' => 'alunos', 'visuais' => array_values($antigo), 'migradoDe' => $chave,
        'criadoPor' => $userId, 'criadoPorNome' => $nomeUsuario, 'criadoEm' => $agora, 'modificadoEm' => $agora, 'modificadoPorNome' => $nomeUsuario];
    dadosOperar('bi/paineis', function (array &$lista) use ($novo) { $lista[] = $novo; });
}

function biPastas(): array { return array_values(dadosLer('bi/pastas')); }

function biPodeVerPasta(string $pastaId, array $pastas): bool {
    if ($pastaId === BI_PASTA_PARTICULAR) return true;
    foreach ($pastas as $p) {
        if ((string) $p['id'] !== $pastaId) continue;
        return ($p['acesso'] ?? 'todos') !== 'egressos' || biPodeEgressos();
    }
    return false;
}

/** O usuário pode ver (e editar) este painel? */
function biPodeVerPainel(array $painel, int $userId, array $pastas): bool {
    $pasta = (string) ($painel['pasta'] ?? '');
    if ($pasta === BI_PASTA_PARTICULAR) return (int) ($painel['criadoPor'] ?? -1) === $userId;
    if (!biPodeVerPasta($pasta, $pastas)) return false;
    // painel que usa dados de egressos só aparece para a Coordenação de Egressos
    if (!biPodeEgressos()) {
        foreach ((array) ($painel['visuais'] ?? []) as $w) {
            if (in_array($w['fonte'] ?? '', ['egressos', 'vagas_egressos'], true)) return false;
        }
    }
    return true;
}

/* =====================================================================
 * RELATÓRIOS (estilo Salesforce)
 * ---------------------------------------------------------------------
 * Um relatório escolhe uma ORIGEM (obrigatória):
 *   totvs  -> alunos do TOTVS (modo teste, real ou banco: TOTVS_MODO)
 *   excel  -> uma planilha importada (fonte custom:<id> do tipo excel)
 *   sql    -> uma consulta num banco ou data lake (fonte custom:<id> do tipo sql)
 * e define colunas, filtros, agrupamento, resumo e ordem.
 * Guardados em data/bi/relatorios.json. Todo relatório vira fonte de
 * painel ("Relatório: nome"), e os painéis usam as linhas já filtradas.
 * ===================================================================== */

const BI_REL_OPERADORES = [
    'igual' => 'é igual a', 'diferente' => 'é diferente de', 'contem' => 'contém', 'nao_contem' => 'não contém',
    'comeca' => 'começa com', 'maior' => 'maior que', 'menor' => 'menor que', 'vazio' => 'está vazio', 'nao_vazio' => 'não está vazio',
];

/** Alunos do TOTVS, já traduzidos para os nomes da Central (sem CPF) */
function biFonteTotvs(): array {
    $r = totvsBuscarAlunos();
    if (empty($r['ok'])) throw new RuntimeException('TOTVS: ' . ($r['erro'] ?? 'não foi possível ler os dados.'));
    $naCentral = [];
    foreach (loadData('usuarios') as $u) {
        if (!empty($u['ra']) && $u['ra'] !== '—') $naCentral['ra:' . trim((string) $u['ra'])] = $u;
        if (!empty($u['cpf'])) $naCentral['cpf:' . preg_replace('/\D/', '', (string) $u['cpf'])] = $u;
    }
    $linhas = [];
    foreach ((array) $r['linhas'] as $bruta) {
        if (!is_array($bruta)) continue;
        $a = totvsConverterLinha($bruta);
        if (!$a) continue;
        $u = $naCentral['ra:' . $a['ra']] ?? ($a['cpf'] !== '' ? ($naCentral['cpf:' . $a['cpf']] ?? null) : null);
        $linhas[] = [
            'ra'        => $a['ra'],
            'nome'      => $a['nome'],
            'email'     => $a['email'],
            'curso'     => $a['curso'] !== '' ? $a['curso'] : 'Não informado',
            'periodo'   => $a['periodo'] !== '' ? $a['periodo'] : 'Não informado',
            'turno'     => $a['turno'] !== '' ? $a['turno'] : 'Não informado',
            'situacao'  => $a['situacao'] !== '' ? $a['situacao'] : 'Não informado',
            'naCentral' => $u ? 'Sim' : 'Não',
            'status'    => $u ? (trim((string) ($u['statusProfissional'] ?? '')) ?: 'Não definido') : 'Sem cadastro',
        ];
    }
    return ['nome' => 'TOTVS (' . totvsModo() . ')', 'campos' => [
        'ra' => ['RA', 'texto'], 'nome' => ['Nome', 'texto'], 'email' => ['E-mail', 'texto'], 'curso' => ['Curso', 'texto'],
        'periodo' => ['Período', 'texto'], 'turno' => ['Turno', 'texto'], 'situacao' => ['Situação da matrícula', 'texto'],
        'naCentral' => ['Cadastrado na Central', 'texto'], 'status' => ['Status profissional (Central)', 'texto'],
    ], 'linhas' => $linhas];
}

function biRelatorios(): array { return array_values(dadosLer('bi/relatorios')); }

function biRelatorioPorId(string $id): ?array {
    foreach (biRelatorios() as $r) if ((string) ($r['id'] ?? '') === $id) return $r;
    return null;
}

/** Mesmas regras dos painéis: pasta particular só o dono; pasta de egressos só a Coordenação de Egressos */
function biPodeVerRelatorio(array $r, int $userId, array $pastas): bool {
    $pasta = (string) ($r['pasta'] ?? '');
    if ($pasta === BI_PASTA_PARTICULAR) return (int) ($r['criadoPor'] ?? -1) === $userId;
    return biPodeVerPasta($pasta, $pastas);
}

/** Fonte de dados da origem do relatório (valida a origem: TOTVS, planilha ou banco) */
function biRelatorioFonteBase(array $def): string {
    $origem = (string) ($def['origem'] ?? '');
    $fonte  = (string) ($def['fonte'] ?? '');
    if ($origem === 'totvs') return 'totvs';
    if (!in_array($origem, ['excel', 'sql'], true)) throw new RuntimeException('Escolha a origem do relatório: TOTVS, planilha ou banco de dados.');
    if (strpos($fonte, 'custom:') !== 0) throw new RuntimeException('Escolha a ' . ($origem === 'excel' ? 'planilha' : 'consulta do banco') . '.');
    foreach (biFontesCriadas() as $f) {
        if ('custom:' . $f['id'] === $fonte && $f['tipo'] === $origem) return $fonte;
    }
    throw new RuntimeException('A fonte escolhida não existe mais ou não é do tipo certo.');
}

function biRelFiltroOk($valor, string $op, string $alvo): bool {
    $v = mb_strtolower(trim((string) $valor));
    $a = mb_strtolower(trim($alvo));
    $numV = is_numeric(str_replace(',', '.', $v)) ? (float) str_replace(',', '.', $v) : null;
    $numA = is_numeric(str_replace(',', '.', $a)) ? (float) str_replace(',', '.', $a) : null;
    switch ($op) {
        case 'diferente':  return !in_array($v, array_map('trim', explode(',', $a)), true);
        case 'contem':     return $a === '' || mb_strpos($v, $a) !== false;
        case 'nao_contem': return $a === '' || mb_strpos($v, $a) === false;
        case 'comeca':     return $a === '' || mb_strpos($v, $a) === 0;
        case 'maior':      return $numV !== null && $numA !== null ? $numV > $numA : strcmp($v, $a) > 0;
        case 'menor':      return $numV !== null && $numA !== null ? $numV < $numA : strcmp($v, $a) < 0;
        case 'vazio':      return $v === '';
        case 'nao_vazio':  return $v !== '';
        default:           return $a === '' || in_array($v, array_map('trim', explode(',', $a)), true);   // igual (aceita lista "a, b")
    }
}

/**
 * Roda o relatório: lê a origem, aplica filtros, escolhe as colunas e ordena.
 * Devolve ['nome', 'campos' => [chave => ['rotulo','tipo']], 'linhas', 'total'].
 */
function biRodarRelatorio(array $def, int $limite = 50000): array {
    $base = biCarregarFonte(biRelatorioFonteBase($def));
    $linhas = $base['linhas'];
    foreach ((array) ($def['filtros'] ?? []) as $f) {
        $campo = (string) ($f['campo'] ?? '');
        if ($campo === '' || !isset($base['campos'][$campo])) continue;
        $op = (string) ($f['op'] ?? 'igual');
        $alvo = (string) ($f['valor'] ?? '');
        $linhas = array_values(array_filter($linhas, fn($l) => biRelFiltroOk($l[$campo] ?? '', $op, $alvo)));
    }
    $ord = (string) ($def['ordenar'] ?? '');
    if ($ord !== '' && isset($base['campos'][$ord])) {
        $desc = ($def['direcao'] ?? 'asc') === 'desc';
        usort($linhas, function ($a, $b) use ($ord, $desc) {
            $x = $a[$ord] ?? ''; $y = $b[$ord] ?? '';
            $c = (is_numeric($x) && is_numeric($y)) ? ($x <=> $y) : strnatcasecmp((string) $x, (string) $y);
            return $desc ? -$c : $c;
        });
    }
    $total = count($linhas);
    $cols = array_values(array_filter((array) ($def['colunas'] ?? []), fn($c) => isset($base['campos'][$c])));
    if (!$cols) $cols = array_keys($base['campos']);
    $campos = [];
    foreach ($cols as $c) $campos[$c] = $base['campos'][$c];
    $lim = (int) ($def['limite'] ?? 0);
    if ($lim > 0) $linhas = array_slice($linhas, 0, $lim);
    $linhas = array_slice($linhas, 0, $limite);
    $saida = [];
    foreach ($linhas as $l) {
        $n = [];
        foreach ($cols as $c) $n[$c] = $l[$c] ?? '';
        $saida[] = $n;
    }
    return ['nome' => (string) ($def['nome'] ?? 'Relatório'), 'campos' => $campos, 'linhas' => $saida, 'total' => $total, 'origemNome' => $base['nome']];
}

/** Relatório salvo usado como fonte de painel */
function biFonteRelatorio(string $id): array {
    $r = biRelatorioPorId($id);
    if (!$r || !biPodeVerRelatorio($r, (int) ($_SESSION['user_id'] ?? 0), biPastas())) throw new RuntimeException('Relatório não encontrado ou sem permissão.');
    $d = biRodarRelatorio($r);
    $campos = [];
    foreach ($d['campos'] as $k => $c) $campos[$k] = [$c['rotulo'], $c['tipo']];
    return ['nome' => 'Relatório: ' . $r['nome'], 'campos' => $campos, 'linhas' => $d['linhas']];
}

/** Limpa a definição que veio da tela */
function biRelatorioLimpar(array $d): array {
    $ops = array_keys(BI_REL_OPERADORES);
    $filtros = [];
    foreach (array_slice((array) ($d['filtros'] ?? []), 0, 10) as $f) {
        if (!is_array($f) || trim((string) ($f['campo'] ?? '')) === '') continue;
        $op = in_array($f['op'] ?? '', $ops, true) ? $f['op'] : 'igual';
        $filtros[] = ['campo' => mb_substr((string) $f['campo'], 0, 60), 'op' => $op, 'valor' => mb_substr((string) ($f['valor'] ?? ''), 0, 200)];
    }
    $origem = in_array($d['origem'] ?? '', ['totvs', 'excel', 'sql'], true) ? $d['origem'] : '';
    return [
        'nome'      => mb_substr(trim((string) ($d['nome'] ?? '')), 0, 80),
        'descricao' => mb_substr(trim((string) ($d['descricao'] ?? '')), 0, 200),
        'pasta'     => (string) ($d['pasta'] ?? ''),
        'origem'    => $origem,
        'fonte'     => $origem === 'totvs' ? 'totvs' : mb_substr((string) ($d['fonte'] ?? ''), 0, 60),
        'colunas'   => array_values(array_map(fn($c) => mb_substr((string) $c, 0, 60), array_slice((array) ($d['colunas'] ?? []), 0, 40))),
        'filtros'   => $filtros,
        'agrupar'   => mb_substr((string) ($d['agrupar'] ?? ''), 0, 60),
        'resumoAgg' => in_array($d['resumoAgg'] ?? '', ['contagem', 'soma', 'media', 'distinto'], true) ? $d['resumoAgg'] : 'contagem',
        'resumoCampo' => mb_substr((string) ($d['resumoCampo'] ?? ''), 0, 60),
        'ordenar'   => mb_substr((string) ($d['ordenar'] ?? ''), 0, 60),
        'direcao'   => ($d['direcao'] ?? '') === 'desc' ? 'desc' : 'asc',
        'limite'    => max(0, min(50000, (int) ($d['limite'] ?? 0))),
    ];
}
