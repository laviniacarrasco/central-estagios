# Versão 16 (Parte 2 do novo design + currículo ATS)

Este zip é o **projeto completo** (sem o `.env` e sem a `.git`). Copie o conteúdo da pasta `central-estagios` por cima da sua, aceitando substituir.

## Passo obrigatório (uma vez só): instalar a biblioteca do PDF
No terminal, dentro da pasta do projeto:
```
composer require dompdf/dompdf:^3.1
```
Isso atualiza o `composer.json` e o `composer.lock`. Suba os dois no git (o Render instala sozinho).
Enquanto a biblioteca não estiver instalada, o botão "Baixar PDF" abre a versão para impressão e o navegador oferece "Salvar como PDF". O resultado também é um PDF com texto.

## O que mudou
1. **Avisos do Início:** a foto aparece inteira (sem corte), no mesmo tamanho, com o fundo desfocado da própria imagem.
2. **Currículo:**
   - os três modelos ficaram mais sóbrios e em **uma coluna** (o "Moderno" não tem mais a barra lateral);
   - títulos padrão ("Experiência Profissional", "Habilidades", "Cursos e Certificações");
   - **PDF novo** (`curriculo-pdf.php`): texto de verdade (os ATS e as IAs conseguem ler), nenhum bloco cortado entre páginas, margens de folha A4, links do LinkedIn/GitHub/portfólio, descrições em tópicos;
   - **IA avalia a leitura por ATS:** nota de 0 a 100 e ajustes para passar nos filtros.
3. **Login** novo (tela dividida, mostrar senha, explicação de primeiro acesso) e **Recuperar senha** no mesmo visual.
4. **Vagas, Minhas Vagas, Documentos, Assinaturas, Mentoria, Solicitar Vaga e Dicas:** cores e botões padronizados (o visual geral vem do topo da Parte 1).
5. **Dockerfile:** extensão GD (foto no PDF).

## Para testar
1. Início: a imagem do aviso aparece inteira.
2. Currículo > Baixar PDF. No PDF, selecione o texto com o mouse: tem que dar para copiar. Confira se nenhum bloco ficou cortado entre as páginas.
3. `curriculo-pdf.php?ver=1` mostra a versão para conferir na tela.
4. Currículo > Avaliar de novo: aparece o quadro "Leitura por robôs de recrutamento (ATS)".
5. Saia e veja a tela de login nova.
