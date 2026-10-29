# Versão 6: vagas externas pela Gupy (grátis, sem chave)

Copie os arquivos deste zip para a raiz da Central, aceitando substituir:
- includes/vagas-externas.php  (Gupy + Adzuna, escolha pela VAGAS_FONTE)
- admin-sugeridas.php           (textos da tela corrigidos)
- importar-vagas-cron.php       (só o comentário)
- .env.example                  (nova variável VAGAS_FONTE)
- teste-gupy.php                (teste pelo terminal; não abre pelo navegador)

Apague o GupyFonte.php e o teste_gupy.php antigos (não são mais usados).

## Configurar
No .env e no Render (Environment):
    VAGAS_FONTE=gupy
Não precisa de chave. Para voltar ao Adzuna: VAGAS_FONTE=adzuna (+ as chaves).
Para desligar: VAGAS_FONTE=nenhuma.

## Testar
    php teste-gupy.php
Depois, em Admin > Vagas Sugeridas > "Buscar vagas agora".

## Como funciona
- Endpoint: https://portal.gupy.io/api/job-search/jobs (o mesmo do projeto gupy-search do GitHub).
- Cerca de 8 consultas por busca (estágio em cada cidade de SP/ABC, trainee no estado, estágio remoto),
  pausa de 1,2 s entre elas; nova tentativa automática se a Gupy responder 429/5xx.
- Fica com: São Paulo, ABC ou remoto; tipo estágio/trainee/aprendiz (informado pela Gupy);
  inscrições ainda abertas; sem sênior/pleno/gerente.
- Tudo entra como PENDENTE na fila; nada aparece para os alunos sem aprovação.
- Vaga aprovada ganha "Fonte: Gupy (portal.gupy.io)" e o link original da vaga.

## Atenção
A busca da Gupy NÃO é uma API oficial. Se ela mudar, a tela mostra o erro
e o resto da Central continua funcionando normalmente.
