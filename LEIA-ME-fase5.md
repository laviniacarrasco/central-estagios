# Fase 5: pacote COMPLETO (usuario restrito + azul FSA + melhorias)

Este e o unico zip a aplicar. Ele ja inclui o pacote anterior (usuario restrito e egressos em azul).
Copie tudo para a raiz da Central, mantendo as pastas, aceitando substituir. Depois:
  git add -A
  git commit -m "Fase 5: melhorias"
  git push

ATENCAO: data/usuarios.json vem do ultimo zip enviado + o usuario restrito (CPF 333.333.333-33,
senha Restrito@2026). Se voce cadastrou ou alterou usuarios depois desse zip, NAO substitua este arquivo:
copie so o bloco do usuario 9003 para o seu usuarios.json.

## O que tem de novo

ALUNO (inclusive o restrito, salvo onde indicado)
- Vagas: filtros por cidade (SP, ABC, cada cidade do ABC), modalidade, bolsa e ordenacao; selos
  Nova / Termina em X dias / Voce ja se candidatou / Vaga externa; compartilhar no WhatsApp e LinkedIn;
  "Carregar mais" (10 por vez). Corrigido o contador "Novas Esta Semana" (datas dd/mm/aaaa).
- Inicio: primeiros passos com barra de progresso, proximas acoes (entrevistas, candidaturas sem retorno,
  vagas salvas vencendo, vagas novas do curso, curriculo incompleto) e 3 vagas recomendadas.
- Curriculo: pontuacao de 0 a 100 com dicas e "Adaptar para uma vaga" (compara palavras-chave).
- Perfil: completude, links (LinkedIn, GitHub, portfolio) e disponibilidade (turno, modalidade, inicio).
- Dicas de Carreira: trilhas, dicas por curso e 3 modelos prontos para copiar.
- Minhas Vagas (so aluno comum): anotacoes, entrevista, contato do recrutador, .ics e Google Agenda,
  lembrete de "sem retorno" e visualizacao em Quadro (arrastar).
- Menu: nome do icone ao passar o mouse e versao para celular (menu embaixo).

ADMIN
- Funil e Indicadores (admin-funil.php): vagas > candidaturas > entrevistas > contratacoes, por mes e por curso,
  taxa de colocacao por curso, empresas que mais contratam e sem retorno, ultimas acoes do admin.
- Vagas Sugeridas (admin-sugeridas.php): fila de vagas (busca externa ou manual), aprovar/rejeitar.
- Importar Alunos (admin-alunos-importar.php): CSV, cria e atualiza.

## Usuario restrito
Tem so: Inicio, Vagas (sem egressos), Candidaturas, Perfil (sem o formulario da IA), Curriculo e Dicas de Carreira (sem IA). Ganhou os filtros de vagas,
o Inicio com passos e vagas recomendadas (sem dados da IA), a pontuacao do curriculo, a completude do perfil
e as dicas. Nao ve Candidaturas, Alertas, Mentoria nem Vagas para Voce.

## Variaveis de ambiente (todas opcionais)
ADZUNA_APP_ID, ADZUNA_APP_KEY   buscar vagas externas (developer.adzuna.com)
GEMINI_API_KEY, GEMINI_MODEL    a IA confere se e estagio e escolhe os cursos (confira o nome do modelo vigente)
PLAUSIBLE_DOMAIN                ex.: central-estagios-fsa.onrender.com (estatisticas de acesso)
ALERTAS_CRON_KEY                a mesma do resumo semanal; agende /importar-vagas-cron.php?chave=SUA_CHAVE 1x por dia

## Arquivos novos
admin-funil.php, admin-sugeridas.php, admin-alunos-importar.php, importar-vagas-cron.php, perfil-extras-salvar.php,
includes/melhorias.php, includes/vagas-externas.php, includes/proximas-acoes.php, includes/curriculo-extras.php,
includes/perfil-extras.php, includes/dicas-extras.php

## Dados novos (criados sozinhos em data/)
vagas_sugeridas.json, admin_log.json

## Nao foi implementado nesta fase
WhatsApp, login Microsoft/Google, integracao com sistema academico, assinatura digital, banco de dados,
armazenamento de arquivos, modo escuro, chatbot, simulador de entrevista com IA, estagio ativo, portal da empresa,
convite de mentor por e-mail, pesquisa de satisfacao, edicao em massa de vagas, calendario no inicio.
