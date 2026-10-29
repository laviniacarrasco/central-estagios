# Guia passo a passo: como mexer na Central de Estágios

> Versão 7 (outubro de 2026). Para entender como o sistema é montado, veja o `docs/ARQUITETURA.md`.
> Aqui estão as tarefas do dia a dia, em ordem: o que abrir, o que mudar e como testar.

**Regra de ouro:** mude no seu computador, teste no seu computador e só depois faça o `git push`. O que é feito direto no site publicado pode sumir (veja a parte 1.5).

---

## Parte 1. Preparar e publicar

### 1.1 Primeira vez no computador

1. Instale o **PHP 8.2** e o **Composer**.
2. Ative a extensão `curl` no `php.ini`: tire o `;` da frente da linha `extension=curl`.
3. Abra a pasta `central-estagios` no VS Code.
4. Copie o `.env.example` para um arquivo chamado `.env` e preencha as chaves (veja a parte 1.4).
5. No terminal (**Terminal > New Terminal**), rode:
   ```
   composer install
   php -S localhost:8000 router.php
   ```
6. Abra `http://localhost:8000` no navegador e entre com CPF e senha.

**Erro de SSL no Windows** (`SSL certificate problem`): baixe o arquivo `cacert.pem` do site do cURL e salve em `includes/cacert.pem`. O código já procura esse arquivo sozinho, e o `.gitignore` impede que ele vá para o Git.

### 1.2 Rodar no dia a dia

```
php -S localhost:8000 router.php
```
Deixe esse terminal aberto. Para parar o servidor, aperte `Ctrl + C`. **Mudou o `.env`? Pare o servidor e rode de novo**, para ele ler o arquivo atualizado.

### 1.3 Publicar uma mudança no site

1. Confira o que vai subir:
   ```
   git status
   ```
   **O `.env` e a pasta `vendor/` não podem aparecer.** Se aparecerem, pare e confira o `.gitignore` (parte 9.1).
2. Envie:
   ```
   git add .
   git commit -m "O que mudou, em poucas palavras"
   git push
   ```
3. No Render, abra o serviço e vá em **Events**. Espere o deploy terminar com **Live** (leva alguns minutos).
4. Abra o site e teste o que você mudou.

### 1.4 Variáveis de ambiente (`.env` e Render)

| Variável | Para que serve | Precisa? |
| --- | --- | --- |
| `APP_URL` | Endereço usado nos links dos e-mails | Recomendada |
| `GMAIL_CLIENT_ID`, `GMAIL_CLIENT_SECRET`, `GMAIL_REFRESH_TOKEN`, `GMAIL_SENDER_EMAIL` | Enviar e-mails | Só para e-mail |
| `ALERTAS_CRON_KEY` | Protege as URLs das rotinas automáticas | Para o agendador |
| `VAGAS_FONTE` | `gupy` (padrão), `adzuna`, `gupy,adzuna` ou `nenhuma` | Não (sem ela, usa a Gupy) |
| `ADZUNA_APP_ID`, `ADZUNA_APP_KEY` | Só se usar o Adzuna (pago) | Não |
| `GEMINI_API_KEY`, `GEMINI_MODEL` | IA que escolhe os cursos das vagas externas | Não |
| `TOTVS_MODO` e `TOTVS_*` | Integração com o TOTVS | Não (hoje `teste`) |
| `DADOS_DRIVER`, `DADOS_DB_*` | Guardar os dados num banco | Não (padrão `json`) |

**Para mudar no site publicado:** Render > serviço > **Environment** > **Add Environment Variable** > **Save Changes**. O Render reinicia sozinho.

**Para conferir se o `.env` foi lido:** abra `http://localhost:8000/teste-env.php`. Ele mostra só se cada variável está preenchida, sem mostrar o valor.

### 1.5 O que some no Render gratuito

| Fica | Pode sumir |
| --- | --- |
| Tudo o que está no Git (o código e a pasta `data/` do seu computador) | Tudo o que é gravado **direto no site**: vagas aprovadas lá, candidaturas, cadastros, fotos e PDFs |

