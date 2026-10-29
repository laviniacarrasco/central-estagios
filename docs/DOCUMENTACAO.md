# Central de Estágios FSA

### Documentação do projeto

Este documento explica como a Central de Estágios funciona: o que cada tela faz, onde ficam os dados e como o código está organizado. Ele também traz os cuidados para mexer no projeto sem quebrar nada.

Ele foi escrito para quem vai manter o sistema daqui pra frente, seja alguém da equipe, da coordenação ou eu mesma daqui a alguns meses, quando eu já tiver esquecido metade das decisões. Por isso tentei explicar também o *porquê* de algumas escolhas, e não só o *como*.

Outros documentos que ficam na pasta `docs/`:
- `ARQUITETURA.md`: como o código está dividido em camadas e como funciona o registro de perfis;
- `INTEGRACAO-TOTVS.md`: a integração com o sistema acadêmico, passo a passo;
- `MELHORIAS.md`: a lista de ideias para o futuro;
- `diagramas/`: as imagens para o Miro e os mesmos diagramas em Mermaid.

A base é a versão do código de outubro de 2026.

---

## 1. Visão geral

A Central nasceu para juntar num lugar só tudo o que hoje fica espalhado entre e-mails, planilhas e formulários. Cada perfil de usuário usa uma parte dela:

- **O aluno** procura vagas, monta o currículo, envia certificados de horas complementares, pede assinatura de documentos, acompanha as candidaturas e pode pedir mentoria para um ex-aluno.
- **A coordenação (admin)** cadastra e aprova vagas, publica avisos no mural, assina documentos e acompanha os alunos.
- **Os egressos** (ex-alunos) divulgam vagas das empresas onde trabalham e podem virar mentores.
- **O admin de egressos** cuida dessa parte: aprova as vagas e os mentores e tira relatórios de empregabilidade.

O sistema é todo em **PHP puro** e não tem banco de dados. Tudo fica guardado em **arquivos JSON** na pasta `data/`. A escolha foi pela simplicidade: dá pra abrir os dados num editor de texto e entender na hora. O preço disso aparece na seção 14.

O site roda no **Render** (plano gratuito), e o código fica num repositório privado no GitHub (`laviniacarrasco/central-estagios`).

---

## 2. Como rodar o projeto

### 2.1 No computador

Você precisa do **PHP 8.2**, com a extensão `curl` ativa, e do **Composer**. Com os dois instalados, rode na pasta do projeto:

```bash
composer install
php -S localhost:8000 router.php
```

Depois abra `http://localhost:8000` no navegador. O login é feito com o **CPF e a senha** cadastrados no `data/usuarios.json`.

Para os e-mails e as integrações funcionarem, crie um arquivo `.env` na raiz (as variáveis estão na seção 9). Sem esse arquivo o site funciona normalmente, só não manda e-mail nem chama nenhuma API externa.

### 2.2 No Render

O Render faz o deploy sozinho a cada `git push` na branch `main`. O `Dockerfile` prepara tudo: usa a imagem `php:8.2-cli`, instala o `curl` e o Composer, roda o `composer install` e sobe o servidor do PHP na porta 80.

As variáveis de ambiente ficam no painel, em **Environment**. O `.env` **nunca** vai para o git.

O endereço público é `central-estagios-fsa.onrender.com`.

### 2.3 Para publicar uma mudança

```bash
git add -A
git status
git commit -m "Descrição da mudança"
git push
```

O `git status` serve pra conferir o que vai subir antes do commit. Se o `.env` aparecer na lista, pare e revise o `.gitignore`.

### 2.4 O que você precisa saber sobre o plano gratuito

Esta parte é a que mais confunde, então vale ler com calma.

- **O disco do Render não é permanente.** Tudo o que for gravado direto no site publicado (cadastro novo, candidatura, foto enviada) pode sumir quando o serviço reinicia ou quando entra um deploy novo. O que sempre volta é o que está no git.
- **O caminho é de mão única.** O que você faz no seu computador vai para o Render quando você dá push. O que alguém faz no Render não volta para o seu computador e ainda é apagado no próximo push.
- **O serviço "dorme" quando ninguém usa.** O primeiro acesso depois disso demora alguns segundos. Não é defeito.
- **A pasta `uploads/` não vai para o git.** Então fotos, currículos em PDF, certificados e documentos assinados aparecem quebrados no Render.

A solução definitiva é um disco persistente ou um banco de dados. Falo mais disso na seção 14.

---

## 3. Tecnologias e organização das pastas

| O quê | Para que serve |
| --- | --- |
| PHP 8.2 | O sistema inteiro: telas e regras |
| Tailwind CSS (pelo CDN) | O visual das telas |
| Font Awesome 6 | Os ícones |
| Arquivos JSON em `data/` | Fazem o papel de banco de dados |
| `vlucas/phpdotenv` | Lê o `.env` quando o site roda no computador |
| `phpmailer/phpmailer` | Está instalado, mas quem envia os e-mails de verdade é a Gmail API, pelo `GmailMailer.php` |
| Docker e Render | Publicação do site |

