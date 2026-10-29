# Versão 13: banco de dados em todas as abas + JSON protegidos

Este zip é o **projeto completo** (sem o `.env` e sem a pasta `.git`). Copie por cima da sua pasta `central-estagios`, aceitando substituir. **Não apague a sua pasta antes:** a `.git` e o `.env` ficam nela.

## O que mudou
1. **Todas as abas usam o banco**, cada uma na sua tabela (`includes/banco-tabelas.php`). Só falta você colocar os nomes das suas tabelas (ou usar os que já vêm).
2. **Banco caiu ou não foi encontrado?** A Central usa e grava nos JSON. Quando ele volta, sincroniza sozinha.
3. **Primeira conexão:** copia o `data/` para o banco (sem sobrescrever nada).
4. **Espelho:** toda gravação no banco vai também para o JSON (que vai para o git).
5. **`.gitignore`:** agora todos os JSON vão para o git. Só `data/_sistema/` fica de fora (explicado no arquivo).
6. **JSON protegidos:** `router.php` (Render e `php -S`) e `.htaccess` (Apache) bloqueiam o download de `data/`, `.env`, `docs/`...
7. **Tela "Banco de dados"** (menu dos funcionários): situação, tabela de cada aba, pendentes, integrações e botões.
8. **TOTVS por tabela/view:** `TOTVS_DB_TABELA` (ou `TOTVS_DB_CONSULTA`).
9. **Proteger senhas:** botão na tela ou `php ferramentas/proteger-senhas.php`.

## IMPORTANTE: como rodar no computador agora
```
php -S localhost:8000 router.php
```

## Para testar
1. Sem banco configurado: tudo continua igual (só JSON). Abra `http://localhost:8000/data/usuarios.json`: tem que aparecer "Acesso negado.".
2. Preencha `DADOS_DB_DSN`, usuário e senha (pode testar com SQLite: `DADOS_DB_DSN=sqlite:C:/central/teste.db`). Abra o site e veja o menu **Banco de dados**.
3. Desligue o banco (ou coloque uma senha errada), use o site, e volte a senha certa: os pendentes vão para o banco.
4. Clique em **Proteger senhas (hash)**.
5. Depois do deploy, abra `https://central-estagios-fsa.onrender.com/data/usuarios.json`: tem que aparecer "Acesso negado.".

Tudo explicado em `docs/BANCO-DE-DADOS.md`.
