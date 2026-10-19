# Fase 2 - Alertas por curso, Vagas para voce, Estagio, Relatorios

Aplique POR CIMA da fase 1 (copie para a raiz da Central mantendo as pastas).

NOVOS (raiz): alertas.php, admin-alertas.php, resumo-semanal.php, vagas-para-voce.php,
              estagio.php, admin-relatorios.php
NOVOS (includes/): alertas.php, matching.php, relatorios.php
ALTERADOS: includes/config.php, includes/header.php, admin-jobs.php, admin-egressos.php

## Variavel nova (agendamento externo)
ALERTAS_CRON_KEY=uma-chave-longa-e-aleatoria     (no .env local / Environment do Render)
Depois, em cron-job.org (ou similar), chame a cada 15-30 min:
  https://SEU-SITE/resumo-semanal.php?chave=SUA_CHAVE
A URL pronta tambem aparece em Admin > Alertas e E-mails.
Sem agendador, o resumo sai quando alguem abre o portal (a partir de segunda 08:00).

## Alertas sao opt-in
O aluno ativa em "Meus Alertas" (icone de sino). Para ja nascerem ativos, troque
ALERTAS_PADRAO_ATIVO para true em includes/alertas.php.

## PDF real (opcional)
Sem fazer nada, o botao PDF abre a versao de impressao (Salvar como PDF no navegador).
Para baixar PDF direto: composer require dompdf/dompdf
O Excel (.xlsx) usa a extensao zip do PHP; sem ela, baixa CSV.

## Teste rapido
1. Aluno: Meus Alertas > marque as duas opcoes > "Enviar um resumo de teste agora".
2. Admin: crie uma vaga para o curso do aluno > Admin > Alertas e E-mails > "Enviar parte da fila".
3. Aluno: Vagas para Voce, Meu Estagio.
4. Admin-egressos: Relatorios de Egressos > Excel e PDF.
