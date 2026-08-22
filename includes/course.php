<?php
/**
 * =============================================================================
 * CONFIGURACAO DE CURSOS: ESTAGIO OBRIGATORIO E HORAS COMPLEMENTARES
 * -----------------------------------------------------------------------------
 * Cada curso da FSA tem regras diferentes:
 *  - alguns EXIGEM estagio obrigatorio (com uma carga horaria minima propria
 *    e um periodo minimo em que o aluno pode comecar a estagiar)
 *  - todos exigem uma quantidade de horas complementares (atividades, cursos,
 *    certificados) para formatura, mas essa quantidade varia por curso.
 *
 * Este arquivo centraliza essa configuracao em data/course.json.
 * Se o arquivo nao existir ainda, ele e criado automaticamente com os
 * valores oficiais informados pela coordenacao (tabela de Estagio
 * Obrigatorio). Cursos que nao exigem estagio obrigatorio devem seguir as
 * orientacoes de Estagio Nao Obrigatorio.
 * ============================================================================
 */

define('COURSE_PATH', DATA_PATH . 'course.json');

/**
 * Seed inicial com os dados OFICIAIS da tabela de Estagio Obrigatorio da
 * coordenacao. Os campos:
 *  - estagioObrigatorio: se o curso exige estagio obrigatorio para formar
 *  - horasEstagio: carga horaria minima de estagio obrigatorio exigida
 *  - horasComplementares: carga horaria de atividades complementares
 *    exigida para formatura (AJUSTAR conforme grade real de cada curso;
 *    a tabela oficial fornecida cobria apenas estagio obrigatorio)
 *  - periodo_inicio: a partir de qual periodo/semestre o aluno pode
 *    comecar a estagiar nesse curso
 *  - observacao: cursos marcados com "*" na tabela oficial possuem alguma
 *    regra especial que deve ser confirmada com a coordenacao
 */
function seedCursosConfig(): array {
    return [
        'Administração' => [
            'estagioObrigatorio'  => true,
            'horasEstagio'        => 300,
            'horasComplementares' => 120,
            'periodo_inicio'      => 1,
            'observacao'          => '',
        ],
        'Arquitetura e Urbanismo' => [
            'estagioObrigatorio'  => true,
            'horasEstagio'        => 360,
            'horasComplementares' => 150,
            'periodo_inicio'      => 3,
            'observacao'          => '',
        ],
        'Biomedicina' => [
            'estagioObrigatorio'  => true,
            'horasEstagio'        => 700,
            'horasComplementares' => 150,
            'periodo_inicio'      => 6,
            'observacao'          => 'Confirmar regra específica com a coordenação (curso sinalizado com * na tabela oficial).',
        ],
        'Ciências Biológicas' => [
            'estagioObrigatorio'  => true,
            'horasEstagio'        => 360,
            'horasComplementares' => 120,
            'periodo_inicio'      => 2,
            'observacao'          => '',
        ],
        'Ciências Contábeis' => [
            'estagioObrigatorio'  => true,
            'horasEstagio'        => 300,
            'horasComplementares' => 120,
            'periodo_inicio'      => 1,
            'observacao'          => '',
        ],
        'Direito' => [
            'estagioObrigatorio'  => true,
            'horasEstagio'        => 200,
            'horasComplementares' => 100,
            'periodo_inicio'      => 7,
            'observacao'          => '',
        ],
        'Engenharias' => [
            'estagioObrigatorio'  => true,
            'horasEstagio'        => 160,
            'horasComplementares' => 150,
            'periodo_inicio'      => 5,
            'observacao'          => 'Regra geral para os cursos de Engenharia. Duplicado abaixo para cada habilitação específica.',
        ],
        'Engenharia Civil' => [
            'estagioObrigatorio'  => true,
            'horasEstagio'        => 160,
            'horasComplementares' => 150,
            'periodo_inicio'      => 5,
            'observacao'          => '',
        ],
        'Engenharia de Produção' => [
            'estagioObrigatorio'  => true,
            'horasEstagio'        => 160,
            'horasComplementares' => 150,
            'periodo_inicio'      => 5,
            'observacao'          => '',
        ],
        'Pedagogia' => [
            'estagioObrigatorio'  => true,
            'horasEstagio'        => 400,
            'horasComplementares' => 200,
            'periodo_inicio'      => 5,
            'observacao'          => 'Confirmar regra específica com a coordenação (curso sinalizado com * na tabela oficial).',
        ],
        'Psicologia' => [
            'estagioObrigatorio'  => true,
            'horasEstagio'        => 620,
            'horasComplementares' => 200,
            'periodo_inicio'      => 4,
            'observacao'          => 'Confirmar regra específica com a coordenação (curso sinalizado com * na tabela oficial).',
        ],
        'Química' => [
            'estagioObrigatorio'  => true,
            'horasEstagio'        => 160,
            'horasComplementares' => 120,
            'periodo_inicio'      => 5,
            'observacao'          => '',
        ],

        /* -------------------------------------------------------------
         * Cursos que NAO exigem estagio obrigatorio (seguem as
         * orientacoes de Estagio Nao Obrigatorio). Mantidos aqui apenas
         * para que a plataforma saiba a carga de horas complementares
         * de cada um -- ajuste os valores conforme a grade real.
         * ------------------------------------------------------------- */
        'Ciência da Computação' => [
            'estagioObrigatorio'  => false,
            'horasEstagio'        => 0,
            'horasComplementares' => 120,
            'periodo_inicio'      => null,
            'observacao'          => '',
        ],
        'Sistemas de Informação' => [
            'estagioObrigatorio'  => false,
            'horasEstagio'        => 0,
            'horasComplementares' => 120,
            'periodo_inicio'      => null,
            'observacao'          => '',
        ],
        'Análise e Desenvolvimento de Sistemas' => [
            'estagioObrigatorio'  => false,
            'horasEstagio'        => 0,
            'horasComplementares' => 100,
            'periodo_inicio'      => null,
            'observacao'          => '',
        ],
        'Educação Física' => [
            'estagioObrigatorio'  => false,
            'horasEstagio'        => 0,
            'horasComplementares' => 200,
            'periodo_inicio'      => null,
            'observacao'          => '',
        ],
        'Fisioterapia' => [
            'estagioObrigatorio'  => false,
            'horasEstagio'        => 0,
            'horasComplementares' => 200,
            'periodo_inicio'      => null,
            'observacao'          => '',
        ],
        'Jornalismo' => [
            'estagioObrigatorio'  => false,
            'horasEstagio'        => 0,
            'horasComplementares' => 100,
            'periodo_inicio'      => null,
            'observacao'          => '',
        ],
        'Publicidade e Propaganda' => [
            'estagioObrigatorio'  => false,
            'horasEstagio'        => 0,
            'horasComplementares' => 120,
            'periodo_inicio'      => null,
            'observacao'          => '',
        ],
        'Enfermagem' => [
            'estagioObrigatorio'  => false,
            'horasEstagio'        => 0,
            'horasComplementares' => 200,
            'periodo_inicio'      => null,
            'observacao'          => '',
        ],
    ];
}