```
central-estagios/
├── *.php                 uma página para cada tela, mais alguns endpoints
├── includes/             funções compartilhadas, cabeçalho, rodapé e módulos
├── data/                 os dados em JSON
│   ├── *.json            dados gerais (vagas, usuários, posts...)
│   └── users/<id>/       dados de cada usuário (currículo, certificados...)
├── uploads/              arquivos enviados (fora do git)
├── vendor/               bibliotecas do Composer (fora do git)
├── Dockerfile, render.yaml, composer.json
├── .env                  credenciais locais (fora do git)
└── LEIA-ME-*.md          anotações de cada pacote de mudanças
```

Quase todas as páginas seguem o mesmo esqueleto. Entendendo ele, fica fácil ler qualquer tela:

```php
<?php
require_once 'includes/config.php';   // sessão, permissões, loadData/saveData
checkAuth();                           // exige login e aplica as permissões
$pageTitle = 'Título';
// ... lê os dados e trata o formulário (POST) ...
include 'includes/header.php';         // menu lateral, topo, bolinhas e caixas padrão
?>
<main class="ml-16 pt-16"> ... </main>
<?php include 'includes/footer.php'; ?>
```

---

## 4. Tipos de usuário e o que cada um pode ver

O tipo de cada pessoa vem do cadastro no `data/usuarios.json`, pelos campos `is_admin` e `tipo`. No login, o `index.php` guarda esse tipo na sessão. Depois, a cada página aberta, a função `verificarPermissaoPagina()` (no `includes/config.php`) confere se a pessoa pode estar ali. Se não puder, ela é mandada para a página inicial do perfil dela.

| Tipo | Como é definido | Começa em | O que acessa |
| --- | --- | --- | --- |
| **Aluno** | `is_admin: false` e sem `tipo` | `dashboard.php` | Toda a área do aluno. Não entra nas páginas de admin nem nas de egresso. |
| **Aluno restrito** | `tipo: "aluno_restrito"` | `dashboard.php` | Só Início, Perfil (sem a parte de IA), Currículo, Documentos, Vagas e Vagas para Você (as duas sem vagas de egressos), Minhas Vagas e Dicas de Carreira (sem IA). As outras telas nem aparecem no menu. Se ele digitar o endereço, volta para o Início. |
| **Professor** | `tipo: "professor"` | `dashboard.php` | Por enquanto só consulta: Início, Vagas, Vagas de Egressos e Dicas de Carreira. O que o professor vai fazer ainda precisa ser definido pela coordenação. |
| **Egresso** | `tipo: "egresso"` | `jobs.php` | Só Vagas (para ver as vagas, inclusive as que divulgou), Divulgar Vaga, o perfil de egresso e a mentoria (com chat). |
| **Admin** (Coordenação de Estágios) | `is_admin: true` | `dashboard.php` | Uma lista fechada de telas: dashboards, vagas, vagas sugeridas, assinaturas, posts, funil, importação de alunos, Integração TOTVS, alertas e o mural de egressos. |
| **Admin de egressos** (Coordenação de Egressos) | `is_admin: true` e `tipo: "admin_egressos"` | `dashboard.php` | Tudo o que o admin vê, mais o painel de egressos, a mentoria do admin e os relatórios. |

Esses seis perfis ficam divididos em quatro grupos: **alunos** (aluno e aluno restrito), **professores**, **egressos** e **funcionários** (as duas coordenações).

Hoje o `usuarios.json` tem 846 cadastros: 842 alunos (um deles restrito), 2 funcionários, 1 egresso e 1 professor. Para testar, use estes:

| ID | Nome | Tipo |
| --- | --- | --- |
| 9001 | Admin Egressos Teste | admin de egressos |
| 9002 | Egresso Teste | egresso |
| 9003 | Aluno Restrito Teste (CPF 333.333.333-33) | aluno restrito |
| 9004 | Professor Teste (CPF 444.444.444-44, senha `Professor@2026`) | professor |
| 2 | Coordenação | admin |
| 1 | Lavinia | aluna |

Um detalhe sobre senhas: algumas ainda estão em texto puro no JSON. Isso não é problema, porque no primeiro login o sistema converte a senha para hash sozinho (está no `index.php`).

Tem também o **aluno bloqueado por má conduta**. O admin marca isso em Admin Vagas, e o cadastro ganha `bloqueado: true` e um `motivoBloqueio`. Para esse aluno, o ícone "Solicitar Vaga" some e aparece uma faixa vermelha no topo de todas as telas explicando o motivo.

Tudo isso fica num arquivo só, o `includes/perfis.php`. Lá cada perfil tem a lista das páginas que pode abrir, os ícones do menu e o que pode fazer dentro das telas. Para mudar o acesso de alguém, é só nele que você mexe. O `docs/ARQUITETURA.md` explica com calma.

---

## 5. As telas

### 5.1 Entrada

| Página | O que faz |
| --- | --- |
| `index.php` | Login por CPF e senha. Registra o último acesso e o log de logins. Com `?logout=1`, encerra a sessão. |
| `forgot-password.php` | Troca de senha em duas etapas: primeiro a pessoa confirma os dados, depois escolhe a senha nova. |

### 5.2 Área do aluno

