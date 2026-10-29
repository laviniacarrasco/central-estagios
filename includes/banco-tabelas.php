<?php
/**
 * =============================================================================
 * TABELAS DO BANCO — uma tabela para cada aba / integração
 * -----------------------------------------------------------------------------
 * ESTE É O ÚNICO ARQUIVO QUE VOCÊ PRECISA MEXER PARA ESCOLHER AS TABELAS.
 *
 * Cada "grupo" junta os dados de uma aba (ou integração) e diz em qual tabela
 * do banco eles ficam. Os nomes abaixo são sugestões e já funcionam: se a
 * tabela não existir, a Central cria sozinha (DADOS_CRIAR_TABELAS=1).
 *
 * Para usar outro nome, você tem DOIS jeitos (escolha um):
 *   1) trocar o 'tabela' aqui embaixo, ou
 *   2) criar a variável de ambiente DADOS_TABELA_<GRUPO>
 *      ex.: DADOS_TABELA_USUARIOS=fsa_central.usuarios
 *      (a variável tem prioridade sobre este arquivo)
 *
 * Nome aceito: letras, números, "_" e um "." opcional para o schema
 * (ex.: "central.vagas" no PostgreSQL ou "dbo.central_vagas" no SQL Server).
 *
 * Formato de TODAS as tabelas (o mesmo para todos os grupos):
 *   chave          VARCHAR(255)  chave primária   (ex.: "usuarios", "users/12/userCurriculum")
 *   conteudo       texto longo   os dados em JSON (LONGTEXT / TEXT / NVARCHAR(MAX))
 *   atualizado_em  VARCHAR(19)   "AAAA-MM-DD HH:MM:SS" da última gravação
 * Os comandos para criar à mão estão em docs/sql/.
 *
 * "chaves" = quais dados entram no grupo. O "*" vale qualquer coisa
 * (ex.: "users/ * /userCurriculum" pega o currículo de todos os alunos).
 * Dado que não se encaixa em nenhum grupo vai para o grupo "geral".
 * =============================================================================
 */

