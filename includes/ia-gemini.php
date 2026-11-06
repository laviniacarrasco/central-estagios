<?php
/**
 * =============================================================================
 * IA DO CURRÍCULO (Google Gemini)
 * -----------------------------------------------------------------------------
 * Duas funções para o aluno:
 *   1) Avaliar o currículo: nota de 0 a 100, pontos fortes, o que melhorar e
 *      a leitura por ATS (robôs / IAs de triagem de estágio).
 *   2) Vagas que combinam: compara o currículo com as vagas abertas e devolve
 *      as que mais combinam, com a nota e o motivo.
 *
 * Privacidade (LGPD): o currículo vai para o Google SEM nome, e-mail, telefone
 * e foto (iaCurriculoAnonimo). E-mails, telefones e CPFs escritos no meio do
 * texto também são apagados antes do envio. Na primeira vez, o aluno vê um aviso
 * e precisa concordar (guardado em userProfile -> iaConsentimento).
 *
 * Economia de cota:
 *   - O resultado fica guardado (users/<id>/iaCurriculo e users/<id>/iaVagas).
 *     A IA só é chamada de novo quando o currículo (ou a lista de vagas) muda.
 *   - Limite por aluno por dia: IA_LIMITE_DIA (padrão 10 chamadas).
 *
 * Se o Gemini não responder (sem chave, fora do ar, limite), as telas usam a
 * comparação por regras de includes/matching.php. Nada quebra.
 *
 * Variáveis: GEMINI_API_KEY (obrigatória), GEMINI_MODEL (padrão gemini-3.8-flash),
 *            IA_LIMITE_DIA (padrão 10).
 * Quem chama pela tela: ia-curriculo.php (responde em JSON).
 * =============================================================================
 */
require_once __DIR__ . '/melhorias.php';
require_once __DIR__ . '/matching.php';
require_once __DIR__ . '/vagas.php';
require_once __DIR__ . '/egressos.php';

function iaEnv(string $k, string $padrao = ''): string {
    $v = $_ENV[$k] ?? getenv($k);
    return ($v === false || $v === null || trim((string) $v) === '') ? $padrao : trim((string) $v);
}
function iaConfigurada(): bool { return iaEnv('GEMINI_API_KEY') !== ''; }
/** Modelos desligados pelo Google (respondem 404). Se o .env ainda tiver um deles, é ignorado. */
const IA_MODELOS_DESLIGADOS = ['gemini-2.0-flash', 'gemini-2.0-flash-001', 'gemini-2.0-flash-lite', 'gemini-2.0-flash-lite-001',
                               'gemini-1.5-flash', 'gemini-1.5-pro', 'gemini-pro'];
function iaModelo(): string {
    $m = iaEnv('GEMINI_MODEL', 'gemini-3.8-flash');
    return in_array($m, IA_MODELOS_DESLIGADOS, true) ? 'gemini-3.8-flash' : $m;
}

/**
 * Pergunta ao Google quais modelos "flash" a SUA chave pode usar (guarda por 1 dia
 * num arquivo temporário). Usado quando todos os modelos da lista respondem 404.
 */
function iaModelosDisponiveis(): array {
    $arq = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'fsa_gemini_modelos_' . md5(iaEnv('GEMINI_API_KEY')) . '.json';
    if (is_file($arq) && filemtime($arq) > time() - 86400) {
        $c = json_decode((string) @file_get_contents($arq), true);
        if (is_array($c) && $c) return $c;
    }
    $chave = iaEnv('GEMINI_API_KEY');
    if ($chave === '' || !function_exists('curl_init')) return [];
    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models?pageSize=200&key=' . rawurlencode($chave));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $cert = __DIR__ . '/cacert.pem';
    if (is_file($cert)) curl_setopt($ch, CURLOPT_CAINFO, $cert);
    $j = json_decode((string) curl_exec($ch), true);
    curl_close($ch);
    $lista = [];
    foreach ((array) ($j['models'] ?? []) as $m) {
        $nome = preg_replace('#^models/#', '', (string) ($m['name'] ?? ''));
        if (!in_array('generateContent', (array) ($m['supportedGenerationMethods'] ?? []), true)) continue;
        if (!preg_match('/^gemini-[0-9.]+-flash(-lite)?$/', $nome)) continue;   // só flash estável (sem preview, imagem, tts)
        $lista[] = $nome;
    }
    rsort($lista, SORT_NATURAL);   // mais novo primeiro
    if ($lista) @file_put_contents($arq, json_encode($lista));
    return $lista;
}