| Página | No menu | O que faz |
| --- | --- | --- |
| `dashboard.php` | Início | Carrossel com os avisos da coordenação, alguns números (vagas abertas, certificados, quanto do perfil está completo) e atalhos. |
| `profile.php` | Perfil | Dados acadêmicos, foto, o card do **Perfil por Competências** (teste com parecer gerado por IA, que vem do app Flask) e o card **Meus Alertas**, que abre pela engrenagem do topo (`profile.php?config=alertas`). |
| `curriculum.php` | Currículo | Editor de currículo que salva sozinho enquanto o aluno digita. Tem dados pessoais, resumo, habilidades, experiências, formação e idiomas, além da escolha de modelo, cor, idioma e foto. Exporta em PDF com a ajuda do `pdf-section.php`. |
| `documents.php` | Documentos | Atividades complementares: o aluno envia os certificados, vê as horas aprovadas e tem uma lixeira para restaurar ou apagar de vez. |
| `jobs.php` | Vagas | **Aba única de vagas:** vagas da FSA, vagas externas aprovadas e vagas de egressos, no mesmo layout de cartões. Cores por tipo (branco = FSA, amarelo = destaque, azul = egresso), filtros por origem, curso, cidade, modalidade e ordem, e os blocos "Vagas em Destaque" e "Mais vagas". Aluno restrito não vê as de egressos. |
| `minhas-vagas.php` | Minhas Vagas | Duas abas: **Candidaturas** (o aluno atualiza o andamento) e **Salvas**. |
| `vagas-para-voce.php` | Vagas para Você | Vagas ordenadas pela compatibilidade com o currículo. A conta é feita por regras, não por IA (ver `includes/matching.php`). |
| `signature.php` | Assinaturas | O aluno manda documentos (o TCE, por exemplo) para a coordenação assinar e acompanha o andamento. Se der erro, ele reenvia. |
| `career-tips.php` | Dicas de Carreira | Abas: **Meu plano** (checklist com progresso; alguns itens se marcam sozinhos, como currículo 70%, foto e candidaturas), **Meu perfil** (radar, só quem tem IA), **Trilhas e modelos**, **Entrevista** (guia e perguntas para treinar) e **Recursos**. |
| `mentoria.php` | Mentoria | O aluno procura mentores (egressos aprovados) e faz pedidos. Depois do aceite, aluno e mentor conversam por **chat** dentro da Central; mensagem nova acende a bolinha no ícone Mentoria. |
| `request-job.php` | Solicitar Vaga | O aluno sugere uma vaga, que fica esperando a aprovação do admin. |

Algumas páginas não têm tela. Elas só recebem um formulário e devolvem o aluno para onde ele estava:
- `candidatar.php` registra a candidatura e abre o link da vaga;
- `favoritar.php` salva ou tira a vaga das salvas;
- `alertas-salvar.php` guarda as preferências de alerta;
- `perfil-extras-salvar.php` guarda os extras do perfil;
- `mentoria-chat.php` lê e envia as mensagens do chat da mentoria (responde em JSON).

Os antigos `candidaturas.php` e `favoritos.php` continuam existindo só pra não quebrar links velhos. Eles redirecionam para Minhas Vagas.

### 5.3 Área do egresso

| Página | O que faz |
| --- | --- |
| `egressos.php` | Não é mais uma aba: só redireciona links antigos para `jobs.php?origem=egresso`. |
| `egresso-publicar.php` | Formulário para divulgar vaga e a lista das divulgações do próprio egresso, com as opções de encerrar, renovar e excluir. |
| `egresso-perfil.php` | Dados de egresso: situação profissional, empresa, cargo, ano de conclusão e LinkedIn. |
| `mentoria.php` | Na aba "Minha mentoria", o egresso se cadastra como mentor e responde os pedidos. |

### 5.4 Área do admin

| Página | O que faz |
| --- | --- |
| `admin-dashboard.php` | Painel geral: alunos, acessos dos últimos 7 dias, horas de estágio, edição dos dados do aluno e envio de e-mail sobre estágio. |
| `admin-paineis.php` | **Painéis BI** no estilo Power BI: os mesmos indicadores, editáveis (tipo de gráfico, fonte, eixo, valor, filtros, largura, cor), filtro cruzado e fontes de Excel/CSV e SQL. Ver `docs/PAINEIS-BI.md`. |
| `admin-jobs.php` | Criar, editar e excluir vagas, aprovar ou recusar as sugestões dos alunos e bloquear aluno. |
| `admin-signature.php` | Fila de assinaturas. O admin marca como em análise, envia o documento assinado ou aponta um erro. |
| `posts.php` | Avisos e notícias que aparecem no carrossel do Início. |
| `admin-alertas.php` | Fila de e-mails, resumo semanal (gerar, mandar uma parte da fila, esvaziar) e a URL do agendador. |
| `admin-funil.php` | Funil de vagas, candidaturas, entrevistas e contratações. |
| `admin-sugeridas.php` | Fila de vagas externas esperando aprovação. |
| `admin-alunos-importar.php` | Importação de alunos por planilha CSV. |
| `admin-totvs.php` | Integração TOTVS: simula e sincroniza os dados dos alunos com o sistema acadêmico. Hoje roda em modo teste (ver seção 8). |

### 5.5 Área do admin de egressos

