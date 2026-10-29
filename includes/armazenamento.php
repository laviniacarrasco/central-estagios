<?php
/**
 * =============================================================================
 * ARMAZENAMENTO — onde os dados da Central ficam guardados
 * -----------------------------------------------------------------------------
 * TODO o sistema lê e grava por aqui (loadData/saveData no config.php chamam
 * estas funções). Por isso as telas não sabem se o dado está no banco ou no
 * arquivo JSON: quem decide é este arquivo.
 *
 * DADOS_DRIVER
 *   json   -> só arquivos data/<chave>.json (como sempre foi)
 *   banco  -> banco de dados PRIMEIRO, com os JSON como reserva   (igual a "auto")
 *   auto   -> igual a "banco". É o padrão quando DADOS_DB_DSN está preenchido.
 *
 * Como funciona no modo banco/auto:
 *   1. LER:    lê do banco. Se a chave ainda não está no banco mas existe o
 *              JSON, usa o JSON e já copia para o banco (migração automática).
 *   2. GRAVAR: grava no banco e TAMBÉM no JSON (espelho), para o JSON estar
 *              sempre em dia caso o banco caia. Desligue com DADOS_ESPELHO_JSON=0.
 *   3. BANCO FORA DO AR (ou não encontrado): lê e grava nos JSON, e anota a
 *              chave em data/_sistema/pendentes.json.
 *   4. BANCO VOLTOU: na primeira página que conseguir conectar, as chaves
 *              pendentes são enviadas para o banco (sincronização). Se alguém
 *              mudou a mesma chave no banco depois, o banco vence e a versão do
 *              JSON vai para data/_sistema/conflitos/ (nada se perde).
 *   5. PRIMEIRA CONEXÃO: copia para o banco tudo o que existe em data/ e
 *              ainda não está no banco (não sobrescreve o que já está lá).
 *
 * Para não deixar cada página lenta quando o banco está fora, depois de uma
 * falha a Central só tenta de novo depois de DADOS_DB_TENTAR_DE_NOVO segundos
 * (padrão 30), e cada tentativa espera no máximo DADOS_DB_TIMEOUT segundos
 * (padrão 3).
 *
 * Em qual TABELA cada dado fica: includes/banco-tabelas.php
 * Tela de controle: admin-banco.php (menu "Banco de dados")
 * Terminal:         php ferramentas/migrar-dados.php status
 * Documentação:     docs/BANCO-DE-DADOS.md
 *
 * A "chave" é o nome do arquivo sem o .json, por exemplo:
 *   'usuarios', 'platform_jobs', 'users/12/userCurriculum', 'bi/paineis'
 * =============================================================================
 */

require_once __DIR__ . '/integracoes/banco-conexao.php';
require_once __DIR__ . '/banco-tabelas.php';

const DADOS_PASTA_SISTEMA = '_sistema';   // data/_sistema/ (controle interno, fora do git)

/* ===========================================================================
 * MODO E ESTADO DA CONEXÃO
 * ======================================================================== */

/** 'json' ou 'banco' */
function dadosDriver(): string {
    $d = strtolower(bancoEnv('DADOS_DRIVER', ''));
    if ($d === 'json') return 'json';
    if (in_array($d, ['banco', 'auto'], true)) return bancoConfigurado('DADOS_DB') ? 'banco' : 'json';
    return bancoConfigurado('DADOS_DB') ? 'banco' : 'json';   // sem DADOS_DRIVER: banco se houver DSN
}

/** Arquivo (na pasta temporária) que lembra a última falha, para não tentar a cada página */
function dadosEstadoArquivo(): string {
    return rtrim(sys_get_temp_dir(), '/\\') . '/central_banco_' . md5(bancoEnv('DADOS_DB_DSN')) . '.json';
}
function dadosEstadoLer(): array {
    $d = @json_decode((string) @file_get_contents(dadosEstadoArquivo()), true);
    return is_array($d) ? $d : [];
}
function dadosEstadoGravar(array $mudancas): void {
    @file_put_contents(dadosEstadoArquivo(), json_encode(array_merge(dadosEstadoLer(), $mudancas)), LOCK_EX);
}

