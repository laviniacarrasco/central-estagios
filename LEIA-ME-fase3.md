# Fase 3 - menu enxuto (Minhas Vagas, alertas no Perfil, sem Meu Estagio)

Aplique POR CIMA do ajuste anterior (copie para a raiz da Central mantendo as pastas).

## NOVOS
minhas-vagas.php, alertas-salvar.php, includes/alertas-card.php

## ALTERADOS
includes/header.php, includes/alertas.php, includes/vagas.php, candidatar.php, favoritar.php,
vagas-para-voce.php, candidaturas.php (agora so redireciona), favoritos.php (agora so redireciona)

## APAGUE na raiz da Central
- alertas.php
- estagio.php

## PASSO MANUAL: alertas dentro do Perfil
Abra profile.php e coloque esta linha logo ANTES do </main> (fim da pagina):

    <?php include 'includes/alertas-card.php'; ?>

Sem essa linha o aluno nao tem onde configurar os alertas.

## Teste rapido
1. Entre como aluno: o menu deve ter 10 icones (sem Candidaturas, Salvas, Meu Estagio e Alertas; com Minhas Vagas).
2. Minhas Vagas: abas Candidaturas e Salvas. Salve uma vaga em Vagas e confira na aba Salvas.
3. Perfil: cartao Meus Alertas no fim. Salve e envie o resumo de teste.
4. Links antigos (candidaturas.php, favoritos.php) devem redirecionar para Minhas Vagas.
