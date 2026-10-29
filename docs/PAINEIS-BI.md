# Painéis

Tela única de indicadores para os funcionários (menu **Painéis**), no estilo do Salesforce: pastas à esquerda, lista de painéis à direita e um botão para criar painel. Cada painel funciona como o Power BI ou o SmartView: cartões, gráficos e filtros, todos editáveis.

Ela substitui quatro telas que existiam antes:

| Antes | Agora |
| --- | --- |
| Dashboard Admin | Painel **Visão geral dos alunos**, na pasta **Alunos**. Ele abre a tela original, **exatamente como era** (`admin-dashboard.php`), com filtros, cartões, gráficos e a tabela de estágio obrigatório |
| Painéis BI | A própria tela de Painéis. O painel que cada um tinha montado foi para **Meus painéis particulares**, com o nome "Meu painel (versão anterior)" |
| Funil e Indicadores | Painel **Funil de candidaturas**, na pasta **Vagas e Funil** |
| Aba Painel do Painel de Egressos | Painel **Empregabilidade dos egressos**, na pasta **Egressos** |
| Relatórios de Egressos | Aba **Relatórios** da tela de Painéis (o Excel/PDF continua igual) |

O que **não** é painel continua onde estava:
- **Gestão de Egressos** (antigo Painel de Egressos): moderação das vagas, lista de egressos e cadastro.

## Tela inicial

- **Pastas fixas:** Alunos, Vagas e Funil, Egressos (esta só aparece para a Coordenação de Egressos).
- **Atalhos:** Todos os painéis, Criados por mim e **Meus painéis particulares** (só quem criou vê).
- **Nova pasta:** para todos os funcionários ou só para a Coordenação de Egressos. Pastas criadas podem ser renomeadas e apagadas (precisam estar vazias).
- **Novo painel:** nome, descrição, pasta e "Começar com": em branco ou cópia de um modelo (alunos, funil ou egressos).
- Em cada painel da lista: **Propriedades** (renomear, mudar de pasta, excluir), **Duplicar** e **Excluir**.

## Dentro de um painel

- **Segmentação** por curso e por período (últimos 30 dias, 3, 6 ou 12 meses). O período vale para os dados que têm mês ou dia: candidaturas, vagas, assinaturas, acessos, mentoria e ações dos funcionários.
- Clicar numa barra ou fatia **filtra os outros visuais** (clique no X para tirar).
- **Editar painel** abre o painel lateral de Visualizações: tipo (cartão, barras, colunas, linha, pizza, rosca, tabela ou **funil**), fonte, eixo, valor (contagem, contagem distinta, soma, média ou %), até 2 filtros, ordem, Top N, largura e cor. Arraste os visuais para reordenar. Tudo é salvo sozinho.
- **Restaurar padrão** (só nos painéis que vieram de um modelo), **Duplicar**, **Imprimir / PDF** e o botão de baixar os dados de cada visual em CSV.

### O visual de funil

As etapas seguem a ordem alfabética do eixo, por isso a fonte Candidaturas tem o campo **Etapa do funil** com "1. Candidatura", "2. Em análise", "3. Entrevista" e "4. Contratação". Com "Contagem", cada etapa soma também as seguintes (quem chegou à entrevista também passou pela análise), e a tela mostra a % sobre a etapa anterior.

## Fontes de dados

| Fonte | O que tem | Quem vê |
| --- | --- | --- |
| Alunos | curso, período, turno, situação, status profissional, empregabilidade, tem currículo, nº de certificados | Funcionários |
| Candidaturas | curso, empresa, andamento, origem, **etapa do funil**, **sem retorno há 14+ dias**, mês | Funcionários |
| Vagas | origem (FSA, externa, egresso), empresa, cidade, modalidade, situação, bolsa, mês | Funcionários |
| Assinaturas | documento, situação, área, curso, dias até assinar, mês | Funcionários |
| Certificados | título, categoria, curso, horas, situação | Funcionários |
| Acessos | dia, dia da semana (últimos 90 dias) | Funcionários |
| Mentoria | assunto, situação, curso, mensagens no chat, mês | Funcionários |
| Ações dos funcionários | funcionário, ação, dia, mês | Funcionários |
| **Egressos** | curso, ano de conclusão, situação, grupo, ocupado, empresa atual, meses até a 1ª colocação, aceita mentoria, perfil desatualizado | Só Coordenação de Egressos |
| **Vagas de egressos** | situação, categoria, egresso que divulgou, empresa, candidaturas geradas, mês | Só Coordenação de Egressos |

As fontes de alunos **não trazem nome, CPF nem e-mail**. As de egressos trazem o nome da empresa e, nas vagas, o nome do egresso que divulgou (como já aparecia no antigo painel). Um painel que usa as fontes de egressos fica escondido para quem não é da Coordenação de Egressos, mesmo que esteja numa pasta compartilhada.

Em **Fontes de dados** (modo edição) dá para acrescentar planilhas (Excel/CSV, até 5000 linhas) e consultas SQL (`BI_SQL_DSN`, `BI_SQL_USUARIO`, `BI_SQL_SENHA`). Elas ficam disponíveis em todos os painéis.

## Como funciona por dentro

| Arquivo | Papel |
| --- | --- |
| `admin-paineis.php` | A tela: pastas, lista, painel e editor. As contas são feitas no navegador (Chart.js) |
| `bi-dados.php` | Serviço em JSON: pastas, painéis, fontes e o salvamento |
| `includes/bi.php` | As fontes, os 3 modelos (`biPainelPadrao`, `biModeloFunil`, `biModeloEgressos`) e as regras de quem vê cada pasta e painel |
| `data/bi/pastas.json` | As pastas |
| `data/bi/paineis.json` | Os painéis (nome, pasta, autor, datas e visuais) |
| `data/bi/fontes.json` | Planilhas e consultas SQL criadas |

