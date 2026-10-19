<?php
/**
 * =============================================================================
 * ALERTAS POR CURSO, FILA DE E-MAILS E RESUMO SEMANAL
 * -----------------------------------------------------------------------------
 * Preferencias do aluno: campo "alertas" dentro do usuarios.json
 *   { "vagaNova": bool, "resumoSemanal": bool, "cursos": ["Curso A", ...] }
 *
 * Como a FSA nao tem cron, ha DOIS gatilhos para o envio:
 *   1) alertasTick(): roda no fim de qualquer pagina (header.php), no maximo uma
 *      vez por minuto, e so quando ha algo a fazer (resumo da semana ou fila).
 *   2) resumo-semanal.php?chave=...: URL protegida que um agendador externo
 *      (ex: cron-job.org) pode chamar. Quando a FSA tiver cron, basta chamar ela.
 *
 * E-mails em lote NAO saem na hora: entram na fila (data/fila_emails.json) e saem
 * em partes pequenas, para nao estourar o tempo da pagina nem o limite do Gmail.
 * =============================================================================
 */

require_once __DIR__ . '/egressos.php';
require_once __DIR__ . '/matching.php';

/** false = o aluno precisa ativar em "Meus Alertas" (opt-in). true = ja nasce ativo. */
const ALERTAS_PADRAO_ATIVO = false;
const ALERTAS_JANELA_DIAS  = 7;

/* ------------------------------------------------------------------ */
/* Preferencias                                                        */
/* ------------------------------------------------------------------ */

function alertasEhAluno(array $u): bool {
    return empty($u['is_admin']) && !in_array($u['tipo'] ?? '', ['egresso', 'admin_egressos'], true);
}

function alertasPrefs(array $u): array {
    $salvo = is_array($u['alertas'] ?? null) ? $u['alertas'] : [];
    $p = [
        'vagaNova'      => array_key_exists('vagaNova', $salvo) ? !empty($salvo['vagaNova']) : ALERTAS_PADRAO_ATIVO,
        'resumoSemanal' => array_key_exists('resumoSemanal', $salvo) ? !empty($salvo['resumoSemanal']) : ALERTAS_PADRAO_ATIVO,
        'cursos'        => [],
    ];
    $cursos = array_values(array_filter(array_map('trim', array_map('strval', (array) ($salvo['cursos'] ?? [])))));
    $meu = trim((string) ($u['curso'] ?? ''));
    if (empty($cursos) && $meu !== '' && $meu !== '—') $cursos = [$meu];
    $p['cursos'] = $cursos;
    return $p;
}

function alertasSalvarPrefs(int $userId, array $prefs): bool {
    $usuarios = loadData('usuarios');
    $achou = false;
    foreach ($usuarios as &$u) {
        if (($u['id'] ?? null) == $userId) {
            $u['alertas'] = [
                'vagaNova'      => !empty($prefs['vagaNova']),
                'resumoSemanal' => !empty($prefs['resumoSemanal']),
                'cursos'        => array_values($prefs['cursos'] ?? []),
            ];
            $achou = true;
            break;
        }
    }
    unset($u);
    if ($achou) saveData('usuarios', $usuarios);
    return $achou;
}

/** A vaga serve para algum dos cursos de interesse? (vaga sem curso = todos) */
function alertasVagaCombina(array $vaga, array $cursosInteresse): bool {
    $daVaga = array_values(array_filter(array_map('trim', explode(',', (string) ($vaga['courses'] ?? '')))));
    if (empty($daVaga) || empty($cursosInteresse)) return true;
    $meus = array_map('matchNormalizar', $cursosInteresse);
    foreach ($daVaga as $c) {
        if (in_array(matchNormalizar($c), $meus, true)) return true;
    }
    return false;
}

/** Momento em que a vaga entrou no ar (as vagas da FSA usam o id como timestamp) */
function alertasTimestampVaga(array $v, string $origem): int {
    if ($origem === 'egresso') return (int) ($v['publishedTs'] ?? $v['createdTs'] ?? 0);
    $id = (int) ($v['id'] ?? 0);
    if ($id > 1400000000 && $id < 4000000000) return $id;
    $d = DateTime::createFromFormat('d/m/Y', (string) ($v['createdAt'] ?? ''));
    return $d ? (int) $d->setTime(12, 0)->format('U') : 0;
}

