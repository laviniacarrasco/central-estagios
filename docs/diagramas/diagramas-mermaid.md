# Diagramas da Central de Estágios em Mermaid

Use este arquivo se o seu Miro tiver a opção de criar diagrama a partir de código Mermaid: copie só o bloco de código de cada diagrama. Se não tiver, use as imagens PNG.

## 0. Visão geral

```mermaid
flowchart LR
  AL[Alunos<br/>vagas, currículo, documentos] --> C((Central de Estágios))
  PR[Professores<br/>consulta] --> C
  EG[Egressos<br/>vagas e mentoria] --> C
  FU[Funcionários<br/>coordenações] --> C
  C <--> GM[Gmail API]
  C <--> FL[App Flask Perfil]
  C <--> AD[Adzuna + Gemini]
  C -. aguardando acesso .-> TV[TOTVS]
  GH[GitHub] -- deploy --> C
```

## 1. Arquitetura em camadas

```mermaid
flowchart TB
  subgraph USU[Usuários]
    A[Alunos<br/>aluno · aluno restrito]
    P[Professores<br/>professor]
    E[Egressos<br/>egresso]
    F[Funcionários<br/>coordenação · admin de egressos]
  end
  A & P & E & F --> NAV[Navegador<br/>login por CPF e senha]
  subgraph RENDER[Render · Docker · PHP 8.2]
    subgraph C1[1 · Apresentação]
      HDR[header.php<br/>menu montado pelo perfil]
      PAG[Páginas *.php<br/>aluno · egresso · gestão]
    end
    subgraph C2[2 · Acesso]
      CFG[config.php<br/>checkAuth]
      PER[perfis.php<br/>páginas · menu · recursos]
    end
    subgraph C3[3 · Módulos]
      MOD[vagas · egressos · mentoria · matching<br/>alertas · avisos · notificacoes<br/>course · relatorios · melhorias]
      INT[vagas-externas · perfil_api · GmailMailer]
    end
    subgraph C4[4 · Dados]
      DAT[loadData / saveData]
      JS[(data/*.json)]
      US[(data/users/id/)]
      UP[(uploads/)]
    end
  end
  NAV --> CFG --> PER --> HDR & PAG
  PAG --> MOD --> DAT --> JS & US & UP
  PAG --> INT
  INT <--> GM[Gmail API]
  INT <--> FL[App Flask<br/>Perfil por Competências]
  INT <--> AD[Adzuna]
  INT <--> GE[Google Gemini]
  INT -. aguardando acesso .-> TV[TOTVS<br/>sistema acadêmico]
  CRON[cron-job.org] --> PAG
  GH[GitHub] -- push = deploy --> RENDER
```

## 2. Divisão por perfis

```mermaid
flowchart LR
  subgraph ALUNOS
    AL[Aluno] --> AL1[Início · Perfil com IA · Currículo · Documentos<br/>Vagas · Minhas Vagas · Vagas para Você<br/>Assinaturas · Dicas · Mentoria · Solicitar Vaga]
    AR[Aluno restrito] --> AR1[Início · Perfil sem IA · Currículo · Documentos<br/>Vagas e Vagas para Você sem egressos<br/>Minhas Vagas · Dicas sem IA]
  end
  subgraph PROFESSORES
    PR[Professor] --> PR1[Início · Vagas consulta<br/>Vagas de Egressos · Dicas]
  end
  subgraph EGRESSOS
    EG[Egresso] --> EG1[Vagas de Egressos · Divulgar Vaga<br/>Perfil de Egresso · Mentoria]
  end
  subgraph FUNCIONÁRIOS
    CO[Coordenação de Estágios] --> CO1[Admin Vagas · Vagas Sugeridas · Assinaturas<br/>Posts · Dashboard · Funil · Importar Alunos · Integração TOTVS · Alertas]
    CE[Coordenação de Egressos] --> CE1[Tudo da Coordenação +<br/>Painel de Egressos · Mentoria Admin · Relatórios]
  end
```

