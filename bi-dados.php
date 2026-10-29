<?php
/**
 * =============================================================================
 * PAINÉIS — serviço de dados (responde em JSON, usado por admin-paineis.php)
 * -----------------------------------------------------------------------------
 *   GET  ?inicio=1                 -> pastas, painéis (sem os visuais), fontes e modelos
 *   GET  ?painel=<id>              -> um painel com os visuais
 *   GET  ?fonte=alunos             -> tabela da fonte (campos + linhas)
 *
 *   POST action=salvar_painel      -> grava os visuais (id, visuais em JSON)
 *   POST action=painel_criar       -> cria (nome, descricao, pasta, modelo)
 *   POST action=painel_editar      -> renomeia / muda de pasta (id, nome, descricao, pasta)
 *   POST action=painel_duplicar    -> copia (id)
 *   POST action=painel_excluir     -> apaga (id)
 *   POST action=padrao             -> volta o painel ao modelo dele (id)
 *   POST action=pasta_criar        -> cria pasta (nome, acesso = todos | egressos)
 *   POST action=pasta_renomear     -> (id, nome)
 *   POST action=pasta_excluir      -> só pasta criada por alguém e vazia (id)
 *   POST action=fonte_excel | fonte_sql | fonte_apagar  -> fontes criadas
 *
 *   RELATÓRIOS (origem TOTVS, planilha ou banco/data lake):
 *   GET  ?relatorios=1             -> lista + operadores + planilhas/consultas disponíveis
 *   GET  ?relatorio=<id>           -> definição de um relatório
 *   POST action=rel_previa         -> roda uma definição (campo def, JSON) e devolve as linhas
 *   POST action=rel_salvar         -> cria ou atualiza (id opcional, def)
 *   POST action=rel_duplicar | rel_excluir (id)
 *
 *   PAINEL COM TELA PRÓPRIA (Visão geral dos alunos):
 *   GET  ?layout=<id>              -> arrumação salva (ordem, ocultos, títulos, larguras, visuais extras)
 *   POST action=salvar_layout      -> grava a arrumação (id, layout em JSON)
 *
 * Só funcionários abrem (lista em includes/perfis.php).
 * =============================================================================
 */