/** O aluno pode usar a IA? (recurso do perfil + chave configurada) */
function iaDisponivel(): bool {
    return perfilPode('ia_curriculo') && iaConfigurada();
}

/** v33: pode usar SÓ a avaliação do currículo? (aluno comum ou aluno restrito) */
function iaCvDisponivel(): bool {
    return (perfilPode('ia_curriculo') || perfilPode('ia_cv')) && iaConfigurada();
}

/** O aluno já concordou em enviar o currículo (sem dados pessoais) para a IA? */
function iaConsentiu(int $userId): bool {
    $p = dadosLer('users/' . $userId . '/userProfile');
    return !empty($p['iaConsentimento']);
}
function iaRegistrarConsentimento(int $userId): void {
    dadosOperar('users/' . $userId . '/userProfile', function (array &$p) {
        $p['iaConsentimento'] = date('Y-m-d H:i:s');
    });
}

/* ===========================================================================
 * CHAMADA AO GEMINI
 * ======================================================================== */

/** Envia o prompt e espera um JSON de volta. Devolve ['ok', 'dados', 'erro']. */
function iaGeminiJson(string $prompt, int $timeout = 180): array {
    // Tenta o modelo configurado e, se ele estiver sobrecarregado (503/500/502/504) ou
    // não existir mais (404), tenta de novo e depois os modelos reserva.
    $modelos = array_values(array_unique(array_filter(array_merge(
        [iaModelo()],
        array_map('trim', explode(',', iaEnv('GEMINI_MODELOS_RESERVA', 'gemini-3.8-flash,gemini-3.1-flash-lite,gemini-3.6-flash')))
    ), fn($m) => $m !== '' && !in_array($m, IA_MODELOS_DESLIGADOS, true))));
    $ultimo = ['ok' => false, 'dados' => [], 'erro' => 'A IA não respondeu.'];
    $buscouLista = false;
    for ($iM = 0; $iM < count($modelos); $iM++) {
        $modelo = $modelos[$iM];
        for ($t = 0; $t < ($iM === 0 ? 2 : 1); $t++) {
            $r = iaGeminiJsonModelo($prompt, $timeout, $modelo);
            if ($r['ok']) { $GLOBALS['iaUsoUsuario'] = 0; return $r; }
            if (!in_array($r['http'] ?? 0, [404, 408, 500, 502, 503, 504], true)) {
                if (!empty($GLOBALS['iaUsoUsuario'])) { iaDevolverUso((int) $GLOBALS['iaUsoUsuario']); $GLOBALS['iaUsoUsuario'] = 0; }
                return $r;
            }
            $ultimo = $r;
            if (in_array($r['http'] ?? 0, [404, 408], true)) break;   // não existe ou demorou demais: vai para o próximo modelo
            if ($iM >= 2) break;            // modelo não existe: não adianta repetir
            if ($t === 0 && $iM === 0) sleep(2);
        }
        // Acabou a lista e só deu 404: pergunta ao Google quais modelos a chave pode usar
        if ($iM === count($modelos) - 1 && !$buscouLista && ($ultimo['http'] ?? 0) === 404) {
            $buscouLista = true;
            foreach (iaModelosDisponiveis() as $novo) if (!in_array($novo, $modelos, true)) $modelos[] = $novo;
        }
    }
    if (($ultimo['http'] ?? 0) === 404) {
        $ultimo['erro'] = 'Nenhum modelo de IA está disponível para a sua chave do Google (erro 404). '
            . 'Confira GEMINI_MODEL no .env e no Render (use gemini-3.8-flash) e se a GEMINI_API_KEY está ativa.';
    }
    if (!empty($GLOBALS['iaUsoUsuario'])) { iaDevolverUso((int) $GLOBALS['iaUsoUsuario']); $GLOBALS['iaUsoUsuario'] = 0; }
    if (($ultimo['http'] ?? 0) === 408) {
        $ultimo['erro'] = 'A IA demorou demais para responder. Tente de novo em instantes.';
    }
    if (in_array($ultimo['http'] ?? 0, [500, 502, 503, 504], true)) {
        $ultimo['erro'] = 'A IA do Google está sobrecarregada agora (erro ' . $ultimo['http'] . '). Isso é do lado do Google; tente de novo em alguns minutos.';
    }
    return $ultimo;
}