Na prática:
- **Aprove as vagas no seu computador** e depois faça o `git push`. Aprovações feitas no site publicado podem ser apagadas no próximo deploy.
- Fotos e PDFs (`uploads/`) não vão para o Git, então aparecem quebrados no Render.
- A solução definitiva é o banco de dados (parte 8.3).

---

## Parte 2. Usuários e acessos

### 2.1 Criar um usuário

1. Abra `data/usuarios.json`.
2. Copie um usuário parecido e cole no fim da lista (cuidado com as vírgulas).
3. Troque:
   - `id`: um número que ainda não exista;
   - `cpf`: só números;
   - `senha`: pode ser em texto, ela vira hash no primeiro login;
   - o perfil, conforme a tabela abaixo.

| Perfil | O que colocar |
| --- | --- |
| Aluno | sem `tipo` e `"is_admin": false` |
| Aluno restrito | `"tipo": "aluno_restrito"` |
| Professor | `"tipo": "professor"` |
| Egresso | `"tipo": "egresso"` |
| Coordenação de Estágios | `"is_admin": true` |
| Coordenação de Egressos | `"is_admin": true` e `"tipo": "admin_egressos"` |

4. Se a pessoa já estava logada, ela precisa sair e entrar de novo.

**Cuidado:** um JSON com vírgula sobrando ou faltando quebra o login de todo mundo. Depois de editar, veja se o VS Code mostra algum sublinhado vermelho no arquivo.

### 2.2 Cadastrar muitos alunos de uma vez

Pela tela **Cadastro de Alunos (TOTVS)**. A importação por planilha foi desativada. Hoje a tela roda em `TOTVS_MODO=teste` e lê o `data/totvs_exemplo.json`. O passo a passo para ligar de verdade está no `docs/INTEGRACAO-TOTVS.md`.

### 2.3 Liberar uma página para um perfil

1. Abra `includes/perfis.php` e procure o perfil no `perfisRegistro()`.
2. Coloque o nome da página na lista `paginas` do perfil.
3. Se ela deve aparecer no menu, coloque também na lista `menu`:
   ```php
   perfilItemMenu('minha-pagina.php', 'Nome no menu', 'fa-star'),
   ```
   O terceiro valor é o ícone do Font Awesome (procure em fontawesome.com, versão gratuita).
4. Saia e entre de novo para ver o menu atualizado.

Para mudar o que **todos os funcionários** veem, mexa nas listas `$paginasCoordenacao` e `$menuCoordenacao`, que valem para os dois perfis de coordenação.

### 2.4 Esconder uma parte da tela de um perfil

1. Invente um nome de recurso, por exemplo `ver_bolsa`.
2. Coloque esse nome em `recursos` de cada perfil que pode ver.
3. Na tela:
   ```php
   <?php if (perfilPode('ver_bolsa')): ?>
       ... parte que só esses perfis veem ...
   <?php endif; ?>
   ```

### 2.5 Criar um perfil novo

1. No `perfisRegistro()`, copie um perfil parecido e mude o nome, o `rotulo`, as `paginas`, o `menu` e os `recursos`.
2. Na `perfilDoUsuario()`, ensine o sistema a reconhecer o `tipo` novo.
3. Na `perfisGrupos()`, coloque o perfil no grupo certo.
4. Crie um usuário de teste com o `tipo` novo (parte 2.1) e teste todas as páginas.

---

## Parte 3. Telas

### 3.1 Criar uma página nova

1. Crie o arquivo na raiz, por exemplo `minha-pagina.php`:
   ```php
   <?php
   require_once 'includes/config.php';
   checkAuth();                      // obrigatório: login + permissão do perfil

   $pageTitle = 'Minha Página';
   $dados = loadData('platform_jobs');   // exemplo de leitura

   include 'includes/header.php';
   ?>
   <main class="p-6">
       <h1 class="text-2xl font-bold">Minha Página</h1>
   </main>
   <?php include 'includes/footer.php'; ?>
   ```
2. Libere a página nos perfis certos (parte 2.3). **Sem isso, ninguém abre.**
3. Teste logado com cada perfil que deve ver a página, e com um que não deve.

**Página só de funcionários:** use `checkAdmin()` no lugar de `checkAuth()`.

