-- =============================================================================
-- Central de Estágios FSA — tabelas do banco (MySQL / MariaDB)
-- -----------------------------------------------------------------------------
-- Uma tabela por aba/integração, todas no mesmo formato (chave, conteudo, atualizado_em).
-- Os nomes são os padrões de includes/banco-tabelas.php. Se você trocou algum
-- nome (no arquivo ou com DADOS_TABELA_<GRUPO>), troque aqui também.
-- Rodar este script é OPCIONAL: com DADOS_CRIAR_TABELAS=1 (padrão) a Central
-- cria as tabelas sozinha na primeira conexão.
-- =============================================================================

-- usuarios: Login, Perfil, Gestão de Alunos, Gestão de Egressos, Cadastro de Alunos (TOTVS)
CREATE TABLE IF NOT EXISTS central_usuarios (
    chave         VARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      LONGTEXT     NOT NULL,
    atualizado_em VARCHAR(19)  NOT NULL
) DEFAULT CHARSET=utf8mb4;

-- perfis: Perfil do aluno e do egresso (foto, dados extras)
CREATE TABLE IF NOT EXISTS central_perfis (
    chave         VARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      LONGTEXT     NOT NULL,
    atualizado_em VARCHAR(19)  NOT NULL
) DEFAULT CHARSET=utf8mb4;

-- curriculos: Currículo
CREATE TABLE IF NOT EXISTS central_curriculos (
    chave         VARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      LONGTEXT     NOT NULL,
    atualizado_em VARCHAR(19)  NOT NULL
) DEFAULT CHARSET=utf8mb4;

-- certificados: Documentos (certificados, arquivados e lixeira)
CREATE TABLE IF NOT EXISTS central_certificados (
    chave         VARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      LONGTEXT     NOT NULL,
    atualizado_em VARCHAR(19)  NOT NULL
) DEFAULT CHARSET=utf8mb4;

-- perfil_competencias: Perfil por Competências (resultado do app de IA)
CREATE TABLE IF NOT EXISTS central_perfil_competencias (
    chave         VARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      LONGTEXT     NOT NULL,
    atualizado_em VARCHAR(19)  NOT NULL
) DEFAULT CHARSET=utf8mb4;

-- dicas: Dicas de Carreira (trilhas concluídas)
CREATE TABLE IF NOT EXISTS central_dicas_progresso (
    chave         VARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      LONGTEXT     NOT NULL,
    atualizado_em VARCHAR(19)  NOT NULL
) DEFAULT CHARSET=utf8mb4;

-- vagas: Vagas, Admin Vagas (vagas da FSA e externas aprovadas)
CREATE TABLE IF NOT EXISTS central_vagas (
    chave         VARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      LONGTEXT     NOT NULL,
    atualizado_em VARCHAR(19)  NOT NULL
) DEFAULT CHARSET=utf8mb4;

-- vagas_sugeridas: Vagas Sugeridas (externas: Gupy / Adzuna)
CREATE TABLE IF NOT EXISTS central_vagas_sugeridas (
    chave         VARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      LONGTEXT     NOT NULL,
    atualizado_em VARCHAR(19)  NOT NULL
) DEFAULT CHARSET=utf8mb4;

-- solicitacoes_vaga: Solicitar Vaga (aluno) e aprovação em Admin Vagas
CREATE TABLE IF NOT EXISTS central_solicitacoes_vaga (
    chave         VARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      LONGTEXT     NOT NULL,
    atualizado_em VARCHAR(19)  NOT NULL
) DEFAULT CHARSET=utf8mb4;

-- vagas_egressos: Divulgar Vaga (egresso) e Gestão de Egressos > Vagas
CREATE TABLE IF NOT EXISTS central_vagas_egressos (
    chave         VARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      LONGTEXT     NOT NULL,
    atualizado_em VARCHAR(19)  NOT NULL
) DEFAULT CHARSET=utf8mb4;

-- candidaturas: Minhas Vagas > Candidaturas, Funil
CREATE TABLE IF NOT EXISTS central_candidaturas (
    chave         VARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      LONGTEXT     NOT NULL,
    atualizado_em VARCHAR(19)  NOT NULL
) DEFAULT CHARSET=utf8mb4;

