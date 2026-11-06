# Versão 17

Este zip é o projeto **sem as pastas `data/` e `uploads/`** (para não apagar os dados de vocês) e sem o `.env` e a `.git`.
Pode copiar tudo por cima da sua pasta, aceitando substituir.

Se ainda não fez: `composer require dompdf/dompdf:^3.1` (PDF do currículo com texto de verdade).

## O que mudou
1. **Erro do Meu Perfil corrigido** (Parse error na linha 365).
2. **Foto dos avisos do Início** preenche o quadro inteiro. Clique na foto para ver a imagem original inteira.
3. **Logo da FSA** branca e sem fundo no menu lateral e na tela de login.
4. **Currículo**: as mudanças da versão 16 que não tinham entrado (modelos em uma coluna, títulos padrão, PDF com texto) e espaçamentos padronizados.
5. **Perfis:**
   - **Professor:** publica vagas (Publicar Vaga → vai para a aprovação em Admin Vagas, com a etiqueta "Professor") e é o **mentor** na Mentoria.
   - **Egresso:** só divulga vagas (Divulgar Vaga e Meu Perfil de Egresso). Não tem mais Vagas nem Mentoria.
   - **Coordenação de Estágios (admin):** tudo de estágios + **Mentoria (Admin)**. Não vê nada de egressos.
   - **Coordenação de Egressos:** só Gestão de Egressos e Relatórios de Egressos.
6. **Aluno restrito:** nada de IA (perfil por competências e currículo).

## Atenção
- Os mentores aprovados que eram egressos deixam de aparecer para os alunos. Os professores precisam se cadastrar em Mentoria > Minha mentoria.
- A Mentoria (Admin) passou para a Coordenação de Estágios. Para voltar para a Coordenação de Egressos, troque o recurso `gestao_mentoria` e as páginas `admin-mentoria*.php` de perfil em `includes/perfis.php`.
