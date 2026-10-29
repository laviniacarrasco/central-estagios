<?php
/**
 * =============================================================================
 * VAGAS EXTERNAS (Gupy ou Adzuna) + FILTRO + CLASSIFICACAO POR CURSO
 * -----------------------------------------------------------------------------
 * Busca estagio/trainee em Sao Paulo e no ABC e coloca as vagas em
 * data/vagas_sugeridas.json (fila). O admin aprova em admin-sugeridas.php;
 * so entao a vaga aparece para os alunos.
 *
 * Variaveis de ambiente (Render > Environment, ou .env local):
 *   VAGAS_FONTE                      gupy (padrao, gratis, sem chave) | adzuna | gupy,adzuna | nenhuma
 *   ADZUNA_APP_ID, ADZUNA_APP_KEY    so se usar a fonte adzuna (plano pago)
 *   GEMINI_API_KEY                   opcional: a IA confere se e estagio e escolhe os cursos
 *   GEMINI_MODEL                     opcional (padrao gemini-2.0-flash; confira o nome vigente)
 * Sem a chave da IA, o filtro e os cursos saem por palavras-chave.
 * =============================================================================
 */

require_once __DIR__ . '/melhorias.php';

function vexEnv(string $k): string {
    $v = $_ENV[$k] ?? getenv($k);
    return ($v === false || $v === null) ? '' : trim((string) $v);
}

function vexAdzunaConfigurado(): bool { return vexEnv('ADZUNA_APP_ID') !== '' && vexEnv('ADZUNA_APP_KEY') !== ''; }
function vexIaConfigurada(): bool     { return vexEnv('GEMINI_API_KEY') !== ''; }

/** Fontes ativas, conforme VAGAS_FONTE (padrao: gupy). */
function vexFontes(): array {
    $v = strtolower(vexEnv('VAGAS_FONTE'));
    if ($v === '') $v = 'gupy';
    if ($v === 'nenhuma' || $v === 'off') return [];
    $ok = [];
    foreach (explode(',', $v) as $f) {
        $f = trim($f);
        if ($f === 'gupy') $ok[] = 'gupy';
        if ($f === 'adzuna' && vexAdzunaConfigurado()) $ok[] = 'adzuna';
    }
    return array_values(array_unique($ok));
}

function vexBuscaConfigurada(): bool { return !empty(vexFontes()); }

/** Texto de status para a tela do admin. */
function vexStatusFonte(): string {
    $f = vexFontes();
    if (empty($f)) {
        if (strpos(strtolower(vexEnv('VAGAS_FONTE')), 'adzuna') !== false && !vexAdzunaConfigurado()) {
            return 'A busca automática está desligada: VAGAS_FONTE pede o Adzuna, mas faltam ADZUNA_APP_ID e ADZUNA_APP_KEY.';
        }
        return 'A busca automática está desligada (VAGAS_FONTE=nenhuma). As vagas entram pelo Admin Vagas e pelos egressos.';
    }
    $nomes = array_map(fn($x) => $x === 'gupy' ? 'Gupy (busca pública, sem chave)' : 'Adzuna (chaves configuradas)', $f);
    return 'Fonte ativa: ' . implode(' + ', $nomes) . '.';
}

/** Nome exibido da fonte, usado no rodape da vaga aprovada. */
function vexCreditoFonte(string $fonte): string {
    $m = ['Adzuna' => 'Adzuna (adzuna.com.br)', 'Gupy' => 'Gupy (portal.gupy.io)'];
    return $m[$fonte] ?? '';
}

function vexHttpJson(string $url, ?array $corpo = null, int $timeout = 20): ?array {
    if (!function_exists('curl_init')) return null;
    $ch = curl_init($url);
    $op = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ];
    if ($corpo !== null) {
        $op[CURLOPT_POST]       = true;
        $op[CURLOPT_POSTFIELDS] = json_encode($corpo, JSON_UNESCAPED_UNICODE);
        $op[CURLOPT_HTTPHEADER] = ['Content-Type: application/json', 'Accept: application/json'];
    }
    curl_setopt_array($ch, $op);
    $res  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res === false || $code < 200 || $code >= 300) return null;
    $j = json_decode((string) $res, true);
    return is_array($j) ? $j : null;
}

/** Cidades aceitas (Sao Paulo capital + ABC) */
function vexCidadesAceitas(): array {
    return ['sao paulo', 'santo andre', 'sao bernardo do campo', 'sao caetano do sul', 'diadema', 'maua', 'ribeirao pires', 'rio grande da serra'];
}

