# Versão 11: painel de alunos editável + Relatórios (TOTVS, Excel, banco)

**Este zip já tem tudo das versões 7 a 11.** Copie para a raiz da Central, aceitando substituir. Não precisa mexer no `.env`.

## 1. Visão geral dos alunos: exata e editável
A tela é a mesma de sempre. No topo agora tem **Editar painel**:
- arrastar ou usar as setas para mudar a ordem dos blocos;
- esconder/mostrar, renomear e mudar a largura de cada bloco;
- **Adicionar visual** de qualquer fonte, inclusive relatórios;
- **Restaurar padrão** volta tudo como era.
Continua valendo: **botão direito em qualquer bloco > Exportar para Excel**.

## 2. Relatórios (Painéis > aba Relatórios)
- **Novo relatório**: escolha a origem (obrigatória): **TOTVS**, **planilha Excel** ou **banco de dados / data lake**.
- Escolha colunas, filtros, agrupamento com resumo, ordem e limite. A prévia atualiza sozinha.
- **Salvar**, **Exportar Excel**, **Criar painel com este relatório**, Duplicar e Excluir.
- Relatório salvo vira fonte de painel ("Relatório: nome").

## Arquivos desta versão
Novo: `includes/painel-editavel.php`.
Alterados: `admin-paineis.php`, `bi-dados.php`, `includes/bi.php`, `admin-dashboard.php`, `docs/PAINEIS-BI.md`.
Dados novos: `data/bi/relatorios.json` (criado no primeiro relatório) e o campo `layout` no painel "alunos".

## Para testar
1. Painéis > Alunos > Visão geral dos alunos > **Editar painel**: mova um gráfico, esconda um cartão, renomeie um bloco. Recarregue a página: tem que continuar arrumado.
2. **Adicionar visual** > fonte "Candidaturas", colunas por "Curso do aluno". Clique com o botão direito nele e exporte.
3. Painéis > **Relatórios** > **Novo relatório** > origem **TOTVS** (modo teste lê `data/totvs_exemplo.json`). Filtre "Cadastrado na Central = Não", agrupe por Curso e salve.
4. **Criar painel com este relatório** e confira o painel novo.
5. Teste também com uma planilha (origem Excel).
