# Versão 7: Painéis num lugar só

Copie os arquivos deste zip para a raiz da Central, aceitando substituir:

| Arquivo | O que mudou |
| --- | --- |
| `admin-paineis.php` | Nova tela de Painéis: pastas, lista, "Novo painel", "Nova pasta", aba Relatórios, visual de funil e filtro por período |
| `bi-dados.php` | Pastas e painéis compartilhados (antes era um painel por pessoa) |
| `includes/bi.php` | Fontes Egressos, Vagas de egressos e Ações dos funcionários; etapa do funil nas candidaturas; modelos de funil e de egressos |
| `includes/perfis.php` | Menu: "Painéis" no lugar de Painéis BI, Dashboard Admin e Funil; "Gestão de Alunos"; "Gestão de Egressos"; Relatórios saiu do menu |
| `admin-egressos.php` | Sem a aba Painel (vira o painel da pasta Egressos). Ficam Vagas, Egressos e Cadastro |
| `admin-dashboard.php` | Passa a se chamar Gestão de Alunos |
| `admin-funil.php` | Só redireciona para o painel "Funil de candidaturas" |
| `admin-relatorios.php` | Link de volta para Painéis > Relatórios |
| `docs/PAINEIS-BI.md` | Documentação nova |

Não precisa mexer no `.env`.

## Para testar

1. `php -S localhost:8000 router.php` e entre como Coordenação de Egressos.
2. Menu **Painéis**: devem aparecer as pastas Alunos, Vagas e Funil e Egressos, com um painel em cada uma.
3. Abra cada painel e confira os números com as telas antigas.
4. Crie uma pasta, crie um painel em branco nela, acrescente um visual e volte para a lista.
5. Entre como Coordenação de Estágios: a pasta Egressos **não** pode aparecer.
6. Confira se o seu painel antigo apareceu em "Meus painéis particulares".

Depois, `git add .`, `git commit` e `git push`. Os arquivos `data/bi/pastas.json` e `data/bi/paineis.json` são criados na primeira vez que a tela abre; mande para o Git também.
