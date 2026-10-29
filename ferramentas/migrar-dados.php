<?php
/**
 * =============================================================================
 * MIGRAÇÃO E CONTROLE DOS DADOS: arquivos JSON  <->  banco de dados
 * -----------------------------------------------------------------------------
 * Roda SÓ pelo terminal (no navegador responde 403). Na pasta do projeto:
 *
 *   php ferramentas/migrar-dados.php status          situação, tabelas e pendentes
 *   php ferramentas/migrar-dados.php criar-tabelas   cria as tabelas que faltam
 *   php ferramentas/migrar-dados.php para-banco      copia data/*.json para o banco (só o que falta)
 *   php ferramentas/migrar-dados.php para-banco --sobrescrever   o JSON substitui o banco
 *   php ferramentas/migrar-dados.php para-json       copia o banco para data/*.json (para o git)
 *   php ferramentas/migrar-dados.php sincronizar     envia o que foi gravado com o banco fora
 *   php ferramentas/migrar-dados.php conferir        compara os dois lados
 *
 * Antes, configure no .env: DADOS_DB_DSN, DADOS_DB_USUARIO, DADOS_DB_SENHA.
 * As mesmas ações existem na tela "Banco de dados" (admin-banco.php).
 * Documentação: docs/BANCO-DE-DADOS.md
 * =============================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Esta ferramenta roda só pelo terminal.\n");
}

require_once __DIR__ . '/../includes/config.php';
@set_time_limit(0);

$acao = $argv[1] ?? 'status';
$sobrescrever = in_array('--sobrescrever', $argv, true);

function linha(string $t = ''): void { echo $t . PHP_EOL; }

if ($acao !== 'status' && !bancoConfigurado('DADOS_DB')) {
    exit("Configure DADOS_DB_DSN (e usuário/senha) no .env antes.\n");
}

try {
    if ($acao !== 'status' && !dadosBancoDisponivel(true)) {
        throw new RuntimeException('Não conectou no banco: ' . dadosUltimoErro());
    }
    switch ($acao) {
        case 'status':
            $s = dadosStatus();
            linha('Modo: ' . ($s['driver'] === 'json' ? 'só JSON' : ($s['conectado'] ? 'BANCO (' . $s['banco'] . ') com espelho nos JSON' : 'banco FORA DO AR -> usando JSON')));
            if ($s['erro']) linha('Erro: ' . $s['erro']);
            linha('Pendentes de enviar ao banco: ' . $s['pendentes']);
            linha();
            foreach ($s['tabelas'] as $t) {
                linha(str_pad($t['grupo'], 22) . str_pad($t['tabela'], 32) . ($t['linhas'] === null ? '' : ($t['linhas'] < 0 ? 'não existe' : $t['linhas'] . ' linha(s)')));
            }
            break;
        case 'criar-tabelas':
            foreach (dadosCriarTodasTabelas() as $t => $r) linha(str_pad($t, 34) . $r);
            break;
        case 'para-banco':
            $r = dadosMigrarJsonParaBanco($sobrescrever);
            linha("Copiadas: {$r['copiadas']}   " . (isset($r['puladas']) ? "Já estavam no banco: {$r['puladas']}   " : '') . "Erros: {$r['erros']}");
            linha('Confira com: php ferramentas/migrar-dados.php conferir');
            break;
        case 'para-json':
            $r = dadosExportarBancoParaJson();
            linha("Arquivos gravados em data/: {$r['copiadas']}   Erros: {$r['erros']}");
            linha('Agora: git add data && git commit -m "Dados" && git push');
            break;
        case 'sincronizar':
            $r = dadosSincronizarPendentes();
            linha("Enviadas: {$r['enviadas']}   Conflitos: {$r['conflitos']} (em data/_sistema/conflitos)   Erros: {$r['erros']}");
            break;
        case 'conferir':
            $d = dadosConferir();
            foreach ($d as $c => $s) linha(str_pad($s, 14) . $c);
            linha($d ? count($d) . ' chave(s) diferente(s).' : 'Banco e JSON estão iguais.');
            break;
        default:
            linha('Uso: php ferramentas/migrar-dados.php status | criar-tabelas | para-banco [--sobrescrever] | para-json | sincronizar | conferir');
    }
} catch (Throwable $e) {
    linha('ERRO: ' . $e->getMessage());
    exit(1);
}
