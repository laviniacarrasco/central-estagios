# Versão 12: painel por tabela ou por relatório

**Este zip já tem tudo das versões 7 a 12.** Copie para a raiz, aceitando substituir. Não precisa mexer no `.env`.

- **Novo painel > Conectar o painel a:** uma tabela, um relatório, um modelo pronto ou em branco. Com tabela ou relatório, o painel já abre com visuais montados.
- **Fontes separadas em "Tabelas" e "Relatórios"** no editor dos painéis e no "Adicionar visual" da Visão geral dos alunos.
- **TOTVS como tabela direta** ("TOTVS: alunos"), além de continuar como origem de relatório.

Alterados: `admin-paineis.php`, `includes/bi.php`, `includes/painel-editavel.php`, `docs/PAINEIS-BI.md`.

## Para testar
1. Painéis > Novo painel > **Uma tabela** > "TOTVS: alunos". Deve abrir com cartão, gráfico por curso e tabela.
2. Crie um relatório (aba Relatórios), salve, e faça Novo painel > **Um relatório** > escolha o relatório.
3. Dentro de um painel em edição, abra um visual: a fonte aparece em dois grupos, Tabelas e Relatórios.
