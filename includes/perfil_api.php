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

/**
 * Faz uma requisicao GET simples via cURL para o app Flask.
 * Retorna um array associativo (decodificado do JSON) ou null se der erro
 * (aluno ainda nao respondeu, servidor fora do ar, timeout, etc.).
 *
 * HISTORICO DE AJUSTES:
 * - 25/08/2026: timeout aumentado de 5s/3s para 90s/15s (Flask com 1 worker
 *   sync ficava enfileirado atras de chamadas lentas a IA). Resolvido depois
 *   com --threads 4 no gunicorn, mas o timeout maior foi mantido como
 *   seguranca extra (cold start do Render, picos de latencia da IA).
 * - 25/08/2026 (correcao): REMOVIDA a opcao CURLOPT_CAINFO apontando para um
 *   "cacert.pem" local. Essa opcao so faz sentido em instalacoes Windows/XAMPP
 *   com bundle de certificados desatualizado. No Render (Linux), o cURL ja usa
 *   o bundle de certificados do sistema operacional, que e valido e atualizado.
 *   Se esse arquivo cacert.pem nao existir no repositorio (como e o caso aqui),
 *   a opcao fazia o handshake SSL FALHAR SILENCIOSAMENTE: curl_exec() retornava
 *   false, e a funcao devolvia null -- exatamente como se o aluno nunca tivesse
 *   respondido o formulario, mesmo com o resultado ja salvo no Flask. Essa era
 *   a causa mais provavel do "calculei o resultado mas nao aparece no site".
 *
 * DEBUG TEMPORARIO: se ainda assim der problema, o erro exato do cURL fica
 * registrado no error_log do PHP (visivel nos logs do Render) com o prefixo
 * "[PERFIL_API]". Pode remover esse bloco de log depois de confirmar que
 * esta tudo funcionando.
 */
function _perfilApiGet($caminho) {
    if (!function_exists('curl_init')) {
        error_log('[PERFIL_API] extensao curl nao esta habilitada neste PHP.');
        return null;
    }

    $url = rtrim(PERFIL_APP_URL, '/') . $caminho;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    // (Sem CURLOPT_CAINFO: usa o bundle de certificados do proprio sistema operacional)

    $resposta = curl_exec($ch);
    $erroCurl = curl_error($ch);
    $codigo   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($erroCurl) {
        error_log("[PERFIL_API] Erro cURL ao chamar {$url}: {$erroCurl}");
        return null;
    }

    if ($codigo !== 200 || !$resposta) {
        error_log("[PERFIL_API] Resposta inesperada de {$url}: HTTP {$codigo}");
        return null;
    }

    $dados = json_decode($resposta, true);
    if (!is_array($dados)) {
        error_log("[PERFIL_API] JSON invalido recebido de {$url}: " . substr((string) $resposta, 0, 300));
        return null;
    }

    return $dados;
}

/**
 * Retorna o ULTIMO resultado do aluno (percentuais, parecer, links de pdf/radar)
 * ou null se ele ainda nao respondeu o formulario / servidor fora do ar.
 */
function buscarResultadoPerfil($nomeAluno) {
    $slug = slugifyNome($nomeAluno);
    return _perfilApiGet('/api/resultado/' . rawurlencode($slug));
}

/**
 * Retorna TODAS as respostas ja dadas pelo aluno (historico completo),
 * mais recente por ultimo no array.
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
 * Monta a URL completa (no dominio do Flask) da imagem do grafico de radar.
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
