# Migração para banco de dados

Este assunto agora está completo em **`docs/BANCO-DE-DADOS.md`**:

- uma tabela para cada aba (`includes/banco-tabelas.php`);
- reserva automática nos JSON quando o banco cai, e sincronização quando ele volta;
- migração automática na primeira conexão;
- integrações (TOTVS por tabela/view ou consulta, banco/data lake dos Painéis);
- proteção dos JSON (`router.php`) e das senhas.

Resumo dos comandos:

```bash
php ferramentas/migrar-dados.php status
php ferramentas/migrar-dados.php criar-tabelas
php ferramentas/migrar-dados.php para-banco
php ferramentas/migrar-dados.php conferir
php ferramentas/migrar-dados.php para-json
php ferramentas/proteger-senhas.php
```