function vexCidadeAceita(string $cidade): bool {
    return in_array(mhSemAcento(trim($cidade)), vexCidadesAceitas(), true);
}

/** Palavras-chave de cada curso (fallback quando nao ha IA) */
function vexCursosPorPalavras(string $texto): array {
    $t = ' ' . mhSemAcento($texto) . ' ';
    $mapa = [
        'Ciência de Dados e Inteligência Artificial' => ['dados', 'data ', 'analytics', 'machine learning', 'inteligencia artificial', 'python', 'sql', 'power bi'],
        'Análise e Desenvolvimento de Sistemas'      => ['desenvolv', 'software', 'sistemas', 'programa', 'front', 'back', 'java', 'php', ' ti ', 'tecnologia'],
        'Ciência da Computação'                      => ['desenvolv', 'software', 'programa', 'algoritm', 'java', 'python', 'tecnologia'],
        'Sistemas de Informação'                     => ['sistemas', 'software', ' ti ', 'tecnologia da informacao', 'banco de dados'],
        'Engenharia Mecânica'                        => ['mecanic', 'manufatura', 'manutencao'],
        'Engenharia Elétrica'                        => ['eletric', 'eletronic', 'automacao'],
        'Engenharia de Produção'                     => ['producao', 'logistica', 'qualidade', 'processos', 'supply'],
        'Engenharia Civil'                           => ['civil', ' obra', 'construcao'],
        'Administração'                              => ['administrativ', 'financeir', ' rh ', 'recursos humanos', 'comercial', 'vendas', 'marketing', 'contabil'],
        'Psicologia'                                 => ['psicolog', 'recrutamento', 'selecao'],
        'Arquitetura e Urbanismo'                    => ['arquitet', 'urbanismo', 'interiores'],
        'Ciências Biológicas'                        => ['biolog', 'laboratorio', 'ambiental'],
        'Direito'                                    => ['juridic', 'advogad', 'direito'],
        'Pedagogia'                                  => ['pedagog', 'educacao', 'ensino'],
    ];
    $validos = getFsaCourses();
    $achados = [];
    foreach ($mapa as $curso => $palavras) {
        if (!in_array($curso, $validos, true)) continue;
        foreach ($palavras as $p) {
            if (strpos($t, $p) !== false) { $achados[] = $curso; break; }
        }
    }
    return $achados;
}

/** IA (Gemini): confere se e estagio e escolhe cursos. Devolve null se nao configurada ou se falhar. */
function vexIaClassificar(string $titulo, string $descricao): ?array {
    $chave = vexEnv('GEMINI_API_KEY');
    if ($chave === '') return null;
    $modelo = vexEnv('GEMINI_MODEL') !== '' ? vexEnv('GEMINI_MODEL') : 'gemini-2.0-flash';
    $prompt = 'Você ajuda uma faculdade a classificar vagas. Responda SOMENTE um JSON no formato '
        . '{"estagio": true ou false, "cursos": ["..."]}. "estagio" é true se a vaga é de estágio, trainee ou início de carreira. '
        . '"cursos" deve ter apenas nomes desta lista: ' . json_encode(getFsaCourses(), JSON_UNESCAPED_UNICODE)
        . '. Vaga: ' . $titulo . "\n" . mb_substr($descricao, 0, 800);
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($modelo) . ':generateContent?key=' . rawurlencode($chave);
    $r = vexHttpJson($url, [
        'contents'         => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.1],
    ], 25);
    $txt = (string) ($r['candidates'][0]['content']['parts'][0]['text'] ?? '');
    $j = json_decode($txt, true);
    if (!is_array($j)) return null;
    return [
        'estagio' => !empty($j['estagio']),
        'cursos'  => array_values(array_intersect((array) ($j['cursos'] ?? []), getFsaCourses())),
    ];
}

/* ============================================================================
 * FONTE: GUPY (busca publica do portal, sem chave)
 * ATENCAO: endpoint NAO oficial (e o que o site portal.gupy.io usa). Pode mudar
 * sem aviso. Por isso: poucas chamadas, pausa entre elas, sempre o link original.
 * ========================================================================== */