| Página | O que faz |
| --- | --- |
| `admin-egressos.php` | Números de empregabilidade. Aqui o admin aprova, recusa e remove vagas de egressos, transforma um aluno formado em egresso pelo RA, cadastra um egresso novo ou desfaz a conversão. |
| `admin-mentoria.php` | Aprova, recusa, suspende e reativa mentores, e mostra os pedidos. |
| `admin-relatorios.php` | Relatórios de egressos em Excel (.xlsx, ou CSV se faltar a extensão zip) e em PDF (pela impressão do navegador, ou pelo dompdf, se estiver instalado). |

### 5.6 O que aparece em todas as telas

Tudo isso mora no `includes/header.php`:

- **O menu lateral**, só com ícones, que muda de acordo com o tipo de usuário.
- **As bolinhas vermelhas** de aviso, calculadas na hora pelo `includes/avisos.php`:
  - o admin vê as sugestões de vaga e as assinaturas pendentes;
  - o admin de egressos vê as vagas de egressos e os mentores esperando aprovação;
  - o aluno vê quando um documento voltou assinado ou com erro e ele ainda não abriu;
  - o egresso vê os pedidos de mentoria esperando resposta.
- **A engrenagem no topo**, só para o aluno, que abre o Meus Alertas.
- **As caixas de confirmação e de aviso** do próprio site, que substituem o `confirm()` e o `alert()` do navegador (ver seção 12).

---

## 6. Como as coisas acontecem (os fluxos)

### 6.1 Vaga cadastrada pela FSA
1. O admin cadastra a vaga em `admin-jobs.php`, com os cursos, o link de candidatura e a data de validade (`expiresAt`).
2. A vaga aparece em `jobs.php` enquanto estiver **Ativa** e dentro do prazo. O prazo é conferido na hora de mostrar a tela, então não precisa de nenhuma rotina para tirar vaga vencida do ar.
3. Os alunos que ativaram alerta para aquele curso recebem um e-mail.

### 6.2 Vaga sugerida pelo aluno
1. O aluno envia pelo `request-job.php`, e a vaga entra no `job_requests.json` como pendente.
2. Os admins recebem um e-mail e veem a bolinha em Admin Vagas.
3. O admin aprova, e a vaga passa para o `platform_jobs.json`. Ou recusa, explicando o motivo. Nos dois casos o aluno é avisado por e-mail.

### 6.3 Vaga divulgada por egresso
1. O egresso divulga pelo `egresso-publicar.php`, e a vaga fica aguardando no `egresso_jobs.json`.
2. O admin de egressos é avisado e aprova ou recusa em `admin-egressos.php`.
3. Depois de aprovada, e enquanto estiver no prazo, ela aparece no mural de egressos e em "Vagas em Destaque" no `jobs.php`. Aluno restrito não vê. Encerrada, recusada, removida ou vencida, ela sai sozinha.

### 6.4 Candidatura
1. O aluno clica em **Candidatar-se**. O `candidatar.php` registra a candidatura no `users/<id>/userCandidaturas.json` e abre o link da empresa.
2. O processo de verdade acontece no site da empresa. Depois, o próprio aluno atualiza o andamento em Minhas Vagas.
3. O admin não registra candidatura. Para ele, o botão leva direto ao link da vaga.

### 6.5 Assinatura de documentos
1. O aluno envia os arquivos pelo `signature.php`, e o pedido vai para o `assinaturas.json`.
2. Os admins são avisados. No `admin-signature.php`, eles marcam como em análise, enviam o documento assinado ou apontam um erro com uma mensagem.
3. O aluno recebe um e-mail e vê a bolinha. Se houver erro, ele corrige e reenvia.

### 6.6 Mentoria
1. O egresso se cadastra como mentor, escolhendo as áreas, quantos alunos aceita acompanhar e como prefere ser contatado.
2. O admin de egressos aprova o cadastro.
3. O aluno faz o pedido, com assunto e mensagem. Cada aluno pode ter no máximo **3 pedidos em andamento**, e cada mentor pode ter no máximo **5 pedidos aguardando resposta**.
4. O mentor aceita ou recusa. **O contato só é liberado depois que o mentor aceita.**
5. Com o pedido aceito, os dois conversam pelo **chat** (botão "Abrir conversa"). As mensagens ficam dentro do pedido, no `mentoria_pedidos.json`, e a tela busca as novas a cada 4 segundos. Mensagem não lida acende a bolinha no ícone Mentoria, que se atualiza sozinha a cada 30 segundos.
6. Quando a mentoria é concluída, a conversa fica só para leitura.

O mentor pode estar pendente, aprovado, recusado ou suspenso. O pedido pode estar pendente, aceito, recusado, cancelado ou concluído.

### 6.7 Como alguém vira egresso
Há duas formas. O admin de egressos pode converter um aluno formado pelo RA, e nesse caso a senha continua a mesma. Ou pode cadastrar um egresso do zero, com uma senha provisória.

### 6.8 Alertas e resumo semanal
- Em Meus Alertas, o aluno escolhe se quer **aviso na hora** quando surgir vaga nova, **resumo semanal**, ou os dois, e de quais cursos. Os alertas começam desligados (a constante `ALERTAS_PADRAO_ATIVO`, no `includes/alertas.php`, controla isso).
- Os e-mails não saem todos de uma vez. Eles entram no `fila_emails.json` e são enviados aos poucos.
- O resumo semanal é montado a partir de segunda-feira, às 8h, uma única vez por semana.