/** Último erro de conexão (para a tela de status) */
function dadosUltimoErro(): string { return (string) (dadosEstadoLer()['erro'] ?? ''); }

/**
 * O banco está disponível agora? Conecta uma vez por página.
 * Quando volta depois de uma falha, sincroniza o que ficou pendente.
 */
function dadosBancoDisponivel(bool $forcar = false): bool {
    static $ok = null;
    if ($ok !== null && !$forcar) return $ok;
    if (dadosDriver() !== 'banco') return $ok = false;

    $estado = dadosEstadoLer();
    $espera = max(5, (int) bancoEnv('DADOS_DB_TENTAR_DE_NOVO', '30'));
    if (!$forcar && !empty($estado['falhouEm']) && (time() - (int) $estado['falhouEm']) < $espera) {
        return $ok = false;                         // falhou há pouco: nem tenta (página não fica lenta)
    }
    try {
        bancoConectar('DADOS_DB');
        $voltou = !empty($estado['falhouEm']);
        dadosEstadoGravar(['falhouEm' => 0, 'erro' => '', 'ultimoOk' => time()]);
        $ok = true;
        if ($voltou || dadosPendentesContar() > 0) dadosSincronizarPendentes();
        dadosMigracaoInicial();
        return true;
    } catch (Throwable $e) {
        dadosMarcarFalha($e);
        return $ok = false;
    }
}

function dadosMarcarFalha(Throwable $e): void {
    error_log('Banco da Central indisponível, usando os JSON: ' . $e->getMessage());
    dadosEstadoGravar(['falhouEm' => time(), 'erro' => mb_substr($e->getMessage(), 0, 300)]);
}

/* ===========================================================================
 * FUNÇÕES PÚBLICAS (use estas)
 * ======================================================================== */

/** Limpa a chave: só letras, números, _, - e / (nunca "..") */
function dadosChaveLimpa(string $chave): string {
    $chave = preg_replace('/[^A-Za-z0-9_\-\/]/', '', $chave);
    $chave = str_replace('..', '', (string) $chave);
    return trim($chave, '/');
}

/** Guarda o que já foi lido nesta página (o painel lê muita coisa repetida) */
function &dadosCache(): array { static $c = []; return $c; }

/** Lê os dados de uma chave. Se não existir, devolve []. */
function dadosLer(string $chave): array {
    $chave = dadosChaveLimpa($chave);
    $cache = &dadosCache();
    if (array_key_exists($chave, $cache)) return $cache[$chave];

    if (dadosBancoDisponivel()) {
        try {
            $r = dadosBancoLerRaw($chave);
            if ($r !== null) return $cache[$chave] = $r;
            // Ainda não está no banco: usa o JSON e já leva para o banco
            if (is_file(dadosJsonCaminho($chave))) {
                $d = dadosJsonLer($chave);
                dadosBancoGravar($chave, $d);
                return $cache[$chave] = $d;
            }
            return $cache[$chave] = [];
        } catch (Throwable $e) {
            dadosMarcarFalha($e);
        }
    }
    return $cache[$chave] = dadosJsonLer($chave);
}

/** Grava os dados de uma chave (cria se não existir). */
function dadosGravar(string $chave, $dados): void {
    $chave = dadosChaveLimpa($chave);
    $cache = &dadosCache();
    unset($cache[$chave]);

    if (dadosBancoDisponivel()) {
        try {
            dadosBancoGravar($chave, $dados);
            if (dadosEspelhar()) dadosJsonGravar($chave, $dados);
            return;
        } catch (Throwable $e) {
            dadosMarcarFalha($e);
        }
    }
    dadosJsonGravar($chave, $dados);
    if (dadosDriver() === 'banco') dadosPendenteMarcar($chave);    // banco fora: envia quando voltar
}

