<?php
/**
 * Ponte entre a Central de Estagios (PHP) e o app de Perfil por Competencias (Flask).
 *
 * O Perfil e um app independente. Quando acessado pela Central, a Central
 * GUARDA uma copia do resultado de cada aluno em JSON:
 *
 *     data/users/<id_do_usuario>/perfilResultado.json
 *
 * Fluxo:
 *   1) Ao abrir o Perfil / Dicas de Carreira, a Central pergunta ao Flask pelo
 *      resultado do aluno. Se for novo, salva o historico no JSON do usuario.
 *   2) Se o Flask nao tiver mais o resultado (reiniciou no Render) ou estiver fora
 *      do ar, a Central usa o JSON salvo e o relatorio continua aparecendo.
 *   3) Se o Flask respondeu "nao encontrado" mas a Central tem o JSON, a Central
 *      devolve o historico ao Flask (/api/restaurar), para o PDF e o radar voltarem.
 */

define('PERFIL_APP_URL', 'https://perfil-fsa.onrender.com');

function slugifyNome($nome) {
    $nome = trim((string) $nome);

    $semAcento = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);
    if ($semAcento === false || $semAcento === null) {
        $de   = ['á','à','â','ã','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','ô','õ','ö','ú','ù','û','ü','ç','ñ',
                 'Á','À','Â','Ã','Ä','É','È','Ê','Ë','Í','Ì','Î','Ï','Ó','Ò','Ô','Õ','Ö','Ú','Ù','Û','Ü','Ç','Ñ'];
        $para = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n',
                 'A','A','A','A','A','E','E','E','E','I','I','I','I','O','O','O','O','O','U','U','U','U','C','N'];
        $semAcento = str_replace($de, $para, $nome);
    }

    $minusculo = strtolower($semAcento);
    $slug = preg_replace('/[^a-z0-9]+/', '_', $minusculo);
    $slug = trim($slug, '_');

    return $slug !== '' ? $slug : 'aluno';
}

/* ------------------------------------------------------------------ */
/* JSON do usuario na Central                                          */
/* ------------------------------------------------------------------ */

/** Le o historico salvo do usuario logado (lista de resultados) ou []. */
function _perfilLerLocal() {
    $dados = loadData('perfilResultado', true);   // data/users/<id>/perfilResultado.json
    return (isset($dados['historico']) && is_array($dados['historico'])) ? $dados['historico'] : [];
}

/** Salva o historico no JSON do usuario logado. */
function _perfilSalvarLocal(array $historico) {
    saveData('perfilResultado', [
        'atualizado_em' => date('c'),
        'historico'     => array_values($historico),
    ], true);
}

/* ------------------------------------------------------------------ */
/* Chamadas HTTP ao Flask                                              */
/* ------------------------------------------------------------------ */

/**
 * GET/POST no Flask. Retorna array (JSON) ou null.
 * O ultimo codigo HTTP fica em $GLOBALS['_perfil_http'] (0 = Flask fora do ar/timeout).
 */
function _perfilApiChamar($caminho, $metodo = 'GET', $corpo = null) {
    $GLOBALS['_perfil_http'] = 0;

    if (!function_exists('curl_init')) {
        error_log('[PERFIL_API] extensao curl nao esta habilitada neste PHP.');
        return null;
    }

    // Se o Flask falhou ha pouco tempo, nem tenta de novo (evita travar a pagina)
    $flagFalha = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'perfil_api_falha.flag';
    if (file_exists($flagFalha) && (time() - filemtime($flagFalha)) < 60) {
        return null;
    }

    $url = rtrim(PERFIL_APP_URL, '/') . $caminho;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if ($metodo === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    $resposta = curl_exec($ch);
    $erroCurl = curl_error($ch);
    $codigo   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $GLOBALS['_perfil_http'] = $codigo;

    if ($erroCurl) {
        error_log("[PERFIL_API] Erro cURL ao chamar {$url}: {$erroCurl}");
        @touch($flagFalha);
        return null;
    }

    if ($codigo !== 200 || !$resposta) {
        error_log("[PERFIL_API] Resposta inesperada de {$url}: HTTP {$codigo}");
        if ($codigo >= 500 || $codigo === 0) {
            @touch($flagFalha);
        }
        return null;
    }

    $dados = json_decode($resposta, true);
    if (!is_array($dados)) {
        error_log("[PERFIL_API] JSON invalido recebido de {$url}: " . substr((string) $resposta, 0, 300));
        return null;
    }

    return $dados;
}

function _perfilApiGet($caminho) {
    return _perfilApiChamar($caminho, 'GET');
}

/* ------------------------------------------------------------------ */
/* Funcoes usadas pelas paginas                                        */
/* ------------------------------------------------------------------ */

/**
 * Retorna o ULTIMO resultado do aluno (do Flask, ou do JSON salvo na Central
 * se o Flask nao tiver mais / estiver fora do ar). null se nunca respondeu.
 */
function buscarResultadoPerfil($nomeAluno) {
    $slug  = slugifyNome($nomeAluno);
    $local = _perfilLerLocal();
    $ultimoLocal = $local ? end($local) : null;

    $remoto = _perfilApiGet('/api/resultado/' . rawurlencode($slug));
    $http   = $GLOBALS['_perfil_http'] ?? 0;

    // 1) Flask tem resultado: atualiza o JSON da Central se for mais novo
    if (is_array($remoto) && isset($remoto['percentuais'])) {
        $tsRemoto = (float) ($remoto['timestamp'] ?? 0);
        $tsLocal  = (float) ($ultimoLocal['timestamp'] ?? 0);

        if (!$ultimoLocal || $tsRemoto > $tsLocal) {
            $hist = _perfilApiGet('/api/historico/' . rawurlencode($slug));
            if (!is_array($hist) || !$hist) {
                $hist = array_merge($local, [$remoto]);
            }
            _perfilSalvarLocal($hist);
        }
        return $remoto;
    }

    // 2) Flask respondeu "nao encontrado" mas a Central tem o JSON:
    //    devolve o historico ao Flask para o PDF/radar voltarem a funcionar.
    if ($ultimoLocal && $http === 404) {
        _perfilApiChamar('/api/restaurar/' . rawurlencode($slug), 'POST', $local);
    }

    // 3) Usa o JSON salvo na Central (ou null se o aluno nunca respondeu)
    return $ultimoLocal ?: null;
}

/**
 * Retorna TODAS as respostas do aluno (mais recente por ultimo).
 */
function buscarHistoricoPerfil($nomeAluno) {
    $slug = slugifyNome($nomeAluno);
    $historico = _perfilApiGet('/api/historico/' . rawurlencode($slug));
    if (is_array($historico) && $historico) {
        return $historico;
    }
    return _perfilLerLocal();
}

function urlPdfPerfil(array $resultado) {
    return rtrim(PERFIL_APP_URL, '/') . ($resultado['pdf_url'] ?? '');
}

function urlRadarPerfil(array $resultado) {
    $url = rtrim(PERFIL_APP_URL, '/') . ($resultado['radar_url'] ?? '');
    $separador = (strpos($url, '?') === false) ? '?' : '&';
    return $url . $separador . 't=' . (int) ($resultado['timestamp'] ?? time());
}

function urlFormularioPerfil($nomeAluno) {
    return rtrim(PERFIL_APP_URL, '/') . '/?nome=' . rawurlencode($nomeAluno);
}