function iaGeminiJsonModelo(string $prompt, int $timeout, string $modelo): array {
    $chave = iaEnv('GEMINI_API_KEY');
    if ($chave === '') return ['ok' => false, 'dados' => [], 'erro' => 'A IA não está configurada (GEMINI_API_KEY).'];
    if (!function_exists('curl_init')) return ['ok' => false, 'dados' => [], 'erro' => 'A extensão curl do PHP não está ativa.'];

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($modelo) . ':generateContent?key=' . rawurlencode($chave);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_POSTFIELDS     => json_encode([
            'contents'         => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => iaConfigGeracao($modelo),
        ], JSON_UNESCAPED_UNICODE),
    ]);
    $cert = __DIR__ . '/cacert.pem';                       // Windows: certificados SSL
    if (is_file($cert)) curl_setopt($ch, CURLOPT_CAINFO, $cert);

    $res  = curl_exec($ch);
    $erro = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false) {
        $timeoutCurl = stripos($erro, 'timed out') !== false;
        error_log('Gemini ' . $modelo . ' falhou: ' . $erro);
        return ['ok' => false, 'http' => $timeoutCurl ? 408 : 0, 'dados' => [],
                'erro' => $timeoutCurl ? 'A IA demorou demais para responder.' : 'Não foi possível falar com a IA: ' . $erro];
    }
    if ($code === 429)   return ['ok' => false, 'http' => 429, 'dados' => [], 'erro' => 'A IA atingiu o limite de uso. Tente de novo em alguns minutos.'];
    if ($code < 200 || $code >= 300) {
        error_log('Gemini ' . $modelo . ' HTTP ' . $code . ': ' . mb_substr((string) $res, 0, 300));
        return ['ok' => false, 'http' => $code, 'dados' => [], 'erro' => 'A IA respondeu com erro (HTTP ' . $code . ').'];
    }
    $j   = json_decode((string) $res, true);
    $txt = '';   // junta as partes de texto da resposta (ignora as partes de "pensamento")
    foreach ((array) ($j['candidates'][0]['content']['parts'] ?? []) as $parte) {
        if (empty($parte['thought']) && isset($parte['text'])) $txt .= (string) $parte['text'];
    }
    $txt = trim(preg_replace('/^```(?:json)?|```$/m', '', $txt));
    $d   = json_decode($txt, true);
    if (!is_array($d)) return ['ok' => false, 'dados' => [], 'erro' => 'A IA não devolveu uma resposta válida. Tente de novo.'];
    return ['ok' => true, 'dados' => $d, 'erro' => ''];
}

/**
 * Configuração da resposta.
 * Raciocínio: ALTO por padrão (a melhor qualidade de análise). Demora mais, por isso
 * o tempo de espera é longo. GEMINI_THINKING aceita: high (padrão), medium, low ou
 * padrao (deixa o Google decidir).
 */
