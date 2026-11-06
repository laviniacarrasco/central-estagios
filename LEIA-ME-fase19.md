# Versão 19 (inclui a Parte 3)

Projeto sem `data/` e `uploads/`: pode copiar tudo por cima.

## O que mudou
1. **A tela não recarrega mais**:
   - Currículo: trocar modelo, cor, idioma, foto, Salvar e Cancelar atualizam só o currículo, sem voltar ao topo.
   - Avaliar com IA e Analisar com IA atualizam só o quadro.
   - Filtros e buscas (Vagas, Mentoria, Painéis, Relatórios de Egressos...) e "Salvar vaga" atualizam sem recarregar.
   - Para uma tela nova: formulário de busca (GET) já funciona sozinho; formulário POST precisa de `data-parcial`.
2. **Currículo com "Mostrar tudo / Mostrar menos"**:
   - "Modelo, cor, idioma e foto" recolhe (mostra um resumo de uma linha);
   - "O que falta" mostra 2 itens e "Ver mais";
   - a avaliação da IA mostra nota e resumo, e "Ver a análise completa" abre o resto.
   A escolha fica guardada no navegador.
3. **Meu Perfil:** a faixa azul não cobre mais o nome.
4. **Parte 3 (egresso e coordenação):** cores, títulos e botões padronizados em Divulgar Vaga, Perfil de Egresso, Admin Vagas, Vagas Sugeridas, Admin Assinaturas, Posts, TOTVS, Banco de dados, Alertas, Gestão de Egressos, Mentoria (Admin), Relatórios de Egressos e Empresas.
