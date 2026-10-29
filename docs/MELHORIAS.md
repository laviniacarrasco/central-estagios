# Central de Estágios FSA — Lista de melhorias

Segurança ficou de fora, como pedido. Cada item tem esforço (P = pequeno, M = médio, G = grande) e impacto (★ a ★★★).
Os itens marcados com 🔥 são os que eu faria primeiro.

---

## 1. Fluxos

| # | Melhoria | Esforço | Impacto |
| --- | --- | --- | --- |
| 1 | 🔥 **Funil de candidatura com status.** Hoje o aluno clica em "Candidatar-se" e sai do site. Depois ele atualiza em Minhas Vagas: Enviada → Em análise → Entrevista → Aprovado ou Reprovado. Adicionar lembrete automático ("você se candidatou há 7 dias, já teve retorno?") e campo de anotações. | M | ★★★ |
| 2 | 🔥 **Onboarding do aluno no primeiro login.** Passo a passo: completar perfil → montar currículo → escolher cursos de interesse → ativar alertas. Barra de progresso no início. | M | ★★★ |
| 3 | **Fluxo de estágio ativo.** Quando o aluno consegue o estágio: registrar empresa, supervisor, datas, carga horária, e acompanhar as horas cumpridas e o vencimento do contrato. A coordenação recebe aviso 30 dias antes do fim. | G | ★★★ |
| 4 | **Solicitação de vaga com aprovação em etapas.** O aluno ou a empresa pede, o admin analisa, o sistema devolve com comentário ("faltou salário"). Hoje a decisão é só aprovar ou rejeitar. | M | ★★ |
| 5 | **Fluxo de empresa parceira.** Login próprio da empresa para cadastrar vagas, ver candidatos e marcar status. Hoje tudo passa pelo admin. | G | ★★★ |
| 6 | **Convite de egresso para mentor.** O egresso que aceita mentoria recebe o pedido por e-mail, com botões Aceitar e Recusar. Depois da mentoria, o aluno avalia (estrelas e comentário). | M | ★★ |
| 7 | **Recuperação de senha por e-mail.** Hoje o login é por CPF. Mostrar o e-mail mascarado na tela de "Esqueci a senha". | P | ★★ |
| 8 | **Fila de vagas sugeridas (importadas por API).** A vaga importada entra como rascunho, o admin aprova e ela vai para os alunos. Base para a integração com Adzuna e JSearch. | M | ★★★ |
| 9 | **Encerramento de vaga pela empresa ou egresso.** Botão "vaga preenchida", que tira a vaga do ar e avisa quem salvou. | P | ★★ |
| 10 | **Pesquisa de satisfação** ao fim do estágio, enviada por e-mail, com resultado no dashboard admin. | M | ★★ |

---

## 2. Telas

### Início (dashboard do aluno)
- 🔥 Trocar os 3 números por um **painel de próximas ações**: "Falta 1 passo no currículo", "2 vagas novas para o seu curso", "Entrevista em 3 dias".
- Mostrar **vagas recomendadas** (já existe o "Vagas para Você") direto no início, com 3 cards.
- Carrossel de posts com **data e categoria** (Evento, Prazo, Dica).
- Calendário com prazos: fim de inscrição de vaga, entrevistas, vencimento de estágio.
- Atalho flutuante "Atualizar currículo".

### Vagas
- 🔥 **Filtros reais:** cidade (SP, Santo André, São Bernardo, São Caetano, ABC), modalidade (presencial, híbrido, remoto), faixa de bolsa, carga horária, curso. Hoje só há busca por texto e curso.
- **Ordenação:** mais recentes, maior bolsa, mais perto de vencer.
- **Mapa** ou distância da FSA (útil no ABC).
- Selo "Nova", "Termina em 3 dias" e "Você já se candidatou".
- **Compartilhar vaga** (WhatsApp e link).
- Visualização em lista compacta ou em cards.
- Paginação ou "carregar mais". Com muitas vagas a tela fica longa.

### Currículo
- 🔥 **Pontuação do currículo** ("Seu currículo está 70% completo") com dicas por seção.
- **Mais modelos** e escolha de cor (já tem tema, falta pré-visualização lado a lado).
- **Modo "adaptar para a vaga":** o aluno cola a descrição e o sistema destaca as palavras-chave que faltam. Com IA, sugere um resumo ajustado.
- **Revisor de texto** (ortografia e verbos de ação).
- **Download em PDF e DOCX**, além de link público do currículo.
- Importar dados do LinkedIn por PDF exportado.
- Várias versões do currículo (ex.: "Dados" e "Desenvolvimento"), cada uma ligada a uma candidatura.
- Seção de projetos e portfólio (GitHub, Behance).

### Perfil
- Barra de completude e checklist do que falta.
- Campo de **links** (LinkedIn, GitHub, portfólio) e **áreas de interesse**.
- **Disponibilidade:** turno livre, data de início, modalidade preferida. Isso melhora o "Vagas para Você".
- Histórico do teste de competências, para comparar resultados ao longo do tempo.

### Minhas Vagas
- **Quadro Kanban** (arrastar entre colunas) além da lista.
- Anotações, data de entrevista e contato do recrutador por candidatura.
- Exportar para o calendário (.ics).