/** A chave existe? */
function dadosExiste(string $chave): bool {
    $chave = dadosChaveLimpa($chave);
    if (dadosBancoDisponivel()) {
        try {
            if (dadosBancoLerRaw($chave) !== null) return true;
        } catch (Throwable $e) {
            dadosMarcarFalha($e);
        }
    }
    return is_file(dadosJsonCaminho($chave));
}

/**
 * Lê, altera e grava SEM risco de duas pessoas sobrescreverem uma à outra
 * (transação no banco, trava de arquivo no JSON).
 *
 * Uso:
 *   $r = dadosOperar('mentoria_pedidos', function (array &$lista) {
 *       $lista[] = [...];
 *       return 'ok';
 *   });
 */
function dadosOperar(string $chave, callable $fn) {
    $chave = dadosChaveLimpa($chave);
    $cache = &dadosCache();
    unset($cache[$chave]);

    if (dadosBancoDisponivel()) {
        try {
            [$resultado, $dados] = dadosBancoOperar($chave, $fn);
            if (dadosEspelhar()) dadosJsonGravar($chave, $dados);
            return $resultado;
        } catch (Throwable $e) {
            dadosMarcarFalha($e);                   // a transação foi desfeita: refaz no JSON
        }
    }
    $r = dadosJsonOperar($chave, $fn);
    if (dadosDriver() === 'banco') dadosPendenteMarcar($chave);
    return $r;
}

/** Espelhar as gravações no JSON? (padrão: sim) */
function dadosEspelhar(): bool {
    return bancoEnv('DADOS_ESPELHO_JSON', '1') !== '0';
}

/* ===========================================================================
 * ARQUIVOS JSON (data/)
 * ======================================================================== */

function dadosJsonCaminho(string $chave): string {
    return DATA_PATH . $chave . '.json';
}

function dadosJsonLer(string $chave): array {
    $caminho = dadosJsonCaminho($chave);
    if (!is_file($caminho)) return [];
    $d = json_decode((string) @file_get_contents($caminho), true);
    return is_array($d) ? $d : [];
}

/** Grava de forma segura: escreve num arquivo temporário e troca (nunca deixa o JSON pela metade) */
function dadosJsonGravar(string $chave, $dados): void {
    $caminho = dadosJsonCaminho($chave);
    if (!is_dir(dirname($caminho))) @mkdir(dirname($caminho), 0775, true);
    $tmp = $caminho . '.tmp' . getmypid();
    $json = json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) { error_log('dadosJsonGravar(' . $chave . '): dados inválidos'); return; }
    if (@file_put_contents($tmp, $json, LOCK_EX) === false || !@rename($tmp, $caminho)) {
        @unlink($tmp);
        file_put_contents($caminho, $json, LOCK_EX);     // plano B (ex.: Windows com o arquivo aberto)
    }
}

function dadosJsonOperar(string $chave, callable $fn) {
    $caminho = dadosJsonCaminho($chave);
    if (!is_dir(dirname($caminho))) @mkdir(dirname($caminho), 0775, true);

    $fp = @fopen($caminho, 'c+');          // abre (ou cria) sem apagar
    if (!$fp) return null;
    flock($fp, LOCK_EX);                    // trava: quem chegar depois espera

    $raw   = stream_get_contents($fp);
    $dados = json_decode($raw !== false && $raw !== '' ? $raw : '[]', true);
    if (!is_array($dados)) $dados = [];

    $resultado = $fn($dados);               // a função altera $dados

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $resultado;
}

/** Lista todas as chaves que existem em data/ (menos o controle interno em data/_sistema). */
function dadosJsonListarChaves(): array {
    $base = realpath(DATA_PATH);
    if ($base === false) return [];
    $chaves = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $arquivo) {
        if (strtolower($arquivo->getExtension()) !== 'json') continue;
        $rel = str_replace('\\', '/', substr($arquivo->getPathname(), strlen($base) + 1));
        if (strpos($rel, DADOS_PASTA_SISTEMA . '/') === 0) continue;
        $chaves[] = substr($rel, 0, -5);   // tira o ".json"
    }
    sort($chaves);
    return $chaves;
}