Antes de criar, abra uma tela parecida (por exemplo, `minhas-vagas.php`) e copie a estrutura dela, para manter o mesmo visual.

### 3.2 Mudar um texto ou o visual

1. Descubra o arquivo pelo endereço do navegador (por exemplo, `jobs.php`).
2. No VS Code, use **Ctrl + Shift + F** e procure um trecho do texto que aparece na tela.
3. Troque o texto. O visual é feito com classes do Tailwind (`bg-[#4A9FCA]`, `rounded-xl`, `text-sm` etc.).
4. Salve e recarregue o navegador (`Ctrl + F5`).

O azul da FSA usado no projeto é o `#4A9FCA`. Para mudar o menu, o topo ou as bolinhas de aviso, o arquivo é o `includes/header.php`.

### 3.3 Adicionar ou mudar um curso

1. Edite a `getFsaCourses()` no `includes/config.php`. Ela é a única lista de cursos do sistema.
2. Se o curso tiver estágio obrigatório ou horas complementares, ajuste também o `data/course.json`.
3. Para as vagas externas acertarem o curso sem a IA, acrescente as palavras-chave do curso na `vexCursosPorPalavras()` (`includes/vagas-externas.php`). O nome tem que ser exatamente igual ao da `getFsaCourses()`.

---

## Parte 4. Vagas

### 4.1 Cadastrar uma vaga da FSA

Menu **Admin Vagas** > preencher > salvar. A vaga fica ativa por 30 dias e depois sai sozinha.

### 4.2 Vagas externas (Gupy): uso diário

1. Menu **Vagas Sugeridas (externas)** > **Buscar vagas agora**. A busca leva até um minuto.
2. A mensagem do topo diz quantas vagas entraram, quantas eram repetidas e quantas o filtro descartou.
3. Na lista **Pendentes**, confira cada vaga e clique em **Aprovar** ou **Rejeitar**.
4. A vaga aprovada vai para Vagas, com "Fonte: Gupy" e o link original, e dispara os alertas dos alunos do curso.
5. **Faça isso no seu computador e depois o `git push`** (parte 1.5).

| Mensagem | O que significa | O que fazer |
| --- | --- | --- |
| Verde, "X nova(s)" | Deu certo | Aprovar ou rejeitar na lista Pendentes |
| Verde, "0 nova(s), X repetida(s)" | Essas vagas já estão na fila | Nada |
| Verde, "0 nova(s), 0 repetida(s), X descartada(s)" | A Gupy respondeu, mas o filtro tirou tudo | Revisar o filtro (parte 4.4) |
| Vermelha, "A Gupy não respondeu..." | O endereço mudou ou houve bloqueio | Parte 4.5 |

### 4.3 Testar a Gupy pelo terminal

```
php teste-gupy.php
```
Mostra cada consulta, quantas vagas vieram, quantas são de SP, do ABC ou remotas, e as 10 primeiras. **Não grava nada.**

### 4.4 Mudar o que a busca traz

Tudo fica no `includes/vagas-externas.php`:

| Quero... | Onde mexer |
| --- | --- |
| Mudar as cidades buscadas | A lista de cidades dentro da `vexBuscarGupy()`. Escreva como a Gupy escreve, **com acento** ("São Paulo", e não "Sao Paulo") |
| Mudar as cidades aceitas no filtro | A `vexCidadesAceitas()` (aqui é **sem acento** e em minúsculas) |
| Buscar outro termo (ex.: "jovem aprendiz") | Acrescente uma linha em `$consultas`, na `vexBuscarGupy()`, copiando o formato das que já existem |
| Tirar jovem aprendiz | Apague `'vacancy_type_apprentice'` da lista `$tiposOk`, na `vexNormalizarGupy()` |
| Descartar outras palavras no título | A linha com `senior|\bsr\b|pleno|gerente...`, na `vexImportar()` |
| Mudar a pausa entre consultas | `usleep(1200000)` na `vexBuscarGupy()` (em microssegundos: 1200000 = 1,2 s). Não diminua muito, para não ser bloqueado |

Depois de mexer, rode `php teste-gupy.php` para conferir.

### 4.5 Se a Gupy parar de responder