require_once 'includes/config.php';
require_once 'includes/bi.php';
checkAuth();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function biResponder(array $d, int $codigo = 200): void {
    http_response_code($codigo);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

// Só chamadas da própria tela (mesma proteção do chat da mentoria)
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') biResponder(['ok' => false, 'erro' => 'Requisição inválida.'], 400);
if (!perfilPode('gestao')) biResponder(['ok' => false, 'erro' => 'Sem permissão.'], 403);

$userId   = (int) $_SESSION['user_id'];
$userNome = (string) ($_SESSION['user_name'] ?? 'Funcionário');

biGarantirEstrutura();
biGarantirPaginas();
biMigrarPainelAntigo($userId, $userNome);

/** Texto curto e limpo */
function biTexto(string $campo, int $max): string {
    return mb_substr(trim((string) ($_POST[$campo] ?? '')), 0, $max);
}

/** Resumo do painel para a lista (sem os visuais) */
function biResumo(array $p): array {
    return [
        'id' => (string) $p['id'], 'nome' => (string) $p['nome'], 'descricao' => (string) ($p['descricao'] ?? ''), 'pasta' => (string) $p['pasta'],
        'modelo' => (string) ($p['modelo'] ?? ''), 'criadoPor' => (int) ($p['criadoPor'] ?? 0), 'criadoPorNome' => (string) ($p['criadoPorNome'] ?? ''),
        'criadoEm' => (string) ($p['criadoEm'] ?? ''), 'modificadoEm' => (string) ($p['modificadoEm'] ?? ''),
        'modificadoPorNome' => (string) ($p['modificadoPorNome'] ?? ''), 'visuais' => count((array) ($p['visuais'] ?? [])),
        'pagina' => (string) ($p['pagina'] ?? ''),   // painel com tela própria
    ];
}

/** Altera um painel com trava. Devolve o painel alterado, ou para com erro. */
function biAlterarPainel(string $id, int $userId, string $userNome, callable $fn): array {
    $pastas = biPastas();
    $r = dadosOperar('bi/paineis', function (array &$lista) use ($id, $fn, $pastas, $userId, $userNome) {
        foreach ($lista as $i => $p) {
            if ((string) ($p['id'] ?? '') !== $id) continue;
            if (!biPodeVerPainel($p, $userId, $pastas)) return ['erro' => 'Sem permissão para este painel.'];
            $p = $fn($p);
            $p['modificadoEm'] = date('Y-m-d H:i');
            $p['modificadoPorNome'] = $userNome;
            $lista[$i] = $p;
            return ['painel' => $p];
        }
        return ['erro' => 'Painel não encontrado.'];
    });
    if (!is_array($r) || isset($r['erro'])) biResponder(['ok' => false, 'erro' => is_array($r) ? $r['erro'] : 'Não foi possível salvar.'], 400);
    return $r['painel'];
}

/** Confere se a pasta existe e se o usuário pode usar */
function biPastaValida(string $pasta): string {
    if (!biPodeVerPasta($pasta, biPastas())) biResponder(['ok' => false, 'erro' => 'Escolha uma pasta válida.'], 400);
    return $pasta;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $pastas = biPastas();

        if (isset($_GET['inicio'])) {
            $visiveis = array_values(array_filter($pastas, fn($p) => biPodeVerPasta((string) $p['id'], $pastas)));
            $paineis  = array_values(array_filter(dadosLer('bi/paineis'), fn($p) => biPodeVerPainel($p, $userId, $pastas)));
            biResponder([
                'ok'             => true,
                'usuario'        => $userId,
                'pastas'         => $visiveis,
                'paineis'        => array_map('biResumo', $paineis),
                'modelos'        => biModelos(),
                'podeEgressos'   => biPodeEgressos(),
                'fontes'         => biListaFontes(),
                'criadas'        => array_map(fn($f) => ['id' => $f['id'], 'nome' => $f['nome'], 'tipo' => $f['tipo']], biFontesCriadas()),
                'sqlConfigurado' => bancoConfigurado('BI_SQL'),
            ]);
        }

        /* ---------- Relatórios ---------- */
        if (isset($_GET['relatorios'])) {
            $lista = array_values(array_filter(biRelatorios(), fn($r) => biPodeVerRelatorio($r, $userId, $pastas)));
            usort($lista, fn($a, $b) => strcmp((string) ($b['modificadoEm'] ?? ''), (string) ($a['modificadoEm'] ?? '')));
            biResponder([
                'ok'         => true,
                'relatorios' => array_map(fn($r) => [
                    'id' => $r['id'], 'nome' => $r['nome'], 'descricao' => $r['descricao'] ?? '', 'pasta' => $r['pasta'],
                    'origem' => $r['origem'], 'criadoPor' => (int) ($r['criadoPor'] ?? 0), 'criadoPorNome' => $r['criadoPorNome'] ?? '',
                    'modificadoEm' => $r['modificadoEm'] ?? '', 'modificadoPorNome' => $r['modificadoPorNome'] ?? '',
                ], $lista),
                'operadores' => BI_REL_OPERADORES,
                'planilhas'  => array_values(array_map(fn($f) => ['id' => 'custom:' . $f['id'], 'nome' => $f['nome']], array_filter(biFontesCriadas(), fn($f) => $f['tipo'] === 'excel'))),
                'consultas'  => array_values(array_map(fn($f) => ['id' => 'custom:' . $f['id'], 'nome' => $f['nome']], array_filter(biFontesCriadas(), fn($f) => $f['tipo'] === 'sql'))),
                'totvsModo'  => totvsModo(),
                'sqlConfigurado' => bancoConfigurado('BI_SQL'),
            ]);
        }
        if (isset($_GET['relatorio'])) {
            $r = biRelatorioPorId((string) $_GET['relatorio']);
            if (!$r || !biPodeVerRelatorio($r, $userId, $pastas)) biResponder(['ok' => false, 'erro' => 'Relatório não encontrado ou sem permissão.'], 404);
            biResponder(['ok' => true, 'relatorio' => $r]);
        }

        /* ---------- Arrumação de painel com tela própria ---------- */
        if (isset($_GET['layout'])) {
            foreach (dadosLer('bi/paineis') as $p) {
                if ((string) ($p['id'] ?? '') !== (string) $_GET['layout']) continue;
                if (!biPodeVerPainel($p, $userId, $pastas)) break;
                biResponder(['ok' => true, 'layout' => $p['layout'] ?? new stdClass(), 'fontes' => biListaFontes()]);
            }
            biResponder(['ok' => false, 'erro' => 'Painel não encontrado.'], 404);
        }

        if (isset($_GET['painel'])) {
            foreach (dadosLer('bi/paineis') as $p) {
                if ((string) ($p['id'] ?? '') !== (string) $_GET['painel']) continue;
                if (!biPodeVerPainel($p, $userId, $pastas)) break;
                biResponder(['ok' => true, 'painel' => $p]);
            }
            biResponder(['ok' => false, 'erro' => 'Painel não encontrado ou sem permissão.'], 404);
        }

        biResponder(['ok' => true] + biCarregarFonte((string) ($_GET['fonte'] ?? '')));
    }

    $acao = (string) ($_POST['action'] ?? '');
    $id   = (string) ($_POST['id'] ?? '');

    /* ---------- Painéis ---------- */
    if ($acao === 'salvar_painel') {
        $visuais = json_decode((string) ($_POST['visuais'] ?? '[]'), true);
        if (!is_array($visuais) || count($visuais) > 60) biResponder(['ok' => false, 'erro' => 'Painel inválido.'], 400);
        biAlterarPainel($id, $userId, $userNome, function (array $p) use ($visuais) { $p['visuais'] = array_values($visuais); return $p; });
        biResponder(['ok' => true]);
    }

    if ($acao === 'painel_criar') {
        $nome = biTexto('nome', 80);
        if ($nome === '') biResponder(['ok' => false, 'erro' => 'Dê um nome para o painel.'], 400);
        $pasta  = biPastaValida((string) ($_POST['pasta'] ?? ''));
        $modelo = (string) ($_POST['modelo'] ?? '');
        if ($modelo !== '' && !isset(biModelos()[$modelo])) $modelo = '';
        $agora = date('Y-m-d H:i');
        $novo = ['id' => uniqid('p'), 'nome' => $nome, 'descricao' => biTexto('descricao', 200), 'pasta' => $pasta, 'modelo' => $modelo,
                 'visuais' => biModeloVisuais($modelo), 'criadoPor' => $userId, 'criadoPorNome' => $userNome,
                 'criadoEm' => $agora, 'modificadoEm' => $agora, 'modificadoPorNome' => $userNome];
        dadosOperar('bi/paineis', function (array &$lista) use ($novo) { $lista[] = $novo; });
        biResponder(['ok' => true, 'id' => $novo['id']]);
    }

    if ($acao === 'painel_editar') {
        $nome = biTexto('nome', 80);
        if ($nome === '') biResponder(['ok' => false, 'erro' => 'Dê um nome para o painel.'], 400);
        $pasta = biPastaValida((string) ($_POST['pasta'] ?? ''));
        $desc  = biTexto('descricao', 200);
        $p = biAlterarPainel($id, $userId, $userNome, function (array $p) use ($nome, $pasta, $desc, $userId) {
            // ao ir para a pasta particular, o painel passa a ser de quem moveu
            if ($pasta === BI_PASTA_PARTICULAR && ($p['pasta'] ?? '') !== BI_PASTA_PARTICULAR) $p['criadoPor'] = $userId;
            $p['nome'] = $nome; $p['pasta'] = $pasta; $p['descricao'] = $desc;
            return $p;
        });
        biResponder(['ok' => true, 'painel' => biResumo($p)]);
    }

    if ($acao === 'painel_duplicar') {
        $original = null;
        foreach (dadosLer('bi/paineis') as $p) if ((string) ($p['id'] ?? '') === $id) $original = $p;
        if (!$original || !biPodeVerPainel($original, $userId, biPastas())) biResponder(['ok' => false, 'erro' => 'Painel não encontrado.'], 404);
        $agora = date('Y-m-d H:i');
        $copia = array_merge($original, ['id' => uniqid('p'), 'nome' => mb_substr($original['nome'] . ' (cópia)', 0, 80),
            'criadoPor' => $userId, 'criadoPorNome' => $userNome, 'criadoEm' => $agora, 'modificadoEm' => $agora, 'modificadoPorNome' => $userNome]);
        unset($copia['migradoDe'], $copia['pagina']);   // a cópia vira um painel editável (com os visuais do modelo)
        dadosOperar('bi/paineis', function (array &$lista) use ($copia) { $lista[] = $copia; });
        biResponder(['ok' => true, 'id' => $copia['id']]);
    }

    if ($acao === 'painel_excluir') {
        $pastas = biPastas();
        $r = dadosOperar('bi/paineis', function (array &$lista) use ($id, $userId, $pastas) {
            foreach ($lista as $i => $p) {
                if ((string) ($p['id'] ?? '') !== $id) continue;
                if (!biPodeVerPainel($p, $userId, $pastas)) return 'sem';
                unset($lista[$i]);
                $lista = array_values($lista);
                return 'ok';
            }
            return 'nao';
        });
        if ($r !== 'ok') biResponder(['ok' => false, 'erro' => $r === 'sem' ? 'Sem permissão.' : 'Painel não encontrado.'], 400);
        biResponder(['ok' => true]);
    }

    if ($acao === 'padrao') {
        $p = biAlterarPainel($id, $userId, $userNome, function (array $p) {
            $p['visuais'] = biModeloVisuais((string) ($p['modelo'] ?? ''));
            return $p;
        });
        biResponder(['ok' => true, 'painel' => $p]);
    }

    /* ---------- Arrumação de painel com tela própria ---------- */
    if ($acao === 'salvar_layout') {
        $layout = json_decode((string) ($_POST['layout'] ?? '{}'), true);
        if (!is_array($layout) || strlen((string) ($_POST['layout'] ?? '')) > 200000) biResponder(['ok' => false, 'erro' => 'Arrumação inválida.'], 400);
        biAlterarPainel($id, $userId, $userNome, function (array $p) use ($layout) { $p['layout'] = $layout; return $p; });
        biResponder(['ok' => true]);
    }

    /* ---------- Relatórios ---------- */
    if ($acao === 'rel_previa') {
        $def = biRelatorioLimpar((array) json_decode((string) ($_POST['def'] ?? '{}'), true));
        $r = biRodarRelatorio($def);
        biResponder(['ok' => true] + $r);
    }

    if ($acao === 'rel_salvar') {
        $def = biRelatorioLimpar((array) json_decode((string) ($_POST['def'] ?? '{}'), true));
        if ($def['nome'] === '') biResponder(['ok' => false, 'erro' => 'Dê um nome para o relatório.'], 400);
        biPastaValida($def['pasta']);
        biRelatorioFonteBase($def);                     // origem obrigatória: TOTVS, planilha ou banco
        $agora = date('Y-m-d H:i');
        $idRel = $id;
        $r = dadosOperar('bi/relatorios', function (array &$lista) use ($def, &$idRel, $agora, $userId, $userNome) {
            $pastas = biPastas();
            foreach ($lista as $i => $r) {
                if ($idRel === '' || (string) ($r['id'] ?? '') !== $idRel) continue;
                if (!biPodeVerRelatorio($r, $userId, $pastas)) return 'sem';
                $lista[$i] = array_merge($r, $def, ['modificadoEm' => $agora, 'modificadoPorNome' => $userNome]);
                if ($def['pasta'] === BI_PASTA_PARTICULAR && ($r['pasta'] ?? '') !== BI_PASTA_PARTICULAR) $lista[$i]['criadoPor'] = $userId;
                return 'ok';
            }
            $idRel = uniqid('r');
            $lista[] = array_merge(['id' => $idRel], $def, ['criadoPor' => $userId, 'criadoPorNome' => $userNome,
                'criadoEm' => $agora, 'modificadoEm' => $agora, 'modificadoPorNome' => $userNome]);
            return 'ok';
        });
        if ($r !== 'ok') biResponder(['ok' => false, 'erro' => 'Sem permissão para este relatório.'], 403);
        biResponder(['ok' => true, 'id' => $idRel]);
    }

    if ($acao === 'rel_duplicar') {
        $orig = biRelatorioPorId($id);
        if (!$orig || !biPodeVerRelatorio($orig, $userId, biPastas())) biResponder(['ok' => false, 'erro' => 'Relatório não encontrado.'], 404);
        $agora = date('Y-m-d H:i');
        $copia = array_merge($orig, ['id' => uniqid('r'), 'nome' => mb_substr($orig['nome'] . ' (cópia)', 0, 80), 'criadoPor' => $userId,
            'criadoPorNome' => $userNome, 'criadoEm' => $agora, 'modificadoEm' => $agora, 'modificadoPorNome' => $userNome]);
        dadosOperar('bi/relatorios', function (array &$lista) use ($copia) { $lista[] = $copia; });
        biResponder(['ok' => true, 'id' => $copia['id']]);
    }

    if ($acao === 'rel_excluir') {
        $pastas = biPastas();
        $r = dadosOperar('bi/relatorios', function (array &$lista) use ($id, $userId, $pastas) {
            foreach ($lista as $i => $r) {
                if ((string) ($r['id'] ?? '') !== $id) continue;
                if (!biPodeVerRelatorio($r, $userId, $pastas)) return 'sem';
                unset($lista[$i]);
                $lista = array_values($lista);
                return 'ok';
            }
            return 'nao';
        });
        if ($r !== 'ok') biResponder(['ok' => false, 'erro' => $r === 'sem' ? 'Sem permissão.' : 'Relatório não encontrado.'], 400);
        biResponder(['ok' => true]);
    }

    /* ---------- Pastas ---------- */
    if ($acao === 'pasta_criar') {
        $nome = biTexto('nome', 60);
        if ($nome === '') biResponder(['ok' => false, 'erro' => 'Dê um nome para a pasta.'], 400);
        $acesso = (($_POST['acesso'] ?? '') === 'egressos' && biPodeEgressos()) ? 'egressos' : 'todos';
        $pasta = ['id' => uniqid('d'), 'nome' => $nome, 'acesso' => $acesso, 'fixa' => false,
                  'criadoPor' => $userId, 'criadoPorNome' => $userNome, 'criadoEm' => date('Y-m-d H:i')];
        dadosOperar('bi/pastas', function (array &$lista) use ($pasta) { $lista[] = $pasta; });
        biResponder(['ok' => true, 'pasta' => $pasta]);
    }

    if ($acao === 'pasta_renomear') {
        $nome = biTexto('nome', 60);
        if ($nome === '') biResponder(['ok' => false, 'erro' => 'Dê um nome para a pasta.'], 400);
        if ($id === BI_PASTA_PARTICULAR || !biPodeVerPasta($id, biPastas())) biResponder(['ok' => false, 'erro' => 'Sem permissão.'], 403);
        dadosOperar('bi/pastas', function (array &$lista) use ($id, $nome) {
            foreach ($lista as $i => $p) if ((string) $p['id'] === $id) $lista[$i]['nome'] = $nome;
        });
        biResponder(['ok' => true]);
    }

    if ($acao === 'pasta_excluir') {
        foreach (array_merge(dadosLer('bi/paineis'), biRelatorios()) as $p) {
            if ((string) ($p['pasta'] ?? '') === $id) biResponder(['ok' => false, 'erro' => 'A pasta tem painéis ou relatórios. Mova ou apague antes.'], 400);
        }
        $podeEg = biPodeEgressos();
        $r = dadosOperar('bi/pastas', function (array &$lista) use ($id, $podeEg) {
            foreach ($lista as $i => $p) {
                if ((string) $p['id'] !== $id) continue;
                if (!empty($p['fixa'])) return 'fixa';
                if (($p['acesso'] ?? '') === 'egressos' && !$podeEg) return 'sem';
                unset($lista[$i]);
                $lista = array_values($lista);
                return 'ok';
            }
            return 'nao';
        });
        $erros = ['fixa' => 'As pastas padrão não podem ser apagadas.', 'sem' => 'Sem permissão.', 'nao' => 'Pasta não encontrada.'];
        if ($r !== 'ok') biResponder(['ok' => false, 'erro' => $erros[$r] ?? 'Não foi possível apagar.'], 400);
        biResponder(['ok' => true]);
    }

    /* ---------- Fontes criadas (Excel e SQL) ---------- */
    if ($acao === 'fonte_excel' || $acao === 'fonte_sql') {
        $nome = biTexto('nome', 60);
        if ($nome === '') biResponder(['ok' => false, 'erro' => 'Dê um nome para a fonte.'], 400);
        $fonte = ['id' => uniqid('f'), 'nome' => $nome, 'criadoPor' => $userId, 'criadoEm' => date('Y-m-d H:i')];

        if ($acao === 'fonte_excel') {
            $linhas = json_decode((string) ($_POST['linhas'] ?? '[]'), true);
            if (!is_array($linhas) || !$linhas) biResponder(['ok' => false, 'erro' => 'A planilha está vazia.'], 400);
            // Limpeza: até 5000 linhas, 30 colunas e 200 caracteres por célula
            $limpas = [];
            foreach (array_slice($linhas, 0, BI_MAX_LINHAS) as $l) {
                if (!is_array($l)) continue;
                $nova = [];
                foreach (array_slice($l, 0, 30, true) as $k => $v) {
                    $k = mb_substr(trim((string) $k), 0, 40);
                    if ($k === '') continue;
                    $nova[$k] = is_numeric($v) ? $v + 0 : mb_substr((string) $v, 0, 200);
                }
                if ($nova) $limpas[] = $nova;
            }
            $fonte += ['tipo' => 'excel', 'linhas' => $limpas];
        } else {
            if (!bancoConfigurado('BI_SQL')) biResponder(['ok' => false, 'erro' => 'Configure BI_SQL_DSN (no .env ou no Render) para usar SQL.'], 400);
            $sql = trim((string) ($_POST['sql'] ?? ''));
            bancoSelecionar('BI_SQL', $sql, 1);   // testa antes de guardar: precisa ser SELECT e funcionar
            $fonte += ['tipo' => 'sql', 'sql' => $sql];
        }

        dadosOperar('bi/fontes', function (array &$lista) use ($fonte) { $lista[] = $fonte; });
        biResponder(['ok' => true, 'id' => 'custom:' . $fonte['id']]);
    }

    if ($acao === 'fonte_apagar') {
        dadosOperar('bi/fontes', function (array &$lista) use ($id) {
            $lista = array_values(array_filter($lista, fn($f) => (string) ($f['id'] ?? '') !== $id));
        });
        biResponder(['ok' => true]);
    }

    biResponder(['ok' => false, 'erro' => 'Ação desconhecida.'], 400);

} catch (Throwable $e) {
    biResponder(['ok' => false, 'erro' => $e->getMessage()], 400);
}
