# Fase 1 - Validade das vagas, Favoritos e E-mails de status

Copie para a raiz da Central mantendo as pastas. Os arquivos alterados substituem os atuais.
Estes arquivos ja incluem tudo do pacote anterior (egressos-candidaturas.zip) que voce aplicou:
aplique ESTE por cima.

NOVOS: includes/vagas.php, includes/notificacoes.php, favoritar.php, favoritos.php
ALTERADOS: includes/config.php, includes/header.php, includes/egressos.php, egressos.php,
candidatar.php, egresso-publicar.php, admin-egressos.php, admin-jobs.php, jobs.php, dashboard.php

## Variaveis de ambiente (e-mail)
Ja usadas pelo admin-dashboard: GMAIL_CLIENT_ID, GMAIL_CLIENT_SECRET, GMAIL_REFRESH_TOKEN, GMAIL_SENDER_EMAIL.
Nova (recomendada): APP_URL, ex: http://localhost:8000 (local) ou o endereco real da Central.
Sem as credenciais o sistema funciona normalmente, so nao envia e-mail (o motivo vai para o log).

## Para o admin-egressos receber o aviso de vaga nova
O usuario precisa ter o campo "email" no usuarios.json.
