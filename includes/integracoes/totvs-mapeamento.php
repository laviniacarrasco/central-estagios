<?php
/**
 * =============================================================================
 * INTEGRAÇÃO TOTVS — 3/4: MAPEAMENTO (tradução TOTVS -> Central)
 * -----------------------------------------------------------------------------
 * O TOTVS e a Central chamam as coisas de jeitos diferentes.
 * Exemplo: o TOTVS manda "CURSO": "ANALISE E DESENV. DE SISTEMAS" e o turno
 * como "N"; a Central usa "Análise e Desenvolvimento de Sistemas" e "Noite".
 *
 * Este arquivo é o TRADUTOR. Se a TI mudar o nome de uma coluna, ou se
 * aparecer um curso escrito diferente, é SÓ AQUI que você mexe.
 * =============================================================================
 */

/**
 * Nome de cada coluna na consulta do TOTVS.
 *   chave (esquerda)  = campo na Central (usuarios.json)
 *   valor (direita)   = nome da coluna que vem do TOTVS
 * Maiúsculas e minúsculas não importam.
 */
function totvsMapaCampos(): array {
    return [
        'ra'       => 'RA',
        'nome'     => 'NOME',
        'cpf'      => 'CPF',
        'email'    => 'EMAIL',
        'curso'    => 'CURSO',
        'periodo'  => 'PERIODO',
        'turno'    => 'TURNO',
        'situacao' => 'SITUACAO',
    ];
}

/**
 * Cursos escritos de um jeito diferente no TOTVS.
 *   esquerda = como vem do TOTVS   |   direita = nome oficial na Central
 * Acento e maiúscula não importam na comparação.
 * Se o nome do TOTVS já for igual ao da Central, não precisa colocar aqui.
 */
function totvsMapaCursos(): array {
    return [
        'ANALISE E DESENV. DE SISTEMAS' => 'Análise e Desenvolvimento de Sistemas',
        'ADS'                           => 'Análise e Desenvolvimento de Sistemas',
        'CIENCIA DE DADOS E IA'         => 'Ciência de Dados e Inteligência Artificial',
        'ENG. MECANICA'                 => 'Engenharia Mecânica',
        'ENG. ELETRICA'                 => 'Engenharia Elétrica',
        'ENG. DE PRODUCAO'              => 'Engenharia de Produção',
        'ENG. CIVIL'                    => 'Engenharia Civil',
    ];
}

/** Turnos: código ou nome do TOTVS -> nome na Central. */
function totvsMapaTurnos(): array {
    return [
        'M' => 'Manhã',  'MANHA'    => 'Manhã',
        'T' => 'Tarde',  'TARDE'    => 'Tarde',
        'N' => 'Noite',  'NOITE'    => 'Noite',
        'I' => 'Integral', 'INTEGRAL' => 'Integral',
    ];
}

/** Situação da matrícula: TOTVS -> Central. O que não estiver aqui é mantido como veio. */
function totvsMapaSituacao(): array {
    return [
        'ATIVO'       => 'Regular',
        'MATRICULADO' => 'Regular',
        'REGULAR'     => 'Regular',
        'TRANCADO'    => 'Trancado',
        'FORMADO'     => 'Formado',
        'CANCELADO'   => 'Cancelado',
    ];
}

/* -----------------------------------------------------------------------------
 * Funções de apoio
 * -------------------------------------------------------------------------- */

/**
 * Deixa um texto "comparável": sem acento, em maiúsculas e só com letras,
 * números e espaço. Ex.: "Análise e Desenv." -> "ANALISE E DESENV"
 */
function totvsNormalizar(string $texto): string {
    $acentos = [
        'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a', 'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i', 'ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u', 'ç'=>'c','ñ'=>'n',
    ];
    $t = mb_strtolower(trim($texto), 'UTF-8');
    $t = strtr($t, $acentos);
    $t = preg_replace('/[^a-z0-9]+/', ' ', $t);
    return strtoupper(trim($t));
}

/**
 * Pega o valor de um campo da linha do TOTVS, sem ligar para maiúsculas.
 * Ex.: totvsCampo($linha, 'ra') procura a coluna "RA", "ra" ou "Ra".
 */
function totvsCampo(array $linha, string $campoCentral): string {
    $colunaTotvs = totvsMapaCampos()[$campoCentral] ?? '';
    if ($colunaTotvs === '') return '';

    foreach ($linha as $coluna => $valor) {
        if (strcasecmp((string) $coluna, $colunaTotvs) === 0) {
            return trim((string) $valor);
        }
    }
    return '';
}

/** Traduz o curso. Se não reconhecer, devolve como veio (a tela avisa o admin). */
function totvsConverterCurso(string $cursoTotvs): string {
    if ($cursoTotvs === '') return '';
    $chave = totvsNormalizar($cursoTotvs);

    // 1) Procura na tabela de nomes diferentes
    foreach (totvsMapaCursos() as $nomeTotvs => $nomeCentral) {
        if (totvsNormalizar($nomeTotvs) === $chave) return $nomeCentral;
    }

    // 2) Procura na lista oficial de cursos da Central (getFsaCourses, no config.php)
    if (function_exists('getFsaCourses')) {
        foreach (getFsaCourses() as $cursoCentral) {
            if (totvsNormalizar($cursoCentral) === $chave) return $cursoCentral;
        }
    }

    // 3) Não achou: mantém o original
    return $cursoTotvs;
}

/** "5" vira "5º Período". Se já vier escrito, mantém. */
function totvsConverterPeriodo(string $periodo): string {
    if ($periodo === '') return '';
    return ctype_digit($periodo) ? ((int) $periodo) . 'º Período' : $periodo;
}

/** "N" vira "Noite", "MANHA" vira "Manhã" e assim por diante. */
function totvsConverterTurno(string $turno): string {
    if ($turno === '') return '';
    return totvsMapaTurnos()[totvsNormalizar($turno)] ?? $turno;
}

function totvsConverterSituacao(string $situacao): string {
    if ($situacao === '') return '';
    return totvsMapaSituacao()[totvsNormalizar($situacao)] ?? $situacao;
}

/* -----------------------------------------------------------------------------
 * Função principal deste arquivo
 * -------------------------------------------------------------------------- */

/**
 * Converte UMA linha do TOTVS num aluno no formato da Central.
 * Devolve null se a linha não tiver nem RA nem CPF (não dá para saber quem é).
 */
function totvsConverterLinha(array $linha): ?array {
    $ra  = totvsCampo($linha, 'ra');
    $cpf = preg_replace('/\D/', '', totvsCampo($linha, 'cpf'));   // só números

    // O TOTVS às vezes manda o CPF como número e perde o zero da frente
    if ($cpf !== '' && strlen($cpf) < 11) {
        $cpf = str_pad($cpf, 11, '0', STR_PAD_LEFT);
    }

    if ($ra === '' && $cpf === '') {
        return null;
    }

    // E-mail: minúsculo e só se for válido
    $email = strtolower(totvsCampo($linha, 'email'));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = '';
    }

    return [
        'ra'       => $ra,
        'cpf'      => $cpf,
        'nome'     => totvsCampo($linha, 'nome'),
        'email'    => $email,
        'curso'    => totvsConverterCurso(totvsCampo($linha, 'curso')),
        'periodo'  => totvsConverterPeriodo(totvsCampo($linha, 'periodo')),
        'turno'    => totvsConverterTurno(totvsCampo($linha, 'turno')),
        'situacao' => totvsConverterSituacao(totvsCampo($linha, 'situacao')),
    ];
}