### 6.9 Perfil por Competências
1. O aluno responde o formulário no app Flask, que é um sistema separado (`perfil-fsa.onrender.com`).
2. Quando ele abre o Perfil ou as Dicas de Carreira, a Central busca o resultado e guarda uma cópia em `users/<id>/perfilResultado.json`.
3. Se o Flask reiniciar e perder o resultado (o que acontece no plano gratuito), a Central usa a cópia e ainda devolve os dados para o Flask pelo endereço `/api/restaurar`.

Um detalhe importante: o aluno é identificado pelo **nome**, transformado em slug. Por exemplo, "Lavínia Silva" vira `lavinia_silva`.

---

## 7. Os dados (arquivos JSON)

Sempre leia e grave os dados pelas funções `loadData('nome')` e `saveData('nome', $dados)`. Se você passar `true` como último parâmetro, o arquivo usado é o do usuário logado (`data/users/<id>/nome.json`). Se o arquivo não existir, o `loadData` devolve uma lista vazia, e o `saveData` cria as pastas que faltarem.

### 7.1 Dados gerais (`data/`)

| Arquivo | O que guarda | Campos principais |
| --- | --- | --- |
| `usuarios.json` | Todos os usuários, numa lista | `id`, `nome`, `cpf` (só números), `senha`, `is_admin`, `tipo`, `email`, `curso`, `ra`, `periodo`, `turno`, `situacao`, `ultimoAcesso`, `alertas`, `bloqueado`, `motivoBloqueio`. Nos egressos também: `statusProfissional`, `empresaAtual`, `cargoAtual`, `anoConclusao`, `linkedin` e os campos `mentoria*` |
| `platform_jobs.json` | Vagas da FSA, num objeto indexado pelo id | `id`, `title`, `company`, `location`, `hours`, `salary`, `type`, `status`, `featured`, `courses`, `applicationLink`, `description`, `requirements`, `benefits`, `createdAt` (dd/mm/aaaa), `expiresAt` (aaaa-mm-dd) |
| `egresso_jobs.json` | Vagas de egressos | os campos da vaga, mais `egressoId`, `egressoName`, `categoria`, `status`, `motivoRejeicao`, `publishedTs`, `aprovadoPor`, `expiresAt` |
| `job_requests.json` | Vagas sugeridas pelos alunos | os campos da vaga, mais `studentId`, `studentName`, `status`, `motivoRejeicao` |
| `assinaturas.json` | Pedidos de assinatura | `userId`, `tipo`, `area`, `arquivos`, `arquivoAssinado`, `status`, `mensagemErro` e as datas |
| `platform_posts.json` | Avisos do Início | `title`, `content`, `hashtags`, `images`, `whiteBackground`, `createdAt` |
| `mentoria_pedidos.json` | Pedidos de mentoria | `alunoId`, `mentorId`, `assunto`, `mensagem`, `status`, `resposta` e os horários |
| `course.json` | Regras de cada curso | `estagioObrigatorio`, `horasEstagio`, `horasComplementares`, `periodo_inicio`, `observacao`. É criado sozinho com os valores oficiais |
| `fila_emails.json` | E-mails esperando envio | destinatário, assunto, corpo e tentativas |
| `alertas_estado.json` | Controle do resumo semanal | `ultimoTick`, `semanaResumo`, `ultimoResumoTs`, `ultimoEnvioTs` |
| `acessos_log.json` | Um registro por login (fica fora do git) | `userId`, `data` |
| `vagas_sugeridas.json` | Fila de vagas externas (criado sozinho) | — |
| `admin_log.json` | Ações feitas pelo admin (criado sozinho) | — |

### 7.2 Dados de cada usuário (`data/users/<id>/`)

| Arquivo | O que guarda |
| --- | --- |
| `userCurriculum.json` | O currículo inteiro: dados, resumo, habilidades, experiências, formação, idiomas, modelo, cor, idioma e foto |
| `userCertificates.json` (e as versões `Archive` e `Trash`) | Os certificados com horas e situação, mais os arquivados e os que estão na lixeira |
| `userCandidaturas.json` | As candidaturas (`origem`, `jobId`, `title`, `link`, `status`, `ts`) |
| `userFavoritos.json` | As vagas salvas |
| `userProfile.json` | A foto de perfil e os extras do perfil |
| `perfilResultado.json` | A cópia do Perfil por Competências, com o histórico |

O campo `origem` diz de onde a vaga veio: `faculdade` (cadastrada pela FSA) ou `egresso`. Como as duas listas podem ter vagas com o mesmo número de id, o sistema junta a origem com o número para criar um identificador único, como `faculdade-12` e `egresso-12`.

---

## 8. Integrações com outros sistemas