function iaConfigGeracao(string $modelo): array {
    $cfg = ['responseMimeType' => 'application/json', 'temperature' => 0.3];
    if (preg_match('/^gemini-3/', $modelo)) {
        unset($cfg['temperature']);   // Gemini 3: o Google recomenda manter a temperatura padrão
        $nivel = strtolower(iaEnv('GEMINI_THINKING', 'high'));
        if (in_array($nivel, ['high', 'medium', 'low'], true)) $cfg['thinkingConfig'] = ['thinkingLevel' => $nivel];
    }
    return $cfg;
}

/** Limite diário por aluno. Devolve true se ainda pode chamar (e já conta a chamada). */
function iaContarUso(int $userId): bool {
    $limite = max(1, (int) iaEnv('IA_LIMITE_DIA', '10'));
    return (bool) dadosOperar('users/' . $userId . '/iaUso', function (array &$u) use ($limite) {
        $hoje = date('Y-m-d');
        if (($u['dia'] ?? '') !== $hoje) $u = ['dia' => $hoje, 'n' => 0];
        if ($u['n'] >= $limite) return false;
        $u['n']++;
        return true;
    });
}

/** Devolve a chamada ao limite do dia (usado quando a IA falhou: erro do Google não gasta a cota do aluno). */
function iaDevolverUso(int $userId): void {
    dadosOperar('users/' . $userId . '/iaUso', function (array &$u) {
        if (($u['dia'] ?? '') === date('Y-m-d') && ($u['n'] ?? 0) > 0) $u['n']--;
    });
}

function iaMsgLimite(): string {
    return 'Você já fez ' . max(1, (int) iaEnv('IA_LIMITE_DIA', '10')) . ' análises com IA hoje (o limite diário). Tente de novo amanhã.';
}

/* ===========================================================================
 * DATAS DA FORMAÇÃO (usadas pela IA, pela tela e pelo PDF)
 * Cursando = marcou "cursando" OU a data de conclusão ainda não chegou.
 * Assim a previsão de conclusão aparece certa mesmo se o aluno esqueceu de marcar.
 * ======================================================================== */
if (!function_exists('cvFormacaoDatas')) {
    function cvFormacaoDatas(array $f): array {
        $mmaaaa = fn($ymd) => preg_match('/^(\d{4})-(\d{2})/', (string) $ymd, $m) ? $m[2] . '/' . $m[1] : '';
        $fimRaw = (string) ($f['fim'] ?? '');
        $cursando = ($f['atual'] ?? '0') === '1' || ($fimRaw !== '' && substr($fimRaw, 0, 7) >= date('Y-m'));
        $ini = $mmaaaa($f['inicio'] ?? ''); $fim = $mmaaaa($fimRaw);
        $partes = [];
        if ($ini !== '') $partes[] = 'Início: ' . $ini;
        if ($cursando) $partes[] = 'Previsão de conclusão: ' . ($fim !== '' ? $fim : 'não informada') . ' (cursando)';
        elseif ($fim !== '') $partes[] = 'Conclusão: ' . $fim;
        return ['cursando' => $cursando, 'inicio' => $ini, 'fim' => $fim, 'texto' => implode(' | ', $partes)];
    }
}

/* ===========================================================================
 * CURRÍCULO SEM DADOS PESSOAIS
 * ======================================================================== */

function iaLimparTexto(string $t, int $max = 1200): string {
    $t = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[e-mail removido]', $t);
    $t = preg_replace('/\d{3}\.?\d{3}\.?\d{3}\-?\d{2}/', '[documento removido]', $t);
    $t = preg_replace('/(\+?55\s?)?\(?\d{2}\)?\s?9?\d{4}[\s\-]?\d{4}/', '[telefone removido]', $t);
    return mb_substr(trim((string) $t), 0, $max);
}