function vexGupyGet(array $params, string &$log): ?array {
    if (!function_exists('curl_init')) { $log = 'extensao curl desligada'; return null; }
    // Endpoint atual (confirmado em out/2026). O segundo e reserva.
    $bases = [
        'https://portal.gupy.io/api/job-search/jobs',
        'https://employability-portal.gupy.io/api/v1/jobs',
    ];
    $params += ['sortBy' => 'publishedDate', 'sortOrder' => 'desc', 'limit' => 100, 'offset' => 0];
    $qs = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $falhas = [];
    foreach ($bases as $base) {
        $host = parse_url($base, PHP_URL_HOST);
        $res = false; $code = 0; $err = '';
        for ($tent = 0; $tent < 3; $tent++) {
            $ch = curl_init($base . '?' . $qs);
            $op = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER     => [
                    'Accept: application/json',
                    'User-Agent: Mozilla/5.0 (compatible; CentralEstagiosFSA/1.0)',
                ],
            ];
            $ca = __DIR__ . '/cacert.pem'; // Windows local: certificado do cURL
            if (is_file($ca)) $op[CURLOPT_CAINFO] = $ca;
            curl_setopt_array($ch, $op);
            $res  = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);
            if ($code === 429 || $code >= 500) { usleep((1 + $tent) * 1500000); continue; }
            break;
        }
        if ($res === false || $code !== 200) { $falhas[] = "$host HTTP $code $err"; continue; }
        $j = json_decode((string) $res, true);
        if (!is_array($j) || !isset($j['data']) || !is_array($j['data'])) { $falhas[] = "$host formato inesperado"; continue; }
        $log = "$host: " . count($j['data']) . ' vagas';
        return $j['data'];
    }
    $log = implode(' | ', $falhas);
    return null;
}

/** Converte uma vaga da Gupy para o formato comum. */
function vexNormalizarGupy(array $j): ?array {
    $tipo = (string) ($j['type'] ?? '');
    $tiposOk = ['vacancy_type_internship', 'vacancy_type_trainee', 'vacancy_type_apprentice'];
    $prazo = (string) ($j['applicationDeadline'] ?? '');
    if ($prazo !== '' && strtotime($prazo) !== false && strtotime($prazo) < time()) return null; // inscricoes encerradas
    $remoto = !empty($j['isRemoteWork']) || (string) ($j['workplaceType'] ?? '') === 'remote';
    $wt = (string) ($j['workplaceType'] ?? '');
    return [
        'extId'      => 'gupy-' . (string) ($j['id'] ?? md5((string) ($j['jobUrl'] ?? ''))),
        'fonte'      => 'Gupy',
        'titulo'     => trim((string) ($j['name'] ?? '')),
        'empresa'    => trim((string) ($j['careerPageName'] ?? '')),
        'descricao'  => trim(html_entity_decode(strip_tags((string) ($j['description'] ?? '')), ENT_QUOTES, 'UTF-8')),
        'cidade'     => trim((string) ($j['city'] ?? '')),
        'remoto'     => $remoto,
        'modalidade' => $remoto ? 'Remoto' : ($wt === 'hybrid' ? 'Híbrido' : 'Presencial'),
        'link'       => (string) ($j['jobUrl'] ?? ''),
        'salario'    => 'A combinar',
        'tipoOk'     => in_array($tipo, $tiposOk, true), // a propria Gupy diz que e estagio/trainee
        'prazo'      => $prazo,
    ];
}

function vexBuscarGupy(): array {
    // A Gupy exige cidade/estado com acento, exatamente assim.
    $consultas = [];
    foreach (['São Paulo', 'Santo André', 'São Bernardo do Campo', 'São Caetano do Sul', 'Diadema', 'Mauá'] as $c) {
        $consultas[] = ["estágio em $c", ['jobName' => 'estágio', 'city' => $c, 'state' => 'São Paulo']];
    }
    $consultas[] = ['trainee em SP',  ['jobName' => 'trainee', 'state' => 'São Paulo']];
    $consultas[] = ['estágio remoto', ['jobName' => 'estágio', 'workplaceType' => 'remote']];

    $limite = 100; $maxPaginas = 2; // so a capital costuma passar de 100
    $vagas = []; $chamadas = 0; $falhas = 0; $logs = [];
    foreach ($consultas as [$rotulo, $p]) {
        for ($pg = 0; $pg < $maxPaginas; $pg++) {
            if ($chamadas > 0) usleep(1200000); // 1,2 s entre chamadas
            $chamadas++;
            $log = '';
            $lista = vexGupyGet($p + ['limit' => $limite, 'offset' => $pg * $limite], $log);
            $logs[] = "[$rotulo p" . ($pg + 1) . "] $log";
            if ($lista === null) { $falhas++; break; }
            foreach ($lista as $item) {
                if (!is_array($item)) continue;
                $n = vexNormalizarGupy($item);
                if ($n !== null) $vagas[$n['extId']] = $n;
            }
            if (count($lista) < $limite) break;
        }
    }
    $erro = '';
    if ($falhas === $chamadas) {
        $erro = 'A Gupy não respondeu (' . end($logs) . '). A busca pública pode ter mudado; o resto da Central continua funcionando.';
    }
    return ['vagas' => array_values($vagas), 'erro' => $erro, 'chamadas' => $chamadas, 'log' => $logs];
}