| Integração | Onde está | Para que serve | Se não estiver configurada |
| --- | --- | --- | --- |
| **Gmail API** (OAuth2) | `includes/GmailMailer.php`, `email-config.php`, `notificacoes.php` | Todos os e-mails do sistema | O site funciona, o e-mail só não sai. O motivo fica registrado no log |
| **App Flask do Perfil por Competências** | `includes/perfil_api.php` | Teste comportamental, parecer de IA, PDF e gráfico radar | Aparece o convite para responder, ou a cópia salva |
| **Adzuna** | `includes/vagas-externas.php` | Busca vagas de estágio e trainee em São Paulo e no ABC (Santo André, São Bernardo, São Caetano, Diadema, Mauá, Ribeirão Pires e Rio Grande da Serra) | A importação fica desligada |
| **Google Gemini** | `includes/vagas-externas.php` | Confere se a vaga é mesmo de estágio e escolhe os cursos | O sistema usa palavras-chave no lugar |
| **TOTVS** (sistema acadêmico) | `includes/integracoes/` e `admin-totvs.php` | Atualizar RA, curso, período, turno e situação dos alunos | Fica desligada. **Ainda não temos acesso ao TOTVS**, então por enquanto ela roda em modo teste, com o arquivo `data/totvs_exemplo.json` |
| **Plausible** | ainda não implementado | Estatísticas de acesso | — |

As vagas externas **nunca são publicadas sozinhas**. Elas entram no `vagas_sugeridas.json` e só aparecem para os alunos depois que o admin aprova. Fizemos assim afim de evitar vaga falsa, golpe ou vaga que já fechou.

---

## 9. Variáveis de ambiente

Elas ficam no `.env` quando o site roda no computador, e em **Render > Environment** no site publicado. Nunca no git.

| Variável | É obrigatória? | Para que serve |
| --- | --- | --- |
| `GMAIL_CLIENT_ID`, `GMAIL_CLIENT_SECRET`, `GMAIL_REFRESH_TOKEN`, `GMAIL_SENDER_EMAIL` | Para mandar e-mail | Gmail API |
| `APP_URL` | Recomendada | O endereço usado nos links dos e-mails, por exemplo `https://central-estagios-fsa.onrender.com` |
| `ALERTAS_CRON_KEY` | Para o agendador | Uma chave longa e aleatória que protege as URLs das rotinas |
| `ADZUNA_APP_ID`, `ADZUNA_APP_KEY` | Para as vagas externas | API do Adzuna |
| `GEMINI_API_KEY`, `GEMINI_MODEL` | Não | IA que classifica as vagas. Vale conferir o nome do modelo atual no site do Google |
| `PLAUSIBLE_DOMAIN` | Não | Estatísticas |
| `TOTVS_MODO` | Não | `desligado` (padrão), `teste` ou `real`. Enquanto não houver acesso ao TOTVS, use `teste` |
| `TOTVS_URL`, `TOTVS_USUARIO`, `TOTVS_SENHA`, `TOTVS_CODSENTENCA`, `TOTVS_CODCOLIGADA`, `TOTVS_CODSISTEMA` | Só no modo `real` | Dados de acesso que a TI vai passar |

Tem um modelo pronto de todas elas no arquivo `.env.example`, na raiz.

Para saber se o `.env` foi lido no computador, abra o `teste-env.php`. Ele mostra só se cada variável está preenchida, sem mostrar o valor.

---

## 10. Rotinas automáticas

O sistema não depende de cron, mas funciona melhor com um agendador externo, como o cron-job.org. São duas URLs:

| URL | Com que frequência | O que faz |
| --- | --- | --- |
| `/resumo-semanal.php?chave=SUA_CHAVE` | a cada 15 a 30 minutos | Monta o resumo da semana (uma vez só) e manda uma parte da fila de e-mails. Com `&forcar=1`, monta o resumo de novo, o que só serve para teste |
| `/importar-vagas-cron.php?chave=SUA_CHAVE` | uma vez por dia | Busca vagas no Adzuna e coloca na fila de sugeridas |
| `/admin-totvs.php?chave=SUA_CHAVE` | uma vez por dia | Sincroniza os alunos com o TOTVS. Só ligue quando o modo for `real` |

Sem o agendador, quem faz esse trabalho é a função `alertasTick()`. Ela roda no fim das páginas, chamada pelo `header.php`, de tempos em tempos, sempre que alguém está usando o site.

**Cuidado:** com o Gmail configurado, o resumo semanal vai para todos os alunos que ativaram essa opção. Enquanto o site estiver em teste, não ligue o agendador.

---

## 11. Guia do código (pasta `includes/`)