-- favoritos: Minhas Vagas > Salvas
CREATE TABLE IF NOT EXISTS central_favoritos (
    chave         VARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      LONGTEXT     NOT NULL,
    atualizado_em VARCHAR(19)  NOT NULL
) DEFAULT CHARSET=utf8mb4;

-- posts: Mural / Admin Posts
CREATE TABLE IF NOT EXISTS central_posts (
    chave         VARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      LONGTEXT     NOT NULL,
    atualizado_em VARCHAR(19)  NOT NULL
) DEFAULT CHARSET=utf8mb4;

-- assinaturas: Assinaturas (aluno) e Admin Assinaturas
CREATE TABLE IF NOT EXISTS central_assinaturas (
    chave         VARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      LONGTEXT     NOT NULL,
    atualizado_em VARCHAR(19)  NOT NULL
) DEFAULT CHARSET=utf8mb4;

-- mentoria: Mentoria: pedidos e chat
CREATE TABLE IF NOT EXISTS central_mentoria (
    chave         VARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      LONGTEXT     NOT NULL,
    atualizado_em VARCHAR(19)  NOT NULL
) DEFAULT CHARSET=utf8mb4;

-- denuncias: Mentoria > Denúncias
CREATE TABLE IF NOT EXISTS central_mentoria_denuncias (
    chave         VARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      LONGTEXT     NOT NULL,
    atualizado_em VARCHAR(19)  NOT NULL
) DEFAULT CHARSET=utf8mb4;

-- empresas: Empresas parceiras (telas desativadas no menu)
CREATE TABLE IF NOT EXISTS central_empresas (
    chave         VARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      LONGTEXT     NOT NULL,
    atualizado_em VARCHAR(19)  NOT NULL
) DEFAULT CHARSET=utf8mb4;

-- cursos: Regras de estágio obrigatório e horas por curso
CREATE TABLE IF NOT EXISTS central_cursos (
    chave         VARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      LONGTEXT     NOT NULL,
    atualizado_em VARCHAR(19)  NOT NULL
) DEFAULT CHARSET=utf8mb4;

-- alertas: Alertas e E-mails (fila e resumo semanal)
CREATE TABLE IF NOT EXISTS central_alertas (
    chave         VARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      LONGTEXT     NOT NULL,
    atualizado_em VARCHAR(19)  NOT NULL
) DEFAULT CHARSET=utf8mb4;

-- paineis: Painéis e Relatórios (pastas, painéis, relatórios, planilhas e consultas)
CREATE TABLE IF NOT EXISTS central_paineis (
    chave         VARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      LONGTEXT     NOT NULL,
    atualizado_em VARCHAR(19)  NOT NULL
) DEFAULT CHARSET=utf8mb4;

-- log_admin: Ações dos funcionários (justificativas, downloads de conversas...)
CREATE TABLE IF NOT EXISTS central_log_admin (
    chave         VARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      LONGTEXT     NOT NULL,
    atualizado_em VARCHAR(19)  NOT NULL
) DEFAULT CHARSET=utf8mb4;

-- log_acessos: Logins (engajamento)
CREATE TABLE IF NOT EXISTS central_log_acessos (
    chave         VARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      LONGTEXT     NOT NULL,
    atualizado_em VARCHAR(19)  NOT NULL
) DEFAULT CHARSET=utf8mb4;

-- totvs: Integração TOTVS (última sincronização e arquivo de teste)
CREATE TABLE IF NOT EXISTS central_totvs (
    chave         VARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      LONGTEXT     NOT NULL,
    atualizado_em VARCHAR(19)  NOT NULL
) DEFAULT CHARSET=utf8mb4;

-- geral: Qualquer dado que não está nos grupos acima (e o controle interno da migração)
CREATE TABLE IF NOT EXISTS central_dados (
    chave         VARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      LONGTEXT     NOT NULL,
    atualizado_em VARCHAR(19)  NOT NULL
) DEFAULT CHARSET=utf8mb4;