### Dicas de Carreira
- Conteúdo em **trilhas** (Primeiro estágio, Entrevista, LinkedIn, Inglês).
- Vídeos curtos e modelos prontos (e-mail de agradecimento pós-entrevista, carta de apresentação).
- **Simulador de entrevista com IA:** o aluno responde e recebe feedback.
- Dicas filtradas pelo curso.

### Documentos e assinaturas
- Status visível de cada documento (enviado, em análise, aprovado, devolvido com motivo).
- Contador de horas complementares com meta do curso ("120 de 200 h").
- Modelos de TCE e plano de atividades para baixar.
- Lembretes de assinatura pendente por e-mail e no menu (já existe a bolinha).

### Egressos e mentoria
- **Perfil público do egresso** com cargo, empresa, turma e trajetória em 3 linhas.
- Busca de mentor por área, empresa e curso.
- Agenda de mentoria (horários livres) em vez de contato por mensagem.
- Mural de "histórias de sucesso" no início.
- Eventos: palestras e encontros de egressos, com inscrição.

### Admin
- 🔥 **Dashboard com funil:** vagas publicadas → candidaturas → entrevistas → contratações, por curso e por mês.
- **Taxa de colocação** por curso e por turma.
- Empresas que mais contratam e empresas sem retorno.
- Edição em massa de vagas (encerrar, renovar validade, mudar curso).
- Importar alunos por planilha (CSV) em vez de editar o JSON.
- Log de ações do admin (quem aprovou o quê).
- Prévia do e-mail antes de enviar o resumo semanal.
- Central de **mensagens aos alunos por curso ou período**.

### Geral (visual e uso)
- **Menu lateral com nome ao passar o mouse**, ou um modo expandido. Hoje são só ícones.
- **Modo escuro** opcional.
- Versão **mobile** revisada (menu inferior em vez da barra lateral).
- Estados vazios com orientação ("Você ainda não salvou vagas. Explore aqui").
- Telas de carregamento (skeleton) em vez de tela em branco.
- Notificações dentro do site (sino) além das bolinhas.
- Busca global (vagas, documentos, mentores).
- Acessibilidade: contraste, foco por teclado, texto alternativo nas imagens.
- Trocar o Tailwind via CDN por CSS compilado. O site carrega mais rápido.
- Padronizar as cores: azul FSA para tudo que é institucional e egresso; amarelo para destaque; verde e vermelho só para status.

---

## 3. Integrações

| # | Integração | O que entrega | Esforço |
| --- | --- | --- | --- |
| 1 | 🔥 **APIs de vagas** (Adzuna, JSearch, Jooble) + filtro por IA | Vagas de SP e do ABC entram numa fila para o admin aprovar. | M |
| 2 | 🔥 **IA no currículo** (Gemini, OpenAI) | Melhora o resumo, adapta o texto à vaga, corrige a escrita. | M |
| 3 | **E-mail profissional** (Brevo, SendGrid ou Resend) no lugar do Gmail | Mais envios por dia e relatório de entrega. O Gmail limita bastante para 800+ alunos. | P |
| 4 | **WhatsApp** (Twilio ou Z-API) | Aviso de vaga nova, entrevista e assinatura pendente. Alta taxa de leitura. | M |
| 5 | **Google Agenda** | Entrevistas e prazos no calendário do aluno. | M |
| 6 | **Login com Microsoft ou Google institucional** | O aluno entra com a conta da FSA, sem CPF e senha. | M |
| 7 | **Sistema acadêmico da FSA** (RA, curso, período, situação) | Dados dos alunos sempre atualizados, sem planilha manual. | G |
| 8 | **Gupy, Greenhouse e Lever das empresas parceiras** | Importa as vagas delas automaticamente. | M |
| 9 | **LinkedIn** (botão "Compartilhar no LinkedIn" e "Adicionar certificado") | Divulgação das vagas e do selo de egresso. Não há API pública de busca de vagas. | P |
| 10 | **Assinatura digital** (Clicksign, D4Sign, Gov.br) | TCE e documentos assinados dentro do fluxo. | G |
| 11 | **Banco de dados** (MySQL ou PostgreSQL) no lugar dos JSON | Mais rapidez, vários acessos ao mesmo tempo, dados que não somem no Render. Também resolve o problema do disco temporário. | G |
| 12 | **Armazenamento de arquivos** (S3 ou Cloudinary) | Fotos, currículos e documentos que continuam funcionando no Render. | M |
| 13 | **Google Analytics ou Plausible** | Quais telas e vagas mais acessadas. | P |
| 14 | **Chatbot de dúvidas** (IA com o regulamento de estágio da FSA) | Reduz perguntas repetidas à coordenação. | M |
| 15 | **Power BI ou Looker Studio** | Painéis para a diretoria, ligados ao banco. | M |

---

## 4. Sugestão de ordem

1. **Rápidos (1 a 2 semanas):** filtros de vagas, pontuação do currículo, painel de próximas ações, e-mail profissional, padronização de cores.
2. **Valor alto (1 a 2 meses):** funil de candidaturas, importação de vagas por API com aprovação, IA no currículo, dashboard admin com funil, WhatsApp.
3. **Estrutura (planejar):** banco de dados, armazenamento de arquivos, login institucional, integração com o sistema acadêmico, portal da empresa.

O item 11 das integrações (banco de dados) é o que mais destrava os outros. No plano gratuito do Render, quase tudo acima que grava dados corre o risco de perdê-los.
