# Comece por aqui

Este pacote é o projeto completo da Central de Estágios FSA, já com:
- a **arquitetura de perfis** (`includes/perfis.php`): alunos, professores, egressos e funcionários;
- o **aluno restrito** e o **professor** de teste;
- as vagas e os selos de **egressos em azul FSA**;
- a **integração TOTVS** em modo teste (`includes/integracoes/` e `admin-totvs.php`);
- a **documentação** e os **diagramas** na pasta `docs/`.

## O que ler primeiro

| Arquivo | Para quê |
| --- | --- |
| `docs/DOCUMENTACAO.md` | Explicação geral do projeto: telas, fluxos, dados e como mexer |
| `docs/ARQUITETURA.md` | Como o código está organizado e como liberar telas para cada perfil |
| `docs/INTEGRACAO-TOTVS.md` | A integração com o sistema acadêmico e o que pedir para a TI |
| `docs/MELHORIAS.md` | Ideias para as próximas versões |
| `docs/diagramas/` | Imagens para o Miro (`miro-*.png`) e o código Mermaid |

## Para rodar

1. Copie o `.env.example` para `.env` e preencha (o `TOTVS_MODO` já vem como `teste`).
2. `composer install`
3. `php -S localhost:8000 router.php`

## Usuários de teste

| CPF | Senha | Perfil |
| --- | --- | --- |
| 333.333.333-33 | Restrito@2026 | Aluno restrito |
| 444.444.444-44 | Professor@2026 | Professor |

O egresso (9002), a Coordenação (2) e o admin de egressos (9001) continuam com as senhas que você já usa.

## O que mudou em relação ao ZIP anterior

- Novos: `includes/perfis.php`, `includes/integracoes/` (4 arquivos), `admin-totvs.php`, `data/totvs_exemplo.json`, `.env.example`, `COMECE-AQUI.md` e `docs/`.
- Alterados: `includes/config.php`, `includes/header.php`, `index.php`, `dashboard.php`, `jobs.php`, `egressos.php`, `profile.php`, `career-tips.php`, `vagas-para-voce.php`, `includes/vagas.php`, `data/usuarios.json` (professor de teste) e `.gitignore`.
- Apagados: `empresas.php`, `admin-empresas.php`, `includes/empresas.php` e `saida.html`.
- Fora do ZIP de propósito: o `.env` (tem as suas chaves) e a pasta `.git`.

## Novidades da versão 3

- **Vagas numa aba só:** `jobs.php` mostra vagas da FSA, externas e de egressos no layout de cartões, com cores e filtros. A aba "Vagas de Egressos" saiu do menu; `egressos.php` só redireciona.
- **Chat na mentoria:** aluno e mentor conversam dentro da Central depois do aceite (`mentoria-chat.php`). Mensagem nova acende a bolinha no ícone Mentoria.
- **Cadastro em massa pelo banco de dados** (tela Integração TOTVS, modo `banco`). A importação por planilha foi desativada.
- **Dados em banco (opcional):** `DADOS_DRIVER=banco` e a ferramenta `ferramentas/migrar-dados.php`.
- Saiu "Adicionar vaga manualmente" das Vagas Sugeridas (isso fica em Admin Vagas).

## Novidades da versão 4

- **Dicas de Carreira nova:** abas (Meu plano, Meu perfil, Trilhas e modelos, Entrevista, Recursos), checklist com progresso que se marca sozinho e "próximo passo".
- **Painéis BI** (estilo Power BI): mesmos indicadores do Dashboard Admin, editáveis, com filtro cruzado e fontes da Central, Excel/CSV e SQL. Veja `docs/PAINEIS-BI.md`.
- **Menu dos funcionários:** "Importar Alunos" e "Integração TOTVS" viraram um item só, **Cadastro de Alunos (TOTVS)**.
- Migração para banco explicada em `docs/MIGRACAO-BANCO.md`.