/* ===========================================================================
 * BANCO DE DADOS (uma tabela por grupo: includes/banco-tabelas.php)
 * ======================================================================== */

/** Conexão com o banco da Central */
function dadosBanco(): PDO {
    return bancoConectar('DADOS_DB');
}

function dadosBancoDriverNome(): string {
    return (string) dadosBanco()->getAttribute(PDO::ATTR_DRIVER_NAME);
}

/** A tabela existe? */
function dadosTabelaExiste(string $tabela): bool {
    try {
        dadosBanco()->query('SELECT 1 FROM ' . $tabela . ' WHERE 1 = 0');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/** Comando CREATE TABLE do formato padrão, para o banco em uso */
function dadosSqlCriarTabela(string $tabela, string $driver): string {
    switch ($driver) {
        case 'mysql':
            return "CREATE TABLE IF NOT EXISTS {$tabela} (
                chave VARCHAR(255) NOT NULL PRIMARY KEY,
                conteudo LONGTEXT NOT NULL,
                atualizado_em VARCHAR(19) NOT NULL
            ) DEFAULT CHARSET=utf8mb4";
        case 'sqlsrv':
        case 'dblib':
            return "IF OBJECT_ID(N'{$tabela}', N'U') IS NULL CREATE TABLE {$tabela} (
                chave NVARCHAR(255) NOT NULL PRIMARY KEY,
                conteudo NVARCHAR(MAX) NOT NULL,
                atualizado_em VARCHAR(19) NOT NULL
            )";
        default:   // pgsql, sqlite
            return "CREATE TABLE IF NOT EXISTS {$tabela} (
                chave VARCHAR(255) NOT NULL PRIMARY KEY,
                conteudo TEXT NOT NULL,
                atualizado_em VARCHAR(19) NOT NULL
            )";
    }
}

/** Garante que a tabela existe (cria se DADOS_CRIAR_TABELAS não for 0). Uma vez por página. */
function dadosGarantirTabela(string $tabela): void {
    static $prontas = [];
    if (isset($prontas[$tabela])) return;
    if (!dadosTabelaExiste($tabela)) {
        if (bancoEnv('DADOS_CRIAR_TABELAS', '1') === '0') {
            throw new RuntimeException("A tabela {$tabela} não existe. Crie com o script de docs/sql/ ou ligue DADOS_CRIAR_TABELAS=1.");
        }
        dadosBanco()->exec(dadosSqlCriarTabela($tabela, dadosBancoDriverNome()));
    }
    $prontas[$tabela] = true;
}

/** Cria todas as tabelas configuradas (botão da tela e ferramenta do terminal) */
function dadosCriarTodasTabelas(): array {
    $r = [];
    foreach (array_unique(array_values(dadosTabelas())) as $t) {
        try {
            $existia = dadosTabelaExiste($t);
            if (!$existia) dadosBanco()->exec(dadosSqlCriarTabela($t, dadosBancoDriverNome()));
            $r[$t] = $existia ? 'já existia' : 'criada';
        } catch (Throwable $e) {
            $r[$t] = 'ERRO: ' . $e->getMessage();
        }
    }
    return $r;
}

/** Lê do banco. null = a chave não está no banco. */
function dadosBancoLerRaw(string $chave): ?array {
    $t = dadosTabela($chave);
    dadosGarantirTabela($t);
    $st = dadosBanco()->prepare("SELECT conteudo FROM {$t} WHERE chave = ?");
    $st->execute([$chave]);
    $raw = $st->fetchColumn();
    if ($raw === false) return null;
    $d = json_decode((string) $raw, true);
    return is_array($d) ? $d : [];
}

function dadosBancoLer(string $chave): array {
    return dadosBancoLerRaw($chave) ?? [];
}

