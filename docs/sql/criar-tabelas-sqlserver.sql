-- =============================================================================
-- Central de Estágios FSA — tabelas do banco (SQL Server)
-- -----------------------------------------------------------------------------
-- Uma tabela por aba/integração, todas no mesmo formato (chave, conteudo, atualizado_em).
-- Os nomes são os padrões de includes/banco-tabelas.php. Se você trocou algum
-- nome (no arquivo ou com DADOS_TABELA_<GRUPO>), troque aqui também.
-- Rodar este script é OPCIONAL: com DADOS_CRIAR_TABELAS=1 (padrão) a Central
-- cria as tabelas sozinha na primeira conexão.
-- =============================================================================

-- usuarios: Login, Perfil, Gestão de Alunos, Gestão de Egressos, Cadastro de Alunos (TOTVS)
IF OBJECT_ID(N'central_usuarios', N'U') IS NULL
CREATE TABLE central_usuarios (
    chave         NVARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      NVARCHAR(MAX) NOT NULL,
    atualizado_em VARCHAR(19)   NOT NULL
);
GO

-- perfis: Perfil do aluno e do egresso (foto, dados extras)
IF OBJECT_ID(N'central_perfis', N'U') IS NULL
CREATE TABLE central_perfis (
    chave         NVARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      NVARCHAR(MAX) NOT NULL,
    atualizado_em VARCHAR(19)   NOT NULL
);
GO

-- curriculos: Currículo
IF OBJECT_ID(N'central_curriculos', N'U') IS NULL
CREATE TABLE central_curriculos (
    chave         NVARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      NVARCHAR(MAX) NOT NULL,
    atualizado_em VARCHAR(19)   NOT NULL
);
GO

-- certificados: Documentos (certificados, arquivados e lixeira)
IF OBJECT_ID(N'central_certificados', N'U') IS NULL
CREATE TABLE central_certificados (
    chave         NVARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      NVARCHAR(MAX) NOT NULL,
    atualizado_em VARCHAR(19)   NOT NULL
);
GO

-- perfil_competencias: Perfil por Competências (resultado do app de IA)
IF OBJECT_ID(N'central_perfil_competencias', N'U') IS NULL
CREATE TABLE central_perfil_competencias (
    chave         NVARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      NVARCHAR(MAX) NOT NULL,
    atualizado_em VARCHAR(19)   NOT NULL
);
GO

-- dicas: Dicas de Carreira (trilhas concluídas)
IF OBJECT_ID(N'central_dicas_progresso', N'U') IS NULL
CREATE TABLE central_dicas_progresso (
    chave         NVARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      NVARCHAR(MAX) NOT NULL,
    atualizado_em VARCHAR(19)   NOT NULL
);
GO

-- vagas: Vagas, Admin Vagas (vagas da FSA e externas aprovadas)
IF OBJECT_ID(N'central_vagas', N'U') IS NULL
CREATE TABLE central_vagas (
    chave         NVARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      NVARCHAR(MAX) NOT NULL,
    atualizado_em VARCHAR(19)   NOT NULL
);
GO

-- vagas_sugeridas: Vagas Sugeridas (externas: Gupy / Adzuna)
IF OBJECT_ID(N'central_vagas_sugeridas', N'U') IS NULL
CREATE TABLE central_vagas_sugeridas (
    chave         NVARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      NVARCHAR(MAX) NOT NULL,
    atualizado_em VARCHAR(19)   NOT NULL
);
GO

-- solicitacoes_vaga: Solicitar Vaga (aluno) e aprovação em Admin Vagas
IF OBJECT_ID(N'central_solicitacoes_vaga', N'U') IS NULL
CREATE TABLE central_solicitacoes_vaga (
    chave         NVARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      NVARCHAR(MAX) NOT NULL,
    atualizado_em VARCHAR(19)   NOT NULL
);
GO

-- vagas_egressos: Divulgar Vaga (egresso) e Gestão de Egressos > Vagas
IF OBJECT_ID(N'central_vagas_egressos', N'U') IS NULL
CREATE TABLE central_vagas_egressos (
    chave         NVARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      NVARCHAR(MAX) NOT NULL,
    atualizado_em VARCHAR(19)   NOT NULL
);
GO

-- candidaturas: Minhas Vagas > Candidaturas, Funil
IF OBJECT_ID(N'central_candidaturas', N'U') IS NULL
CREATE TABLE central_candidaturas (
    chave         NVARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      NVARCHAR(MAX) NOT NULL,
    atualizado_em VARCHAR(19)   NOT NULL
);
GO