function dadosTabelasConfig(): array {
    return [
        /* ----------------------------- PESSOAS ----------------------------- */
        'usuarios' => [
            'tabela' => 'central_usuarios',
            'aba'    => 'Login, Perfil, Gestão de Alunos, Gestão de Egressos, Cadastro de Alunos (TOTVS)',
            'chaves' => ['usuarios', 'usuarios_backup_totvs'],
        ],
        'perfis' => [
            'tabela' => 'central_perfis',
            'aba'    => 'Perfil do aluno e do egresso (foto, dados extras)',
            'chaves' => ['users/*/userProfile'],
        ],
        'curriculos' => [
            'tabela' => 'central_curriculos',
            'aba'    => 'Currículo',
            'chaves' => ['users/*/userCurriculum'],
        ],
        'certificados' => [
            'tabela' => 'central_certificados',
            'aba'    => 'Documentos (certificados, arquivados e lixeira)',
            'chaves' => ['users/*/userCertificates', 'users/*/userCertificatesArchive', 'users/*/userCertificatesTrash'],
        ],
        'perfil_competencias' => [
            'tabela' => 'central_perfil_competencias',
            'aba'    => 'Perfil por Competências (resultado do app de IA)',
            'chaves' => ['users/*/perfilResultado'],
        ],
        'dicas' => [
            'tabela' => 'central_dicas_progresso',
            'aba'    => 'Dicas de Carreira (trilhas concluídas)',
            'chaves' => ['users/*/dicasProgresso'],
        ],

        /* ------------------------------ VAGAS ------------------------------ */
        'vagas' => [
            'tabela' => 'central_vagas',
            'aba'    => 'Vagas, Admin Vagas (vagas da FSA e externas aprovadas)',
            'chaves' => ['platform_jobs'],
        ],
        'vagas_sugeridas' => [
            'tabela' => 'central_vagas_sugeridas',
            'aba'    => 'Vagas Sugeridas (externas: Gupy / Adzuna)',
            'chaves' => ['vagas_sugeridas'],
        ],
        'solicitacoes_vaga' => [
            'tabela' => 'central_solicitacoes_vaga',
            'aba'    => 'Solicitar Vaga (aluno) e aprovação em Admin Vagas',
            'chaves' => ['job_requests'],
        ],
        'vagas_egressos' => [
            'tabela' => 'central_vagas_egressos',
            'aba'    => 'Divulgar Vaga (egresso) e Gestão de Egressos > Vagas',
            'chaves' => ['egresso_jobs'],
        ],
        'candidaturas' => [
            'tabela' => 'central_candidaturas',
            'aba'    => 'Minhas Vagas > Candidaturas, Funil',
            'chaves' => ['users/*/userCandidaturas'],
        ],
        'favoritos' => [
            'tabela' => 'central_favoritos',
            'aba'    => 'Minhas Vagas > Salvas',
            'chaves' => ['users/*/userFavoritos'],
        ],

        /* ---------------------------- COMUNIDADE --------------------------- */
        'posts' => [
            'tabela' => 'central_posts',
            'aba'    => 'Mural / Admin Posts',
            'chaves' => ['platform_posts'],
        ],
        'assinaturas' => [
            'tabela' => 'central_assinaturas',
            'aba'    => 'Assinaturas (aluno) e Admin Assinaturas',
            'chaves' => ['assinaturas'],
        ],
        'mentoria' => [
            'tabela' => 'central_mentoria',
            'aba'    => 'Mentoria: pedidos e chat',
            'chaves' => ['mentoria_pedidos'],
        ],
        'denuncias' => [
            'tabela' => 'central_mentoria_denuncias',
            'aba'    => 'Mentoria > Denúncias',
            'chaves' => ['mentoria_denuncias'],
        ],
        'empresas' => [
            'tabela' => 'central_empresas',
            'aba'    => 'Empresas parceiras (telas desativadas no menu)',
            'chaves' => ['empresas_parceiras'],
        ],

        /* --------------------------- CONFIGURAÇÕES -------------------------- */
        'cursos' => [
            'tabela' => 'central_cursos',
            'aba'    => 'Regras de estágio obrigatório e horas por curso',
            'chaves' => ['course'],
        ],
        'alertas' => [
            'tabela' => 'central_alertas',
            'aba'    => 'Alertas e E-mails (fila e resumo semanal)',
            'chaves' => ['fila_emails', 'alertas_estado'],
        ],
        'paineis' => [
            'tabela' => 'central_paineis',
            'aba'    => 'Painéis e Relatórios (pastas, painéis, relatórios, planilhas e consultas)',
            'chaves' => ['bi/*'],
        ],

        /* ------------------------------- LOGS ------------------------------- */
        'log_admin' => [
            'tabela' => 'central_log_admin',
            'aba'    => 'Ações dos funcionários (justificativas, downloads de conversas...)',
            'chaves' => ['admin_log'],
        ],
        'log_acessos' => [
            'tabela' => 'central_log_acessos',
            'aba'    => 'Logins (engajamento)',
            'chaves' => ['acessos_log'],
        ],

        /* ---------------------------- INTEGRAÇÕES --------------------------- */
        'totvs' => [
            'tabela' => 'central_totvs',
            'aba'    => 'Integração TOTVS (última sincronização e arquivo de teste)',
            'chaves' => ['totvs_ultima_sincronizacao', 'totvs_exemplo'],
        ],

        /* ------------------------------ O RESTO ----------------------------- */
        'geral' => [
            'tabela' => 'central_dados',
            'aba'    => 'Qualquer dado que não está nos grupos acima (e o controle interno da migração)',
            'chaves' => [],
        ],
    ];
}

/** Nome válido de tabela (com schema opcional). Devolve '' se for inválido. */
function dadosNomeTabelaValido(string $nome): string {
    $nome = trim($nome);
    return preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,62}(\.[A-Za-z_][A-Za-z0-9_]{0,62})?$/', $nome) ? $nome : '';
}

/** Grupo => nome da tabela (já com as variáveis DADOS_TABELA_* aplicadas) */
function dadosTabelas(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    foreach (dadosTabelasConfig() as $grupo => $cfg) {
        $env  = bancoEnv('DADOS_TABELA_' . strtoupper($grupo));
        $nome = dadosNomeTabelaValido($env !== '' ? $env : (string) $cfg['tabela']);
        if ($nome === '') {
            error_log("DADOS_TABELA_{$grupo}: nome de tabela inválido, usando central_dados.");
            $nome = 'central_dados';
        }
        $cache[$grupo] = $nome;
    }
    return $cache;
}

/** Em qual grupo uma chave cai */
function dadosGrupoDaChave(string $chave): string {
    static $cache = [];
    if (isset($cache[$chave])) return $cache[$chave];
    foreach (dadosTabelasConfig() as $grupo => $cfg) {
        foreach ((array) $cfg['chaves'] as $padrao) {
            $re = '#^' . str_replace('\*', '[^/]+', preg_quote($padrao, '#')) . '$#';
            if (preg_match($re, $chave)) return $cache[$chave] = $grupo;
        }
    }
    return $cache[$chave] = 'geral';
}

/** Em qual tabela uma chave fica */
function dadosTabela(string $chave): string {
    return dadosTabelas()[dadosGrupoDaChave($chave)];
}
