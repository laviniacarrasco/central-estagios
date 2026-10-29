# Versão 10: log das conversas por mentor e denúncias na mentoria

**Este zip já tem tudo das versões 7, 8, 9 e 10.** Copie para a raiz da Central, aceitando substituir. Não precisa mexer no `.env`.

## 1. Log das conversas em cada mentor (Mentoria Admin > Mentores)
Cada quadrado de mentor tem agora **Conversas e log (N)**. Ao abrir:
- uma linha por conversa (aluno, assunto, status, nº de mensagens);
- **Ver log**: abre a conversa inteira (só leitura);
- **TXT**: baixa aquela conversa;
- **Aluno (TXT)**: baixa todas as conversas daquele aluno;
- **Baixar todas deste mentor (TXT)**: todas as conversas do mentor num arquivo só.
Se a pessoa tem denúncias, aparece o selo vermelho "N denúncia(s)" no quadrado.

## 2. Denúncias (aluno e mentor)
- Na tela **Mentoria**, aluno e egresso têm a aba **Denúncias**: escolher a conversa, o motivo e contar o que aconteceu (obrigatório). Lá também aparecem as denúncias feitas e a resposta da coordenação.
- No **chat**, cada mensagem da outra pessoa tem **Denunciar**, e o topo do chat tem **Denunciar** para a conversa inteira.
- A pessoa denunciada **não é avisada** e não sabe quem denunciou.
- A **Coordenação de Egressos recebe e-mail** na hora, com a mensagem denunciada e o relato.

## 3. Coordenação de Egressos (Mentoria Admin > Denúncias)
- **Bolinha vermelha** no ícone Mentoria (Admin) e na aba Denúncias, enquanto houver denúncias novas.
- Cada denúncia mostra quem denunciou, quem foi denunciado, a mensagem, o relato e o histórico.
- Ações: **Marcar em análise**, **Resolver** e **Arquivar** (os dois últimos exigem resposta, que vai por e-mail para quem denunciou), **Ver log da conversa**, **TXT** e, se for mentor, **Suspender mentor** (com justificativa).
- No log da conversa, a mensagem denunciada aparece marcada em vermelho (no TXT: "Mensagem DENUNCIADA").
- Tudo fica no log de ações dos funcionários.

## Arquivos desta versão
Alterados: `includes/mentoria.php`, `mentoria.php`, `mentoria-chat.php`, `admin-mentoria.php`, `admin-mentoria-conversa.php`.
Dados novos: `data/mentoria_denuncias.json` (criado na primeira denúncia).

## Para testar
1. Como aluno com mentoria em andamento: abra o chat, clique em **Denunciar** embaixo de uma mensagem do mentor e envie.
2. Confira o e-mail da Coordenação de Egressos (precisa do Gmail configurado).
3. Entre como Coordenação de Egressos: a bolinha vermelha aparece em Mentoria (Admin). Abra a aba **Denúncias**, clique em **Ver log da conversa** e depois em **Resolver** (sem resposta, a caixa não deixa confirmar).
4. Volte como aluno em Mentoria > Denúncias: a resposta da coordenação aparece.
5. Em Mentoria (Admin) > Mentores, abra **Conversas e log** e baixe o TXT do mentor.