1. Rode `php teste-gupy.php` e veja o código de erro.
2. Abra `https://portal.gupy.io/api/job-search/jobs?jobName=estágio&limit=5` no navegador.
   - Se aparecer um texto começando com `{"data":[`, o endereço funciona. O problema é da sua rede ou do servidor.
   - Se der erro 404, a Gupy mudou o endereço. Abra `portal.gupy.io`, aperte F12, vá em **Network (Rede)**, faça uma busca e procure a chamada que traz as vagas. Troque o endereço na lista `$bases`, na `vexGupyGet()`.
3. Enquanto isso, a Central continua funcionando. Para desligar a busca: `VAGAS_FONTE=nenhuma`.

### 4.6 Busca automática todo dia

1. Confira se a `ALERTAS_CRON_KEY` está preenchida no Render (uma chave longa e aleatória).
2. Crie uma conta grátis no **cron-job.org**.
3. Clique em **Create cronjob**:
   - URL: `https://central-estagios-fsa.onrender.com/importar-vagas-cron.php?chave=SUA_CHAVE`
   - Frequência: uma vez por dia (por exemplo, às 7h).
   - Em **Advanced**, aumente o tempo limite (timeout) para o máximo, porque a busca demora.
4. Salve e use **Test run** para conferir.

**Atenção:** a busca automática grava no site publicado, então as vagas que ela encontra também podem sumir no próximo deploy (parte 1.5).

### 4.7 Trocar a fonte das vagas externas

| Quero | No `.env` e no Render |
| --- | --- |
| Só a Gupy | `VAGAS_FONTE=gupy` (ou nada) |
| Só o Adzuna (pago) | `VAGAS_FONTE=adzuna` + `ADZUNA_APP_ID` + `ADZUNA_APP_KEY` |
| As duas | `VAGAS_FONTE=gupy,adzuna` |
| Desligar | `VAGAS_FONTE=nenhuma` |

### 4.8 Limpar a fila

Em Vagas Sugeridas, o botão de limpar o histórico apaga as vagas aprovadas e rejeitadas e mantém as pendentes. Para começar do zero, apague o `data/vagas_sugeridas.json` (ele é criado de novo na próxima busca).

---

## Parte 5. E-mails e alertas

### 5.1 Configurar o Gmail

1. Preencha as quatro variáveis `GMAIL_*` no `.env` e no Render.
2. Teste: entre como aluno > **Perfil** > **Meus Alertas** > **Enviar um resumo de teste agora**.
3. Acompanhe em **Alertas e E-mails** (menu dos funcionários).

**Se os e-mails pararem** com erro de token: gere um novo `GMAIL_REFRESH_TOKEN` no Google Cloud e troque no `.env` e no Render.

### 5.2 Resumo semanal automático

No cron-job.org, crie um agendamento para `https://central-estagios-fsa.onrender.com/resumo-semanal.php?chave=SUA_CHAVE`, a cada 15 ou 30 minutos.

**Cuidado:** com o Gmail ligado, o resumo vai para todos os alunos que ativaram a opção. Não ligue enquanto o site estiver em teste.

---

## Parte 6. Outras áreas

| Quero... | Onde |
| --- | --- |
| Mudar o que o aluno restrito vê | Perfil `aluno_restrito` no `includes/perfis.php` |
| Definir o papel do professor | Perfil `professor` no `includes/perfis.php` (hoje ele só consulta) |
| Mudar os assuntos da mentoria | `mentoriaAssuntos()` no `includes/mentoria.php` |
| Mudar o limite de alunos por mentor | `mentoriaMax()` no `includes/mentoria.php` |
| Mudar as situações profissionais do egresso | `egressoStatusOptions()` no `includes/egressos.php` |
| Mudar a pontuação de "Vagas para Você" | `matchPontuar()` no `includes/matching.php` |
| Mudar as trilhas e os modelos das Dicas | `includes/dicas-extras.php` |
| Criar um painel ou uma pasta | Menu **Painéis** > **Novo painel** ou **Nova pasta** (veja o `docs/PAINEIS-BI.md`) |
| Criar uma fonte ou um modelo novo nos Painéis | `includes/bi.php` (veja o `docs/PAINEIS-BI.md`) |
| Mudar os relatórios de egressos | `includes/relatorios.php` |