-- favoritos: Minhas Vagas > Salvas
IF OBJECT_ID(N'central_favoritos', N'U') IS NULL
CREATE TABLE central_favoritos (
    chave         NVARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      NVARCHAR(MAX) NOT NULL,
    atualizado_em VARCHAR(19)   NOT NULL
);
GO

-- posts: Mural / Admin Posts
IF OBJECT_ID(N'central_posts', N'U') IS NULL
CREATE TABLE central_posts (
    chave         NVARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      NVARCHAR(MAX) NOT NULL,
    atualizado_em VARCHAR(19)   NOT NULL
);
GO

-- assinaturas: Assinaturas (aluno) e Admin Assinaturas
IF OBJECT_ID(N'central_assinaturas', N'U') IS NULL
CREATE TABLE central_assinaturas (
    chave         NVARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      NVARCHAR(MAX) NOT NULL,
    atualizado_em VARCHAR(19)   NOT NULL
);
GO

-- mentoria: Mentoria: pedidos e chat
IF OBJECT_ID(N'central_mentoria', N'U') IS NULL
CREATE TABLE central_mentoria (
    chave         NVARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      NVARCHAR(MAX) NOT NULL,
    atualizado_em VARCHAR(19)   NOT NULL
);
GO

-- denuncias: Mentoria > Denúncias
IF OBJECT_ID(N'central_mentoria_denuncias', N'U') IS NULL
CREATE TABLE central_mentoria_denuncias (
    chave         NVARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      NVARCHAR(MAX) NOT NULL,
    atualizado_em VARCHAR(19)   NOT NULL
);
GO

-- empresas: Empresas parceiras (telas desativadas no menu)
IF OBJECT_ID(N'central_empresas', N'U') IS NULL
CREATE TABLE central_empresas (
    chave         NVARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      NVARCHAR(MAX) NOT NULL,
    atualizado_em VARCHAR(19)   NOT NULL
);
GO

-- cursos: Regras de estágio obrigatório e horas por curso
IF OBJECT_ID(N'central_cursos', N'U') IS NULL
CREATE TABLE central_cursos (
    chave         NVARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      NVARCHAR(MAX) NOT NULL,
    atualizado_em VARCHAR(19)   NOT NULL
);
GO

-- alertas: Alertas e E-mails (fila e resumo semanal)
IF OBJECT_ID(N'central_alertas', N'U') IS NULL
CREATE TABLE central_alertas (
    chave         NVARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      NVARCHAR(MAX) NOT NULL,
    atualizado_em VARCHAR(19)   NOT NULL
);
GO

-- paineis: Painéis e Relatórios (pastas, painéis, relatórios, planilhas e consultas)
IF OBJECT_ID(N'central_paineis', N'U') IS NULL
CREATE TABLE central_paineis (
    chave         NVARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      NVARCHAR(MAX) NOT NULL,
    atualizado_em VARCHAR(19)   NOT NULL
);
GO

-- log_admin: Ações dos funcionários (justificativas, downloads de conversas...)
IF OBJECT_ID(N'central_log_admin', N'U') IS NULL
CREATE TABLE central_log_admin (
    chave         NVARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      NVARCHAR(MAX) NOT NULL,
    atualizado_em VARCHAR(19)   NOT NULL
);
GO

-- log_acessos: Logins (engajamento)
IF OBJECT_ID(N'central_log_acessos', N'U') IS NULL
CREATE TABLE central_log_acessos (
    chave         NVARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      NVARCHAR(MAX) NOT NULL,
    atualizado_em VARCHAR(19)   NOT NULL
);
GO

-- totvs: Integração TOTVS (última sincronização e arquivo de teste)
IF OBJECT_ID(N'central_totvs', N'U') IS NULL
CREATE TABLE central_totvs (
    chave         NVARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      NVARCHAR(MAX) NOT NULL,
    atualizado_em VARCHAR(19)   NOT NULL
);
GO

-- geral: Qualquer dado que não está nos grupos acima (e o controle interno da migração)
IF OBJECT_ID(N'central_dados', N'U') IS NULL
CREATE TABLE central_dados (
    chave         NVARCHAR(255) NOT NULL PRIMARY KEY,
    conteudo      NVARCHAR(MAX) NOT NULL,
    atualizado_em VARCHAR(19)   NOT NULL
);
GO