| Arquivo | Do que cuida | Funções principais |
| --- | --- | --- |
| `config.php` | Sessão, leitura do `.env`, permissões, acesso aos dados e lista de cursos | `checkAuth`, `checkAdmin`, `verificarPermissaoPagina`, `loadData`, `saveData`, `getDataPath`, `getFsaCourses` |
| `header.php` | Menu lateral, topo, bolinhas, faixa de bloqueio e caixas de confirmação | `avisosBadge`, `getInitials` |
| `footer.php` | Fecha a página | — |
| `vagas.php` | Validade das vagas e vagas salvas | `vagaVigente`, `vagaExpirou`, `vagaDiasRestantes`, `vagaValidadeHtml`, `favoritoBotaoHtml` |
| `egressos.php` | Não é mais uma aba: só redireciona links antigos para `jobs.php?origem=egresso`. |
| `mentoria.php` | Regras e e-mails da mentoria | `mentoriaMentores`, `mentoriaCriarPedido`, `mentoriaResponderPedido`, `mentoriaMudarStatus` |
| `matching.php` | Compatibilidade entre aluno e vaga, por regras (+40 pelo curso, +12 por habilidade e assim por diante) | `matchVagasPara`, `matchPontuar`, `matchRotulo` |
| `alertas.php` | Preferências, fila de e-mails e resumo semanal | `alertasPrefs`, `alertasEnfileirarVagaNova`, `alertasProcessarFila`, `alertasEnfileirarResumoSemanal`, `alertasTick` |
| `alertas-card.php` | O cartão Meus Alertas do Perfil | `alertasCardRender` |
| `avisos.php` | Contagem das bolinhas e e-mails de aviso | `avisosContagens`, `avisosNotificar*` |
| `notificacoes.php` | Modelo e envio dos e-mails de mudança de situação | `notificarEmail`, `notificarSolicitacaoAluno`, `notificarEgressoVaga` |
| `email-config.php` e `GmailMailer.php` | Envio pela Gmail API | `enviarEmail`, `getUltimoErroEmail` |
| `acesso_log.php` | Log de logins e engajamento | `registrarAcesso`, `getAcessosUltimos7Dias` |
| `course.php` | Regras de estágio e de horas de cada curso | `getConfigDoCurso`, `cursoTemEstagioObrigatorio` |
| `relatorios.php` | Relatórios de egressos (Excel, CSV e PDF) | `relatorioEgressos`, `relatorioXlsx`, `relatorioCsv` |
| `perfil_api.php` | Ligação com o app Flask | `buscarResultadoPerfil`, `urlPdfPerfil`, `urlRadarPerfil`, `urlFormularioPerfil` |
| `melhorias.php` | Filtros (cidade e ABC, modalidade, bolsa), selos, compartilhamento, pontuação do currículo, ações do Início e log do admin | funções `mh*`, `vagaSelosHtml`, `vagaCompartilharHtml` |
| `vagas-externas.php` | Adzuna, Gemini e fila de sugeridas | `vexBuscarAdzuna`, `vexIaClassificar`, `vexImportar` |
| `proximas-acoes.php`, `curriculo-extras.php`, `perfil-extras.php`, `dicas-extras.php` | Blocos visuais da fase 5 (Início, Currículo, Perfil e Dicas) | — |
| `perfis.php` | **Registro de perfis:** páginas, menu e recursos de cada perfil | `perfilAtual`, `perfilPode`, `perfilMenu`, `perfilHome`, `perfilProtegerPagina` |
| `integracoes/totvs-*.php` | Integração TOTVS em quatro partes: config, cliente, mapeamento e sincronização | `totvsBuscarAlunos`, `totvsConverterLinha`, `totvsSincronizar` |

---

## 12. Combinados do projeto

São regras que seguimos até aqui. Vale manter, para o código continuar fácil de ler:

- **Uma página é uma tela.** As ações de cada tela são formulários (POST) enviados para a própria página, com um campo `action`. Depois de tratar o formulário, a página redireciona. Isso evita que o formulário seja reenviado quando alguém aperta F5.
- **Os dados sempre passam pelo `loadData` e pelo `saveData`.** Não leia os JSON direto.
- **Todo texto que vem do usuário é escapado** com `htmlspecialchars` ou `egh()` antes de aparecer na tela.
- **Nada de `confirm()` nem `alert()` do navegador.** Use as caixas do próprio site:

```html
<form method="POST" data-confirm="Texto" data-confirm-titulo="Título"
      data-confirm-botao="Excluir" data-confirm-tipo="perigo" data-confirm-icone="fa-trash-alt">
```

  No JavaScript, use `fsaConfirmar({titulo, texto, botao, tipo, icone}, function(){ ... })` e `fsaAviso('texto', 'título')`. O `tipo` pode ser `perigo` (vermelho) ou `aviso` (azul).
- **Cores:** o azul da FSA, `#4A9FCA` (com os tons mais escuros `#3A8FB0` e `#2B7FA6`), é usado no que é institucional e em tudo que é de **egresso**. O amarelo fica para destaque, e o verde e o vermelho só para situação (aprovado, erro etc.).
- **Datas:** o `createdAt` fica em dd/mm/aaaa, porque é só para mostrar. O `expiresAt` fica em aaaa-mm-dd, e os campos terminados em `Ts` são timestamps, usados pra ordenar.
- **A lista de cursos existe num lugar só:** a função `getFsaCourses()`, no `config.php`. Se um curso mudar, mude só ali.
- **Falha de e-mail nunca derruba a página.** O erro vai para o log e o resto continua funcionando.

---

## 13. Como fazer as tarefas mais comuns

**Criar um usuário de um tipo específico**
Acrescente um objeto no `data/usuarios.json` com um `id` que ainda não exista, o `cpf` só com números, a `senha` (pode ser em texto, ela vira hash no primeiro login), o `is_admin` e o `tipo`. Se a pessoa já estiver logada, ela precisa sair e entrar de novo.

**Criar uma página nova**
1. Copie o esqueleto da seção 3.
2. Coloque o nome da página na lista `paginas` de cada perfil que pode abrir, no `includes/perfis.php`. Todos os perfis usam lista fechada: página fora da lista não abre pra ninguém.
3. Se ela tiver que aparecer no menu, coloque também um `perfilItemMenu(...)` na lista `menu` do perfil.

**Adicionar um curso**
Edite a `getFsaCourses()`, no `config.php`. Se o curso tiver regra de estágio, ajuste também o `data/course.json`.