/**
 * Le (ou cria, se ainda nao existir) o arquivo de configuracao de cursos.
 */
function getCursosConfig(): array {
    if (!file_exists(COURSE_PATH)) {
        $seed = seedCursosConfig();
        $dir = dirname(COURSE_PATH);
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        file_put_contents(COURSE_PATH, json_encode($seed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $seed;
    }
    $conteudo = json_decode(file_get_contents(COURSE_PATH), true);
    return is_array($conteudo) ? $conteudo : [];
}

function salvarCursosConfig(array $config): void {
    file_put_contents(COURSE_PATH, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Retorna a configuracao de um curso especifico. Se o curso nao estiver
 * cadastrado ainda, devolve um padrao "sem estagio obrigatorio".
 */
function getConfigDoCurso(string $curso): array {
    $config = getCursosConfig();
    return $config[$curso] ?? [
        'estagioObrigatorio'  => false,
        'horasEstagio'        => 0,
        'horasComplementares' => 0,
        'periodo_inicio'      => null,
        'observacao'          => '',
    ];
}

function cursoTemEstagioObrigatorio(string $curso): bool {
    return !empty(getConfigDoCurso($curso)['estagioObrigatorio']);
}

/**
 * Lista apenas os cursos que exigem estagio obrigatorio, com base na
 * configuracao salva.
 */
function getCursosComEstagioObrigatorio(): array {
    $config = getCursosConfig();
    $lista = [];
    foreach ($config as $curso => $dados) {
        if (!empty($dados['estagioObrigatorio'])) {
            $lista[] = $curso;
        }
    }
    sort($lista);
    return $lista;
}

/**
 * Retorna o periodo minimo (numero do semestre) a partir do qual o aluno
 * pode iniciar o estagio obrigatorio do seu curso. Retorna null se o
 * curso nao tiver essa regra definida (ex: nao exige estagio obrigatorio).
 */
function getPeriodoInicioDoCurso(string $curso): ?int {
    $cfg = getConfigDoCurso($curso);
    return $cfg['periodo_inicio'] !== null ? intval($cfg['periodo_inicio']) : null;
}

/**
 * Retorna a observacao/regra especial cadastrada para o curso (usada nos
 * cursos marcados com "*" na tabela oficial da coordenacao).
 */
function getObservacaoDoCurso(string $curso): string {
    return trim(getConfigDoCurso($curso)['observacao'] ?? '');
}