Na primeira vez que alguém abre a tela, o sistema cria as 3 pastas e os 3 painéis prontos. Para começar do zero, apague `data/bi/pastas.json` e `data/bi/paineis.json`.

**Fonte nova da Central:** escreva uma função `biFonteAlgo()` no `includes/bi.php` (devolvendo nome, campos e linhas) e coloque em `biListaFontes()` e em `biCarregarFonte()`.

**Modelo novo:** escreva `biModeloAlgo()` (uma lista de `biVisual(...)`), coloque em `biModelos()` e em `biModeloVisuais()`.

**Atenção no Render gratuito:** painéis criados ou alterados direto no site publicado podem sumir no próximo deploy, como qualquer dado gravado lá. Monte os painéis no seu computador e faça o `git push`.

## Exportar para Excel (botão direito)

Em **todos os painéis** (inclusive a Visão geral dos alunos), clique com o botão direito num bloco:

| Opção | O que baixa |
| --- | --- |
| Exportar este bloco para Excel | O que o bloco mostra: a tabela, os valores do gráfico, a lista ou o número |
| Exportar as linhas detalhadas | Só nos painéis editáveis: as linhas da fonte, já com os filtros do visual e da segmentação |
| Exportar o painel inteiro | Um arquivo .xlsx com uma aba por bloco |

O ícone verde do Excel, no canto de cada visual, faz a mesma coisa que a primeira opção. Tudo fica em `includes/exportar-excel.php`; para usar numa tela nova, basta incluir esse arquivo antes do footer.

## Painéis com tela própria

O painel **Visão geral dos alunos** não usa o editor: ele abre a tela `admin-dashboard.php`, exatamente como ela é. Para ter uma versão editável, use **Duplicar**: a cópia vira um painel comum, com os visuais do modelo. A lista desses painéis fica em `biPaginasFixas()` (`includes/bi.php`).

## Painel "Visão geral dos alunos" editável

A tela continua exatamente como foi feita, e o botão **Editar painel** (no topo) permite:
- mudar a ordem dos blocos (arrastar ou usar as setas; cada bloco se move dentro da sua grade);
- esconder e mostrar blocos (no modo edição eles aparecem apagados);
- renomear o título de um bloco;
- mudar a largura (1 a 4 colunas);
- **Adicionar visual**: cartão, colunas, barras, linha, pizza, rosca ou tabela, de qualquer fonte (Central, relatórios do TOTVS, planilhas, banco/data lake).

A arrumação fica no painel (`data/bi/paineis.json`, campo `layout`) e vale para todos os funcionários. **Restaurar padrão** volta à tela original. Código: `includes/painel-editavel.php`.

## Relatórios (estilo Salesforce)

Menu **Painéis > aba Relatórios > Novo relatório**. Todo relatório precisa de uma **origem**:

| Origem | De onde vêm os dados |
| --- | --- |
| TOTVS | Alunos do sistema acadêmico, já traduzidos (RA, nome, e-mail, curso, período, turno, situação, se tem cadastro na Central e o status profissional). Segue o `TOTVS_MODO` (teste, real ou banco). O CPF não entra |
| Planilha Excel | Uma planilha importada (.xlsx, .xls ou .csv, até 5000 linhas) |
| Banco / data lake | Uma consulta SELECT no banco configurado em `BI_SQL_DSN` (MySQL, PostgreSQL, SQL Server ou ODBC) |

Depois: **colunas**, **filtros** (é igual a, diferente, contém, não contém, começa com, maior, menor, vazio, não vazio), **agrupar por** com resumo (contagem, distinta, soma, média, com subtotal por grupo), **ordem** e **limite**. A prévia atualiza sozinha.

- **Salvar** guarda em `data/bi/relatorios.json`, na pasta escolhida (as mesmas pastas dos painéis; "particular" só você vê).
- **Exportar Excel** (ou botão direito na prévia) leva todas as linhas.
- **Criar painel com este relatório** monta um painel com cartão, gráfico e tabela, já usando o relatório como fonte.
- Todo relatório salvo aparece como fonte **"Relatório: nome"** nos painéis e no "Adicionar visual" da Visão geral dos alunos. Os painéis usam as linhas já filtradas do relatório.

## Painel conectado a uma tabela ou a um relatório

Ao clicar em **Novo painel**, escolha **Conectar o painel a**:

| Opção | O que acontece |
| --- | --- |
| Uma tabela | Lê a tabela direto: dados da Central (alunos, vagas, candidaturas...), **TOTVS: alunos**, uma planilha ou uma consulta SQL |
| Um relatório | Usa as linhas já filtradas de um relatório salvo |
| Um modelo pronto | Cópia editável do painel de alunos, do funil ou dos egressos |
| Em branco | Sem visuais; a fonte é escolhida em cada visual |

Com tabela ou relatório, o painel já abre em edição com um cartão (total), um gráfico de colunas, uma linha no tempo (se houver data) e uma tabela. Dentro do editor, a lista de fontes aparece separada em **Tabelas** e **Relatórios**; cada visual pode usar uma fonte diferente.

A tabela **TOTVS: alunos** segue o `TOTVS_MODO` (teste lê `data/totvs_exemplo.json`; `banco` lê o banco do TOTVS). Não traz CPF.
