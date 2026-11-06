# Versão 20 (inclui a Parte 4)

Projeto sem `data/` e `uploads/`: pode copiar tudo por cima.

## Novo perfil: Admin de Denúncias
No `data/usuarios.json`: `"is_admin": true` e `"tipo": "admin_denuncias"`.
- Recebe o Canal de Denúncias (admin-denuncias.php): lê, responde com anexos e muda a situação.
- Controla os chats e as denúncias da mentoria (admin-mentoria.php, abas Pedidos e Denúncias).
- Avisos: bolinha vermelha e e-mail nos dois lados.

## Canal de Denúncias (denuncias.php)
Para todos os perfis. Tipo, assunto, relato, anexos (imagem ou PDF, até 5 de 5 MB), imagem colada com Ctrl+V e opção anônima.
Anexos em `data/denuncias_anexos/` (só abrem para quem enviou e para o admin).

## Início diferente por perfil
Aluno, Professor, Egresso, Coordenação de Estágios, Coordenação de Egressos e Admin de Denúncias.

## Acessos
- Vagas: alunos, professores e as duas coordenações. O egresso vê só as vagas dele.
- Coordenação de Egressos: Painéis e Relatórios.
- Mentoria: a Coordenação de Estágios aprova os mentores; o Admin de Denúncias vê as conversas e cuida das denúncias.
- E-mails de solicitação de vaga e assinatura: só para a Coordenação de Estágios.

## IA do currículo
Recebe as datas da formação e não pede o que já está no currículo. Itens opcionais viram lembrete e não baixam a nota.