/** Data da última gravação no banco ('' se não existir) */
function dadosBancoAtualizadoEm(string $chave): string {
    $t = dadosTabela($chave);
    dadosGarantirTabela($t);
    $st = dadosBanco()->prepare("SELECT atualizado_em FROM {$t} WHERE chave = ?");
    $st->execute([$chave]);
    return (string) ($st->fetchColumn() ?: '');
}

function dadosBancoGravar(string $chave, $dados): void {
    $t = dadosTabela($chave);
    dadosGarantirTabela($t);
    $pdo   = dadosBanco();
    $json  = json_encode($dados, JSON_UNESCAPED_UNICODE);
    $agora = date('Y-m-d H:i:s');
    // Funciona em MySQL, PostgreSQL, SQLite e SQL Server: tenta UPDATE e, se não havia linha, INSERT
    $up = $pdo->prepare("UPDATE {$t} SET conteudo = ?, atualizado_em = ? WHERE chave = ?");
    $up->execute([$json, $agora, $chave]);
    if ($up->rowCount() === 0) {
        try {
            $pdo->prepare("INSERT INTO {$t} (chave, conteudo, atualizado_em) VALUES (?, ?, ?)")->execute([$chave, $json, $agora]);
        } catch (PDOException $e) {
            // Outra página inseriu ao mesmo tempo (ou o MySQL disse 0 linhas porque o conteúdo era igual): atualiza
            $pdo->prepare("UPDATE {$t} SET conteudo = ?, atualizado_em = ? WHERE chave = ?")->execute([$json, $agora, $chave]);
        }
    }
}

/** Lê-altera-grava numa transação. Devolve [resultado da função, dados gravados]. */
function dadosBancoOperar(string $chave, callable $fn): array {
    $t = dadosTabela($chave);
    dadosGarantirTabela($t);
    $pdo = dadosBanco();
    $driver = dadosBancoDriverNome();

    for ($tentativa = 1; ; $tentativa++) {
        $pdo->beginTransaction();
        try {
            if ($driver === 'sqlsrv' || $driver === 'dblib') {
                $sql = "SELECT conteudo FROM {$t} WITH (UPDLOCK, ROWLOCK) WHERE chave = ?";
            } else {
                $sql = "SELECT conteudo FROM {$t} WHERE chave = ?" . ($driver === 'sqlite' ? '' : ' FOR UPDATE');
            }
            $st = $pdo->prepare($sql);
            $st->execute([$chave]);
            $raw = $st->fetchColumn();
            if ($raw === false && is_file(dadosJsonCaminho($chave))) {
                $dados = dadosJsonLer($chave);              // primeira vez: parte do JSON
            } else {
                $dados = $raw !== false ? json_decode((string) $raw, true) : [];
            }
            if (!is_array($dados)) $dados = [];

            $resultado = $fn($dados);

            dadosBancoGravar($chave, $dados);
            $pdo->commit();
            return [$resultado, $dados];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($tentativa < 2 && $e instanceof PDOException) continue;   // duas gravações ao mesmo tempo: tenta de novo
            throw $e;
        }
    }
}

/** Lista as chaves gravadas no banco (todas as tabelas) */
function dadosBancoListarChaves(): array {
    $chaves = [];
    foreach (array_unique(array_values(dadosTabelas())) as $t) {
        if (!dadosTabelaExiste($t)) continue;
        foreach (dadosBanco()->query("SELECT chave FROM {$t}")->fetchAll(PDO::FETCH_COLUMN) as $c) {
            if (strpos((string) $c, DADOS_PASTA_SISTEMA . '/') === 0) continue;
            $chaves[] = (string) $c;
        }
    }
    $chaves = array_values(array_unique($chaves));
    sort($chaves);
    return $chaves;
}

/** Quantas linhas uma tabela tem (-1 = não existe) */
function dadosTabelaContar(string $tabela): int {
    try {
        return (int) dadosBanco()->query("SELECT COUNT(*) FROM {$tabela}")->fetchColumn();
    } catch (Throwable $e) {
        return -1;
    }
}

/* ===========================================================================
 * PENDENTES (gravado no JSON com o banco fora do ar) E SINCRONIZAÇÃO
 * ======================================================================== */