**Mudar o que o aluno restrito vê**
Agora é um lugar só: o perfil `aluno_restrito` no `includes/perfis.php`. As páginas ficam em `paginas`, os ícones em `menu` e o que ele pode fazer em `recursos` (por exemplo, sem `ia_perfil` ele não vê a parte de IA, e sem `vagas_egresso` não vê as vagas de egressos).

**Mudar o que o professor faz**
Mesmo esquema, no perfil `professor`. Ele começou só com consulta de propósito, até a coordenação decidir o papel dele.

**Testar a integração TOTVS**
Coloque `TOTVS_MODO=teste` no `.env`, entre como Coordenação, abra Integração TOTVS e clique em **Simular**. O passo a passo está no `docs/INTEGRACAO-TOTVS.md`.

**Testar os e-mails**
Configure o Gmail no `.env`, abra Perfil > Meus Alertas e clique em "Enviar um resumo de teste agora". Depois acompanhe em Admin > Alertas e E-mails.

---

## 14. Como o projeto está hoje, e o que falta

Revisando esta versão do código, achei estes pontos:

1. **A fase 5 ainda está pela metade.** Os arquivos novos estão na pasta (`includes/melhorias.php`, `proximas-acoes.php`, `curriculo-extras.php`, `perfil-extras.php` e `dicas-extras.php`), mas as telas que deviam usar eles ainda são as versões antigas: o `jobs.php` não tem os filtros por cidade, o `dashboard.php` não mostra as próximas ações e o `curriculum.php` está sem a pontuação. As três telas novas do admin (Funil, Vagas Sugeridas e Importar Alunos) já abrem e estão no menu.
2. **Ainda não temos acesso ao TOTVS.** A integração está pronta, mas só roda em modo teste. Para ligar de verdade, a TI precisa passar o usuário, o endereço e a consulta (a lista completa está no `docs/INTEGRACAO-TOTVS.md`). Também pode ser que o servidor do TOTVS não aceite acesso de fora da rede da FSA.
3. **O papel do professor não está definido.** Ele só consulta por enquanto.
4. **Os dados não ficam salvos no Render gratuito** (ver seção 2.4), e a pasta `uploads/` não está no git.
5. **Algumas telas ainda checam o perfil do jeito antigo**, com `$_SESSION['is_admin']`. Funciona, porque o `perfis.php` mantém essas marcas certinhas, mas o ideal é trocar aos poucos por `perfilPode()`.
6. **O `teste-env.php` continua na raiz** e não pede login. Ele não mostra os valores, só se cada variável está preenchida, mas pode ser apagado no site publicado.
7. **Algumas páginas não chamam o `checkAuth()`:** o `pdf-section.php`, o `resumo-semanal.php` (que é protegido pela chave), o `teste-env.php`, o `index.php` e as páginas que só redirecionam.
8. **O Perfil por Competências encontra o aluno pelo nome.** Haviam poucos casos de nomes repetidos quando isso foi feito, mas dois alunos com o mesmo nome podem ter os resultados misturados.
9. **Alguns LEIA-ME antigos ainda falam da etiqueta roxa** de egresso. Hoje ela é azul.

Já resolvido nesta versão: a tela de Empresas Parceiras e o `saida.html` foram apagados, e o `.env` não vai mais junto no ZIP (no lugar dele tem o `.env.example`).

**O que eu faria a seguir, nesta ordem**
1. Pedir o acesso ao TOTVS para a TI e testar no modo `real`.
2. Terminar a fase 5, trocando as telas pelas versões completas.
3. Combinar com a coordenação o que o professor vai fazer.
4. Colocar um disco persistente ou um banco de dados. Isso resolve o item 4 e destrava quase todas as melhorias planejadas.
5. Guardar os arquivos enviados num serviço próprio, como S3 ou Cloudinary, pra `uploads/` parar de quebrar.

---

## 15. Glossário

| Termo | O que quer dizer |
| --- | --- |
| Egresso | Ex-aluno formado na FSA |
| Vaga vigente | Vaga Ativa e dentro da data de validade (`expiresAt`) |
| Origem | De onde a vaga veio: `faculdade` (cadastrada pela FSA) ou `egresso` |
| Fila de e-mails | Lista de e-mails esperando para sair, enviados aos poucos |
| Tick | Rotina leve que roda no fim das páginas e faz o trabalho agendado |
| Bolinha | Contador vermelho de pendências em cima de um ícone do menu |
| Vaga sugerida | Vaga externa (do Adzuna) esperando a aprovação do admin |
| TCE | Termo de Compromisso de Estágio |
| Perfil | O tipo de usuário (aluno, aluno restrito, professor, egresso, admin, admin de egressos), definido no `perfis.php` |
| Recurso | Uma permissão dentro das telas, como `candidatar` ou `ia_perfil`, consultada com `perfilPode()` |
| TOTVS | O sistema acadêmico da FSA, de onde vêm os dados oficiais dos alunos |
| Modo teste | Jeito de rodar a integração TOTVS com um arquivo de exemplo, sem precisar do TOTVS |

---

*Qualquer dúvida que não estiver aqui, provavelmente a resposta está nos comentários do topo de cada arquivo. Tentei deixar todos eles explicando o que o arquivo faz e porque ele existe.*