/** O que vai para a IA: sem nome, e-mail, telefone, foto */
function iaCurriculoAnonimo(array $cv, string $curso = ''): array {
    $exp = [];
    foreach ((array) ($cv['experiencias'] ?? []) as $e) {
        if (!is_array($e)) continue;
        $exp[] = [
            'cargo'     => iaLimparTexto((string) ($e['cargo'] ?? ''), 120),
            'empresa'   => iaLimparTexto((string) ($e['empresa'] ?? ''), 120),
            'periodo'   => trim(($e['inicio'] ?? '') . ' a ' . (($e['atual'] ?? '0') === '1' ? 'atual' : ($e['fim'] ?? ''))),
            'descricao' => iaLimparTexto((string) ($e['descricao'] ?? ''), 800),
        ];
    }
    $form = [];
    foreach ((array) ($cv['formacoes'] ?? []) as $f) {
        if (!is_array($f)) continue;
        $d = cvFormacaoDatas($f);
        $form[] = [
            'curso'       => iaLimparTexto((string) ($f['curso'] ?? ''), 150),
            'instituicao' => iaLimparTexto((string) ($f['instituicao'] ?? ''), 150),
            'tipo'        => (string) ($f['tipo'] ?? ''),
            'situacao'    => $d['cursando'] ? 'cursando' : 'concluído',
            'dataInicio'  => $d['inicio'] ?: 'não informada',                       // mm/aaaa
            ($d['cursando'] ? 'previsaoConclusao' : 'dataConclusao') => $d['fim'] ?: 'não informada',
            'periodo'     => $d['texto'],                                           // ex.: "Início: 03/2024 | Previsão de conclusão: 12/2027"
        ];
    }
    $idi = [];
    foreach ((array) ($cv['idiomas'] ?? []) as $i) {
        if (is_array($i) && !empty($i['idioma'])) $idi[] = trim($i['idioma'] . ' (' . ($i['nivel'] ?? '') . ')');
    }
    return [
        'cursoNaFSA'   => $curso,
        'cidade'       => iaLimparTexto((string) ($cv['cidade'] ?? ''), 80),
        'resumo'       => iaLimparTexto((string) ($cv['resumo'] ?? ''), 1500),
        'habilidades'  => array_slice(array_map(fn($h) => iaLimparTexto((string) $h, 60), array_values((array) ($cv['habilidades'] ?? []))), 0, 40),
        'experiencias' => array_slice($exp, 0, 10),
        'formacoes'    => array_slice($form, 0, 6),
        'idiomas'      => $idi,
    ];
}

/* ===========================================================================
 * 1) AVALIAR O CURRÍCULO
 * ======================================================================== */

/** Avaliação guardada (null se não houver). 'atual' = false se o currículo mudou depois. */
function iaAvaliacaoGuardada(int $userId, array $cv, string $curso): ?array {
    $g = dadosLer('users/' . $userId . '/iaCurriculo');
    if (empty($g['resultado'])) return null;
    $g['atual'] = ($g['hash'] ?? '') === md5(json_encode(iaCurriculoAnonimo($cv, $curso)));
    return $g;
}