## 3. Login e permissão

```mermaid
flowchart LR
  A[Usuário informa CPF e senha] --> B[index.php confere no usuarios.json]
  B --> C{Senha certa?}
  C -- não --> X[CPF ou senha inválidos]
  C -- sim --> D[perfilDoUsuario<br/>define o perfil]
  D --> E[Grava o perfil na sessão<br/>vai para a home do perfil]
  E --> F{checkAuth: página está<br/>na lista do perfil?}
  F -- sim --> G[Mostra a tela<br/>menu = perfilMenu]
  F -- não --> H[Volta para a página inicial do perfil]
```

## 4. Fluxo de vagas

```mermaid
flowchart LR
  subgraph Funcionários
    F1[Coordenação cadastra vaga]
    F2{Aprova ou recusa}
  end
  subgraph Egressos
    E1[Egresso divulga vaga]
  end
  subgraph Externo
    X1[Adzuna: estágio em SP e ABC]
  end
  subgraph Alunos
    A0[Aluno sugere vaga]
    A1[Vagas e Vagas para Você]
    A2[Candidatar-se: abre o site da empresa]
    A3[Minhas Vagas: atualiza o andamento]
  end
  subgraph Sistema
    S1[Fila de aprovação]
    S2[Vaga publicada]
    S3[Alertas por e-mail]
  end
  E1 & X1 & A0 --> S1 --> F2
  F2 -- aprova --> S2
  F1 -- publica direto --> S2
  S2 --> S3
  S2 --> A1 --> A2 --> A3
  S2 --> P1[Professor consulta]
```

## 5. Documentos e assinaturas

```mermaid
flowchart LR
  A1[Aluno envia certificado ou TCE] --> S1[Sistema grava e avisa a coordenação]
  S1 --> F1[Coordenação analisa]
  F1 --> F2{Tudo certo?}
  F2 -- sim --> F3[Envia assinado ou aprova as horas]
  F2 -- não --> F4[Marca erro com mensagem]
  F3 --> S2[E-mail e bolinha para o aluno] --> A2[Aluno baixa ou vê as horas]
  F4 --> A3[Aluno corrige e reenvia] --> S1
```

## 6. Mentoria

```mermaid
flowchart LR
  E1[Egresso se cadastra como mentor] --> F1[Coordenação de Egressos aprova]
  F1 -- mentor visível --> A1[Aluno envia pedido<br/>máx. 3 em andamento]
  A1 --> E2{Mentor aceita?<br/>máx. 5 aguardando}
  E2 -- sim --> A2[Contato liberado]
  E2 -- não --> A4[Pedido recusado]
  A2 --> A3[Conclui ou cancela]
```

## 7. Integração TOTVS

```mermaid
flowchart LR
  subgraph TI[TI da FSA - pendente]
    T1[Cria usuário de leitura<br/>e consulta SQL] -.-> T2[Passa endereço, usuário,<br/>senha e códigos]
  end
  subgraph Fonte[Fonte dos dados]
    D1[HOJE: totvs_exemplo.json<br/>modo teste]
    D2[DEPOIS: API do TOTVS<br/>modo real]
  end
  subgraph Central[Central - includes/integracoes/]
    C1[totvs-cliente<br/>busca] --> C2[totvs-mapeamento<br/>traduz] --> C3[totvs-sincronizar<br/>compara pelo RA/CPF]
    C4[Grava: atualiza ou cria<br/>nunca apaga, backup antes]
  end
  subgraph Func[Funcionários]
    F1[Simular] --> F2{Relatório ok?} -- sim --> F3[Sincronizar agora<br/>ou agendador diário]
  end
  T2 -.-> D2
  D1 -- hoje --> C1
  D2 -. depois .-> C1
  F1 --> C3 --> F2
  F3 --> C4
```
