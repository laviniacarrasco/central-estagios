# Versão 15 (Parte 1 do novo design)

Este zip é o **projeto completo** (sem o `.env` e sem a `.git`). Copie o conteúdo da pasta `central-estagios` por cima da sua, aceitando substituir.

## O que mudou
1. **Design novo em todas as páginas** (`includes/header.php`): fonte Inter, cartões com borda suave, botões e campos padronizados, tabelas mais limpas, menu lateral com o item da página atual destacado e o nome do item ao passar o mouse, topo com menu do avatar (Meu perfil, Configurações, Sair). Classes prontas para as próximas telas: `fsa-card`, `fsa-btn`, `fsa-input`, `fsa-chip`, `fsa-switch`.
2. **Caixa de Configurações** (`includes/configuracoes-modal.php` + `configuracoes.php`): a engrenagem abre uma janela por cima de qualquer página e salva sem recarregar. Abas: Alertas de vagas, Disponibilidade e links, Conta e privacidade.
3. **Meu Perfil** (`profile.php`): só os dados principais, sem porcentagem. O Perfil por Competências saiu daqui.
4. **Dicas de Carreira > Meu perfil**: o resumo do parecer da IA aparece formatado logo abaixo do gráfico de radar, com "Ler o parecer completo".
5. **Início** (`dashboard.php`): números, vagas que combinam, primeiros passos, o que fazer agora, avisos e atalhos. A porcentagem é a do **currículo**.
6. **Currículo** (`curriculum.php` + `includes/curriculo-ia.php`): porcentagem com o que falta e **Avaliação da IA** (nota, pontos fortes, o que melhorar, palavras-chave).
7. **Vagas para Você** (`vagas-para-voce.php`): botão **Analisar com IA**, que dá nota e motivo para cada vaga. Sem IA, usa a comparação por regras.
8. **IA (Gemini)** (`includes/ia-gemini.php` + `ia-curriculo.php`): o currículo vai **sem nome, e-mail, telefone e CPF**; o aluno concorda na primeira vez (e pode retirar nas Configurações); resultado guardado; limite diário `IA_LIMITE_DIA`.

## Para testar
1. Clique na engrenagem em qualquer página: a caixa abre por cima e, ao salvar, você continua na mesma tela.
2. Abra o Currículo e clique em **Avaliar com IA** (aceite o aviso). A avaliação aparece no topo.
3. Abra **Vagas para Você** e clique em **Analisar com IA**.
4. Abra **Dicas de Carreira > Meu perfil** e confira o resumo abaixo do gráfico.
5. Entre como aluno restrito: não aparece nada de IA, só a porcentagem do currículo.