function dadosSistemaCaminho(string $nome): string {
    $dir = DATA_PATH . DADOS_PASTA_SISTEMA . '/';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir . $nome;
}

/** Abre um arquivo de controle com trava e deixa a função alterar o conteúdo */
function dadosSistemaOperar(string $nome, callable $fn) {
    $fp = @fopen(dadosSistemaCaminho($nome), 'c+');
    if (!$fp) return null;
    flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    $d = json_decode($raw !== false && $raw !== '' ? $raw : '{}', true);
    if (!is_array($d)) $d = [];
    $r = $fn($d);
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fp); flock($fp, LOCK_UN); fclose($fp);
    return $r;
}

function dadosPendenteMarcar(string $chave): void {
    dadosSistemaOperar('pendentes.json', function (array &$p) use ($chave) {
        $p[$chave] = date('Y-m-d H:i:s');
    });
}

function dadosPendentes(): array {
    $f = DATA_PATH . DADOS_PASTA_SISTEMA . '/pendentes.json';
    if (!is_file($f)) return [];
    $d = json_decode((string) @file_get_contents($f), true);
    return is_array($d) ? $d : [];
}

function dadosPendentesContar(): int { return count(dadosPendentes()); }

/**
 * Envia para o banco o que foi gravado nos JSON enquanto ele estava fora.
 * Devolve um resumo: ['enviadas' => n, 'conflitos' => n, 'erros' => n].
 */
