<?php
/**
 * =============================================================================
 * INTEGRAÇÃO TOTVS — 1/4: CONFIGURAÇÃO
 * -----------------------------------------------------------------------------
 * Aqui ficam só as CONFIGURAÇÕES da integração. Nenhuma senha fica escrita
 * neste arquivo: tudo vem das variáveis de ambiente (.env no computador,
 * Render > Environment no site publicado).
 *
 * Variáveis usadas:
 *   TOTVS_MODO          desligado | teste | real | banco   (padrão: desligado)
 *                         desligado -> a integração não faz nada
 *                         teste     -> lê o arquivo data/totvs_exemplo.json
 *                                      (serve para testar sem ter o TOTVS)
 *                         real      -> chama a API do TOTVS (consulta SQL cadastrada no RM)
 *                         banco     -> lê DIRETO do banco de dados do TOTVS (SQL Server/Oracle),
 *                                      com TOTVS_DB_DSN, TOTVS_DB_USUARIO, TOTVS_DB_SENHA
 *                                      e TOTVS_DB_TABELA (tabela ou view de alunos, ex.: dbo.VW_CENTRAL_ALUNOS)
 *                                      ou TOTVS_DB_CONSULTA (um SELECT pronto; tem prioridade)
 *   TOTVS_URL           endereço do servidor TOTVS, ex.: https://totvs.fsa.br:8051
 *   TOTVS_USUARIO       usuário de integração criado pela TI no TOTVS
 *   TOTVS_SENHA         senha desse usuário
 *   TOTVS_CODSENTENCA   código da consulta SQL cadastrada no TOTVS RM
 *   TOTVS_CODCOLIGADA   código da coligada (geralmente 1)
 *   TOTVS_CODSISTEMA    código do sistema (no RM Educacional costuma ser "S")
 *   TOTVS_CAMINHO       caminho da API (opcional, veja o padrão abaixo)
 *   TOTVS_TIMEOUT       segundos de espera pela resposta (padrão: 30)
 *
 * IMPORTANTE: os valores certos (endereço, código da consulta, coligada e
 * sistema) quem informa é a TI da FSA. Confirme com eles antes de usar o
 * modo "real".
 * =============================================================================
 */

/**
 * Lê uma variável de ambiente.
 * Procura primeiro no $_ENV (preenchido pelo .env) e depois no getenv()
 * (usado pelo Render). Se não achar, devolve o valor padrão.
 */
function totvsEnv(string $nome, string $padrao = ''): string {
    $valor = $_ENV[$nome] ?? getenv($nome);

    if ($valor === false || $valor === null || trim((string) $valor) === '') {
        return $padrao;
    }
    return trim((string) $valor);
}

/**
 * Junta todas as configurações num array só.
 * Assim o resto do código nunca precisa chamar getenv() direto.
 */
function totvsConfig(): array {
    return [
        'modo'     => strtolower(totvsEnv('TOTVS_MODO', 'desligado')),
        'url'      => rtrim(totvsEnv('TOTVS_URL'), '/'),   // tira a barra do fim, se tiver
        'usuario'  => totvsEnv('TOTVS_USUARIO'),
        'senha'    => totvsEnv('TOTVS_SENHA'),
        'sentenca' => totvsEnv('TOTVS_CODSENTENCA'),
        'coligada' => totvsEnv('TOTVS_CODCOLIGADA', '1'),
        'sistema'  => totvsEnv('TOTVS_CODSISTEMA', 'S'),

        // Caminho da API. O padrão é a rota de "consulta SQL" do TOTVS RM.
        // As partes entre chaves são trocadas pelos valores acima.
        // Se a TI usar outra rota, basta mudar a variável TOTVS_CAMINHO.
        'caminho'  => totvsEnv(
            'TOTVS_CAMINHO',
            '/api/framework/v1/consultaSQLServer/RealizaConsulta/{sentenca}/{coligada}/{sistema}'
        ),

        'timeout'  => max(5, (int) totvsEnv('TOTVS_TIMEOUT', '30')),
    ];
}

/**
 * Devolve o modo atual. Qualquer valor estranho vira "desligado",
 * para a integração nunca rodar por engano.
 */
function totvsModo(): string {
    $modo = totvsConfig()['modo'];
    return in_array($modo, ['desligado', 'teste', 'real', 'banco'], true) ? $modo : 'desligado';
}

/**
 * Lista o que está faltando para o modo "real" funcionar.
 * Lista vazia = está tudo preenchido.
 * A tela admin-totvs.php usa isso para avisar o admin.
 */
function totvsProblemasConfig(): array {
    $faltando = [];

    // Modo banco: precisa do acesso ao banco e da consulta
    if (totvsModo() === 'banco') {
        if (totvsEnv('TOTVS_DB_DSN') === '')      $faltando[] = 'TOTVS_DB_DSN';
        if (totvsEnv('TOTVS_DB_CONSULTA') === '' && totvsEnv('TOTVS_DB_TABELA') === '') $faltando[] = 'TOTVS_DB_TABELA (ou TOTVS_DB_CONSULTA)';
        return $faltando;
    }

    // Modo real (API): precisa do endereço, usuário, senha e código da consulta
    $cfg = totvsConfig();
    if ($cfg['url'] === '')      $faltando[] = 'TOTVS_URL';
    if ($cfg['usuario'] === '')  $faltando[] = 'TOTVS_USUARIO';
    if ($cfg['senha'] === '')    $faltando[] = 'TOTVS_SENHA';
    if ($cfg['sentenca'] === '') $faltando[] = 'TOTVS_CODSENTENCA';

    return $faltando;
}