/* ============================================================================
 * FONTE: ADZUNA (plano pago: 25 chamadas de teste, depois £8/mes)
 * ========================================================================== */

function vexNormalizarAdzuna(array $r): array {
    $area   = (array) ($r['location']['area'] ?? []);
    $cidade = $area ? (string) end($area) : trim(explode(',', (string) ($r['location']['display_name'] ?? ''))[0]);
    $descr  = trim(strip_tags((string) ($r['description'] ?? '')));
    $min = (float) ($r['salary_min'] ?? 0);
    $max = (float) ($r['salary_max'] ?? 0);
    $salario = 'A combinar';
    if ($min > 0) {
        $salario = 'R$ ' . number_format($min, 2, ',', '.');
        if ($max > $min) $salario .= ' a R$ ' . number_format($max, 2, ',', '.');
    }
    $remoto = (bool) preg_match('/remot|home office/', mhSemAcento((string) ($r['title'] ?? '') . ' ' . $descr));
    return [
        'extId'      => 'adzuna-' . (string) ($r['id'] ?? ''),
        'fonte'      => 'Adzuna',
        'titulo'     => trim((string) ($r['title'] ?? '')),
        'empresa'    => trim((string) ($r['company']['display_name'] ?? '')),
        'descricao'  => $descr,
        'cidade'     => $cidade,
        'remoto'     => $remoto,
        'modalidade' => $remoto ? 'Remoto' : 'Presencial',
        'link'       => (string) ($r['redirect_url'] ?? ''),
        'salario'    => $salario,
        'tipoOk'     => false,
        'prazo'      => '',
    ];
}

/** Busca no Adzuna. Devolve ['vagas' => [...normalizadas], 'erro' => '', 'chamadas' => n] */
function vexBuscarAdzuna(): array {
    if (!vexAdzunaConfigurado()) {
        return ['vagas' => [], 'erro' => 'Defina ADZUNA_APP_ID e ADZUNA_APP_KEY em Environment (Render) ou no .env.', 'chamadas' => 0];
    }
    $cidades = ['Santo André', 'São Bernardo do Campo', 'São Caetano do Sul', 'São Paulo', 'Diadema', 'Mauá'];
    $termos  = ['estágio', 'trainee'];
    $vagas = []; $chamadas = 0; $falhas = 0;

    foreach ($cidades as $cidade) {
        foreach ($termos as $termo) {
            $url = 'https://api.adzuna.com/v1/api/jobs/br/search/1?' . http_build_query([
                'app_id'           => vexEnv('ADZUNA_APP_ID'),
                'app_key'          => vexEnv('ADZUNA_APP_KEY'),
                'results_per_page' => 30,
                'what'             => $termo,
                'where'            => $cidade,
                'max_days_old'     => 21,
                'sort_by'          => 'date',
            ]);
            $chamadas++;
            $r = vexHttpJson($url);
            if ($r === null) { $falhas++; continue; }
            foreach ((array) ($r['results'] ?? []) as $item) {
                if (is_array($item)) $vagas[] = vexNormalizarAdzuna($item);
            }
        }
    }
    $erro = '';
    if ($falhas === $chamadas) $erro = 'A API do Adzuna não respondeu. Confira as chaves e o limite do plano.';
    return ['vagas' => $vagas, 'erro' => $erro, 'chamadas' => $chamadas];
}

/** Junta as fontes ativas. */
function vexBuscar(): array {
    $fontes = vexFontes();
    if (empty($fontes)) return ['vagas' => [], 'erro' => vexStatusFonte(), 'chamadas' => 0];
    $vagas = []; $erros = []; $chamadas = 0;
    foreach ($fontes as $f) {
        $r = $f === 'gupy' ? vexBuscarGupy() : vexBuscarAdzuna();
        $vagas = array_merge($vagas, $r['vagas']);
        $chamadas += (int) $r['chamadas'];
        if ($r['erro'] !== '') $erros[] = $r['erro'];
    }
    return ['vagas' => $vagas, 'erro' => implode(' ', $erros), 'chamadas' => $chamadas];
}

