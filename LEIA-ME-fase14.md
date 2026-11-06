# Versão 14: os JSON são a reserva de tudo

Este zip é o **projeto completo** (sem o `.env` e sem a `.git`). Copie o conteúdo da pasta `central-estagios` por cima da sua, aceitando substituir.

## O que mudou
1. **Reserva do TOTVS e do banco / data lake.** Toda leitura que dá certo guarda uma cópia em `data/reserva/`. Se eles não responderem, os Painéis e Relatórios usam a cópia e mostram um aviso amarelo com a data dela.
2. **A sincronização de alunos não usa a cópia**, para nunca gravar dados velhos no cadastro.
3. **CPF não vai para a cópia.**
4. **Tela Banco de dados:** novo quadro "Reserva das integrações".
5. **`.gitignore`:** todos os `data/**/*.json` vão para o git e para o Render, inclusive `data/reserva/` e o `usuarios_backup_totvs.json` (que antes ficava de fora). Só `data/_sistema/` continua fora.
6. **`render.yaml`** agora sobe com o `router.php`.

Alterados: `includes/reserva-externa.php` (novo), `includes/config.php`, `includes/banco-tabelas.php`, `includes/integracoes/totvs-cliente.php`, `includes/bi.php`, `admin-paineis.php`, `includes/painel-editavel.php`, `admin-banco.php`, `.gitignore`, `.env.example`, `render.yaml`, `docs/BANCO-DE-DADOS.md`.

## Para testar
1. Com `TOTVS_MODO=banco` (ou `real`) funcionando, abra um painel com a tabela "TOTVS: alunos". Em **Banco de dados > Reserva das integrações** aparece a cópia `totvs_alunos`.
2. Coloque uma senha errada em `TOTVS_DB_SENHA` (ou `TOTVS_SENHA`) e abra o painel de novo: os dados aparecem, com o aviso amarelo no canto da tela.
3. Em **Cadastro de Alunos (TOTVS) > Simular**, deve aparecer o erro (a sincronização não usa a cópia).
4. Volte a senha certa.
