<?php
/**
 * =============================================================================
 * INTEGRAÇÃO TOTVS — 2/4: CLIENTE (quem conversa com o TOTVS)
 * -----------------------------------------------------------------------------
 * Este arquivo tem UMA responsabilidade: buscar a lista de alunos.
 * Ele não sabe nada da Central (não grava usuário, não mexe em tela).
 * Só devolve as linhas do jeito que o TOTVS mandou.
 *
 * Toda função daqui devolve sempre o mesmo formato:
 *   [ 'ok' => true/false, 'linhas' => [ ...alunos... ], 'erro' => 'mensagem' ]
 * Assim quem chama só precisa olhar o 'ok'.
 * =============================================================================
 */

require_once __DIR__ . '/totvs-config.php';
require_once __DIR__ . '/banco-conexao.php';

/** Monta a resposta padrão de erro (para não repetir o array toda hora). */
function totvsErro(string $mensagem): array {
    return ['ok' => false, 'linhas' => [], 'erro' => $mensagem];
}

/**
 * Busca os alunos de acordo com o modo configurado.
 *   desligado -> devolve erro explicando
 *   teste     -> lê data/totvs_exemplo.json
 *   real      -> chama a API do TOTVS
 *   banco     -> lê direto do banco do TOTVS
 */
function totvsBuscarAlunos(): array {
    $modo = totvsModo();

    if ($modo === 'desligado') {
        return totvsErro('A integração está desligada. Defina TOTVS_MODO como "teste" ou "real".');
    }

    if ($modo === 'teste') {
        return totvsBuscarAlunosTeste();
    }

    if ($modo === 'banco') {
        return totvsBuscarAlunosBanco();
    }

    return totvsBuscarAlunosReal();
}

/**
 * MODO TESTE: lê um arquivo de exemplo, no mesmo formato que o TOTVS devolve.
 * Serve para testar a tela e as regras sem depender da TI.
 */
function totvsBuscarAlunosTeste(): array {
    $linhas = loadData('totvs_exemplo');   // data/totvs_exemplo.json

    if (empty($linhas)) {
        return totvsErro('O arquivo data/totvs_exemplo.json não existe ou está vazio.');
    }
    return ['ok' => true, 'linhas' => array_values($linhas), 'erro' => ''];
}

/**
 * MODO REAL: chama a API do TOTVS com usuário e senha (autenticação Basic).
 */
function totvsBuscarAlunosReal(): array {
    // 1) Confere se a configuração está completa
    $faltando = totvsProblemasConfig();
    if ($faltando) {
        return totvsErro('Faltam variáveis de ambiente: ' . implode(', ', $faltando) . '.');
    }

    if (!function_exists('curl_init')) {
        return totvsErro('A extensão curl do PHP não está ativa.');
    }

    // 2) Monta o endereço trocando {sentenca}, {coligada} e {sistema}
    $cfg = totvsConfig();
    $caminho = strtr($cfg['caminho'], [
        '{sentenca}' => rawurlencode($cfg['sentenca']),
        '{coligada}' => rawurlencode($cfg['coligada']),
        '{sistema}'  => rawurlencode($cfg['sistema']),
    ]);
    $url = $cfg['url'] . $caminho;

    // 3) Faz a chamada
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,                       // devolve a resposta como texto
        CURLOPT_TIMEOUT        => $cfg['timeout'],            // não fica esperando para sempre
        CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,             // usuário e senha
        CURLOPT_USERPWD        => $cfg['usuario'] . ':' . $cfg['senha'],
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);

    // No Windows, o curl às vezes não acha os certificados SSL.
    // O projeto já usa este arquivo para o Gmail; se ele existir, usamos aqui também.
    $certificado = __DIR__ . '/../cacert.pem';
    if (is_file($certificado)) {
        curl_setopt($ch, CURLOPT_CAINFO, $certificado);
    }

    $resposta = curl_exec($ch);
    $erroCurl = curl_error($ch);
    $codigo   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 4) Trata os erros mais comuns com mensagens que a pessoa entende
    if ($resposta === false) {
        return totvsErro('Não foi possível conectar ao TOTVS: ' . $erroCurl);
    }
    if ($codigo === 401 || $codigo === 403) {
        return totvsErro('O TOTVS recusou o usuário ou a senha (HTTP ' . $codigo . ').');
    }
    if ($codigo === 404) {
        return totvsErro('Endereço não encontrado (HTTP 404). Confira TOTVS_URL, TOTVS_CAMINHO e o código da consulta.');
    }
    if ($codigo < 200 || $codigo >= 300) {
        return totvsErro('O TOTVS respondeu com erro (HTTP ' . $codigo . ').');
    }

    // 5) Converte o JSON em array
    $dados = json_decode((string) $resposta, true);
    if (!is_array($dados)) {
        return totvsErro('A resposta do TOTVS não veio em JSON.');
    }

    // Algumas rotas devolvem a lista dentro de "data" ou "items". Aceitamos os três jeitos.
    if (isset($dados['data']) && is_array($dados['data']))   $dados = $dados['data'];
    if (isset($dados['items']) && is_array($dados['items'])) $dados = $dados['items'];

    return ['ok' => true, 'linhas' => array_values($dados), 'erro' => ''];
}

/**
 * MODO BANCO: lê os alunos direto do banco de dados do TOTVS.
 * A consulta (TOTVS_DB_CONSULTA) é escrita pela TI e precisa devolver as
 * colunas RA, NOME, CPF, EMAIL, CURSO, PERIODO, TURNO e SITUACAO
 * (use "AS" para renomear, ex.: SELECT P.NOME AS NOME ...).
 * Use um usuário de banco SÓ DE LEITURA.
 */
function totvsBuscarAlunosBanco(): array {
    $faltando = totvsProblemasConfig();
    if ($faltando) {
        return totvsErro('Faltam variáveis de ambiente: ' . implode(', ', $faltando) . '.');
    }
    try {
        // TOTVS_DB_CONSULTA (SELECT pronto) tem prioridade; senão lê a tabela/view TOTVS_DB_TABELA
        $consulta = totvsEnv('TOTVS_DB_CONSULTA');
        if ($consulta === '') {
            $tabela = totvsEnv('TOTVS_DB_TABELA');
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*){0,2}$/', $tabela)) {
                return totvsErro('TOTVS_DB_TABELA inválida: use só letras, números, "_" e "." (ex.: dbo.VW_CENTRAL_ALUNOS).');
            }
            $consulta = 'SELECT * FROM ' . $tabela;
        }
        $linhas = bancoSelecionar('TOTVS_DB', $consulta);
    } catch (Throwable $e) {
        return totvsErro($e->getMessage());
    }
    return ['ok' => true, 'linhas' => $linhas, 'erro' => ''];
}
