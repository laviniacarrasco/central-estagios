<?php
/**
 * Ponte entre a Central de Estagios (PHP) e o app de Perfil por Competencias (Flask).
 *
 * Como os dois sistemas rodam em servidores/dominios SEPARADOS, a integracao e
 * feita 100% via HTTP: o PHP faz uma chamada cURL no SERVIDOR (nao no navegador
 * do aluno) para os endpoints de leitura expostos pelo app.py do Flask.
 * Isso evita qualquer problema de CORS.
 *
 * IMPORTANTE: ajuste a constante PERFIL_APP_URL abaixo para o endereco real
 * onde o Flask estiver publicado (ex: https://perfil.suaescola.com).
 */

//AJUSTE AQUI antes de subir para producao
define('PERFIL_APP_URL', 'https://perfil-fsa.onrender.com');

/**
 * Gera o mesmo "slug" que o Flask gera a partir do nome do aluno
 * (ver identificacao.py -> slugify_nome). PRECISA seguir exatamente a
 * mesma regra dos dois lados, senao o PHP procura um slug que nunca vai bater
 * com o que o Flask salvou.
 *
 * NOTA: esta versao NAO depende da extensao "mbstring" (nem de mb_strtolower),
 * porque em algumas instalacoes de PHP no Windows (XAMPP/WAMP mal configurado)
 * essa extensao vem desativada por padrao no php.ini. Usamos apenas funcoes
 * nativas (strtolower, iconv, preg_replace), que sempre existem.
 */
function slugifyNome($nome) {
    $nome = trim((string) $nome);

    // remove acentos (a -> a, e -> e, c -> c, etc.) ANTES de baixar a caixa,
    // porque iconv//TRANSLIT lida melhor com o texto original.
    $semAcento = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);
    if ($semAcento === false || $semAcento === null) {
        // fallback caso a extensao iconv tambem nao esteja disponivel:
        // troca manualmente os acentos mais comuns em portugues.
        $de   = ['á','à','â','ã','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','ô','õ','ö','ú','ù','û','ü','ç','ñ',
                 'Á','À','Â','Ã','Ä','É','È','Ê','Ë','Í','Ì','Î','Ï','Ó','Ò','Ô','Õ','Ö','Ú','Ù','Û','Ü','Ç','Ñ'];
        $para = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n',
                 'A','A','A','A','A','E','E','E','E','I','I','I','I','O','O','O','O','O','U','U','U','U','C','N'];
        $semAcento = str_replace($de, $para, $nome);
    }

    // minusculo (sem precisar de mb_strtolower, ja que apos o iconv o texto e ASCII puro)
    $minusculo = strtolower($semAcento);

    // troca qualquer coisa que nao seja letra/numero por "_"
    $slug = preg_replace('/[^a-z0-9]+/', '_', $minusculo);
    $slug = trim($slug, '_');

    return $slug !== '' ? $slug : 'aluno';
}

/**
 * Faz uma requisicao GET simples via cURL para o app Flask.
 * Retorna um array associativo (decodificado do JSON) ou null se der erro
 * (aluno ainda nao respondeu, servidor fora do ar, timeout, etc.).
 *
 * NOTA IMPORTANTE (ajuste feito em 25/08/2026):
 * O Flask roda com apenas 1 worker (sync) no plano free do Render. Quando esse
 * worker esta ocupado processando um /api/submit (que pode levar 1-2 minutos
 * esperando a IA do Gemini responder, incluindo retries em caso de erro 503),
 * qualquer outra requisicao -- inclusive esta consulta de leitura, que em
 * condicoes normais e quase instantanea -- fica ENFILEIRADA atras dela.
 * Um timeout de 5s era baixo demais para esse cenario e fazia o profile.php
 * cair no estado de "aluno ainda nao respondeu" mesmo com o resultado ja
 * salvo no Flask. Aumentamos a margem para acomodar tanto o cold start do
 * Render quanto essa fila do worker unico.
 */
function _perfilApiGet($caminho) {
    if (!function_exists('curl_init')) {
        // extensao curl nao habilitada no php.ini
        return null;
    }

    $url = rtrim(PERFIL_APP_URL, '/') . $caminho;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);         // era 5s -- agora da tempo do worker unico do Flask ficar livre
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);  // era 3s -- agora da tempo do cold start do Render
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/cacert.pem'); // certificados raiz (necessario no Windows/XAMPP)
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $resposta = curl_exec($ch);
    $erroCurl = curl_error($ch);
    $codigo   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($erroCurl || $codigo !== 200 || !$resposta) {
        return null;
    }

    $dados = json_decode($resposta, true);
    return is_array($dados) ? $dados : null;
}

/**
 * Retorna o ULTIMO resultado do aluno (percentuais, parecer, links de pdf/radar)
 * ou null se ele ainda nao respondeu o formulario / servidor fora do ar.
 *
 * Uso:
 *   $resultado = buscarResultadoPerfil($_SESSION['user_name']);
 */
function buscarResultadoPerfil($nomeAluno) {
    $slug = slugifyNome($nomeAluno);
    return _perfilApiGet('/api/resultado/' . rawurlencode($slug));
}

/**
 * Retorna TODAS as respostas ja dadas pelo aluno (historico completo),
 * mais recente por ultimo no array. Util se um dia quiser mostrar
 * "evolução ao longo do tempo".
 */
function buscarHistoricoPerfil($nomeAluno) {
    $slug = slugifyNome($nomeAluno);
    $historico = _perfilApiGet('/api/historico/' . rawurlencode($slug));
    return is_array($historico) ? $historico : [];
}

/**
 * Monta a URL completa (no dominio do Flask) do PDF do relatorio
 * a partir do "pdf_url" relativo que vem dentro do resultado.
 */
function urlPdfPerfil(array $resultado) {
    return rtrim(PERFIL_APP_URL, '/') . ($resultado['pdf_url'] ?? '');
}

/**
 * Monta a URL completa (no dominio do Flask) da imagem do grafico de radar
 * a partir do "radar_url" relativo que vem dentro do resultado.
 * Adiciona um "cache buster" (?t=timestamp) para o navegador nao mostrar
 * uma imagem antiga em cache depois que o aluno responde de novo.
 */
function urlRadarPerfil(array $resultado) {
    $url = rtrim(PERFIL_APP_URL, '/') . ($resultado['radar_url'] ?? '');
    $separador = (strpos($url, '?') === false) ? '?' : '&';
    return $url . $separador . 't=' . (int) ($resultado['timestamp'] ?? time());
}

/**
 * Monta a URL do formulario do Flask, ja com o nome do aluno pre-preenchido.
 */
function urlFormularioPerfil($nomeAluno) {
    return rtrim(PERFIL_APP_URL, '/') . '/?nome=' . rawurlencode($nomeAluno);
}
