# Egressos + Candidaturas - como aplicar

Copie os arquivos para a raiz do projeto da Central, mantendo as pastas.
Arquivos NOVOS: includes/egressos.php, egressos.php, egresso-perfil.php,
egresso-publicar.php, admin-egressos.php, candidaturas.php, candidatar.php.
Arquivos ALTERADOS (substituem os atuais): includes/config.php, includes/header.php,
index.php, jobs.php, admin-dashboard.php.

## Criar o admin-egressos
No data/usuarios.json, no usuario admin que vai cuidar dos egressos, acrescente:
    "tipo": "admin_egressos"
(mantenha "is_admin": true). Ele precisa sair e entrar de novo.
Se a Central roda no Render, faca isso pelo ambiente onde o data/ realmente fica.

## Criar egressos
Entre como admin-egressos > Painel de Egressos > aba Cadastro:
  - "Converter aluno formado": pelo RA, o aluno vira egresso e mantem a senha.
  - "Cadastrar egresso novo": cria login com CPF e senha provisoria.

## Antes de subir
Teste local com php -S localhost:8000 (nao consegui rodar PHP aqui).