/** Todas as vagas vigentes (FSA + egressos) e um mapa id => vaga para consultas */
function alertasContextoVagas(): array {
    $vigentes = [];
    $mapa = ['faculdade' => [], 'egresso' => []];
    foreach (array_values(loadData('platform_jobs')) as $j) {
        $mapa['faculdade'][(int) ($j['id'] ?? 0)] = $j;
        if (vagaVigente($j)) $vigentes[] = ['origem' => 'faculdade', 'vaga' => $j, 'ts' => alertasTimestampVaga($j, 'faculdade')];
    }
    foreach (egressoVagas() as $j) {
        $mapa['egresso'][(int) ($j['id'] ?? 0)] = $j;
        if (egressoVagaStatusEfetivo($j) === 'aprovada') $vigentes[] = ['origem' => 'egresso', 'vaga' => $j, 'ts' => alertasTimestampVaga($j, 'egresso')];
    }
    return ['vigentes' => $vigentes, 'mapa' => $mapa];
}

/* ------------------------------------------------------------------ */
/* Fila de e-mails (com trava de arquivo)                              */
/* ------------------------------------------------------------------ */

function alertasFilaOperar(callable $fn) {
    $path = DATA_PATH . 'fila_emails.json';
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $fp = @fopen($path, 'c+');
    if (!$fp) return null;
    flock($fp, LOCK_EX);
    $raw  = stream_get_contents($fp);
    $fila = json_decode($raw !== false && $raw !== '' ? $raw : '[]', true);
    if (!is_array($fila)) $fila = [];
    $res = $fn($fila);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode(array_values($fila), JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $res;
}

function alertasFilaTamanho(): int {
    $path = DATA_PATH . 'fila_emails.json';
    if (!is_file($path) || filesize($path) < 3) return 0;
    $d = json_decode((string) @file_get_contents($path), true);
    return is_array($d) ? count($d) : 0;
}

function alertasEnfileirarLote(array $itens): int {
    if (empty($itens)) return 0;
    return (int) alertasFilaOperar(function (array &$fila) use ($itens) {
        $chaves = [];
        foreach ($fila as $i) {
            if (!empty($i['chave'])) $chaves[$i['chave']] = true;
        }
        $n = 0;
        foreach ($itens as $it) {
            if (!empty($it['chave']) && isset($chaves[$it['chave']])) continue;
            $it['id']       = bin2hex(random_bytes(6));
            $it['ts']       = time();
            $it['tent']     = 0;
            $it['claim']    = 0;
            $it['naoAntes'] = 0;
            $fila[] = $it;
            $n++;
        }
        return $n;
    });
}

/** Envia ate $max e-mails da fila, parando se passar de $segundos */
function alertasProcessarFila(int $max = 5, int $segundos = 15): array {
    $agora = time();
    $lote = alertasFilaOperar(function (array &$fila) use ($max, $agora) {
        $sel = [];
        foreach ($fila as &$it) {
            if (count($sel) >= $max) break;
            if (($it['naoAntes'] ?? 0) > $agora) continue;
            if (($it['claim'] ?? 0) > $agora - 300) continue;   // outro processo esta cuidando
            $it['claim'] = $agora;
            $sel[] = $it;
        }
        unset($it);
        return $sel;
    });

    if (empty($lote)) return ['enviados' => 0, 'falhas' => 0, 'restantes' => alertasFilaTamanho()];

    $ok = []; $falhou = []; $liberar = [];
    $inicio = microtime(true);
    $semCredencial = false;

    foreach ($lote as $it) {
        if ($semCredencial || (microtime(true) - $inicio) > $segundos) { $liberar[] = $it['id']; continue; }
        $enviou = notificarEmail(
            (string) $it['to'], (string) ($it['nome'] ?? ''), (string) $it['assunto'], (string) $it['titulo'],
            (string) $it['corpo'], $it['cta'] ?? null, $it['ctaUrl'] ?? null
        );
        if ($enviou) {
            $ok[] = $it['id'];
        } else {
            if (function_exists('getUltimoErroEmail') && stripos((string) getUltimoErroEmail(), 'ausente') !== false) {
                $semCredencial = true;      // credenciais do Gmail nao configuradas: nao gasta tentativas
                $liberar[] = $it['id'];
            } else {
                $falhou[] = $it['id'];
            }
        }
    }

    $restantes = alertasFilaOperar(function (array &$fila) use ($ok, $falhou, $liberar) {
        $novo = [];
        foreach ($fila as $it) {
            $id = $it['id'] ?? '';
            if (in_array($id, $ok, true)) continue;
            if (in_array($id, $liberar, true)) { $it['claim'] = 0; }
            if (in_array($id, $falhou, true)) {
                $it['tent'] = (int) ($it['tent'] ?? 0) + 1;
                if ($it['tent'] >= 3) { error_log('alertas: descartado apos 3 falhas -> ' . ($it['to'] ?? '')); continue; }
                $it['claim'] = 0;
                $it['naoAntes'] = time() + 600;
            }
            $novo[] = $it;
        }
        $fila = $novo;
        return count($fila);
    });

    return ['enviados' => count($ok), 'falhas' => count($falhou), 'restantes' => (int) $restantes, 'semCredencial' => $semCredencial];
}

/* ------------------------------------------------------------------ */
/* Estado (ultimo resumo etc.)                                         */
/* ------------------------------------------------------------------ */

function alertasEstado(): array {
    $e = loadData('alertas_estado');
    return is_array($e) ? $e : [];
}
function alertasSalvarEstado(array $e): void {
    saveData('alertas_estado', $e);
}

/* ------------------------------------------------------------------ */
/* Conteudo dos e-mails                                                */
/* ------------------------------------------------------------------ */

function alertasLinkVaga(string $origem): string {
    return appBaseUrl() . '/jobs.php'; // vagas de egressos tambem ficam em jobs.php
}

function alertasCardVaga(string $origem, array $v, ?int $compat = null): string {
    $linha = array_filter([$v['company'] ?? '', $v['location'] ?? '', $v['salary'] ?? '']);
    $tag   = $origem === 'egresso' ? 'Vaga de egresso' : 'Vaga FSA';
    $dias  = vagaDiasRestantes($v);
    $prazo = '';
    if ($dias !== null && $dias >= 0 && $dias <= 7) {
        $prazo = ' &middot; <span style="color:#d97706">encerra ' . ($dias === 0 ? 'hoje' : 'em ' . $dias . ' dia' . ($dias > 1 ? 's' : '')) . '</span>';
    }
    $comp = $compat !== null ? ' &middot; <span style="color:#059669">compatibilidade ' . (int) $compat . '%</span>' : '';
    return '<div style="border:1px solid #e5e7eb;border-radius:8px;padding:10px 14px;margin:8px 0">'
         . '<strong style="color:#1e293b">' . emH($v['title'] ?? '') . '</strong><br>'
         . '<span style="color:#64748b;font-size:13px">' . emH(implode(' · ', $linha)) . '</span><br>'
         . '<span style="color:#94a3b8;font-size:12px">' . $tag . $prazo . $comp . '</span></div>';
}

/**
 * Monta o resumo semanal de um aluno. Devolve null se nao ha nada para contar
 * (a menos que $permitirVazio, usado no "enviar teste").
 */
function alertasMontarResumo(array $u, array $ctx, bool $permitirVazio = false): ?array {
    $prefs = alertasPrefs($u);
    $corte = time() - ALERTAS_JANELA_DIAS * 86400;

    $novas = [];
    foreach ($ctx['vigentes'] as $c) {
        if ($c['ts'] >= $corte && alertasVagaCombina($c['vaga'], $prefs['cursos'])) $novas[] = $c;
    }
    usort($novas, fn($a, $b) => $b['ts'] <=> $a['ts']);

    // favoritos que encerram nos proximos 7 dias
    $encerrando = [];
    foreach ((array) usuarioLerDados((int) $u['id'], 'userFavoritos') as $f) {
        $orig = $f['origem'] ?? 'faculdade';
        $vaga = $ctx['mapa'][$orig][(int) ($f['jobId'] ?? 0)] ?? null;
        if (!$vaga) continue;
        $vig = $orig === 'egresso' ? egressoVagaStatusEfetivo($vaga) === 'aprovada' : vagaVigente($vaga);
        $d = vagaDiasRestantes($vaga);
        if ($vig && $d !== null && $d <= 7) $encerrando[] = ['origem' => $orig, 'vaga' => $vaga];
    }

    // melhores compatibilidades (so se o aluno tem curriculo)
    $perfil = matchPerfilAluno($u);
    $topo = [];
    if ($perfil['temCurriculo']) {
        $topo = array_slice(array_filter(
            matchVagasPara($perfil, $ctx['vigentes'], 50, 3),
            fn($m) => true
        ), 0, 3);
    }

    if (!$permitirVazio && empty($novas) && empty($encerrando)) return null;

    $nome = emH($u['nome'] ?? 'aluno(a)');
    $corpo = '<p>Olá, <strong>' . $nome . '</strong>! Este é o seu resumo da semana.</p>';

    if (!empty($novas)) {
        $corpo .= '<h3 style="margin:18px 0 4px;font-size:15px">Vagas novas para os seus cursos (' . count($novas) . ')</h3>';
        foreach (array_slice($novas, 0, 8) as $c) $corpo .= alertasCardVaga($c['origem'], $c['vaga']);
        if (count($novas) > 8) $corpo .= '<p style="color:#64748b;font-size:13px">e mais ' . (count($novas) - 8) . ' vaga(s) no portal.</p>';
    } else {
        $corpo .= '<p style="color:#64748b">Nenhuma vaga nova para os seus cursos nesta semana.</p>';
    }

    if (!empty($encerrando)) {
        $corpo .= '<h3 style="margin:18px 0 4px;font-size:15px">Suas vagas salvas que estão encerrando</h3>';
        foreach ($encerrando as $c) $corpo .= alertasCardVaga($c['origem'], $c['vaga']);
    }

    if (!empty($topo)) {
        $corpo .= '<h3 style="margin:18px 0 4px;font-size:15px">Vagas com mais a ver com o seu currículo</h3>';
        foreach ($topo as $m) $corpo .= alertasCardVaga($m['origem'], $m['vaga'], $m['score']);
    }

    $corpo .= '<p style="color:#94a3b8;font-size:12px;margin-top:18px">Para mudar os cursos ou parar de receber, acesse '
            . '<a href="' . emH(appBaseUrl() . '/profile.php#alertas') . '">Meus Alertas</a> no portal.</p>';

    return [
        'assunto' => 'Seu resumo semanal de vagas' . (count($novas) ? ' (' . count($novas) . ' nova' . (count($novas) > 1 ? 's' : '') . ')' : ''),
        'titulo'  => 'Resumo semanal de vagas',
        'corpo'   => $corpo,
        'novas'   => count($novas),
    ];
}

function alertasEmailValido(array $u): bool {
    return filter_var(trim((string) ($u['email'] ?? '')), FILTER_VALIDATE_EMAIL) !== false;
}

/** Coloca na fila o resumo semanal de todos os alunos que ativaram. */
function alertasEnfileirarResumoSemanal(string $sufixo = ''): array {
    $ctx = alertasContextoVagas();
    $itens = [];
    $optIn = 0;
    foreach (loadData('usuarios') as $u) {
        if (!alertasEhAluno($u)) continue;
        $p = alertasPrefs($u);
        if (empty($p['resumoSemanal']) || !alertasEmailValido($u)) continue;
        $optIn++;
        $r = alertasMontarResumo($u, $ctx, false);
        if (!$r) continue;
        $itens[] = [
            'chave'   => 'resumo:' . date('o-W') . ':' . $u['id'] . $sufixo,
            'to'      => trim($u['email']),
            'nome'    => $u['nome'] ?? '',
            'assunto' => $r['assunto'],
            'titulo'  => $r['titulo'],
            'corpo'   => $r['corpo'],
            'cta'     => 'Ver todas as vagas',
            'ctaUrl'  => appBaseUrl() . '/jobs.php',
        ];
    }
    return ['alunosAtivos' => $optIn, 'enfileirados' => alertasEnfileirarLote($itens)];
}

/** Aviso imediato: saiu uma vaga nova (chamado ao criar/aprovar vaga). */
function alertasEnfileirarVagaNova(array $vaga, string $origem): int {
    try {
        $vigente = $origem === 'egresso' ? egressoVagaStatusEfetivo($vaga) === 'aprovada' : vagaVigente($vaga);
        if (!$vigente) return 0;
        $itens = [];
        foreach (loadData('usuarios') as $u) {
            if (!alertasEhAluno($u)) continue;
            $p = alertasPrefs($u);
            if (empty($p['vagaNova']) || !alertasEmailValido($u)) continue;
            if (!alertasVagaCombina($vaga, $p['cursos'])) continue;

            $compat = null;
            $perfil = matchPerfilAluno($u);
            if ($perfil['temCurriculo']) {
                $r = matchPontuar($perfil, $vaga);
                if ($r['score'] > 0) $compat = $r['score'];
            }
            $itens[] = [
                'chave'   => 'vaga:' . $origem . ':' . ($vaga['id'] ?? 0) . ':' . $u['id'],
                'to'      => trim($u['email']),
                'nome'    => $u['nome'] ?? '',
                'assunto' => 'Nova vaga para o seu curso: ' . ($vaga['title'] ?? ''),
                'titulo'  => 'Saiu uma vaga para o seu curso',
                'corpo'   => '<p>Olá, <strong>' . emH($u['nome'] ?? 'aluno(a)') . '</strong>! Uma vaga nova combina com os cursos que você acompanha:</p>'
                           . alertasCardVaga($origem, $vaga, $compat)
                           . '<p style="color:#94a3b8;font-size:12px">Você recebe este aviso porque ativou os alertas. Para mudar, acesse '
                           . '<a href="' . emH(appBaseUrl() . '/profile.php#alertas') . '">Meus Alertas</a>.</p>',
                'cta'     => 'Ver a vaga',
                'ctaUrl'  => alertasLinkVaga($origem),
            ];
        }
        return alertasEnfileirarLote($itens);
    } catch (Throwable $e) {
        error_log('alertasEnfileirarVagaNova: ' . $e->getMessage());
        return 0;
    }
}

/** Envia o resumo de teste direto para o proprio aluno (sem fila). */
function alertasEnviarResumoTeste(array $u): bool {
    if (!alertasEmailValido($u)) return false;
    $r = alertasMontarResumo($u, alertasContextoVagas(), true);
    return notificarEmail(trim($u['email']), $u['nome'] ?? '', '[Teste] ' . $r['assunto'], $r['titulo'], $r['corpo'], 'Ver todas as vagas', appBaseUrl() . '/jobs.php');
}

/* ------------------------------------------------------------------ */
/* Manutencao: gera o resumo da semana (se devido) e esvazia a fila    */
/* ------------------------------------------------------------------ */

/** O resumo sai uma vez por semana ISO, a partir de segunda-feira 08:00 */
function alertasResumoDevido(array $estado): bool {
    if (($estado['semanaResumo'] ?? '') === date('o-W')) return false;
    return (int) date('N') > 1 || (int) date('G') >= 8;
}

function alertasManutencao(bool $forcarResumo = false, int $maxEnvios = 5, int $segundos = 15): array {
    $estado = alertasEstado();
    $resumo = null;
    if ($forcarResumo || alertasResumoDevido($estado)) {
        $estado['semanaResumo']  = date('o-W');
        $estado['ultimoResumoTs'] = time();
        alertasSalvarEstado($estado);   // grava ANTES para dois acessos simultaneos nao gerarem em duplicidade
        $resumo = alertasEnfileirarResumoSemanal($forcarResumo ? ':f' . time() : '');
        $estado['ultimoResumoTotal'] = $resumo['enfileirados'];
        alertasSalvarEstado($estado);
    }
    $envio = alertasProcessarFila($maxEnvios, $segundos);
    if (($envio['enviados'] ?? 0) > 0) {
        $estado = alertasEstado();
        $estado['ultimoEnvioTs'] = time();
        alertasSalvarEstado($estado);
    }
    return ['resumoGerado' => $resumo, 'envio' => $envio];
}

/** Registrado pelo header.php: roda DEPOIS que a pagina ja foi entregue ao usuario */
function alertasAgendarTick(): void {
    static $registrado = false;
    if ($registrado) return;
    $registrado = true;
    register_shutdown_function('alertasTick');
}

function alertasTick(): void {
    try {
        $estado   = alertasEstado();
        $temTrabalho = alertasResumoDevido($estado) || alertasFilaTamanho() > 0;
        if (!$temTrabalho) return;
        if (time() - (int) ($estado['ultimoTick'] ?? 0) < 60) return;

        $estado['ultimoTick'] = time();
        alertasSalvarEstado($estado);

        ignore_user_abort(true);
        @set_time_limit(60);
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        } else {
            while (ob_get_level() > 0) { @ob_end_flush(); }
            @flush();
        }
        alertasManutencao(false, 4, 15);
    } catch (Throwable $e) {
        error_log('alertasTick: ' . $e->getMessage());
    }
}