function iaAvaliarCurriculo(int $userId, array $cv, string $curso): array {
    $anon = iaCurriculoAnonimo($cv, $curso);
    if (!$anon['habilidades'] && !$anon['experiencias'] && mb_strlen($anon['resumo']) < 30) {
        return ['ok' => false, 'dados' => [], 'erro' => 'Preencha o currículo (resumo, habilidades ou experiências) antes de pedir a avaliação.'];
    }
    $hash = md5(json_encode($anon));
    $g = dadosLer('users/' . $userId . '/iaCurriculo');
    if (($g['hash'] ?? '') === $hash && !empty($g['resultado'])) return ['ok' => true, 'dados' => $g + ['atual' => true], 'erro' => ''];

    if (!iaContarUso($userId)) return ['ok' => false, 'dados' => [], 'erro' => iaMsgLimite()];
    $GLOBALS['iaUsoUsuario'] = $userId;   // se a IA falhar, a chamada é devolvida (iaGeminiJson)

    $prompt = "Você é um orientador de carreira da Fundação Santo André (FSA), uma faculdade em Santo André, SP. "
        . "Avalie o currículo de um(a) estudante que busca ESTÁGIO. Seja honesto, gentil e prático, em português do Brasil, "
        . "com frases curtas. Não invente informações que não estão no currículo.\n"
        . "Hoje é " . date('m/Y') . ". Datas vêm como mm/aaaa. Em cada formação: 'dataInicio' é o início do curso; "
        . "'previsaoConclusao' (quando cursando) ou 'dataConclusao' (quando concluído) é o fim; 'periodo' resume os dois. "
        . "Se essas datas estiverem preenchidas, considere que o currículo JÁ TEM início e previsão/conclusão do curso e NÃO diga que faltam. "
        . "NUNCA peça uma informação que já está no currículo. Itens opcionais (semestre atual, previsão de conclusão, LinkedIn, "
        . "foto, separar hard e soft skills) entram no máximo como LEMBRETE curto, começando com 'Se quiser,', e NÃO reduzem a nota.\n\n"
        . "Responda SOMENTE um JSON neste formato:\n"
        . '{"nota": número de 0 a 100, "resumo": "2 ou 3 frases com a impressão geral", '
        . '"pontosFortes": ["até 4 itens curtos"], '
        . '"melhorar": [{"secao": "Resumo | Experiências | Formação | Habilidades | Idiomas | Geral", "dica": "o que fazer, de forma concreta"}] (até 6 itens, do mais importante para o menos), '
        . '"palavrasChave": ["até 8 palavras-chave da área do curso que fariam falta no currículo"], '
        . '"ats": {"nota": número de 0 a 100 de quão bem um sistema ATS / IA de triagem de estágio entenderia e aprovaria este conteúdo, '
        . '"dicas": ["até 4 ajustes concretos para passar melhor nesses filtros (palavras-chave da área, verbos de ação, resultados, datas, termos padrão)"]}}' . "\n\n"
        . "O PDF que a Central gera já usa uma coluna, texto selecionável e títulos padrão; avalie o CONTEÚDO "
        . "(clareza, palavras-chave, verbos de ação, resultados, datas e coerência com o curso).\n\n"
        . "Currículo (sem dados pessoais):\n" . json_encode($anon, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $r = iaGeminiJson($prompt);
    if (!$r['ok']) return $r;
    $d = $r['dados'];
    $lista = fn($x, int $n, int $max) => array_slice(array_values(array_filter(array_map(fn($y) => mb_substr(trim((string) $y), 0, $max), (array) $x))), 0, $n);
    $res = [
        'nota'          => max(0, min(100, (int) ($d['nota'] ?? 0))),
        'resumo'        => mb_substr(trim((string) ($d['resumo'] ?? '')), 0, 600),
        'pontosFortes'  => $lista($d['pontosFortes'] ?? [], 4, 200),
        'melhorar'      => [],
        'palavrasChave' => $lista($d['palavrasChave'] ?? [], 8, 40),
        'ats'           => [
            'nota'  => max(0, min(100, (int) ($d['ats']['nota'] ?? 0))),
            'dicas' => $lista($d['ats']['dicas'] ?? [], 4, 250),
        ],
    ];
    foreach (array_slice((array) ($d['melhorar'] ?? []), 0, 6) as $m) {
        if (!is_array($m) || trim((string) ($m['dica'] ?? '')) === '') continue;
        $res['melhorar'][] = ['secao' => mb_substr(trim((string) ($m['secao'] ?? 'Geral')), 0, 30), 'dica' => mb_substr(trim((string) $m['dica']), 0, 300)];
    }
    $g = ['hash' => $hash, 'quando' => date('Y-m-d H:i:s'), 'modelo' => iaModelo(), 'resultado' => $res];
    dadosGravar('users/' . $userId . '/iaCurriculo', $g);
    return ['ok' => true, 'dados' => $g + ['atual' => true], 'erro' => ''];
}

/* ===========================================================================
 * 2) VAGAS QUE COMBINAM
 * ======================================================================== */

/** Vagas abertas que o aluno pode ver: [['origem','vaga'], ...] */
function iaCandidatas(): array {
    $c = [];
    foreach (array_values(loadData('platform_jobs')) as $j) {
        if (vagaVigente($j)) $c[] = ['origem' => 'faculdade', 'vaga' => $j];
    }
    if (perfilPode('vagas_egresso')) {
        foreach (egressoVagas() as $j) {
            if (egressoVagaStatusEfetivo($j) === 'aprovada') $c[] = ['origem' => 'egresso', 'vaga' => $j];
        }
    }
    return $c;
}

function iaIdVaga(array $c): string { return ($c['origem'] === 'egresso' ? 'e' : 'f') . ':' . (int) ($c['vaga']['id'] ?? 0); }

/** Chave de cache: currículo + lista de vagas */
function iaHashVagas(array $cv, string $curso, array $candidatas): string {
    return md5(json_encode(iaCurriculoAnonimo($cv, $curso)) . '|' . implode(',', array_map('iaIdVaga', $candidatas)));
}

/**
 * Lista final para a tela. Usa a análise da IA guardada (se existir); senão,
 * a comparação por regras. NUNCA chama a IA aqui (a tela não fica lenta).
 * Devolve ['fonte' => 'ia'|'regras', 'quando', 'desatualizada' => bool, 'lista' => [...]]
 */
function iaVagasParaTela(array $usuario, int $limite = 20): array {
    $userId = (int) ($usuario['id'] ?? 0);
    $cv = dadosLer('users/' . $userId . '/userCurriculum');
    $curso = (string) ($usuario['curso'] ?? '');
    $cands = iaCandidatas();
    $porId = [];
    foreach ($cands as $c) $porId[iaIdVaga($c)] = $c;

    $g = perfilPode('ia_curriculo') ? dadosLer('users/' . $userId . '/iaVagas') : [];
    if (!empty($g['lista'])) {
        $lista = [];
        foreach ($g['lista'] as $item) {
            if (!isset($porId[$item['id']])) continue;          // a vaga saiu do ar
            $lista[] = $porId[$item['id']] + ['score' => (int) $item['nota'], 'motivos' => [(string) $item['motivo']], 'ia' => true];
        }
        if ($lista) {
            $vale = (time() - (strtotime((string) ($g['quando'] ?? '')) ?: 0)) < 86400;
            return ['fonte' => 'ia', 'quando' => (string) $g['quando'],
                    'desatualizada' => !$vale || ($g['hash'] ?? '') !== iaHashVagas($cv, $curso, $cands),
                    'lista' => array_slice($lista, 0, $limite)];
        }
    }
    $perfil = matchPerfilAluno($usuario);
    if (!perfilPode('ia_perfil')) $perfil['competencias'] = [];
    return ['fonte' => 'regras', 'quando' => '', 'desatualizada' => false, 'lista' => matchVagasPara($perfil, $cands, 25, $limite)];
}

/** Pede à IA a análise das vagas (botão "Analisar com IA") */
function iaAnalisarVagas(array $usuario): array {
    $userId = (int) ($usuario['id'] ?? 0);
    $cv = dadosLer('users/' . $userId . '/userCurriculum');
    $curso = (string) ($usuario['curso'] ?? '');
    $cands = iaCandidatas();
    if (!$cands) return ['ok' => false, 'dados' => [], 'erro' => 'Não há vagas abertas no momento.'];
    $anon = iaCurriculoAnonimo($cv, $curso);
    if (!$anon['habilidades'] && !$anon['experiencias'] && mb_strlen($anon['resumo']) < 30) {
        return ['ok' => false, 'dados' => [], 'erro' => 'Preencha o currículo (resumo, habilidades ou experiências) antes de pedir a análise.'];
    }
    $hash = iaHashVagas($cv, $curso, $cands);
    $g = dadosLer('users/' . $userId . '/iaVagas');
    if (($g['hash'] ?? '') === $hash && (time() - (strtotime((string) ($g['quando'] ?? '')) ?: 0)) < 86400) {
        return ['ok' => true, 'dados' => $g, 'erro' => ''];
    }
    if (!iaContarUso($userId)) return ['ok' => false, 'dados' => [], 'erro' => iaMsgLimite()];
    $GLOBALS['iaUsoUsuario'] = $userId;   // se a IA falhar, a chamada é devolvida (iaGeminiJson)

    // Até 40 vagas: as que as regras acham mais próximas vão primeiro
    $ordem = matchVagasPara(matchPerfilAluno($usuario), $cands, 0, 40);
    $ids = array_map('iaIdVaga', $ordem);
    foreach ($cands as $c) {
        if (count($ids) >= 40) break;
        if (!in_array(iaIdVaga($c), $ids, true)) { $ids[] = iaIdVaga($c); $ordem[] = $c; }
    }
    $vagasTxt = [];
    foreach ($ordem as $c) {
        $v = $c['vaga'];
        $vagasTxt[] = [
            'id'      => iaIdVaga($c),
            'titulo'  => mb_substr((string) ($v['title'] ?? ''), 0, 120),
            'empresa' => mb_substr((string) ($v['company'] ?? ''), 0, 80),
            'cursos'  => trim((string) ($v['courses'] ?? '')) ?: 'todos',
            'local'   => mb_substr((string) ($v['location'] ?? ''), 0, 60),
            'texto'   => mb_substr(trim(($v['description'] ?? '') . ' ' . ($v['requirements'] ?? '')), 0, 600),
        ];
    }
    $prompt = "Você ajuda estudantes da FSA a encontrar estágio. Compare o currículo com cada vaga e dê uma nota de 0 a 100 "
        . "de quanto a vaga combina (curso, habilidades, experiências, idiomas e interesses). Seja realista: "
        . "vaga de outro curso sem relação deve ter nota baixa. O motivo deve ter UMA frase curta, em português do Brasil, "
        . "falando diretamente com o estudante (\"Você ...\").\n\n"
        . 'Responda SOMENTE um JSON: {"vagas": [{"id": "id da vaga", "nota": número, "motivo": "frase"}]} com TODAS as vagas.' . "\n\n"
        . "Currículo (sem dados pessoais):\n" . json_encode($anon, JSON_UNESCAPED_UNICODE) . "\n\nVagas:\n" . json_encode($vagasTxt, JSON_UNESCAPED_UNICODE);

    $r = iaGeminiJson($prompt, 180);
    if (!$r['ok']) return $r;
    $validos = array_flip($ids);
    $lista = [];
    foreach ((array) ($r['dados']['vagas'] ?? []) as $it) {
        $id = (string) ($it['id'] ?? '');
        if (!isset($validos[$id])) continue;
        $lista[$id] = ['id' => $id, 'nota' => max(0, min(100, (int) ($it['nota'] ?? 0))), 'motivo' => mb_substr(trim((string) ($it['motivo'] ?? '')), 0, 250)];
    }
    if (!$lista) return ['ok' => false, 'dados' => [], 'erro' => 'A IA não devolveu as vagas. Tente de novo.'];
    $lista = array_values(array_filter($lista, fn($x) => $x['nota'] >= 30));
    usort($lista, fn($a, $b) => $b['nota'] <=> $a['nota']);
    $g = ['hash' => $hash, 'quando' => date('Y-m-d H:i:s'), 'modelo' => iaModelo(), 'lista' => $lista];
    dadosGravar('users/' . $userId . '/iaVagas', $g);
    return ['ok' => true, 'dados' => $g, 'erro' => ''];
}
