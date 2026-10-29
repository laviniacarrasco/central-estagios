# Integração com o TOTVS

Este documento explica a integração da Central com o sistema acadêmico da FSA (TOTVS). A ideia é simples: os dados acadêmicos dos alunos param de ser digitados ou importados à mão e passam a vir do TOTVS.

> **Situação hoje:** ainda não temos acesso ao banco nem à API do TOTVS. Por isso a integração roda só no **modo teste**, com o arquivo `data/totvs_exemplo.json`. Quando a TI liberar o acesso, basta preencher as variáveis da seção 5 e trocar `TOTVS_MODO` para `real`. Não precisa mexer no código.
>
> A integração foi pensada para a **API** do TOTVS (consulta SQL cadastrada no RM), e não para ligar direto no banco de dados. É o caminho que a TI normalmente libera, e é mais seguro, porque o usuário só consegue ler aquela consulta.

## 1. O que a integração faz

Ela busca a lista de alunos no TOTVS e compara com o `data/usuarios.json`:

- **Aluno que já existe** (achado pelo RA ou pelo CPF): atualiza nome, e-mail, curso, período, turno e situação, se algo mudou.
- **Aluno que não existe:** cria o cadastro, sem senha. Ele cria a senha pelo "Esqueci minha senha", que pede o CPF e o e-mail.
- **Aluno que sumiu do TOTVS:** não acontece nada. A integração **nunca apaga ninguém**.

Ela não mexe em admin, egresso nem professor, e nunca mexe na senha de quem já tem cadastro.

## 2. Como está organizada

São quatro arquivos pequenos, cada um com uma tarefa só. Assim, se algo mudar no TOTVS, você sabe exatamente onde mexer.

```
Tela (admin-totvs.php)  ou  agendador (?chave=...)
            │
            ▼
totvs-sincronizar.php   compara com o usuarios.json e grava
            │
     ┌──────┴────────┐
     ▼               ▼
totvs-cliente.php   totvs-mapeamento.php
busca no TOTVS      traduz os campos e os nomes
     │
     ▼
totvs-config.php    lê as variáveis de ambiente
```

| Arquivo | Tarefa | Quando mexer |
| --- | --- | --- |
| `includes/integracoes/totvs-config.php` | Lê as variáveis de ambiente | Se surgir uma configuração nova |
| `includes/integracoes/totvs-cliente.php` | Busca os alunos (no TOTVS ou no arquivo de exemplo) | Se a TI mudar a forma de acesso |
| `includes/integracoes/totvs-mapeamento.php` | Traduz colunas, cursos, turnos e situação | **O mais comum:** coluna com outro nome, curso escrito diferente |
| `includes/integracoes/totvs-sincronizar.php` | Compara e grava, com as regras de segurança | Se mudar uma regra (por exemplo, quais campos atualizar) |
| `admin-totvs.php` | Tela do admin e rotina do agendador | Se mudar a tela |
| `data/totvs_exemplo.json` | Alunos de exemplo para o modo teste | Para testar outros casos |

Todos os arquivos estão comentados linha a linha.

## 3. Os três modos

A variável `TOTVS_MODO` escolhe o modo:

| Modo | O que faz | Para quê |
| --- | --- | --- |
| `desligado` (padrão) | Nada | Enquanto a TI não libera o acesso |
| `teste` | Lê `data/totvs_exemplo.json` | Testar a tela e as regras sem o TOTVS |
| `real` | Chama a API do TOTVS | Uso de verdade |

Comece pelo **teste**, clique em **Simular** e veja o resultado. O arquivo de exemplo tem quatro casos: um aluno que muda de período (a Lavinia), um aluno novo, um aluno com curso que não existe na Central e uma linha sem RA e sem CPF. Não clique em "Sincronizar" no modo teste, porque os alunos de exemplo entram no cadastro de verdade.

## 4. O que pedir para a TI da FSA

1. **Um usuário de integração** no TOTVS, só com permissão de leitura.
2. **Uma consulta SQL cadastrada no TOTVS RM** que devolva os alunos ativos com estas colunas:

| Coluna | Exemplo | Obrigatória? |
| --- | --- | --- |
| `RA` | 760876 | Sim (ou o CPF) |
| `CPF` | 00000000001 | Sim, para criar o login de aluno novo |
| `NOME` | Lavinia | Sim |
| `EMAIL` | lavinia@graduacao.fsa.br | Sim, para o aluno novo criar a senha |
| `CURSO` | Ciência de Dados e Inteligência Artificial | Sim |
| `PERIODO` | 5 | Sim |
| `TURNO` | N | Não |
| `SITUACAO` | ATIVO | Não |

3. **O endereço do servidor**, o **código da consulta**, o **código da coligada** e o **código do sistema**.

O caminho padrão usado é o da consulta SQL do TOTVS RM (`/api/framework/v1/consultaSQLServer/RealizaConsulta/...`). Se a TI usar outra rota, é só mudar a variável `TOTVS_CAMINHO`, sem mexer no código. Se as colunas vierem com outros nomes, ajuste a `totvsMapaCampos()`.

## 5. Variáveis de ambiente

No `.env` (computador) e em **Render > Environment** (site publicado):

```
TOTVS_MODO=teste
TOTVS_URL=https://endereco-do-totvs.fsa.br
TOTVS_USUARIO=usuario.integracao
TOTVS_SENHA=senha-do-usuario
TOTVS_CODSENTENCA=CENTRAL.ALUNOS
TOTVS_CODCOLIGADA=1
TOTVS_CODSISTEMA=S
```

Os valores acima são só exemplos. Os verdadeiros vêm da TI.

## 6. Como usar

**Pela tela:** entre como Coordenação e clique no ícone **Integração TOTVS** no menu.
1. **Simular:** mostra quantos alunos seriam criados e atualizados e o que mudaria em cada um. Não grava nada.
2. **Sincronizar agora:** grava. Antes, guarda uma cópia do cadastro em `data/usuarios_backup_totvs.json`.

**Automático:** no cron-job.org, agende uma chamada por dia para:

```
https://central-estagios-fsa.onrender.com/admin-totvs.php?chave=SUA_CHAVE
```

A chave é a mesma do resumo semanal (`ALERTAS_CRON_KEY`).

## 7. Se algo der errado

| Mensagem | O que fazer |
| --- | --- |
| "A integração está desligada" | Defina `TOTVS_MODO` |
| "Faltam variáveis de ambiente" | Preencha as que a tela lista |
| "O TOTVS recusou o usuário ou a senha" | Confira `TOTVS_USUARIO` e `TOTVS_SENHA` com a TI |
| "Endereço não encontrado (HTTP 404)" | Confira `TOTVS_URL`, `TOTVS_CAMINHO` e o código da consulta |
| "Não foi possível conectar" | O servidor do TOTVS pode não aceitar acesso de fora da rede da FSA. Peça para a TI liberar o acesso do Render |
| "Cursos que não estão na lista da Central" | Inclua o nome em `totvsMapaCursos()` |

Para desfazer uma sincronização, troque o `data/usuarios.json` pelo `data/usuarios_backup_totvs.json`.

## 8. Limites conhecidos

- O Render gratuito não guarda os arquivos gravados (ver a documentação geral). Os alunos atualizados pelo TOTVS podem sumir num reinício até haver disco persistente ou banco de dados. A sincronização diária ajuda, porque refaz tudo.
- A comparação é feita com o cadastro inteiro na memória. Para alguns milhares de alunos, não há problema.
- Se duas sincronizações rodarem ao mesmo tempo, uma pode sobrescrever a outra. Evite clicar em "Sincronizar" no mesmo horário do agendador.