function dadosSincronizarPendentes(): array {
    $resumo = ['enviadas' => 0, 'conflitos' => 0, 'erros' => 0];
    $lock = @fopen(dadosSistemaCaminho('sincronizando.lock'), 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return $resumo;      // outra página já está sincronizando
    @set_time_limit(120);
    try {
        foreach (dadosPendentes() as $chave => $quando) {
            try {
                $noBanco = dadosBancoAtualizadoEm($chave);
                if ($noBanco !== '' && strcmp($noBanco, (string) $quando) > 0) {
                    // Alguém mudou no banco DEPOIS: o banco vence, e o JSON vai para conflitos/
                    $arq = dadosSistemaCaminho('conflitos/');
                    if (!is_dir($arq)) @mkdir($arq, 0775, true);
                    @copy(dadosJsonCaminho($chave), $arq . str_replace('/', '__', $chave) . '_' . date('Ymd_His') . '.json');
                    dadosJsonGravar($chave, dadosBancoLer($chave));
                    $resumo['conflitos']++;
                } else {
                    dadosBancoGravar($chave, dadosJsonLer($chave));
                    $resumo['enviadas']++;
                }
                dadosSistemaOperar('pendentes.json', function (array &$p) use ($chave) { unset($p[$chave]); });
            } catch (Throwable $e) {
                $resumo['erros']++;
                error_log('Sincronizar ' . $chave . ': ' . $e->getMessage());
            }
        }
        dadosSistemaOperar('sincronizacao.json', function (array &$s) use ($resumo) {
            $s = ['quando' => date('Y-m-d H:i:s')] + $resumo;
        });
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    return $resumo;
}

/**
 * Primeira conexão com o banco: copia para ele tudo o que existe em data/
 * e ainda não está lá. Roda uma vez só (fica marcado no banco).
 * Desligue com DADOS_MIGRAR_AUTO=0 (aí use a tela ou o terminal).
 */
function dadosMigracaoInicial(bool $forcar = false): ?array {
    if (!$forcar && bancoEnv('DADOS_MIGRAR_AUTO', '1') === '0') return null;
    $estado = dadosEstadoLer();
    if (!$forcar && !empty($estado['migrado'])) return null;
    $marca = DADOS_PASTA_SISTEMA . '/migracao_inicial';
    try {
        if (!$forcar && dadosBancoLerRaw($marca) !== null) { dadosEstadoGravar(['migrado' => true]); return null; }
        dadosBancoGravar($marca, ['inicio' => date('Y-m-d H:i:s')]);     // marca antes: evita duas páginas migrando juntas
        @set_time_limit(300);
        $r = dadosMigrarJsonParaBanco(false);
        dadosBancoGravar($marca, ['inicio' => date('Y-m-d H:i:s')] + $r);
        dadosEstadoGravar(['migrado' => true]);
        return $r;
    } catch (Throwable $e) {
        error_log('Migração inicial: ' . $e->getMessage());
        return null;
    }
}

/**
 * Copia data/*.json para o banco.
 * $sobrescrever = false: só o que ainda não está no banco (seguro).
 * $sobrescrever = true:  o JSON substitui o que está no banco.
 */
function dadosMigrarJsonParaBanco(bool $sobrescrever = false): array {
    $r = ['copiadas' => 0, 'puladas' => 0, 'erros' => 0];
    foreach (dadosJsonListarChaves() as $chave) {
        try {
            if (!$sobrescrever && dadosBancoLerRaw($chave) !== null) { $r['puladas']++; continue; }
            dadosBancoGravar($chave, dadosJsonLer($chave));
            $r['copiadas']++;
        } catch (Throwable $e) {
            $r['erros']++;
            error_log('Migrar ' . $chave . ': ' . $e->getMessage());
        }
    }
    return $r;
}

/** Copia o banco para data/*.json (para levar ao git ou ter uma cópia local) */
function dadosExportarBancoParaJson(): array {
    $r = ['copiadas' => 0, 'erros' => 0];
    foreach (dadosBancoListarChaves() as $chave) {
        try {
            dadosJsonGravar($chave, dadosBancoLer($chave));
            $r['copiadas']++;
        } catch (Throwable $e) {
            $r['erros']++;
        }
    }
    return $r;
}

/** Compara banco e JSON. Devolve [chave => 'só no banco' | 'só no JSON' | 'diferente'] */
function dadosConferir(): array {
    $json = dadosJsonListarChaves();
    $banco = dadosBancoListarChaves();
    $dif = [];
    foreach (array_unique(array_merge($json, $banco)) as $chave) {
        $a = in_array($chave, $json, true)  ? dadosJsonLer($chave)  : null;
        $b = in_array($chave, $banco, true) ? dadosBancoLer($chave) : null;
        if ($a === null)      $dif[$chave] = 'só no banco';
        elseif ($b === null)  $dif[$chave] = 'só no JSON';
        elseif ($a != $b)     $dif[$chave] = 'diferente';
    }
    ksort($dif);
    return $dif;
}

/** Resumo para a tela "Banco de dados" e para o terminal */
function dadosStatus(): array {
    $s = [
        'driver'      => dadosDriver(),
        'configurado' => bancoConfigurado('DADOS_DB'),
        'conectado'   => false,
        'banco'       => '',
        'erro'        => '',
        'pendentes'   => dadosPendentesContar(),
        'sincronizacao' => is_file(DATA_PATH . DADOS_PASTA_SISTEMA . '/sincronizacao.json')
            ? (array) json_decode((string) file_get_contents(DATA_PATH . DADOS_PASTA_SISTEMA . '/sincronizacao.json'), true) : [],
        'tabelas'     => [],
    ];
    if ($s['driver'] === 'banco') {
        $s['conectado'] = dadosBancoDisponivel(true);
        $s['erro'] = $s['conectado'] ? '' : dadosUltimoErro();
        if ($s['conectado']) $s['banco'] = dadosBancoDriverNome();
    }
    $cfg = dadosTabelasConfig();
    foreach (dadosTabelas() as $grupo => $tabela) {
        $s['tabelas'][] = [
            'grupo'  => $grupo,
            'aba'    => $cfg[$grupo]['aba'],
            'chaves' => $cfg[$grupo]['chaves'],
            'tabela' => $tabela,
            'daEnv'  => bancoEnv('DADOS_TABELA_' . strtoupper($grupo)) !== '',
            'linhas' => $s['conectado'] ? dadosTabelaContar($tabela) : null,
        ];
    }
    return $s;
}
