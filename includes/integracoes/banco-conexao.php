<?php
/**
 * =============================================================================
 * CONEXÃO COM BANCO DE DADOS (PDO) — usada por várias partes da Central
 * -----------------------------------------------------------------------------
 * Um único jeito de abrir conexão com qualquer banco SQL. Cada parte do sistema
 * usa um PREFIXO diferente nas variáveis de ambiente:
 *
 *   DADOS_DB_*   -> onde a Central guarda os próprios dados (includes/armazenamento.php)
 *   TOTVS_DB_*   -> banco do TOTVS, para o cadastro de alunos em massa
 *   BI_SQL_*     -> banco ou data lake (com endpoint SQL) usado pelos Painéis BI
 *
 * Para cada prefixo são lidas três variáveis:
 *   PREFIXO_DSN      ex.: mysql:host=10.0.0.5;dbname=central;charset=utf8mb4
 *                         pgsql:host=meu-servidor;port=5432;dbname=central
 *                         sqlite:/var/data/central.db
 *                         sqlsrv:Server=10.0.0.9;Database=CorporeRM   (SQL Server, comum no TOTVS)
 *                         odbc:NomeDaFonteODBC                        (Databricks, Synapse, Athena...)
 *   PREFIXO_USUARIO  usuário do banco (use um usuário SÓ DE LEITURA quando for só consulta)
 *   PREFIXO_SENHA    senha
 *   PREFIXO_TIMEOUT  segundos para conectar (opcional; padrão 3 no DADOS_DB e 10 nos outros)
 *
 * O driver precisa estar instalado no PHP. O Dockerfile já instala MySQL,
 * PostgreSQL e SQLite. SQL Server e ODBC precisam de passos extras (ver docs).
 * =============================================================================
 */

/** Lê uma variável de ambiente (.env no computador, Environment no Render). */
function bancoEnv(string $nome, string $padrao = ''): string {
    $valor = $_ENV[$nome] ?? getenv($nome);
    if ($valor === false || $valor === null || trim((string) $valor) === '') return $padrao;
    return trim((string) $valor);
}

/** Tem DSN configurado para esse prefixo? */
function bancoConfigurado(string $prefixo): bool {
    return bancoEnv($prefixo . '_DSN') !== '';
}

/**
 * Abre (e guarda) a conexão do prefixo informado.
 * Em caso de problema, lança RuntimeException com uma mensagem que a pessoa entende.
 */
function bancoConectar(string $prefixo): PDO {
    static $conexoes = [];
    if (isset($conexoes[$prefixo])) return $conexoes[$prefixo];

    $dsn = bancoEnv($prefixo . '_DSN');
    if ($dsn === '') {
        throw new RuntimeException("Banco não configurado: defina {$prefixo}_DSN.");
    }

    // O driver é a parte antes dos dois pontos: "mysql", "pgsql", "sqlite", "sqlsrv"...
    $driver = strtolower((string) strtok($dsn, ':'));
    if (!in_array($driver, PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException("O PHP deste servidor não tem o driver \"{$driver}\" instalado. Drivers disponíveis: "
            . implode(', ', PDO::getAvailableDrivers()) . '.');
    }

    // Tempo máximo para conectar. O banco da Central usa pouco (3 s): se ele cair,
    // a página não trava e a Central passa a usar os JSON (includes/armazenamento.php).
    $timeout = max(1, (int) bancoEnv($prefixo . '_TIMEOUT', $prefixo === 'DADOS_DB' ? '3' : '10'));
    if ($driver === 'pgsql'  && stripos($dsn, 'connect_timeout') === false) $dsn .= ';connect_timeout=' . $timeout;
    if ($driver === 'sqlsrv' && stripos($dsn, 'LoginTimeout') === false)    $dsn .= ';LoginTimeout=' . $timeout;

    try {
        $pdo = new PDO($dsn, bancoEnv($prefixo . '_USUARIO'), bancoEnv($prefixo . '_SENHA'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // erro vira exceção
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // linhas como array com nome da coluna
            PDO::ATTR_TIMEOUT            => $timeout,                       // não trava a página para sempre
        ]);
    } catch (PDOException $e) {
        // Não mostramos a senha nem o DSN completo na mensagem
        throw new RuntimeException('Não foi possível conectar ao banco (' . $driver . '): ' . $e->getMessage());
    }

    return $conexoes[$prefixo] = $pdo;
}

/**
 * A consulta é só de leitura? Aceita apenas um comando SELECT (ou WITH ... SELECT).
 * É uma proteção extra: o certo é o usuário do banco ter só permissão de leitura.
 */
function bancoConsultaSegura(string $sql): bool {
    $s = trim(preg_replace('/\s+/', ' ', $sql));
    $s = rtrim($s, "; ");
    if (!preg_match('/^(select|with)\b/i', $s)) return false;
    if (strpos($s, ';') !== false) return false;   // um comando só
    return !preg_match('/\b(insert|update|delete|drop|alter|truncate|create|grant|merge|exec|execute)\b/i', $s);
}

/**
 * Roda um SELECT e devolve as linhas (no máximo $limite).
 * Ex.: bancoSelecionar('TOTVS_DB', 'SELECT RA, NOME FROM ...')
 */
function bancoSelecionar(string $prefixo, string $sql, int $limite = 50000): array {
    if (!bancoConsultaSegura($sql)) {
        throw new RuntimeException('Só é permitido um comando SELECT (somente leitura).');
    }
    $stmt = bancoConectar($prefixo)->query($sql);
    $linhas = [];
    while (($linha = $stmt->fetch()) !== false) {
        $linhas[] = $linha;
        if (count($linhas) >= $limite) break;
    }
    return $linhas;
}