---

## Parte 7. Problemas comuns

| Sintoma | Causa provável | Solução |
| --- | --- | --- |
| Tela branca ou erro 500 | Erro de PHP | Olhe o terminal do `php -S` (no computador) ou **Logs** (no Render) |
| Ninguém consegue entrar | `usuarios.json` com erro de vírgula ou aspas | Veja o que mudou com `git diff data/usuarios.json` e desfaça |
| A página volta para o Início | A página não está na lista do perfil | Parte 2.3 |
| O menu não mudou | Sessão antiga | Sair e entrar de novo |
| `php não é reconhecido` | PHP fora do PATH do Windows | Reinstale o PHP ou ajuste o PATH |
| `Call to undefined function curl_init` | Extensão `curl` desligada | Parte 1.1, passo 2 |
| `SSL certificate problem` | Falta o certificado no Windows | `includes/cacert.pem` (parte 1.1) |
| Sublinhado amarelo no VS Code ("Use of unknown class") | Aviso do editor, não é erro | Pode ignorar |
| O primeiro acesso ao site demora | O Render gratuito dorme | Normal |
| Um dado sumiu do site | Disco não permanente do Render | Parte 1.5 |
| Botão "Buscar vagas agora" cinza | Busca desligada ou código antigo | Confira a `VAGAS_FONTE` e o último deploy |

---

## Parte 8. Tarefas maiores

### 8.1 Ligar o TOTVS de verdade

Siga o `docs/INTEGRACAO-TOTVS.md`. Em resumo: a TI passa o endereço, o usuário e a consulta; você preenche os `TOTVS_*`, troca para `TOTVS_MODO=real` e testa com **Simular** antes de **Sincronizar**.

### 8.2 Usar a IA para escolher os cursos

Coloque a `GEMINI_API_KEY` no `.env` e no Render. A IA confere até 25 vagas por busca. Sem a chave, os cursos saem pelas palavras-chave. Confira no site do Google o nome do modelo atual e, se precisar, preencha a `GEMINI_MODEL`.

### 8.3 Ligar o banco de dados

Siga o `docs/BANCO-DE-DADOS.md`. Em resumo:
1. Crie um banco (PostgreSQL no Render, por exemplo) e preencha `DADOS_DB_DSN`, `DADOS_DB_USUARIO` e `DADOS_DB_SENHA` no `.env` e no Render.
2. (Opcional) Troque os nomes das tabelas em `includes/banco-tabelas.php` ou com `DADOS_TABELA_<GRUPO>`.
3. Abra o site: na primeira conexão, as tabelas são criadas e os JSON são copiados para o banco.
4. Confira no menu **Banco de dados** e clique em **Proteger senhas (hash)**.

Se o banco cair, o site continua com os JSON e sincroniza quando ele voltar.

---

## Parte 9. Cuidados com o Git e as chaves

### 9.1 O `.gitignore` precisa ter estas linhas

```
.env
.env.*
!.env.example
includes/cacert.pem
/vendor/
/uploads/*
node_modules/
```

Se você copiar outro projeto para dentro da pasta, **confira se ele não trocou o seu `.gitignore`** (isso já aconteceu com o gupy-search).

### 9.2 Nunca envie

- o `.env` (nem no Git, nem em zip para outras pessoas);
- a pasta `.git` em zip;
- prints com as chaves aparecendo.

### 9.3 Se uma chave vazou

1. Gere uma chave nova no serviço (Google Cloud para o Gmail, Google AI Studio para o Gemini).
2. Troque no `.env` e no Render.
3. Se o `.env` chegou a ir para o GitHub, apagar o arquivo não basta, porque ele continua no histórico. A chave precisa ser trocada.

---

## Parte 10. Antes de cada `git push`

- [ ] Testei no computador (`php -S localhost:8000 router.php`).
- [ ] Testei com o perfil que vai usar a mudança.
- [ ] Se mexi nas vagas externas, rodei `php teste-gupy.php`.
- [ ] O `git status` não mostra o `.env` nem o `vendor/`.
- [ ] A mensagem do commit diz o que mudou.
- [ ] Depois do push, o Render terminou com **Live** e eu testei no site.
