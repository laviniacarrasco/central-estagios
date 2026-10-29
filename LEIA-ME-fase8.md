# Versão 8: conversas da mentoria e justificativa obrigatória

Copie os arquivos deste zip para a raiz da Central, aceitando substituir. Não precisa mexer no `.env`.

## 1. Conversas da mentoria (Coordenação de Egressos)

Em **Mentoria (Admin) > Pedidos**, cada pedido ganhou a coluna **Conversa**:
- **Ver**: abre a conversa só para leitura (pedido do aluno, resposta do mentor e o chat).
- **TXT** ou **CSV**: baixa aquela conversa.
- **Baixar todas as conversas (CSV)**: uma linha por mensagem, respeitando o filtro de status.

Cuidados:
- Cada consulta e cada download ficam no log de ações (`data/admin_log.json`), com o nome de quem fez.
- O chat agora avisa aluno e mentor: "a Coordenação de Egressos pode consultar esta conversa".
- O limite de mensagens guardadas por conversa subiu de 500 para 2000.

## 2. Justificativa obrigatória

Nada é negado, suspenso ou removido sem um motivo de pelo menos 10 caracteres. A tela pede o texto e o servidor confere de novo.

| Tela | Ação | Quem vê a justificativa |
| --- | --- | --- |
| Mentoria (Admin) | Recusar cadastro de mentor, Suspender mentor | O egresso (e-mail e tela) |
| Mentoria (mentor) | Recusar pedido de um aluno | O aluno |
| Admin Vagas | Rejeitar solicitação de vaga (e bloquear o aluno) | O aluno |
| Admin Assinaturas | Marcar erro | O aluno |
| Vagas Sugeridas | Rejeitar vaga externa | Log e histórico |
| Gestão de Egressos | Rejeitar, Encerrar ou Remover vaga de egresso | O egresso (rejeitar e encerrar) e o log |

Em telas novas: no formulário, `data-justificativa="motivo"` (opcional: `data-justificativa-dica="Ex.: ..."`);
no PHP, `$motivo = justificativaPost('motivo'); if ($motivo === null) { ...erro... }`.

## Arquivos

Novo: `admin-mentoria-conversa.php`.
Alterados: `includes/header.php`, `includes/config.php`, `includes/perfis.php`, `includes/mentoria.php`, `admin-mentoria.php`, `mentoria.php`, `admin-jobs.php`, `admin-signature.php`, `admin-sugeridas.php`, `admin-egressos.php`, `egresso-publicar.php`.

## Para testar

1. Coordenação de Egressos > Mentoria (Admin) > Pedidos > **Ver** e **TXT**. Confira se apareceu no log.
2. Tente **Suspender** um mentor sem escrever nada: a caixa não deixa confirmar.
3. Repita em Admin Vagas (rejeitar), Admin Assinaturas (marcar erro), Vagas Sugeridas (rejeitar) e Gestão de Egressos (encerrar/remover).
4. Como egresso mentor, recuse um pedido: precisa escrever o motivo.