/** Busca, filtra, classifica e coloca na fila. Devolve contagens. */
function vexImportar(): array {
    @set_time_limit(180);
    $busca = vexBuscar();
    if ($busca['erro'] !== '' && empty($busca['vagas'])) {
        return ['novas' => 0, 'duplicadas' => 0, 'descartadas' => 0, 'erro' => $busca['erro']];
    }

    $fila = loadData('vagas_sugeridas');
    if (!is_array($fila)) $fila = [];
    $chaves = []; $externos = [];
    foreach ($fila as $s) {
        $chaves[mhChaveVaga((string) ($s['title'] ?? ''), (string) ($s['company'] ?? ''), (string) ($s['cidade'] ?? ''))] = true;
        if (!empty($s['externalId'])) $externos[(string) $s['externalId']] = true;
    }
    foreach (loadData('platform_jobs') as $j) {
        $chaves[mhChaveVaga((string) ($j['title'] ?? ''), (string) ($j['company'] ?? ''), (string) ($j['location'] ?? ''))] = true;
    }

    $novas = 0; $dup = 0; $desc = 0; $iaUsos = 0;
    foreach ($busca['vagas'] as $v) {
        $titulo  = $v['titulo'];
        $empresa = $v['empresa'];
        $descr   = $v['descricao'];
        $cidade  = $v['cidade'];
        $extId   = $v['extId'];

        // Cidade: SP + ABC, ou vaga remota
        if ($titulo === '' || $empresa === '') { $desc++; continue; }
        if (!vexCidadeAceita($cidade) && !$v['remoto']) { $desc++; continue; }

        // E estagio? (a Gupy ja informa o tipo; nas outras fontes, por palavras)
        $t = mhSemAcento($titulo . ' ' . $descr);
        if (!$v['tipoOk'] && !preg_match('/estag|trainee|aprendiz/', $t)) { $desc++; continue; }
        if (preg_match('/senior|\bsr\b|pleno|gerente|coordenador|diretor|especialista/', mhSemAcento($titulo))) { $desc++; continue; }

        $chave = mhChaveVaga($titulo, $empresa, $cidade);
        if (isset($chaves[$chave]) || isset($externos[$extId])) { $dup++; continue; }

        $cursos = vexCursosPorPalavras($titulo . ' ' . $descr);
        $nota = 'Filtro por palavras-chave';
        if (vexIaConfigurada() && $iaUsos < 25) {
            $iaUsos++;
            $ia = vexIaClassificar($titulo, $descr);
            if ($ia !== null) {
                if (!$ia['estagio'] && !$v['tipoOk']) { $desc++; continue; }
                if (!empty($ia['cursos'])) $cursos = $ia['cursos'];
                $nota = 'Conferida por IA';
            }
        }
        if ($v['tipoOk'] && $nota === 'Filtro por palavras-chave') $nota = 'Tipo informado pela ' . $v['fonte'];

        $local = $cidade !== '' ? $cidade . ', SP' : 'Remoto';
        $fila[] = [
            'id'              => time() * 1000 + random_int(0, 999),
            'status'          => 'pendente',
            'title'           => $titulo,
            'company'         => $empresa,
            'location'        => $local,
            'cidade'          => $cidade,
            'salary'          => $v['salario'],
            'type'            => $v['modalidade'],
            'hours'           => 'A combinar',
            'description'     => mb_substr($descr, 0, 600) . (mb_strlen($descr) > 600 ? '...' : ''),
            'requirements'    => '',
            'benefits'        => '',
            'courses'         => implode(', ', $cursos),
            'applicationLink' => $v['link'],
            'fonte'           => $v['fonte'],
            'externalId'      => $extId,
            'prazo'           => $v['prazo'],
            'nota'            => $nota,
            'criadoTs'        => time(),
        ];
        $chaves[$chave] = true;
        $externos[$extId] = true;
        $novas++;
    }
    saveData('vagas_sugeridas', array_values($fila));
    return ['novas' => $novas, 'duplicadas' => $dup, 'descartadas' => $desc, 'erro' => (string) $busca['erro']];
}
